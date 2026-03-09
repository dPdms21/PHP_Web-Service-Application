<?php
require_once __DIR__ . '/../config/db.php';
$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

/* ===============================
   1) 원본 행동 라벨 통계 (10 클래스)
   =============================== */
$res1 = $mysqli->query("
  SELECT predicted_label, COUNT(*) as cnt
  FROM har_fusion_results
  GROUP BY predicted_label
  ORDER BY cnt DESC
");

$labels_raw = [];
$counts_raw = [];

while ($row = $res1->fetch_assoc()) {
    $labels_raw[] = $row['predicted_label'];
    $counts_raw[] = $row['cnt'];
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="utf-8" />
<title>HAR 통계</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
body {
  margin:0; padding:60px;
  background:#0b0f19;
  font-family:ui-sans-serif,system-ui,-apple-system,Roboto;
  color:white;
}

.container {
  max-width:1100px;
  margin:auto;
}

.card {
  background:#121a2a;
  border-radius:20px;
  padding:32px;
  margin-top:30px;
  box-shadow:0 8px 24px rgba(0,0,0,0.4);
}

h1 {
  font-size:2rem;
  margin-bottom:20px;
  display:flex;
  align-items:center;
  gap:12px;
}

h2 {
  margin:0 0 16px 0;
  color:#60a5fa;
}

.chart-wrapper {
  margin-top:12px;
  padding:20px;
  border-radius:16px;
  background:#0f1625;
}

.btn {
  background:#3b82f6;
  padding:12px 20px;
  border-radius:10px;
  color:white;
  text-decoration:none;
  font-weight:600;
}
</style>
</head>

<body>
<div class="container">

  <h1>📊 HAR 예측 통계</h1>
  <a href="main.php" class="btn">🏠 메인으로</a>

  <!-- 1) 원본 행동(10개) -->
  <div class="card">
    <h2>🔹 원본 행동 예측 분포 (10 Class)</h2>
    <p style="color:#94a3b8;">지금까지 저장된 모든 예측값의 분포입니다.</p>

    <div class="chart-wrapper">
      <canvas id="chart_raw" height="260"></canvas>
    </div>
  </div>

</div>

<script>
/* ==========================
   1) 원본 행동 (10-class)
   ========================== */
const rawLabels  = <?= json_encode($labels_raw) ?>;
const rawCounts  = <?= json_encode($counts_raw) ?>;

new Chart(document.getElementById('chart_raw'), {
  type: 'bar',
  data: {
    labels: rawLabels,
    datasets: [{
      label: '횟수',
      data: rawCounts,
      borderRadius: 12,
      backgroundColor: '#3b82f6'
    }]
  },
  options: {
    plugins: { legend:{ display:false } },
    scales: {
      x: { ticks:{ color:'#e2e8f0' }, grid:{ display:false } },
      y: { beginAtZero:true, ticks:{ color:'#94a3b8' }, grid:{ color:'#1e293b' } }
    }
  }
});
</script>

</body>
</html>
