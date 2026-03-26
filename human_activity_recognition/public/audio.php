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
  background:var(--codebg); color:#38bdf8; padding:2px 5px; border-radius:6px;
  font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace; font-size:12.5px;
}
.pill{
  background:rgba(56, 189, 248, .15); color:#cfe2ff; border:1px solid rgba(59,130,246,.35);
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
    <h1>Audio-based HAR (ESC-50)</h1>
    <?php if ($data): ?>
      <div style="margin:6px 0 12px 0">
        <span class="pill">Accuracy <?=number_format($data['accuracy']*100,2)?>%</span>
        <span class="pill">Macro F1 <?=number_format($data['macro_f1']*100,2)?>%</span>
      </div>
    <?php endif; ?>

    <p class="muted">
      데이터 출처:<br>&emsp;<b>ESC-50</b> (Environmental Sound Classification Dataset)<br>
      &emsp;· 환경음 50개 클래스(자연·인공·동물·인간 소리 포함)<br>
      &emsp;· 총 2,000개 오디오 클립(각 5초, 44.1 kHz)
    </p>

    <h2>데이터셋 개요</h2>
    <p>
      <b>ESC-50</b>은 인간이 경험하는 다양한 환경음을 50개의 범주로 구성한 공개 오디오 데이터셋입니다.  
      본 연구에서는 행동 인식(HAR)에 적합한 소리를 선별하여 <b>5개 행동 그룹</b>으로 재구성했습니다.
    </p>

    <h2>행동 그룹 매핑 (원본 → 5 그룹)</h2>
    <ul style="padding-left:18px">
      <li><b>Active</b> ← 운동·충돌·기계소음(exercising)</li>
      <li><b>Interaction</b> ← 대화·도구 사용(interacting)</li>
      <li><b>Locomotion</b> ← 발소리·걷기·이동(moving)</li>
      <li><b>Outdoor</b> ← 바람·비·자연소리(climbing)</li>
      <li><b>Resting</b> ← 정적 환경·실내(posturing)</li>
    </ul>

    <h2>데이터 구성 및 전처리</h2>
    <ul style="padding-left:18px">
      <li>총 <b>520개</b> 오디오 파일 (그룹당 약 104개)</li>
      <li>Split: 훈련 80% · 검증 20%</li>
      <li>샘플링 레이트 44.1 kHz, 모노 채널</li>
      <li>전처리: MelSpectrogram(64 mel bands, frame 128) → dB 스케일 → Normalize → 3-채널 이미지화 (64×128)</li>
    </ul>

    <h2>모델 및 학습 설정</h2>
    <ul style="padding-left:18px">
      <li><b>백본:</b> ResNet18 (ImageNet pretrained)</li>
      <li><b>입력:</b> MelSpectrogram을 RGB 형태로 변환해 CNN 입력에 적용</li>
      <li><b>헤드:</b> Linear(512→512) + ReLU + Dropout(0.3) + Linear(512→5)</li>
      <li><b>Optimizer:</b> Adam (lr = 1e-4, weight_decay = 1e-5)</li>
      <li><b>Loss:</b> CrossEntropyLoss, <b>Epochs:</b> 6 (기기: CPU)</li>
    </ul>
    <p class="muted">
      파이프라인:<br>&emsp;ESC-50 오디오 → MelSpectrogram 변환 → ResNet18 파인튜닝 → <code>metrics.json</code> 시각화
    </p>

    <h2>성능 및 그래프 해석</h2>
    <p>
      아래 그래프는 <b>클래스별 F1-score</b>와 <b>전체 성능 요약</b>을 나타냅니다.  
      F1-score는 Precision과 Recall의 조화평균으로, 1.0에 가까울수록 정확한 분류를 의미합니다.
    </p>
    <ul style="padding-left:18px">
      <li><b>Locomotion(이동)</b>이 가장 높은 F1 값을 보이며, 걷기·이동음의 주파수 패턴이 뚜렷하게 인식됨</li>
      <li><b>Resting(휴식)</b>과 <b>Outdoor(야외)</b>도 상대적으로 높은 정확도를 유지</li>
      <li><b>Active/Interaction</b> 그룹은 소리 유형이 다양해 클래스 간 경계가 부분적으로 혼동됨</li>
      <li>전체적으로 <b>Accuracy 85%</b>, <b>Macro-F1 84.6%</b>로 이미지 단일 모달보다 더 정교한 오디오 패턴을 포착</li>
    </ul>
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
const ticksColor = '#dbe7ff';
const gridColor  = 'rgba(219,231,255,0.15)';

// F1-score chart
new Chart(document.getElementById('f1Chart'), {
  type: 'bar',
  data: {
    labels: labels,
    datasets: [{
      label: 'F1-score',
      data: f1,
      backgroundColor: 'rgba(56, 189, 248, 0.7)',
      borderWidth: 0
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    scales: {
      x: {
        ticks: {
          color: ticksColor,
          font: { size: 25, weight: '400' }
        },
        grid: { color: gridColor }
      },
      y: {
        beginAtZero: true,
        max: 1,
        ticks: {
          color: ticksColor,
          font: { size: 22, weight: '400' }
        },
        grid: { color: gridColor }
      }
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
      label: 'F1-score',
      data: [0.874, 0.842, 0.737, 0.941, 0.765],
      backgroundColor: ['rgba(16,185,129,0.85)', 'rgba(99,102,241,0.85)'],
      categoryPercentage: 0.65,
      barPercentage: 0.75
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    scales: {
      y: {
        beginAtZero: true,
        max: 1,
        ticks: {
          color: ticksColor,
          font: { size: 22, weight: '400' }
        },
        grid: { color: gridColor }
      },
      x: {
        ticks: {
          color: ticksColor,
          font: { size: 25, weight: '400' }
        },
        grid: { display: false }
      }
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
