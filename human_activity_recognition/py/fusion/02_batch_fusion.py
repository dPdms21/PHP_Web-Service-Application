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

def fuse(imu6, audio6, alpha):
    audio6 = audio6.copy()
    # 계단에서 오디오 영향 축소(필요시 조정)
    audio6[4] *= 0.0
    audio6[5] *= 0.0
    fused = alpha*imu6 + (1-alpha)*audio6
    return norm(fused)

def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--imu_csv", required=True)
    ap.add_argument("--audio_csv", required=True)
    ap.add_argument("--alpha", type=float, default=0.8)
    ap.add_argument("--out_csv", default="outputs/fusion/fused_results.csv")
    ap.add_argument("--join_mode", choices=["file","index"], default="file",
                    help="file: 파일명 기준 매칭, index: 행 인덱스 기준 결합")
    args = ap.parse_args()

    ROOT = Path(__file__).resolve().parents[2]
    imu_df = pd.read_csv(args.imu_csv)
    aud_df = pd.read_csv(args.audio_csv)

    if args.join_mode == "file":
        def norm_path(s): return Path(str(s)).as_posix().split("/")[-1]
        imu_df["__key__"] = imu_df["file"].apply(norm_path)
        aud_df["__key__"] = aud_df["file"].apply(norm_path)
        df = pd.merge(imu_df, aud_df, on="__key__", suffixes=("_imu","_aud"))
        if df.empty:
            raise SystemExit("파일명 매칭 실패. --join_mode index 로 실행해보세요.")
    else:
        m = min(len(imu_df), len(aud_df))
        df = pd.concat([imu_df.iloc[:m].reset_index(drop=True),
                        aud_df.iloc[:m].reset_index(drop=True)], axis=1)

    rows = []
    for _, r in df.iterrows():
        imu6 = np.array([r[k] for k in HAR_LABELS], dtype=float)
        audio6 = audio5_to_har6(r)
        fused = fuse(imu6, audio6, args.alpha)
        rows.append({
            "pred_fused": HAR_LABELS[int(np.argmax(fused))],
            "pred_imu": HAR_LABELS[int(np.argmax(imu6))],
            "pred_audio_har": HAR_LABELS[int(np.argmax(audio6))],
            **{f"p_fused_{k}": fused[i] for i,k in enumerate(HAR_LABELS)},
            **{f"p_imu_{k}": imu6[i] for i,k in enumerate(HAR_LABELS)},
            **{f"p_audio_as_{k}": audio6[i] for i,k in enumerate(HAR_LABELS)},
        })

    out = ROOT / args.out_csv
    out.parent.mkdir(parents=True, exist_ok=True)
    pd.DataFrame(rows).to_csv(out, index=False)
    print(f"[OK] saved -> {out} (rows={len(rows)})")

if __name__ == "__main__":
    main()
