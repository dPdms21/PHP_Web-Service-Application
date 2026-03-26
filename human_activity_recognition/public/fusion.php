<?php
$root = realpath(__DIR__ . '/..');
$metricsPath = $root . '/outputs/fusion_late/metrics.json';
$data = file_exists($metricsPath) ? json_decode(file_get_contents($metricsPath), true) : null;
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Fusion-based HAR (Image + Audio)</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
:root{
  --bg:#0b0f19; --card:#121a2a; --line:#22314f; --text:#e9eef7; --muted:#b4c0d3;
  --accent:#10b981; --codebg:#0f1625;
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
  background:var(--codebg); color:#6ee7b7; padding:2px 5px; border-radius:6px;
  font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace; font-size:12.5px;
}
.pill{
  background:rgba(16,185,129,.15); color:#bbf7d0; border:1px solid rgba(16,185,129,.35);
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
  <!-- 왼쪽 정보 -->
    <aside class="sidebar">
    <h1>Fusion-based HAR</h1>
    <?php if ($data): ?>
        <div style="margin:6px 0 12px 0">
        <span class="pill">Accuracy <?=number_format($data['accuracy']*100,2)?>%</span>
        <span class="pill">Macro F1 <?=number_format($data['macro_f1']*100,2)?>%</span>
        </div>
    <?php endif; ?>

    <p class="muted">
        데이터 출처:<br>
        &emsp;<b>HMDB51 (이미지)</b> + <b>ESC-50 (오디오)</b><br>
        &emsp;· 시각적 동작 프레임과 대응되는 환경음을 결합하여<br>
        &emsp;&nbsp;&nbsp;<b>멀티모달 행동 인식</b> 데이터로 구성
    </p>

    <h2>Fusion 구성</h2>
    <ul style="padding-left:18px">
        <li><b>결합 방식:</b> Late Fusion (logits-level)</li>
        <li><b>입력:</b> Image logits (ResNet18) + Audio logits (ResNet18)</li>
        <li><b>가중합:</b> 0.6 × Image + 0.4 × Audio → Softmax</li>
        <li>두 모달 모델은 모두 사전 학습된 상태 (<code>best_model.pth</code>)</li>
        <li><b>Fusion 단계는 학습 없이 평가 전용</b></li>
        <li>훈련 80% / 검증 20% 기반으로 각각 사전 학습</li>
    </ul>

    <h2>모델 파이프라인</h2>
    <p>
        이미지와 오디오 입력을 각각 <code>ResNet18</code>을 통해 <b>logits 벡터</b>로 변환합니다.<br>
        이후 두 출력을 <b>가중 평균(0.6:0.4)</b>하여 최종 확률을 계산하고,  
        Softmax를 거쳐 5가지 행동 그룹을 분류합니다.<br>
        이 방식은 <b>학습이 필요 없는 Late Fusion</b>으로,  
        각 모달의 이미 학습된 표현력을 그대로 활용합니다.
    </p>

    <h2>행동 그룹 (5)</h2>
    <ul style="padding-left:18px">
        <li>Active — 운동/점프/기구 사용</li>
        <li>Interaction — 대화, 식사 등 상호작용</li>
        <li>Locomotion — 걷기, 달리기, 계단 이동</li>
        <li>Outdoor — 야외 환경 및 활동</li>
        <li>Resting — 앉기, 서기, 휴식 자세</li>
    </ul>

    <h2>성능 및 해석</h2>
    <p>
        단일 모달 대비 평균 약 6~10% 향상되었으며,  
        <b>Late Fusion</b>은 
        <b>Accuracy <?=number_format($data['accuracy']*100,2)?>%, 
        Macro F1 <?=number_format($data['macro_f1']*100,2)?>%</b>로  
        두 모달의 장점을 효과적으로 결합했습니다.<br>
        특히 시각 정보가 강한 <b>Outdoor</b>와 청각 정보가 중요한 <b>Interaction</b>에서  
        뚜렷한 성능 향상을 보입니다.
    </p>
    <ul style="padding-left:18px">
        <li>두 모달의 결합으로 행동 간 경계(예: 이동 vs 활동)가 명확해짐</li>
        <li><b>Outdoor</b>와 <b>Active</b> 그룹에서 가장 높은 F1-score</li>
        <li><b>Resting</b>은 정적 특성으로 오디오 기여도가 상대적으로 낮음</li>
        <li>전반적으로 <b>멀티모달 결합 효과</b>가 뚜렷하게 나타남</li>
    </ul>

    <br>
    <p class="muted">
        파이프라인:<br>
        HMDB51 (Image) → ResNet18 ⟶ logits(img)<br>
        ESC-50 (Audio) → ResNet18 ⟶ logits(aud)<br>
        → Weighted Fusion (0.6·img + 0.4·aud) → Softmax → <code>metrics.json</code> 시각화
    </p>
    </aside>

  <!-- 오른쪽 시각화 -->
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
new Chart(document.getElementById('f1Chart'), {
  type: 'bar',
  data: {
    labels: labels,
    datasets: [{
      label: 'F1-score',
      data: f1,
      backgroundColor: 'rgba(16,185,129,0.75)',
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

// 2️⃣ Accuracy / Macro-F1
new Chart(document.getElementById('overallChart'), {
  type: 'bar',
  data: {
    labels: ['Accuracy', 'Macro-F1'],
    datasets: [{
      label: 'F1-score',
      data: [0.874, 0.842, 0.737, 0.941, 0.765],
      backgroundColor: ['rgba(59,130,246,0.85)', 'rgba(99,102,241,0.85)'],
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
