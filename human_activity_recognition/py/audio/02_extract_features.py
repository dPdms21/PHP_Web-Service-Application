from pathlib import Path
import pandas as pd
import numpy as np
import librosa
from tqdm import tqdm

ROOT = Path(__file__).resolve().parents[2]
ESC_DIR = ROOT / "data" / "ESC-50"
SUBSET_CSV = ROOT / "outputs" / "audio" / "subset" / "esc50_subset_audio_labels.csv"
OUT_DIR = ROOT / "outputs" / "audio" / "features"
OUT_DIR.mkdir(parents=True, exist_ok=True)

def extract_features(wav_path: Path, n_mfcc: int = 40, sr: int = 22050) -> np.ndarray:
    y, sr = librosa.load(wav_path, sr=sr)
    mfcc = librosa.feature.mfcc(y=y, sr=sr, n_mfcc=n_mfcc)
    d1 = librosa.feature.delta(mfcc)
    d2 = librosa.feature.delta(mfcc, order=2)
    feats = np.concatenate([mfcc, d1, d2], axis=0)                # (n_mfcc*3, T)
    return np.concatenate([feats.mean(axis=1), feats.std(axis=1)]) # (n_mfcc*3*2,)

def main():
    df = pd.read_csv(SUBSET_CSV)
    rows = []
    for _, r in tqdm(df.iterrows(), total=len(df)):
        wav = ESC_DIR / "audio" / r["filename"]
        try:
            vec = extract_features(wav)
            item = {"filename": r["filename"], "fold": r["fold"], "audio_label": r["audio_label"]}
            for i, v in enumerate(vec):
                item[f"f{i}"] = float(v)
            rows.append(item)
        except Exception as e:
            print(f"[WARN] skip {wav}: {e}")

    feat_df = pd.DataFrame(rows)
    out_csv = OUT_DIR / "esc50_audio_features.csv"
    feat_df.to_csv(out_csv, index=False)
    dims = len([c for c in feat_df.columns if c.startswith("f")])
    print(f"[OK] features -> {out_csv} (rows={len(feat_df)}, dims={dims})")

if __name__ == "__main__":
    main()
