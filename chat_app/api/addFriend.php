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
    
    // 본인과 관리자는 친구 추가 불가
    if ($friend_id == $current_user_id || $friend_id == 1) {
        http_response_code(400);
        echo json_encode(['error' => '해당 사용자와는 친구가 될 수 없습니다.']);
        exit();
    }
    
    // 사용자 존재 확인
    $userStmt = $db->prepare("SELECT id, nickname FROM users WHERE id = ?");
    $userStmt->execute([$friend_id]);
    $user = $userStmt->fetch();
    
    if (!$user) {
        http_response_code(404);
        echo json_encode(['error' => '사용자를 찾을 수 없습니다.']);
        exit();
    }
    
    // 기존 친구 관계 확인
    $checkStmt = $db->prepare("
        SELECT id, status FROM friendships 
        WHERE user_id = ? AND friend_id = ?
    ");
    $checkStmt->execute([$current_user_id, $friend_id]);
    $existing = $checkStmt->fetch();
    
    if ($existing) {
        if ($existing['status'] === 'accepted') {
            echo json_encode(['error' => '이미 친구입니다.']);
        } elseif ($existing['status'] === 'pending') {
            echo json_encode(['error' => '친구 요청이 대기 중입니다.']);
        } elseif ($existing['status'] === 'blocked') {
            echo json_encode(['error' => '차단된 사용자입니다.']);
        }
        exit();
    }
    
    // 친구 요청을 바로 수락된 상태로 생성
    $db->beginTransaction();
    
    $insertStmt = $db->prepare("
        INSERT INTO friendships (user_id, friend_id, status) 
        VALUES (?, ?, 'accepted')
    ");
    $insertStmt->execute([$current_user_id, $friend_id]);
    
    // 상대방에게도 친구 요청을 수락된 상태로 생성
    $insertStmt2 = $db->prepare("
        INSERT INTO friendships (user_id, friend_id, status) 
        VALUES (?, ?, 'accepted')
    ");
    $insertStmt2->execute([$friend_id, $current_user_id]);
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => "{$user['nickname']}님이 친구로 추가되었습니다."
    ]);
    
} catch (PDOException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => '친구 추가 중 오류가 발생했습니다.']);
}
?>
