-- =============================================================
-- Enterprise Wallet Subsystem Schema (Geo Auction Platform)
-- Precision: DECIMAL(18, 4) for zero floating-point rounding errors
-- =============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Wallets Table
CREATE TABLE IF NOT EXISTS `wallets` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) NOT NULL,
    `currency` VARCHAR(3) NOT NULL DEFAULT 'PKR',
    `available_balance` DECIMAL(18, 4) NOT NULL DEFAULT 0.0000,
    `locked_balance` DECIMAL(18, 4) NOT NULL DEFAULT 0.0000,
    `version` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `status` ENUM('ACTIVE', 'SUSPENDED', 'FROZEN') NOT NULL DEFAULT 'ACTIVE',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_wallet_user_currency` (`user_id`, `currency`),
    KEY `idx_wallet_status` (`status`),
    CONSTRAINT `chk_available_non_neg` CHECK (`available_balance` >= 0.0000),
    CONSTRAINT `chk_locked_non_neg` CHECK (`locked_balance` >= 0.0000),
    CONSTRAINT `fk_wallets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Wallet Locks Table (Bid Guarantees on Live Auctions)
CREATE TABLE IF NOT EXISTS `wallet_locks` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `lock_id` VARCHAR(64) NOT NULL,
    `wallet_id` BIGINT UNSIGNED NOT NULL,
    `auction_id` INT(11) NOT NULL,
    `amount` DECIMAL(18, 4) NOT NULL,
    `status` ENUM('ACTIVE', 'RELEASED', 'SETTLED', 'EXPIRED') NOT NULL DEFAULT 'ACTIVE',
    `expires_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_lock_id` (`lock_id`),
    KEY `idx_active_wallet_auction` (`wallet_id`, `auction_id`, `status`),
    KEY `idx_auction_status` (`auction_id`, `status`),
    CONSTRAINT `fk_locks_wallet` FOREIGN KEY (`wallet_id`) REFERENCES `wallets` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `chk_lock_amount_pos` CHECK (`amount` > 0.0000)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Wallet Transactions Table (Double-Entry Ledger)
CREATE TABLE IF NOT EXISTS `wallet_transactions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `transaction_id` VARCHAR(64) NOT NULL,
    `wallet_id` BIGINT UNSIGNED NOT NULL,
    `type` ENUM(
        'DEPOSIT',
        'WITHDRAWAL_PENDING',
        'WITHDRAWAL_COMPLETED',
        'WITHDRAWAL_REJECTED',
        'BID_HOLD',
        'BID_RELEASE',
        'BID_WON',
        'AUCTION_PAYOUT',
        'PLATFORM_FEE',
        'ADJUSTMENT'
    ) NOT NULL,
    `amount` DECIMAL(18, 4) NOT NULL,
    `available_balance_before` DECIMAL(18, 4) NOT NULL,
    `available_balance_after` DECIMAL(18, 4) NOT NULL,
    `locked_balance_before` DECIMAL(18, 4) NOT NULL,
    `locked_balance_after` DECIMAL(18, 4) NOT NULL,
    `reference_id` VARCHAR(128) NOT NULL,
    `reference_type` ENUM('AUCTION', 'PAYMENT_GATEWAY', 'PAYOUT', 'SYSTEM') NOT NULL,
    `idempotency_key` VARCHAR(128) DEFAULT NULL,
    `metadata` JSON DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_tx_transaction_id` (`transaction_id`),
    UNIQUE KEY `uk_idempotency_wallet` (`wallet_id`, `idempotency_key`),
    KEY `idx_tx_wallet_created` (`wallet_id`, `created_at` DESC),
    KEY `idx_tx_reference` (`reference_type`, `reference_id`),
    CONSTRAINT `fk_tx_wallet` FOREIGN KEY (`wallet_id`) REFERENCES `wallets` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Idempotency Tracking Table
CREATE TABLE IF NOT EXISTS `idempotency_records` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `key` VARCHAR(128) NOT NULL,
    `scope` VARCHAR(64) NOT NULL,
    `request_hash` VARCHAR(64) NOT NULL,
    `response_body` JSON NOT NULL,
    `status_code` INT NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_scope_key` (`scope`, `key`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed / Migrate existing user balances into wallets table
INSERT INTO `wallets` (`user_id`, `currency`, `available_balance`, `locked_balance`, `status`)
SELECT `id`, 'PKR', COALESCE(`balance`, 0.0000), 0.0000, 'ACTIVE'
FROM `users`
ON DUPLICATE KEY UPDATE `available_balance` = VALUES(`available_balance`);

SET FOREIGN_KEY_CHECKS = 1;
