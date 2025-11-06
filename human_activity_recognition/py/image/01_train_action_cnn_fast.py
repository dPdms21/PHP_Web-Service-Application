# py/image/01_train_action_cnn_fast.py
import os, argparse, csv, json, random, itertools
import numpy as np
import torch, torch.nn as nn, torch.optim as optim
from torch.utils.data import DataLoader, Subset
from torchvision import datasets, transforms, models
import matplotlib.pyplot as plt
from sklearn.metrics import confusion_matrix, classification_report

def set_seed(s=42):
    import torch, random, numpy as np
    random.seed(s); np.random.seed(s); torch.manual_seed(s); torch.cuda.manual_seed_all(s)

def save_metrics_csv(path, split, metrics: dict, epoch=None):
    os.makedirs(os.path.dirname(path), exist_ok=True)
    new = not os.path.exists(path)
    with open(path, "a", newline="", encoding="utf-8") as f:
        w = csv.writer(f)
        if new: w.writerow(["split","metric","value","epoch"])
        for k,v in metrics.items():
            w.writerow([split,k,float(v),epoch if epoch is not None else ""])

def plot_cm(cm, classes, out_path):
    import matplotlib.pyplot as plt, numpy as np, itertools
    fig = plt.figure(figsize=(6,6))
    plt.imshow(cm, interpolation="nearest"); plt.title("Confusion matrix"); plt.colorbar()
    t = np.arange(len(classes)); plt.xticks(t, classes, rotation=90); plt.yticks(t, classes)
    th = cm.max()/2
    for i,j in itertools.product(range(cm.shape[0]), range(cm.shape[1])):
        c = "white" if cm[i,j] > th else "black"
        plt.text(j,i,str(cm[i,j]),ha="center",va="center",color=c,fontsize=8)
    plt.ylabel("True"); plt.xlabel("Pred"); plt.tight_layout(); fig.savefig(out_path, dpi=150, bbox_inches="tight"); plt.close(fig)

def build_model(name, num_classes, freeze_backbone=True):
    name = name.lower()
    if name == "mobilenet_v3_small":
        m = models.mobilenet_v3_small(weights=models.MobileNet_V3_Small_Weights.IMAGENET1K_V1)
        in_f = m.classifier[-1].in_features
        m.classifier[-1] = nn.Linear(in_f, num_classes)
    elif name == "resnet18":
        m = models.resnet18(weights=models.ResNet18_Weights.IMAGENET1K_V1)
        in_f = m.fc.in_features
        m.fc = nn.Linear(in_f, num_classes)
    else:
        raise ValueError("model must be mobilenet_v3_small or resnet18")
    if freeze_backbone:
        for n,p in m.named_parameters():
            if "classifier" in n or n.startswith("fc"):
                p.requires_grad = True
            else:
                p.requires_grad = False
    return m

def make_transforms(img_size):
    mean=(0.485,0.456,0.406); std=(0.229,0.224,0.225)
    train_tf = transforms.Compose([
        transforms.RandomResizedCrop(img_size, scale=(0.7,1.0)),
        transforms.RandomHorizontalFlip(),
        transforms.ToTensor(),
        transforms.Normalize(mean,std),
    ])
    val_tf = transforms.Compose([
        transforms.Resize(int(img_size*1.12)),
        transforms.CenterCrop(img_size),
        transforms.ToTensor(),
        transforms.Normalize(mean,std),
    ])
    return train_tf, val_tf

def filter_subset_indices(ds, wanted):
    # ds: ImageFolder, ds.classes: list, ds.samples: [(path, idx), ...]
    idx_of = {c:i for i,c in enumerate(ds.classes)}
    keep_labels = [idx_of[c] for c in ds.classes if c in wanted]
    if not keep_labels:
        raise ValueError(f"subset labels not found. wanted={wanted}, classes={ds.classes[:10]}...")
    keep = [i for i,(_,y) in enumerate(ds.samples) if y in keep_labels]
    return keep, [c for c in ds.classes if c in wanted]

def eval_epoch(model, loader, device, crit):
    model.eval(); tot=0; cor=0; loss_sum=0.0; y_true=[]; y_pred=[]
    with torch.no_grad():
        for x,y in loader:
            x,y = x.to(device), y.to(device)
            out = model(x); loss = crit(out,y)
            loss_sum += loss.item()*y.size(0)
            p = out.argmax(1)
            cor += (p==y).sum().item(); tot += y.size(0)
            y_true.append(y.cpu().numpy()); y_pred.append(p.cpu().numpy())
    y_true = np.concatenate(y_true); y_pred = np.concatenate(y_pred)
    return loss_sum/tot, cor/tot, y_true, y_pred

