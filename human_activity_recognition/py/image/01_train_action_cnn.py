# py/image/01_train_action_cnn.py
import os, argparse, csv, json, random, itertools
from datetime import datetime

import numpy as np
import torch
import torch.nn as nn
import torch.optim as optim

from torch.utils.data import DataLoader, random_split, Subset
from torchvision import datasets, transforms, models

import matplotlib.pyplot as plt
from sklearn.metrics import confusion_matrix, classification_report

def set_seed(seed: int = 42):
    random.seed(seed); np.random.seed(seed); torch.manual_seed(seed); torch.cuda.manual_seed_all(seed)

def save_metrics_csv(path, split, metrics: dict, epoch=None):
    os.makedirs(os.path.dirname(path), exist_ok=True)
    newfile = not os.path.exists(path)
    with open(path, "a", newline="", encoding="utf-8") as f:
        w = csv.writer(f)
        if newfile:
            w.writerow(["split","metric","value","epoch"])
        for k,v in metrics.items():
            w.writerow([split, k, float(v), epoch if epoch is not None else ""])

def plot_confusion_matrix(cm, classes, out_path):
    fig = plt.figure(figsize=(7,7))
    plt.imshow(cm, interpolation="nearest")
    plt.title("Confusion matrix"); plt.colorbar()
    ticks = np.arange(len(classes))
    plt.xticks(ticks, classes, rotation=90)
    plt.yticks(ticks, classes)
    thresh = cm.max()/2
    for i, j in itertools.product(range(cm.shape[0]), range(cm.shape[1])):
        c = "white" if cm[i, j] > thresh else "black"
        plt.text(j, i, str(cm[i, j]), ha="center", va="center", color=c, fontsize=7)
    plt.ylabel("True"); plt.xlabel("Pred")
    plt.tight_layout()
    fig.savefig(out_path, dpi=150, bbox_inches="tight")
    plt.close(fig)

def build_model(name: str, num_classes: int):
    name = name.lower()
    if name == "resnet18":
        m = models.resnet18(weights=models.ResNet18_Weights.IMAGENET1K_V1)
        in_f = m.fc.in_features
        m.fc = nn.Linear(in_f, num_classes)
        return m
    elif name == "efficientnet_b0":
        m = models.efficientnet_b0(weights=models.EfficientNet_B0_Weights.IMAGENET1K_V1)
        in_f = m.classifier[1].in_features
        m.classifier[1] = nn.Linear(in_f, num_classes)
        return m
    elif name == "convnext_tiny":
        m = models.convnext_tiny(weights=models.ConvNeXt_Tiny_Weights.IMAGENET1K_V1)
        in_f = m.classifier[2].in_features
        m.classifier[2] = nn.Linear(in_f, num_classes)
        return m
    else:
        raise ValueError(f"Unknown model {name}")

def get_transforms(img_size=224):
    train_tf = transforms.Compose([
        transforms.RandomResizedCrop(img_size),
        transforms.RandomHorizontalFlip(),
        transforms.ColorJitter(0.2,0.2,0.2,0.1),
        transforms.ToTensor(),
        transforms.Normalize(mean=(0.485,0.456,0.406), std=(0.229,0.224,0.225)),
    ])
    val_tf = transforms.Compose([
        transforms.Resize(int(img_size*1.14)),
        transforms.CenterCrop(img_size),
        transforms.ToTensor(),
        transforms.Normalize(mean=(0.485,0.456,0.406), std=(0.229,0.224,0.225)),
    ])
    return train_tf, val_tf

def split_dataset(full_ds, val_ratio=0.2, seed=42):
    set_seed(seed)
    n = len(full_ds)
    n_val = max(1, int(n * val_ratio))
    n_train = n - n_val
    train_ds, val_ds = random_split(full_ds, [n_train, n_val], generator=torch.Generator().manual_seed(seed))
    return train_ds, val_ds

def eval_epoch(model, loader, device, crit):
    model.eval()
    tot, correct, loss_sum = 0, 0, 0.0
    y_true, y_pred = [], []
    with torch.no_grad():
        for x, y in loader:
            x, y = x.to(device), y.to(device)
            out = model(x)
            loss = crit(out, y)
            loss_sum += loss.item() * y.size(0)
            pred = out.argmax(1)
            correct += (pred == y).sum().item()
            tot += y.size(0)
            y_true.append(y.cpu().numpy())
            y_pred.append(pred.cpu().numpy())
    y_true = np.concatenate(y_true); y_pred = np.concatenate(y_pred)
    return loss_sum/tot, correct/tot, y_true, y_pred

