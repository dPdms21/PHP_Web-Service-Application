import os, cv2, subprocess, librosa, numpy as np, torch, json
import torch.nn.functional as F
from torchvision import models, transforms

# =========================================================
# ⚙️ 설정
# =========================================================
VIDEO_PATH = "data/test_videos/run.mp4"
IMG_MODEL_PATH = "outputs/image_fast_v2/best_model.pth"
AUD_MODEL_PATH = "outputs/audio_fast/best_model.pth"
RESULT_PATH = "outputs/fusion_fast/infer_result.json"
DEVICE = torch.device("cpu")

IMG_SIZE = 128
SR = 44100
N_MELS = 64
W_IMG, W_AUD = 0.6, 0.4
TEMP_DIR = "temp_infer"

os.makedirs(TEMP_DIR, exist_ok=True)
os.makedirs("outputs/fusion_fast", exist_ok=True)

HAR_LABELS = ["Active", "Interaction", "Locomotion", "Outdoor", "Resting"]


# =========================================================
# 🎞 영상 프레임 추출 (OpenCV)
# =========================================================
def extract_frames(video_path, out_dir=TEMP_DIR, fps=3):
    os.makedirs(out_dir, exist_ok=True)
    cap = cv2.VideoCapture(video_path)
    frame_rate = int(cap.get(cv2.CAP_PROP_FPS))
    interval = max(1, frame_rate // fps)

    frames = []
    count = 0

    while cap.isOpened():
        ret, frame = cap.read()
        if not ret:
            break
        if count % interval == 0:
            frame_rgb = cv2.cvtColor(frame, cv2.COLOR_BGR2RGB)
            frames.append(frame_rgb)
        count += 1
    cap.release()

    if frames:
        thumb_path = os.path.join(out_dir, "frame_000.jpg")
        cv2.imwrite(thumb_path, cv2.cvtColor(frames[0], cv2.COLOR_RGB2BGR))

    print(f"🖼️ Extracted {len(frames)} frames from video.")
    return frames


# =========================================================
# 🎧 오디오 추출 (ffmpeg)
# =========================================================
def extract_audio(video_path, audio_path=os.path.join(TEMP_DIR, "temp_audio.wav")):
    cmd = [
        "ffmpeg", "-y", "-i", video_path,
        "-vn", "-ac", "1", "-ar", str(SR), "-f", "wav", audio_path
    ]
    subprocess.run(cmd, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    print(f"🎧 Audio extracted to {audio_path}")
    return audio_path


# =========================================================
# 🔊 MelSpectrogram 변환
# =========================================================
def wav_to_mel_tensor(wav_path, n_mels=N_MELS, sr=SR):
    y, _ = librosa.load(wav_path, sr=sr)
    mel = librosa.feature.melspectrogram(y=y, sr=sr, n_mels=n_mels)
    mel_db = librosa.power_to_db(mel, ref=np.max)
    mel_norm = (mel_db - mel_db.mean()) / (mel_db.std() + 1e-6)

    tensor = torch.tensor(mel_norm).unsqueeze(0).repeat(3, 1, 1)
    tensor = transforms.Resize((IMG_SIZE, IMG_SIZE))(tensor)

    print(f"📈 MelSpectrogram shape: {tuple(tensor.shape)}")
    return tensor


# =========================================================
# 🧩 이미지 변환
# =========================================================
img_transform = transforms.Compose([
    transforms.ToPILImage(),
    transforms.Resize((IMG_SIZE, IMG_SIZE)),
    transforms.ToTensor(),
    transforms.Normalize(mean=[0.485, 0.456, 0.406],
                         std=[0.229, 0.224, 0.225])
])


# =========================================================
# 🧠 모델 로드
# =========================================================
def build_model(model_path):
    model = models.resnet18(weights=models.ResNet18_Weights.DEFAULT)
    model.fc = torch.nn.Sequential(
        torch.nn.Linear(model.fc.in_features, 512),
        torch.nn.ReLU(),
        torch.nn.Dropout(0.3),
        torch.nn.Linear(512, len(HAR_LABELS))
    )
    model.load_state_dict(torch.load(model_path, map_location=DEVICE), strict=False)
    model.eval().to(DEVICE)
    return model


print("📦 Loading models...")
img_model = build_model(IMG_MODEL_PATH)
aud_model = build_model(AUD_MODEL_PATH)


# =========================================================
# 🚀 Fusion 예측
# =========================================================
def predict_fusion(video_path):
    video_name = os.path.basename(video_path)

    # 1. 프레임 + 오디오 추출
    frames = extract_frames(video_path)
    audio_path = extract_audio(video_path)
    audio_tensor = wav_to_mel_tensor(audio_path).unsqueeze(0).to(DEVICE)

    # 2. 이미지 모델 예측
    img_logits_list = []
    for frame in frames:
        img_tensor = img_transform(frame).unsqueeze(0).to(DEVICE)
        with torch.no_grad():
            logit = img_model(img_tensor)
        img_logits_list.append(logit)
    img_logits = torch.stack(img_logits_list).mean(dim=0)

    # 3. 오디오 예측
    with torch.no_grad():
        aud_logits = aud_model(audio_tensor)

    # 4. Late Fusion
    fused = W_IMG * img_logits + W_AUD * aud_logits
    probs = F.softmax(fused, dim=1).squeeze()

    pred_idx = probs.argmax().item()
    pred_label = HAR_LABELS[pred_idx]
    confidence = float(probs[pred_idx].item())

    # 5. Top 3
    top3_idx = probs.topk(3).indices.tolist()
    top3 = [
        {"label": HAR_LABELS[i], "prob": float(probs[i].item())}
        for i in top3_idx
    ]

    # 6. JSON 저장
    result = {
        "video_name": video_name,
        "predicted_action": pred_label,
        "confidence": confidence,
        "top3": top3
    }

    with open(RESULT_PATH, "w", encoding="utf-8") as f:
        json.dump(result, f, ensure_ascii=False, indent=2)

    print(f"Saved result → {RESULT_PATH}")
    return pred_label, confidence


# =========================================================
# 🧹 실행부
# =========================================================
if __name__ == "__main__":
    if not os.path.exists(VIDEO_PATH):
        print(f"❌ Video not found: {VIDEO_PATH}")
    else:
        predict_fusion(VIDEO_PATH)

        # temp 오디오 제거
        temp_audio = os.path.join(TEMP_DIR, "temp_audio.wav")
        if os.path.exists(temp_audio):
            os.remove(temp_audio)
