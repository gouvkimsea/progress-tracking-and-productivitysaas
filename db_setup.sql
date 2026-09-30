-- Mindrift Progress Tracking System Database Schema
-- Database Name: mindrift_db
-- Highly Optimized Schema with Indexes for SaaS Scalability (100 -> 100,000 Users)

CREATE DATABASE IF NOT EXISTS `mindrift_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `mindrift_db`;

-- 1. Users Table
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `password` VARCHAR(255) NULL,
  `avatar_url` VARCHAR(255) NULL,
  `schedule_token` VARCHAR(64) NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_users_schedule_token` (`schedule_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. User Stats Summary Table (1:1 with User)
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
  UNIQUE KEY `idx_stats_user` (`user_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Courses / Projects Table
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
  `is_starred` INT DEFAULT 0,
  `status` VARCHAR(30) DEFAULT 'No status',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_courses_user` (`user_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Daily Activity Log Table (Heatmap & Time Tracking)
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

-- 6. User Skills Table (Radar Breakdown)
CREATE TABLE IF NOT EXISTS `user_skills` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `label` VARCHAR(50) NOT NULL,
  `score_pct` DECIMAL(3,2) NOT NULL DEFAULT 0.50,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_user_skills_user` (`user_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Group Assignments Table
CREATE TABLE IF NOT EXISTS `group_assignments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `created_by_user_id` INT NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `course_name` VARCHAR(150) NOT NULL,
  `due_date` VARCHAR(50) NOT NULL,
  `description` TEXT NULL,
  `task_planning` TEXT NULL,
  `status` VARCHAR(30) DEFAULT 'In Progress',
  `completed_by_user_id` INT NULL,
  `completed_by_user_name` VARCHAR(100) NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_assignments_creator` (`created_by_user_id`),
  INDEX `idx_assignments_status` (`status`, `id`),
  FOREIGN KEY (`created_by_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Daily Reflection Journal Table
CREATE TABLE IF NOT EXISTS `daily_journal` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `entry_date` DATE NOT NULL,
  `rating` INT NOT NULL DEFAULT 3,
  `mood_label` VARCHAR(50) DEFAULT 'Okay',
  `accomplishments` TEXT NULL,
  `challenges` TEXT NULL,
  `learning_notes` TEXT NULL,
  `journal_text` TEXT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `user_date_unique` (`user_id`, `entry_date`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. Daily Journal To-Dos Table
CREATE TABLE IF NOT EXISTS `journal_todos` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `task_text` VARCHAR(255) NOT NULL,
  `is_completed` TINYINT(1) DEFAULT 0,
  `todo_date` DATE NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `completed_at` DATETIME NULL,
  INDEX `idx_todos_user_date` (`user_id`, `todo_date`, `is_completed`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 10. Tasks Table (Kanban, Calendar, Workload, Gantt)
CREATE TABLE IF NOT EXISTS `tasks` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `task_name` VARCHAR(200) NOT NULL,
  `project_name` VARCHAR(150) NOT NULL,
  `start_date` VARCHAR(50) NOT NULL,
  `due_date` VARCHAR(50) NULL,
  `priority` VARCHAR(20) DEFAULT 'Medium',
  `assigned_to` VARCHAR(100) DEFAULT 'unassigned',
  `status` VARCHAR(30) DEFAULT 'Open',
  `time_log` INT DEFAULT 0,
  `description` TEXT NULL,
  `deleted_at` DATETIME NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_tasks_user_status` (`user_id`, `deleted_at`, `status`),
  INDEX `idx_tasks_user_due` (`user_id`, `due_date`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 11. Milestones Table (Gantt & Project Deadlines)
CREATE TABLE IF NOT EXISTS `milestones` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `project_id` INT NOT NULL,
  `name` VARCHAR(200) NOT NULL,
  `due_date` VARCHAR(50) NOT NULL,
  `status` VARCHAR(30) DEFAULT 'Pending',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_milestones_proj` (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 12. Task Dependencies Table (Gantt Chart Precedence)
CREATE TABLE IF NOT EXISTS `task_dependencies` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `task_id` INT NOT NULL,
  `depends_on_task_id` INT NOT NULL,
  `dependency_type` VARCHAR(50) DEFAULT 'finish_to_start',
  INDEX `idx_task_deps_task` (`task_id`),
  INDEX `idx_task_deps_depends` (`depends_on_task_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 13. Task Checklists Table (Subtasks)
CREATE TABLE IF NOT EXISTS `task_checklists` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `task_id` INT NOT NULL,
  `item_text` VARCHAR(255) NOT NULL,
  `is_completed` TINYINT(1) DEFAULT 0,
  INDEX `idx_checklists_task` (`task_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 14. Files Table (Attachment & Document Management)
CREATE TABLE IF NOT EXISTS `files` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `project_id` INT NULL,
  `task_id` INT NULL,
  `user_id` INT NOT NULL,
  `file_name` VARCHAR(255) NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `file_size` INT DEFAULT 0,
  `uploaded_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_files_user` (`user_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 15. Notifications Table
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `message` TEXT NOT NULL,
  `is_read` TINYINT(1) DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_notif_user_read` (`user_id`, `is_read`, `id`),
  INDEX `idx_notif_user_title` (`user_id`, `title`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 16. Activity Logs Table (Audit Trail)
CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `action_type` VARCHAR(100) NOT NULL,
  `description` TEXT NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_activity_user` (`user_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 17. Flashcards Table (Spaced Repetition System)
CREATE TABLE IF NOT EXISTS `flashcards` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `course_name` VARCHAR(100) NOT NULL,
  `question` TEXT NOT NULL,
  `answer` TEXT NOT NULL,
  `interval_days` INT DEFAULT 1,
  `ease_factor` DECIMAL(3,2) DEFAULT 2.50,
  `due_date` DATE NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_flashcards_user_due` (`user_id`, `due_date`),
  INDEX `idx_flashcards_user_course` (`user_id`, `course_name`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 18. Project Goals / OKRs Table
CREATE TABLE IF NOT EXISTS `project_goals` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `title` VARCHAR(150) NOT NULL,
  `category` VARCHAR(50) DEFAULT 'Productivity',
  `target_value` INT DEFAULT 100,
  `current_value` INT DEFAULT 0,
  `unit` VARCHAR(20) DEFAULT '%',
  `due_date` VARCHAR(50) NULL,
  `status` VARCHAR(30) DEFAULT 'On Track',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_goals_user` (`user_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 19. Login Attempts Table (Brute-Force Attack Prevention)
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `ip_address` VARCHAR(45) NOT NULL,
  `email` VARCHAR(190) NOT NULL,
  `attempted_at` INT NOT NULL,
  INDEX `idx_login_ip_time` (`ip_address`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========================================================
-- SEED INITIAL DATA FOR DEFAULT USER
-- ========================================================
INSERT INTO `users` (`id`, `name`, `email`, `password`, `avatar_url`)
VALUES (1, 'Alex Morgan', 'alex@mindrift.io', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL)
ON DUPLICATE KEY UPDATE `name`=`name`;

INSERT INTO `user_stats` (`user_id`, `learning_streak`, `streak_delta`, `longest_streak`, `missed_days`, `inactive_pct`, `course_progress_pct`, `progress_delta_pct`, `weekly_lessons_current`, `weekly_lessons_last`, `study_hours`, `study_minutes`, `study_delta_pct`)
VALUES (1, 67, 2, 1000000, 0, 0, 0.00, 0.00, 0, 0, 0, 0, 0.00)
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
