<?php
require_once 'db.php';

// 이미 로그인된 사용자는 채팅 페이지로 리다이렉트
if (isLoggedIn()) {
    header('Location: chat.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = sanitizeInput($_POST['username'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $nickname = sanitizeInput($_POST['nickname'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // 유효성 검사
    if (empty($username) || empty($email) || empty($nickname) || empty($password)) {
        $error = '모든 필드를 입력해주세요.';
    } elseif (strlen($username) < 3) {
        $error = '사용자명은 최소 3자 이상이어야 합니다.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = '올바른 이메일 주소를 입력해주세요.';
    } elseif (strlen($password) < 6) {
        $error = '비밀번호는 최소 6자 이상이어야 합니다.';
    } elseif ($password !== $confirm_password) {
        $error = '비밀번호가 일치하지 않습니다.';
    } else {
        try {
            $db = getDB();
            
            // 중복 검사
            $checkStmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $checkStmt->execute([$username, $email]);
            if ($checkStmt->fetch()) {
                $error = '이미 사용 중인 사용자명 또는 이메일입니다.';
            } else {
                // 사용자 등록
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $insertStmt = $db->prepare("INSERT INTO users (username, email, nickname, password, status) VALUES (?, ?, ?, ?, 'online')");
                $insertStmt->execute([$username, $email, $nickname, $hashedPassword]);
                
                $success = '회원가입이 완료되었습니다. 로그인해주세요.';
                
                // 성공 후 폼 데이터 초기화
                $username = $email = $nickname = '';
                
                // 3초 후 로그인 페이지로 리다이렉트
                header("refresh:3;url=login.php");
            }
        } catch (PDOException $e) {
            $error = '회원가입 중 오류가 발생했습니다.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>회원가입 - 카카오톡 스타일 채팅</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-logo">
                <i class="fas fa-user-plus"></i> 회원가입
            </div>
            <p class="auth-subtitle">새 계정을 만들어보세요</p>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="registerForm">
                <div class="form-group">
                    <label for="username">
                        <i class="fas fa-user"></i> 사용자명
                    </label>
                    <input type="text" id="username" name="username" class="form-control" 
                           value="<?php echo htmlspecialchars($username ?? ''); ?>" 
                           minlength="3" required>
                    <small style="color: #666; font-size: 0.8rem;">최소 3자 이상</small>
                </div>
                
                <div class="form-group">
                    <label for="email">
                        <i class="fas fa-envelope"></i> 이메일
                    </label>
                    <input type="email" id="email" name="email" class="form-control" 
                           value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="nickname">
                        <i class="fas fa-id-card"></i> 닉네임
                    </label>
                    <input type="text" id="nickname" name="nickname" class="form-control" 
                           value="<?php echo htmlspecialchars($nickname ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="password">
                        <i class="fas fa-lock"></i> 비밀번호
                    </label>
                    <input type="password" id="password" name="password" class="form-control" 
                           minlength="6" required>
                    <small style="color: #666; font-size: 0.8rem;">최소 6자 이상</small>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">
                        <i class="fas fa-lock"></i> 비밀번호 확인
                    </label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" 
                           minlength="6" required>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-user-plus"></i> 회원가입
                </button>
            </form>
            
            <div class="auth-links">
                <p>이미 계정이 있으신가요? <a href="login.php">로그인</a></p>
            </div>
        </div>
    </div>
    
    <script>
        // 비밀번호 확인 검증
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (password !== confirmPassword) {
                this.setCustomValidity('비밀번호가 일치하지 않습니다.');
            } else {
                this.setCustomValidity('');
            }
        });
        
        // 폼 제출 시 로딩 상태 표시
        document.getElementById('registerForm').addEventListener('submit', function() {
            const submitBtn = document.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 가입 중...';
            submitBtn.disabled = true;
        });
        
        // 실시간 유효성 검사
        document.getElementById('username').addEventListener('input', function() {
            if (this.value.length < 3) {
                this.setCustomValidity('사용자명은 최소 3자 이상이어야 합니다.');
            } else {
                this.setCustomValidity('');
            }
        });
        
        document.getElementById('password').addEventListener('input', function() {
            if (this.value.length < 6) {
                this.setCustomValidity('비밀번호는 최소 6자 이상이어야 합니다.');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html>
