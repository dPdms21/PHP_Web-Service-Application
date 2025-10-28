from pathlib import Path
import pandas as pd

# repo 루트(human_activity_recognition) 기준 경로 계산
ROOT = Path(__file__).resolve().parents[2]
ESC_DIR = ROOT / "data" / "ESC-50"
META_CSV = ESC_DIR / "meta" / "esc50.csv"
OUT_DIR = ROOT / "outputs" / "audio" / "subset"
OUT_DIR.mkdir(parents=True, exist_ok=True)

# 오디오 보조 라벨 5종으로 매핑
MAP_RULES = {
    "footsteps":        ["footstep", "footsteps"],
    "desk_work":        ["keyboard"],
    "hygiene":          ["brushing", "toilet", "electric shaver", "shaver"],
    "cleaning":         ["vacuum", "washing machine"],
    "outdoor_ambient":  ["wind", "rain", "thunder", "sea", "wave", "bird", "cricket", "engine", "car horn", "siren"]
}

def map_category_to_label(category: str) -> str:
    cat = category.lower()
    for label, kws in MAP_RULES.items():
        for kw in kws:
            if kw in cat:
                return label
    return "unknown"


def main():
    if not META_CSV.exists():
        raise FileNotFoundError(f"meta not found: {META_CSV}")
    meta = pd.read_csv(META_CSV)  # columns: filename, fold, target, category, etc.
    meta["audio_label"] = meta["category"].apply(map_category_to_label)
    clean = meta[meta["audio_label"] != "unknown"].copy().reset_index(drop=True)

    out_csv = OUT_DIR / "esc50_subset_audio_labels.csv"
    clean.to_csv(out_csv, index=False)
    print(f"[OK] subset -> {out_csv} (rows={len(clean)})")
    print(clean["audio_label"].value_counts())

if __name__ == "__main__":
    main()
