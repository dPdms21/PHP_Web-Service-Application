# py_light/audio/train_audio_mobilenet_v2.py
import os, json, random
from collections import defaultdict, Counter

import torch
import torch.nn as nn
import torch.optim as optim
from torch.utils.data import DataLoader, Subset, random_split
from torchvision.datasets import DatasetFolder
from torchvision import models, transforms

import torchaudio
import torchaudio.transforms as T
from sklearn.metrics import classification_report
from tqdm import tqdm

# =========================================================
# ⚙️ 설정
# =========================================================
DATA_DIR   = "data/audio_esc50_grouped"
OUTPUT_DIR = "outputs_light/audio_mobilenet_v2"
os.makedirs(OUTPUT_DIR, exist_ok=True)

SAMPLE_RATE = 44100
N_MELS      = 64
IMG_SIZE    = 128
BATCH_SIZE  = 16
EPOCHS      = 8
LR          = 1e-4
WEIGHT_DECAY = 1e-5
DEVICE      = torch.device("cpu")
SAMPLES_PER_CLASS = 80   # 클래스당 샘플 수

SEED = 42
random.seed(SEED)
torch.manual_seed(SEED)

# =========================================================
# 🎯 HAR 라벨 통합 (5개)
# =========================================================
label_map = {
    "climbing": "Outdoor",
    "exercising": "Active",
    "interacting": "Interaction",
    "moving": "Locomotion",
    "posturing": "Resting",
}

HAR_LABELS = sorted(set(label_map.values()))
label_to_idx = {lbl: i for i, lbl in enumerate(HAR_LABELS)}
idx_to_label = {i: lbl for lbl, i in label_to_idx.items()}

# =========================================================
# 🎧 안전한 오디오 로딩
# =========================================================
def load_waveform(path: str):
    try:
        wav, sr = torchaudio.load(path, backend="soundfile")
    except Exception:
        import soundfile as sf
        x, sr = sf.read(path, dtype="float32", always_2d=False)
        if x.ndim == 1:
            wav = torch.tensor(x).unsqueeze(0)
        else:
            wav = torch.tensor(x).T
    return wav, sr

# =========================================================
# 🔊 MelSpectrogram 변환
# =========================================================
mel_transform = T.MelSpectrogram(
    sample_rate=SAMPLE_RATE, n_fft=1024, hop_length=256, n_mels=N_MELS
)
amp_to_db = T.AmplitudeToDB()

def waveform_to_mel(wav: torch.Tensor) -> torch.Tensor:
    if wav.size(0) > 1:
        wav = wav.mean(dim=0, keepdim=True)
    mel = mel_transform(wav)
    mel = amp_to_db(mel)
    mel = (mel - mel.mean()) / (mel.std() + 1e-6)
    mel = mel.repeat(3, 1, 1)
    mel = transforms.Resize((IMG_SIZE, IMG_SIZE))(mel)
    return mel

def audio_loader(path: str) -> torch.Tensor:
    wav, _ = load_waveform(path)
    return waveform_to_mel(wav)

# =========================================================
# 📂 데이터셋 로드 및 균형 샘플링
# =========================================================
dataset = DatasetFolder(root=DATA_DIR, loader=audio_loader, extensions=(".wav",))
classes = dataset.classes
print(f"✅ Loaded audio dataset: {len(dataset)} files, {len(classes)} classes → {classes}")

grouped_indices = defaultdict(list)
for idx, (_, y) in enumerate(dataset.samples):
    orig_label = classes[y]
    if orig_label in label_map:
        grouped_label = label_map[orig_label]
        grouped_indices[grouped_label].append(idx)

selected_indices = []
group_labels = []
for group, indices in grouped_indices.items():
    n = min(SAMPLES_PER_CLASS, len(indices))
    sampled = random.sample(indices, n)
    selected_indices.extend(sampled)
    group_labels.extend([group] * n)

subset = Subset(dataset, selected_indices)
targets = torch.tensor([label_to_idx[g] for g in group_labels])

print(f"📊 Balanced groups: {Counter(group_labels)}")

# =========================================================
# ✂️ Train / Val split
# =========================================================
num_total = len(subset)
train_size = int(0.8 * num_total)
val_size = num_total - train_size

train_ds, val_ds = random_split(list(zip(subset, targets)), [train_size, val_size])

