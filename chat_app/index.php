<?php
require_once 'db.php';

// 로그인 상태 확인
if (isLoggedIn()) {
    // 로그인된 사용자는 채팅 페이지로 리다이렉트
    header('Location: chat.php');
} else {
    // 로그인되지 않은 사용자는 로그인 페이지로 리다이렉트
    header('Location: login.php');
}
exit();
?>
