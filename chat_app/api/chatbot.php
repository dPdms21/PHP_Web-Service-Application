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

$message = trim($_POST['message'] ?? '');

if (empty($message)) {
    http_response_code(400);
    echo json_encode(['error' => '메시지가 필요합니다.']);
    exit();
}

// 챗봇 응답 생성
function generateChatbotResponse($message) {
    $message = strtolower($message);
    
    // 인사말 패턴
    if (preg_match('/안녕|hello|hi|하이/', $message)) {
        return [
            '안녕하세요! 오늘 기분은 어때요?',
            '안녕! 반가워요 😊',
            '안녕하세요! 무엇을 도와드릴까요?'
        ][array_rand([0, 1, 2])];
    }
    
    // 날씨 관련
    if (preg_match('/날씨|weather|비|눈|맑음/', $message)) {
        return [
            '오늘 날씨가 정말 좋네요! 산책하기 좋은 날씨예요.',
            '날씨에 대해 물어보시는군요. 창밖을 보니 맑은 날씨네요!',
            '날씨가 좋으면 기분도 좋아지죠! 🌤️'
        ][array_rand([0, 1, 2])];
    }
    
    // 시간 관련
    if (preg_match('/시간|time|몇시|언제/', $message)) {
        $current_time = date('H:i');
        $current_date = date('Y년 m월 d일');
        return "현재 시간은 {$current_time}이고, 오늘은 {$current_date}입니다.";
    }
    
    // 기분 관련
    if (preg_match('/기분|mood|좋아|나빠|슬퍼|행복/', $message)) {
        return [
            '기분이 좋으시군요! 긍정적인 에너지가 느껴져요 😊',
            '기분이 안 좋으시다면 제가 도와드릴게요. 무엇이든 말씀해주세요.',
            '기분이 어떠시든 저는 항상 여기 있을게요!'
        ][array_rand([0, 1, 2])];
    }
    
    // 감사 표현
    if (preg_match('/감사|고마워|thank|thanks/', $message)) {
        return [
            '천만에요! 도움이 되어서 기뻐요 😊',
            '별말씀을요! 언제든지 말씀해주세요.',
            '고마워요! 저도 도움이 되어서 행복해요.'
        ][array_rand([0, 1, 2])];
    }
    
    // 이름 관련
    if (preg_match('/이름|name|누구|who/', $message)) {
        return [
            '저는 챗봇이에요! 여러분과 대화하는 것이 즐거워요.',
            '안녕하세요! 저는 이 채팅앱의 챗봇입니다.',
            '저는 여러분의 친구가 되고 싶은 챗봇이에요!'
        ][array_rand([0, 1, 2])];
    }
    
    // 도움말
    if (preg_match('/도움|help|도와|어떻게/', $message)) {
        return "저는 여러분과 대화하는 챗봇이에요! 인사말, 날씨, 시간, 기분 등에 대해 이야기할 수 있어요. 무엇이든 편하게 말씀해주세요! 😊";
    }
    
    // 질문 패턴
    if (preg_match('/\?|물어|궁금/', $message)) {
        return [
            '좋은 질문이네요! 더 자세히 말씀해주시면 도움을 드릴 수 있을 것 같아요.',
            '흥미로운 질문이에요! 저도 궁금해지네요.',
            '그런 질문을 하시는군요! 저는 항상 대답할 준비가 되어있어요.'
        ][array_rand([0, 1, 2])];
    }
    
    // 기본 응답
    $default_responses = [
        '흥미로운 말씀이네요! 더 자세히 들려주세요.',
        '그렇군요! 저도 그렇게 생각해요.',
        '정말요? 저도 비슷한 생각을 하고 있었어요.',
        '좋은 이야기네요! 계속 들려주세요.',
        '저도 그런 것에 대해 생각해봤어요.',
        '정말 흥미로워요! 더 말씀해주세요.',
        '그런 관점도 있군요! 새로운 걸 배웠어요.',
        '저도 그렇게 느껴요! 공감이 가네요.',
        '좋은 의견이에요! 저도 동감해요.',
        '정말 재미있는 이야기네요! 😊'
    ];
    
    return $default_responses[array_rand($default_responses)];
}

try {
    $db = getDB();
    $current_user_id = getCurrentUserId();
    
    // 챗봇 사용자 ID 조회
    $chatbotStmt = $db->prepare("SELECT id FROM users WHERE username = 'chatbot'");
    $chatbotStmt->execute();
    $chatbot = $chatbotStmt->fetch();
    
    if (!$chatbot) {
        http_response_code(500);
        echo json_encode(['error' => '챗봇을 찾을 수 없습니다.']);
        exit();
    }
    
    $chatbot_id = $chatbot['id'];
    
    // 사용자와 챗봇 간의 친구 관계 확인
    $friendStmt = $db->prepare("
        SELECT id FROM friendships 
        WHERE user_id = ? AND friend_id = ? AND status = 'accepted'
    ");
    $friendStmt->execute([$current_user_id, $chatbot_id]);
    
    if (!$friendStmt->fetch()) {
        http_response_code(403);
        echo json_encode(['error' => '챗봇과 친구가 되어야 대화할 수 있습니다.']);
        exit();
    }
    
    // 사용자와 챗봇 간의 채팅방 찾기
    $roomStmt = $db->prepare("
        SELECT cr.id 
        FROM chat_rooms cr
        JOIN chat_room_participants crp1 ON cr.id = crp1.room_id
        JOIN chat_room_participants crp2 ON cr.id = crp2.room_id
        WHERE cr.room_type = 'private'
        AND crp1.user_id = ? AND crp2.user_id = ?
    ");
    $roomStmt->execute([$current_user_id, $chatbot_id]);
    $room = $roomStmt->fetch();
    
    if (!$room) {
        // 챗봇과의 채팅방이 없으면 생성
        $db->beginTransaction();
        
        $createRoomStmt = $db->prepare("
            INSERT INTO chat_rooms (room_type, created_by) 
            VALUES ('private', ?)
        ");
        $createRoomStmt->execute([$current_user_id]);
        $room_id = $db->lastInsertId();
        
        $participantStmt = $db->prepare("
            INSERT INTO chat_room_participants (room_id, user_id) 
            VALUES (?, ?)
        ");
        $participantStmt->execute([$room_id, $current_user_id]);
        $participantStmt->execute([$room_id, $chatbot_id]);
        
        $db->commit();
    } else {
        $room_id = $room['id'];
    }
    
    // 채팅방 참여자 수 조회
    $participantStmt = $db->prepare("SELECT COUNT(*) as count FROM chat_room_participants WHERE room_id = ?");
    $participantStmt->execute([$room_id]);
    $participant_count = $participantStmt->fetch()['count'];

    // 챗봇 응답 생성
    $response = generateChatbotResponse($message);
    
    // 챗봇 응답 저장
    $chatbotMessageStmt = $db->prepare("
        INSERT INTO messages (room_id, sender_id, content, message_type, read_count) 
        VALUES (?, ?, ?, 'text', ?)
    ");
    $chatbotMessageStmt->execute([$room_id, $chatbot_id, $response, $participant_count - 1]);
    
    // 채팅방 업데이트 시간 갱신
    $updateStmt = $db->prepare("UPDATE chat_rooms SET updated_at = NOW() WHERE id = ?");
    $updateStmt->execute([$room_id]);
    
    echo json_encode([
        'success' => true,
        'response' => $response,
        'room_id' => $room_id
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => '챗봇 응답 생성 중 오류가 발생했습니다.']);
}
?>
