# py_light/image/prune_image_model.py
import os
import torch
import torch.nn.utils.prune as prune
from torchvision import models

MODEL_PATH = "outputs_light/image_mobilenet_v3/best_model.pth"
OUTPUT_PATH = "outputs_light/image_mobilenet_v3/best_model_pruned.pth"

HAR_LABELS = ["Active", "Interaction", "Locomotion", "Outdoor", "Resting"]

def build_model():
    model = models.mobilenet_v3_small(weights=models.MobileNet_V3_Small_Weights.DEFAULT)
    in_features = model.classifier[3].in_features
    model.classifier[3] = torch.nn.Linear(in_features, len(HAR_LABELS))

    state = torch.load(MODEL_PATH, map_location="cpu")
    model.load_state_dict(state, strict=True)
    model.eval()
    return model

def structured_prune(model, amount=0.3):
    """
    Conv2d 채널 기준 구조적 pruning (진짜 파일 크기 감소 효과)
    """
    print("🔧 Applying structured pruning (Conv2d channels)...")

    for name, module in model.named_modules():
        if isinstance(module, torch.nn.Conv2d) and module.groups == 1:
            try:
                prune.ln_structured(module, name="weight", amount=amount, n=2, dim=0)
                prune.remove(module, "weight")
            except:
                pass

    return model

def main():
    print("📦 Loading model...")
    model = build_model()

    print("✂️ Starting structured pruning...")
    model = structured_prune(model, amount=0.3)

    torch.save(model.state_dict(), OUTPUT_PATH)

    # 사이즈 비교
    original_size = os.path.getsize(MODEL_PATH)/1024/1024
    pruned_size = os.path.getsize(OUTPUT_PATH)/1024/1024
    print(f"💾 Saved pruned model → {OUTPUT_PATH}")
    print(f"📦 Size: original={original_size:.2f} MB → pruned={pruned_size:.2f} MB")

if __name__ == "__main__":
    main()
