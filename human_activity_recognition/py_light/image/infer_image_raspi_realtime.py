# py_light/image/infer_image_raspi_realtime.py
import cv2
import time
import torch
import torch.nn.functional as F
from torchvision import transforms

# =========================================================
# ⚙ 설정
# =========================================================
DEVICE = torch.device("cpu")
IMG_MODEL_PATH = "outputs_light/image_mobilenet_v3/model_script_int8.pt"
IMG_SIZE = 128

HAR_LABELS = ["Active", "Interaction", "Locomotion", "Outdoor", "Resting"]

# 이미지 전처리
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
# 🧠 TorchScript 이미지 모델 로드
# =========================================================
def load_image_model():
    print("📦 Loading image TorchScript model on Raspberry Pi...")
    model = torch.jit.load(IMG_MODEL_PATH, map_location=DEVICE)
    model.eval()
    return model


# =========================================================
# 🎥 실시간 카메라 루프
# =========================================================
def run_realtime_camera(cam_index=0):
    model = load_image_model()

    cap = cv2.VideoCapture(cam_index)
    if not cap.isOpened():
        print(f"❌ Cannot open camera index {cam_index}")
        return

    print("✅ Camera opened. Press 'q' to quit.")

    prev_time = time.time()
    while True:
        ret, frame = cap.read()
        if not ret:
            print("⚠️ Failed to grab frame.")
            break

        # BGR → RGB
        frame_rgb = cv2.cvtColor(frame, cv2.COLOR_BGR2RGB)

        # 전처리 & 추론
        with torch.no_grad():
            img_tensor = img_transform(frame_rgb).unsqueeze(0).to(DEVICE)
            logits = model(img_tensor)
            probs = F.softmax(logits, dim=1).squeeze(0)

        pred_idx = int(probs.argmax().item())
        pred_label = HAR_LABELS[pred_idx]
        confidence = float(probs[pred_idx].item()) * 100.0

        # FPS 계산
        now = time.time()
        fps = 1.0 / (now - prev_time)
        prev_time = now

        # 화면에 출력용 텍스트
        text = f"{pred_label} ({confidence:.1f}%) | FPS: {fps:.1f}"

        cv2.putText(
            frame,
            text,
            (10, 30),
            cv2.FONT_HERSHEY_SIMPLEX,
            0.8,
            (255, 255, 255),
            2,
            cv2.LINE_AA
        )

        cv2.imshow("HAR (Image-only, MobileNetV3, Raspberry Pi)", frame)

        # 콘솔에도 찍고 싶으면:
        # print(text)

        # 'q' 키로 종료
        if cv2.waitKey(1) & 0xFF == ord("q"):
            break

    cap.release()
    cv2.destroyAllWindows()
    print("👋 Realtime HAR stopped.")


if __name__ == "__main__":
    run_realtime_camera(cam_index=0)
