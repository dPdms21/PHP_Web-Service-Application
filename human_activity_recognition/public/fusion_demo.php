<?php
// ---- 환경 설정 ----
$PROJECT_ROOT = 'C:\\xampp\\htdocs\\webS\\human_activity_recognition';
$PYTHON       = $PROJECT_ROOT . '\\.venv311\\Scripts\\python.exe';   // venv의 python
$FUSION_PY    = $PROJECT_ROOT . '\\py\\fusion\\01_late_fusion.py';

// 업로드 경로
$UPLOAD_DIR   = $PROJECT_ROOT . '\\outputs\\web_uploads';
if (!is_dir($UPLOAD_DIR)) { @mkdir($UPLOAD_DIR, 0777, true); }

// 기본값
$default_imu = "0.05,0.10,0.15,0.60,0.05,0.05";
$default_alpha = "0.8";
$sample_wav = $PROJECT_ROOT . '\\data\\ESC-50\\audio\\1-100032-A-0.wav';

$result = null;
$error  = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $imu_probs = trim($_POST['imu_probs'] ?? $default_imu);
    $alpha     = trim($_POST['alpha'] ?? $default_alpha);
    $use_sample= isset($_POST['use_sample']);
    $wav_path  = '';

    // 파일 업로드 or 샘플 사용
    if ($use_sample) {
        $wav_path = $sample_wav;
    } else if (!empty($_FILES['audio_wav']['name'])) {
        $fname = basename($_FILES['audio_wav']['name']);
        $dest = $UPLOAD_DIR . '\\' . $fname;
        if (move_uploaded_file($_FILES['audio_wav']['tmp_name'], $dest)) {
            $wav_path = $dest;
        } else {
            $error = "WAV 파일 업로드 실패";
        }
    } else if (!empty($_POST['audio_path_text'])) {
        // 경로 직접 입력 지원(선택)
        $wav_path = $_POST['audio_path_text'];
    }

    if (!$error) {
        // PowerShell/Windows 호환을 위해 인자에 큰따옴표 사용
        $cmd = sprintf('"%s" "%s" --imu_probs "%s" --alpha %s',
            $PYTHON,
            $FUSION_PY,
            $imu_probs,
            $alpha
        );
        if ($wav_path) {
            $cmd .= ' --audio_wav ' . escapeshellarg($wav_path);
        }

        // 실행 및 출력 캡쳐
        $output = shell_exec($cmd . ' 2>&1');

        // 01_late_fusion.py 는 dict를 print -> JSON이 아닐 수도 있으니 유연 파싱
        $json = null;
        // dict 형태를 JSON처럼 바꿔보기 시도
        $maybe = trim($output);
        $maybe = preg_replace("/'/", '"', $maybe); // 작은따옴표 -> 큰따옴표
        $maybe = preg_replace('/([A-Za-z_]+):/', '"$1":', $maybe); // key: -> "key":
        $json = json_decode($maybe, true);
        if (!$json) {
            // 실패 시 그대로 보여주자
            $error = "Python 출력 파싱 실패\nCMD:\n$cmd\n\nOUTPUT:\n$output";
        } else {
            $result = $json;
        }
    }
}
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>HAR Late Fusion Demo (IMU + Audio)</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1"></script>
<style>
.container { max-width: 980px; margin: 30px auto; }
code { white-space: pre-wrap; }
.grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
label small { color:#777; }
</style>
</head>
<body>
<main class="container">
  <h2>HAR Late Fusion Demo</h2>
  <p>IMU 확률(6라벨) + 오디오 WAV를 결합해 최종 활동을 추론합니다.</p>

  <form method="post" enctype="multipart/form-data">
    <article>
      <header><strong>입력</strong></header>
      <div class="grid">
        <div>
          <label>IMU 확률 (LAYING,SITTING,STANDING,WALKING,DOWN,UP)
            <input type="text" name="imu_probs" value="<?=htmlspecialchars($_POST['imu_probs'] ?? $default_imu)?>" required>
            <small>쉼표로 6개. 합은 자동 정규화.</small>
          </label>
        </div>
        <div>
          <label>α (IMU 가중치, 0~1)
            <input type="number" name="alpha" step="0.05" min="0" max="1" value="<?=htmlspecialchars($_POST['alpha'] ?? $default_alpha)?>">
          </label>
        </div>
      </div>

      <details>
        <summary>오디오 입력</summary>
        <label><input type="checkbox" name="use_sample" <?= isset($_POST['use_sample']) ? 'checked' : '' ?>> 샘플 WAV 사용 (ESC-50 footsteps)</label>
        <label>WAV 업로드<input type="file" name="audio_wav" accept=".wav"></label>
        <label>또는 WAV 경로 직접 입력<input type="text" name="audio_path_text" placeholder="C:\path\to\file.wav"></label>
      </details>

      <footer>
        <button type="submit">Fusion 실행</button>
      </footer>
    </article>
  </form>

  <?php if ($error): ?>
    <article>
      <header><strong style="color:#c00">에러</strong></header>
      <code><?=htmlspecialchars($error)?></code>
    </article>
  <?php endif; ?>

  <?php if ($result): 
    $labels = $result['HAR_LABELS'] ?? ["LAYING","SITTING","STANDING","WALKING","WALKING_DOWNSTAIRS","WALKING_UPSTAIRS"];
    $fused  = $result['fused_probs'] ?? [];
    ?>
    <article>
      <header><strong>결과</strong></header>
      <p><b>Final:</b> <?=htmlspecialchars($result['final_pred'] ?? 'N/A')?></p>
      <div style="max-width:720px;">
        <canvas id="bar"></canvas>
      </div>
      <script>
        const fused = <?= json_encode(array_values($fused), JSON_UNESCAPED_UNICODE) ?>;
        const labels = <?= json_encode(array_values($labels), JSON_UNESCAPED_UNICODE) ?>;
        const ctx = document.getElementById('bar').getContext('2d');
        new Chart(ctx, {
          type:'bar',
          data:{ labels: labels, datasets:[{ label:'Fused Prob.', data:fused }]},
          options:{ responsive:true, scales:{ y:{ beginAtZero:true, max:1 } } }
        });
      </script>

      <details>
        <summary>원시 JSON 보기</summary>
        <code><?=htmlspecialchars(json_encode($result, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE))?></code>
      </details>
    </article>
  <?php endif; ?>

  <hr>
  <p><small>Python: <?=$PYTHON?><br>Fusion Script: <?=$FUSION_PY?></small></p>
</main>
</body>
</html>
