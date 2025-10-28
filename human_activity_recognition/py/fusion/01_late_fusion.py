"""
IMU(6라벨) 예측 확률과 오디오(5라벨) 예측 확률을 가중 결합해 최종 HAR 6라벨을 산출.
- IMU 라벨 순서(고정): ["LAYING","SITTING","STANDING","WALKING","WALKING_DOWNSTAIRS","WALKING_UPSTAIRS"]
- 오디오 라벨: ['cleaning','desk_work','footsteps','hygiene','outdoor_ambient']

사용 예:
python py/fusion/01_late_fusion.py ^
  --imu_probs 0.05,0.10,0.15,0.60,0.05,0.05 ^
  --audio_wav data/ESC-50/audio/1-100032-A-0.wav

또는 오디오 확률을 CSV/JSON 등에서 읽어서 --audio_probs 로 직접 주는 것도 가능:
--audio_probs prob_cleaning=0.02,prob_desk_work=0.01,prob_footsteps=0.91,prob_hygiene=0.03,prob_outdoor_ambient=0.03

결과: 최종 라벨과 결합된 확률 벡터를 출력.
"""

import argparse
from pathlib import Path
import numpy as np
from joblib import load

# 고정 라벨 순서
HAR_LABELS = ["LAYING","SITTING","STANDING","WALKING","WALKING_DOWNSTAIRS","WALKING_UPSTAIRS"]
AUDIO_LABELS = ["cleaning","desk_work","footsteps","hygiene","outdoor_ambient"]

# 오디오→HAR 매핑 행렬 (shape: 6 x 5) 열 = 오디오, 행 = HAR
# 설계 원칙:
# - footsteps: WALKING 0.6, DOWN/UP 0.2/0.2 (계단은 IMU가 주도하므로 분산)
# - desk_work: SITTING 0.8, STANDING 0.2
# - hygiene: STANDING 0.8, SITTING 0.2
# - cleaning: STANDING 0.9, WALKING 0.1 (청소기 이동)
# - outdoor_ambient: WALKING 0.3, STANDING 0.2, 나머지는 미약(정규화 되어 합=1)
A2H = np.array([
    # cleaning, desk_work, footsteps, hygiene, outdoor_ambient
    [0.00,     0.00,      0.00,      0.00,    0.00],  # LAYING
    [0.00,     0.80,      0.00,      0.20,    0.10],  # SITTING
    [0.90,     0.20,      0.00,      0.80,    0.20],  # STANDING
    [0.10,     0.00,      0.60,      0.00,    0.30],  # WALKING
    [0.00,     0.00,      0.20,      0.00,    0.20],  # WALKING_DOWNSTAIRS
    [0.00,     0.00,      0.20,      0.00,    0.20],  # WALKING_UPSTAIRS
], dtype=float)

ROOT = Path(__file__).resolve().parents[2]
AUDIO_MODEL = ROOT/"outputs"/"audio"/"models"/"audio_rf.joblib"
AUDIO_LE    = ROOT/"outputs"/"audio"/"models"/"label_encoder.joblib"

def parse_probs_list(s):
    # "0.1,0.2,0.3,0.2,0.1,0.1" -> np.array shape (6,)
    vals = [float(x.strip()) for x in s.split(",")]
    arr = np.asarray(vals, dtype=float)
    if arr.shape[0] != 6:
        raise ValueError("imu_probs must have 6 comma-separated values for HAR labels order: "
                         + ",".join(HAR_LABELS))
    # 안정성 위해 정규화
    s = arr.sum()
    return arr / s if s > 0 else np.full(6, 1/6)

def parse_named_audio_probs(s):
    # "prob_cleaning=0.1,prob_desk_work=0.2,..." -> dict
    d = {}
    for kv in s.split(","):
        k, v = kv.split("=")
        d[k.strip()] = float(v.strip())
    return d