def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--data_dir", default="data/Stanford40")  # class별 폴더
    ap.add_argument("--out", default="outputs/image_action")
    ap.add_argument("--model", default="resnet18", choices=["resnet18","efficientnet_b0","convnext_tiny"])
    ap.add_argument("--epochs", type=int, default=15)
    ap.add_argument("--bs", type=int, default=32)
    ap.add_argument("--lr", type=float, default=3e-4)
    ap.add_argument("--val_ratio", type=float, default=0.2)
    ap.add_argument("--seed", type=int, default=42)
    ap.add_argument("--img_size", type=int, default=224)
    args = ap.parse_args()

    os.makedirs(args.out, exist_ok=True)
    set_seed(args.seed)

    device = "cuda" if torch.cuda.is_available() else "cpu"

    train_tf, val_tf = get_transforms(args.img_size)
    full_ds = datasets.ImageFolder(root=args.data_dir, transform=train_tf)
    class_names = full_ds.classes
    num_classes = len(class_names)

    # 클래스 이름 저장 (퓨전/예측 시 필요)
    with open(os.path.join(args.out, "class_names.txt"), "w", encoding="utf-8") as f:
        for c in class_names:
            f.write(c + "\n")

    # train / val split
    train_idx = list(range(len(full_ds)))
    train_ds, val_idx_ds = split_dataset(Subset(full_ds, train_idx), val_ratio=args.val_ratio, seed=args.seed)

    # val_ds는 transform을 val_tf로 교체
    # random_split은 Subset을 반환하므로 underlying dataset의 transform을 바꾸는 방식으로 처리
    full_ds_val = datasets.ImageFolder(root=args.data_dir, transform=val_tf)
    # val_idx_ds.indices는 train_idx 서브셋 기준 인덱스이므로 그대로 사용 가능
    val_ds = Subset(full_ds_val, val_idx_ds.indices)

    train_loader = DataLoader(train_ds, batch_size=args.bs, shuffle=True, num_workers=2, pin_memory=False)
    val_loader   = DataLoader(val_ds,   batch_size=args.bs, shuffle=False, num_workers=2, pin_memory=False)

    model = build_model(args.model, num_classes).to(device)
    crit = nn.CrossEntropyLoss()
    optimizer = optim.AdamW(model.parameters(), lr=args.lr, weight_decay=1e-4)
    scheduler = optim.lr_scheduler.CosineAnnealingLR(optimizer, T_max=args.epochs)

    history = {"train_loss":[], "train_acc":[], "val_loss":[], "val_acc":[]}
    best_acc = 0.0
    best_path = os.path.join(args.out, "best.ckpt")

    for epoch in range(1, args.epochs+1):
        model.train()
        tot, correct, loss_sum = 0, 0, 0.0
        for x, y in train_loader:
            x, y = x.to(device), y.to(device)
            optimizer.zero_grad()
            out = model(x)
            loss = crit(out, y)
            loss.backward()
            optimizer.step()
            loss_sum += loss.item() * y.size(0)
            pred = out.argmax(1)
            correct += (pred == y).sum().item()
            tot += y.size(0)
        tr_loss, tr_acc = loss_sum/tot, correct/tot

        va_loss, va_acc, y_true, y_pred = eval_epoch(model, val_loader, device, crit)

        history["train_loss"].append(tr_loss); history["train_acc"].append(tr_acc)
        history["val_loss"].append(va_loss);   history["val_acc"].append(va_acc)

        save_metrics_csv(os.path.join(args.out, "metrics.csv"), "train",
                         {"loss": tr_loss, "accuracy": tr_acc}, epoch)
        save_metrics_csv(os.path.join(args.out, "metrics.csv"), "val",
                         {"loss": va_loss, "accuracy": va_acc}, epoch)

        if va_acc > best_acc:
            best_acc = va_acc
            torch.save(model.state_dict(), best_path)

        scheduler.step()
        print(f"[{epoch}/{args.epochs}] train_acc={tr_acc:.3f} val_acc={va_acc:.3f}")

    # Best 로드 후 최종 혼동행렬/리포트/커브 저장
    model.load_state_dict(torch.load(best_path, map_location=device))
    va_loss, va_acc, y_true, y_pred = eval_epoch(model, val_loader, device, crit)
    cm = confusion_matrix(y_true, y_pred)
    plot_confusion_matrix(cm, class_names, os.path.join(args.out, "confusion_matrix.png"))

    # per-class report
    rep = classification_report(y_true, y_pred, target_names=class_names, output_dict=True, zero_division=0)
    with open(os.path.join(args.out, "per_class.csv"), "w", newline="", encoding="utf-8") as f:
        w = csv.writer(f); w.writerow(["class","precision","recall","f1","support"])
        for cls in class_names:
            r = rep[cls]
            w.writerow([cls, r["precision"], r["recall"], r["f1-score"], int(r["support"])])

    # curves
    fig = plt.figure()
    plt.plot(history["train_acc"], label="train_acc")
    plt.plot(history["val_acc"],   label="val_acc")
    plt.plot(history["train_loss"], label="train_loss")
    plt.plot(history["val_loss"],   label="val_loss")
    plt.legend(); plt.title(f"{args.model} — Accuracy/Loss")
    fig.savefig(os.path.join(args.out, "curves.png"), dpi=150, bbox_inches="tight")
    plt.close(fig)

    # 메타 저장(재현성)
    meta = {
        "timestamp": datetime.now().isoformat(timespec="seconds"),
        "model": args.model,
        "img_size": args.img_size,
        "epochs": args.epochs,
        "batch_size": args.bs,
        "lr": args.lr,
        "val_ratio": args.val_ratio,
        "classes": class_names,
        "best_val_acc": best_acc
    }
    with open(os.path.join(args.out, "train_meta.json"), "w", encoding="utf-8") as f:
        json.dump(meta, f, ensure_ascii=False, indent=2)

if __name__ == "__main__":
    main()
