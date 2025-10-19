<?php
require_once '../db.php';
header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => '로그인이 필요합니다.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $db = getDB();
    $me = getCurrentUserId();

    $sql = "
        SELECT 
            u.id, u.username, u.nickname, u.email,
            u.profile_image, u.status, u.last_seen
        FROM friendships f
        JOIN users u ON u.id = f.friend_id
        WHERE f.user_id = ?
          AND f.status = 'accepted'
          AND u.username <> 'admin'
        ORDER BY u.nickname ASC
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute([$me]);
    $friends = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'friends' => $friends], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
