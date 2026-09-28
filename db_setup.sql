-- Mindrift Progress Tracking System Database Schema
-- Database Name: mindrift_db

CREATE DATABASE IF NOT EXISTS `mindrift_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `mindrift_db`;

-- 1. Users Table
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `avatar_url` VARCHAR(255) NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. User Stats Summary Table
CREATE TABLE IF NOT EXISTS `user_stats` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `learning_streak` INT DEFAULT 0,
  `streak_delta` INT DEFAULT 0,
  `longest_streak` INT DEFAULT 0,
  `missed_days` INT DEFAULT 0,
  `inactive_pct` INT DEFAULT 0,
  `course_progress_pct` DECIMAL(5,2) DEFAULT 0.00,
  `progress_delta_pct` DECIMAL(4,2) DEFAULT 0.00,
  `weekly_lessons_current` INT DEFAULT 0,
  `weekly_lessons_last` INT DEFAULT 0,
  `study_hours` INT DEFAULT 0,
  `study_minutes` INT DEFAULT 0,
  `study_delta_pct` DECIMAL(4,2) DEFAULT 0.00,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Courses Table
CREATE TABLE IF NOT EXISTS `courses` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `code` VARCHAR(10) NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `category` VARCHAR(50) NOT NULL,
  `level` VARCHAR(50) DEFAULT 'Intermediate',
  `total_modules` INT DEFAULT 10,
  `completed_modules` INT DEFAULT 0,
  `progress_pct` INT DEFAULT 0,
  `bg_gradient` VARCHAR(100) DEFAULT 'linear-gradient(145deg,#8B7CF0,#5A46E0)',
  `ring_color` VARCHAR(30) DEFAULT '#6C5CE7',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Daily Activity Log Table (For Heatmap & Sparklines)
CREATE TABLE IF NOT EXISTS `daily_activities` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `activity_date` DATE NOT NULL,
  `lessons_completed` INT DEFAULT 0,
  `study_minutes` INT DEFAULT 0,
  `category` VARCHAR(50) DEFAULT 'General',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `user_date` (`user_id`, `activity_date`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Weekly Streak Table (Mon-Sun Check-in)
CREATE TABLE IF NOT EXISTS `weekly_streaks` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `day_index` INT NOT NULL COMMENT '0=Mon, 1=Tue, 2=Wed, 3=Thu, 4=Fri, 5=Sat, 6=Sun',
  `day_name` VARCHAR(10) NOT NULL,
  `is_completed` TINYINT(1) DEFAULT 0,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `user_day` (`user_id`, `day_index`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. User Skills Table (For Radar Breakdown)
CREATE TABLE IF NOT EXISTS `user_skills` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `label` VARCHAR(50) NOT NULL,
  `score_pct` DECIMAL(3,2) NOT NULL DEFAULT 0.50,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========================================================
-- SEED INITIAL DATA FOR DEFAULT USER
-- ========================================================
INSERT INTO `users` (`id`, `name`, `email`, `avatar_url`)
VALUES (1, 'Alex Morgan', 'alex@mindrift.io', NULL)
ON DUPLICATE KEY UPDATE `name`=`name`;

INSERT INTO `user_stats` (`user_id`, `learning_streak`, `streak_delta`, `longest_streak`, `missed_days`, `inactive_pct`, `course_progress_pct`, `progress_delta_pct`, `weekly_lessons_current`, `weekly_lessons_last`, `study_hours`, `study_minutes`, `study_delta_pct`)
VALUES (1, 0, 0, 0, 0, 0, 0.00, 0.00, 0, 0, 0, 0, 0.00)
ON DUPLICATE KEY UPDATE `learning_streak`=`learning_streak`;

-- Seed Courses
INSERT INTO `courses` (`user_id`, `code`, `name`, `category`, `level`, `total_modules`, `completed_modules`, `progress_pct`, `bg_gradient`, `ring_color`) VALUES
(1, 'UI', 'UI Design Mastery', 'Design', 'Intermediate', 12, 0, 0, 'linear-gradient(145deg,#8B7CF0,#5A46E0)', '#6C5CE7'),
(1, 'JS', 'Learn JavaScript', 'Programming', 'Beginner', 8, 0, 0, 'linear-gradient(145deg,#FBAE68,#F2994A)', '#F2994A'),
(1, 'PS', 'Learn Photoshop', 'Design', 'Advanced', 32, 0, 0, 'linear-gradient(145deg,#6FC1F0,#4FA3E0)', '#4FA3E0'),
(1, 'PY', 'Python for Data', 'Data Science', 'Intermediate', 20, 0, 0, 'linear-gradient(145deg,#7FD9A5,#2FBE73)', '#2FBE73')
ON DUPLICATE KEY UPDATE `name`=`name`;

-- Seed Weekly Streaks
INSERT INTO `weekly_streaks` (`user_id`, `day_index`, `day_name`, `is_completed`) VALUES
(1, 0, 'Mon', 0),
(1, 1, 'Tue', 0),
(1, 2, 'Wed', 0),
(1, 3, 'Thu', 0),
(1, 4, 'Fri', 0),
(1, 5, 'Sat', 0),
(1, 6, 'Sun', 0)
ON DUPLICATE KEY UPDATE `is_completed`=`is_completed`;

-- Seed Skills
INSERT INTO `user_skills` (`user_id`, `label`, `score_pct`) VALUES
(1, 'Productivity', 0.50),
(1, 'Data & Analysis', 0.50),
(1, 'Communication', 0.50),
(1, 'Creativity', 0.50),
(1, 'Technical', 0.50)
ON DUPLICATE KEY UPDATE `score_pct`=`score_pct`;
