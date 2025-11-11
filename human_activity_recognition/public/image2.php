<?php
$root = realpath(__DIR__ . '/..');
$metricsPath = $root . '/outputs/image_fast_v2/metrics.json';
$data = file_exists($metricsPath) ? json_decode(file_get_contents($metricsPath), true) : null;
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Image-based HAR (HMDB51 → 5-class Grouped)</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
:root{
  --bg:#0b0f19; --card:#121a2a; --line:#22314f; --text:#e9eef7; --muted:#b4c0d3; --accent:#3b82f6; --codebg:#0f1625;
}
*{box-sizing:border-box}
html,body{height:100%}
body{
  margin:0; font-family:ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,Helvetica,Arial;
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
.sidebar{padding:20px; position:sticky; top:16px; height:fit-content;}
h1{margin:0 0 10px 0; font-size:24px; font-weight:800;}
h2{margin:14px 0 10px 0; font-size:18px; font-weight:700;}
p,li{color:var(--text); opacity:.92; line-height:1.7; font-size:15.5px;}
small,.muted{color:var(--muted); font-size:13px;}
.sidebar code{
  background:var(--codebg); color:#b9d2ff; padding:2px 5px; border-radius:6px;
  font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace; font-size:12.5px;
}
.pill{
  background:rgba(59,130,246,.15); color:#cfe2ff; border:1px solid rgba(59,130,246,.35);
  padding:8px 12px; border-radius:999px; display:inline-block; margin:0 8px 8px 0; font-weight:700;
}
.content{padding:18px;}
.grid{display:grid; grid-template-columns:1fr; gap:18px;}
.card{padding:16px;}
.chart-box{position:relative; height:360px; width:100%;}
@media (max-width:1000px){.container{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="container">
  <!-- 왼쪽: 설명 -->
  <aside class="sidebar">
    <h1>Image-based HAR</h1>
    <?php if ($data): ?>
      <div style="margin:6px 0 12px 0">
        <span class="pill">Accuracy <?=number_format($data['accuracy']*100,2)?>%</span>
        <span class="pill">Macro F1 <?=number_format($data['macro_f1']*100,2)?>%</span>
      </div>
    <?php endif; ?>

    <p class="muted">데이터 출처:<br>&emsp;<b>HMDB51 (5개 행동 그룹)</b></p>

    <h2>데이터셋</h2>
    <ul style="padding-left:18px">
      <li>총 2,000 이미지 (클래스당 400장)</li>
      <li>그룹 (5): <b>climbing, moving, posturing, interacting, exercising</b></li>
      <li>원본 HMDB51의 10클래스 → 5그룹으로 병합</li>
      <li>훈련 80% / 검증 20%</li>
      <li>전처리: 128×128 Resize + Augmentation + Normalize</li>
    </ul>

    <h2>모델</h2>
    <ul style="padding-left:18px">
      <li><b>ResNet18</b> (ImageNet pretrained)</li>
      <li>Optimizer: Adam (lr=1e-4, weight_decay=1e-5)</li>
      <li>Loss: CrossEntropyLoss</li>
      <li>Epochs: 6 (CPU)</li>
    </ul>

    <h2>ResNet18 개요</h2>
    <p>
      <b>ResNet18</b>은 <b>Residual Network</b> 기반의 18층 CNN으로,  
      skip connection을 통해 깊은 네트워크에서도 안정적인 학습이 가능합니다.
    </p>
    <p>
      본 실험에서는 HMDB51 영상에서 추출된 이미지 프레임을  
      <b>5개의 행동 그룹</b>으로 분류하도록 학습시켰습니다.
    </p>

    <h2>지표</h2>
    <ul style="padding-left:18px">
      <li>Accuracy, Macro-F1</li>
      <li>클래스별 Precision / Recall / F1-score</li>
    </ul>

    <br><br>
    <p class="muted">파이프라인:<br>&emsp;HMDB → 이미지 병합 → ResNet18 학습 → metrics.json 시각화</p>
  </aside>

  <!-- 오른쪽: 그래프 -->
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
const ticksColor = '#dbe7ff';
const gridColor  = 'rgba(219,231,255,0.15)';

// 1️⃣ 클래스별 F1-score
const ctx1 = document.getElementById('f1Chart').getContext('2d');
new Chart(ctx1, {
  type: 'bar',
  data: {
    labels: labels,
    datasets: [{
      label: 'F1-score',
      data: f1,
      backgroundColor: 'rgba(59,130,246,0.8)',
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

// 2️⃣ Accuracy / Macro-F1
const ctx2 = document.getElementById('overallChart').getContext('2d');
new Chart(ctx2, {
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
