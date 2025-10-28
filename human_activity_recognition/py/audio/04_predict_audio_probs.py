import argparse
from pathlib import Path
import pandas as pd
import numpy as np
import librosa
from joblib import load

ROOT = Path(__file__).resolve().parents[2]
MODEL_PATH = ROOT / "outputs" / "audio" / "models" / "audio_rf.joblib"
LE_PATH    = ROOT / "outputs" / "audio" / "models" / "label_encoder.joblib"

def extract_vec(wav: Path, n_mfcc=40, sr=22050) -> np.ndarray:
    y, sr = librosa.load(wav, sr=sr)
    mfcc = librosa.feature.mfcc(y=y, sr=sr, n_mfcc=n_mfcc)
    d1 = librosa.feature.delta(mfcc)
    d2 = librosa.feature.delta(mfcc, order=2)
    feats = np.concatenate([mfcc, d1, d2], axis=0)
    return np.concatenate([feats.mean(axis=1), feats.std(axis=1)])

def main():
    p = argparse.ArgumentParser()
    p.add_argument("--wav", type=str, help="single wav path")
    p.add_argument("--dir", type=str, help="directory of wavs (*.wav)")
    p.add_argument("--out_csv", type=str, default="", help="optional output csv")
    args = p.parse_args()

    clf = load(MODEL_PATH)
    le = load(LE_PATH)
    classes = list(le.classes_)

    if args.wav:
        files = [Path(args.wav)]
    elif args.dir:
        files = sorted(Path(args.dir).glob("*.wav"))
    else:
        raise SystemExit("Provide --wav or --dir")

    rows = []
    for f in files:
        x = extract_vec(f).reshape(1, -1)
        proba = clf.predict_proba(x)[0]
        pred = classes[int(np.argmax(proba))]
        rec = {"file": str(f), "pred": pred}
        for i, c in enumerate(classes):
            rec[f"prob_{c}"] = float(proba[i])
        rows.append(rec)
        print(rec)

    if args.out_csv:
        out = Path(args.out_csv)
        pd.DataFrame(rows).to_csv(out, index=False)
        print(f"[OK] saved -> {out}")

if __name__ == "__main__":
    main()
