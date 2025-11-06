import pandas as pd
from pathlib import Path

HAR_LABELS = ["LAYING","SITTING","STANDING","WALKING","WALKING_DOWNSTAIRS","WALKING_UPSTAIRS"]

in_path = "outputs/imu/imu_probs.csv"
out_path = "outputs/imu/imu_probs_with_true.csv"

df = pd.read_csv(in_path)

# 각 행에서 가장 확률이 높은 라벨을 true_class로 지정
df["true_class"] = df[HAR_LABELS].idxmax(axis=1)

# 순서를 file → true_class → 확률 순으로 정렬
cols = ["file", "true_class"] + HAR_LABELS
df = df[cols]

Path(out_path).parent.mkdir(parents=True, exist_ok=True)
df.to_csv(out_path, index=False)
print(f"Saved: {out_path} ({len(df)} rows)")
