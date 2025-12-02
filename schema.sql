-- ============================================================
-- MARITIME EXAM PORTAL - COMPLETE DATABASE SCHEMA
-- ============================================================
-- Database: maritime_exam_portal
-- Version: 1.0
-- Author: System Architecture
-- Date: 2025-12-01
-- ============================================================

-- Create database (run this first in phpMyAdmin if needed)
-- CREATE DATABASE IF NOT EXISTS maritime_exam_portal
-- CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE maritime_exam_portal;

-- ============================================================
-- TABLE: users
-- Purpose: Store user accounts (students and administrators)
-- ============================================================
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `first_name` VARCHAR(100) NOT NULL,
  `last_name` VARCHAR(100) NOT NULL,
  `role` ENUM('USER', 'ADMIN') NOT NULL DEFAULT 'USER',
  `is_suspended` BOOLEAN NOT NULL DEFAULT FALSE,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_login` TIMESTAMP NULL,

  INDEX `idx_email` (`email`),
  INDEX `idx_role` (`role`),
  INDEX `idx_suspended` (`is_suspended`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: categories
-- Purpose: Store exam categories/functions
-- ============================================================
CREATE TABLE IF NOT EXISTS `categories` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name_bg` VARCHAR(255) NOT NULL,
  `name_en` VARCHAR(255) NOT NULL,
  `price` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
  `duration_days` INT UNSIGNED NOT NULL DEFAULT 365,
  `question_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `exam_duration_minutes` INT UNSIGNED NOT NULL DEFAULT 60,
  `is_active` BOOLEAN NOT NULL DEFAULT TRUE,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  INDEX `idx_active` (`is_active`),
  FULLTEXT `idx_search` (`name_bg`, `name_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: packages
