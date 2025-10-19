<?php
require_once '../db.php';

header('Content-Type: application/json; charset=utf-8');

// 로그인 확인
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => '로그인이 필요합니다.']);
    exit();
}

$search_term = trim($_GET['q'] ?? '');

if (empty($search_term)) {
    http_response_code(400);
    echo json_encode(['error' => '검색어를 입력해주세요.']);
    exit();
}

try {
    $db = getDB();
    $current_user_id = getCurrentUserId();
    
    // 닉네임 또는 이메일로 사용자 검색 (본인과 관리자 제외)
    $stmt = $db->prepare("
        SELECT id, username, email, nickname, status, profile_image
        FROM users 
        WHERE id != ? 
        AND id != 1 
        AND (nickname LIKE ? OR email LIKE ?)
        ORDER BY nickname ASC
        LIMIT 20
    ");
    $searchPattern = "%{$search_term}%";
    $stmt->execute([$current_user_id, $searchPattern, $searchPattern]);
    $users = $stmt->fetchAll();
    
    // 친구 관계 확인
    $friendStmt = $db->prepare("
        SELECT friend_id, status 
        FROM friendships 
        WHERE user_id = ? AND friend_id IN (" . implode(',', array_fill(0, count($users), '?')) . ")
    ");
    
    if (!empty($users)) {
        $userIds = array_column($users, 'id');
        $friendStmt->execute(array_merge([$current_user_id], $userIds));
        $friendships = $friendStmt->fetchAll();
        
        // 친구 관계를 배열로 변환
        $friendStatus = [];
        foreach ($friendships as $friendship) {
            $friendStatus[$friendship['friend_id']] = $friendship['status'];
        }
        
        // 사용자 정보에 친구 상태 추가
        foreach ($users as &$user) {
            $user['friendship_status'] = $friendStatus[$user['id']] ?? null;
            $user['is_online'] = $user['status'] === 'online';
        }
    }
    
    echo json_encode([
        'success' => true,
        'users' => $users
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => '사용자 검색 중 오류가 발생했습니다.']);
}
?>
