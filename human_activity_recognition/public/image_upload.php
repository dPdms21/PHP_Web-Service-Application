<?php
// image_upload.php
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="utf-8">
<title>이미지 업로드 (Image HAR)</title>
<style>
body {
  background:#0b0f19; color:white; padding:60px;
  font-family:ui-sans-serif,system-ui;
}
.box {
  max-width:500px; margin:auto; background:#121a2a;
  padding:30px; border-radius:14px; text-align:center;
  box-shadow:0 8px 24px rgba(0,0,0,0.4);
}
input[type=file] {
  margin:20px 0; background:#1e293b; color:white; padding:12px;
  border-radius:8px; width:100%;
}
button {
  padding:12px 24px; background:#3b82f6; border-radius:10px;
  border:none; color:white; font-size:1rem; cursor:pointer;
}
a { color:#3b82f6; }
</style>
</head>
<body>

<div class="box">
  <h2>🖼 이미지 업로드 (Image HAR)</h2>
  <form action="image_result.php" method="post" enctype="multipart/form-data">
      <input type="file" name="image" required>
      <br><br>
      <button type="submit">예측 실행</button>
  </form>

  <p><a href="main.php">← 메인으로 돌아가기</a></p>
</div>

</body>
</html>
