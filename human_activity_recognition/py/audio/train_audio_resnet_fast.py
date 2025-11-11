# py/audio/train_audio_resnet_fast.py
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

# =====[ 경로 / 하이퍼파라미터 ]=================================================
DATA_DIR   = "data/audio_esc50_grouped"
OUTPUT_DIR = "outputs/audio_fast"
os.makedirs(OUTPUT_DIR, exist_ok=True)

SAMPLE_RATE = 44100
N_MELS      = 64
IMG_SIZE    = 128
BATCH_SIZE  = 16
EPOCHS      = 6
LR          = 1e-4
WEIGHT_DECAY = 1e-5
DEVICE      = torch.device("cpu")

# 클래스 균형 샘플 수 (총 5클래스 × 80 = 400)
SAMPLES_PER_CLASS = 80

# =====[ 안전한 오디오 로딩 (TorchCodec 완전 우회) ]=============================
def load_waveform(path: str):
    # backend="soundfile" 사용. 실패 시 soundfile 직접 사용.
    try:
        wav, sr = torchaudio.load(path, backend="soundfile")
    except Exception:
        import soundfile as sf  # pip install soundfile
        x, sr = sf.read(path, dtype="float32", always_2d=False)
        if x.ndim == 1:
            wav = torch.tensor(x).unsqueeze(0)         # [1, T]
        else:
            wav = torch.tensor(x).T                    # [C, T]
    return wav, sr

# =====[ 스펙트로그램 변환 ]=====================================================
mel_transform = T.MelSpectrogram(
    sample_rate=SAMPLE_RATE, n_fft=1024, hop_length=256, n_mels=N_MELS
)
amp_to_db = T.AmplitudeToDB()

def waveform_to_mel(wav: torch.Tensor) -> torch.Tensor:
    # mono
    if wav.size(0) > 1:
        wav = wav.mean(dim=0, keepdim=True)
    mel = mel_transform(wav)               # [1, n_mels, time]
    mel = amp_to_db(mel)
    mel = (mel - mel.mean()) / (mel.std() + 1e-6)
    # 3채널 복제 + 128x128 리사이즈
    mel = mel.repeat(3, 1, 1)
    resize = transforms.Resize((IMG_SIZE, IMG_SIZE))
    mel = resize(mel)
    return mel

def audio_loader(path: str) -> torch.Tensor:
    wav, _ = load_waveform(path)
    return waveform_to_mel(wav)

# =====[ 데이터셋 / 균형 샘플링 ]================================================
dataset = DatasetFolder(root=DATA_DIR, loader=audio_loader, extensions=(".wav",))
classes = dataset.classes
print(f"✅ Loaded audio dataset: {len(dataset)} files, {len(classes)} classes → {classes}")

by_class = defaultdict(list)
for idx, (_, y) in enumerate(dataset.samples):
    by_class[classes[y]].append(idx)

selected_indices = []
for cls in classes:
    indices = by_class[cls]
    n = min(SAMPLES_PER_CLASS, len(indices))
    selected_indices.extend(random.sample(indices, n))

subset = Subset(dataset, selected_indices)

# train/val = 8:2
num_total = len(subset)
train_size = int(0.8 * num_total)
val_size = num_total - train_size
train_ds, val_ds = random_split(subset, [train_size, val_size])

# ----- 증강 -----
def augment(spec: torch.Tensor) -> torch.Tensor:
    # spec: [3, H, W]
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
    for x, y in batch:
        if train:
            x = augment(x.clone())
        xs.append(x)
        ys.append(y)
    return torch.stack(xs, 0), torch.tensor(ys)

train_loader = DataLoader(train_ds, batch_size=BATCH_SIZE, shuffle=True,
                          collate_fn=lambda b: collate_fn(b, True))
val_loader   = DataLoader(val_ds, batch_size=BATCH_SIZE, shuffle=False,
                          collate_fn=lambda b: collate_fn(b, False))

print(f"📦 Balanced: {Counter([classes[dataset.samples[i][1]] for i in selected_indices])}")
print(f"📦 Train={len(train_ds)}, Val={len(val_ds)}")

# =====[ 모델 ]==================================================================
model = models.resnet18(pretrained=True)
# 일부 레이어 고정(학습 속도/안정성 증가)
for p in model.layer1.parameters(): p.requires_grad = False
for p in model.layer2.parameters(): p.requires_grad = False

in_feats = model.fc.in_features
model.fc = nn.Sequential(
    nn.Linear(in_feats, 256),
    nn.ReLU(),
    nn.Dropout(0.4),
    nn.Linear(256, len(classes))
)
model = model.to(DEVICE)

criterion = nn.CrossEntropyLoss()
optimizer = optim.Adam(filter(lambda p: p.requires_grad, model.parameters()),
                       lr=LR, weight_decay=WEIGHT_DECAY)
scheduler = optim.lr_scheduler.StepLR(optimizer, step_size=3, gamma=0.5)

# =====[ 평가 함수 ]=============================================================
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

# =====[ 학습 루프 ]=============================================================
best_acc, best_report = 0.0, None
for epoch in range(EPOCHS):
    model.train()
    pbar = tqdm(train_loader, desc=f"Epoch {epoch+1}/{EPOCHS}")
    for x, y in pbar:
        x, y = x.to(DEVICE), y.to(DEVICE)
        optimizer.zero_grad()
        out = model(x)
        loss = criterion(out, y)
        loss.backward()
        optimizer.step()
    scheduler.step()

    val_acc, val_loss, y_true, y_pred = evaluate(val_loader)
    print(f"📈 Epoch {epoch+1}: Val Acc={val_acc*100:.2f}%, Loss={val_loss:.4f}")

    if val_acc > best_acc:
        best_acc = val_acc
        torch.save(model.state_dict(), os.path.join(OUTPUT_DIR, "best_model.pth"))
        report = classification_report(
            y_true, y_pred, target_names=classes,
            output_dict=True, zero_division=0
        )
        best_report = report

print(f"✅ Finished. Best Val Accuracy: {best_acc*100:.2f}%")

# =====[ metrics.json 저장 (PHP 호환) ]==========================================
if best_report is not None:
    labels = list(best_report.keys())[:-3]
    f1 = [best_report[k]["f1-score"] for k in labels]
    macro_f1 = best_report["macro avg"]["f1-score"]
    metrics = {
        "accuracy": round(best_acc, 4),
        "macro_f1": round(macro_f1, 4),
        "labels": labels,
        "f1": [round(v, 4) for v in f1]
    }
    with open(os.path.join(OUTPUT_DIR, "metrics.json"), "w", encoding="utf-8") as f:
        json.dump(metrics, f, indent=2, ensure_ascii=False)
    print("💾 Saved:", os.path.join(OUTPUT_DIR, "metrics.json"))
