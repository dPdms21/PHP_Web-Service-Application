import csv, os, argparse, numpy as np
from pathlib import Path

# 입력 CSV 예시:
# sample_id,true_class,pred_class,pred_score,p_cleaning,p_desk_work,p_footsteps,p_hygiene,p_outdoor_ambient

HAR = ["LAYING","SITTING","STANDING","WALKING","WALKING_DOWNSTAIRS","WALKING_UPSTAIRS"]
AUDIO = ["cleaning","desk_work","footsteps","hygiene","outdoor_ambient"]

# 오디오→HAR 매핑 (6x5)
A2H = np.array([
    [0.00, 0.00, 0.00, 0.00, 0.00],  # LAYING
    [0.00, 0.80, 0.00, 0.20, 0.10],  # SITTING
    [0.90, 0.20, 0.00, 0.80, 0.20],  # STANDING
    [0.10, 0.00, 0.60, 0.00, 0.30],  # WALKING
    [0.00, 0.00, 0.20, 0.00, 0.20],  # WALKING_DOWNSTAIRS
    [0.00, 0.00, 0.20, 0.00, 0.20],  # WALKING_UPSTAIRS
], dtype=float)

def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--in_csv",  required=True, help="ESC-50 확률 CSV (5라벨)")
    ap.add_argument("--out_csv", default="outputs/audio/preds/audio_as_har6.csv", help="변환 출력 CSV (HAR 6라벨)")
    args = ap.parse_args()

    with open(args.in_csv, newline="", encoding="utf-8") as f:
        r = csv.reader(f)
        hdr = next(r)
        rows = [row for row in r]

    # 확률 컬럼 자동 탐지
    prob_cols = []
    for k in AUDIO:
        if f"p_{k}" in hdr:
            prob_cols.append(f"p_{k}")
        elif f"prob_{k}" in hdr:
            prob_cols.append(f"prob_{k}")
        else:
            raise ValueError(f"'{k}' 확률 컬럼을 찾을 수 없습니다. CSV 헤더: {hdr}")

    idx = [hdr.index(c) for c in prob_cols]

    out_hdr = ["file"] + HAR
    with open(args.out_csv, "w", newline="", encoding="utf-8") as f:
        w = csv.writer(f)
        w.writerow(out_hdr)

        for i, row in enumerate(rows):
            # sample_번호.wav로 파일명 통일
            file_name = f"sample_{i}.wav"
            ap = np.array([float(row[j]) for j in idx], dtype=float)
            ap = ap / ap.sum() if ap.sum() > 0 else np.full(5, 1/5)
            har = A2H @ ap
            har = har / har.sum()
            w.writerow([file_name, *[round(float(v), 6) for v in har.tolist()]])

    print(f"[OK] Saved: {args.out_csv} (rows={len(rows)})")

if __name__ == "__main__":
    main()
