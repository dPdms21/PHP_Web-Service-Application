<?php
require_once 'db.php';

session_start(); // 세션이 열려 있어야 파괴 가능

// 로그인 확인 후 상태 업데이트(있으면)
if (isLoggedIn()) {
    try {
        $db = getDB();
        $current_user_id = getCurrentUserId();

        $updateStmt = $db->prepare("UPDATE users SET status = 'offline', last_seen = NOW() WHERE id = ?");
        $updateStmt->execute([$current_user_id]);
    } catch (PDOException $e) {
        // 에러가 나도 로그아웃은 계속 진행
    }
}

// 세션 데이터 비우기
$_SESSION = [];

// 세션 쿠키 제거
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
}

// 세션 파괴
session_destroy();

// 로그인 페이지로 이동
header('Location: login.php');
exit();
