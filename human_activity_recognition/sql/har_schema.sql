-- DB 생성 (이미 있다면 생략)
CREATE DATABASE IF NOT EXISTS har_db
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE har_db;

-- Fusion 추론 로그 테이블
CREATE TABLE IF NOT EXISTS har_fusion_results (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  video_name      VARCHAR(255) NOT NULL,
  predicted_label VARCHAR(50)  NOT NULL,
  confidence      FLOAT        NOT NULL,
  top1_label      VARCHAR(50)  NOT NULL,
  top1_prob       FLOAT        NOT NULL,
  top2_label      VARCHAR(50),
  top2_prob       FLOAT,
  top3_label      VARCHAR(50),
  top3_prob       FLOAT,
  created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);
