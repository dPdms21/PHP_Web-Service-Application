"""
05_eval_audio_per_class.py
ESC-50 오디오 분류 결과(per-class metrics) 계산
"""
import pandas as pd
from sklearn.metrics import classification_report

# ===== 경로 설정 =====
PRED_CSV = "outputs/audio/preds/audio_probs.csv"
OUT_CSV = "outputs/audio/preds/per_class.csv"

# ===== 1. 데이터 로드 =====
audio = pd.read_csv(PRED_CSV)

# 필수 컬럼 검사
for col in ["pred", "true_label"]:
    if col not in audio.columns:
        raise ValueError(f"❌ '{col}' 컬럼이 {PRED_CSV}에 없습니다.")

# ===== 2. 정답/예측 전처리 =====
audio["true_label"] = audio["true_label"].astype(str).str.strip().str.lower()
audio["pred"] = audio["pred"].astype(str).str.strip().str.lower()

# NaN, unknown 제거
before = len(audio)
audio = audio[audio["true_label"].notna() & (audio["true_label"] != "unknown")]
after = len(audio)
if before != after:
    print(f"⚠️ 제거된 항목: {before - after}건 (NaN/unknown)")

# 남은 샘플이 없으면 종료
if len(audio) == 0:
    raise SystemExit("❌ 남은 유효한 샘플이 없습니다. true_label 매핑을 확인하세요.")

# ===== 3. 평가 =====
y_true = audio["true_label"]
y_pred = audio["pred"]

report = classification_report(y_true, y_pred, output_dict=True, zero_division=0)

df = pd.DataFrame(report).transpose().reset_index().rename(columns={"index": "class"})
df = df[~df["class"].isin(["accuracy", "macro avg", "weighted avg"])]
df = df[["class", "precision", "recall", "f1-score", "support"]]
df.rename(columns={"f1-score": "f1"}, inplace=True)

# ===== 4. 저장 =====
df.to_csv(OUT_CSV, index=False)
print(f"✅ Saved per-class metrics → {OUT_CSV}")
