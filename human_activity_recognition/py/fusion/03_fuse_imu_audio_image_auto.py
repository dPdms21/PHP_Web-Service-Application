from pathlib import Path
import argparse
import numpy as np
import pandas as pd
from scipy.special import softmax

# ====== 기본 라벨 ======
HAR_LABELS = ["LAYING","SITTING","STANDING","WALKING","WALKING_DOWNSTAIRS","WALKING_UPSTAIRS"]
AUDIO_LABELS = ["cleaning","desk_work","footsteps","hygiene","outdoor_ambient"]

# ====== 오디오 → HAR 매핑 행렬 ======
A2H = np.array([
    [0.00, 0.00, 0.00, 0.00, 0.00],  # LAYING
    [0.00, 0.80, 0.00, 0.20, 0.10],  # SITTING
    [0.90, 0.20, 0.00, 0.80, 0.20],  # STANDING
    [0.10, 0.00, 0.60, 0.00, 0.30],  # WALKING
    [0.00, 0.00, 0.20, 0.00, 0.20],  # DOWN
    [0.00, 0.00, 0.20, 0.00, 0.20],  # UP
], dtype=float)


# ====== 유틸 함수 ======
def norm(v):
    s = v.sum()
    return v/s if s > 0 else np.full_like(v, 1/len(v))

def softmax(x):
    e = np.exp(x - np.max(x))
    return e / e.sum()

def normalize_filename(path_str):
    """파일 경로 문자열에서 파일명만 추출 (확장자 포함)"""
    return Path(str(path_str)).name.strip().lower()


# ====== 변환 함수 ======
def audio5_to_har6(row):
    """오디오 CSV → HAR6 확률로 변환"""
    ap = np.array([row.get(f"prob_{k}", 0.0) for k in AUDIO_LABELS], dtype=float)
    return norm(A2H @ ap)


def fuse3(imu6, audio6, image6, w_imu, w_aud, w_img):
    """IMU + Audio + Image 융합"""
    audio6 = audio6.copy()
    # 계단 동작에서 오디오 영향 줄이기
    audio6[4] *= 0.0
    audio6[5] *= 0.0
    # 단순 합산 → softmax 정규화
    fused = w_imu * imu6 + w_aud * audio6 + w_img * image6
    return softmax(fused)


# ====== 메인 실행 ======
def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--imu_csv", required=True)
    ap.add_argument("--audio_csv", required=True)
    ap.add_argument("--image_csv", required=True)
    ap.add_argument("--w_imu", type=float, default=0.7)
    ap.add_argument("--w_aud", type=float, default=0.15)
    ap.add_argument("--w_img", type=float, default=0.15)
    ap.add_argument("--out_csv", default="outputs/fusion/fused_3modal_auto.csv")
    args = ap.parse_args()

    ROOT = Path(__file__).resolve().parents[2]

    imu_df = pd.read_csv(args.imu_csv)
    aud_df = pd.read_csv(args.audio_csv)
    img_df = pd.read_csv(args.image_csv)

    # ====== 파일명 컬럼 자동 감지 ======
    def detect_file_col(df):
        for c in df.columns:
            if "file" in c or "sample" in c or "path" in c:
                return c
        return df.columns[0]

    imu_col = detect_file_col(imu_df)
    aud_col = detect_file_col(aud_df)
    img_col = detect_file_col(img_df)

    # ====== 파일명 정규화 후 병합 ======
    imu_df["__key__"] = imu_df[imu_col].apply(normalize_filename)
    aud_df["__key__"] = aud_df[aud_col].apply(normalize_filename)
    img_df["__key__"] = img_df[img_col].apply(normalize_filename)

    df = pd.merge(imu_df, aud_df, on="__key__", suffixes=("_imu", "_aud"))
    df = pd.merge(df, img_df, on="__key__", suffixes=("", "_img"))

    if df.empty:
        raise SystemExit("⚠️ 자동 매칭 실패: 파일명 패턴이 너무 달라요. (예: 확장자 없이 저장된 경우)")

    rows = []
    for _, r in df.iterrows():
        imu6 = np.array([r.get(k, 0.0) for k in HAR_LABELS], dtype=float)
        audio6 = audio5_to_har6(r)
        image6 = np.array([r.get(f"p_{k}", r.get(k, 0.0)) for k in HAR_LABELS], dtype=float)

        fused = fuse3(imu6, audio6, image6, args.w_imu, args.w_aud, args.w_img)

        rows.append({
            "file": r["__key__"],
            "pred_fused": HAR_LABELS[int(np.argmax(fused))],
            "pred_imu": HAR_LABELS[int(np.argmax(imu6))],
            "pred_audio": HAR_LABELS[int(np.argmax(audio6))],
            "pred_image": HAR_LABELS[int(np.argmax(image6))],
            **{f"p_fused_{k}": fused[i] for i, k in enumerate(HAR_LABELS)}
        })

    out = ROOT / args.out_csv
    out.parent.mkdir(parents=True, exist_ok=True)
    pd.DataFrame(rows).to_csv(out, index=False)
    print(f"[OK] saved -> {out} (rows={len(rows)})")


if __name__ == "__main__":
    main()
