import sys
import json
import torch
from torchvision import transforms, models
from PIL import Image
import os
import traceback

# =========================================================
# 📌 절대경로 설정 (정확한 프로젝트 루트 계산)
# =========================================================

CURRENT = os.path.dirname(__file__)               # /py/infer
ROOT = os.path.dirname(os.path.dirname(CURRENT))  # /py → /project_root

MODEL_PATH = os.path.join(ROOT, "outputs", "image_fast_v2", "best_model.pth")

IMG_SIZE = 128
labels = ["Active", "Interaction", "Locomotion", "Outdoor", "Resting"]

try:
    # -----------------------------------------------------
    # 1) 인자 체크
    # -----------------------------------------------------
    if len(sys.argv) < 2:
        print(json.dumps({"error": "no_file"}))
        sys.exit(0)

    img_path = sys.argv[1]

    if not os.path.exists(img_path):
        print(json.dumps({"error": "file_not_found", "path": img_path}))
        sys.exit(0)

    # -----------------------------------------------------
    # 2) 이미지 로드
    # -----------------------------------------------------
    img = Image.open(img_path).convert("RGB")

    transform = transforms.Compose([
        transforms.Resize((IMG_SIZE, IMG_SIZE)),
        transforms.ToTensor(),
        transforms.Normalize(
            mean=[0.485, 0.456, 0.406],
            std=[0.229, 0.224, 0.225]
        )
    ])

    x = transform(img).unsqueeze(0)

    # -----------------------------------------------------
    # 3) 모델 구성
    # -----------------------------------------------------
    model = models.resnet18(weights=None)
    model.fc = torch.nn.Sequential(
        torch.nn.Linear(model.fc.in_features, 512),
        torch.nn.ReLU(),
        torch.nn.Dropout(0.3),
        torch.nn.Linear(512, len(labels))
    )

    # -----------------------------------------------------
    # 4) 모델 로드
    # -----------------------------------------------------
    if not os.path.exists(MODEL_PATH):
        print(json.dumps({"error": "model_not_found", "model_path": MODEL_PATH}))
        sys.exit(0)

    sd = torch.load(MODEL_PATH, map_location="cpu")
    model.load_state_dict(sd, strict=False)
    model.eval()

    # -----------------------------------------------------
    # 5) 예측
    # -----------------------------------------------------
    with torch.no_grad():
        out = model(x)
        prob = torch.softmax(out, dim=1)[0]
        conf, idx = torch.max(prob, dim=0)

    pred_label = labels[idx.item()]

    # -----------------------------------------------------
    # 6) JSON 출력 (PHP가 받는 값)
    # -----------------------------------------------------
    print(json.dumps({
        "predicted_action": pred_label,
        "confidence": float(conf)
    }))

except Exception as e:
    # -----------------------------------------------------
    # 7) 에러 JSON 출력
    # -----------------------------------------------------
    print(json.dumps({
        "error": "exception",
        "message": str(e),
        "trace": traceback.format_exc()
    }))
