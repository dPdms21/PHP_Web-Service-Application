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

$nickname = sanitizeInput($_POST['nickname'] ?? '');
$profile_image = $_FILES['profile_image'] ?? null;

if (empty($nickname)) {
    http_response_code(400);
    echo json_encode(['error' => '닉네임을 입력해주세요.']);
    exit();
}

try {
    $db = getDB();
    $current_user_id = getCurrentUserId();
    $current_user = getCurrentUser();
    
    // 닉네임 중복 확인 (본인 제외)
    $checkStmt = $db->prepare("SELECT id FROM users WHERE nickname = ? AND id != ?");
    $checkStmt->execute([$nickname, $current_user_id]);
    
    if ($checkStmt->fetch()) {
        http_response_code(400);
        echo json_encode(['error' => '이미 사용 중인 닉네임입니다.']);
        exit();
    }
    
    $profile_image_path = $current_user['profile_image'];
    
    // 프로필 이미지 업로드 처리
    if ($profile_image && $profile_image['error'] == 0) {
        $upload_dir = '../assets/uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 2 * 1024 * 1024; // 2MB
        
        if (in_array($profile_image['type'], $allowed_types) && $profile_image['size'] <= $max_size) {
            $file_extension = pathinfo($profile_image['name'], PATHINFO_EXTENSION);
            $new_filename = 'profile_' . $current_user_id . '_' . time() . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($profile_image['tmp_name'], $upload_path)) {
                // 기존 이미지 삭제 (기본 이미지가 아닌 경우)
                if ($profile_image_path && $profile_image_path !== 'default_profile.png' && file_exists($upload_dir . $profile_image_path)) {
                    unlink($upload_dir . $profile_image_path);
                }
                $profile_image_path = $new_filename;
            } else {
                http_response_code(500);
                echo json_encode(['error' => '이미지 업로드에 실패했습니다.']);
                exit();
            }
        } else {
            http_response_code(400);
            echo json_encode(['error' => '지원하지 않는 파일 형식이거나 파일 크기가 너무 큽니다. (최대 2MB)']);
            exit();
        }
    }
    
    // 프로필 업데이트
    $updateStmt = $db->prepare("UPDATE users SET nickname = ?, profile_image = ? WHERE id = ?");
    $updateStmt->execute([$nickname, $profile_image_path, $current_user_id]);
    
    // 세션 정보 업데이트
    $_SESSION['nickname'] = $nickname;
    $_SESSION['user']['nickname'] = $nickname;
    $_SESSION['user']['profile_image'] = $profile_image_path;
    
    echo json_encode([
        'success' => true,
        'message' => '프로필이 성공적으로 업데이트되었습니다.',
        'profile_image' => $profile_image_path
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => '프로필 업데이트 중 오류가 발생했습니다.']);
}
?>
