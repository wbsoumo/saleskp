-- ==========================================================
-- Telecaller Reporting & Lead Management CRM - MySQL Database Schema
-- Compatible with MySQL 5.7+, MySQL 8.0+, and MariaDB 10.3+
-- Production Database: helnovexaa_kpsales
-- ==========================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------
-- Table structure for: users
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id` VARCHAR(50) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(120) DEFAULT NULL,
  `phone` VARCHAR(25) DEFAULT NULL,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'employee') NOT NULL DEFAULT 'employee',
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `last_login` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_employee_id` (`employee_id`),
  KEY `idx_role_status` (`role`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- Table structure for: leads
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `leads` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `phone` VARCHAR(30) NOT NULL,
  `company` VARCHAR(150) DEFAULT NULL,
  `email` VARCHAR(120) DEFAULT NULL,
  `city` VARCHAR(100) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `assigned_to` INT UNSIGNED DEFAULT NULL,
  `status` VARCHAR(50) NOT NULL DEFAULT 'New',
  `call_attempts` INT NOT NULL DEFAULT 0,
  `last_called_at` DATETIME DEFAULT NULL,
  `last_call_status` VARCHAR(50) DEFAULT NULL,
  `follow_up_date` DATETIME DEFAULT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_lead_phone` (`phone`),
  KEY `idx_lead_assigned_to` (`assigned_to`),
  KEY `idx_lead_status` (`status`),
  KEY `idx_lead_follow_up` (`follow_up_date`),
  CONSTRAINT `fk_leads_assigned_user` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_leads_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- Table structure for: lead_call_logs
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `lead_call_logs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `lead_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `call_outcome` VARCHAR(50) NOT NULL,
  `call_duration` VARCHAR(30) DEFAULT NULL,
  `follow_up_date` DATETIME DEFAULT NULL,
  `remarks` TEXT DEFAULT NULL,
  `recording_path` VARCHAR(255) DEFAULT NULL,
  `recording_name` VARCHAR(255) DEFAULT NULL,
  `recording_size` BIGINT UNSIGNED DEFAULT 0,
  `recording_mime` VARCHAR(100) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_log_lead_id` (`lead_id`),
  KEY `idx_log_user_id` (`user_id`),
  KEY `idx_log_outcome` (`call_outcome`),
  KEY `idx_log_created_at` (`created_at`),
  CONSTRAINT `fk_call_logs_lead` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_call_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- Table structure for: attendance
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `attendance` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `attendance_date` DATE NOT NULL,
  `status` ENUM('Present', 'Half Day', 'Absent') NOT NULL DEFAULT 'Present',
  `punch_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `punch_ip` VARCHAR(45) DEFAULT NULL,
  `employee_remarks` VARCHAR(255) DEFAULT NULL,
  `is_modified` TINYINT(1) NOT NULL DEFAULT 0,
  `modified_by` INT UNSIGNED DEFAULT NULL,
  `modified_at` DATETIME DEFAULT NULL,
  `admin_remarks` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_attendance_date` (`user_id`, `attendance_date`),
  KEY `idx_attendance_date` (`attendance_date`),
  KEY `idx_attendance_status` (`status`),
  KEY `idx_attendance_user` (`user_id`),
  CONSTRAINT `fk_attendance_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_attendance_modified_by` FOREIGN KEY (`modified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- Table structure for: daily_reports
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `daily_reports` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `report_date` DATE NOT NULL,
  `total_calls` INT NOT NULL DEFAULT 0,
  `call_attempts` INT NOT NULL DEFAULT 0,
  `interested_leads` INT NOT NULL DEFAULT 0,
  `meetings_booked` INT NOT NULL DEFAULT 0,
  `sales_closed` INT NOT NULL DEFAULT 0,
  `notes` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_user_date` (`user_id`, `report_date`),
  KEY `idx_report_date` (`report_date`),
  KEY `idx_user_id` (`user_id`),
  CONSTRAINT `fk_daily_reports_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- Table structure for: call_recordings
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `call_recordings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `report_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `phone_number` VARCHAR(30) NOT NULL,
  `customer_name` VARCHAR(100) DEFAULT NULL,
  `call_status` VARCHAR(50) NOT NULL DEFAULT 'Interested',
  `call_duration` VARCHAR(30) DEFAULT NULL,
  `call_notes` TEXT DEFAULT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `file_name` VARCHAR(255) NOT NULL,
  `original_name` VARCHAR(255) NOT NULL,
  `file_size` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `file_mime` VARCHAR(100) NOT NULL DEFAULT 'audio/mpeg',
  `uploaded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_report_id` (`report_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_phone_number` (`phone_number`),
  KEY `idx_call_status` (`call_status`),
  CONSTRAINT `fk_call_recordings_report` FOREIGN KEY (`report_id`) REFERENCES `daily_reports` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_call_recordings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- Table structure for: system_settings
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS `system_settings` (
  `setting_key` VARCHAR(50) NOT NULL,
  `setting_value` TEXT NOT NULL,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- Seed default settings
-- ----------------------------------------------------------
INSERT INTO `system_settings` (`setting_key`, `setting_value`) VALUES
('company_name', 'TeleConnect Sales CRM'),
('max_upload_size_mb', '25'),
('allowed_extensions', 'mp3,wav,m4a,ogg,aac,amr,webm'),
('timezone', 'Asia/Kolkata')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);

-- ----------------------------------------------------------
-- Seed default Admin and sample Telecaller Employees
-- admin -> Admin@123
-- EMP001 -> telecaller123
-- EMP002 -> telecaller123
-- ----------------------------------------------------------
INSERT INTO `users` (`id`, `employee_id`, `name`, `email`, `phone`, `password`, `role`, `status`, `created_at`) VALUES
(1, 'admin', 'System Administrator', 'admin@telecaller.com', '+91 9876543210', '$2y$12$yWcDldYTUuM65Vb8J6aVQenIunKoky/SNT0zcJ9WG9JeHXwoC1r3e', 'admin', 'active', NOW()),
(2, 'EMP001', 'Rahul Sharma', 'rahul.s@telecaller.com', '+91 9811122233', '$2y$12$AgY9wRDsvCziBNfgDbUcue5QvLJ7Uxtf03Hkp0T2ERk/X4PtfN24K', 'employee', 'active', NOW()),
(3, 'EMP002', 'Priya Patel', 'priya.p@telecaller.com', '+91 9822233344', '$2y$12$AgY9wRDsvCziBNfgDbUcue5QvLJ7Uxtf03Hkp0T2ERk/X4PtfN24K', 'employee', 'active', NOW())
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

SET FOREIGN_KEY_CHECKS = 1;
