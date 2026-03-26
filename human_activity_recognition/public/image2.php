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
:root{ --bg:#0b0f19; --card:#121a2a; --line:#22314f; --text:#e9eef7; --muted:#b4c0d3; --accent:#3b82f6; --codebg:#0f1625; }
*{box-sizing:border-box}
html,body{height:100%}
body{ margin:0; font-family:ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,Helvetica,Arial; background:var(--bg); color:var(--text); }
.container{ display:grid; grid-template-columns:380px 1fr; gap:22px; max-width:1380px; margin:24px auto; padding:0 16px; }
.sidebar,.content,.card{ background:var(--card); border:1px solid var(--line); border-radius:14px; box-shadow:0 10px 24px rgba(0,0,0,.25); }
.sidebar{padding:20px; position:sticky; top:16px; height:fit-content;}
h1{margin:0 0 10px 0; font-size:24px; font-weight:800;}
h2{margin:14px 0 10px 0; font-size:18px; font-weight:700;}
p,li{color:var(--text); opacity:.92; line-height:1.7; font-size:15.5px;}
small,.muted{color:var(--muted); font-size:13px;}
.sidebar code{ background:var(--codebg); color:#b9d2ff; padding:2px 5px; border-radius:6px; font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace; font-size:12.5px; }
.pill{ background:rgba(59,130,246,.15); color:#cfe2ff; border:1px solid rgba(59,130,246,.35); padding:8px 12px; border-radius:999px; display:inline-block; margin:0 8px 8px 0; font-weight:700; }
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
    <h1>Image-based HAR (HMDB51)</h1>
    <?php if ($data): ?>
      <div style="margin:6px 0 12px 0">
        <span class="pill">Accuracy <?=number_format($data['accuracy']*100,2)?>%</span>
        <span class="pill">Macro F1 <?=number_format($data['macro_f1']*100,2)?>%</span>
      </div>
    <?php endif; ?>

    <p class="muted">
      출처: <b>HMDB51</b> (Human Motion Database) <br>
      &emsp;· 인간 행동 인식(HAR)을 위한 공개 비디오 데이터셋 <br>
      &emsp;· 51개 행동 클래스, 약 7,000개 영상 클립
    </p>

    <h2>데이터셋 개요</h2>
    <p>
      <b>HMDB51</b>은 인간의 다양한 움직임(걷기, 뛰기, 대화, 앉기 등)을 포함하는 영상 기반 행동 인식 데이터셋입니다. 
      조명·배경·카메라 각도가 다양하여 모델의 일반화 성능을 평가하기에 적합합니다. 
      본 실험에서는 HMDB51의 10개 행동 클래스를 선정하여 <b>5개 행동 그룹</b>으로 병합했습니다.
    </p>

    <h2>행동 그룹 매핑 (10 → 5)</h2>
    <ul style="padding-left:18px">
      <li><b>Active</b> ← <code>jump</code>, <code>situp</code></li>
      <li><b>Interaction</b> ← <code>eat</code>, <code>talk</code></li>
      <li><b>Locomotion</b> ← <code>walk</code>, <code>run</code>, <code>climb_stairs</code></li>
      <li><b>Outdoor</b> ← <code>climb</code></li>
      <li><b>Resting</b> ← <code>sit</code>, <code>stand</code></li>
    </ul>

    <h2>데이터 구성 및 전처리</h2>
    <p>
      그룹별 균형을 위해 <b>Locomotion</b>과 <b>Resting</b>은 각각 <b>600장</b>, 그 외 그룹은 <b>400장</b>을 사용하여 
      총 <b>약 2,400장 이미지</b>로 학습했습니다. 데이터는 훈련 80%, 검증 20%로 분리되며, 다음과 같은 증강을 적용했습니다.
    </p>
    <ul style="padding-left:18px">
      <li>Resize: 128×128</li>
      <li>Random Horizontal Flip, Rotation(±8°), Affine(±10°, ±5%)</li>
      <li>ColorJitter(밝기·대비·채도 소폭)</li>
      <li>Normalize(ImageNet mean/std)</li>
    </ul>

    <h2>모델 및 학습 설정</h2>
    <ul style="padding-left:18px">
      <li><b>백본:</b> ResNet18 (ImageNet pretrained)</li>
      <li><b>헤드:</b> Linear(512→512) + ReLU + Dropout(0.3) + Linear(512→5)</li>
      <li><b>Optimizer:</b> Adam (lr=8e-5, weight_decay=1e-5)</li>
      <li><b>Scheduler:</b> StepLR(step_size=4, γ=0.6)</li>
      <li><b>Loss / Epochs / Device:</b> CrossEntropyLoss / 8 Epochs / CPU</li>
    </ul>
    <p class="muted">
      파이프라인: HMDB51 프레임 추출 → 라벨 병합(10→5) → ResNet18 파인튜닝 → 
      <code>metrics.json</code> → PHP 대시보드 시각화
    </p>

    <h2>성능 및 그래프 해석</h2>
    <p>
      전체 정확도 <b><?=number_format($data['accuracy']*100,2)?>%</b>, Macro F1 <b><?=number_format($data['macro_f1']*100,2)?>%</b>입니다. 
      아래 그래프의 <b>F1-score</b>는 각 그룹별 정밀도와 재현율의 조화평균을 의미합니다.
    </p>
    <ul style="padding-left:18px">
      <li><b>Outdoor</b>가 가장 높은 F1-score를 기록 — 배경과 동작이 뚜렷합니다.</li>
      <li><b>Interaction</b>도 손·얼굴 중심 모션으로 높은 구분력을 보입니다.</li>
      <li><b>Locomotion</b>·<b>Resting</b>은 정지/이동 경계가 모호한 프레임에서 일부 혼동이 있었으나, 전체적으로 안정적입니다.</li>
    </ul>
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

// 1) 클래스별 F1-score
new Chart(document.getElementById('f1Chart').getContext('2d'), {
  type: 'bar',
  data: {
    labels: labels,
    datasets: [{ label: 'F1-score', data: f1, backgroundColor: 'rgba(59,130,246,0.8)', borderWidth: 0 }]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
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
    plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c)=>` F1: ${c.parsed.y.toFixed(3)}` } } }
  }
});

// 2) Accuracy / Macro-F1
new Chart(document.getElementById('overallChart').getContext('2d'), {
  type: 'bar',
  data: {
    labels: ['Accuracy', 'Macro-F1'],
    datasets: [{
      label: 'F1-score',
      data: [0.874, 0.842, 0.737, 0.941, 0.765],
      backgroundColor: ['rgba(16,185,129,0.85)','rgba(99,102,241,0.85)'],
      categoryPercentage: 0.65,
      barPercentage: 0.75
    }]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
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
    plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c)=>` ${(c.parsed.y*100).toFixed(2)}%` } } }
  }
});
</script>
<?php endif; ?>
</body>
</html>
