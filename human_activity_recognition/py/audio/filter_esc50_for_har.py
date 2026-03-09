# py/audio/filter_esc50_for_har.py
import os
import shutil
import pandas as pd
from collections import defaultdict

# =========================================================
# ⚙️ 경로 설정
# =========================================================
ESC50_DIR = "data/ESC-50"  # 원본 ESC-50 폴더
AUDIO_DIR = os.path.join(ESC50_DIR, "audio")  # wav 파일 폴더
META_CSV = os.path.join(ESC50_DIR, "esc50.csv")  # ✅ meta가 아닌 루트에 있음
TARGET_DIR = "data/audio_esc50_grouped"

os.makedirs(TARGET_DIR, exist_ok=True)

# =========================================================
# 🎯 HAR 5개 그룹 매핑 (이미지와 동일한 카테고리)
# =========================================================
label_map = {
    "climbing": ["door_wood_knock", "door_wood_creaks", "hammer", "chainsaw"],
    "moving": ["footsteps", "keyboard_typing"],
    "posturing": ["breathing", "snoring"],
    "interacting": ["clapping", "laughing", "speech"],
    "exercising": ["coughing", "sneezing", "vacuum_cleaner", "washing_machine"]
}

# =========================================================
# 🧾 메타데이터 로드
# =========================================================
if not os.path.exists(META_CSV):
    raise FileNotFoundError(f"❌ Meta file not found: {META_CSV}")

meta = pd.read_csv(META_CSV)

# =========================================================
# 📂 그룹 폴더 생성
# =========================================================
for group in label_map.keys():
    os.makedirs(os.path.join(TARGET_DIR, group), exist_ok=True)

# =========================================================
# 🎧 그룹별 오디오 파일 복사
# =========================================================
count = defaultdict(int)
for _, row in meta.iterrows():
    for group, esc_classes in label_map.items():
        if row["category"] in esc_classes:
            src = os.path.join(AUDIO_DIR, row["filename"])  # ✅ audio 폴더 기준
            dst = os.path.join(TARGET_DIR, group, row["filename"])
            if os.path.exists(src):
                shutil.copy(src, dst)
                count[group] += 1
            break

# =========================================================
# ✅ 결과 출력
# =========================================================
print("\n✅ Grouped ESC-50 samples by 5 HAR categories:\n")
for k, v in count.items():
    print(f"  • {k:<12} : {v} files")

print(f"\n🎧 Filtered audio saved to: {TARGET_DIR}")
