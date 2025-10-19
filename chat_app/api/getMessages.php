<?php
require_once '../db.php';
header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => '로그인이 필요합니다.']);
    exit();
}

$room_id = $_GET['room_id'] ?? null;
$last_message_id = $_GET['last_message_id'] ?? 0;
if (!$room_id) {
    http_response_code(400);
    echo json_encode(['error' => '채팅방 ID가 필요합니다.']);
    exit();
}

try {
    $db = getDB();
    $current_user_id = getCurrentUserId();

    // 상대가 챗봇인지 확인
    $chatbotCheck = $db->prepare("
        SELECT u.username
        FROM chat_room_participants p
        JOIN users u ON u.id = p.user_id
        WHERE p.room_id = ?
        AND u.id <> ?
        LIMIT 1
    ");
    $chatbotCheck->execute([$room_id, $current_user_id]);
    $otherUser = $chatbotCheck->fetch(PDO::FETCH_ASSOC);
    $isChatbotRoom = ($otherUser && $otherUser['username'] === 'chatbot');

    // 채팅방 참여자 확인
    $checkStmt = $db->prepare("SELECT id FROM chat_room_participants WHERE room_id = ? AND user_id = ?");
    $checkStmt->execute([$room_id, $current_user_id]);
    if (!$checkStmt->fetch()) {
        http_response_code(403);
        echo json_encode(['error' => '이 채팅방에 접근할 권한이 없습니다.']);
        exit();
    }

    // 새 메시지 조회
    $stmt = $db->prepare("
        SELECT m.*, u.nickname, u.username
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE m.room_id = ? AND m.id > ?
        ORDER BY m.created_at ASC
    ");
    $stmt->execute([$room_id, $last_message_id]);
    $messages = $stmt->fetchAll();

    // (생략) 메시지 읽음 처리 로직 그대로 유지

    $formatted_messages = [];
    foreach ($messages as $message) {
        $formatted_messages[] = [
            'id' => $message['id'],
            'content' => $message['content'],
            'sender_id' => $message['sender_id'],
            'sender_name' => $message['nickname'],
            'sender_username' => $message['username'],
            'message_type' => $message['message_type'],
            'read_count' => $message['read_count'],
            'created_at' => $message['created_at'],
            'formatted_time' => formatMessageTime($message['created_at']),
            'is_sent' => $message['sender_id'] == $current_user_id
        ];
    }

    echo json_encode([
        'success' => true,
        'messages' => $formatted_messages,
        'is_chatbot_room' => $isChatbotRoom  // 챗봇 여부를 직접 반환
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
