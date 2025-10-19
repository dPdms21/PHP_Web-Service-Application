import json
import csv
import glob
import os

def get_time_range(hour):
    if 0 <= hour <= 5:
        return "새벽"
    elif 6 <= hour <= 11:
        return "오전"
    elif 12 <= hour <= 17:
        return "오후"
    else:
        return "저녁"

counts = {}

# 입력/출력 경로
input_folder = "../AIhub/VL_sample/*.json"
output_file = "../output/topic_by_time_simple.csv"

os.makedirs("../output", exist_ok=True)

# JSON 파일 반복
for file in glob.glob(input_folder):
    with open(file, "r", encoding="utf-8") as f:
        data = json.load(f)

    # single_topic 가져오기
    dialogue_info = data["header"].get("dialogueInfo", {})
    single_topic = dialogue_info.get("single_topic", "기타")

    # 발화별 시간대 카운트
    for item in data["body"]:
        time_str = item.get("time", "00:00:00")
        try:
            hour = int(time_str.split(":")[0])
        except:
            hour = 0
        time_range = get_time_range(hour)

        key = (time_range, single_topic)
        counts[key] = counts.get(key, 0) + 1

# CSV 저장
with open(output_file, "w", newline="", encoding="utf-8-sig") as f:
    writer = csv.writer(f)
    writer.writerow(["time_range", "single_topic", "count"])
    for (time_range, single_topic), count in counts.items():
        writer.writerow([time_range, single_topic, count])

print(f"CSV 변환 완료 → {output_file}")
