-- 카카오톡 스타일 채팅 애플리케이션 데이터베이스 스크립트
-- MySQL 8.0+ 호환

SET NAMES utf8mb4;

-- 데이터베이스 생성
CREATE DATABASE IF NOT EXISTS chat_app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE chat_app;

-- 사용자 테이블
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    nickname VARCHAR(50) NOT NULL,
    profile_image VARCHAR(255) DEFAULT 'default_profile.png',
    status ENUM('online', 'offline', 'away') DEFAULT 'offline',
    last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 친구 관계 테이블
CREATE TABLE friendships (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    friend_id INT NOT NULL,
    status ENUM('pending', 'accepted', 'blocked') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (friend_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_friendship (user_id, friend_id)
);

-- 채팅방 테이블
CREATE TABLE chat_rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_name VARCHAR(100),
    room_type ENUM('private', 'group') DEFAULT 'private',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- 채팅방 참여자 테이블
CREATE TABLE chat_room_participants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    user_id INT NOT NULL,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES chat_rooms(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_participant (room_id, user_id)
);

-- 메시지 테이블
CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    sender_id INT NOT NULL,
    message_type ENUM('text', 'image', 'file', 'system') DEFAULT 'text',
    content TEXT NOT NULL,
    read_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES chat_rooms(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_room_created (room_id, created_at)
);

-- 메시지 읽음 상태 테이블
CREATE TABLE message_reads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT NOT NULL,
    user_id INT NOT NULL,
    read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_message_user (message_id, user_id)
);

-- 기본 사용자 데이터 삽입 (테스트용)
INSERT INTO users (username, password, email, nickname, status) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@example.com', '관리자', 'online'),
('user1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user1@example.com', '사용자1', 'online'),
('user2', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user2@example.com', '사용자2', 'online'),
('chatbot', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'chatbot@example.com', '챗봇', 'online');

-- 기본 친구 관계 설정
INSERT INTO friendships (user_id, friend_id, status) VALUES
(1, 2, 'accepted'),
(1, 3, 'accepted'),
(2, 1, 'accepted'),
(2, 3, 'accepted'),
(3, 1, 'accepted'),
(3, 2, 'accepted'),
(1, 4, 'accepted'),
(4, 1, 'accepted');

-- 기본 채팅방 생성
INSERT INTO chat_rooms (room_name, room_type, created_by) VALUES
('Admin-User1', 'private', 1),
('Admin-User2', 'private', 1),
('User1-User2', 'private', 2),
('Admin-Chatbot', 'private', 1);

-- 채팅방 참여자 설정
INSERT INTO chat_room_participants (room_id, user_id) VALUES
(1, 1), (1, 2),
(2, 1), (2, 3),
(3, 2), (3, 3),
(4, 1), (4, 4);

-- 샘플 메시지 데이터
INSERT INTO messages (room_id, sender_id, content, read_count) VALUES
(1, 1, '안녕하세요!', 1),
(1, 2, '안녕하세요! 오늘 날씨가 좋네요.', 1),
(1, 1, '네, 정말 좋은 날씨입니다.', 1),
(4, 1, '안녕', 0),
(4, 4, '안녕하세요! 오늘 기분은 어때요?', 1);

-- 인덱스 최적화
CREATE INDEX idx_messages_room_created ON messages(room_id, created_at);
CREATE INDEX idx_friendships_user_status ON friendships(user_id, status);
CREATE INDEX idx_users_username ON users(username);
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_messages_room_id_id ON messages(room_id, id);
CREATE INDEX idx_message_reads_user ON message_reads(user_id);
CREATE INDEX idx_message_reads_msg_user ON message_reads(message_id, user_id);