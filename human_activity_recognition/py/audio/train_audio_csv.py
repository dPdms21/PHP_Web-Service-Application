# 📁 파일: py/audio/train_audio_csv_fake.py
import os, json, random, torch, torch.nn as nn, torch.optim as optim
import pandas as pd
from torch.utils.data import DataLoader, Dataset
from sklearn.metrics import accuracy_score, f1_score, precision_recall_fscore_support
import numpy as np

# ============================
# 1️⃣ 경로 설정
# ============================
CSV_DIR = "data/epic-sounds"
OUTPUT_DIR = "outputs/audio_fake"
os.makedirs(OUTPUT_DIR, exist_ok=True)

TRAIN_CSV = os.path.join(CSV_DIR, "EPIC_Sounds_train.csv")
VAL_CSV   = os.path.join(CSV_DIR, "EPIC_Sounds_validation.csv")

# ============================
# 2️⃣ EPIC → HAR 매핑
# ============================
epic_to_har = {
    "rustle": "WALKING",
    "plastic-only collision": "SITTING",
    "open / close": "STANDING",
    "ceramic / wood collision": "WALKING_UPSTAIRS"
}
har_classes = ["WALKING", "WALKING_UPSTAIRS", "SITTING", "STANDING"]
har_to_idx = {c: i for i, c in enumerate(har_classes)}

# ============================
# 3️⃣ Fake Audio Dataset
# ============================
class FakeAudioDataset(Dataset):
    def __init__(self, csv_path, nrows=2000, seq_len=16000):
        self.data = pd.read_csv(csv_path, nrows=nrows)
        self.samples = []
        self.seq_len = seq_len

        for _, row in self.data.iterrows():
            raw_label = str(row["class"]).strip().lower()
            normalized = raw_label.replace("-", " ").replace("/", " ").replace("_", " ").strip()
            har_label = None
            for key in epic_to_har:
                key_norm = key.replace("-", " ").replace("/", " ").replace("_", " ").strip()
                if normalized == key_norm:
                    har_label = epic_to_har[key]
                    break
            if har_label is not None:
                self.samples.append(har_to_idx[har_label])

    def __len__(self):
        return len(self.samples)

    def __getitem__(self, idx):
        label = self.samples[idx]
        # 🎵 가짜 오디오 생성 (랜덤 노이즈 + sine)
        t = torch.linspace(0, 1, self.seq_len)
        freq = random.choice([220, 330, 440, 550])
        sine_wave = 0.3 * torch.sin(2 * np.pi * freq * t)
        noise = 0.1 * torch.randn(self.seq_len)
        waveform = (sine_wave + noise).unsqueeze(0).unsqueeze(0)  # (1, 1, L)
        return waveform, label

# ============================
# 4️⃣ 모델 정의
# ============================
class SimpleAudioCNN(nn.Module):
    def __init__(self, num_classes):
        super().__init__()
        self.net = nn.Sequential(
            nn.Conv1d(1, 16, 9, 2), nn.ReLU(),
            nn.Conv1d(16, 32, 9, 2), nn.ReLU(),
            nn.AdaptiveAvgPool1d(32),
        )
        self.fc = nn.Sequential(
            nn.Flatten(),
            nn.Linear(32*32, 128),
            nn.ReLU(),
            nn.Linear(128, num_classes)
        )
    def forward(self, x):
        x = self.net(x.squeeze(1))  # (B, 1, L)
        return self.fc(x)

# ============================
# 5️⃣ 데이터 준비
# ============================
train_ds = FakeAudioDataset(TRAIN_CSV, nrows=2000)
val_ds   = FakeAudioDataset(VAL_CSV, nrows=500)
print(f"✅ Loaded fake data: {len(train_ds)} train, {len(val_ds)} val")

train_loader = DataLoader(train_ds, batch_size=32, shuffle=True)
val_loader   = DataLoader(val_ds, batch_size=32)

# ============================
# 6️⃣ 학습
# ============================
device = torch.device("cpu")
model = SimpleAudioCNN(num_classes=len(har_classes)).to(device)
criterion = nn.CrossEntropyLoss()
optimizer = optim.Adam(model.parameters(), lr=1e-3)
EPOCHS = 5

for epoch in range(EPOCHS):
    model.train()
    total_loss = 0.0
    for x, y in train_loader:
        x, y = x.to(device), y.to(device)
        optimizer.zero_grad()
        out = model(x)
        loss = criterion(out, y)
        loss.backward()
        optimizer.step()
        total_loss += loss.item()
    print(f"Epoch [{epoch+1}/{EPOCHS}] Loss: {total_loss/len(train_loader):.4f}")

# ============================
# 7️⃣ 평가 + metrics.json 저장
# ============================
model.eval()
preds, trues = [], []
with torch.no_grad():
    for x, y in val_loader:
        out = model(x)
        pred = out.argmax(1)
        preds.extend(pred.numpy())
        trues.extend(y.numpy())

acc = accuracy_score(trues, preds)
macro_f1 = f1_score(trues, preds, average="macro")
prec, rec, f1, _ = precision_recall_fscore_support(trues, preds, average=None, labels=range(len(har_classes)))

metrics = {
    "accuracy": round(float(acc), 4),
    "macro_f1": round(float(macro_f1), 4),
    "labels": har_classes,
    "precision": [round(float(x), 4) for x in prec],
    "recall": [round(float(x), 4) for x in rec],
    "f1": [round(float(x), 4) for x in f1]
}
os.makedirs(OUTPUT_DIR, exist_ok=True)
with open(os.path.join(OUTPUT_DIR, "metrics.json"), "w", encoding="utf-8") as f:
    json.dump(metrics, f, indent=2, ensure_ascii=False)

print(f"\n✅ Saved metrics to {OUTPUT_DIR}/metrics.json")
print(f"📊 Accuracy={acc:.4f}, Macro-F1={macro_f1:.4f}")
