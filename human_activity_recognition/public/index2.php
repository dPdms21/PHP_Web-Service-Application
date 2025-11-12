<?php
$root = realpath(__DIR__ . '/..');
$metricsPath = $root . '/outputs/imu/metrics.json';
$data = file_exists($metricsPath) ? json_decode(file_get_contents($metricsPath), true) : null;
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Human Activity Recognition (UCI HAR)</title>
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
  display:grid; grid-template-columns: 380px 1fr; gap:22px;
  max-width:1280px; margin:24px auto; padding:0 16px;
}
.sidebar,.content,.card{
  background:var(--card); border:1px solid var(--line); border-radius:14px;
  box-shadow:0 10px 24px rgba(0,0,0,.25);
}
.sidebar{ padding:20px; position:sticky; top:16px; height:fit-content; }
h1{margin:0 0 10px 0; font-size:24px; font-weight:800; letter-spacing:.2px}
h2{margin:14px 0 10px 0; font-size:18px; font-weight:700}
p,li,dd{color:var(--text); opacity:.92; line-height:1.7; font-size:15.5px}
small,.muted{color:var(--muted); font-size:13px}
.sidebar p,.sidebar ul,.sidebar dl{max-width:100%}
.sidebar code{
  background:var(--codebg); color:#b9d2ff; padding:2px 5px; border-radius:6px;
  font-family: ui-monospace,SFMono-Regular,Menlo,Consolas,monospace; font-size:12.5px;
}
.pill{
  background:rgba(59,130,246,.15); color:#cfe2ff; border:1px solid rgba(59,130,246,.35);
  padding:8px 12px; border-radius:999px; display:inline-block; margin:0 8px 8px 0; font-weight:700;
}
a.btn{
  background:var(--accent); color:#fff; padding:10px 14px; border-radius:10px; text-decoration:none; display:inline-block; font-weight:700;
}
.content{padding:18px}
.grid{display:grid; grid-template-columns:1fr; gap:18px}
.card{padding:16px}
.chart-box{position:relative; height:360px; width:100%}
img.cm{width:100%; height:auto; border-radius:10px; border:1px solid var(--line)}
@media (max-width:1000px){ .container{grid-template-columns:1fr} }
</style>
</head>
<body>
  <div class="container">
    <!-- LEFT: 설명 패널 -->
    <aside class="sidebar">
      <h1>Human Activity Recognition (UCI HAR)</h1>
      <?php if ($data): ?>
        <div style="margin:6px 0 12px 0">
          <span class="pill">Accuracy <?=number_format($data['accuracy']*100,2)?>%</span>
          <span class="pill">Macro F1 <?=number_format($data['macro_f1']*100,2)?>%</span>
        </div>
      <?php endif; ?>

      <p class="muted">출처: UCI HAR <br>&emsp;(Human Activity Recognition with Smartphones) <br>&emsp;· 스마트폰 IMU(가속도/자이로) 공개 데이터셋</p>

      <h2>데이터셋</h2>
      <p><b>샘플:</b> 총 10,299 (Train 7,352 · Test 2,947), <br>&emsp;<b>피험자 30명</b><br>
         <b>라벨(6개):</b> WALKING, WALKING_UPSTAIRS, <br>&emsp;WALKING_DOWNSTAIRS, SITTING, <br>&emsp;STANDING, LAYING</p>
      <p><b>수집/윈도잉:</b> 허리 장착 스마트폰, 약 50Hz <br>&emsp;→ 2.56초(128시점) 창, 50% 오버랩</p>
      <p><b>특징:</b> 통계·주파수 기반 <b>561차원</b> 벡터 <br>&emsp;(예: <code>tBodyAcc-mean()</code>, <code>tBodyGyro-std()</code>)</p>
      <p><b>입력 파일:</b> <code>train.csv</code>/<code>test.csv</code> 또는 <br>&emsp;UCI TXT 구조를 자동 감지하여 로드</p>

      <h2>모델/학습</h2>
      <ul style="padding-left:18px">
        <li><b>스케일링:</b> <code>StandardScaler</code> (Z-정규화)</li>
        <li><b>모델:</b> <code>RandomForestClassifier</code> <br>&emsp;(n_estimators=300, <br>&emsp;random_state=42, n_jobs=-1)</li>
        <li><b>과제 정의:</b> 6-클래스 다중분류</li>
        <li><b>지표:</b> Accuracy, Macro-F1, <br>&emsp;Confusion Matrix, 클래스별 PRF</li>
      </ul>
      <p class="muted">파이프라인: <br>&emsp;561D 특징 → 스케일러 → RF 예측 → 지표/그래프 렌더</p>

      <h2>확장</h2>
      <ul style="padding-left:18px">
        <li>SVM/로지스틱 비교, <br>&emsp;특징 중요도(Feature Importance) 시각화</li>
        <li>원시 신호 기반 1D-CNN/LSTM</li>
        <li>Subject-wise 검증, DB 로깅/대시보드</li>
      </ul>
      <br>
      <div style="margin-top:10px"><a class="btn" href="train.php">재학습 실행</a></div>
    </aside>

    <!-- RIGHT: 그래프 2개 -->
    <main class="content">
      <div class="grid">

        <!-- 클래스 분포 (Train/Test) -->
        <div class="card">
          <h2>클래스 분포 (Train / Test)</h2>
          <?php if ($data): ?>
            <div class="chart-box"><canvas id="classDist"></canvas></div>
          <?php else: ?>
            <p class="muted">학습 후 확인 가능합니다.</p>
          <?php endif; ?>
        </div>

        <!-- 오분류 TOP-5 -->
        <div class="card">
          <h2>오분류 상위 5쌍 (Misclassification Top-5)</h2>
          <?php if ($data): ?>
            <div class="chart-box"><canvas id="misTop"></canvas></div>
          <?php else: ?>
            <p class="muted">학습 후 확인 가능합니다.</p>
          <?php endif; ?>
        </div>

      </div>
    </main>
  </div>

