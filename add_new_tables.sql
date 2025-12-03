-- ============================================
-- ADD NEW TABLES TO EXISTING DATABASE
-- Run this AFTER importing morskiiz_dfrnw.sql
-- ============================================

-- First, let's fix the question_categories table
-- Add new columns needed by the backend

ALTER TABLE `question_categories`
ADD COLUMN `name_bg` VARCHAR(255) NULL AFTER `category`,
ADD COLUMN `name_en` VARCHAR(255) NULL AFTER `name_bg`,
ADD COLUMN `price` DECIMAL(10,2) DEFAULT 25.00,
ADD COLUMN `duration_days` INT DEFAULT 365,
ADD COLUMN `exam_duration_minutes` INT DEFAULT 60,
ADD COLUMN `question_count` INT DEFAULT 0;

-- Copy category name to name_bg (assuming original categories are in Bulgarian)
UPDATE `question_categories` SET name_bg = category;

-- For English categories, also set name_en
UPDATE `question_categories` SET name_en = category WHERE category LIKE '%Navigation%' OR category LIKE '%Cargo%' OR category LIKE '%Care%';

-- Update question counts
UPDATE question_categories c
SET question_count = (
    SELECT COUNT(*) FROM questions WHERE question_category_id = c.id
);

-- ============================================
-- CREATE NEW TABLES FOR BACKEND
-- ============================================

