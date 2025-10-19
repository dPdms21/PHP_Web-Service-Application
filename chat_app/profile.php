<?php
require_once 'db.php';

// 로그인 확인
requireLogin();

$current_user = getCurrentUser();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nickname = sanitizeInput($_POST['nickname'] ?? '');
    $profile_image = $_FILES['profile_image'] ?? null;
    
    if (empty($nickname)) {
        $error = '닉네임을 입력해주세요.';
    } else {
        try {
            $db = getDB();
            $current_user_id = getCurrentUserId();
            
            // 닉네임 중복 확인 (본인 제외)
            $checkStmt = $db->prepare("SELECT id FROM users WHERE nickname = ? AND id != ?");
            $checkStmt->execute([$nickname, $current_user_id]);
            
            if ($checkStmt->fetch()) {
                $error = '이미 사용 중인 닉네임입니다.';
            } else {
                $profile_image_path = $current_user['profile_image'];
                
                // 프로필 이미지 업로드 처리
                if ($profile_image && $profile_image['error'] == 0) {
                    $upload_dir = 'assets/uploads/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg'];
                    $max_size = 2 * 1024 * 1024; // 2MB
                    
                    // 파일 타입과 크기 검증
                    if (in_array($profile_image['type'], $allowed_types) && $profile_image['size'] <= $max_size) {
                        $file_extension = strtolower(pathinfo($profile_image['name'], PATHINFO_EXTENSION));
                        $new_filename = 'profile_' . $current_user_id . '_' . time() . '.' . $file_extension;
                        $upload_path = $upload_dir . $new_filename;
                        
                        // 파일 업로드 시도
                        if (move_uploaded_file($profile_image['tmp_name'], $upload_path)) {
                            // 기존 이미지 삭제 (기본 이미지가 아닌 경우)
                            if ($profile_image_path && $profile_image_path !== 'default_profile.png' && $profile_image_path !== 'default.png' && file_exists($upload_dir . $profile_image_path)) {
                                unlink($upload_dir . $profile_image_path);
                            }
                            $profile_image_path = $new_filename;
                        } else {
                            $error = '이미지 업로드에 실패했습니다. 파일 권한을 확인해주세요. (경로: ' . $upload_path . ')';
                        }
                    } else {
                        if (!in_array($profile_image['type'], $allowed_types)) {
                            $error = '지원하지 않는 파일 형식입니다. (JPEG, PNG, GIF만 허용)';
                        } else {
                            $error = '파일 크기가 너무 큽니다. (최대 2MB)';
                        }
                    }
                } elseif ($profile_image && $profile_image['error'] != 0) {
                    // 업로드 에러 처리
                    switch ($profile_image['error']) {
                        case UPLOAD_ERR_INI_SIZE:
                        case UPLOAD_ERR_FORM_SIZE:
                            $error = '파일 크기가 너무 큽니다.';
                            break;
                        case UPLOAD_ERR_PARTIAL:
                            $error = '파일이 부분적으로만 업로드되었습니다.';
                            break;
                        case UPLOAD_ERR_NO_FILE:
                            // 파일이 선택되지 않음 - 에러 아님
                            break;
                        default:
                            $error = '파일 업로드 중 오류가 발생했습니다.';
                    }
                }
                
                if (empty($error)) {
                    // 프로필 업데이트
                    $updateStmt = $db->prepare("UPDATE users SET nickname = ?, profile_image = ? WHERE id = ?");
                    $updateStmt->execute([$nickname, $profile_image_path, $current_user_id]);
                    
                    $success = '프로필이 성공적으로 업데이트되었습니다.';
                    
                    // 세션 정보 업데이트
                    $_SESSION['nickname'] = $nickname;
                    $_SESSION['user']['nickname'] = $nickname;
                    $_SESSION['user']['profile_image'] = $profile_image_path;
                    
                    // 현재 사용자 정보 새로고침
                    $current_user = getCurrentUser();
                    
                    // 2초 후 채팅 페이지로 리다이렉트
                    header("refresh:2;url=chat.php");
                }
            }
        } catch (PDOException $e) {
            $error = '프로필 업데이트 중 오류가 발생했습니다.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>프로필 수정 - <?php echo htmlspecialchars($current_user['nickname']); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .profile-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
        }
        
        .profile-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            padding: 40px;
            width: 100%;
            max-width: 500px;
            text-align: center;
        }
        
        .profile-image-container {
            margin-bottom: 30px;
        }
        
        .profile-image {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #3c1e1e;
            margin-bottom: 15px;
        }
        
        .profile-image-placeholder {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: #3c1e1e;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
            font-weight: bold;
            margin: 0 auto 15px;
            border: 4px solid #3c1e1e;
        }
        
        .file-input-wrapper {
            position: relative;
            display: inline-block;
            margin-bottom: 20px;
        }
        
        .file-input {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        
        .file-input-label {
            display: inline-block;
            padding: 10px 20px;
            background: #f8f9fa;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .file-input-label:hover {
            background: #e9ecef;
            border-color: #3c1e1e;
        }
        
        .back-btn {
            position: absolute;
            top: 20px;
            left: 20px;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: none;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        
        .back-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.1);
        }
    </style>
