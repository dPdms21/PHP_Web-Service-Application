<?php
require_once __DIR__ . '/../config/db.php';
$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

$result = $mysqli->query("
    SELECT id, video_name, predicted_label, confidence, created_at
    FROM har_fusion_results
    ORDER BY id DESC
");
?>

<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="utf-8">
<title>HAR 결과 목록</title>
<style>
:root {
    --bg:#0b0f19; --card:#121a2a; --line:#22314f;
    --text:#e9eef7; --accent:#3b82f6; --muted:#94a3b8;
}

body {
    background:var(--bg);
    color:var(--text);
    font-family:ui-sans-serif,system-ui,-apple-system,Roboto;
    padding:40px;
}

.container {
    max-width:1200px;
    margin:auto;
}

h1 {
    font-size:2rem;
    margin-bottom:20px;
}

table {
    width:100%;
    border-collapse:collapse;
    margin-top:20px;
    font-size:0.95rem;
}

th {
    background:#1e293b;
    padding:12px;
    border-bottom:1px solid var(--line);
    color:#60a5fa;
}

td {
    padding:10px 12px;
    border-bottom:1px solid #1e293b;
    text-align:center;
    max-width:180px;
    overflow:hidden;
    white-space:nowrap;
    text-overflow:ellipsis;
}

tr:nth-child(even) {
    background:#0f1625;
}

tr:hover {
    background:#22314f;
    transition:0.15s;
}

a.btn {
    background:#3b82f6;
    padding:10px 18px;
    border-radius:8px;
    text-decoration:none;
    color:white;
    font-weight:600;
}
</style>
</head>

<body>
<div class="container">

<h1>📄 저장된 HAR 결과 목록</h1>
<a class="btn" href="main.php">🏠 메인으로</a>

<table>
<tr>
  <th>ID</th>
  <th>파일 이름</th>
  <th>예측 라벨</th>
  <th>신뢰도</th>
  <th>시간</th>
  <th>삭제</th>
</tr>

<?php while ($row = $result->fetch_assoc()): ?>
<tr>
  <td><?= htmlspecialchars($row['id']) ?></td>

  <td title="<?= htmlspecialchars($row['video_name']) ?>">
      <?= htmlspecialchars($row['video_name']) ?>
  </td>

  <td><?= htmlspecialchars($row['predicted_label']) ?></td>

  <td><?= round($row['confidence'] * 100, 2) ?>%</td>

  <td><?= htmlspecialchars($row['created_at']) ?></td>

  <td>
      <a href="delete.php?id=<?= $row['id'] ?>"
         onclick="return confirm('정말 삭제하시겠습니까?');"
         style="color:#ef4444; font-weight:600;">
         삭제
      </a>
  </td>
</tr>
<?php endwhile; ?>

</table>

</div>
</body>
</html>
