import sys
import json
import os
import traceback

import warnings
warnings.filterwarnings("ignore")

import torch
import torchaudio
import torchaudio.transforms as T
from torchvision import models, transforms
from torchvision.models import ResNet18_Weights

# =========================================================
# 📌 경로 설정
# =========================================================
CURRENT = os.path.dirname(__file__)
ROOT = os.path.dirname(os.path.dirname(CURRENT))
MODEL_PATH = os.path.join(ROOT, "outputs", "audio_fast", "best_model.pth")

DATA_SAMPLE_RATE = 44100
N_MELS = 64
IMG_SIZE = 128

labels = ["Active", "Interaction", "Locomotion", "Outdoor", "Resting"]

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

mel_transform = T.MelSpectrogram(
    sample_rate=DATA_SAMPLE_RATE, n_fft=1024,
    hop_length=256, n_mels=N_MELS
)
amp_to_db = T.AmplitudeToDB()
resize_tf = transforms.Resize((IMG_SIZE, IMG_SIZE))

def waveform_to_mel(wav: torch.Tensor) -> torch.Tensor:
    if wav.size(0) > 1:
        wav = wav.mean(dim=0, keepdim=True)
    mel = mel_transform(wav)
    mel = amp_to_db(mel)
    mel = (mel - mel.mean()) / (mel.std() + 1e-6)
    mel = mel.repeat(3, 1, 1)
    mel = resize_tf(mel)
    return mel

try:
    if len(sys.argv) < 2:
        print(json.dumps({"error": "no_file"}))
        sys.exit(0)

    audio_path = sys.argv[1]

    if not os.path.exists(audio_path):
        print(json.dumps({"error": "file_not_found", "path": audio_path}))
        sys.exit(0)

    wav, sr = load_waveform(audio_path)
    mel = waveform_to_mel(wav)
    x = mel.unsqueeze(0).float()

    model = models.resnet18(weights=ResNet18_Weights.DEFAULT)

    for p in model.layer1.parameters():
        p.requires_grad = False
    for p in model.layer2.parameters():
        p.requires_grad = False

    model.fc = torch.nn.Sequential(
        torch.nn.Linear(model.fc.in_features, 512),
        torch.nn.ReLU(),
        torch.nn.Dropout(0.3),
        torch.nn.Linear(512, len(labels)),
    )

    sd = torch.load(MODEL_PATH, map_location="cpu")
    model.load_state_dict(sd, strict=False)
    model.eval()

    with torch.no_grad():
        out = model(x)
        prob = torch.softmax(out, dim=1)[0]
        conf, idx = torch.max(prob, dim=0)

    print(json.dumps({
        "predicted_action": labels[idx.item()],
        "confidence": float(conf)
    }))

except Exception as e:
    print(json.dumps({
        "error": "exception",
        "message": str(e),
        "trace": traceback.format_exc(),
    }))