import argparse
from pathlib import Path
import pandas as pd
import numpy as np
import librosa
from joblib import load

ROOT = Path(__file__).resolve().parents[2]
MODEL_PATH = ROOT / "outputs" / "audio" / "models" / "audio_rf.joblib"
LE_PATH    = ROOT / "outputs" / "audio" / "models" / "label_encoder.joblib"
META_PATH  = ROOT / "data" / "ESC-50" / "meta" / "esc50.csv"

# ✅ ESC-50 → 우리 5개 라벨 매핑 규칙
MAP_RULES = {
    "footsteps":        ["footstep", "steps", "walking", "run", "running"],
    "desk_work":        ["keyboard", "typing", "mouse", "click"],
    "hygiene":          ["brushing", "toilet", "shaver", "hairdryer", "flush"],
    "cleaning":         ["vacuum", "washing machine", "scrub", "wipe", "clean"],
    "outdoor_ambient":  ["wind", "rain", "thunder", "storm", "sea", "wave", "ocean",
                         "bird", "birds", "cricket", "engine", "car horn", "siren",
                         "traffic", "street", "crowd", "insects", "environment"]
}

def map_category_to_label(cat: str) -> str:
    """ESC-50 카테고리를 5개 라벨 중 하나로 매핑"""
    if not isinstance(cat, str):
        return "unknown"
    c = cat.lower().strip()
    for k, words in MAP_RULES.items():
        if any(w in c for w in words):
            return k
    return "unknown"

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
    p.add_argument("--out_csv", type=str, default="outputs/audio/preds/audio_probs.csv")
    args = p.parse_args()

    clf = load(MODEL_PATH)
    le = load(LE_PATH)
    classes = list(le.classes_)

    # ✅ ESC-50 메타데이터 로드 및 매핑
    meta = pd.read_csv(META_PATH)
    meta["file"] = meta["filename"].apply(lambda x: f"data/ESC-50/audio/{x}")
    meta["true_label"] = meta["category"].apply(map_category_to_label)
    meta["file_norm"] = meta["file"].str.replace("\\", "/", regex=False)

    # ===== 예측 시작 =====
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

    df = pd.DataFrame(rows)

    # ✅ 경로 정규화 후 병합
    df["file_norm"] = df["file"].str.replace("\\", "/", regex=False)
    df = pd.merge(df, meta[["file_norm", "true_label"]], on="file_norm", how="left")
    df.drop(columns=["file_norm"], inplace=True)

    # ===== 저장 =====
    out = Path(args.out_csv)
    df.to_csv(out, index=False)
    print(f"[OK] saved with true_label -> {out}")

if __name__ == "__main__":
    main()
