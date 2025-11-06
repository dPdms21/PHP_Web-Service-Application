# py/fusion/03_fuse_3modal_final.py
from pathlib import Path
import argparse
import numpy as np
import pandas as pd

HAR = ["LAYING","SITTING","STANDING","WALKING","WALKING_DOWNSTAIRS","WALKING_UPSTAIRS"]

def norm_safe(v):
    v = np.maximum(v, 1e-8)
    s = v.sum()
    return v / s if s > 0 else np.full_like(v, 1/len(v))

def softmax_safe(x):
    e = np.exp(x - np.max(x))
    p = e / e.sum()
    return norm_safe(p)

def normalize_filename(p):
    return Path(str(p)).name.strip().lower()

def fuse3(imu6, audio6, image6, w_imu, w_aud, w_img):
    imu6   = norm_safe(imu6)
    audio6 = norm_safe(audio6)
    image6 = norm_safe(image6)
    fused = w_imu * imu6 + w_aud * audio6 + w_img * image6
    return softmax_safe(fused)

def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--imu_csv", required=True)
    ap.add_argument("--audio_csv", required=True)
    ap.add_argument("--image_csv", required=True)
    ap.add_argument("--w_imu", type=float, default=0.7)
    ap.add_argument("--w_aud", type=float, default=0.15)
    ap.add_argument("--w_img", type=float, default=0.15)
    ap.add_argument("--out_csv", default="outputs/fusion/fused_3modal_final.csv")
    args = ap.parse_args()

    ROOT = Path(__file__).resolve().parents[2]
    imu_df = pd.read_csv(args.imu_csv)
    aud_df = pd.read_csv(args.audio_csv)
    img_df = pd.read_csv(args.image_csv)

    for df in [imu_df, aud_df, img_df]:
        if "file" not in df.columns:
            raise SystemExit("❌ 각 CSV에 'file' 컬럼이 필요합니다.")
        df["file"] = df["file"].apply(normalize_filename)

    df = imu_df.merge(aud_df, on="file", suffixes=("_imu", "_aud"))
    df = df.merge(img_df, on="file", suffixes=("", "_img"))
    if df.empty:
        raise SystemExit("❌ 파일명 매칭 실패 — sample_x.wav 이름이 다를 수 있습니다.")

    rows = []
    for _, r in df.iterrows():
        imu6   = np.array([r[f"{k}"] for k in HAR], dtype=float)
        audio6 = np.array([r[f"{k}_aud"] for k in HAR], dtype=float)
        image6 = np.array([r[f"{k}"] for k in HAR], dtype=float)

        fused = fuse3(imu6, audio6, image6, args.w_imu, args.w_aud, args.w_img)

        rows.append({
            "file": r["file"],
            "pred_fused": HAR[int(np.argmax(fused))],
            "pred_imu": HAR[int(np.argmax(imu6))],
            "pred_audio": HAR[int(np.argmax(audio6))],
            "pred_image": HAR[int(np.argmax(image6))],
            **{f"p_fused_{k}": fused[i] for i,k in enumerate(HAR)}
        })

    out = ROOT / args.out_csv
    out.parent.mkdir(parents=True, exist_ok=True)
    pd.DataFrame(rows).to_csv(out, index=False)
    print(f"✅ Saved fused CSV: {out} (rows={len(rows)})")

if __name__ == "__main__":
    main()
