<?php
$root = realpath(__DIR__ . '/..');

// --- CSV & JSON 로드 유틸 ---
function read_json($path) {
    return file_exists($path) ? json_decode(file_get_contents($path), true) : null;
}
function read_csv_clean($path) {
    if (!file_exists($path)) return [];
    $rows = [];
    $fp = fopen($path, 'r');
    $hdr = fgetcsv($fp);
    while ($r = fgetcsv($fp)) {
        if (count($r) === count($hdr))
            $rows[] = array_combine($hdr, $r);
    }
    fclose($fp);
    return $rows;
}

// --- 경로 설정 ---
$imuMetrics = $root . '/outputs/imu/metrics.json';
$imageCSV   = $root . '/outputs/image_action_fast/per_class.csv';
$audioCSV   = $root . '/outputs/audio/preds/per_class.csv';
$fusionSum  = $root . '/outputs/fusion/fused_summary.txt';

// --- 데이터 로드 ---
$imuData   = read_json($imuMetrics);
$imageData = read_csv_clean($imageCSV);
$audioData = read_csv_clean($audioCSV);

// --- Fusion summary 읽기 ---
$acc = $macro_f1 = $weighted_f1 = null;
if (file_exists($fusionSum)) {
    foreach (file($fusionSum, FILE_IGNORE_NEW_LINES) as $line) {
        if (str_starts_with($line, 'accuracy'))    $acc = (float)explode('=', $line)[1];
        if (str_starts_with($line, 'macro_f1'))    $macro_f1 = (float)explode('=', $line)[1];
        if (str_starts_with($line, 'weighted_f1')) $weighted_f1 = (float)explode('=', $line)[1];
    }
}

