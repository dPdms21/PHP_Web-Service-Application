<?php
// ===============================================
// 🎯 Fusion-based HAR 결과 리포트 페이지 + MySQL 저장
// ===============================================
$result_path = __DIR__ . '/../outputs/fusion_fast/infer_result.json';
$thumb_path  = __DIR__ . '/../temp_infer/frame_000.jpg';

// 결과 JSON 로드
$data = null;
if (file_exists($result_path)) {
    $json = file_get_contents($result_path);
    $data = json_decode($json, true);
}

if (!$data) {
    echo "<!DOCTYPE html><html lang='ko'><meta charset='utf-8'>
    <body style='background:#0b0f19;color:#fca5a5;font-family:system-ui;padding:60px;'>
    <h2>❌ 분석 결과를 찾을 수 없습니다.</h2>
    <p>먼저 Python 스크립트를 실행해 <code>outputs/fusion_fast/infer_result.json</code> 파일을 생성하세요.</p>
    </body></html>";
    exit;
}

// JSON에서 값 추출
$videoName = $data['video_name']        ?? 'unknown';
$pred      = $data['predicted_action']  ?? 'N/A';
$confRaw   = $data['confidence']        ?? 0;
$conf      = round($confRaw * 100, 2);
$top3      = $data['top3']              ?? [];

// ===============================================
// 🗄️ MySQL 연결 & INSERT
// ===============================================
require_once __DIR__ . '/../config/db.php';
$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

if ($mysqli->connect_errno) {
    // 연결 실패해도 페이지는 계속 보여주고, 경고만 출력
    error_log('MySQL 연결 실패: ' . $mysqli->connect_error);
} else {
    // top3에서 라벨/확률 추출 (없으면 NULL)
    $top1_label = $pred;
    $top1_prob  = $confRaw;

    $top2_label = null;
    $top2_prob  = null;
    $top3_label = null;
    $top3_prob  = null;

    if (count($top3) > 1) {
        $top2_label = $top3[1]['label'] ?? null;
        $top2_prob  = $top3[1]['prob']  ?? null;
    }
    if (count($top3) > 2) {
        $top3_label = $top3[2]['label'] ?? null;
        $top3_prob  = $top3[2]['prob']  ?? null;
    }

    $stmt = $mysqli->prepare("
        INSERT INTO har_fusion_results
        (video_name, predicted_label, confidence,
         top1_label, top1_prob,
         top2_label, top2_prob,
         top3_label, top3_prob)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    if ($stmt) {
        $stmt->bind_param(
            'ssdsdsdsd',
            $videoName,
            $pred,
            $confRaw,
            $top1_label, $top1_prob,
            $top2_label, $top2_prob,
            $top3_label, $top3_prob
        );
        $stmt->execute();
        $stmt->close();
    } else {
        error_log('har_fusion_results INSERT prepare 실패: ' . $mysqli->error);
    }

    $mysqli->close();
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Fusion-based HAR 결과</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
:root {
  --bg:#0b0f19; --card:#121a2a; --line:#22314f;
  --text:#e9eef7; --accent:#3b82f6; --muted:#94a3b8;
}
body {
  margin:0; padding:40px; background:var(--bg); color:var(--text);
  font-family:ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto;
}
.card {
  background:var(--card);
  border-radius:16px;
  padding:32px 40px;
  max-width:820px;
  margin:auto;
  box-shadow:0 8px 24px rgba(0,0,0,0.4);
}
h1 {font-size:1.8rem; margin-bottom:0.4em;}
h2 {font-size:1.2rem; color:var(--accent); margin-top:1.8em;}
p.muted {color:var(--muted); font-size:0.9rem;}
img.thumb {
  width:100%; border-radius:12px; margin:20px 0;
  border:1px solid var(--line); object-fit:cover;
}
.conf {
  font-size:2rem; font-weight:700; text-align:left;
  color:#38bdf8; margin:10px 0 20px;
}
.top3 {background:#0f1625; border-radius:12px; padding:18px; margin-top:24px;}
canvas {margin-top:12px; background:#0f1625; border-radius:12px; padding:8px;}
</style>
</head>

<body>
  <div class="card">
    <h1>🎥 Fusion-based HAR 결과 리포트</h1>
    <p class="muted">영상: <b><?=htmlspecialchars($videoName)?></b></p>
    <p class="muted">이미지 + 오디오 융합 기반 행동 인식 결과</p>
    <hr style="border:1px solid var(--line); margin:20px 0;">

    <!-- 썸네일 (필요하면 주석 해제) -->
    <?php if (file_exists($thumb_path)): ?>
      <!-- <img src="../temp_infer/frame_000.jpg" alt="썸네일" class="thumb"> -->
    <?php else: ?>
      <p class="muted">(썸네일 없음)</p>
    <?php endif; ?>

    <h2>예측된 행동</h2>
    <p style="font-size:1.5rem; font-weight:600;"><?=$pred?></p>

    <h2>신뢰도 (Confidence)</h2>
    <div class="conf"><?=$conf?>%</div>

    <?php if (!empty($top3)): ?>
      <div class="top3">
        <h2>Top-3 예측 결과</h2>
        <canvas id="chartTop3" height="180"></canvas>
      </div>
    <?php endif; ?>

    <p class="muted" style="margin-top:28px;">
      💾 결과 파일: <code><?=$result_path?></code><br>
      📸 썸네일: <code><?=$thumb_path?></code><br>
      🗄 DB 테이블: <code>har_fusion_results</code> (최근 결과 1건이 저장되었습니다)
    </p>
  </div>

<?php if (!empty($top3)): ?>
<script>
const ctx = document.getElementById('chartTop3');
const labels = <?=json_encode(array_column($top3, 'label'))?>;
const probs = <?=json_encode(array_map(fn($x)=>round($x['prob']*100,2), $top3))?>;

new Chart(ctx, {
  type: 'bar',
  data: {
    labels: labels,
    datasets: [{
      label: 'Confidence (%)',
      data: probs,
      borderRadius: 8,
      backgroundColor: ['#3b82f6','#10b981','#8b5cf6']
    }]
  },
  options: {
    indexAxis: 'y',
    scales: {
      x: {
        beginAtZero: true,
        max: 100,
        grid: {color:'#22314f'},
        ticks:{color:'#cbd5e1', font:{size:12}}
      },
      y: {
        grid:{display:false},
        ticks:{color:'#cbd5e1', font:{size:13}}
      }
    },
    plugins: { legend: { display:false } }
  }
});
</script>
<?php endif; ?>
</body>
</html>
