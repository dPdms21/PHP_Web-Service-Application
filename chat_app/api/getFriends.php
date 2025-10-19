<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../db.php';
header('Content-Type: application/json; charset=utf-8');

function json_fail($c, $m) {
    http_response_code($c);
    echo json_encode(['success' => false, 'error' => $m], JSON_UNESCAPED_UNICODE);
    exit;
}
function json_ok($a) {
    echo json_encode(array_merge(['success' => true], $a), JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isLoggedIn()) json_fail(401, '로그인이 필요합니다.');

try {
    $db = getDB();
    $me = getCurrentUserId();

    // 친구 목록 (관리자 제외)
    $sqlFriends = "
        SELECT 
            u.id, u.username, u.nickname, u.email,
            u.profile_image, u.status, u.last_seen
        FROM friendships f
        JOIN users u ON u.id = f.friend_id
        WHERE f.user_id = ?
        AND f.status = 'accepted'
        AND u.username <> 'admin'
        AND u.id NOT IN (
            SELECT p2.user_id
            FROM chat_room_participants p1
            JOIN chat_room_participants p2 ON p1.room_id = p2.room_id
            JOIN chat_rooms r ON r.id = p1.room_id
            WHERE p1.user_id = ?
                AND r.room_type = 'private'
        )
        ORDER BY u.nickname ASC
    ";
    $stmt = $db->prepare($sqlFriends);
    $stmt->execute([$me, $me]);
    $friends = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 내가 포함된 채팅방 목록 (Admin 제외)
    $sqlRooms = "
        SELECT 
            r.id AS room_id,
            r.room_name,
            r.room_type,
            r.created_at,
            CASE 
                WHEN r.room_type = 'group' THEN r.room_name
                ELSE (
                    SELECT u2.nickname
                    FROM chat_room_participants p2
                    JOIN users u2 ON u2.id = p2.user_id
                    WHERE p2.room_id = r.id
                    AND u2.id <> ?
                    AND u2.username <> 'admin'
                    LIMIT 1
                )
            END AS display_name,
            (
                SELECT GROUP_CONCAT(u3.nickname SEPARATOR ', ')
                FROM chat_room_participants p3
                JOIN users u3 ON u3.id = p3.user_id
                WHERE p3.room_id = r.id
                AND u3.id <> ?
            ) AS participant_names,
            lm.content AS last_message,
            lm.created_at AS last_message_time,
            snd.nickname AS last_message_sender_name,

            -- 미읽음 메시지 수 (항상 0 이상 반환)
            COALESCE((
                SELECT COUNT(*)
                FROM messages m3
                JOIN users u5 ON u5.id = m3.sender_id
                WHERE m3.room_id = r.id
                AND m3.sender_id <> ?
                AND u5.username <> 'chatbot'  -- 챗봇 제외
                AND m3.id > COALESCE((
                    SELECT MAX(m4.id)
                    FROM messages m4
                    JOIN message_reads mr ON mr.message_id = m4.id
                    WHERE mr.user_id = ?
                        AND m4.room_id = r.id
                ), 0)
            ), 0) AS unread_count

        FROM chat_rooms r
        JOIN chat_room_participants p ON p.room_id = r.id
        LEFT JOIN messages lm ON lm.id = (
            SELECT MAX(m2.id)
            FROM messages m2
            WHERE m2.room_id = r.id
        )
        LEFT JOIN users snd ON snd.id = lm.sender_id
        WHERE p.user_id = ?
        AND NOT EXISTS (
            SELECT 1 FROM chat_room_participants p4
            JOIN users u4 ON u4.id = p4.user_id
            WHERE p4.room_id = r.id
                AND u4.username = 'admin'
        )
        ORDER BY 
            COALESCE(lm.created_at, r.created_at) DESC
    ";

    $stmt = $db->prepare($sqlRooms);
    $stmt->execute([$me, $me, $me, $me, $me]);
    $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // display_name 보정
    foreach ($rooms as &$r) {
        if (empty($r['display_name'])) {
            $r['display_name'] = $r['participant_names'] ?: '이름 없음';
        }
    }

    json_ok([
        'friends' => $friends,
        'rooms' => $rooms
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
