# py/image/02_predict_action_probs.py
import os, argparse, csv

import numpy as np
import torch
import torch.nn as nn
from torch.utils.data import DataLoader, Subset
from torchvision import datasets, transforms, models

def load_class_names(class_file):
    with open(class_file, "r", encoding="utf-8") as f:
        classes = [line.strip() for line in f if line.strip()]
    return classes

def build_model(name: str, num_classes: int):
    name = name.lower()
    if name == "mobilenet_v3_small":
        m = models.mobilenet_v3_small(weights=None)
        in_f = m.classifier[-1].in_features
        m.classifier[-1] = nn.Linear(in_f, num_classes)
        return m
    elif name == "resnet18":
        m = models.resnet18(weights=None)
        in_f = m.fc.in_features
        m.fc = nn.Linear(in_f, num_classes)
        return m
    elif name == "efficientnet_b0":
        m = models.efficientnet_b0(weights=None)
        in_f = m.classifier[1].in_features
        m.classifier[1] = nn.Linear(in_f, num_classes)
        return m
    elif name == "convnext_tiny":
        m = models.convnext_tiny(weights=None)
        in_f = m.classifier[2].in_features
        m.classifier[2] = nn.Linear(in_f, num_classes)
        return m
    else:
        raise ValueError(f"Unknown model {name}")

def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--data_dir", default="data/Stanford40")
    ap.add_argument("--out_dir", default="outputs/image_action")
    ap.add_argument("--model", default="resnet18")
    ap.add_argument("--ckpt",  default="outputs/image_action/best.ckpt")
    ap.add_argument("--img_size", type=int, default=224)
    ap.add_argument("--bs", type=int, default=64)
    # 전체에서 랜덤 샘플 일부만 뽑아서 예측하고 싶을 때
    ap.add_argument("--max_samples", type=int, default=0)  # 0이면 전체
    args = ap.parse_args()

    device = "cuda" if torch.cuda.is_available() else "cpu"
    classes = load_class_names(os.path.join(args.out_dir, "class_names.txt"))
    num_classes = len(classes)

    tf = transforms.Compose([
        transforms.Resize(int(args.img_size*1.14)),
        transforms.CenterCrop(args.img_size),
        transforms.ToTensor(),
        transforms.Normalize(mean=(0.485,0.456,0.406), std=(0.229,0.224,0.225)),
    ])
    ds = datasets.ImageFolder(root=args.data_dir, transform=tf)
    if args.max_samples and args.max_samples > 0 and args.max_samples < len(ds):
        # 클래스 분포 유지보다 간단히 랜덤 subset
        indices = np.random.choice(len(ds), size=args.max_samples, replace=False)
        ds = Subset(ds, indices)

    loader = DataLoader(ds, batch_size=args.bs, shuffle=False, num_workers=2)

    model = build_model(args.model, num_classes).to(device)
    model.load_state_dict(torch.load(args.ckpt, map_location=device))
    model.eval()

    out_csv = os.path.join(args.out_dir, "image_probs.csv")
    with open(out_csv, "w", newline="", encoding="utf-8") as f:
        w = csv.writer(f)
        header = ["sample_id", "true_class", "pred_class", "pred_score"] + [f"p_{c}" for c in classes]
        w.writerow(header)

        softmax = nn.Softmax(dim=1)
        sample_id = 0
        with torch.no_grad():
            for xb, yb in loader:
                xb = xb.to(device)
                logits = model(xb)                  # (B, C)
                probs = softmax(logits).cpu().numpy()
                preds = probs.argmax(axis=1)
                scores = probs.max(axis=1)

                y_true = yb.numpy() if isinstance(yb, torch.Tensor) else np.zeros(len(preds), dtype=int)

                for i in range(len(preds)):
                    w.writerow([
                        sample_id,
                        classes[y_true[i]] if y_true[i] < len(classes) else "",
                        classes[preds[i]],
                        float(scores[i]),
                        *[float(p) for p in probs[i].tolist()]
                    ])
                    sample_id += 1

    print(f"Saved: {out_csv}")

if __name__ == "__main__":
    main()
