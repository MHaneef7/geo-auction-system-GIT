-- ============================================================
-- Geo Auction System - Manual Payment System Migration
-- File: database/migrations/2026_10_manual_payments.sql
-- ============================================================

USE geo_auction_db;

-- 1. Payment Methods Table
CREATE TABLE IF NOT EXISTS `payment_methods` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `method_code` VARCHAR(50) NOT NULL UNIQUE,
    `method_name` VARCHAR(100) NOT NULL,
    `provider_name` VARCHAR(100) NOT NULL,
    `account_title` VARCHAR(150) NOT NULL,
    `account_number` VARCHAR(100) NOT NULL,
    `instructions` TEXT NULL,
    `is_enabled` TINYINT(1) NOT NULL DEFAULT 1,
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_pm_enabled` (`is_enabled`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Payment Submissions Table
CREATE TABLE IF NOT EXISTS `payment_submissions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` BIGINT UNSIGNED NULL,
    `item_id` INT NOT NULL,
    `buyer_id` INT NOT NULL,
    `seller_id` INT NOT NULL,
    `payment_method_id` INT NOT NULL,
    `payment_method_code` VARCHAR(50) NOT NULL,
    `amount_due` DECIMAL(12,2) NOT NULL,
    `amount_submitted` DECIMAL(12,2) NOT NULL,
    `reference_number` VARCHAR(100) NOT NULL,
    `payment_date` DATE NOT NULL,
    `sender_account_info` VARCHAR(150) NULL,
    `receipt_image` VARCHAR(255) NULL,
    `buyer_notes` TEXT NULL,
    `status` ENUM('Pending', 'Under Review', 'Verified', 'Rejected', 'Failed', 'Refunded') NOT NULL DEFAULT 'Pending',
    `admin_notes` TEXT NULL,
    `reviewed_by` INT NULL,
    `reviewed_at` DATETIME NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_method_ref` (`payment_method_id`, `reference_number`),
    INDEX `idx_sub_buyer` (`buyer_id`, `status`),
    INDEX `idx_sub_item` (`item_id`),
    INDEX `idx_sub_status` (`status`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Seed Default Pakistani Manual Payment Methods
INSERT INTO `payment_methods` (`method_code`, `method_name`, `provider_name`, `account_title`, `account_number`, `instructions`, `is_enabled`, `sort_order`)
VALUES 
('bank_transfer', 'Bank Alfalah Online Transfer', 'Bank Alfalah Ltd', 'Geo Auction Systems (Pvt) Ltd', 'PK36ALFH0012345678901234', 'Please transfer the exact payable amount to our Bank Alfalah corporate account. Retain the 6-12 digit Transaction Reference Number (TID) from your banking app receipt and attach it during submission.', 1, 1),
('jazzcash', 'JazzCash Mobile Account', 'JazzCash / Mobilink Microfinance Bank', 'Geo Auction Systems', '03001234567', 'Send money via JazzCash to the mobile account number above. Enter your TID (Transaction ID) from the 8558 SMS in the reference number field.', 1, 2),
('easypaisa', 'Easypaisa Mobile Account', 'Easypaisa / Telenor Microfinance Bank', 'Geo Auction Systems', '03451234567', 'Send money via Easypaisa App or *786# to the registered account number above. Provide the 11-digit TRX ID received from 3737 in the payment reference box.', 1, 3)
ON DUPLICATE KEY UPDATE `method_name` = VALUES(`method_name`), `instructions` = VALUES(`instructions`);
