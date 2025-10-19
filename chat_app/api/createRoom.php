<?php
require_once '../db.php';
header('Content-Type: application/json; charset=utf-8');

// ──────────────────────────────────────────────────────────────
// 공용 응답 헬퍼
// ──────────────────────────────────────────────────────────────
function json_fail($code, $msg) {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}
function json_ok($arr) {
    echo json_encode(array_merge(['success' => true], $arr), JSON_UNESCAPED_UNICODE);
    exit;
}

// ──────────────────────────────────────────────────────────────
// 인증 / 메서드 체크
// ──────────────────────────────────────────────────────────────
if (!isLoggedIn()) json_fail(401, '로그인이 필요합니다.');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_fail(405, 'POST 메서드만 허용됩니다.');

// ──────────────────────────────────────────────────────────────
// 입력 파싱 (JSON 바디/폼데이터 모두 허용)
// ──────────────────────────────────────────────────────────────
$raw = file_get_contents('php://input');
if ($raw) {
    $tmp = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($tmp)) {
        $_POST = $tmp;
    }
}

$room_type  = $_POST['room_type'] ?? 'private';        // 'private' | 'group'
$friend_ids = $_POST['friend_ids'] ?? [];
$room_name  = trim($_POST['room_name'] ?? '');

// friend_ids가 JSON 문자열로 왔을 수도 있음
if (is_string($friend_ids)) {
    $dec = json_decode($friend_ids, true);
    if (is_array($dec)) $friend_ids = $dec;
}

// 정수화 + 중복 제거
$friend_ids = array_values(array_unique(array_map('intval', (array)$friend_ids)));

// ──────────────────────────────────────────────────────────────
/* 기본 검증 */
// ──────────────────────────────────────────────────────────────
if (empty($friend_ids)) json_fail(400, '참여자를 선택해주세요.');

if ($room_type === 'group') {
    if ($room_name === '')      json_fail(400, '그룹 채팅방 이름을 입력해주세요.');
    if (count($friend_ids) < 2) json_fail(400, '그룹 채팅은 2명 이상 선택해주세요.');
} else { // private
    if (count($friend_ids) !== 1) json_fail(400, '1:1 채팅은 한 명만 선택해주세요.');
}

try {
    $db = getDB();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $me = getCurrentUserId();

    // ──────────────────────────────────────────────────────────
    // 1) 친구 관계 확인
    // ──────────────────────────────────────────────────────────
    $stmt = $db->prepare("
        SELECT friend_id
        FROM friendships
        WHERE user_id = ? AND status = 'accepted'
    ");
    $stmt->execute([$me]);
    $ok_map = array_fill_keys($stmt->fetchAll(PDO::FETCH_COLUMN), true);

    foreach ($friend_ids as $fid) {
        if (!isset($ok_map[$fid])) {
            json_fail(403, '친구 관계가 아닌 사용자가 포함되어 있습니다.');
        }
    }

    // ──────────────────────────────────────────────────────────
    // 2) 동일 멤버셋의 방이 이미 있는지 확인 (정확 매칭)
    //    - 참가자 수 COUNT(*) == N
    //    - 우리 멤버셋에 속하는 수 SUM(user_id IN (...)) == N
    // ──────────────────────────────────────────────────────────
    $members = array_values(array_unique(array_merge([$me], $friend_ids)));
    sort($members);
    $N = count($members);

    $place = implode(',', array_fill(0, $N, '?'));
    $sqlExact = "
        SELECT p.room_id
        FROM chat_room_participants p
        GROUP BY p.room_id
        HAVING COUNT(*) = ?
           AND SUM(p.user_id IN ($place)) = ?
        LIMIT 1
    ";
    $paramsExact = array_merge([$N], $members, [$N]);
    $stmt = $db->prepare($sqlExact);
    $stmt->execute($paramsExact);
    $dupRoomId = $stmt->fetchColumn();

    if ($dupRoomId) {
        // 중복이면 에러 대신 기존 방으로 접속시키기
        json_ok([
            'room_id'   => (int)$dupRoomId,
            'is_new'    => false,
            'room_type' => $room_type,
            'room_name' => ($room_type === 'group' ? $room_name : null)
        ]);
    }

    // ──────────────────────────────────────────────────────────
    // 3) 새 방 생성 + 참가자 추가 + 시스템 메시지 1건 삽입
    //    (메시지 1건을 넣어야 좌측 목록에도 즉시 노출됨)
// ──────────────────────────────────────────────────────────
    $db->beginTransaction();

    $stmt = $db->prepare("
        INSERT INTO chat_rooms (room_name, room_type, created_by)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$room_type === 'group' ? $room_name : null, $room_type, $me]);
    $room_id = (int)$db->lastInsertId();

    // 참가자
    $ins = $db->prepare("INSERT INTO chat_room_participants (room_id, user_id) VALUES (?, ?)");
    $ins->execute([$room_id, $me]);
    foreach ($friend_ids as $fid) {
        $ins->execute([$room_id, $fid]);
    }

    // 시스템 메시지 1건 (목록 노출용)
    $stmtMsg = $db->prepare("
        INSERT INTO messages (room_id, sender_id, message_type, content, read_count)
        VALUES (?, ?, 'system', ?, 0)
    ");
    $systemText = ($room_type === 'group')
        ? '그룹 채팅방이 생성되었습니다.'
        : '채팅방이 생성되었습니다.';
    $stmtMsg->execute([$room_id, $me, $systemText]);

    $db->commit();

    json_ok([
        'room_id'   => $room_id,
        'is_new'    => true,
        'room_type' => $room_type,
        'room_name' => ($room_type === 'group' ? $room_name : null)
    ]);

} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    // 개발 중 필요시 주석 해제해서 로그 확인
    // error_log('createRoom error: '.$e->getMessage().' | friend_ids='.json_encode($friend_ids, JSON_UNESCAPED_UNICODE));
    json_fail(500, '채팅방 생성 중 오류가 발생했습니다.');
}