def augment(spec: torch.Tensor) -> torch.Tensor:
    # 간단한 SpecAugment
    _, H, W = spec.shape
    if random.random() < 0.5:
        f = random.randint(0, max(1, H // 8))
        f0 = random.randint(0, max(0, H - f))
        spec[:, f0:f0+f, :] = 0
    if random.random() < 0.5:
        t = random.randint(0, max(1, W // 8))
        t0 = random.randint(0, max(0, W - t))
        spec[:, :, t0:t0+t] = 0
    return spec

def collate_fn(batch, train: bool):
    xs, ys = [], []
    for (x, _), y in batch:
        if train:
            x = augment(x.clone())
        xs.append(x)
        ys.append(y)
    return torch.stack(xs, 0), torch.tensor(ys)

train_loader = DataLoader(
    train_ds, batch_size=BATCH_SIZE, shuffle=True,
    collate_fn=lambda b: collate_fn(b, True)
)
val_loader = DataLoader(
    val_ds, batch_size=BATCH_SIZE, shuffle=False,
    collate_fn=lambda b: collate_fn(b, False)
)

print(f"📦 Train={len(train_ds)}, Val={len(val_ds)}")

# =========================================================
# 🧠 모델 정의 (MobileNetV2)
# =========================================================
weights = models.MobileNet_V2_Weights.DEFAULT
model = models.mobilenet_v2(weights=weights)

in_features = model.classifier[1].in_features
model.classifier[1] = nn.Linear(in_features, len(HAR_LABELS))

model = model.to(DEVICE)

criterion = nn.CrossEntropyLoss()
optimizer = optim.Adam(model.parameters(), lr=LR, weight_decay=WEIGHT_DECAY)
scheduler = optim.lr_scheduler.StepLR(optimizer, step_size=4, gamma=0.6)

# =========================================================
# 🚀 평가 함수
# =========================================================
def evaluate(loader):
    model.eval()
    correct, total, loss_sum = 0, 0, 0.0
    y_true, y_pred = [], []
    with torch.no_grad():
        for x, y in loader:
            x, y = x.to(DEVICE), y.to(DEVICE)
            out = model(x)
            loss = criterion(out, y)
            loss_sum += loss.item()
            _, pred = out.max(1)
            correct += (pred == y).sum().item()
            total += y.size(0)
            y_true.extend(y.cpu().tolist())
            y_pred.extend(pred.cpu().tolist())
    acc = correct / total
    return acc, loss_sum / max(1, len(loader)), y_true, y_pred

# =========================================================
# 🔁 학습 루프
# =========================================================
best_acc, best_report = 0.0, None

for epoch in range(1, EPOCHS + 1):
    model.train()
    pbar = tqdm(train_loader, desc=f"[MobileNetV2] Epoch {epoch}/{EPOCHS}")
    for x, y in pbar:
        x, y = x.to(DEVICE), y.to(DEVICE)
        optimizer.zero_grad()
        out = model(x)
        loss = criterion(out, y)
        loss.backward()
        optimizer.step()
    scheduler.step()

    val_acc, val_loss, y_true, y_pred = evaluate(val_loader)
    print(f"📈 Epoch {epoch}: Val Acc={val_acc*100:.2f}%, Loss={val_loss:.4f}")

    if val_acc > best_acc * 0.995:
        best_acc = val_acc
        torch.save(model.state_dict(), os.path.join(OUTPUT_DIR, "best_model.pth"))
        report = classification_report(
            y_true,
            y_pred,
            target_names=HAR_LABELS,
            output_dict=True,
            zero_division=0,
        )
        best_report = report

print(f"✅ Finished Training. Best Val Accuracy: {best_acc*100:.2f}%")
print(f"💾 Saved best model → {os.path.join(OUTPUT_DIR, 'best_model.pth')}")

# =========================================================
# 💾 metrics.json 저장
# =========================================================
if best_report:
    labels = HAR_LABELS
    f1_scores = [best_report[lbl]["f1-score"] for lbl in labels]
    macro_f1 = best_report["macro avg"]["f1-score"]

    metrics = {
        "accuracy": round(best_acc, 4),
        "macro_f1": round(macro_f1, 4),
        "labels": labels,
        "f1": [round(v, 4) for v in f1_scores],
    }

    with open(os.path.join(OUTPUT_DIR, "metrics.json"), "w", encoding="utf-8") as f:
        json.dump(metrics, f, indent=2, ensure_ascii=False)

    print("💾 Saved metrics.json for comparison.")
