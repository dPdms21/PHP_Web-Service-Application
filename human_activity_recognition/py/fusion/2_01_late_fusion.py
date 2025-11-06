# py/fusion/01_late_fusion.py
import os, csv, argparse, numpy as np

# 네 코드의 순서에 맞춤
IMU_CLASSES = ["LAYING","SITTING","STANDING","WALKING","WALKING_DOWNSTAIRS","WALKING_UPSTAIRS"]

def softmax(x, t=1.0):
    x = np.array(x, dtype=float) / float(t)
    x = x - np.max(x)
    e = np.exp(x)
    return e / np.sum(e)

def load_probs(csv_path, expected_classes=None):
    """
    CSV 형식:
      sample_id, true_class, pred_class, pred_score, p_CLASS1, p_CLASS2, ...
    """
    with open(csv_path, newline="", encoding="utf-8") as f:
        r = csv.reader(f); header = next(r)
        rows = [row for row in r]
    rows = np.array(rows, dtype=object)

    prob_start = 4
    class_names = [h[2:] for h in header[prob_start:]]  # "p_CLASS" -> "CLASS"
    probs = rows[:, prob_start:].astype(float)
    true_labels = rows[:, 1] if rows.shape[1] > 1 else np.array([""]*len(rows))
    sample_ids = rows[:, 0] if rows.shape[1] > 0 else np.arange(len(rows)).astype(str)

    # expected_classes가 주어지면 그 순서로 재정렬
    if expected_classes is not None:
        new_probs = np.zeros((probs.shape[0], len(expected_classes)), dtype=float)
        for j, cname in enumerate(expected_classes):
            if cname in class_names:
                src_idx = class_names.index(cname)
                new_probs[:, j] = probs[:, src_idx]
            else:
                new_probs[:, j] = 0.0
        probs = new_probs
        class_names = expected_classes[:]
    return sample_ids, true_labels, class_names, probs

def expand_image_to_imu(img_probs, img_classes, imu_ref=None):
    """
    이미지 3클래스 [WALKING, SITTING, STANDING] → IMU 6라벨로 확장
    네 IMU 순서에 맞춰 매핑:
      idx: LAY(0) SIT(1) STD(2) WALK(3) DOWN(4) UP(5)

    - WALKING 이미지는 보행 3개(WALK/ DOWN/ UP)에 분배
      (기본은 균등 1/3, imu_ref 있으면 그 비율 사용)
    - SITTING → SITTING
    - STANDING → STANDING
    - LAYING은 이미지 없음(0)
    """
    idx_map = {c.lower(): i for i, c in enumerate(img_classes)}  # e.g. {'sitting':0, 'standing':1, 'walking':2} 등
    has_walk = "walking" in idx_map
    has_sit  = "sitting" in idx_map
    has_std  = "standing" in idx_map

    N = img_probs.shape[0]
    out = np.zeros((N, len(IMU_CLASSES)), dtype=float)

    p_walk = img_probs[:, idx_map["walking"]] if has_walk else np.zeros(N)
    p_sit  = img_probs[:, idx_map["sitting"]] if has_sit  else np.zeros(N)
    p_std  = img_probs[:, idx_map["standing"]] if has_std else np.zeros(N)

    if imu_ref is not None:
        walk_sum = np.clip(imu_ref[:, 3] + imu_ref[:, 4] + imu_ref[:, 5], 1e-8, None)
        w_walk = imu_ref[:, 3] / walk_sum
        w_down = imu_ref[:, 4] / walk_sum
        w_up   = imu_ref[:, 5] / walk_sum
    else:
        w_walk = np.full(N, 1/3); w_down = np.full(N, 1/3); w_up = np.full(N, 1/3)

    # LAYING(0): 없음
    out[:, 1] = p_sit                       # SITTING
    out[:, 2] = p_std                       # STANDING
    out[:, 3] = p_walk * w_walk             # WALKING
    out[:, 4] = p_walk * w_down             # WALKING_DOWNSTAIRS
    out[:, 5] = p_walk * w_up               # WALKING_UPSTAIRS
    return out

def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--imu_csv", required=True, help="예: outputs/imu_rf/imu_probs.csv (6라벨 순서는 코드상 고정)")
    ap.add_argument("--image_csv", required=True, help="예: outputs/image_action_fast/image_probs.csv (3라벨: walking/sitting/standing)")
    ap.add_argument("--audio_csv", default="", help="선택: ESC-50 기반 6라벨 확률 CSV (이미 6라벨 공간이어야 함)")
    ap.add_argument("--out_dir", default="outputs/fusion")
    ap.add_argument("--w_imu", type=float, default=0.7)
    ap.add_argument("--w_img", type=float, default=0.3)
    ap.add_argument("--w_aud", type=float, default=0.0)
    ap.add_argument("--temperature", type=float, default=1.0)
    args = ap.parse_args()

    os.makedirs(args.out_dir, exist_ok=True)

    # 1) 로드
    imu_sid, imu_true, imu_classes, imu_probs = load_probs(args.imu_csv, expected_classes=IMU_CLASSES)
    img_sid, img_true, img_classes, img_probs = load_probs(args.image_csv)  # 이미지 클래스 순서는 파일 기준

    # 길이 맞추기(간단히 min)
    N = min(len(imu_sid), len(img_sid))
    imu_sid, imu_true, imu_probs = imu_sid[:N], imu_true[:N], imu_probs[:N]
    img_sid, img_true, img_probs = img_sid[:N], img_true[:N], img_probs[:N]

    # 2) 이미지 3라벨 → IMU 6라벨
    img_to_imu = expand_image_to_imu(img_probs, img_classes, imu_ref=imu_probs)

    # 3) (옵션) 오디오 6라벨 CSV
    if args.audio_csv and os.path.exists(args.audio_csv):
        aud_sid, aud_true, aud_classes, aud_probs = load_probs(args.audio_csv, expected_classes=IMU_CLASSES)
        M = min(N, len(aud_sid))
        aud_probs = aud_probs[:M]
        imu_probs = imu_probs[:M]; img_to_imu = img_to_imu[:M]
        N = M
        w_imu, w_img, w_aud = args.w_imu, args.w_img, args.w_aud
    else:
        aud_probs = None
        w_imu, w_img, w_aud = args.w_imu, args.w_img, args.w_aud

    # 4) 가중합 + softmax(temperature)
    fused = w_imu * imu_probs + w_img * img_to_imu
    if aud_probs is not None:
        fused += w_aud * aud_probs
    fused = np.apply_along_axis(lambda x: softmax(x, t=args.temperature), 1, fused)

    pred_idx = fused.argmax(axis=1)
    pred_cls = [IMU_CLASSES[i] for i in pred_idx]
    pred_score = fused.max(axis=1)

    out_csv = os.path.join(args.out_dir, "fused_probs.csv")
    with open(out_csv, "w", newline="", encoding="utf-8") as f:
        w = csv.writer(f)
        header = ["sample_id","true_class","pred_class","pred_score"] + [f"p_{c}" for c in IMU_CLASSES]
        w.writerow(header)
        for i in range(N):
            w.writerow([imu_sid[i], imu_true[i], pred_cls[i], float(pred_score[i]), *[float(p) for p in fused[i].tolist()]])
    print(f"✅ Saved: {out_csv}")

if __name__ == "__main__":
    main()
