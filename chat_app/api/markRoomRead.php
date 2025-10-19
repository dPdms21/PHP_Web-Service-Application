<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once '../db.php';
header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
  http_response_code(401);
  echo json_encode(['success' => false, 'error' => '로그인이 필요합니다.']);
  exit();
}

$room_id = intval($_POST['room_id'] ?? 0);
if ($room_id <= 0) {
  http_response_code(400);
  echo json_encode(['success' => false, 'error' => 'room_id 필요']);
  exit();
}

try {
  $db = getDB();
  $user_id = getCurrentUserId();

  // message_reads 테이블이 message_id, user_id 컬럼만 있는 경우
    $sql = "
    INSERT INTO message_reads (message_id, user_id)
    SELECT m.id, ?
    FROM messages m
    LEFT JOIN message_reads mr
      ON mr.message_id = m.id AND mr.user_id = ?
    WHERE m.room_id = ?
      AND m.sender_id <> ?
      AND mr.message_id IS NULL
  ";
  $stmt = $db->prepare($sql);
  $stmt->execute([$user_id, $user_id, $room_id, $user_id]);

  echo json_encode(['success' => true]);
 } catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
      'success' => false,
      'error' => $e->getMessage()
    ]);
  }
  
