-- =============================================================
-- User Profile & KYC Extensions Schema
-- =============================================================

-- 1. Extend Users Table with KYC & Verification Attributes
ALTER TABLE `users`
ADD COLUMN IF NOT EXISTS `username` VARCHAR(50) NULL UNIQUE AFTER `name`,
ADD COLUMN IF NOT EXISTS `shipping_address` TEXT NULL AFTER `bio`,
ADD COLUMN IF NOT EXISTS `cnic_tax_id` VARCHAR(50) NULL AFTER `shipping_address`,
ADD COLUMN IF NOT EXISTS `kyc_tier` ENUM('unverified', 'verified_bidder', 'vip_buyer') NOT NULL DEFAULT 'verified_bidder' AFTER `role`,
ADD COLUMN IF NOT EXISTS `kyc_verified_at` TIMESTAMP NULL DEFAULT NULL AFTER `kyc_tier`;

-- 2. User Sessions Table (For Multi-Device Session Management)
CREATE TABLE IF NOT EXISTS `user_sessions` (
    `id` VARCHAR(128) NOT NULL,
    `user_id` INT(11) NOT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `user_agent` TEXT NOT NULL,
    `payload` LONGTEXT NOT NULL,
    `last_activity` INT(11) NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_user_sessions_user_id` (`user_id`),
    CONSTRAINT `fk_user_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. KYC Document Verifications Table (Audit Trail)
CREATE TABLE IF NOT EXISTS `user_kyc_verifications` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) NOT NULL,
    `document_type` ENUM('CNIC_FRONT', 'CNIC_BACK', 'PASSPORT', 'NTN_CERTIFICATE') NOT NULL,
    `document_path` VARCHAR(255) NOT NULL,
    `status` ENUM('PENDING', 'APPROVED', 'REJECTED') NOT NULL DEFAULT 'PENDING',
    `rejection_reason` VARCHAR(255) DEFAULT NULL,
    `verified_by` INT(11) DEFAULT NULL,
    `verified_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_kyc_user` (`user_id`),
    CONSTRAINT `fk_kyc_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