// --- CSV 데이터 필터링 ---
function clean_per_class($data) {
    return array_values(array_filter($data, function($row) {
        return isset($row['class']) &&
               !str_starts_with($row['class'], 'prob_') &&
               $row['class'] !== 'unknown' &&
               $row['class'] !== '';
    }));
}
$imageData = clean_per_class($imageData);
$audioData = clean_per_class($audioData);
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>IMU / Image / Audio 모달별 성능 비교</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
:root{
  --bg:#0b0f19; --card:#121a2a; --line:#22314f; --text:#e9eef7;
  --muted:#b4c0d3; --accent:#3b82f6;
}
body{
  margin:0; font-family:ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,Helvetica,Arial;
  background:var(--bg); color:var(--text);
}
.container{
  display:grid; grid-template-columns:420px 1fr; gap:22px;
  max-width:1440px; margin:24px auto; padding:0 16px;
}
.sidebar,.card{
  background:var(--card); border:1px solid var(--line);
  border-radius:14px; box-shadow:0 10px 24px rgba(0,0,0,.25);
}
.sidebar{padding:20px; position:sticky; top:16px; height:fit-content;}
h1{margin:0 0 10px;font-size:24px;font-weight:800;}
h2{margin:14px 0 10px;font-size:18px;font-weight:700;}
p,li{color:var(--text);opacity:.92;line-height:1.7;font-size:15.5px;}
small,.muted{color:var(--muted);font-size:13px;}
.pill{
  background:rgba(59,130,246,.15); color:#cfe2ff;
  border:1px solid rgba(59,130,246,.35);
  padding:8px 12px; border-radius:999px; display:inline-block;
  margin:0 8px 8px 0; font-weight:700;
}
.content{padding:18px;}
.grid{display:grid;grid-template-columns:1fr;gap:18px;}
.card{padding:16px;}
.chart-box{position:relative;height:360px;width:100%;}
@media(max-width:1000px){.container{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="container">

  <!-- LEFT PANEL -->
  <aside class="sidebar">
    <h1>3-Modal Fusion 결과</h1>

    <hr style="border:none;height:1px;background:var(--line);margin:18px 0;">

    <h2>모달별 성능 비교</h2>
    <div style="line-height:1.8">
      <?php
      // --- IMU 성능 ---
      if ($imuData && isset($imuData['accuracy'])) {
          echo "<p><b>IMU</b> ▶ Accuracy " . number_format($imuData['accuracy']*100,2) . "%";
          if (isset($imuData['macro_f1']))
              echo " | F1 " . number_format($imuData['macro_f1']*100,2) . "%";
          echo "</p>";
      } else echo "<p><b>IMU</b> ▶ N/A</p>";

      // --- Image 성능 ---
      if ($imageData) {
          $f1s = array_map('floatval', array_column($imageData, 'f1'));
          $mean_f1 = count($f1s) ? array_sum($f1s)/count($f1s) : 0;

          // Precision & Recall 평균으로 근사 Accuracy 계산
          $precisions = array_map('floatval', array_column($imageData, 'precision'));
          $recalls = array_map('floatval', array_column($imageData, 'recall'));
          $acc_est = (count($precisions) > 0)
              ? (array_sum($precisions) + array_sum($recalls)) / (2 * count($precisions))
              : 0;

          echo "<p><b>Image</b> ▶ Accuracy " . number_format($acc_est*100,2) .
              "% | F1 " . number_format($mean_f1*100,2) . "%</p>";
      } else echo "<p><b>Image</b> ▶ N/A</p>";

      // --- Audio 성능 ---
      if ($audioData) {
          $f1s = array_map('floatval', array_column($audioData, 'f1'));
          $mean_f1 = count($f1s) ? array_sum($f1s)/count($f1s) : 0;

          $precisions = array_map('floatval', array_column($audioData, 'precision'));
          $recalls = array_map('floatval', array_column($audioData, 'recall'));
          $acc_est = (count($precisions) > 0)
              ? (array_sum($precisions) + array_sum($recalls)) / (2 * count($precisions))
              : 0;

          echo "<p><b>Audio</b> ▶ Accuracy " . number_format($acc_est*100,2) .
              "% | F1 " . number_format($mean_f1*100,2) . "%</p>";
      } else echo "<p><b>Audio</b> ▶ N/A</p>";
      ?>
    </div>

    <hr style="border:none;height:1px;background:var(--line);margin:18px 0;">

    <h2>Fusion 구성</h2>
    <ul style="padding-left:18px">
      <li>IMU (UCI HAR 6라벨)</li>
      <li>Image (Stanford40 행동 인식)</li>
      <li>Audio (ESC-50 환경 소리)</li>
    </ul>
    <p class="muted">
      세 모달의 확률 벡터를 가중 결합 (IMU 0.7, Audio 0.1, Image 0.2)<br>
      → 각 샘플별 통합 예측 수행<br>
      → 클래스별 Precision / Recall / F1 비교
    </p>

    <hr style="border:none;height:1px;background:var(--line);margin:20px 0;">
    <h2>IMU (UCI HAR)</h2>
    <p><b>데이터:</b> 스마트폰 IMU 센서 (가속도 + 자이로)<br>샘플 10,299개, 라벨 6종</p>
    <p><b>모델:</b> RandomForestClassifier (n=300)<br>입력 561차원 통계/주파수 특징</p>
    <p><b>지표:</b> Accuracy / Macro-F1 / Confusion Matrix</p>

    <h2>Image (Stanford40)</h2>
    <p><b>데이터:</b> Stanford 40 Actions 데이터셋<br>행동 이미지 40클래스, 약 10,000장</p>
    <p><b>모델:</b> CNN 기반 Fast Action Classifier<br>ResNet-18 fine-tuning</p>
    <p><b>지표:</b> Precision / Recall / F1 per class</p>

    <h2>Audio (ESC-50)</h2>
    <p><b>데이터:</b> 환경 소리(ESC-50) 중 5개 행동 연관 라벨<br>
      cleaning, desk_work, footsteps, hygiene, outdoor_ambient</p>
    <p><b>모델:</b> RandomForestClassifier (MFCC+Δ+Δ² 특징 120D)</p>
    <p><b>지표:</b> Precision / Recall / F1 per class</p>
  </aside>

  <!-- RIGHT: 그래프 -->
  <main class="content">
    <div class="grid">

      <div class="card">
        <h2>IMU (UCI HAR 6라벨)</h2>
        <?php if ($imuData): ?><div class="chart-box"><canvas id="imuChart"></canvas></div>
        <?php else:?><p class="muted">IMU metrics.json 파일이 없습니다.</p><?php endif; ?>
      </div>

      <div class="card">
        <h2>Image (Stanford40 행동 인식)</h2>
        <?php if ($imageData): ?><div class="chart-box"><canvas id="imgChart"></canvas></div>
        <?php else:?><p class="muted">Image per_class.csv 파일이 없습니다.</p><?php endif; ?>
      </div>

      <div class="card">
        <h2>Audio (ESC-50 환경 소리)</h2>
        <?php if ($audioData): ?><div class="chart-box"><canvas id="audChart"></canvas></div>
        <?php else:?><p class="muted">Audio per_class.csv 파일이 없습니다.</p><?php endif; ?>
      </div>
    </div>
  </main>
</div>

<script>
const ticksColor = '#dbe7ff';
function renderChart(id, labels, precisions, recalls, f1s){
  new Chart(document.getElementById(id), {
    type:'bar',
    data:{
      labels,
      datasets:[
        {label:'Precision',data:precisions,backgroundColor:'rgba(59,130,246,0.8)'},
        {label:'Recall',data:recalls,backgroundColor:'rgba(16,185,129,0.8)'},
        {label:'F1-score',data:f1s,backgroundColor:'rgba(234,179,8,0.8)'}
      ]
    },
    options:{
      responsive:true,maintainAspectRatio:false,
      scales:{
        y:{beginAtZero:true,max:1,ticks:{color:ticksColor,stepSize:0.2}},
        x:{ticks:{color:ticksColor,autoSkip:false}}
      },
      plugins:{
        legend:{position:'top',labels:{color:ticksColor}},
        tooltip:{callbacks:{label:ctx=>`${ctx.dataset.label}: ${(ctx.parsed.y*100).toFixed(1)}%`}}
      }
    }
  });
}

<?php if ($imuData): ?>
renderChart(
  'imuChart',
  <?= json_encode($imuData['labels']) ?>,
  <?= json_encode(array_map(fn($l)=>$imuData['per_class'][$l]['precision']??0,$imuData['labels'])) ?>,
  <?= json_encode(array_map(fn($l)=>$imuData['per_class'][$l]['recall']??0,$imuData['labels'])) ?>,
  <?= json_encode(array_map(fn($l)=>$imuData['per_class'][$l]['f1']??0,$imuData['labels'])) ?>
);
<?php endif; ?>

<?php if ($imageData): ?>
renderChart(
  'imgChart',
  <?= json_encode(array_column($imageData,'class')) ?>,
  <?= json_encode(array_map('floatval',array_column($imageData,'precision'))) ?>,
  <?= json_encode(array_map('floatval',array_column($imageData,'recall'))) ?>,
  <?= json_encode(array_map('floatval',array_column($imageData,'f1'))) ?>
);
<?php endif; ?>

<?php if ($audioData): ?>
renderChart(
  'audChart',
  <?= json_encode(array_column($audioData,'class')) ?>,
  <?= json_encode(array_map('floatval',array_column($audioData,'precision'))) ?>,
  <?= json_encode(array_map('floatval',array_column($audioData,'recall'))) ?>,
  <?= json_encode(array_map('floatval',array_column($audioData,'f1'))) ?>
);
<?php endif; ?>
</script>
</body>
</html>
