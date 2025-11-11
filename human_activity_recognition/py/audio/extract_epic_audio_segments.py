# -*- coding: utf-8 -*-
"""
EPIC-Sounds CSV(annot) -> 필요한 구간만 오디오로 잘라 저장 (WAV)
- 원본 전체 영상 다운로드 불필요 (ffmpeg가 HTTP 입력에서 구간 추출 가능)
- 로컬 영상 폴더 또는 원격 URL 템플릿 모두 지원
- 클래스 디렉토리명은: "open / close" -> "open_-_close", "ceramic / wood collision" -> "ceramic_-_wood_collision"
Usage 예시는 파일 하단 주석 참고
"""
import os
import csv
import argparse
import subprocess
from collections import defaultdict

def ensure_dir(p: str):
    os.makedirs(p, exist_ok=True)

def norm_class_dir(label: str) -> str:
    """
    EPIC class 라벨을 디렉토리명으로 통일
    - " / " 또는 "/" -> "_-_"
    - 공백 -> "_"
    - 하이픈(-)은 유지
    예) "open / close" -> "open_-_close"
        "ceramic / wood collision" -> "ceramic_-_wood_collision"
        "plastic-only collision" -> "plastic-only_collision"
    """
    s = label.strip()
    s = s.replace(" / ", "_-_").replace("/", "_-_")
    s = s.replace(" ", "_")
    return s

def build_video_source(videos_root: str, url_template: str, participant_id: str, video_id: str) -> str:
    """
    로컬 폴더 기준 또는 URL 템플릿 기준으로 원본 비디오 경로(또는 URL)를 만든다.
    - 로컬: <videos_root>/<participant_id>/<video_id>.MP4
    - URL : url_template.format(participant=participant_id, video_id=video_id)
    """
    if url_template:
        return url_template.format(participant=participant_id, video_id=video_id)
    else:
        return os.path.join(videos_root, participant_id, f"{video_id}.MP4")

def run_ffmpeg_cut(src: str, ss: str, to: str, out_wav: str, sr: int = 16000, ac: int = 1, overwrite: bool = True) -> int:
    """
    ffmpeg로 [ss, to] 구간만 오디오 추출
    - HTTP/로컬 모두 지원
    - 정확한 컷팅 vs 속도는 입력 위치의 -ss 배치에 따라 다르나, 네트워크 입력 호환을 위해 -ss 먼저 둔다.
    """
    if overwrite and os.path.exists(out_wav):
        try:
            os.remove(out_wav)
        except Exception:
            pass

    cmd = [
        "ffmpeg",
        "-hide_banner", "-loglevel", "error",
        "-ss", ss,            # 시작 시각
        "-to", to,            # 종료 시각
        "-i", src,            # 입력 (URL/로컬)
        "-vn",                # 비디오는 버림
        "-ac", str(ac),       # 채널 수
        "-ar", str(sr),       # 샘플레이트
        "-y",                 # 덮어쓰기
        out_wav
    ]
    try:
        result = subprocess.run(cmd, capture_output=True, text=True)
        return result.returncode
    except FileNotFoundError:
        raise RuntimeError("ffmpeg 실행 파일을 찾을 수 없습니다. (Windows는 ffmpeg.exe PATH 추가 필요)")

def parse_args():
    ap = argparse.ArgumentParser(description="EPIC-Sounds CSV에서 필요한 구간만 오디오(WAV)로 추출")
    ap.add_argument("--csv", required=True, help="EPIC_Sounds_train.csv 또는 EPIC_Sounds_validation.csv 경로")
    ap.add_argument("--out_dir", default="data/audio_epic", help="WAV를 저장할 루트 디렉토리")
    ap.add_argument("--videos_root", default="", help="로컬 원본 영상 루트 폴더 (예: data/epic-videos)")
    ap.add_argument("--url_template", default="", help="원격 URL 템플릿 (예: https://.../{participant}/{video_id}.MP4)")
    ap.add_argument("--sr", type=int, default=16000, help="오디오 샘플레이트(Hz)")
    ap.add_argument("--ac", type=int, default=1, help="오디오 채널 수")
    ap.add_argument("--max_per_class", type=int, default=200, help="클래스별 최대 추출 개수 (데모/속도용)")
    ap.add_argument("--only_classes", nargs="*", default=[],
                    help="이 리스트에 포함된 class만 추출 (예: --only_classes 'rustle' 'open / close')")
    ap.add_argument("--skip_existing", action="store_true", help="이미 존재하는 파일은 건너뜀")
    return ap.parse_args()

def main():
    args = parse_args()

    if not args.url_template and not args.videos_root:
        raise SystemExit("하나는 반드시 지정해야 합니다: --videos_root (로컬) 또는 --url_template (원격)")

    ensure_dir(args.out_dir)

    # 클래스별 개수 카운터
    per_class_count = defaultdict(int)
    extracted_total = 0
    failed_total = 0

    # CSV 읽기
    # 컬럼 예시:
    # annotation_id,participant_id,video_id,start_timestamp,stop_timestamp,...,class,...
    with open(args.csv, "r", encoding="utf-8") as f:
        reader = csv.DictReader(f)
        for row in reader:
            raw_class = (row.get("class") or "").strip()
            if not raw_class:
                continue

            # 필요한 클래스만 선택 (옵션)
            if args.only_classes:
                # CSV 라벨은 공백/슬래시 등 혼용 가능 → 비교는 normalize하여 느슨하게
                def loose(s: str) -> str:
                    return s.lower().replace("_", " ").replace("-", " ").replace("/", " ").replace("  ", " ").strip()
                if loose(raw_class) not in [loose(x) for x in args.only_classes]:
                    continue

            # per-class 제한
            if per_class_count[raw_class] >= args.max_per_class:
                continue

            participant_id = (row.get("participant_id") or "").strip()
            video_id = (row.get("video_id") or "").strip()
            ss = (row.get("start_timestamp") or "").strip()
            to = (row.get("stop_timestamp") or "").strip()
            annot_id = (row.get("annotation_id") or "").strip()

            if not (participant_id and video_id and ss and to and annot_id):
                continue

            # 원본 비디오 소스 (로컬 또는 URL)
            src = build_video_source(args.videos_root, args.url_template, participant_id, video_id)

            # 출력 경로: <out_dir>/<class_dir>/<annotation_id>.wav
            class_dir_name = norm_class_dir(raw_class)
            out_class_dir = os.path.join(args.out_dir, class_dir_name)
            ensure_dir(out_class_dir)
            out_wav = os.path.join(out_class_dir, f"{annot_id}.wav")

            if args.skip_existing and os.path.exists(out_wav):
                per_class_count[raw_class] += 1
                extracted_total += 1
                continue

            # ffmpeg 컷
            code = run_ffmpeg_cut(src=src, ss=ss, to=to, out_wav=out_wav, sr=args.sr, ac=args.ac, overwrite=True)
            if code == 0 and os.path.exists(out_wav) and os.path.getsize(out_wav) > 0:
                per_class_count[raw_class] += 1
                extracted_total += 1
            else:
                failed_total += 1

    # 요약 출력
    print("\n==== Extraction Summary ====")
    for k in sorted(per_class_count.keys()):
        print(f"{k}: {per_class_count[k]}")
    print(f"Extracted: {extracted_total}, Failed: {failed_total}")

if __name__ == "__main__":
    main()
