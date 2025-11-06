# py/fusion/2_02_eval_fused_csv.py
import argparse, csv, os
import numpy as np
import matplotlib.pyplot as plt
from sklearn.metrics import confusion_matrix, classification_report, accuracy_score

CLASSES = ["LAYING","SITTING","STANDING","WALKING","WALKING_DOWNSTAIRS","WALKING_UPSTAIRS"]

def load_labels_map(labels_csv):
    m = {}
    with open(labels_csv, newline="", encoding="utf-8") as f:
        r = csv.reader(f); hdr = next(r)
        sid_idx = hdr.index("file") if "file" in hdr else (hdr.index("sample_id") if "sample_id" in hdr else 0)
        true_idx = hdr.index("true_class") if "true_class" in hdr else 1
        for row in r:
            if len(row) <= max(sid_idx, true_idx): continue
            m[row[sid_idx]] = row[true_idx]
    return m

def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--csv", required=True)
    ap.add_argument("--out_dir", default="outputs/fusion")
    ap.add_argument("--labels_csv", default="")
    args = ap.parse_args()

    os.makedirs(args.out_dir, exist_ok=True)

    # 🔹 1. 예측 파일 읽기
    with open(args.csv, newline="", encoding="utf-8") as f:
        r = csv.reader(f)
        hdr = next(r)
        rows = [row for row in r]

    file_idx = next((i for i,c in enumerate(hdr) if "file" in c or "sample" in c), 0)
    pred_idx = next((i for i,c in enumerate(hdr) if "pred_fused" in c or "pred_class" in c), -1)
    if pred_idx == -1: raise SystemExit("❌ pred_class 컬럼을 찾을 수 없습니다.")
    y_pred = [row[pred_idx] for row in rows]
    file_list = [row[file_idx] for row in rows]

    # 🔹 2. true_class 로드
    y_true = []
    if args.labels_csv:
        label_map = load_labels_map(args.labels_csv)
        for f in file_list:
            y_true.append(label_map.get(f, None))
    else:
        y_true = [None] * len(y_pred)

    # 🔹 3. 길이 자동 보정
    y_true = [t for t in y_true if t is not None]
    if len(y_true) != len(y_pred):
        print(f"⚠️ 길이 불일치 수정: y_true={len(y_true)}, y_pred={len(y_pred)} → 맞춰서 평가")
        n = min(len(y_true), len(y_pred))
        y_true, y_pred = y_true[:n], y_pred[:n]

    # 🔹 4. 평가
    present = sorted(set(y_true))
    if not any(c in present for c in CLASSES):
        print("⚠️ 라벨 불일치로 평가 불가. 예측 분포만 저장합니다.")
        from collections import Counter
        dist = Counter(y_pred)
        out = os.path.join(args.out_dir, "fused_pred_distribution.csv")
        with open(out, "w", newline="", encoding="utf-8") as f:
            w = csv.writer(f); w.writerow(["class","count"])
            for k,v in dist.items(): w.writerow([k,v])
        print("✅ Saved:", out)
        return

    cm = confusion_matrix(y_true, y_pred, labels=CLASSES)
    acc = accuracy_score(y_true, y_pred)
    report = classification_report(y_true, y_pred, labels=CLASSES, output_dict=True, zero_division=0)

    # 🔹 5. 혼동행렬 시각화
    plt.figure(figsize=(6,6))
    plt.imshow(cm, interpolation="nearest", cmap="Blues")
    plt.title(f"Fusion Confusion Matrix (acc={acc:.3f})"); plt.colorbar()
    ticks = np.arange(len(CLASSES))
    plt.xticks(ticks, CLASSES, rotation=90); plt.yticks(ticks, CLASSES)
    for i in range(len(CLASSES)):
        for j in range(len(CLASSES)):
            plt.text(j,i,str(cm[i,j]),ha='center',va='center',
                     color="white" if cm[i,j] > cm.max()/2 else "black",fontsize=8)
    plt.tight_layout()
    out_png = os.path.join(args.out_dir, "fused_confusion_matrix.png")
    plt.savefig(out_png, dpi=150, bbox_inches="tight")
    plt.close()

    # 🔹 6. per-class 저장
    out_csv = os.path.join(args.out_dir, "fused_per_class.csv")
    with open(out_csv, "w", newline="", encoding="utf-8") as f:
        w = csv.writer(f)
        w.writerow(["class","precision","recall","f1","support"])
        for c in CLASSES:
            d = report[c]
            w.writerow([c, d["precision"], d["recall"], d["f1-score"], int(d["support"])])

    # 🔹 7. 요약 저장
    with open(os.path.join(args.out_dir, "fused_summary.txt"), "w", encoding="utf-8") as f:
        f.write(f"accuracy = {acc:.4f}\n")
        f.write(f"macro_f1 = {report['macro avg']['f1-score']:.4f}\n")
        f.write(f"weighted_f1 = {report['weighted avg']['f1-score']:.4f}\n")

    print("✅ saved:",
          out_png,
          out_csv,
          os.path.join(args.out_dir, "fused_summary.txt"))

if __name__ == "__main__":
    main()
