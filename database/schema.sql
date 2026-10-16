-- =====================================================================
-- CivicLens — Report. Track. Resolve.
-- Full MySQL Database Schema
-- Database: civiclens_db
-- =====================================================================

CREATE DATABASE IF NOT EXISTS `civiclens_db` 
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `civiclens_db`;

-- ---------------------------------------------------------------------
-- 1. Citizens Table
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `citizens` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `phone` VARCHAR(20) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `address` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_citizens_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2. Administrators Table
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `administrators` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `name` VARCHAR(100) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('superadmin', 'admin') DEFAULT 'admin',
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_admin_username` (`username`),
    INDEX `idx_admin_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3. Authorities Table
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `authorities` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `department` VARCHAR(100) NOT NULL,
    `official_name` VARCHAR(100) NOT NULL,
    `designation` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `phone` VARCHAR(20) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `jurisdiction` VARCHAR(150) NOT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_authorities_email` (`email`),
    INDEX `idx_authorities_department` (`department`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4. Authority Subscriptions Table (₹5,000 / year)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `authority_subscriptions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `authority_id` INT NOT NULL,
    `plan_name` VARCHAR(100) DEFAULT 'Annual Official Access',
    `amount` DECIMAL(10,2) NOT NULL DEFAULT 5000.00,
    `payment_status` ENUM('pending', 'active', 'expired', 'cancelled') DEFAULT 'pending',
    `payment_method` VARCHAR(50) DEFAULT 'Razorpay',
    `transaction_reference` VARCHAR(100) NULL,
    `start_date` DATETIME NULL,
    `end_date` DATETIME NULL,
    `verified_by_admin` TINYINT(1) DEFAULT 0,
    `admin_notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`authority_id`) REFERENCES `authorities`(`id`) ON DELETE CASCADE,
    INDEX `idx_auth_sub_status` (`payment_status`, `end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 5. Complaints Table
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `complaints` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `complaint_id` VARCHAR(30) NOT NULL UNIQUE,
    `citizen_id` INT NULL,
    `is_anonymous` TINYINT(1) DEFAULT 0,
    `anonymous_tracking_hash` VARCHAR(255) NULL,
    `payment_id` VARCHAR(100) NULL,
    `category` ENUM(
        'Water', 
        'Road', 
        'Food', 
        'Waste', 
        'Drugs / Substance Abuse (Thoofan)', 
        'Other Issues'
    ) NOT NULL,
    `title` VARCHAR(200) NOT NULL,
    `description` TEXT NOT NULL,
    `location` VARCHAR(255) NOT NULL,
    `photo_path` VARCHAR(255) NULL,
    `status` ENUM('Opened', 'In Progress', 'Solved', 'Reopened', 'Closed') DEFAULT 'Opened',
    `priority` ENUM('Low', 'Medium', 'High', 'Urgent') DEFAULT 'Medium',
    `assigned_authority_id` INT NULL,
    `assigned_at` DATETIME NULL,
    `resolution_note` TEXT NULL,
    `resolved_at` DATETIME NULL,
    `feedback_deadline` DATETIME NULL,
    `satisfaction_status` ENUM('Pending', 'Satisfied', 'Not Satisfied', 'No Response') DEFAULT 'Pending',
    `closure_reason` VARCHAR(100) NULL,
    `feedback_text` TEXT NULL,
    `feedback_submitted_at` DATETIME NULL,
    `reopen_count` INT DEFAULT 0,
    `closed_at` DATETIME NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`citizen_id`) REFERENCES `citizens`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`assigned_authority_id`) REFERENCES `authorities`(`id`) ON DELETE SET NULL,
    INDEX `idx_complaints_id` (`complaint_id`),
    INDEX `idx_complaints_status` (`status`),
    INDEX `idx_complaints_category` (`category`),
    INDEX `idx_complaints_citizen` (`citizen_id`),
    INDEX `idx_complaints_authority` (`assigned_authority_id`),
    INDEX `idx_complaints_deadline` (`status`, `feedback_deadline`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 6. Complaint Status History Table
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `complaint_status_history` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `complaint_id` INT NOT NULL,
    `from_status` VARCHAR(50) NULL,
    `to_status` VARCHAR(50) NOT NULL,
    `actor_type` ENUM('citizen', 'anonymous_user', 'authority', 'admin', 'system_cron') NOT NULL,
    `actor_id` INT NULL,
    `actor_name` VARCHAR(100) NOT NULL,
    `note` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`complaint_id`) REFERENCES `complaints`(`id`) ON DELETE CASCADE,
    INDEX `idx_history_complaint` (`complaint_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 7. Notifications Table
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `recipient_type` ENUM('citizen', 'authority', 'admin') NOT NULL,
    `recipient_id` INT NOT NULL,
    `complaint_id` INT NULL,
    `title` VARCHAR(200) NOT NULL,
    `message` TEXT NOT NULL,
    `event_type` VARCHAR(50) NOT NULL,
    `is_read` TINYINT(1) DEFAULT 0,
    `delivery_channel` ENUM('in_app', 'email', 'sms') DEFAULT 'in_app',
    `delivered_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`complaint_id`) REFERENCES `complaints`(`id`) ON DELETE CASCADE,
    INDEX `idx_notifications_user` (`recipient_type`, `recipient_id`, `is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 8. Payment Orders & Transactions Table
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `payment_orders` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_reference` VARCHAR(100) NOT NULL UNIQUE,
    `razorpay_order_id` VARCHAR(100) NULL,
    `razorpay_payment_id` VARCHAR(100) NULL UNIQUE,
    `purpose` ENUM('anonymous_complaint', 'authority_subscription', 'advertising_package') NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `currency` VARCHAR(10) DEFAULT 'INR',
    `payer_email` VARCHAR(150) NULL,
    `payer_phone` VARCHAR(20) NULL,
    `status` ENUM('created', 'paid', 'failed', 'refunded') DEFAULT 'created',
    `signature_verified` TINYINT(1) DEFAULT 0,
    `related_entity_id` INT NULL,
    `idempotency_key` VARCHAR(100) NOT NULL UNIQUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `verified_at` DATETIME NULL,
    INDEX `idx_payment_order_ref` (`order_reference`),
    INDEX `idx_payment_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 9. Businesses Table
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `businesses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `business_name` VARCHAR(150) NOT NULL,
    `owner_name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `phone` VARCHAR(20) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `registration_number` VARCHAR(100) NULL,
    `category` VARCHAR(100) NOT NULL,
    `address` TEXT NOT NULL,
    `city` VARCHAR(100) NOT NULL,
    `website_url` VARCHAR(255) NULL,
    `is_verified` TINYINT(1) DEFAULT 0,
    `verification_notes` TEXT NULL,
    `verified_at` DATETIME NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_business_email` (`email`),
    INDEX `idx_business_verified` (`is_verified`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 10. Advertising Packages Table
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `advertising_packages` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `duration_days` INT NOT NULL,
    `price` DECIMAL(10,2) NOT NULL,
    `description` TEXT NOT NULL,
    `impressions_estimate` INT DEFAULT 5000,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 11. Advertising Campaigns Table
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `advertising_campaigns` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `business_id` INT NOT NULL,
    `package_id` INT NULL,
    `title` VARCHAR(150) NOT NULL,
    `description` TEXT NOT NULL,
    `service_area` VARCHAR(150) NOT NULL,
    `banner_image_path` VARCHAR(255) NULL,
    `target_url` VARCHAR(255) NULL,
    `status` ENUM('draft', 'pending_approval', 'approved', 'rejected', 'active', 'paused', 'expired') DEFAULT 'pending_approval',
    `rejection_reason` TEXT NULL,
    `start_date` DATETIME NULL,
    `end_date` DATETIME NULL,
    `paid_status` ENUM('unpaid', 'paid') DEFAULT 'unpaid',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`package_id`) REFERENCES `advertising_packages`(`id`) ON DELETE SET NULL,
    INDEX `idx_ad_status_dates` (`status`, `start_date`, `end_date`),
    INDEX `idx_ad_location` (`service_area`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 12. Advertisement Analytics Table (Impressions and Clicks)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `advertisement_analytics` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `campaign_id` INT NOT NULL,
    `event_type` ENUM('impression', 'click') NOT NULL,
    `user_ip_hash` VARCHAR(64) NULL,
    `user_agent` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`campaign_id`) REFERENCES `advertising_campaigns`(`id`) ON DELETE CASCADE,
    INDEX `idx_ad_analytics_event` (`campaign_id`, `event_type`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 13. Rate Limiting Table
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `login_attempts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `ip_address` VARCHAR(45) NOT NULL,
    `identifier` VARCHAR(150) NOT NULL,
    `attempted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_login_ip_time` (`ip_address`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
