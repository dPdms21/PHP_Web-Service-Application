from pathlib import Path
import pandas as pd
import numpy as np
from sklearn.preprocessing import StandardScaler, LabelEncoder
from sklearn.ensemble import RandomForestClassifier
from sklearn.pipeline import make_pipeline
from sklearn.model_selection import StratifiedKFold
from sklearn.metrics import accuracy_score, f1_score
from joblib import dump
import re

ROOT = Path(__file__).resolve().parents[2]
FEAT_CSV = ROOT / "outputs" / "audio" / "features" / "esc50_audio_features.csv"
MODEL_DIR = ROOT / "outputs" / "audio" / "models"
MODEL_DIR.mkdir(parents=True, exist_ok=True)

def main():
    df = pd.read_csv(FEAT_CSV)
    feat_cols = [c for c in df.columns if re.fullmatch(r"f\d+", c)]
    X = df[feat_cols].values
    y_text = df["audio_label"].values

    le = LabelEncoder()
    y = le.fit_transform(y_text)

    cv = StratifiedKFold(n_splits=5, shuffle=True, random_state=42)
    accs, f1s = [], []
    for tr, te in cv.split(X, y):
        clf = make_pipeline(StandardScaler(), RandomForestClassifier(
            n_estimators=600, random_state=42, n_jobs=-1
        ))
        clf.fit(X[tr], y[tr])
        pred = clf.predict(X[te])
        accs.append(accuracy_score(y[te], pred))
        f1s.append(f1_score(y[te], pred, average="macro"))
    print(f"[CV] Acc={np.mean(accs):.3f} ± {np.std(accs):.3f}, Macro-F1={np.mean(f1s):.3f}")

    final_clf = make_pipeline(StandardScaler(), RandomForestClassifier(
        n_estimators=600, random_state=42, n_jobs=-1
    ))
    final_clf.fit(X, y)

    dump(final_clf, MODEL_DIR / "audio_rf.joblib")
    dump(le,        MODEL_DIR / "label_encoder.joblib")
    print(f"[OK] model -> {MODEL_DIR/'audio_rf.joblib'}")
    print(f"[OK] encoder -> {MODEL_DIR/'label_encoder.joblib'}")
    print("classes:", list(le.classes_))

if __name__ == "__main__":
    main()
