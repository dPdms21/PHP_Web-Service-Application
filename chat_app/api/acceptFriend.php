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

$friend_id = $_POST['friend_id'] ?? null;

if (!$friend_id) {
    http_response_code(400);
    echo json_encode(['error' => '친구 ID가 필요합니다.']);
    exit();
}

try {
    $db = getDB();
    $current_user_id = getCurrentUserId();
    
    // 친구 요청 확인
    $checkStmt = $db->prepare("
        SELECT id FROM friendships 
        WHERE user_id = ? AND friend_id = ? AND status = 'pending'
    ");
    $checkStmt->execute([$current_user_id, $friend_id]);
    
    if (!$checkStmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => '친구 요청을 찾을 수 없습니다.']);
        exit();
    }
    
    // 친구 요청 수락
    $db->beginTransaction();
    
    $updateStmt = $db->prepare("
        UPDATE friendships 
        SET status = 'accepted' 
        WHERE user_id = ? AND friend_id = ?
    ");
    $updateStmt->execute([$current_user_id, $friend_id]);
    
    $updateStmt2 = $db->prepare("
        UPDATE friendships 
        SET status = 'accepted' 
        WHERE user_id = ? AND friend_id = ?
    ");
    $updateStmt2->execute([$friend_id, $current_user_id]);
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => '친구 요청을 수락했습니다.'
    ]);
    
} catch (PDOException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => '친구 요청 수락 중 오류가 발생했습니다.']);
}
?>
