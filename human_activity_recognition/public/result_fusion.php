<?php
$root = realpath(__DIR__ . '/..');
$csvPath = $root . '/outputs/fusion/fused_3modal.csv';

// CSV 로드
if (!file_exists($csvPath)) {
    $error = "융합 결과 CSV 파일을 찾을 수 없습니다.";
    $data = [];
} else {
    $rows = [];
    if (($h = fopen($csvPath, 'r')) !== false) {
        $header = fgetcsv($h);
        while (($r = fgetcsv($h)) !== false) $rows[] = array_combine($header, $r);
        fclose($h);
    }
    $data = $rows;
}

// Fused 라벨 분포
$labelCounts = [];
if ($data) {
    foreach ($data as $row) {
        $label = $row['pred_fused'] ?? 'UNKNOWN';
        $labelCounts[$label] = ($labelCounts[$label] ?? 0) + 1;
    }
}
$HAR_LABELS = ["LAYING","SITTING","STANDING","WALKING","WALKING_DOWNSTAIRS","WALKING_UPSTAIRS"];
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>HAR 3모달 융합 결과</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
:root{
    --bg:#0b0f19; --card:#121a2a; --line:#22314f;
    --text:#e9eef7; --muted:#b4c0d3; --accent:#3b82f6; --codebg:#0f1625;
}
*{box-sizing:border-box}
html,body{height:100%}
body{
    margin:0; font-family: ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,Helvetica,Arial;
    background:var(--bg); color:var(--text);
}
.container{display:grid; grid-template-columns: 380px 1fr; gap:22px;
    max-width:1400px; margin:24px auto; padding:0 16px;}
