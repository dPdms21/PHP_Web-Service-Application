<?php
require_once __DIR__ . '/../config/db.php';

if (!isset($_GET['id'])) {
    die("잘못된 접근입니다.");
}

$id = intval($_GET['id']);

$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

if ($mysqli->connect_errno) {
    die("DB 연결 오류: " . $mysqli->connect_error);
}

$stmt = $mysqli->prepare("DELETE FROM har_fusion_results WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->close();
$mysqli->close();

// 삭제 후 목록으로 이동
header("Location: list_results.php");
exit;
