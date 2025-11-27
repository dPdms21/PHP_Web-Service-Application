# py/fusion_infer.py
import os, sys, json, traceback
import torch
import torch.nn as nn
import torch.nn.functional as F
from torchvision import models, transforms
from torchvision.models import ResNet18_Weights
from PIL import Image
from moviepy.video.io.VideoFileClip import VideoFileClip
import torchaudio
import torchaudio.transforms as T
import soundfile as sf

DEVICE = torch.device("cpu")

IMG_SIZE = 128
SAMPLE_RATE = 44100
N_MELS = 64

HAR_LABELS = ["Active", "Interaction", "Locomotion", "Outdoor", "Resting"]
W_IMG = 0.6
W_AUD = 0.4

CURRENT = os.path.dirname(__file__)
ROOT = os.path.dirname(os.path.dirname(CURRENT))

IMG_MODEL_PATH = os.path.join(ROOT, "outputs/image_fast_v2/best_model.pth")
AUD_MODEL_PATH = os.path.join(ROOT, "outputs/audio_fast/best_model.pth")
OUT_JSON_DIR   = os.path.join(ROOT, "outputs/fusion_fast")
os.makedirs(OUT_JSON_DIR, exist_ok=True)

img_tf = transforms.Compose([
    transforms.Resize((IMG_SIZE, IMG_SIZE)),
    transforms.ToTensor(),
    transforms.Normalize([0.485,0.456,0.406],[0.229,0.224,0.225])
])

mel_tf = T.MelSpectrogram(sample_rate=SAMPLE_RATE, n_fft=1024, hop_length=256, n_mels=N_MELS)
amp_to_db = T.AmplitudeToDB()
resize_tf = transforms.Resize((IMG_SIZE, IMG_SIZE))

def load_wav(x):
    if isinstance(x, str):
        try:
            wav, sr = torchaudio.load(x, backend="soundfile")
        except:
            raw, sr = sf.read(x, dtype="float32", always_2d=False)
            wav = torch.tensor(raw).unsqueeze(0) if raw.ndim == 1 else torch.tensor(raw).T
        return wav
    else:
        if x.ndim == 1: return torch.tensor(x).unsqueeze(0)
        return torch.tensor(x).T

def wav_to_mel(wav):
    wav = wav.to(torch.float32)

    if wav.size(0) > 1:
        wav = wav.mean(dim=0, keepdim=True)

    mel = mel_tf(wav)
    mel = amp_to_db(mel)
    mel = (mel - mel.mean()) / (mel.std() + 1e-6)
    mel = mel.repeat(3,1,1)
    mel = resize_tf(mel)
    return mel.unsqueeze(0)

def build_model(path):
    m = models.resnet18(weights=ResNet18_Weights.DEFAULT)
    m.fc = nn.Sequential(
        nn.Linear(m.fc.in_features, 512),
        nn.ReLU(),
        nn.Dropout(0.3),
        nn.Linear(512, len(HAR_LABELS))
    )
    sd = torch.load(path, map_location="cpu")
    m.load_state_dict(sd, strict=False)
    m.eval()
    return m

def extract(video_path):
    clip = VideoFileClip(video_path)
    t = clip.duration / 2
    frame = clip.get_frame(t)
    img = Image.fromarray(frame).convert("RGB")

    if clip.audio:
        audio_np = clip.audio.to_soundarray(fps=SAMPLE_RATE)
        wav = load_wav(audio_np)
    else:
        wav = torch.zeros(1, SAMPLE_RATE * 3)

    clip.close()
    return img, wav

def infer(video_path):
    img, wav = extract(video_path)
    x_img = img_tf(img).unsqueeze(0)
    x_aud = wav_to_mel(wav)

    img_m = build_model(IMG_MODEL_PATH)
    aud_m = build_model(AUD_MODEL_PATH)

    with torch.no_grad():
        li = img_m(x_img)
        la = aud_m(x_aud)
        logits = W_IMG * li + W_AUD * la
        pr = F.softmax(logits, 1)[0]

    conf, idxs = torch.topk(pr, 3)
    top3 = [{"label": HAR_LABELS[i], "prob": float(c)} for i,c in zip(idxs.tolist(), conf.tolist())]

    return {
        "video_name": os.path.basename(video_path),
        "predicted_action": HAR_LABELS[idxs[0].item()],
        "confidence": float(conf[0]),
        "top3": top3
    }

if __name__ == "__main__":
    if len(sys.argv) < 2:
        print(json.dumps({"error": "no video path"}))
        sys.exit(1)

    video_path = sys.argv[1]

    result = infer(video_path)
    print(json.dumps(result, ensure_ascii=False))
