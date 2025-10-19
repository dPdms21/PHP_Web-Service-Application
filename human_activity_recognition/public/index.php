<?php
$root = realpath(__DIR__ . '/..');
$metricsPath = $root . '/outputs/metrics.json';
$data = file_exists($metricsPath) ? json_decode(file_get_contents($metricsPath), true) : null;
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8" />
<title>HAR 대시보드</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
  body { max-width: 1000px; margin: 24px auto; font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; color:#e5e7eb; background:#0b1220; }
  h1 { margin:0 0 12px 0; }
  .card { background:#111827; padding:16px; border-radius:12px; margin-bottom:16px; border:1px solid #1f2937; }
  .pill { background:#1f2937; padding:8px 12px; border-radius:999px; display:inline-block; margin-right:8px; }
  a.btn { background:#2563eb; color:#fff; padding:8px 12px; border-radius:8px; text-decoration:none; }
</style>
</head>
<body>
  <h1>Human Activity Recognition (UCI HAR)</h1>

  <div class="card">
    <h2>학습 결과</h2>
    <?php if ($data): ?>
      <span class="pill">Accuracy: <b><?=number_format($data['accuracy'] * 100, 2)?>%</b></span>
      <span class="pill">Macro F1: <b><?=number_format($data['macro_f1'] * 100, 2)?>%</b></span>
      <a class="btn" href="train.php">재학습 실행</a>
    <?php else: ?>
      <p>아직 결과가 없습니다. 먼저 아래 버튼으로 학습을 실행하세요.</p>
      <a class="btn" href="train.php">학습 실행</a>
    <?php endif; ?>
  </div>

  <?php if ($data): ?>
    <div class="card">
      <h2>혼동행렬</h2>
      <canvas id="cm"></canvas>
    </div>

    <div class="card">
      <h2>클래스별 F1</h2>
      <canvas id="f1"></canvas>
    </div>

    <script>
      const labels = <?=json_encode($data['labels'])?>;
      const cm = <?=json_encode($data['confusion_matrix'])?>;
      const f1 = <?=json_encode(array_map(fn($c) => $c['f1'], $data['per_class']))?>;

      // 혼동행렬을 true별 stacked bar로 표현
      const cmDatasets = cm.map((row, i) => ({
        label: 'True: ' + labels[i],
        data: row,
        borderWidth: 1
      }));
      new Chart(document.getElementById('cm'), {
        type: 'bar',
        data: { labels, datasets: cmDatasets },
        options: { responsive: true, scales:{ x:{stacked:true}, y:{stacked:true, beginAtZero:true} }, plugins:{ legend:{ position:'bottom' } } }
      });

      // 클래스별 F1 막대
      new Chart(document.getElementById('f1'), {
        type: 'bar',
        data: { labels, datasets: [{ label:'F1-score', data: f1, borderWidth:1 }] },
        options: { responsive: true, scales: { y: { beginAtZero:true, max:1 } } }
      });
    </script>
  <?php endif; ?>
</body>
</html>
