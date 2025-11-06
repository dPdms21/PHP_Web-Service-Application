# py/utils/match_label_rows.py
import pandas as pd

fusion_path = "outputs/fusion/fused_3modal_boost.csv"
imu_path = "outputs/imu/imu_probs_with_true.csv"
out_path = "outputs/imu/imu_probs_matched.csv"

# 파일 읽기
fuse = pd.read_csv(fusion_path)
imu = pd.read_csv(imu_path)

# file 컬럼 이름 자동 인식
fuse_file_col = "file" if "file" in fuse.columns else "sample_id"
imu_file_col = "file" if "file" in imu.columns else "sample_id"

# 동일한 파일명만 추출
matched = imu[imu[imu_file_col].isin(fuse[fuse_file_col])].reset_index(drop=True)
matched.to_csv(out_path, index=False)

print(f"Saved matched label file: {out_path} ({len(matched)} rows)")
