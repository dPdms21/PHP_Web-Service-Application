#그리드 서치로 최적의 비율 알아봄

import os, json, random
from collections import defaultdict
from tqdm import tqdm

import torch
import torch.nn.functional as F
from torch.utils.data import Dataset, DataLoader
from torchvision import datasets, models, transforms
from torchvision.datasets import DatasetFolder
import torchaudio
import torchaudio.transforms as T
from sklearn.metrics import classification_report

# =========================================================
# ⚙️ 설정
# =========================================================
IMG_DATA_DIR   = "data/HMDB51"
AUD_DATA_DIR   = "data/audio_esc50_grouped"
IMG_MODEL_PATH = "outputs/image_fast_v2/best_model.pth"
AUD_MODEL_PATH = "outputs/audio_fast/best_model.pth"
OUTPUT_DIR     = "outputs/fusion_late"
os.makedirs(OUTPUT_DIR, exist_ok=True)

DEVICE = torch.device("cpu")
IMG_SIZE = 128
BATCH = 16
SAMPLE_RATE = 44100
N_MELS = 64
SEED = 42

MAX_PAIRS_PER_CLASS = 120

random.seed(SEED)
torch.manual_seed(SEED)

# =========================================================
# 🎯 HAR 라벨 정의
# =========================================================
HAR_LABELS = ["Active", "Interaction", "Locomotion", "Outdoor", "Resting"]
label_to_idx = {l:i for i,l in enumerate(HAR_LABELS)}

