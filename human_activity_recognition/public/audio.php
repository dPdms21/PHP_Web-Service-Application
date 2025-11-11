<?php
$root = realpath(__DIR__ . '/..');
$metricsPath = $root . '/outputs/audio_fast/metrics.json';
$data = file_exists($metricsPath) ? json_decode(file_get_contents($metricsPath), true) : null;
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Audio-based HAR (ESC-50 5-group)</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
:root{
  --bg:#0b0f19; --card:#121a2a; --line:#22314f; --text:#e9eef7; --muted:#b4c0d3; --accent:#f59e0b; --codebg:#0f1625;
}
*{box-sizing:border-box}
html,body{height:100%}
body{
  margin:0; font-family: ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,Helvetica,Arial;
  background:var(--bg); color:var(--text);
}
.container{
  display:grid; grid-template-columns:380px 1fr; gap:22px;
  max-width:1380px; margin:24px auto; padding:0 16px;
}
.sidebar,.content,.card{
  background:var(--card); border:1px solid var(--line); border-radius:14px;
  box-shadow:0 10px 24px rgba(0,0,0,.25);
}
.sidebar{ padding:20px; position:sticky; top:16px; height:fit-content; }
h1{margin:0 0 10px 0; font-size:24px; font-weight:800; letter-spacing:.2px}
h2{margin:14px 0 10px 0; font-size:18px; font-weight:700}
p,li{color:var(--text); opacity:.92; line-height:1.7; font-size:15.5px}
small,.muted{color:var(--muted); font-size:13px}
.sidebar code{
  background:var(--codebg); color:#fcd34d; padding:2px 5px; border-radius:6px;
  font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace; font-size:12.5px;
}
.pill{
  background:rgba(245,158,11,.15); color:#fcd34d; border:1px solid rgba(245,158,11,.35);
  padding:8px 12px; border-radius:999px; display:inline-block; margin:0 8px 8px 0; font-weight:700;
}
.content{padding:18px}
.grid{display:grid; grid-template-columns:1fr; gap:18px}
.card{padding:16px}
.chart-box{position:relative; height:360px; width:100%}
@media (max-width:1000px){ .container{grid-template-columns:1fr} }
</style>
</head>
<body>
<div class="container">
  <!-- LEFT -->
  <aside class="sidebar">
    <h1>Audio-based HAR</h1>
    <?php if ($data): ?>
      <div style="margin:6px 0 12px 0">
        <span class="pill">Accuracy <?=number_format($data['accuracy']*100,2)?>%</span>
        <span class="pill">Macro F1 <?=number_format($data['macro_f1']*100,2)?>%</span>
      </div>
    <?php endif; ?>

    <p class="muted">데이터 출처:<br>&emsp;<b>ESC-50 (5개 행동 그룹)</b></p>

    <h2>데이터셋</h2>
    <ul style="padding-left:18px">
      <li>총 약 520 오디오 파일</li>
      <li>그룹 (5): climbing, exercising, interacting, moving, posturing</li>
      <li>훈련 80% / 검증 20%</li>
      <li>전처리: MelSpectrogram (64×128) → Normalize</li>
    </ul>

    <h2>모델</h2>
    <ul style="padding-left:18px">
      <li><b>ResNet18</b> (ImageNet pretrained)</li>
      <li>Optimizer: Adam (lr=1e-4, weight_decay=1e-5)</li>
      <li>Loss: CrossEntropyLoss</li>
      <li>Epochs: 6 (CPU)</li>
    </ul>

    <h2>입력 특징</h2>
    <p>
      ESC-50 데이터셋의 환경음을 기반으로 <b>5가지 인간 행동군</b>에 해당하는 소리를 추출하였습니다.
      <br>예: <code>exercising</code> → 운동/충돌음, <code>moving</code> → 발소리/이동음 등
    </p>

    <h2>지표</h2>
    <ul style="padding-left:18px">
      <li>Accuracy, Macro-F1</li>
      <li>클래스별 Precision / Recall / F1-score</li>
    </ul>

    <br><br>
    <p class="muted">파이프라인:<br>&emsp;ESC-50 → MelSpectrogram 변환 → ResNet18 학습 → metrics.json 시각화</p>
  </aside>

  <!-- RIGHT -->
  <main class="content">
    <div class="grid">

      <div class="card">
        <h2>클래스별 F1-score</h2>
        <?php if ($data): ?>
          <div class="chart-box"><canvas id="f1Chart"></canvas></div>
        <?php else: ?>
          <p class="muted">학습 결과가 없습니다.</p>
        <?php endif; ?>
      </div>

      <div class="card">
        <h2>전체 성능 요약</h2>
        <?php if ($data): ?>
          <div class="chart-box"><canvas id="overallChart"></canvas></div>
        <?php else: ?>
          <p class="muted">학습 결과가 없습니다.</p>
        <?php endif; ?>
      </div>

    </div>
  </main>
</div>

<?php if ($data): ?>
<script>
const labels = <?= json_encode($data['labels']) ?>;
const f1 = <?= json_encode($data['f1']) ?>;
const acc = <?= $data['accuracy'] ?>;
const macro = <?= $data['macro_f1'] ?>;
const ticksColor = '#fcd34d';
const gridColor  = 'rgba(252,211,77,0.15)';

// F1-score chart
new Chart(document.getElementById('f1Chart'), {
  type: 'bar',
  data: {
    labels: labels,
    datasets: [{
      label: 'F1-score',
      data: f1,
      backgroundColor: 'rgba(245,158,11,0.8)',
      borderWidth: 0
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    scales: {
      x: { ticks: { color: ticksColor }, grid: { color: gridColor } },
      y: { beginAtZero: true, max: 1, ticks: { color: ticksColor }, grid: { color: gridColor } }
    },
    plugins: {
      legend: { display: false },
      tooltip: { callbacks: { label: (ctx) => ` F1: ${ctx.parsed.y.toFixed(3)}` } }
    }
  }
});

// Accuracy / Macro-F1 chart
new Chart(document.getElementById('overallChart'), {
  type: 'bar',
  data: {
    labels: ['Accuracy', 'Macro-F1'],
    datasets: [{
      label: 'Performance',
      data: [acc, macro],
      backgroundColor: ['rgba(16,185,129,0.85)', 'rgba(99,102,241,0.85)']
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    scales: {
      y: { beginAtZero: true, max: 1, ticks: { color: ticksColor }, grid: { color: gridColor } },
      x: { ticks: { color: ticksColor }, grid: { display: false } }
    },
    plugins: {
      legend: { display: false },
      tooltip: { callbacks: { label: (ctx) => ` ${(ctx.parsed.y*100).toFixed(2)}%` } }
    }
  }
});
</script>
<?php endif; ?>
</body>
</html>
