<?php
require_once '../db.php';

header('Content-Type: application/json; charset=utf-8');

// 로그인 확인
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => '로그인이 필요합니다.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST 메서드만 허용됩니다.']);
    exit();
}

$room_id = $_POST['room_id'] ?? null;
$content = trim($_POST['content'] ?? '');
$message_type = $_POST['message_type'] ?? 'text';

if (!$room_id || empty($content)) {
    http_response_code(400);
    echo json_encode(['error' => '채팅방 ID와 메시지 내용이 필요합니다.']);
    exit();
}

try {
    $db = getDB();
    $current_user_id = getCurrentUserId();
    
    // 채팅방 참여자 확인
    $checkStmt = $db->prepare("SELECT id FROM chat_room_participants WHERE room_id = ? AND user_id = ?");
    $checkStmt->execute([$room_id, $current_user_id]);
    
    if (!$checkStmt->fetch()) {
        http_response_code(403);
        echo json_encode(['error' => '이 채팅방에 접근할 권한이 없습니다.']);
        exit();
    }
    
    // 채팅방 참여자 수 조회
    $participantStmt = $db->prepare("SELECT COUNT(*) as count FROM chat_room_participants WHERE room_id = ?");
    $participantStmt->execute([$room_id]);
    $participant_count = $participantStmt->fetch()['count'];
    
    // 메시지 저장 (read_count = 참여자 수 - 1, 본인 제외)
    $read_count = $participant_count - 1;
    $stmt = $db->prepare("
        INSERT INTO messages (room_id, sender_id, content, message_type, read_count) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$room_id, $current_user_id, $content, $message_type, $read_count]);
    
    $message_id = $db->lastInsertId();
    
    // 채팅방 업데이트 시간 갱신
    $updateStmt = $db->prepare("UPDATE chat_rooms SET updated_at = NOW() WHERE id = ?");
    $updateStmt->execute([$room_id]);
    
    // 사용자 정보 조회
    $userStmt = $db->prepare("SELECT nickname, username FROM users WHERE id = ?");
    $userStmt->execute([$current_user_id]);
    $user = $userStmt->fetch();
    
    // 응답 데이터
    echo json_encode([
        'success' => true,
        'message' => [
            'id' => $message_id,
            'content' => $content,
            'sender_id' => $current_user_id,
            'sender_name' => $user['nickname'],
            'sender_username' => $user['username'],
            'message_type' => $message_type,
            'read_count' => $read_count,
            'created_at' => date('Y-m-d H:i:s'),
            'formatted_time' => formatMessageTime(date('Y-m-d H:i:s')),
            'is_sent' => true
        ]
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => '메시지 전송 중 오류가 발생했습니다.']);
}
?>
