<?php
// ========================================
// fusion_result.php
// ========================================

// 업로드 경로 준비
$root = realpath(__DIR__ . '/..');
$uploadDir = $root . "/uploads/video/";

if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);

// 업로드된 파일 확인
if (!isset($_FILES['video'])) {
    die("영상이 업로드되지 않았습니다.");
}

// 파일 저장
$filename = time() . "_" . basename($_FILES['video']['name']);
$savePath = $uploadDir . $filename;

move_uploaded_file($_FILES['video']['tmp_name'], $savePath);

// Python 실행 경로
$python = "C:/xampp/htdocs/webS/human_activity_recognition/.venv311/Scripts/python.exe";
$pyScript = "C:/xampp/htdocs/webS/human_activity_recognition/py/infer/fusion_infer.py";

$fixedPath = str_replace('\\', '/', $savePath);
$cmd = "\"$python\" \"$pyScript\" " . escapeshellarg($fixedPath) . " 2>&1";
$output = shell_exec($cmd);

$data = json_decode($output, true);

// 에러 처리
if (!$data || isset($data["error"])) {
    die("<b>❌ Python 모델 실행 실패 또는 JSON 파싱 실패</b><br>
         <pre>$output</pre>");
}

$pred = $data['predicted_action'];
$confRaw = $data['confidence'];
$conf = round($confRaw * 100, 2);

$top3 = $data['top3'] ?? [];

// DB 저장
require_once __DIR__ . '/../config/db.php';
$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

$stmt = $mysqli->prepare("
    INSERT INTO har_fusion_results 
    (video_name, predicted_label, confidence)
    VALUES (?, ?, ?)
");
$stmt->bind_param("ssd", $filename, $pred, $confRaw);
$stmt->execute();
$stmt->close();

$mysqli->close();
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="utf-8">
<title>Fusion HAR 결과</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
body { background:#0b0f19; color:white; padding:40px; font-family:system-ui; }
.card {
  max-width:700px; margin:auto; background:#121a2a; padding:32px;
  border-radius:16px; box-shadow:0 8px 24px rgba(0,0,0,.4);
}
video {
  width:100%; border-radius:14px; margin-bottom:20px;
  border:1px solid #22314f;
}
a { color:#3b82f6; }
.top3 {background:#0f1625; padding:18px; border-radius:12px; margin-top:20px;}
canvas {margin-top:10px; background:#0f1625; border-radius:12px;}
</style>
</head>

<body>
<div class="card">
  <h2>🎥 Fusion 기반 HAR 결과</h2>

  <video controls>
      <source src="../uploads/video/<?= $filename ?>" type="video/mp4">
  </video>

  <p><b>예측 결과:</b> <?= $pred ?></p>
  <p><b>신뢰도:</b> <?= $conf ?>%</p>

  <?php if (!empty($top3)): ?>
  <div class="top3">
      <h3>Top 3</h3>
      <canvas id="chartTop3" height="150"></canvas>
  </div>
  <?php endif; ?>

  <a href="main.php">← 메인으로 돌아가기</a>
</div>

<?php if (!empty($top3)): ?>
<script>
const ctx = document.getElementById('chartTop3');
new Chart(ctx, {
  type: 'bar',
  data: {
    labels: <?= json_encode(array_column($top3, 'label')) ?>,
    datasets: [{
      label: 'Confidence (%)',
      data: <?= json_encode(array_map(fn($x)=>round($x['prob']*100,2), $top3)) ?>,
      backgroundColor: ['#3b82f6','#10b981','#8b5cf6']
    }]
  },
  options: { indexAxis: 'y', plugins:{legend:{display:false}} }
});
</script>
<?php endif; ?>
</body>
</html>