.sidebar,.content,.card{
    background:var(--card); border:1px solid var(--line);
    border-radius:14px; box-shadow:0 10px 24px rgba(0,0,0,.25);
}
.sidebar{padding:20px; position:sticky; top:16px; height:fit-content;}
h1{margin:0 0 10px; font-size:24px; font-weight:800;}
h2{margin:14px 0 10px; font-size:18px; font-weight:700}
p,li{color:var(--text); opacity:.92; line-height:1.7; font-size:15.5px}
small,.muted{color:var(--muted); font-size:13px}
.pill{
    background:rgba(59,130,246,.15); color:#cfe2ff; border:1px solid rgba(59,130,246,.35);
    padding:8px 12px; border-radius:999px; display:inline-block; margin:0 8px 8px 0; font-weight:700;
}
a.btn{
    background:var(--accent); color:#fff; padding:10px 14px;
    border-radius:10px; text-decoration:none; display:inline-block; font-weight:700
}
.content{padding:18px}
.grid{display:grid; grid-template-columns: 1fr; gap:18px}
.card{padding:16px}
.chart-box{position:relative; height:360px; width:100%}
table{width:100%; border-collapse:collapse; font-size:14px;}
th,td{border:1px solid var(--line); padding:6px 8px; text-align:center;}
th{background:#1c2741;}
tbody tr:nth-child(even){background:#16203a;}
@media (max-width:1000px){.container{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="container">

<!-- 왼쪽 -->
<aside class="sidebar">
<h1>HAR 3모달 융합 결과</h1>
<?php if ($data): ?>
  <div style="margin:6px 0 12px 0">
    <span class="pill">총 샘플 <?=number_format(count($data))?></span>
    <span class="pill">라벨 <?=count($HAR_LABELS)?></span>
  </div>
<?php endif; ?>

<p class="muted">3가지 센서(IMU + Audio + Image)를 결합한 <br>Late Fusion 기반 HAR 결과입니다.</p>

<h2>모달 구성</h2>
<ul style="padding-left:18px">
  <li><b>IMU:</b> UCI HAR 기반 6클래스 확률</li>
  <li><b>Audio:</b> ESC-50 소리 → HAR6 변환(A2H)</li>
  <li><b>Image:</b> Stanford40 행동 인식 → HAR6 매핑</li>
  <li><b>Fusion:</b> 0.7·IMU + 0.1·Audio + 0.2·Image</li>
</ul>

<h2>출력 컬럼</h2>
<p><code>pred_fused</code> 최종 예측 라벨<br>
   <code>p_fused_*</code> 융합 확률 (LAYING~UPSTAIRS)<br>
   <code>pred_imu/audio/image</code> 개별 모달 예측</p>

<h2>확장 방향</h2>
<ul style="padding-left:18px">
  <li>가중치 자동 튜닝 (AutoML)</li>
  <li>Soft Voting vs Logit Fusion 비교</li>
  <li>모달별 신뢰도(Entropy) 기반 동적 융합</li>
</ul>

<br>
<a class="btn" href="train.php">IMU 학습 페이지</a>
</aside>

<!-- 오른쪽 -->
<main class="content">
<div class="grid">

  <!-- 그래프 1: Fused -->
  <div class="card">
    <h2>Fused 라벨 분포</h2>
    <div class="chart-box"><canvas id="fusedChart"></canvas></div>
  </div>

  <!-- 그래프 2: 모달별 비교 -->
  <div class="card">
    <h2>모달별 예측 라벨 분포</h2>
    <div class="chart-box"><canvas id="modalChart"></canvas></div>
  </div>

  <!-- 샘플 비교 -->
  <div class="card">
    <h2>샘플 비교 (상위 10개)</h2>
    <div style="overflow-x:auto;max-height:340px;">
    <table>
      <thead><tr><th>파일</th><th>Fused</th><th>IMU</th><th>Audio</th><th>Image</th></tr></thead>
      <tbody>
      <?php foreach (array_slice($data,0,10) as $r): ?>
        <tr>
          <td><?=htmlspecialchars($r['file'] ?? '')?></td>
          <td><?=htmlspecialchars($r['pred_fused'] ?? '')?></td>
          <td><?=htmlspecialchars($r['pred_imu'] ?? '')?></td>
          <td><?=htmlspecialchars($r['pred_audio_har'] ?? '')?></td>
          <td><?=htmlspecialchars($r['pred_image'] ?? '')?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </div>

</div>
</main>
</div>

<?php if ($data): ?>
<script>
const HAR_LABELS = <?=json_encode($HAR_LABELS)?>;
const countsMap = <?=json_encode($labelCounts)?>;
const fusedCounts = HAR_LABELS.map(l => countsMap[l] ?? 0);

// Chart ①: Fused 분포
new Chart(document.getElementById('fusedChart').getContext('2d'), {
  type:'bar',
  data:{
    labels:HAR_LABELS,
    datasets:[{
      label:'샘플 수',
      data:fusedCounts,
      backgroundColor:['#60a5fa','#fbbf24','#ef4444','#22c55e','#06b6d4','#8b5cf6']
    }]
  },
  options:{
    plugins:{legend:{display:false}},
    scales:{x:{ticks:{color:'#e9eef7'}},y:{beginAtZero:true,ticks:{color:'#e9eef7'}}}
  }
});

// Chart ②: 모달별 분포
const dataRows = <?=json_encode($data)?>;
const modalCounts = {imu:{}, audio:{}, image:{}};
dataRows.forEach(r=>{
  modalCounts.imu[r.pred_imu] = (modalCounts.imu[r.pred_imu]??0)+1;
  modalCounts.audio[r.pred_audio_har] = (modalCounts.audio[r.pred_audio_har]??0)+1;
  modalCounts.image[r.pred_image] = (modalCounts.image[r.pred_image]??0)+1;
});
const imuVals = HAR_LABELS.map(l => modalCounts.imu[l]??0);
const audioVals = HAR_LABELS.map(l => modalCounts.audio[l]??0);
const imageVals = HAR_LABELS.map(l => modalCounts.image[l]??0);

new Chart(document.getElementById('modalChart').getContext('2d'), {
  type:'bar',
  data:{
    labels:HAR_LABELS,
    datasets:[
      {label:'IMU',data:imuVals,backgroundColor:'rgba(99,102,241,0.8)'},
      {label:'Audio',data:audioVals,backgroundColor:'rgba(16,185,129,0.8)'},
      {label:'Image',data:imageVals,backgroundColor:'rgba(234,179,8,0.8)'}
    ]
  },
  options:{
    plugins:{legend:{labels:{color:'#dbe7ff'}}},
    scales:{x:{ticks:{color:'#e9eef7'}},y:{beginAtZero:true,ticks:{color:'#e9eef7'}}}
  }
});
</script>
<?php endif; ?>
</body>
</html>
