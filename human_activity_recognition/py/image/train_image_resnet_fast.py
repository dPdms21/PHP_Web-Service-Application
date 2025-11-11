# 📁 파일: py/image/train_image_resnet_fast.py
import os, json, random, torch, torch.nn as nn, torch.optim as optim
from torch.utils.data import DataLoader, random_split, Dataset
from torchvision import models, transforms
from torchvision.datasets.folder import default_loader
from sklearn.metrics import accuracy_score, f1_score, precision_recall_fscore_support
from collections import defaultdict
import numpy as np

# ============================
# 1️⃣ 경로 설정
# ============================
DATA_DIR = "data/HMDB51"
OUTPUT_DIR = "outputs/image_fast"
os.makedirs(OUTPUT_DIR, exist_ok=True)

# ============================
# 2️⃣ HMDB → HAR 매핑
# ============================
hmdb_to_har = {
    "walk": "WALKING",
    "run": "WALKING",
    "climb_stairs": "WALKING_UPSTAIRS",
    "climb": "WALKING_UPSTAIRS",
    "sit": "SITTING",
    "situp": "SITTING",
    "eat": "SITTING",
    "stand": "STANDING",
    "jump": "STANDING",
    "talk": "STANDING",
    "wave": "STANDING"
}

har_classes = ["WALKING", "WALKING_UPSTAIRS", "SITTING", "STANDING"]
har_to_idx = {c: i for i, c in enumerate(har_classes)}

# ============================
# 3️⃣ 하위폴더 재귀 이미지 로더
# ============================
class RecursiveImageFolder(Dataset):
    def __init__(self, root, transform=None):
        self.samples = []
        self.transform = transform
        self.classes = sorted(os.listdir(root))
        self.class_to_idx = {cls_name: i for i, cls_name in enumerate(self.classes)}

        for cls in self.classes:
            cls_path = os.path.join(root, cls)
            for subdir, _, files in os.walk(cls_path):
                for f in files:
                    if f.lower().endswith((".jpg", ".jpeg", ".png")):
                        self.samples.append((os.path.join(subdir, f), self.class_to_idx[cls]))

    def __len__(self): return len(self.samples)
    def __getitem__(self, idx):
        path, label = self.samples[idx]
        img = default_loader(path)
        if self.transform:
            img = self.transform(img)
        return img, label

# ============================
# 4️⃣ 전처리
# ============================
transform = transforms.Compose([
    transforms.Resize((96, 96)),   # 더 작은 이미지로 빠른 학습
    transforms.ToTensor(),
    transforms.Normalize([0.5], [0.5])
])

dataset_raw = RecursiveImageFolder(DATA_DIR, transform=transform)
idx_to_class = {v: k for k, v in dataset_raw.class_to_idx.items()}

def map_to_har_idx(hmdb_idx):
    hmdb_label = idx_to_class[hmdb_idx]
    har_label = hmdb_to_har.get(hmdb_label)
    return har_to_idx.get(har_label)

mapped_samples = []
for img_path, label_idx in dataset_raw.samples:
    mapped_idx = map_to_har_idx(label_idx)
    if mapped_idx is not None:
        mapped_samples.append((img_path, mapped_idx))

# ============================
# 5️⃣ 균등 샘플링 (총 약 2,000장)
# ============================
target_per_class = 500  # 500 × 4 = 2000
class_groups = defaultdict(list)
for img, label in mapped_samples:
    class_groups[label].append(img)

subset_samples = []
for label, imgs in class_groups.items():
    subset = random.sample(imgs, min(target_per_class, len(imgs)))
    subset_samples.extend([(img, label) for img in subset])

print(f"⚡ Sampled {len(subset_samples)} images ({len(class_groups)} classes)")

class CustomDataset(Dataset):
    def __init__(self, samples, transform=None):
        self.samples = samples
        self.transform = transform
    def __len__(self): return len(self.samples)
    def __getitem__(self, idx):
        path, label = self.samples[idx]
        img = default_loader(path)
        if self.transform:
            img = self.transform(img)
        return img, label

dataset = CustomDataset(subset_samples, transform=transform)

# ============================
# 6️⃣ Train/Test Split
# ============================
train_size = int(0.8 * len(dataset))
val_size = len(dataset) - train_size
train_ds, val_ds = random_split(dataset, [train_size, val_size])
train_loader = DataLoader(train_ds, batch_size=16, shuffle=True, num_workers=0)
val_loader = DataLoader(val_ds, batch_size=16, num_workers=0)

print(f"✅ Loaded dataset: {len(dataset)} images (train={len(train_ds)}, val={len(val_ds)})")

# ============================
# 7️⃣ 모델 (ResNet18)
# ============================
device = torch.device("cpu")  # CPU 강제
model = models.resnet18(weights=models.ResNet18_Weights.DEFAULT)
num_ftrs = model.fc.in_features
model.fc = nn.Linear(num_ftrs, len(har_classes))
model = model.to(device)

criterion = nn.CrossEntropyLoss()
optimizer = optim.Adam(model.parameters(), lr=1e-4)

# ============================
# 8️⃣ 학습
# ============================
EPOCHS = 3  # 빠른 테스트용
for epoch in range(EPOCHS):
    model.train()
    running_loss = 0.0
    for imgs, labels in train_loader:
        imgs, labels = imgs.to(device), labels.to(device)
        optimizer.zero_grad()
        outputs = model(imgs)
        loss = criterion(outputs, labels)
        loss.backward()
        optimizer.step()
        running_loss += loss.item()
    print(f"Epoch [{epoch+1}/{EPOCHS}] Loss: {running_loss/len(train_loader):.4f}")

# ============================
# 9️⃣ 평가
# ============================
model.eval()
all_preds, all_labels = [], []
with torch.no_grad():
    for imgs, labels in val_loader:
        outputs = model(imgs)
        preds = outputs.argmax(1)
        all_preds.extend(preds.cpu().numpy())
        all_labels.extend(labels.cpu().numpy())

acc = accuracy_score(all_labels, all_preds)
macro_f1 = f1_score(all_labels, all_preds, average="macro")
precision, recall, f1, _ = precision_recall_fscore_support(all_labels, all_preds, average=None, labels=range(len(har_classes)))

# ============================
# 🔟 metrics.json 저장
# ============================
metrics = {
    "accuracy": round(float(acc), 4),
    "macro_f1": round(float(macro_f1), 4),
    "labels": har_classes,
    "precision": [round(float(x), 4) for x in precision],
    "recall": [round(float(x), 4) for x in recall],
    "f1": [round(float(x), 4) for x in f1]
}

with open(os.path.join(OUTPUT_DIR, "metrics.json"), "w", encoding="utf-8") as f:
    json.dump(metrics, f, indent=2, ensure_ascii=False)

print(f"\n✅ Saved metrics to {OUTPUT_DIR}/metrics.json")
print(f"📊 Accuracy={acc:.4f}, Macro-F1={macro_f1:.4f}")