hmdb_to_har = {
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
esc50_to_har = {
    'climbing': 'Outdoor',
    'exercising': 'Active',
    'interacting': 'Interaction',
    'moving': 'Locomotion',
    'posturing': 'Resting'
}

valid_img_src = set(hmdb_to_har.keys())

# =========================================================
# 🧩 Transform 정의
# =========================================================
img_transform = transforms.Compose([
    transforms.Resize((IMG_SIZE, IMG_SIZE)),
    transforms.ToTensor(),
    transforms.Normalize(mean=[0.485,0.456,0.406], std=[0.229,0.224,0.225])
])

mel_transform = T.MelSpectrogram(sample_rate=SAMPLE_RATE, n_fft=1024, hop_length=256, n_mels=N_MELS)
amp_to_db = T.AmplitudeToDB()

def safe_load_wav(path):
    try:
        wav, sr = torchaudio.load(path, backend="soundfile")
    except Exception:
        import soundfile as sf
        x, sr = sf.read(path, dtype="float32", always_2d=False)
        wav = torch.tensor(x).unsqueeze(0) if x.ndim==1 else torch.tensor(x).T
    return wav, sr

def wav_to_mel_img(wav):
    if wav.size(0)>1: wav = wav.mean(dim=0, keepdim=True)
    mel = mel_transform(wav)
    mel = amp_to_db(mel)
    mel = (mel - mel.mean()) / (mel.std()+1e-6)
    mel = mel.repeat(3,1,1)
    mel = transforms.Resize((IMG_SIZE,IMG_SIZE))(mel)
    return mel

# =========================================================
# 📂 데이터셋 로드
# =========================================================
img_folder = datasets.ImageFolder(IMG_DATA_DIR, transform=img_transform)
aud_folder = DatasetFolder(AUD_DATA_DIR, loader=lambda p: wav_to_mel_img(safe_load_wav(p)[0]), extensions=(".wav",))

img_idx_by_har = defaultdict(list)
for i, (p,y) in enumerate(img_folder.samples):
    cls = img_folder.classes[y]
    if cls in valid_img_src:
        img_idx_by_har[hmdb_to_har[cls]].append(i)

aud_idx_by_har = defaultdict(list)
for i, (p,y) in enumerate(aud_folder.samples):
    cls = aud_folder.classes[y]
    if cls in esc50_to_har:
        aud_idx_by_har[esc50_to_har[cls]].append(i)

pairs = []
for har in HAR_LABELS:
    n = min(len(img_idx_by_har[har]), len(aud_idx_by_har[har]), MAX_PAIRS_PER_CLASS)
    img_sel = random.sample(img_idx_by_har[har], n)
    aud_sel = random.sample(aud_idx_by_har[har], n)
    for ii, ai in zip(img_sel, aud_sel):
        pairs.append((ii, ai, label_to_idx[har]))
random.shuffle(pairs)

print(f"📦 Created {len(pairs)} random HAR pairs (max {MAX_PAIRS_PER_CLASS}/class)")

# =========================================================
# 🧠 모델 로드
# =========================================================
def build_model(model_path):
    m = models.resnet18(weights=models.ResNet18_Weights.DEFAULT)
    m.fc = torch.nn.Sequential(
        torch.nn.Linear(m.fc.in_features, 512),
        torch.nn.ReLU(),
        torch.nn.Dropout(0.3),
        torch.nn.Linear(512, len(HAR_LABELS))
    )
    sd = torch.load(model_path, map_location="cpu")
    m.load_state_dict(sd, strict=False)
    m.eval().to(DEVICE)
    return m

img_model = build_model(IMG_MODEL_PATH)
aud_model = build_model(AUD_MODEL_PATH)

# =========================================================
# 🔗 Late Fusion Dataset
# =========================================================
class FusionDataset(Dataset):
    def __init__(self, img_folder, aud_folder, pairs):
        self.img_folder = img_folder
        self.aud_folder = aud_folder
        self.pairs = pairs
    def __len__(self): return len(self.pairs)
    def __getitem__(self, i):
        img_i, aud_i, y = self.pairs[i]
        img,_ = self.img_folder[img_i]
        aud,_ = self.aud_folder[aud_i]
        return img, aud, y

loader = DataLoader(FusionDataset(img_folder, aud_folder, pairs), batch_size=BATCH, shuffle=False)

# =========================================================
# 🔍 1) Fusion Weight Grid Search (딱 1번만 실행)
# =========================================================
def eval_with_weights(wi, wa):
    y_true, y_pred = [], []
    with torch.no_grad():
        for img, aud, y in loader:
            img, aud = img.to(DEVICE), aud.to(DEVICE)
            logits_img = img_model(img)
            logits_aud = aud_model(aud)
            logits = wi*logits_img + wa*logits_aud
            pred = logits.argmax(1)
            y_true.extend(y)
            y_pred.extend(pred.cpu().tolist())

    report = classification_report(
        y_true, y_pred,
        target_names=HAR_LABELS,
        output_dict=True,
        zero_division=0
    )
    return report["macro avg"]["f1-score"]

print("🔍 Running Fusion Weight Grid Search...")
weights = [round(x, 2) for x in torch.arange(0, 1.01, 0.05).tolist()]

best_f1 = -1
best_w = (0, 1)

for wi in weights:
    wa = round(1 - wi, 2)
    macro_f1 = eval_with_weights(wi, wa)
    print(f"  - wi={wi}, wa={wa} → Macro-F1={macro_f1:.4f}")
    if macro_f1 > best_f1:
        best_f1 = macro_f1
        best_w = (wi, wa)

print(f"\n🔥 Best Weights Found: W_IMG={best_w[0]}, W_AUD={best_w[1]} | Macro-F1={best_f1:.4f}\n")

# =========================================================
# 🚀 2) Best Weight로 최종 Fusion 평가
# =========================================================
W_IMG, W_AUD = best_w
y_true, y_pred = [], []

with torch.no_grad():
    for img, aud, y in tqdm(loader, desc="Evaluating final fusion"):
        img, aud = img.to(DEVICE), aud.to(DEVICE)
        logits_img = img_model(img)
        logits_aud = aud_model(aud)
        logits = W_IMG*logits_img + W_AUD*logits_aud
        pred = logits.argmax(1)

        y_true.extend(y)
        y_pred.extend(pred.cpu().tolist())

report = classification_report(
    y_true, y_pred,
    target_names=HAR_LABELS,
    output_dict=True,
    zero_division=0
)

acc = report["accuracy"]
macro_f1 = report["macro avg"]["f1-score"]

print(f"✅ Final Fusion Accuracy: {acc*100:.2f}% | Macro-F1: {macro_f1*100:.2f}%")

# =========================================================
# 💾 metrics.json 저장
# =========================================================
labels = list(report.keys())[:-3]
f1_scores = [report[k]["f1-score"] for k in labels]

metrics = {
    "accuracy": round(acc,4),
    "macro_f1": round(macro_f1,4),
    "labels": labels,
    "f1": [round(v,4) for v in f1_scores],
    "best_weights": { "img": W_IMG, "aud": W_AUD }
}

with open(os.path.join(OUTPUT_DIR,"metrics.json"),"w",encoding="utf-8") as f:
    json.dump(metrics,f,indent=2,ensure_ascii=False)

print("💾 Saved metrics:", os.path.join(OUTPUT_DIR,"metrics.json"))
