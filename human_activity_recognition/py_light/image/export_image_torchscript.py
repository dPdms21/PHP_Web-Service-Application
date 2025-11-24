# py_light/image/export_image_torchscript.py
import os
import torch
from torchvision import models

MODEL_PATH = "outputs_light/image_mobilenet_v3/best_model_pruned.pth"
OUTPUT_FP32 = "outputs_light/image_mobilenet_v3/model_script_fp32.pt"
OUTPUT_INT8 = "outputs_light/image_mobilenet_v3/model_script_int8.pt"

IMG_SIZE = 128
HAR_LABELS = ["Active", "Interaction", "Locomotion", "Outdoor", "Resting"]

def load_model():
    model = models.mobilenet_v3_small(weights=models.MobileNet_V3_Small_Weights.DEFAULT)
    in_features = model.classifier[3].in_features
    model.classifier[3] = torch.nn.Linear(in_features, len(HAR_LABELS))
    model.load_state_dict(torch.load(MODEL_PATH, map_location="cpu"), strict=True)
    model.eval()
    return model

def main():
    print("📦 Loading pruned model...")
    model_fp32 = load_model()

    dummy = torch.randn(1, 3, IMG_SIZE, IMG_SIZE)

    # =============== FP32 TorchScript (비교용) ===============
    script_fp32 = torch.jit.trace(model_fp32, dummy)
    script_fp32.save(OUTPUT_FP32)
    print(f"💾 Saved FP32 TorchScript → {OUTPUT_FP32}")

    # =============== INT8 Dynamic Quantization ===============
    print("⚙️ Applying dynamic INT8 quantization...")
    model_int8 = torch.quantization.quantize_dynamic(
        model_fp32,
        {torch.nn.Linear},
        dtype=torch.qint8
    )

    script_int8 = torch.jit.trace(model_int8, dummy)
    script_int8.save(OUTPUT_INT8)
    print(f"💾 Saved INT8 TorchScript → {OUTPUT_INT8}")

    # 사이즈 비교
    fp32_size = os.path.getsize(OUTPUT_FP32) / 1024 / 1024
    int8_size = os.path.getsize(OUTPUT_INT8) / 1024 / 1024

    print(f"📦 Size comparison: FP32={fp32_size:.2f}MB → INT8={int8_size:.2f}MB")
    print("✅ Use model_script_int8.pt on Raspberry Pi.")

if __name__ == "__main__":
    main()
