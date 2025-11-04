<?php
require_once 'db.php';

header('Content-Type: text/plain; charset=utf-8');
header('Content-Disposition: attachment; filename="chat_log.txt"');

if (!isLoggedIn()) {
    echo "로그인이 필요합니다.\n";
    exit();
}

$room_id = intval($_GET['room_id'] ?? 0);
if ($room_id <= 0) {
    echo "유효하지 않은 채팅방입니다.\n";
    exit();
}

try {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT 
            m.id,
            u.nickname AS sender,
            m.content,
            DATE_FORMAT(m.created_at, '%Y-%m-%d %H:%i:%s') AS time
        FROM messages m
        JOIN users u ON u.id = m.sender_id
        WHERE m.room_id = ?
        ORDER BY m.id ASC
    ");
    $stmt->execute([$room_id]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$messages) {
        echo "채팅 기록이 없습니다.\n";
        exit();
    }

    echo "=== 채팅 로그 (Room #{$room_id}) ===\n\n";
    foreach ($messages as $msg) {
        echo "[{$msg['time']}] {$msg['sender']}: {$msg['content']}\n";
    }

} catch (Exception $e) {
    echo "오류: " . $e->getMessage();
}
