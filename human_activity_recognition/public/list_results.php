<?php
require_once __DIR__ . '/../config/db.php';
$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

$result = $mysqli->query("SELECT * FROM har_fusion_results ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="utf-8">
<title>HAR 결과 목록</title>
<style>
body { background:#0b0f19; color:white; font-family:sans-serif; padding:40px; }
table { border-collapse:collapse; width:100%; margin-top:20px; }
th, td { border:1px solid #334155; padding:8px 10px; text-align:center; }
th { background:#1e293b; }
tr:nth-child(even) { background:#0f172a; }
</style>
</head>

<body>
<h1>📄 저장된 HAR Fusion 결과 목록</h1>

<table>
<tr>
  <th>ID</th>
  <th>영상 이름</th>
  <th>예측</th>
  <th>신뢰도</th>
  <th>Top2</th>
  <th>Top3</th>
  <th>시간</th>
</tr>

<?php while ($row = $result->fetch_assoc()): ?>
<tr>
  <td><?= $row['id'] ?></td>
  <td><?= $row['video_name'] ?></td>
  <td><?= $row['predicted_label'] ?></td>
  <td><?= round($row['confidence']*100,2) ?>%</td>
  <td><?= $row['top2_label'] ?> (<?= round($row['top2_prob']*100,2) ?>%)</td>
  <td><?= $row['top3_label'] ?> (<?= round($row['top3_prob']*100,2) ?>%)</td>
  <td><?= $row['created_at'] ?></td>
</tr>
<?php endwhile; ?>

</table>
</body>
</html>
