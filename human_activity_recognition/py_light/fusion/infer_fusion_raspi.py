# py_light/fusion/infer_fusion_raspi.py
import os
import cv2
import argparse
import subprocess
import json
import numpy as np
import torch
import torch.nn.functional as F
import librosa
from torchvision import transforms

# =========================================================
# ⚙ 설정
# =========================================================
DEVICE = torch.device("cpu")

IMG_MODEL_PATH = "outputs_light/image_mobilenet_v3/model_script_int8.pt"  # 이미지 INT8 TorchScript
AUD_MODEL_PATH = "outputs_light/audio_mobilenet_v3/model_script.pt"       # 오디오 TorchScript

RESULT_DIR  = "outputs_light/fusion_raspi"
RESULT_PATH = os.path.join(RESULT_DIR, "infer_result.json")
TEMP_DIR    = "temp_infer_raspi"

os.makedirs(RESULT_DIR, exist_ok=True)
os.makedirs(TEMP_DIR, exist_ok=True)

IMG_SIZE = 128
SR       = 44100
N_MELS   = 64
FPS_SAMPLING = 2          # 초당 2프레임만 사용 (라즈베리파이용)
MAX_FRAMES   = 30         # 최대 30프레임까지만 사용

W_IMG, W_AUD = 0.6, 0.4

HAR_LABELS = ["Active", "Interaction", "Locomotion", "Outdoor", "Resting"]


