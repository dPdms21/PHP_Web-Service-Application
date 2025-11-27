<?php
// ===============================================
// 🏠 메인 대시보드 (이미지/오디오/퓨전 + 통계)
// ===============================================
require_once __DIR__ . '/../config/db.php';
$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

$counts = [];
$recent = [];

// 기본 5개 그룹 초기값
$counts = [
    "Active" => 0,
    "Interaction" => 0,
    "Locomotion" => 0,
    "Outdoor" => 0,
    "Resting" => 0
];

if (!$mysqli->connect_errno) {

    // ========================================
    // 🔥 예측 라벨 기반 그룹 카운트 실시간 계산
    // ========================================
    $res = $mysqli->query("
        SELECT predicted_label, COUNT(*) AS cnt
        FROM har_fusion_results
        GROUP BY predicted_label
    ");

    while ($row = $res->fetch_assoc()) {
        $label = $row['predicted_label'];
        if (isset($counts[$label])) {
            $counts[$label] = (int)$row['cnt'];
        }
    }

    // ========================================
    // 🔥 최신 5개 결과
    // ========================================
    $res2 = $mysqli->query("
        SELECT video_name, predicted_label, confidence, created_at
        FROM har_fusion_results
        ORDER BY created_at DESC
        LIMIT 5
    ");

    while ($row = $res2->fetch_assoc()) {
        $recent[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="utf-8">
<title>HAR 메인 대시보드</title>
<style>
:root {
  --bg:#0b0f19; --card:#121a2a; --line:#22314f;
  --text:#e9eef7; --accent:#3b82f6; --muted:#94a3b8;
}
body {
  margin:0; padding:40px; background:var(--bg); color:var(--text);
  font-family:ui-sans-serif,system-ui,-apple-system,Segoe UI,Roboto;
}
.card {
  background:var(--card);
  border-radius:14px;
  padding:28px 32px;
  margin:16px auto;
  max-width:1200px;
  box-shadow:0 6px 20px rgba(0,0,0,0.35);
}
.btn-group {display:flex; flex-wrap:wrap; gap:12px; margin:18px 0;}
.btn {
  background:var(--accent); padding:14px 22px; border-radius:10px; 
  color:white; font-weight:600; text-decoration:none;
}
.count-box {display:flex; flex-wrap:wrap; gap:14px; margin-top:14px;}
.box {
  background:#0f1625; border-radius:12px; padding:16px 20px;
  flex: 1 1 180px; text-align:center;
}
.box h3 {margin:0; color:#38bdf8;}
.muted {color:var(--muted); font-size:0.9rem;}

table {
  width:100%; border-collapse:collapse; margin-top:16px;
}
th, td {
  padding:12px 16px;
  border-bottom:1px solid var(--line);
  text-align:left;
}
th {color:#60a5fa; font-size:1rem;}
td {font-size:0.95rem;}
</style>
</head>

<body>

<div class="card">
  <h1 style="margin-bottom:0.4em;">🏠 HAR 메인 대시보드</h1>
  <p class="muted">이미지 · 오디오 · Fusion 영상 기반 행동 인식</p>

  <!-- 버튼 -->
  <div class="btn-group">
    <a class="btn" href="image_upload.php">🖼 이미지 업로드</a>
    <a class="btn" href="audio_upload.php">🎧 오디오 업로드</a>
    <a class="btn" href="fusion_upload.php">🎥 Fusion 영상 업로드</a>
  </div>

  <hr style="border:1px solid var(--line); margin:26px 0;">

  <!-- 행동 그룹 카운트 -->
  <h2>📊 행동 그룹 카운트</h2>
  <p class="muted">10개 행동 → 5개 그룹 기반 누적 카운트</p>

  <div class="count-box">
    <?php foreach ($counts as $grp => $val): ?>
      <div class="box">
        <h3><?=htmlspecialchars($grp)?></h3>
        <p style="font-size:1.8rem; font-weight:700; margin:8px 0;"><?=$val?></p>
      </div>
    <?php endforeach; ?>
  </div>

  <hr style="border:1px solid var(--line); margin:26px 0;">

  <!-- 최근 5개 -->
  <h2>📝 최근 결과 5개</h2>
  <table>
    <tr>
      <th>영상/이미지 이름</th>
      <th>예측 라벨</th>
      <th>확률</th>
      <th>시간</th>
    </tr>

    <?php foreach ($recent as $r): ?>
      <tr>
        <td><?=htmlspecialchars($r['video_name'])?></td>
        <td><?=htmlspecialchars($r['predicted_label'])?></td>
        <td><?=round($r['confidence'] * 100, 2)?>%</td>
        <td><?=htmlspecialchars($r['created_at'])?></td>
      </tr>
    <?php endforeach; ?>
  </table>

  <div class="btn-group" style="margin-top:28px;">
    <a class="btn" style="background:#10b981;" href="list_results.php">📄 전체 결과 보기</a>
    <a class="btn" style="background:#8b5cf6;" href="stats.php">📈 통계 페이지</a>
  </div>
</div>

</body>
</html>
