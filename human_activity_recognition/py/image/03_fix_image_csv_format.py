from pathlib import Path
import pandas as pd
import argparse
import numpy as np

# HAR 6개 클래스 정의
HAR_LABELS = [
    "LAYING",
    "SITTING",
    "STANDING",
    "WALKING",
    "WALKING_DOWNSTAIRS",
    "WALKING_UPSTAIRS"
]

def norm(v):
    """확률 벡터 정규화 (합 = 1)"""
    s = np.sum(v)
    return v / s if s > 0 else np.full_like(v, 1 / len(v))

def image3_to_har6(r):
    s = float(r.get("p_SITTING", 0.0))
    st = float(r.get("p_STANDING", 0.0))
    w = float(r.get("p_WALKING", 0.0))

    laying = s * 0.4       # SITTING 일부는 LAYING으로 강하게
    down = w * 0.25        # WALKING 일부는 DOWN으로
    up = w * 0.25          # WALKING 일부는 UP으로

    har6 = np.array([
        laying,  # LAYING
        s,       # SITTING
        st,      # STANDING
        w,       # WALKING
        down,    # DOWN
        up       # UP
    ], dtype=float)

    return norm(har6)

def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--in_csv", required=True, help="원본 이미지 확률 CSV (예: preds.csv)")
    ap.add_argument("--out_csv", default="outputs/image_action_fast/image_probs_fixed.csv")
    args = ap.parse_args()

    df = pd.read_csv(args.in_csv)

    # 필수 컬럼 확인
    expected_cols = {"p_SITTING", "p_STANDING", "p_WALKING"}
    if not expected_cols.intersection(df.columns):
        raise SystemExit(f"❌ 입력 CSV에 필요한 확률 컬럼이 없습니다: {expected_cols}")

    rows = []
    for i, r in df.iterrows():
        file_name = f"sample_{r.get('sample_id', i)}.wav"
        probs = image3_to_har6(r)
        row = {"file": file_name}
        row.update({HAR_LABELS[j]: probs[j] for j in range(len(HAR_LABELS))})
        row["pred_class"] = HAR_LABELS[int(np.argmax(probs))]  # ✅ 가장 높은 확률 클래스 추가
        rows.append(row)

    out = Path(args.out_csv)
    out.parent.mkdir(parents=True, exist_ok=True)
    pd.DataFrame(rows).to_csv(out, index=False)
    print(f"[OK] saved fixed HAR6 image probs: {out} (rows={len(rows)})")

if __name__ == "__main__":
    main()