# =========================================================
# 🎞 영상 프레임 추출
# =========================================================
def extract_frames(video_path, out_dir=TEMP_DIR, fps=FPS_SAMPLING):
    os.makedirs(out_dir, exist_ok=True)
    cap = cv2.VideoCapture(video_path)
    if not cap.isOpened():
        raise RuntimeError(f"Failed to open video: {video_path}")

    frame_rate = cap.get(cv2.CAP_PROP_FPS)
    if frame_rate <= 0:
        frame_rate = fps * 2  # fallback

    interval = max(1, int(frame_rate // fps))

    frames = []
    count = 0

    while cap.isOpened():
        ret, frame = cap.read()
        if not ret:
            break
        if count % interval == 0:
            frame_rgb = cv2.cvtColor(frame, cv2.COLOR_BGR2RGB)
            frames.append(frame_rgb)
            if len(frames) >= MAX_FRAMES:
                break
        count += 1

    cap.release()

    # 썸네일 저장 (필요하면 PHP에서 사용)
    if frames:
        thumb_path = os.path.join(out_dir, "frame_000.jpg")
        cv2.imwrite(thumb_path, cv2.cvtColor(frames[0], cv2.COLOR_RGB2BGR))

    print(f"🖼️ Extracted {len(frames)} frames from video.")
    return frames


# =========================================================
# 🎧 오디오 추출 (ffmpeg)
# =========================================================
def extract_audio(video_path, audio_path):
    cmd = [
        "ffmpeg", "-y", "-i", video_path,
        "-vn", "-ac", "1", "-ar", str(SR), "-f", "wav", audio_path
    ]
    subprocess.run(cmd, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    if not os.path.exists(audio_path):
        raise RuntimeError(f"Failed to extract audio: {audio_path}")
    print(f"🎧 Audio extracted to {audio_path}")
    return audio_path


# =========================================================
# 🔊 MelSpectrogram 변환 (오디오)
# =========================================================
def wav_to_mel_tensor(wav_path, n_mels=N_MELS, sr=SR):
    y, _ = librosa.load(wav_path, sr=sr)
    mel = librosa.feature.melspectrogram(y=y, sr=sr, n_mels=n_mels)
    mel_db = librosa.power_to_db(mel, ref=np.max)
    mel_norm = (mel_db - mel_db.mean()) / (mel_db.std() + 1e-6)

    tensor = torch.tensor(mel_norm, dtype=torch.float32).unsqueeze(0).repeat(3, 1, 1)
    tensor = transforms.Resize((IMG_SIZE, IMG_SIZE))(tensor)

    print(f"📈 MelSpectrogram shape: {tuple(tensor.shape)}")
    return tensor


# =========================================================
# 🧩 이미지 변환 (프레임 → 텐서)
# =========================================================
img_transform = transforms.Compose([
    transforms.ToPILImage(),
    transforms.Resize((IMG_SIZE, IMG_SIZE)),
    transforms.ToTensor(),
    transforms.Normalize(
        mean=[0.485, 0.456, 0.406],
        std =[0.229, 0.224, 0.225]
    )
])


# =========================================================
# 🧠 TorchScript 모델 로드
# =========================================================
def load_models():
    print("📦 Loading TorchScript models on Raspberry Pi...")
    img_model = torch.jit.load(IMG_MODEL_PATH, map_location=DEVICE)
    aud_model = torch.jit.load(AUD_MODEL_PATH, map_location=DEVICE)

    img_model.eval()
    aud_model.eval()

    return img_model, aud_model


# =========================================================
# 🚀 Fusion 추론
# =========================================================
def predict_fusion(video_path):
    video_name = os.path.basename(video_path)

    # 1) 모델 로드
    img_model, aud_model = load_models()

    # 2) 프레임 / 오디오 추출
    frames = extract_frames(video_path, out_dir=TEMP_DIR, fps=FPS_SAMPLING)
    if len(frames) == 0:
        raise RuntimeError("No frames extracted from video.")

    audio_path = os.path.join(TEMP_DIR, "temp_audio.wav")
    extract_audio(video_path, audio_path)
    audio_tensor = wav_to_mel_tensor(audio_path).unsqueeze(0).to(DEVICE)

    # 3) 이미지 모델 추론 (프레임 평균)
    img_logits_list = []
    with torch.no_grad():
        for frame in frames:
            img_tensor = img_transform(frame).unsqueeze(0).to(DEVICE)
            logit = img_model(img_tensor)
            img_logits_list.append(logit)

    img_logits = torch.stack(img_logits_list).mean(dim=0)

    # 4) 오디오 모델 추론
    with torch.no_grad():
        aud_logits = aud_model(audio_tensor)

    # 5) Late Fusion
    fused = W_IMG * img_logits + W_AUD * aud_logits
    probs = F.softmax(fused, dim=1).squeeze(0)

    pred_idx   = int(probs.argmax().item())
    pred_label = HAR_LABELS[pred_idx]
    confidence = float(probs[pred_idx].item())

    top3_probs, top3_idx = torch.topk(probs, k=3)
    top3 = [
        {"label": HAR_LABELS[int(i)], "prob": float(p)}
        for p, i in zip(top3_probs, top3_idx)
    ]

    print("======================================")
    print(f"🎯 Predicted Action: {pred_label}")
    print(f"🔥 Confidence: {confidence*100:.2f}%")
    print("======================================")

    # 6) JSON 저장 (PHP, 로그용)
    result = {
        "video_name": video_name,
        "predicted_action": pred_label,
        "confidence": confidence,
        "top3": top3
    }

    with open(RESULT_PATH, "w", encoding="utf-8") as f:
        json.dump(result, f, ensure_ascii=False, indent=2)

    print(f"💾 Saved fusion result → {RESULT_PATH}")

    # temp 오디오 삭제
    if os.path.exists(audio_path):
        os.remove(audio_path)

    return result


# =========================================================
# 🧹 main
# =========================================================
def main():
    parser = argparse.ArgumentParser()
    parser.add_argument(
        "--video",
        type=str,
        default="data/test_videos/run.mp4",
        help="Input video path (mp4)"
    )
    args = parser.parse_args()

    if not os.path.exists(args.video):
        print(f"❌ Video not found: {args.video}")
        return

    predict_fusion(args.video)


if __name__ == "__main__":
    main()
