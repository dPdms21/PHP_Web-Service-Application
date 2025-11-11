<?php
$root = realpath(__DIR__ . '/..');
$metricsPath = $root . '/outputs/image_fast/metrics.json';
$data = file_exists($metricsPath) ? json_decode(file_get_contents($metricsPath), true) : null;
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Image-based HAR (HMDB51 → HAR)</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
:root{
  --bg:#0b0f19; --card:#121a2a; --line:#22314f; --text:#e9eef7; --muted:#b4c0d3; --accent:#3b82f6; --codebg:#0f1625;
}
*{box-sizing:border-box}
html,body{height:100%}
body{
  margin:0; font-family: ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,Helvetica,Arial;
  background:var(--bg); color:var(--text);
}
.container{
  display:grid; grid-template-columns:380px 1fr; gap:22px;
  max-width:1280px; margin:24px auto; padding:0 16px;
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
  background:var(--codebg); color:#b9d2ff; padding:2px 5px; border-radius:6px;
  font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace; font-size:12.5px;
}
.pill{
  background:rgba(59,130,246,.15); color:#cfe2ff; border:1px solid rgba(59,130,246,.35);
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
  <!-- LEFT: 설명 패널 -->
  <aside class="sidebar">
    <h1>Image-based HAR</h1>
    <?php if ($data): ?>
      <div style="margin:6px 0 12px 0">
        <span class="pill">Accuracy <?=number_format($data['accuracy']*100,2)?>%</span>
        <span class="pill">Macro F1 <?=number_format($data['macro_f1']*100,2)?>%</span>
      </div>
    <?php endif; ?>

    <p class="muted">데이터 출처: <br>&emsp;<b>HMDB51 (이미지 프레임)</b> → HAR 라벨 매핑</p>

    <h2>데이터셋</h2>
    <ul style="padding-left:18px">
      <li>총 약 2,000 이미지</li>
      <li>클래스 (4): WALKING, WALKING_UPSTAIRS, SITTING, STANDING</li>
      <li>훈련 80% / 검증 20%</li>
      <li>전처리: 96×96 Resize + Normalize</li>
    </ul>

    <h2>모델</h2>
    <ul style="padding-left:18px">
      <li><b>ResNet18</b> (ImageNet pretrained)</li>
      <li>Optimizer: Adam (lr=1e-4)</li>
      <li>Loss: CrossEntropyLoss</li>
      <li>Epochs: 3 (CPU)</li>
    </ul>

    <h2>ResNet18 개요</h2>
    <p>
      <b>ResNet18</b>은 <b>Residual Network</b> 구조를 기반으로 한 18층 CNN 모델로,  
      <b>skip connection</b>(지름길 연결)을 통해 깊은 신경망에서도 학습이 안정적으로 이루어집니다.
    </p>
    <p>
      기존 CNN은 층이 깊어질수록 <b>기울기 소실(Gradient Vanishing)</b>이 발생하지만,  
      ResNet은 입력값을 일부 그대로 더해주는 <b>Residual Block</b>을 사용해 이를 해결했습니다.
    </p>
    <ul style="padding-left:18px">
      <li>구조: Conv → BatchNorm → ReLU + Skip Connection</li>
      <li>18층 구성 (가벼운 버전으로 CPU에서도 가능)</li>
      <li>ImageNet 사전학습(Pretrained)으로 빠른 수렴</li>
      <li>적은 데이터로도 높은 정확도 달성</li>
    </ul>
    <p>
      즉, ResNet18은 <b>이미지 분류</b> 분야에서 가장 널리 쓰이는  
      <b>기본 전이학습(Transfer Learning) 모델</b>이며,  
      지금 이 실험에서는 사람의 <b>행동(자세) 인식</b>을 위해  
      HAR 라벨 체계(WALKING, SITTING 등)에 맞춰 재학습되었습니다.
    </p>

    <h2>지표</h2>
    <ul style="padding-left:18px">
      <li>Accuracy, Macro-F1</li>
      <li>클래스별 Precision / Recall / F1-score</li>
    </ul>

    <br><br>
    <p class="muted">파이프라인:<br>&emsp;HMDB → 프레임 추출 → 라벨 매핑 → ResNet 학습 → metrics.json</p>
  </aside>

  <!-- RIGHT: 그래프 2개 -->
  <main class="content">
    <div class="grid">

      <!-- 클래스별 F1 -->
      <div class="card">
        <h2>클래스별 F1-score</h2>
        <?php if ($data): ?>
          <div class="chart-box"><canvas id="f1Chart"></canvas></div>
        <?php else: ?>
          <p class="muted">학습 결과가 없습니다.</p>
        <?php endif; ?>
      </div>

      <!-- Accuracy / Macro-F1 -->
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

// 2️⃣ 전체 성능 (Accuracy / Macro-F1)
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
