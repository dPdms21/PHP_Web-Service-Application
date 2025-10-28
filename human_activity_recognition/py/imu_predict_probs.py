# -*- coding: utf-8 -*-
"""
UCI HAR 테스트셋에 대해 IMU 모델의 클래스 확률을 CSV로 저장
- 입력: outputs/rf_model.pkl (train_rf.py가 저장한 dict: {model, scaler, labels})
- 입력: data/UCI HAR Dataset/(train.csv|test.csv) 또는 TXT(X_test.txt, y_test.txt)
- 출력: outputs/imu/imu_probs.csv
"""
from pathlib import Path
import numpy as np
import pandas as pd
import joblib

ROOT = Path(__file__).resolve().parents[1]
DATA_ROOT = ROOT / "data" / "UCI HAR Dataset"
OUT_DIR = ROOT / "outputs" / "imu"
OUT_DIR.mkdir(parents=True, exist_ok=True)

MODEL_PATH = ROOT / "outputs" / "imu" / "rf_model.pkl"
OUT_CSV = OUT_DIR / "imu_probs.csv"

# 배치 결합과 맞추기 위한 표준 라벨 순서(6개)
HAR_ORDER = ["LAYING","SITTING","STANDING","WALKING","WALKING_DOWNSTAIRS","WALKING_UPSTAIRS"]

def load_from_csv():
    train_csv = DATA_ROOT / "train.csv"
    test_csv  = DATA_ROOT / "test.csv"
    if not (train_csv.exists() and test_csv.exists()):
        return None
    def parse(path: Path):
        df = pd.read_csv(path)
        # 라벨 추정
        cands = [c for c in df.columns if c.lower() in ("activity","label","y","act")]
        label_col = cands[0] if cands else df.columns[-1]
        drop_cols = [label_col] + [c for c in df.columns if c.lower()=="subject"]
        X = df.drop(columns=drop_cols).to_numpy(dtype=float)
        y = df[label_col].astype(str).to_numpy()
        return X, y
    return parse(test_csv)

def load_from_txt():
    X_path = DATA_ROOT / "test" / "X_test.txt"
    y_path = DATA_ROOT / "test" / "y_test.txt"
    if not (X_path.exists() and y_path.exists()):
        return None
    X = np.loadtxt(X_path)
    y = np.loadtxt(y_path).astype(int)
    label_map = {
        1:'WALKING', 2:'WALKING_UPSTAIRS', 3:'WALKING_DOWNSTAIRS',
        4:'SITTING', 5:'STANDING', 6:'LAYING'
    }
    y = np.array([label_map[i] for i in y])
    return X, y

def main():
    if not MODEL_PATH.exists():
        raise SystemExit(f"모델 파일 없음: {MODEL_PATH}")

    packed = joblib.load(MODEL_PATH)  # {"model": rf, "scaler": scaler, "labels": labels_sorted}
    rf = packed["model"]
    scaler = packed["scaler"]
    labels_saved = list(packed["labels"])  # 학습 시 사용된 라벨 순서

    loaded = load_from_csv() or load_from_txt()
    if loaded is None:
        raise SystemExit("테스트 데이터를 찾을 수 없습니다. CSV(test.csv) 또는 TXT(X_test.txt) 확인")

    X_test, y_test = loaded
    Xs = scaler.transform(X_test)

    # scikit-learn의 predict_proba는 labels_saved 순서로 열이 정렬됨
    proba = rf.predict_proba(Xs)  # shape: (n, n_classes)
    # 일부 모델에서 list로 클래스별 확률이 나오는 경우를 방지
    if isinstance(proba, list):
        # 이 케이스는 거의 없음(OneVsRest 등). 안전 장치.
        proba = np.stack([p[:,1] for p in proba], axis=1)

    # labels_saved → HAR_ORDER로 재정렬
    # (없는 라벨이 있다면 0 확률로 채움)
    n = proba.shape[0]
    out = pd.DataFrame({"file": [f"sample_{i}.wav" for i in range(n)]})
    for lab in HAR_ORDER:
        if lab in labels_saved:
            idx = labels_saved.index(lab)
            out[lab] = proba[:, idx]
        else:
            out[lab] = 0.0

    out.to_csv(OUT_CSV, index=False)
    print(f"[OK] saved -> {OUT_CSV} (rows={len(out)})")
    print("[INFO] columns:", ["file"] + HAR_ORDER)

if __name__ == "__main__":
    main()
