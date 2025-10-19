# -*- coding: utf-8 -*-
"""
UCI HAR 데이터 자동 로더(CSV 또는 TXT) + RandomForest 학습/평가 + 결과 저장
출력: outputs/metrics.json, outputs/confusion_matrix_rf.png, outputs/rf_model.pkl
"""
from pathlib import Path
import json
import numpy as np
import pandas as pd
import matplotlib.pyplot as plt
from sklearn.ensemble import RandomForestClassifier
from sklearn.preprocessing import StandardScaler
from sklearn.metrics import accuracy_score, classification_report, confusion_matrix
import joblib

# 프로젝트 경로
ROOT = Path(__file__).resolve().parents[1]
DATA_ROOT = ROOT / "data" / "UCI HAR Dataset"
OUT = ROOT / "outputs"
OUT.mkdir(parents=True, exist_ok=True)

# ---------------------------
# 데이터 로더 (CSV 또는 TXT 자동 감지)
# ---------------------------
def load_from_csv():
    train_csv = DATA_ROOT / "train.csv"
    test_csv = DATA_ROOT / "test.csv"
    if not (train_csv.exists() and test_csv.exists()):
        return None
    def parse(path: Path):
        df = pd.read_csv(path)
        # 라벨 컬럼 자동탐지 (없으면 마지막 컬럼을 라벨로)
        cands = [c for c in df.columns if c.lower() in ("activity","label","y","act")]
        label_col = cands[0] if cands else df.columns[-1]
        # subject 컬럼 있으면 제외
        drop_cols = [label_col] + [c for c in df.columns if c.lower()=="subject"]
        X = df.drop(columns=drop_cols).to_numpy(dtype=float)
        y = df[label_col].astype(str).to_numpy()
        return X, y
    Xtr, ytr = parse(train_csv)
    Xte, yte = parse(test_csv)
    labels = sorted(np.unique(np.concatenate([ytr, yte])).tolist())
    return Xtr, ytr, Xte, yte, labels

def load_from_txt():
    train_X = DATA_ROOT / "train" / "X_train.txt"
    train_y = DATA_ROOT / "train" / "y_train.txt"
    test_X  = DATA_ROOT / "test"  / "X_test.txt"
    test_y  = DATA_ROOT / "test"  / "y_test.txt"
    if not (train_X.exists() and train_y.exists() and test_X.exists() and test_y.exists()):
        return None
    Xtr = np.loadtxt(train_X)
    ytr = np.loadtxt(train_y).astype(int)
    Xte = np.loadtxt(test_X)
    yte = np.loadtxt(test_y).astype(int)
    label_map = {
        1:'WALKING', 2:'WALKING_UPSTAIRS', 3:'WALKING_DOWNSTAIRS',
        4:'SITTING', 5:'STANDING', 6:'LAYING'
    }
    ytr = np.array([label_map[i] for i in ytr])
    yte = np.array([label_map[i] for i in yte])
    labels = list(label_map.values())
    return Xtr, ytr, Xte, yte, labels

loaded = load_from_csv() or load_from_txt()
if not loaded:
    raise FileNotFoundError("데이터를 찾을 수 없습니다. CSV(train.csv/test.csv) 또는 TXT(UCI 구조)를 확인하세요.")
X_train, y_train, X_test, y_test, labels_sorted = loaded

# ---------------------------
# 스케일링 + 학습
# ---------------------------
scaler = StandardScaler()
X_train_s = scaler.fit_transform(X_train)
X_test_s  = scaler.transform(X_test)

rf = RandomForestClassifier(n_estimators=300, n_jobs=-1, random_state=42)
rf.fit(X_train_s, y_train)

# ---------------------------
# 평가
# ---------------------------
y_pred = rf.predict(X_test_s)
acc = accuracy_score(y_test, y_pred)
rep = classification_report(y_test, y_pred, output_dict=True, zero_division=0)
cm  = confusion_matrix(y_test, y_pred, labels=labels_sorted)

# --- 클래스 분포 저장 (labels_sorted 순서 기준) ---
def counts_per(labels, y):
    y_arr = np.asarray(y)
    return [int(np.sum(y_arr == lab)) for lab in labels]

class_dist = {
    "train": counts_per(labels_sorted, y_train),
    "test":  counts_per(labels_sorted, y_test),
}

# ---------------------------
# 저장 (모델/메트릭/이미지)
# ---------------------------
joblib.dump({"model": rf, "scaler": scaler, "labels": labels_sorted}, OUT / "rf_model.pkl")

metrics = {
    "accuracy": float(acc),
    "macro_f1": float(rep["macro avg"]["f1-score"]),
    "per_class": {
        k: {
            "precision": float(v.get("precision", 0.0)),
            "recall": float(v.get("recall", 0.0)),
            "f1": float(v.get("f1-score", 0.0)),
        }
        for k, v in rep.items() if k in labels_sorted
    },
    "labels": labels_sorted,
    "confusion_matrix": cm.astype(int).tolist(),
    "class_dist": class_dist
}
with open(OUT / "metrics.json", "w", encoding="utf-8") as f:
    json.dump(metrics, f, ensure_ascii=False, indent=2)

plt.figure(figsize=(7,6))
plt.imshow(cm, interpolation='nearest')
plt.title(f"Confusion Matrix (RF) - Acc {acc:.3f}")
plt.xlabel("Predicted"); plt.ylabel("True")
plt.xticks(range(len(labels_sorted)), labels_sorted, rotation=45, ha='right')
plt.yticks(range(len(labels_sorted)), labels_sorted)
plt.colorbar()
for i in range(cm.shape[0]):
    for j in range(cm.shape[1]):
        plt.text(j, i, cm[i, j], ha='center', va='center', fontsize=9)
plt.tight_layout()
plt.savefig(OUT / "confusion_matrix_rf.png", dpi=180)
print(f"[OK] accuracy={acc:.4f}, macro_f1={metrics['macro_f1']:.4f}, saved to {OUT}")