def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--data_dir", default="data/Stanford40")
    ap.add_argument("--out", default="outputs/image_action")
    ap.add_argument("--model", default="mobilenet_v3_small", choices=["mobilenet_v3_small","resnet18"])
    ap.add_argument("--img_size", type=int, default=160)
    ap.add_argument("--epochs", type=int, default=6)
    ap.add_argument("--bs", type=int, default=32)
    ap.add_argument("--lr", type=float, default=1e-3)
    ap.add_argument("--freeze_backbone", action="store_true", default=True)
    ap.add_argument("--subset", type=str, default="")  # e.g. "walking,running,sitting,lying"
    ap.add_argument("--seed", type=int, default=42)
    ap.add_argument("--workers", type=int, default=0) # Windows는 0이 빠를 때 많음
    args = ap.parse_args()

    os.makedirs(args.out, exist_ok=True); set_seed(args.seed)
    device = "cuda" if torch.cuda.is_available() else "cpu"

    train_tf, val_tf = make_transforms(args.img_size)
    full_train = datasets.ImageFolder(root=args.data_dir, transform=train_tf)
    full_val   = datasets.ImageFolder(root=args.data_dir, transform=val_tf)

    # subset 선택(선택 사항)
    classes = full_train.classes
    if args.subset:
        wanted = [s.strip() for s in args.subset.split(",") if s.strip()]
        keep_idx, classes = filter_subset_indices(full_train, wanted)
        full_train = Subset(full_train, keep_idx)
        keep_idx_val, _ = filter_subset_indices(full_val, wanted)
        full_val = Subset(full_val, keep_idx_val)

    num_classes = len(classes)
    with open(os.path.join(args.out,"class_names.txt"),"w",encoding="utf-8") as f:
        for c in classes: f.write(c+"\n")

    train_loader = DataLoader(full_train, batch_size=args.bs, shuffle=True,  num_workers=args.workers, pin_memory=False)
    val_loader   = DataLoader(full_val,   batch_size=args.bs, shuffle=False, num_workers=args.workers, pin_memory=False)

    model = build_model(args.model, num_classes, freeze_backbone=args.freeze_backbone).to(device)
    crit = nn.CrossEntropyLoss()
    # 분류기(헤드)만 학습하면 학습 파라미터 수가 적어서 훨씬 빠름
    params = [p for p in model.parameters() if p.requires_grad]
    opt = optim.AdamW(params, lr=args.lr, weight_decay=1e-4)

    hist={"train_acc":[], "val_acc":[], "train_loss":[], "val_loss":[]}
    best=0.0; best_ckpt=os.path.join(args.out,"best.ckpt")

    for ep in range(1, args.epochs+1):
        model.train(); tot=0; cor=0; loss_sum=0.0
        for xb,yb in train_loader:
            xb,yb = xb.to(device), yb.to(device)
            opt.zero_grad(); out = model(xb); loss = crit(out,yb)
            loss.backward(); opt.step()
            loss_sum += loss.item()*yb.size(0)
            cor += (out.argmax(1)==yb).sum().item(); tot += yb.size(0)
        tr_loss=loss_sum/tot; tr_acc=cor/tot

        va_loss, va_acc, y_true, y_pred = eval_epoch(model, val_loader, device, crit)
        hist["train_loss"].append(tr_loss); hist["train_acc"].append(tr_acc)
        hist["val_loss"].append(va_loss);   hist["val_acc"].append(va_acc)
        save_metrics_csv(os.path.join(args.out,"metrics.csv"),"train",{"loss":tr_loss,"accuracy":tr_acc},ep)
        save_metrics_csv(os.path.join(args.out,"metrics.csv"),"val",{"loss":va_loss,"accuracy":va_acc},ep)
        if va_acc>best: best=va_acc; torch.save(model.state_dict(), best_ckpt)
        print(f"[{ep}/{args.epochs}] train_acc={tr_acc:.3f} val_acc={va_acc:.3f}")

    # 최종 결과 저장
    model.load_state_dict(torch.load(best_ckpt, map_location=device))
    va_loss, va_acc, y_true, y_pred = eval_epoch(model, val_loader, device, crit)
    cm = confusion_matrix(y_true, y_pred); plot_cm(cm, classes, os.path.join(args.out,"confusion_matrix.png"))
    rep = classification_report(y_true, y_pred, target_names=classes, output_dict=True, zero_division=0)
    with open(os.path.join(args.out,"per_class.csv"),"w",newline="",encoding="utf-8") as f:
        w=csv.writer(f); w.writerow(["class","precision","recall","f1","support"])
        for c in classes: r=rep[c]; w.writerow([c,r["precision"],r["recall"],r["f1-score"],int(r["support"])])

    import matplotlib.pyplot as plt
    fig=plt.figure()
    plt.plot(hist["train_acc"],label="train_acc"); plt.plot(hist["val_acc"],label="val_acc")
    plt.plot(hist["train_loss"],label="train_loss"); plt.plot(hist["val_loss"],label="val_loss")
    plt.legend(); plt.title(f"{args.model} fast — Acc/Loss"); fig.savefig(os.path.join(args.out,"curves.png"),dpi=150,bbox_inches="tight"); plt.close(fig)

    meta={"model":args.model,"img_size":args.img_size,"epochs":args.epochs,"batch_size":args.bs,
          "freeze_backbone":args.freeze_backbone,"subset":args.subset,"best_val_acc":best}
    with open(os.path.join(args.out,"train_meta.json"),"w",encoding="utf-8") as f: json.dump(meta,f,ensure_ascii=False,indent=2)

if __name__ == "__main__":
    main()
