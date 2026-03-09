# py_light/audio/prune_audio_model.py
import os
import torch
import torch.nn.utils.prune as prune
from torchvision import models
import torch.nn as nn

DEVICE = torch.device("cpu")

# =========================================================
# ⚙️ 설정
# =========================================================
MODEL_TYPE  = "mobilenet_v3"   # "mobilenet_v2" 또는 "mobilenet_v3"
INPUT_DIR   = "outputs_light/audio_mobilenet_v3"
OUTPUT_PATH = os.path.join(INPUT_DIR, "pruned_model.pth")

NUM_CLASSES = 5  # Active, Interaction, Locomotion, Outdoor, Resting

PRUNE_AMOUNT = 0.3  # 30% 제거

# =========================================================
# 🧠 모델 로더
# =========================================================
def build_model(model_type: str):
    if model_type == "mobilenet_v2":
        weights = None  # 구조만 필요
        model = models.mobilenet_v2(weights=weights)
        in_features = model.classifier[1].in_features
        model.classifier[1] = nn.Linear(in_features, NUM_CLASSES)
        state_path = os.path.join("outputs_light/audio_mobilenet_v2", "best_model.pth")
    else:
        weights = None
        model = models.mobilenet_v3_small(weights=weights)
        in_features = model.classifier[3].in_features
        model.classifier[3] = nn.Linear(in_features, NUM_CLASSES)
        state_path = os.path.join("outputs_light/audio_mobilenet_v3", "best_model.pth")

    sd = torch.load(state_path, map_location=DEVICE)
    model.load_state_dict(sd, strict=False)
    model.to(DEVICE).eval()
    return model

# =========================================================
# ✂️ Pruning 적용
# =========================================================
def apply_pruning(model):
    total_params = 0
    for name, module in model.named_modules():
        if isinstance(module, torch.nn.Conv2d):
            # Conv layer에 L1 Unstructured Pruning 적용
            prune.l1_unstructured(module, name="weight", amount=PRUNE_AMOUNT)
            prune.remove(module, "weight")  # reparam 제거
        total_params += sum(p.numel() for p in module.parameters() if p.requires_grad)

    return model

if __name__ == "__main__":
    os.makedirs(os.path.dirname(OUTPUT_PATH), exist_ok=True)

    model = build_model(MODEL_TYPE)
    print(f"✅ Loaded {MODEL_TYPE} audio model for pruning.")

    model = apply_pruning(model)
    torch.save(model.state_dict(), OUTPUT_PATH)

    print(f"✂️ Pruning finished. Saved pruned model → {OUTPUT_PATH}")
