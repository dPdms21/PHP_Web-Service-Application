import torch
import torch.nn as nn
import torch.optim as optim
from torch.utils.data import random_split, DataLoader, Subset
from torchvision import datasets, models, transforms
from collections import defaultdict, Counter
from sklearn.metrics import classification_report
import random, json, os
from tqdm import tqdm

# =========================================================
# ⚙️ 설정
# =========================================================
DATA_DIR = "data/HMDB51"
OUTPUT_DIR = "outputs/image_fast_v2"
os.makedirs(OUTPUT_DIR, exist_ok=True)

BATCH_SIZE = 16
SAMPLES_PER_CLASS = 400
EXTRA_SAMPLES = {"Locomotion": 600, "Resting": 600}  # 클래스별 샘플 수 보정
IMG_SIZE = 128
EPOCHS = 8
LR = 8e-5
DEVICE = torch.device("cpu")

# =========================================================
# 🎯 라벨 병합 매핑 (HAR 통합 그룹)
# =========================================================
label_map = {
    'climb': 'Outdoor',
    'climb_stairs': 'Locomotion',
    'walk': 'Locomotion',
    'run': 'Locomotion',
    'sit': 'Resting',
    'stand': 'Resting',
    'eat': 'Interaction',
    'talk': 'Interaction',
    'jump': 'Active',
    'situp': 'Active'
}
valid_classes = set(label_map.keys())

# =========================================================
# 🧩 데이터셋 로드 및 전처리
# =========================================================
# 💡 모든 클래스에 동일한 augmentation 적용
transform = transforms.Compose([
    transforms.Resize((IMG_SIZE, IMG_SIZE)),
    transforms.RandomHorizontalFlip(p=0.5),
    transforms.RandomRotation(8),
    transforms.RandomAffine(degrees=10, translate=(0.05, 0.05)),
    transforms.ColorJitter(brightness=0.25, contrast=0.25, saturation=0.2),
    transforms.ToTensor(),
    transforms.Normalize(mean=[0.485, 0.456, 0.406],
                         std=[0.229, 0.224, 0.225])
])

dataset = datasets.ImageFolder(DATA_DIR, transform=transform)
print(f"✅ Loaded dataset: {len(dataset)} images, {len(dataset.classes)} classes")

# =========================================================
# ⚖️ 클래스 균형 샘플링
# =========================================================
class_to_indices = defaultdict(list)
for idx, (path, class_idx) in enumerate(dataset.samples):
    orig_label = dataset.classes[class_idx]
    if orig_label not in valid_classes:
        continue
    merged_label = label_map[orig_label]
    class_to_indices[merged_label].append(idx)

selected_indices = []
for label, indices in class_to_indices.items():
    target_n = EXTRA_SAMPLES.get(label, SAMPLES_PER_CLASS)
    n = min(target_n, len(indices))
    selected_indices.extend(random.sample(indices, n))

subset = Subset(dataset, selected_indices)
merged_labels = [label_map[dataset.classes[dataset.samples[i][1]]] for i in selected_indices]
label_to_idx = {lbl: i for i, lbl in enumerate(sorted(set(label_map.values())))}
targets = torch.tensor([label_to_idx[t] for t in merged_labels])

print(f"📊 Balanced label count: {Counter(merged_labels)}")

# =========================================================
# ✂️ Train / Val split
# =========================================================
num_total = len(subset)
train_size = int(0.8 * num_total)
val_size = num_total - train_size
train_ds, val_ds = random_split(list(zip(subset, targets)), [train_size, val_size])

def collate_fn(batch):
    imgs, labels = zip(*batch)
    imgs = torch.stack([img for (img, _) in imgs])
    labels = torch.tensor(labels)
    return imgs, labels

train_loader = DataLoader(train_ds, batch_size=BATCH_SIZE, shuffle=True, collate_fn=collate_fn)
val_loader = DataLoader(val_ds, batch_size=BATCH_SIZE, shuffle=False, collate_fn=collate_fn)

print(f"📦 Train={len(train_ds)}, Val={len(val_ds)}")

# =========================================================
# 🧠 모델 정의 (표현력 확장)
# =========================================================
model = models.resnet18(pretrained=True)
model.fc = nn.Sequential(
    nn.Linear(model.fc.in_features, 512),
    nn.ReLU(),
    nn.Dropout(0.3),
    nn.Linear(512, len(label_to_idx))
)
model = model.to(DEVICE)

criterion = nn.CrossEntropyLoss()
optimizer = optim.Adam(model.parameters(), lr=LR, weight_decay=1e-5)
scheduler = optim.lr_scheduler.StepLR(optimizer, step_size=4, gamma=0.6)

# =========================================================
# 🚀 학습 및 검증
# =========================================================
def evaluate(model, loader):
    model.eval()
    correct, total, loss_sum = 0, 0, 0
    all_preds, all_labels = [], []
    with torch.no_grad():
        for imgs, labels in loader:
            imgs, labels = imgs.to(DEVICE), labels.to(DEVICE)
            outputs = model(imgs)
            loss = criterion(outputs, labels)
            loss_sum += loss.item()
            _, predicted = outputs.max(1)
            correct += (predicted == labels).sum().item()
            total += labels.size(0)
            all_preds.extend(predicted.cpu().numpy())
            all_labels.extend(labels.cpu().numpy())
    acc = correct / total
    return acc, loss_sum / len(loader), all_labels, all_preds

best_acc, best_report = 0, None

for epoch in range(EPOCHS):
    model.train()
    pbar = tqdm(train_loader, desc=f"Epoch {epoch+1}/{EPOCHS}")
    for imgs, labels in pbar:
        imgs, labels = imgs.to(DEVICE), labels.to(DEVICE)
        optimizer.zero_grad()
        outputs = model(imgs)
        loss = criterion(outputs, labels)
        loss.backward()
        optimizer.step()
    scheduler.step()

    val_acc, val_loss, y_true, y_pred = evaluate(model, val_loader)
    print(f"📈 Epoch {epoch+1}: Val Acc={val_acc*100:.2f}%, Loss={val_loss:.4f}")

    if val_acc > best_acc * 0.995:   # 소폭 개선도 갱신
        best_acc = val_acc
        torch.save(model.state_dict(), os.path.join(OUTPUT_DIR, "best_model.pth"))
        report = classification_report(
            y_true, y_pred,
            target_names=list(label_to_idx.keys()),
            output_dict=True, zero_division=0
        )
        best_report = report

print(f"✅ Finished Training. Best Accuracy: {best_acc*100:.2f}%")

# =========================================================
# 📊 metrics.json 저장
# =========================================================
if best_report:
    labels = list(best_report.keys())[:-3]
    f1_scores = [best_report[k]["f1-score"] for k in labels]
    macro_f1 = best_report["macro avg"]["f1-score"]

    metrics = {
        "accuracy": round(best_acc, 4),
        "macro_f1": round(macro_f1, 4),
        "labels": labels,
        "f1": [round(v, 4) for v in f1_scores]
    }

    with open(os.path.join(OUTPUT_DIR, "metrics.json"), "w", encoding="utf-8") as f:
        json.dump(metrics, f, indent=2, ensure_ascii=False)

    print("💾 Saved metrics.json for PHP dashboard.")
