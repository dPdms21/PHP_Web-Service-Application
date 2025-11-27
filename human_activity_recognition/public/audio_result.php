<?php
// ========================================
// audio_result.php
// ========================================

// 업로드 디렉토리 설정
$root = realpath(__DIR__ . '/..');
$uploadDir = $root . "/uploads/audio/";

if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);

// 업로드 확인
if (!isset($_FILES['audio'])) {
    die("오디오 파일이 업로드되지 않았습니다.");
}

// 파일 저장
$filename = time() . "_" . basename($_FILES['audio']['name']);
$savePath = $uploadDir . $filename;

move_uploaded_file($_FILES['audio']['tmp_name'], $savePath);

// -----------------------------------------
// 🔹 1) Python 실행
// -----------------------------------------
$fixedPath = str_replace('\\', '/', $savePath);

// python.exe 경로
$python = "C:/xampp/htdocs/webS/human_activity_recognition/.venv311/Scripts/python.exe";

// audio_infer.py 경로 (❗ PHP → PY 로 확장자 수정)
$pyScript = "C:/xampp/htdocs/webS/human_activity_recognition/py/infer/audio_infer.py";

$cmd = "\"$python\" \"$pyScript\" " . escapeshellarg($fixedPath) . " 2>&1";
$output = shell_exec($cmd);

$data = json_decode($output, true);

if (!$data || isset($data["error"])) {
    die("<b>❌ Python 모델 실행 실패 또는 JSON 파싱 실패</b><br>
         <pre>출력 내용:\n$output</pre>");
}

$pred = $data['predicted_action'];
$confRaw = $data['confidence'];
$conf = round($confRaw * 100, 2);

// -----------------------------------------
// 🔹 2) DB 저장 (group 삭제됨)
// -----------------------------------------
require_once __DIR__ . '/../config/db.php';
$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

$stmt = $mysqli->prepare("
    INSERT INTO har_fusion_results (video_name, predicted_label, confidence)
    VALUES (?, ?, ?)
");
$stmt->bind_param("ssd", $filename, $pred, $confRaw);
$stmt->execute();
$stmt->close();

$mysqli->close();
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="utf-8">
<title>Audio HAR 결과</title>
<style>
body { background:#0b0f19; color:white; padding:40px; font-family:system-ui; }
.card {
  max-width:700px; margin:auto; background:#121a2a; padding:32px;
  border-radius:16px; box-shadow:0 8px 24px rgba(0,0,0,.4);
}
a { color:#3b82f6; }
</style>
</head>

<body>
<div class="card">
  <h2>🎧 오디오 기반 HAR 결과</h2>

  <p><b>예측 결과:</b> <?= $pred ?></p>
  <p><b>신뢰도:</b> <?= $conf ?>%</p>

  <a href="main.php">← 메인으로 돌아가기</a>
</div>
</body>
</html>