-- Users table
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `first_name` VARCHAR(100) NOT NULL,
  `last_name` VARCHAR(100) NOT NULL,
  `role` ENUM('USER', 'ADMIN') DEFAULT 'USER',
  `suspended` BOOLEAN DEFAULT FALSE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `last_login` TIMESTAMP NULL,
  INDEX `idx_email` (`email`),
  INDEX `idx_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default admin user (password: admin123)
INSERT INTO `users` (`email`, `password_hash`, `first_name`, `last_name`, `role`) VALUES
('admin@maritime.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin', 'User', 'ADMIN');

-- Access requests table
CREATE TABLE IF NOT EXISTS `access_requests` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `category_id` INT UNSIGNED NOT NULL,
  `status` ENUM('PENDING', 'APPROVED', 'REJECTED') DEFAULT 'PENDING',
  `requested_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `processed_at` TIMESTAMP NULL,
  `processed_by` INT UNSIGNED NULL,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_status` (`status`),
  INDEX `idx_user` (`user_id`),
  INDEX `idx_category` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User categories (granted access) table
CREATE TABLE IF NOT EXISTS `user_categories` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `category_id` INT UNSIGNED NOT NULL,
  `granted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `expires_at` TIMESTAMP NULL,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_user_category` (`user_id`, `category_id`),
  INDEX `idx_expires` (`expires_at`),
  INDEX `idx_category` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Test sessions table
CREATE TABLE IF NOT EXISTS `test_sessions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `category_id` INT UNSIGNED NOT NULL,
  `started_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `completed_at` TIMESTAMP NULL,
  `score` INT DEFAULT 0,
  `total_questions` INT DEFAULT 60,
  `completed` BOOLEAN DEFAULT FALSE,
  `question_order` JSON NULL COMMENT 'Array of question IDs in test order',
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_user` (`user_id`),
  INDEX `idx_category` (`category_id`),
  INDEX `idx_completed` (`completed`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Test answers table
CREATE TABLE IF NOT EXISTS `test_answers` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `session_id` INT UNSIGNED NOT NULL,
  `question_id` INT UNSIGNED NOT NULL,
  `user_answer` ENUM('A', 'B', 'C', 'D') NULL,
  `is_correct` BOOLEAN NULL,
  `answered_at` TIMESTAMP NULL,
  FOREIGN KEY (`session_id`) REFERENCES `test_sessions`(`id`) ON DELETE CASCADE,
  INDEX `idx_session` (`session_id`),
  INDEX `idx_question` (`question_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Packages table
CREATE TABLE IF NOT EXISTS `packages` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name_bg` VARCHAR(255) NOT NULL,
  `name_en` VARCHAR(255) NOT NULL,
  `price` DECIMAL(10,2) NOT NULL,
  `duration_days` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Package categories (many-to-many) table
CREATE TABLE IF NOT EXISTS `package_categories` (
  `package_id` INT UNSIGNED NOT NULL,
  `category_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`package_id`, `category_id`),
  FOREIGN KEY (`package_id`) REFERENCES `packages`(`id`) ON DELETE CASCADE,
  INDEX `idx_category` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Settings table
CREATE TABLE IF NOT EXISTS `settings` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `key_name` VARCHAR(100) NOT NULL UNIQUE,
  `value` TEXT NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default settings
INSERT INTO `settings` (`key_name`, `value`) VALUES
('revolut_link', ''),
('facebook_link', '');

-- Audit log table
CREATE TABLE IF NOT EXISTS `audit_log` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NULL,
  `action` VARCHAR(100) NOT NULL,
  `details` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_user` (`user_id`),
  INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- MODIFY EXISTING QUESTIONS TABLE
-- ============================================

-- Add new columns to questions table for easier backend access
ALTER TABLE `questions`
ADD COLUMN `option_a` TEXT NULL AFTER `question`,
ADD COLUMN `option_b` TEXT NULL AFTER `option_a`,
ADD COLUMN `option_c` TEXT NULL AFTER `option_b`,
ADD COLUMN `option_d` TEXT NULL AFTER `option_c`,
ADD COLUMN `correct_answer` ENUM('A', 'B', 'C', 'D') NULL AFTER `option_d`,
ADD COLUMN `original_index` INT NULL AFTER `correct_answer`,
ADD INDEX `idx_category` (`question_category_id`),
ADD INDEX `idx_original_index` (`question_category_id`, `original_index`);

-- Populate option_a, option_b, option_c, option_d from question_answer_choices
-- This makes backend queries much faster and simpler
UPDATE questions q
SET
    option_a = (SELECT choice FROM question_answer_choices WHERE question_id = q.id ORDER BY id LIMIT 1),
    option_b = (SELECT choice FROM question_answer_choices WHERE question_id = q.id ORDER BY id LIMIT 1 OFFSET 1),
    option_c = (SELECT choice FROM question_answer_choices WHERE question_id = q.id ORDER BY id LIMIT 1 OFFSET 2),
    option_d = (SELECT choice FROM question_answer_choices WHERE question_id = q.id ORDER BY id LIMIT 1 OFFSET 3);

-- Set correct_answer based on is_correct flag in question_answer_choices
UPDATE questions q
SET correct_answer = (
    CASE
        WHEN (SELECT is_correct FROM question_answer_choices WHERE question_id = q.id ORDER BY id LIMIT 1) = 1 THEN 'A'
        WHEN (SELECT is_correct FROM question_answer_choices WHERE question_id = q.id ORDER BY id LIMIT 1 OFFSET 1) = 1 THEN 'B'
        WHEN (SELECT is_correct FROM question_answer_choices WHERE question_id = q.id ORDER BY id LIMIT 1 OFFSET 2) = 1 THEN 'C'
        WHEN (SELECT is_correct FROM question_answer_choices WHERE question_id = q.id ORDER BY id LIMIT 1 OFFSET 3) = 1 THEN 'D'
        ELSE 'A'
    END
);

-- Generate original_index for 25% distribution algorithm
-- This assigns sequential numbers within each category
UPDATE questions q
JOIN (
    SELECT
        id,
        @row_num := IF(@prev_cat = question_category_id, @row_num + 1, 1) AS row_index,
        @prev_cat := question_category_id
    FROM questions,
    (SELECT @row_num := 0, @prev_cat := NULL) AS vars
    ORDER BY question_category_id, id
) AS numbered ON q.id = numbered.id
SET q.original_index = numbered.row_index;

-- ============================================
-- VIEWS FOR CONVENIENCE
-- ============================================

CREATE OR REPLACE VIEW `v_user_access` AS
SELECT
    u.id as user_id,
    u.email,
    u.first_name,
    u.last_name,
    c.id as category_id,
    c.category as category_name,
    uc.granted_at,
    uc.expires_at,
    CASE
        WHEN uc.expires_at IS NULL THEN 'ACTIVE'
        WHEN uc.expires_at > NOW() THEN 'ACTIVE'
        ELSE 'EXPIRED'
    END as status
FROM users u
JOIN user_categories uc ON u.id = uc.user_id
JOIN question_categories c ON uc.category_id = c.id;

CREATE OR REPLACE VIEW `v_pending_requests` AS
SELECT
    ar.id as request_id,
    u.email,
    u.first_name,
    u.last_name,
    c.category as category_name,
    ar.requested_at
FROM access_requests ar
JOIN users u ON ar.user_id = u.id
JOIN question_categories c ON ar.category_id = c.id
WHERE ar.status = 'PENDING'
ORDER BY ar.requested_at ASC;

CREATE OR REPLACE VIEW `v_test_results` AS
SELECT
    ts.id as session_id,
    u.email,
    u.first_name,
    u.last_name,
    c.category as category_name,
    ts.score,
    ts.total_questions,
    ROUND((ts.score / ts.total_questions) * 100, 2) as percentage,
    ts.started_at,
    ts.completed_at,
    TIMESTAMPDIFF(MINUTE, ts.started_at, ts.completed_at) as duration_minutes
FROM test_sessions ts
JOIN users u ON ts.user_id = u.id
JOIN question_categories c ON ts.category_id = c.id
WHERE ts.completed = TRUE
ORDER BY ts.completed_at DESC;

-- ============================================
-- DONE!
-- ============================================

SELECT 'Database update completed successfully!' as status;
SELECT COUNT(*) as total_questions FROM questions;
SELECT COUNT(*) as total_categories FROM question_categories;
SELECT COUNT(*) as total_users FROM users;
