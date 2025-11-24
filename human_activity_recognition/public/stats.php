<?php
require_once __DIR__ . '/../config/db.php';
$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

$result = $mysqli->query("
  SELECT predicted_label, COUNT(*) as cnt
  FROM har_fusion_results
  GROUP BY predicted_label
  ORDER BY cnt DESC
");

$labels = [];
$counts = [];
while ($row = $result->fetch_assoc()) {
    $labels[] = $row['predicted_label'];
    $counts[] = $row['cnt'];
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="utf-8" />
<title>HAR 예측 통계</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
body {
  margin:0; padding:60px;
  background:#0b0f19;
  font-family:ui-sans-serif,system-ui,-apple-system,Roboto;
  color:white;
}

.container {
  max-width:900px;
  margin:auto;
}

.card {
  background:#121a2a;
  border-radius:20px;
  padding:32px;
  box-shadow:0 8px 24px rgba(0,0,0,0.4);
}

h1 {
  font-size:2rem;
  margin-bottom:20px;
  display:flex;
  align-items:center;
  gap:12px;
}

.chart-wrapper {
  margin-top:24px;
  padding:20px;
  border-radius:16px;
  background:#0f1625;
}
</style>
</head>

<body>
<div class="container">

  <h1>📊 HAR 예측 통계</h1>

  <div class="card">
    <p style="color:#94a3b8;">Fusion 기반 예측 분포 (지금까지 저장된 모든 결과)</p>

    <div class="chart-wrapper">
      <canvas id="chart" height="260"></canvas>
    </div>

  </div>
</div>

<script>
const labels = <?= json_encode($labels) ?>;
const counts = <?= json_encode($counts) ?>;

const ctx = document.getElementById('chart');

new Chart(ctx, {
  type: 'bar',
  data: {
    labels: labels,
    datasets: [{
      label: '예측 횟수',
      data: counts,
      borderRadius: 12,
      backgroundColor: '#3b82f6',
    }]
  },
  options: {
    plugins: {
      legend: { display: false }
    },
    scales: {
      x: {
        grid: { display:false },
        ticks: {
          color:'#e2e8f0',
          font:{ size:14 }
        }
      },
      y: {
        beginAtZero:true,
        ticks: { color:'#94a3b8' },
        grid: { color:'#1e293b' }
      }
    }
  }
});
</script>

</body>
</html>
