import pandas as pd
from pathlib import Path
import argparse

def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--in_csv", required=True)
    ap.add_argument("--out_csv", default="outputs/audio/preds/audio_probs_fixed.csv")
    args = ap.parse_args()

    df = pd.read_csv(args.in_csv)
    df["file"] = df["file"].apply(lambda x: Path(str(x)).name)  # 경로 제거
    out = Path(args.out_csv)
    out.parent.mkdir(parents=True, exist_ok=True)
    df.to_csv(out, index=False)
    print(f"[OK] saved: {out} (rows={len(df)})")

if __name__ == "__main__":
    main()