</head>
<body>
    <div class="profile-container">
        <a href="chat.php" class="back-btn" title="채팅으로 돌아가기">
            <i class="fas fa-arrow-left"></i>
        </a>
        
        <div class="profile-card">
            <h2 style="color: #3c1e1e; margin-bottom: 30px;">
                <i class="fas fa-user-edit"></i> 프로필 수정
            </h2>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    <br><small>잠시 후 채팅 페이지로 이동합니다...</small>
                </div>
            <?php endif; ?>
            
            <div class="profile-image-container">
                <?php if ($current_user['profile_image'] && $current_user['profile_image'] !== 'default_profile.png' && $current_user['profile_image'] !== 'default.png' && file_exists('assets/uploads/' . $current_user['profile_image'])): ?>
                    <img src="assets/uploads/<?php echo htmlspecialchars($current_user['profile_image']); ?>" 
                         alt="프로필 이미지" class="profile-image" id="profileImage">
                <?php else: ?>
                    <div class="profile-image-placeholder" id="profileImagePlaceholder">
                        <?php echo strtoupper(substr($current_user['nickname'], 0, 1)); ?>
                    </div>
                <?php endif; ?>
                
                <div class="file-input-wrapper">
                    <input type="file" id="profileImageInput" class="file-input" 
                           accept="image/*" name="profile_image">
                    <label for="profileImageInput" class="file-input-label">
                        <i class="fas fa-camera"></i> 이미지 변경
                    </label>
                </div>
            </div>
            
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="nickname">
                        <i class="fas fa-id-card"></i> 닉네임
                    </label>
                    <input type="text" id="nickname" name="nickname" class="form-control" 
                           value="<?php echo htmlspecialchars($current_user['nickname']); ?>" required>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> 프로필 저장
                </button>
            </form>
            
            <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;">
                <h4 style="color: #666; margin-bottom: 15px;">계정 정보</h4>
                <div style="text-align: left; font-size: 0.9rem; color: #666;">
                    <p><strong>사용자명:</strong> <?php echo htmlspecialchars($current_user['username']); ?></p>
                    <p><strong>이메일:</strong> <?php echo htmlspecialchars($current_user['email']); ?></p>
                    <p><strong>가입일:</strong> <?php echo date('Y-m-d', strtotime($current_user['created_at'])); ?></p>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // 프로필 이미지 미리보기
        document.getElementById('profileImageInput').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                // 파일 타입 검증
                const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg'];
                if (!allowedTypes.includes(file.type)) {
                    alert('지원하지 않는 파일 형식입니다. (JPEG, PNG, GIF만 허용)');
                    this.value = '';
                    return;
                }
                
                // 파일 크기 검증 (2MB)
                if (file.size > 2 * 1024 * 1024) {
                    alert('파일 크기가 너무 큽니다. (최대 2MB)');
                    this.value = '';
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    const profileImage = document.getElementById('profileImage');
                    const profileImagePlaceholder = document.getElementById('profileImagePlaceholder');
                    
                    if (profileImage) {
                        profileImage.src = e.target.result;
                    } else if (profileImagePlaceholder) {
                        profileImagePlaceholder.style.display = 'none';
                        const newImg = document.createElement('img');
                        newImg.src = e.target.result;
                        newImg.alt = '프로필 이미지';
                        newImg.className = 'profile-image';
                        newImg.id = 'profileImage';
                        profileImagePlaceholder.parentNode.insertBefore(newImg, profileImagePlaceholder);
                    }
                };
                reader.readAsDataURL(file);
            }
        });
        
        // 폼 제출 시 로딩 상태 표시
        document.querySelector('form').addEventListener('submit', function() {
            const submitBtn = document.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 저장 중...';
            submitBtn.disabled = true;
        });
    </script>
</body>
</html>
