<?php
require_once 'db.php';

// 이미 로그인된 사용자는 채팅 페이지로 리다이렉트
if (isLoggedIn()) {
    header('Location: chat.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = '사용자명과 비밀번호를 입력해주세요.';
    } else {
        try {
            $db = getDB();
            $stmt = $db->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                // 로그인 성공
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['nickname'] = $user['nickname'];
                
                // 사용자 상태를 온라인으로 변경
                $updateStmt = $db->prepare("UPDATE users SET status = 'online', last_seen = NOW() WHERE id = ?");
                $updateStmt->execute([$user['id']]);
                
                header('Location: chat.php');
                exit();
            } else {
                $error = '사용자명 또는 비밀번호가 올바르지 않습니다.';
            }
        } catch (PDOException $e) {
            $error = '로그인 중 오류가 발생했습니다.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>로그인 - 카카오톡 스타일 채팅</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-logo">
                <i class="fas fa-comments"></i> 채팅앱
            </div>
            <p class="auth-subtitle">친구들과 대화를 시작해보세요</p>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="username">
                        <i class="fas fa-user"></i> 사용자명 또는 이메일
                    </label>
                    <input type="text" id="username" name="username" class="form-control" 
                           value="<?php echo htmlspecialchars($username ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="password">
                        <i class="fas fa-lock"></i> 비밀번호
                    </label>
                    <input type="password" id="password" name="password" class="form-control" required>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt"></i> 로그인
                </button>
            </form>
            
            <div class="auth-links">
                <p>계정이 없으신가요? <a href="register.php">회원가입</a></p>
            </div>
            
            <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;">
                <h4 style="color: #666; margin-bottom: 15px;">테스트 계정</h4>
                <div style="text-align: left; font-size: 0.9rem; color: #666;">
                    <p><strong>관리자:</strong> admin / password</p>
                    <p><strong>사용자1:</strong> user1 / password</p>
                    <p><strong>사용자2:</strong> user2 / password</p>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // 폼 제출 시 로딩 상태 표시
        document.querySelector('form').addEventListener('submit', function() {
            const submitBtn = document.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 로그인 중...';
            submitBtn.disabled = true;
        });
        
        // 엔터키로 폼 제출
        document.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.querySelector('form').submit();
            }
        });
    </script>
</body>
</html>
