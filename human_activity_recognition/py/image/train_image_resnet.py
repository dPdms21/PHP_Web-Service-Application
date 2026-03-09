# 📁 파일: py/image/train_image_resnet.py
import os, json, torch, torch.nn as nn, torch.optim as optim
from torch.utils.data import DataLoader, random_split, Dataset
from torchvision import models, transforms
from torchvision.datasets.folder import default_loader
from sklearn.metrics import accuracy_score, f1_score, precision_recall_fscore_support
import numpy as np

# ============================
# 1️⃣ 경로 설정
# ============================
DATA_DIR = "data/HMDB51"  # 하위폴더에 jpg가 있는 구조
OUTPUT_DIR = "outputs/image"
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

# ✅ HMDB에 존재하는 4개 클래스만 사용
har_classes = ["WALKING", "WALKING_UPSTAIRS", "SITTING", "STANDING"]
har_to_idx = {c: i for i, c in enumerate(har_classes)}

# ============================
# 3️⃣ Recursive Image Dataset
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

    def __len__(self):
        return len(self.samples)

    def __getitem__(self, idx):
        path, label = self.samples[idx]
        img = default_loader(path)
        if self.transform:
            img = self.transform(img)
        return img, label

# ============================
# 4️⃣ 데이터 전처리
# ============================
transform = transforms.Compose([
    transforms.Resize((128, 128)),
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

class CustomMappedDataset(Dataset):
    def __init__(self, samples, transform=None):
        self.samples = samples
        self.transform = transform

    def __len__(self):
        return len(self.samples)

    def __getitem__(self, idx):
        path, label = self.samples[idx]
        img = default_loader(path)
        if self.transform:
            img = self.transform(img)
        return img, label

dataset = CustomMappedDataset(mapped_samples, transform=transform)

# ============================
# 5️⃣ 데이터 분할
# ============================
train_size = int(0.8 * len(dataset))
val_size = len(dataset) - train_size
train_ds, val_ds = random_split(dataset, [train_size, val_size])
train_loader = DataLoader(train_ds, batch_size=32, shuffle=True)
val_loader = DataLoader(val_ds, batch_size=32)

print(f"✅ Loaded dataset: {len(dataset)} images (train={len(train_ds)}, val={len(val_ds)})")

# ============================
# 6️⃣ 모델 설정 (ResNet18)
# ============================
device = torch.device("cuda" if torch.cuda.is_available() else "cpu")
model = models.resnet18(weights=models.ResNet18_Weights.DEFAULT)
num_ftrs = model.fc.in_features
model.fc = nn.Linear(num_ftrs, len(har_classes))
model = model.to(device)

criterion = nn.CrossEntropyLoss()
optimizer = optim.Adam(model.parameters(), lr=1e-4)

# ============================
# 7️⃣ 학습 루프
# ============================
EPOCHS = 5
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
# 8️⃣ 평가
# ============================
model.eval()
all_preds, all_labels = [], []
with torch.no_grad():
    for imgs, labels in val_loader:
        imgs, labels = imgs.to(device), labels.to(device)
        outputs = model(imgs)
        preds = outputs.argmax(1)
        all_preds.extend(preds.cpu().numpy())
        all_labels.extend(labels.cpu().numpy())

acc = accuracy_score(all_labels, all_preds)
macro_f1 = f1_score(all_labels, all_preds, average="macro")
precision, recall, f1, _ = precision_recall_fscore_support(all_labels, all_preds, average=None, labels=range(len(har_classes)))

# ============================
# 9️⃣ metrics.json 저장
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