def extract_audio_probs_from_wav(wav_path: Path):
    # 오디오 모델 로드
    clf = load(AUDIO_MODEL)
    le  = load(AUDIO_LE)

    import librosa
    def extract_vec(p: Path, n_mfcc=40, sr=22050):
        y, sr = librosa.load(p, sr=sr)
        mfcc = librosa.feature.mfcc(y=y, sr=sr, n_mfcc=n_mfcc)
        d1 = librosa.feature.delta(mfcc)
        d2 = librosa.feature.delta(mfcc, order=2)
        feats = np.concatenate([mfcc, d1, d2], axis=0)
        return np.concatenate([feats.mean(axis=1), feats.std(axis=1)])

    x = extract_vec(wav_path).reshape(1, -1)
    proba = clf.predict_proba(x)[0]
    # label order를 AUDIO_LABELS로 정렬
    classes = list(le.classes_)  # ['cleaning','desk_work','footsteps','hygiene','outdoor_ambient']
    probs = np.zeros(len(AUDIO_LABELS), dtype=float)
    for i, cls in enumerate(classes):
        j = AUDIO_LABELS.index(cls)
        probs[j] = proba[i]
    return probs

def map_audio_to_har(audio_probs: np.ndarray) -> np.ndarray:
    # (5,) -> (6,) 선형변환
    har_from_audio = A2H @ audio_probs  # shape (6,)
    s = har_from_audio.sum()
    return har_from_audio / s if s > 0 else np.full(6, 1/6)

def late_fusion(imu_probs: np.ndarray, audio_probs_har: np.ndarray, alpha=0.8) -> np.ndarray:
    # 계단 라벨에는 오디오 영향 축소(안정성)
    stair_mask = np.array([0,0,0,0.5,0.0,0.0])  # DOWN/UP 가중 0, WALKING 0.5로 예시
    audio_adj = audio_probs_har.copy()
    audio_adj[4] *= stair_mask[4]
    audio_adj[5] *= stair_mask[5]
    # 가중 평균
    fused = alpha * imu_probs + (1 - alpha) * audio_adj
    s = fused.sum()
    return fused / s if s > 0 else np.full(6, 1/6)

def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--imu_probs", type=str, required=True,
                    help="HAR 6라벨 확률 CSV: LAYING,SITTING,STANDING,WALKING,WALKING_DOWNSTAIRS,WALKING_UPSTAIRS")
    ap.add_argument("--audio_wav", type=str, default="",
                    help="오디오 wav 경로(지정 시 wav→확률 추론)")
    ap.add_argument("--audio_probs", type=str, default="",
                    help="직접 오디오 확률 입력 (예: prob_cleaning=0.1,prob_desk_work=0.2,...)")
    ap.add_argument("--alpha", type=float, default=0.8, help="IMU 가중치(0~1), 기본 0.8")
    args = ap.parse_args()

    imu = parse_probs_list(args.imu_probs)

    if args.audio_wav:
        audio_prob_vec = extract_audio_probs_from_wav(Path(args.audio_wav))
    elif args.audio_probs:
        named = parse_named_audio_probs(args.audio_probs)
        audio_prob_vec = np.array([
            float(named.get("prob_cleaning", 0.0)),
            float(named.get("prob_desk_work", 0.0)),
            float(named.get("prob_footsteps", 0.0)),
            float(named.get("prob_hygiene", 0.0)),
            float(named.get("prob_outdoor_ambient", 0.0)),
        ], dtype=float)
        s = audio_prob_vec.sum()
        if s > 0: audio_prob_vec /= s
        else: audio_prob_vec = np.full(5, 1/5)
    else:
        # 오디오 정보가 없으면 균등 분포
        audio_prob_vec = np.full(5, 1/5)

    audio_as_har = map_audio_to_har(audio_prob_vec)
    fused = late_fusion(imu, audio_as_har, alpha=args.alpha)

    pred_idx = int(np.argmax(fused))
    print({
        "HAR_LABELS": HAR_LABELS,
        "imu_probs": imu.tolist(),
        "audio_probs_5": dict(zip(AUDIO_LABELS, audio_prob_vec.tolist())),
        "audio_as_har_probs": dict(zip(HAR_LABELS, audio_as_har.tolist())),
        "alpha": args.alpha,
        "fused_probs": dict(zip(HAR_LABELS, fused.tolist())),
        "final_pred": HAR_LABELS[pred_idx],
    })

if __name__ == "__main__":
    import numpy as np
    main()
