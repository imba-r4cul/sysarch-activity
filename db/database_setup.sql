CREATE DATABASE IF NOT EXISTS `student_management`;
USE `student_management`;

CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_number` varchar(50) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) NOT NULL,
  `course` varchar(50) NOT NULL,
  `course_level` int(11) NOT NULL,
  `email` varchar(150) NOT NULL,
  `address` varchar(255) NOT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `earned_points` int(11) DEFAULT 0,
  `tasks_completed` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id_number` (`id_number`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Migration for existing databases:
-- ALTER TABLE `users` ADD COLUMN `profile_image` varchar(255) DEFAULT NULL AFTER `address`;
-- ALTER TABLE `users` ADD COLUMN `earned_points` INT DEFAULT 0, ADD COLUMN `tasks_completed` INT DEFAULT 0;

-- Admin users table (for admin login)
CREATE TABLE IF NOT EXISTS `admin_users` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(100) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `display_name` VARCHAR(150) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `admin_users` (`id`, `username`, `password`, `display_name`) VALUES 
(1, 'admin', '$2y$10$agiy52TxrO0eVGzq6RVUMOA/piGmx.UEiHUyldoVc/ruBxjElrgvi', 'Admin');

-- Sit-in records table
CREATE TABLE IF NOT EXISTS `sit_in_records` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `id_number` VARCHAR(50) NOT NULL,
  `first_name` VARCHAR(100) NOT NULL, 
  `last_name` VARCHAR(100) NOT NULL,
  `purpose` VARCHAR(255) NOT NULL,
  `lab` VARCHAR(100) NOT NULL,
  `pc_number` INT NULL DEFAULT NULL,
  `time_in` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `time_out` TIMESTAMP NULL DEFAULT NULL,
  `status` ENUM('Active', 'Completed') DEFAULT 'Active',
  `feedback` TEXT NULL DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  INDEX `idx_status` (`status`),
  INDEX `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Announcements table
CREATE TABLE IF NOT EXISTS `announcements` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `admin_id` INT NOT NULL,
  `content` TEXT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`admin_id`) REFERENCES `admin_users` (`id`) ON DELETE CASCADE,
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Notification read tracking table (per user, per announcement)
CREATE TABLE IF NOT EXISTS `notification_reads` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `announcement_id` INT NOT NULL,
  `read_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_user_announcement` (`user_id`, `announcement_id`),
  INDEX `idx_notification_reads_user_id` (`user_id`),
  INDEX `idx_notification_reads_announcement_id` (`announcement_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`announcement_id`) REFERENCES `announcements` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Reservations table
CREATE TABLE IF NOT EXISTS `reservations` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `purpose` VARCHAR(255) NOT NULL,
  `lab` VARCHAR(100) NOT NULL,
  `pc_number` INT DEFAULT NULL,
  `reservation_date` DATE NOT NULL,
  `reservation_time` TIME NOT NULL,
  `status` ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
  `admin_note` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- System settings table
CREATE TABLE IF NOT EXISTS `system_settings` (
  `setting_key` VARCHAR(100) NOT NULL,
  `setting_value` VARCHAR(255) NOT NULL,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `system_settings` (`setting_key`, `setting_value`) VALUES ('reservations_enabled', '1');

-- Lab software table
CREATE TABLE IF NOT EXISTS `lab_software` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `lab` VARCHAR(100) NOT NULL,
  `software_name` VARCHAR(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_lab_software` (`lab`, `software_name`),
  INDEX `idx_lab` (`lab`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