-- Purpose: Store bundled category packages with discounted pricing
-- ============================================================
CREATE TABLE IF NOT EXISTS `packages` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name_bg` VARCHAR(255) NOT NULL,
  `name_en` VARCHAR(255) NOT NULL,
  `description_bg` TEXT,
  `description_en` TEXT,
  `price` DECIMAL(10, 2) NOT NULL,
  `duration_days` INT UNSIGNED NOT NULL DEFAULT 365,
  `is_active` BOOLEAN NOT NULL DEFAULT TRUE,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: package_categories
-- Purpose: Many-to-many relationship between packages and categories
-- ============================================================
CREATE TABLE IF NOT EXISTS `package_categories` (
  `package_id` INT UNSIGNED NOT NULL,
  `category_id` INT UNSIGNED NOT NULL,

  PRIMARY KEY (`package_id`, `category_id`),
  FOREIGN KEY (`package_id`) REFERENCES `packages`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: questions
-- Purpose: Store exam questions for each category
-- ============================================================
CREATE TABLE IF NOT EXISTS `questions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `category_id` INT UNSIGNED NOT NULL,
  `original_index` INT UNSIGNED NOT NULL,
  `question_text` TEXT NOT NULL,
  `option_a` TEXT NOT NULL,
  `option_b` TEXT NOT NULL,
  `option_c` TEXT NOT NULL,
  `option_d` TEXT NOT NULL,
  `correct_answer` ENUM('A', 'B', 'C', 'D') NOT NULL,
  `image_filename` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE,
  INDEX `idx_category` (`category_id`),
  INDEX `idx_original_index` (`category_id`, `original_index`),
  FULLTEXT `idx_question_text` (`question_text`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: access_requests
-- Purpose: Track user requests for category access
-- ============================================================
CREATE TABLE IF NOT EXISTS `access_requests` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `category_id` INT UNSIGNED NULL,
  `package_id` INT UNSIGNED NULL,
  `status` ENUM('PENDING', 'APPROVED', 'REJECTED') NOT NULL DEFAULT 'PENDING',
  `requested_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `processed_at` TIMESTAMP NULL,
  `processed_by` INT UNSIGNED NULL,
  `notes` TEXT NULL,

  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`package_id`) REFERENCES `packages`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`processed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_user` (`user_id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_requested_at` (`requested_at`),

  CONSTRAINT `chk_request_type` CHECK (
    (`category_id` IS NOT NULL AND `package_id` IS NULL) OR
    (`category_id` IS NULL AND `package_id` IS NOT NULL)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: user_categories
-- Purpose: Track which categories users have access to and when they expire
-- ============================================================
CREATE TABLE IF NOT EXISTS `user_categories` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `category_id` INT UNSIGNED NOT NULL,
  `granted_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` TIMESTAMP NOT NULL,
  `granted_by` INT UNSIGNED NULL,

  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`granted_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  UNIQUE KEY `unique_user_category` (`user_id`, `category_id`),
  INDEX `idx_user` (`user_id`),
  INDEX `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: test_sessions
-- Purpose: Store test attempts and results
-- ============================================================
CREATE TABLE IF NOT EXISTS `test_sessions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `category_id` INT UNSIGNED NOT NULL,
  `start_time` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `end_time` TIMESTAMP NULL,
  `duration_seconds` INT UNSIGNED NULL,
  `score` INT UNSIGNED NOT NULL DEFAULT 0,
  `total_questions` INT UNSIGNED NOT NULL DEFAULT 60,
  `percentage` DECIMAL(5, 2) NULL,
  `grade` VARCHAR(20) NULL,
  `is_completed` BOOLEAN NOT NULL DEFAULT FALSE,
  `questions_data` JSON NULL COMMENT 'Stores the 60 selected question IDs in order',

  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE,
  INDEX `idx_user` (`user_id`),
  INDEX `idx_category` (`category_id`),
  INDEX `idx_completed` (`is_completed`),
  INDEX `idx_start_time` (`start_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: test_answers
-- Purpose: Store individual answers for each test session
-- ============================================================
CREATE TABLE IF NOT EXISTS `test_answers` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `session_id` INT UNSIGNED NOT NULL,
  `question_id` INT UNSIGNED NOT NULL,
  `selected_answer` ENUM('A', 'B', 'C', 'D') NULL,
  `is_correct` BOOLEAN NULL,
  `answered_at` TIMESTAMP NULL,

  FOREIGN KEY (`session_id`) REFERENCES `test_sessions`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`question_id`) REFERENCES `questions`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_session_question` (`session_id`, `question_id`),
  INDEX `idx_session` (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: settings
-- Purpose: Store global application settings
-- ============================================================
CREATE TABLE IF NOT EXISTS `settings` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `setting_key` VARCHAR(100) NOT NULL UNIQUE,
  `setting_value` TEXT NULL,
  `description` VARCHAR(255) NULL,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  INDEX `idx_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: audit_log (Optional but recommended)
-- Purpose: Track important admin actions for security
-- ============================================================
CREATE TABLE IF NOT EXISTS `audit_log` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NULL,
  `action` VARCHAR(100) NOT NULL,
  `entity_type` VARCHAR(50) NULL,
  `entity_id` INT UNSIGNED NULL,
  `details` JSON NULL,
  `ip_address` VARCHAR(45) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_user` (`user_id`),
  INDEX `idx_action` (`action`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- INSERT DEFAULT DATA
-- ============================================================

-- Default admin user (password: admin123 - CHANGE THIS IMMEDIATELY!)
-- Password hash is bcrypt hash of 'admin123'
INSERT INTO `users` (`email`, `password_hash`, `first_name`, `last_name`, `role`) VALUES
('admin@maritime.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin', 'User', 'ADMIN');

-- Default settings
INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) VALUES
('revolut_payment_link', '', 'Revolut payment link for user payments'),
('facebook_link', '', 'Facebook page link'),
('announcement_text', '', 'Global announcement banner text'),
('site_name_bg', '>@A:8 7?8B5= >@B0;', 'Site name in Bulgarian'),
('site_name_en', 'Maritime Exam Portal', 'Site name in English'),
('max_test_attempts', '0', 'Maximum test attempts per category (0 = unlimited)'),
('passing_score', '50', 'Minimum percentage to pass an exam');

-- ============================================================
-- SAMPLE CATEGORIES (Based on your list)
-- ============================================================
INSERT INTO `categories` (`name_bg`, `name_en`, `price`, `duration_days`, `exam_duration_minutes`) VALUES
('02830F8O  ?5@0B82=> =82>', 'Navigation  Operational level', 25.00, 365, 60),
('1@01>B:0 =0 B>20@8  ?5@0B82=> =82>', 'Cargo handling  Operational level', 25.00, 365, 60),
('@868 70 ;8F0 =0 1>@40  ?5@0B82=> =82>', 'Care for persons on board  Operational level', 25.00, 365, 60),
('!!  ?5@0B82=> 8 #?@02;5=A:> =82>', 'COLREG  Operational and Management level', 30.00, 365, 60),
('02830F8O  #?@02;5=A:> =82>', 'Navigation  Management level', 30.00, 365, 60),
('1@01>B:0 =0 B>20@8  #?@02;5=A:> =82>', 'Cargo handling  Management level', 30.00, 365, 60),
('@868 70 ;8F0 =0 1>@40  #?@02;5=A:> =82>', 'Care for persons on board  Management level', 30.00, 365, 60),
('#?@02;5=85 =0 :>@010  ?5@0B82=> =82>', 'Ship operation control  Operational level', 25.00, 365, 60),
('>@01=> 8=65=5@AB2>  ?5@0B82=> =82>', 'Marine Engineering  Operational level', 25.00, 365, 60),
(';5:B@8G5A:> >1>@C420=5  ?5@0B82=> =82>', 'Electrical equipment and control systems  Operational level', 25.00, 365, 60),
('>44@J6:0 8 @5<>=B  ?5@0B82=> =82>', 'Maintenance and Repair  Operational level', 25.00, 365, 60),
('>40G =0 :>@01 4> 40 "', 'Ship operator up to 40 GT', 35.00, 365, 60),
('>40G =0 :>@01 4> 40 "  =0 @CA:8', 'Ship operator up to 40 GT  in Russian', 35.00, 365, 60),
(';5:B@><5E0=8:  1>@C420=5  ?5@0B82=> =82>', 'Electrical mechanic  Equipment  Operational level', 25.00, 365, 60),
(';5:B@><5E0=8:  >44@J6:0 8 @5<>=B  ?5@0B82=> =82>', 'Electrical mechanic  Maintenance and Repair  Operational level', 25.00, 365, 60),
(';5:B@><5E0=8:  #?@02;5=85 =0 :>@010  ?5@0B82=> =82>', 'Electrical mechanic  Ship operation control  Operational level', 25.00, 365, 60),
('@868 70 ;8F0 =0 1>@40  ?5@0B82=> =82> (AB0@)', 'Care for persons on board  Operational level (old)', 20.00, 365, 60),
('02830F8O  ?5@0B82=> =82> (AB0@)', 'Navigation  Operational level (old)', 20.00, 365, 60),
('1@01>B:0 =0 B>20@8  ?5@0B82=> =82> (AB0@)', 'Cargo handling  Operational level (old)', 20.00, 365, 60);

-- ============================================================
-- SAMPLE PACKAGE
-- ============================================================
INSERT INTO `packages` (`name_bg`, `name_en`, `description_bg`, `description_en`, `price`, `duration_days`) VALUES
('J;5= ?0:5B  ?5@0B82=> =82>', 'Full Package  Operational Level',
 '>ABJ? 4> 2A8G:8 DC=:F88 =0 >?5@0B82=> =82>',
 'Access to all operational level functions',
 150.00, 365);

-- Link categories 1-3 to the package
INSERT INTO `package_categories` (`package_id`, `category_id`) VALUES
(1, 1), (1, 2), (1, 3);

-- ============================================================
-- USEFUL VIEWS (Optional but helpful for reporting)
-- ============================================================

-- View: Active user categories with expiration info
CREATE OR REPLACE VIEW `v_user_active_categories` AS
SELECT
  uc.user_id,
  u.email,
  u.first_name,
  u.last_name,
  uc.category_id,
  c.name_en AS category_name,
  uc.granted_at,
  uc.expires_at,
  CASE
    WHEN uc.expires_at > NOW() THEN 'ACTIVE'
    ELSE 'EXPIRED'
  END AS status,
  DATEDIFF(uc.expires_at, NOW()) AS days_remaining
FROM user_categories uc
JOIN users u ON uc.user_id = u.id
JOIN categories c ON uc.category_id = c.id;

-- View: Test statistics by user
CREATE OR REPLACE VIEW `v_user_test_stats` AS
SELECT
  ts.user_id,
  u.email,
  u.first_name,
  u.last_name,
  ts.category_id,
  c.name_en AS category_name,
  COUNT(*) AS total_attempts,
  AVG(ts.percentage) AS avg_percentage,
  MAX(ts.percentage) AS best_percentage,
  SUM(CASE WHEN ts.percentage >= 50 THEN 1 ELSE 0 END) AS passed_attempts
FROM test_sessions ts
JOIN users u ON ts.user_id = u.id
JOIN categories c ON ts.category_id = c.id
WHERE ts.is_completed = TRUE
GROUP BY ts.user_id, ts.category_id;

-- View: Pending access requests with details
CREATE OR REPLACE VIEW `v_pending_requests` AS
SELECT
  ar.id,
  ar.user_id,
  u.email,
  u.first_name,
  u.last_name,
  ar.category_id,
  c.name_bg AS category_name_bg,
  c.name_en AS category_name_en,
  c.price AS category_price,
  ar.package_id,
  p.name_bg AS package_name_bg,
  p.name_en AS package_name_en,
  p.price AS package_price,
  ar.requested_at,
  ar.notes
FROM access_requests ar
JOIN users u ON ar.user_id = u.id
LEFT JOIN categories c ON ar.category_id = c.id
LEFT JOIN packages p ON ar.package_id = p.id
WHERE ar.status = 'PENDING'
ORDER BY ar.requested_at ASC;

-- ============================================================
-- END OF SCHEMA
-- ============================================================
