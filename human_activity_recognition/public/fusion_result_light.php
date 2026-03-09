<?php
// ===============================================
// 🎯 Fusion HAR (Raspberry Pi 경량화 버전) 결과 페이지
// ===============================================

$result_path = __DIR__ . '/../outputs_light/fusion_raspi/infer_result.json';

// JSON 읽기
$data = null;
if (file_exists($result_path)) {
    $json = file_get_contents($result_path);
    $data = json_decode($json, true);
}

if (!$data) {
    echo "<!DOCTYPE html><html lang='ko'><meta charset='utf-8'>
    <body style='background:#0b0f19;color:#f87171;font-family:system-ui;padding:60px;'>
      <h2>❌ 분석 결과 파일을 찾을 수 없습니다.</h2>
      <p><code>$result_path</code> 파일이 존재하는지 확인하세요.</p>
    </body></html>";
    exit;
}

// JSON 데이터 파싱
$video = $data["video_name"] ?? "unknown";
$pred  = $data["predicted_action"] ?? "N/A";
$conf  = isset($data["confidence"]) ? round($data["confidence"] * 100, 2) : 0;
$top3  = $data["top3"] ?? [];

?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="utf-8">
<title>Fusion HAR — Lightweight</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
:root {
  --bg:#0b0f19; --card:#111827; --text:#e5e7eb;
  --accent:#3b82f6; --muted:#9ca3af; --border:#1f2937;
}
body {
  margin:0; padding:40px;
  background:var(--bg); color:var(--text);
  font-family:-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto;
}
.card {
  background:var(--card); border-radius:18px;
  padding:32px; max-width:780px; margin:auto;
  box-shadow:0 8px 20px rgba(0,0,0,.35);
}
h1 {margin-top:0; font-size:1.8rem;}
h2 {color:var(--accent); margin-top:2rem; font-size:1.3rem;}
p.muted {color:var(--muted);}
.conf {
  font-size:2.4rem; font-weight:700;
  color:#38bdf8; margin:12px 0 24px;
}
.top3 {
  background:#0f172a; padding:20px; border-radius:12px;
}
canvas {
  background:#0f172a; padding:10px; border-radius:12px;
}
</style>
</head>

<body>
<div class="card">

  <h1>🎥 Fusion HAR (Lightweight / Raspberry Pi)</h1>
  <p class="muted">영상 파일: <b><?=htmlspecialchars($video)?></b></p>
  <p class="muted">라즈베리파이 최적화 모델로 수행된 결과</p>

  <hr style="border:1px solid var(--border); margin:24px 0;">

  <h2>예측된 행동</h2>
  <p style="font-size:1.6rem; font-weight:600;"><?=htmlspecialchars($pred)?></p>

  <h2>신뢰도</h2>
  <div class="conf"><?=$conf?>%</div>

  <?php if (!empty($top3)): ?>
  <div class="top3">
    <h2>Top-3 예측 결과</h2>
    <canvas id="chartTop3" height="180"></canvas>
  </div>
  <?php endif; ?>

  <p class="muted" style="margin-top:28px;">
    📄 JSON 결과: <code><?=$result_path?></code><br>
    ⚙️ 모델: MobileNetV3 (INT8, TorchScript)<br>
    🍓 실행환경: Raspberry Pi 4 (ARM CPU)
  </p>

</div>

<?php if (!empty($top3)): ?>
<script>
const labels = <?=json_encode(array_column($top3, 'label'))?>;
const probs  = <?=json_encode(array_map(fn($x)=>round($x['prob']*100,2), $top3))?>;

new Chart(document.getElementById("chartTop3"), {
  type: 'bar',
  data: {
    labels: labels,
    datasets: [{
      label: "Confidence (%)",
      data: probs,
      borderRadius: 8,
      backgroundColor: ["#3b82f6", "#10b981", "#a855f7"]
    }]
  },
  options: {
    indexAxis: 'y',
    scales: {
      x: { beginAtZero:true, max:100, ticks:{color:"#d1d5db"}, grid:{color:"#1f2937"} },
      y: { ticks:{color:"#d1d5db"}, grid:{display:false} }
    },
    plugins:{ legend:{ display:false } }
  }
});
</script>
<?php endif; ?>

</body>
</html>