<?php if ($data): ?>
<script>
  // 공통 색상
  const ticksColor = '#dbe7ff';
  const gridColor  = 'rgba(219,231,255,0.15)';

  // ===== 클래스 분포 (Train/Test) =====
  const labels = <?= json_encode($data['labels']) ?>;
  const cm     = <?= json_encode($data['confusion_matrix'] ?? []) ?>;

  // Test 분포: 혼동행렬의 각 행 합계(= 실제 라벨 분포)
  const testCounts = (cm && cm.length)
    ? cm.map(row => row.reduce((a,b) => a + b, 0))
    : (<?= json_encode($data['class_dist']['test'] ?? []) ?> || []);

  // Train 분포(있으면 사용, 없으면 null)
  const trainCounts = <?= json_encode($data['class_dist']['train'] ?? null) ?>;

  const distCtx = document.getElementById('classDist').getContext('2d');
  new Chart(distCtx, {
    type: 'bar',
    data: {
      labels: labels.map(l => l.includes('_') ? l.split('_') : l),
      datasets: [
        ...(trainCounts ? [{
          label: 'Train',
          data: trainCounts,
          backgroundColor: 'rgba(59,130,246,0.75)',
          borderWidth: 0,
          categoryPercentage: 0.7,
          barPercentage: 0.8
        }] : []),
        {
          label: 'Test',
          data: testCounts,
          backgroundColor: trainCounts ? 'rgba(16,185,129,0.85)' : 'rgba(99,102,241,0.85)',
          borderWidth: 0,
          categoryPercentage: 0.7,
          barPercentage: 0.8
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      scales: {
        x: {
          stacked: false,
          ticks: { color: ticksColor, autoSkip: false, padding: 8, minRotation: 0, maxRotation: 0 },
          grid: { color: gridColor }
        },
        y: {
          beginAtZero: true,
          ticks: { color: ticksColor },
          grid: { color: gridColor }
        }
      },
      plugins: {
        legend: { position: 'top', labels: { color: ticksColor } },
        tooltip: { callbacks: {
          label: (ctx) => ` ${ctx.dataset.label}: ${ctx.parsed.y}`
        } }
      }
    }
  });

  // ===== 오분류 TOP-5 =====
  const misPairs = [];
  for (let i = 0; i < cm.length; i++) {
    for (let j = 0; j < cm[i].length; j++) {
      if (i === j) continue; // 정분류 제외
      const c = cm[i][j];
      if (c > 0) misPairs.push({ pair: `${labels[i]} → ${labels[j]}`, count: c });
    }
  }
  misPairs.sort((a,b) => b.count - a.count);
  const top5 = misPairs.slice(0, 5);

  const misCtx = document.getElementById('misTop').getContext('2d');
  new Chart(misCtx, {
    type: 'bar',
    data: {
      labels: top5.map(p => p.pair.replaceAll('_','\n')),
      datasets: [{
        label: '오분류 개수',
        data: top5.map(p => p.count),
        backgroundColor: 'rgba(239,68,68,0.85)', // 빨강톤
        borderWidth: 0,
        categoryPercentage: 0.8,
        barPercentage: 0.9
      }]
    },
    options: {
      indexAxis: 'y', // 가로 막대
      responsive: true,
      maintainAspectRatio: false,
      scales: {
        x: {
          beginAtZero: true,
          ticks: { color: ticksColor },
          grid: { color: gridColor }
        },
        y: {
          ticks: { color: ticksColor, autoSkip: false },
          grid: { display: false }
        }
      },
      plugins: {
        legend: { display: false },
        tooltip: { callbacks: {
          label: (ctx) => ` ${ctx.parsed.x} 건`
        } }
      }
    }
  });
</script>
<?php endif; ?>
</body>
</html>
