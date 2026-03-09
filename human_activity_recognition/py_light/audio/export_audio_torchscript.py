# py_light/audio/export_audio_torchscript.py
import os
import torch
import torch.nn as nn
from torchvision import models

DEVICE = torch.device("cpu")

# =========================================================
# ⚙️ 설정
# =========================================================
MODEL_TYPE    = "mobilenet_v3"   # "mobilenet_v2" 또는 "mobilenet_v3"
NUM_CLASSES   = 5
INPUT_DIR_V2  = "outputs_light/audio_mobilenet_v2"
INPUT_DIR_V3  = "outputs_light/audio_mobilenet_v3"
USE_PRUNED    = True  # True면 pruned_model.pth, False면 best_model.pth 사용

if MODEL_TYPE == "mobilenet_v2":
    BASE_DIR = INPUT_DIR_V2
else:
    BASE_DIR = INPUT_DIR_V3

STATE_PATH = os.path.join(BASE_DIR, "pruned_model.pth" if USE_PRUNED else "best_model.pth")
SCRIPT_OUT = os.path.join(BASE_DIR, "model_script.pt")

# 입력 텐서 샘플 shape (1, 3, 128, 128)
DUMMY_INPUT_SHAPE = (1, 3, 128, 128)

# =========================================================
# 🧠 모델 구성
# =========================================================
def build_model(model_type: str):
    if model_type == "mobilenet_v2":
        model = models.mobilenet_v2(weights=None)
        in_features = model.classifier[1].in_features
        model.classifier[1] = nn.Linear(in_features, NUM_CLASSES)
    else:
        model = models.mobilenet_v3_small(weights=None)
        in_features = model.classifier[3].in_features
        model.classifier[3] = nn.Linear(in_features, NUM_CLASSES)

    sd = torch.load(STATE_PATH, map_location=DEVICE)
    model.load_state_dict(sd, strict=False)
    model.to(DEVICE).eval()
    return model

if __name__ == "__main__":
    os.makedirs(BASE_DIR, exist_ok=True)

    model = build_model(MODEL_TYPE)
    print(f"✅ Loaded {MODEL_TYPE} audio model from: {STATE_PATH}")

    dummy = torch.randn(*DUMMY_INPUT_SHAPE).to(DEVICE)

    # trace 방식 사용 (입력이 고정된 CNN이라 안전)
    scripted = torch.jit.trace(model, dummy)
    scripted.save(SCRIPT_OUT)

    print(f"💾 Exported TorchScript model → {SCRIPT_OUT}")
