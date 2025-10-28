<?php
// ====== 경로 설정 ======
$PROJECT_ROOT = 'C:\\xampp\\htdocs\\webS\\human_activity_recognition';
$CSV_PATH     = $PROJECT_ROOT . '\\outputs\\fusion\\fused_results.csv';

// (선택) CSV 업로드로 교체 가능
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file']) && $_FILES['csv_file']['tmp_name']) {
  $tmp = $_FILES['csv_file']['tmp_name'];
  $dst = $PROJECT_ROOT . '\\outputs\\fusion\\fused_results_uploaded.csv';
  if (move_uploaded_file($tmp, $dst)) {
    $CSV_PATH = $dst;
  } else {
    $upload_err = 'CSV 업로드 실패';
  }
}

// ====== CSV 로드 ======
$rows = [];
$headers = [];
if (!file_exists($CSV_PATH)) {
  $err = "CSV 파일이 없습니다: $CSV_PATH";
} else {
  if (($fp = fopen($CSV_PATH, 'r')) !== false) {
    $headers = fgetcsv($fp);
    while (($r = fgetcsv($fp)) !== false) {
      $rows[] = array_combine($headers, $r);
    }
    fclose($fp);
  } else {
    $err = "CSV를 열 수 없습니다.";
  }
}

// 라벨 목록 고정(순서 유지)
$HAR = ["LAYING","SITTING","STANDING","WALKING","WALKING_DOWNSTAIRS","WALKING_UPSTAIRS"];

// ====== 요약 통계 ======
$cnt_fused = array_fill_keys($HAR, 0);
$cnt_imu   = array_fill_keys($HAR, 0);
foreach ($rows as $r) {
  $pf = $r['pred_fused'] ?? null;
  $pi = $r['pred_imu'] ?? null;
  if (isset($cnt_fused[$pf])) $cnt_fused[$pf]++;
  if (isset($cnt_imu[$pi]))   $cnt_imu[$pi]++;
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>HAR Batch Fusion Results</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<style>
.container{max-width:1200px;margin:24px auto}
.table-wrap{overflow:auto;max-height:520px;border:1px solid #eee}
table thead th{position:sticky;top:0;background:#fff}
.kv{display:grid;grid-template-columns:140px 1fr;gap:8px;align-items:center}
.badge{padding:.2rem .5rem;border-radius:999px;background:#eef}
</style>
</head>
<body>
<main class="container">
  <h2>HAR Batch Fusion Results</h2>
  <p class="kv"><b>CSV 경로</b><span><?=h($CSV_PATH)?></span></p>

  <form method="post" enctype="multipart/form-data">
    <label>CSV 업로드로 교체
      <input type="file" name="csv_file" accept=".csv">
    </label>
    <button type="submit">적용</button>
    <?php if(!empty($upload_err)): ?><small style="color:#c00"><?=h($upload_err)?></small><?php endif; ?>
  </form>

  <?php if (!empty($err)): ?>
    <article><strong style="color:#c00">에러:</strong> <?=h($err)?></article>
  <?php else: ?>
    <article>
      <header><strong>요약</strong></header>
      <div class="grid">
        <div>
          <p><b>샘플 수:</b> <span class="badge"><?=count($rows)?></span></p>
          <p><b>라벨별 개수 (Fused)</b></p>
          <ul>
            <?php foreach($HAR as $k): ?>
              <li><?=h($k)?>: <b><?=h($cnt_fused[$k])?></b></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <div>
          <canvas id="chartCounts"></canvas>
        </div>
      </div>
    </article>

    <article>
      <header class="grid">
        <strong>상세 테이블</strong>
        <div>
          <label>라벨 필터(최종)
            <select id="filterLabel">
              <option value="">전체</option>
              <?php foreach($HAR as $k): ?>
                <option value="<?=h($k)?>"><?=h($k)?></option>
              <?php endforeach; ?>
            </select>
          </label>
        </div>
      </header>
      <div class="table-wrap">
        <table id="tbl">
          <thead>
            <tr>
              <th>#</th>
              <th>pred_fused</th>
              <th>pred_imu</th>
              <th>pred_audio_har</th>
              <?php foreach($HAR as $k): ?>
                <th><?=h("p_fused_$k")?></th>
              <?php endforeach; ?>
              <?php foreach($HAR as $k): ?>
                <th><?=h("p_imu_$k")?></th>
              <?php endforeach; ?>
              <?php foreach($HAR as $k): ?>
                <th><?=h("p_audio_as_$k")?></th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach($rows as $i=>$r): ?>
            <tr>
              <td><?=($i+1)?></td>
              <td><?=h($r['pred_fused'])?></td>
              <td><?=h($r['pred_imu'])?></td>
              <td><?=h($r['pred_audio_har'])?></td>
              <?php foreach($HAR as $k): ?><td><?=h(number_format((float)$r["p_fused_$k"], 4))?></td><?php endforeach; ?>
              <?php foreach($HAR as $k): ?><td><?=h(number_format((float)$r["p_imu_$k"],   4))?></td><?php endforeach; ?>
              <?php foreach($HAR as $k): ?><td><?=h(number_format((float)$r["p_audio_as_$k"],4))?></td><?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <footer>
        <a class="secondary" href="<?=h(str_replace($PROJECT_ROOT, '', $CSV_PATH))?>" download>CSV 다운로드</a>
      </footer>
    </article>

    <script>
      const labels = <?=json_encode($HAR, JSON_UNESCAPED_UNICODE)?>;
      const fusedCounts = <?=json_encode(array_values($cnt_fused))?>;
      const imuCounts   = <?=json_encode(array_values($cnt_imu))?>;

      new Chart(document.getElementById('chartCounts'), {
        type:'bar',
        data:{ labels,
          datasets:[
            {label:'Fused', data:fusedCounts},
            {label:'IMU',   data:imuCounts}
          ]
        },
        options:{responsive:true, scales:{y:{beginAtZero:true}}}
      });

      // 간단 필터(최종 라벨)
      const sel = document.getElementById('filterLabel');
      const tbody = document.querySelector('#tbl tbody');
      sel.addEventListener('change', () => {
        const v = sel.value;
        for (const tr of tbody.querySelectorAll('tr')) {
          const pf = tr.children[1].textContent.trim();
          tr.style.display = (!v || v===pf) ? '' : 'none';
        }
      });
    </script>
  <?php endif; ?>
  <hr>
  <p><small>파일: <?=$CSV_PATH?></small></p>
</main>
</body>
</html>
