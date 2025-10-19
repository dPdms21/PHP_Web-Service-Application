<?php
/**
 * 데이터베이스 연결 설정
 * 카카오톡 스타일 채팅 애플리케이션
 */

// 데이터베이스 설정
define('DB_HOST', 'localhost');
define('DB_NAME', 'chat_app');
define('DB_USER', 'root');
define('DB_PASS', '123456');
define('DB_CHARSET', 'utf8mb4');

class Database {
    private $connection;
    private static $instance = null;
    
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
            ];
            
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die("데이터베이스 연결 실패: " . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    // 싱글톤 패턴을 위한 private 메서드들
    private function __clone() {}
    public function __wakeup() {}
}

// 세션 시작
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 유틸리티 함수들
function getDB() {
    return Database::getInstance()->getConnection();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([getCurrentUserId()]);
    return $stmt->fetch();
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function formatTime($timestamp) {
    $time = new DateTime($timestamp);
    $now = new DateTime();
    $diff = $now->diff($time);
    
    if ($diff->days > 0) {
        return $time->format('m/d H:i');
    } else {
        return $time->format('H:i');
    }
}

function formatMessageTime($timestamp) {
    $time = new DateTime($timestamp);
    $now = new DateTime();
    $diff = $now->diff($time);
    
    if ($diff->days > 0) {
        return $time->format('Y-m-d H:i');
    } else {
        return $time->format('H:i');
    }
}
?>
