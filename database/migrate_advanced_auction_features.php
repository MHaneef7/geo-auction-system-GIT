<?php
/**
 * Migration Script: Advanced Auction Features & Bidding Mechanics
 * File: database/migrate_advanced_auction_features.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/Database.php';

try {
    $pdo = Database::getPdo();
    echo "Running Advanced Auction Features Migration...\n\n";

    // 1. Check and add columns to `items` table
    $existingCols = $pdo->query("DESCRIBE items")->fetchAll(PDO::FETCH_COLUMN);

    $columnsToAdd = [
        'auction_type'             => "ALTER TABLE `items` ADD COLUMN `auction_type` ENUM('english', 'dutch', 'vickrey', 'penny') DEFAULT 'english' AFTER `status`",
        'dutch_decrement_amount'   => "ALTER TABLE `items` ADD COLUMN `dutch_decrement_amount` DECIMAL(12,2) DEFAULT NULL AFTER `auction_type`",
        'dutch_decrement_interval' => "ALTER TABLE `items` ADD COLUMN `dutch_decrement_interval` INT DEFAULT 30 COMMENT 'Interval in seconds' AFTER `dutch_decrement_amount`",
        'dutch_floor_price'        => "ALTER TABLE `items` ADD COLUMN `dutch_floor_price` DECIMAL(12,2) DEFAULT NULL AFTER `dutch_decrement_interval`",
        'last_price_drop_at'       => "ALTER TABLE `items` ADD COLUMN `last_price_drop_at` DATETIME DEFAULT NULL AFTER `dutch_floor_price`",
        'penny_bid_fee'            => "ALTER TABLE `items` ADD COLUMN `penny_bid_fee` DECIMAL(10,2) DEFAULT 0.00 AFTER `last_price_drop_at`",
        'timer_increment_seconds'  => "ALTER TABLE `items` ADD COLUMN `timer_increment_seconds` INT DEFAULT 15 AFTER `penny_bid_fee`",
        'required_deposit_pct'     => "ALTER TABLE `items` ADD COLUMN `required_deposit_pct` DECIMAL(5,2) DEFAULT 0.00 COMMENT '0 to 100 percent' AFTER `timer_increment_seconds`",
        'is_sealed_bid'            => "ALTER TABLE `items` ADD COLUMN `is_sealed_bid` TINYINT(1) DEFAULT 0 AFTER `required_deposit_pct`"
    ];

    foreach ($columnsToAdd as $col => $sql) {
        if (!in_array($col, $existingCols, true)) {
            $pdo->exec($sql);
            echo " [+] Added column `items`.`$col`\n";
        } else {
            echo " [.] Column `items`.`$col` already exists.\n";
        }
    }

    // 2. Second Chance Offers Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `second_chance_offers` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `item_id` INT NOT NULL,
            `user_id` INT NOT NULL,
            `offered_price` DECIMAL(12,2) NOT NULL,
            `bid_id` INT DEFAULT NULL,
            `rank` INT NOT NULL COMMENT '2 for 2nd highest, 3 for 3rd',
            `status` ENUM('pending', 'accepted', 'declined', 'expired') DEFAULT 'pending',
            `token` VARCHAR(64) NOT NULL,
            `expires_at` DATETIME NOT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `responded_at` DATETIME DEFAULT NULL,
            INDEX `idx_item_status` (`item_id`, `status`),
            INDEX `idx_user_status` (`user_id`, `status`),
            UNIQUE KEY `uk_token` (`token`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo " [+] Table `second_chance_offers` ensured.\n";

    // 3. Syndicate / Pool Bidding Tables
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `syndicate_pools` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `item_id` INT NOT NULL,
            `lead_user_id` INT NOT NULL,
            `title` VARCHAR(150) NOT NULL,
            `target_budget` DECIMAL(12,2) NOT NULL,
            `collected_amount` DECIMAL(12,2) DEFAULT 0.00,
            `status` ENUM('open', 'funded', 'active_bidding', 'won', 'lost', 'refunded') DEFAULT 'open',
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_item` (`item_id`),
            INDEX `idx_lead_user` (`lead_user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo " [+] Table `syndicate_pools` ensured.\n";

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `syndicate_contributions` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `pool_id` INT NOT NULL,
            `user_id` INT NOT NULL,
            `amount` DECIMAL(12,2) NOT NULL,
            `share_percentage` DECIMAL(5,2) NOT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_pool_user` (`pool_id`, `user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo " [+] Table `syndicate_contributions` ensured.\n";

    // 4. Spectator Cheers & Reactions Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `item_reactions` (
            `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
            `item_id` INT NOT NULL,
            `user_id` INT NOT NULL,
            `reaction_type` VARCHAR(30) NOT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_item_time` (`item_id`, `created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo " [+] Table `item_reactions` ensured.\n";

    // 5. Digital Provenance Certificate Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `item_provenance` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `item_id` INT NOT NULL,
            `certificate_number` VARCHAR(64) NOT NULL,
            `provenance_hash` VARCHAR(64) NOT NULL,
            `issuer_name` VARCHAR(150) NOT NULL DEFAULT 'Geo Auction Provenance Bureau',
            `condition_report` TEXT NOT NULL,
            `metadata_json` JSON DEFAULT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `uk_cert` (`certificate_number`),
            UNIQUE KEY `uk_item` (`item_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo " [+] Table `item_provenance` ensured.\n";

    echo "\n>>> MIGRATION COMPLETED SUCCESSFULLY! <<<\n";
} catch (\Throwable $e) {
    echo "[-] Migration Error: " . $e->getMessage() . "\n";
    exit(1);
}
