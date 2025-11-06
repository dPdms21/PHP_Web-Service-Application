from pathlib import Path
import argparse
import numpy as np
import pandas as pd

HAR_LABELS = ["LAYING","SITTING","STANDING","WALKING","WALKING_DOWNSTAIRS","WALKING_UPSTAIRS"]
AUDIO_LABELS = ["cleaning","desk_work","footsteps","hygiene","outdoor_ambient"]

A2H = np.array([
    [0.00, 0.00, 0.00, 0.00, 0.00],  # LAYING
    [0.00, 0.80, 0.00, 0.20, 0.10],  # SITTING
    [0.90, 0.20, 0.00, 0.80, 0.20],  # STANDING
    [0.10, 0.00, 0.60, 0.00, 0.30],  # WALKING
    [0.00, 0.00, 0.20, 0.00, 0.20],  # DOWN
    [0.00, 0.00, 0.20, 0.00, 0.20],  # UP
], dtype=float)

def norm(v):
    s = v.sum()
    return v/s if s>0 else np.full_like(v, 1/len(v))

def audio5_to_har6(row):
    ap = np.array([row[f"prob_{k}"] for k in AUDIO_LABELS], dtype=float)
    return norm(A2H @ ap)

def fuse3(imu6, audio6, image6, w_imu, w_aud, w_img):
    # 오디오 영향 계단 부분 축소
    audio6 = audio6.copy()
    audio6[4] *= 0.0
    audio6[5] *= 0.0
    fused = w_imu*imu6 + w_aud*audio6 + w_img*image6
    return norm(fused)

def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--imu_csv", required=True)
    ap.add_argument("--audio_csv", required=True)
    ap.add_argument("--image_csv", required=True)
    ap.add_argument("--w_imu", type=float, default=0.7)
    ap.add_argument("--w_aud", type=float, default=0.1)
    ap.add_argument("--w_img", type=float, default=0.2)
    ap.add_argument("--out_csv", default="outputs/fusion/fused_3modal.csv")
    ap.add_argument("--join_mode", choices=["file","index"], default="file")
    args = ap.parse_args()

    ROOT = Path(__file__).resolve().parents[2]
    imu_df = pd.read_csv(args.imu_csv)
    aud_df = pd.read_csv(args.audio_csv)
    img_df = pd.read_csv(args.image_csv)

    if args.join_mode == "file":
        def norm_path(s): return Path(str(s)).as_posix().split("/")[-1]
        for df in [imu_df, aud_df, img_df]:
            df["__key__"] = df[df.columns[0]].apply(norm_path)
        df = pd.merge(imu_df, aud_df, on="__key__", suffixes=("_imu","_aud"))
        df = pd.merge(df, img_df, on="__key__", suffixes=("","_img"))
        if df.empty:
            raise SystemExit("⚠️ 파일명 매칭 실패. --join_mode index 로 실행해보세요.")
    else:
        m = min(len(imu_df), len(aud_df), len(img_df))
        df = pd.concat([
            imu_df.iloc[:m].reset_index(drop=True),
            aud_df.iloc[:m].reset_index(drop=True),
            img_df.iloc[:m].reset_index(drop=True)
        ], axis=1)

    rows = []
    for _, r in df.iterrows():
        imu6 = np.array([r[k] for k in HAR_LABELS], dtype=float)
        audio6 = audio5_to_har6(r)
        image6 = np.array([r[f"p_{k}"] if f"p_{k}" in r else r.get(k,0.0) for k in HAR_LABELS], dtype=float)
        fused = fuse3(imu6, audio6, image6, args.w_imu, args.w_aud, args.w_img)
        rows.append({
            "file": r["__key__"],
            "pred_fused": HAR_LABELS[int(np.argmax(fused))],
            **{f"p_fused_{k}": fused[i] for i,k in enumerate(HAR_LABELS)},
        })

    out = ROOT / args.out_csv
    out.parent.mkdir(parents=True, exist_ok=True)
    pd.DataFrame(rows).to_csv(out, index=False)
    print(f"[OK] saved -> {out} (rows={len(rows)})")

if __name__ == "__main__":
    main()
