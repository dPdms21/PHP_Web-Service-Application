# ==============================================
# File: py/audio/extract_epic_sounds_audio.py
# Desc: Extract short audio clips from EPIC-SOUNDS annotations
# ==============================================
import os
import pandas as pd
import subprocess
from tqdm import tqdm

# ===== 설정 =====
ANNOTATION_CSV = "data/epic-sounds/EPIC_Sounds_train.csv"
VIDEO_DIR = "data/epic-videos"        # EPIC-KITCHENS 영상 폴더
OUTPUT_DIR = "data/audio_epic"        # 결과 저장 폴더
SAMPLE_RATE = 16000                   # 오디오 샘플링 속도 (Hz)

# ===== 주요 행동 라벨 선택 =====
TARGET_LABELS = [
    "walk",
    "sit down",
    "stand up",
    "open / close",
    "plastic-only collision",
    "rustle",
    "ceramic / wood collision"
]

os.makedirs(OUTPUT_DIR, exist_ok=True)

# ===== 어노테이션 로드 =====
df = pd.read_csv(ANNOTATION_CSV)
print(f"📄 Loaded {len(df)} total annotations")

# ===== 라벨 필터 =====
df = df[df["class"].isin(TARGET_LABELS)]
print(f"🎯 Filtered {len(df)} target actions: {set(df['class'])}")

# ===== 오디오 추출 =====
for _, row in tqdm(df.iterrows(), total=len(df), desc="Extracting"):
    video_id = row["video_id"]
    label = row["class"].replace(" ", "_").replace("/", "-")
    start, end = row["start_timestamp"], row["stop_timestamp"]

    input_path = os.path.join(VIDEO_DIR, f"{video_id}.MP4")
    if not os.path.exists(input_path):
        continue  # 영상이 없으면 스킵

    label_dir = os.path.join(OUTPUT_DIR, label)
    os.makedirs(label_dir, exist_ok=True)

    output_path = os.path.join(label_dir, f"{video_id}_{start}_{end}.wav")

    cmd = [
        "ffmpeg",
        "-i", input_path,
        "-ss", start,
        "-to", end,
        "-vn",
        "-acodec", "pcm_s16le",
        "-ar", str(SAMPLE_RATE),
        "-ac", "1",
        output_path,
        "-y",
    ]

    subprocess.run(cmd, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)

print("\n✅ Extraction finished!")
print(f"📂 Saved under: {OUTPUT_DIR}/")
