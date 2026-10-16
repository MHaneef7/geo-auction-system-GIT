<?php
/**
 * Settings Service - Geo Auction System
 * File: includes/settings.php
 *
 * OOP PHP + PDO Architecture for Global, Auction, and User-Level Settings.
 */

declare(strict_types=1);

require_once __DIR__ . '/database.php';

class Settings
{
    private static ?PDO $pdo = null;
    private static array $cache = [];
    private static bool $initialized = false;

    /**
     * Get or initialize PDO connection
     */
    public static function getPdo(): PDO
    {
        if (self::$pdo === null) {
            self::$pdo = Database::getPdo();
        }
        return self::$pdo;
    }

    /**
     * Initialize Settings Engine and seed defaults
     */
    public static function init(?PDO $pdo = null): void
    {
        if ($pdo !== null) {
            self::$pdo = $pdo;
        }
        $db = self::getPdo();

        // 1. Ensure settings table exists
        try {
            $db->exec("
                CREATE TABLE IF NOT EXISTS `settings` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `key` VARCHAR(100) NOT NULL UNIQUE,
                    `value` TEXT NULL,
                    `group` VARCHAR(50) NOT NULL DEFAULT 'general',
                    `type` VARCHAR(20) NOT NULL DEFAULT 'text',
                    `label` VARCHAR(255) NULL,
                    `description` TEXT NULL,
                    `sort_order` INT NOT NULL DEFAULT 0,
                    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX `idx_settings_group` (`group`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");
        } catch (\Throwable $e) {
            error_log('Settings table init error: ' . $e->getMessage());
        }

        // 2. Ensure users table has settings column
        try {
            $colStmt = $db->query("SHOW COLUMNS FROM `users` LIKE 'settings'");
            if (!$colStmt->fetch()) {
                $db->exec("ALTER TABLE `users` ADD COLUMN `settings` LONGTEXT NULL DEFAULT NULL AFTER `last_login_ip`");
            }
        } catch (\Throwable $e) {
            error_log('Users settings column check: ' . $e->getMessage());
        }

        // 3. Define default global settings dictionary
        $defaults = [
            // General Settings
            'site_name' => ['value' => 'Geo Auction System', 'group' => 'general', 'type' => 'text', 'label' => 'Website Name', 'desc' => 'Public brand name of the auction marketplace'],
            'site_logo' => ['value' => 'assets/image/Geo-auction.png', 'group' => 'general', 'type' => 'text', 'label' => 'Website Logo', 'desc' => 'Primary marketplace logo image path'],
            'site_email' => ['value' => 'info@geoauction.com', 'group' => 'general', 'type' => 'email', 'label' => 'Website Email', 'desc' => 'Primary platform support and contact email'],
            'site_phone' => ['value' => '+92-300-1234567', 'group' => 'general', 'type' => 'text', 'label' => 'Phone', 'desc' => 'Official customer hotline and contact number'],
            'site_address' => ['value' => 'Main Auto Bhan Road, Qasimabad, Hyderabad, Sindh', 'group' => 'general', 'type' => 'textarea', 'label' => 'Address', 'desc' => 'Physical business address or headquarters'],
            'currency_symbol' => ['value' => 'Rs. ', 'group' => 'general', 'type' => 'text', 'label' => 'Currency Symbol', 'desc' => 'Prefix displayed alongside prices (e.g. Rs. )'],
            'currency_code' => ['value' => 'PKR', 'group' => 'general', 'type' => 'text', 'label' => 'Currency Code', 'desc' => 'ISO Currency standard (PKR)'],
            'timezone' => ['value' => 'Asia/Karachi', 'group' => 'general', 'type' => 'text', 'label' => 'Timezone', 'desc' => 'Marketplace operational timezone'],
            'site_status' => ['value' => 'active', 'group' => 'general', 'type' => 'select', 'label' => 'Website Status', 'desc' => 'Global operational status (active / maintenance)'],
            'maintenance_mode' => ['value' => '0', 'group' => 'general', 'type' => 'boolean', 'label' => 'Maintenance Mode', 'desc' => 'Enable to temporarily pause public bidding'],

            // Auction Settings
            'min_starting_price' => ['value' => '100', 'group' => 'auction', 'type' => 'number', 'label' => 'Minimum Starting Price', 'desc' => 'Absolute lowest starting bid allowed for any listing (PKR)'],
            'max_starting_price' => ['value' => '10000000', 'group' => 'auction', 'type' => 'number', 'label' => 'Maximum Starting Price', 'desc' => 'Maximum allowed starting bid for any listing (PKR)'],
            'min_bid_increment' => ['value' => '100', 'group' => 'auction', 'type' => 'number', 'label' => 'Minimum Bid Increment', 'desc' => 'Default minimum increment step between consecutive bids (PKR)'],
            'default_auction_duration_hours' => ['value' => '72', 'group' => 'auction', 'type' => 'number', 'label' => 'Default Auction Duration', 'desc' => 'Default runtime for new listings in hours'],
            'max_auction_duration_days' => ['value' => '30', 'group' => 'auction', 'type' => 'number', 'label' => 'Maximum Auction Duration', 'desc' => 'Longest allowed runtime for any listing in days'],
            'auto_extend_enabled' => ['value' => '1', 'group' => 'auction', 'type' => 'boolean', 'label' => 'Auto-Extend Auction Time', 'desc' => 'Anti-sniping protection on last-minute bids'],
            'auto_extend_minutes' => ['value' => '3', 'group' => 'auction', 'type' => 'number', 'label' => 'Auto-Extend Minutes', 'desc' => 'Minutes added when a bid is placed in the final window'],
            'auto_extend_trigger_seconds' => ['value' => '180', 'group' => 'auction', 'type' => 'number', 'label' => 'Trigger Window Seconds', 'desc' => 'Seconds remaining that trigger anti-sniping (180s = 3m)'],
            'buy_now_enabled' => ['value' => '1', 'group' => 'auction', 'type' => 'boolean', 'label' => 'Enable Buy Now', 'desc' => 'Allow sellers to offer instant Buy It Now purchases'],
            'buy_now_min_markup_pct' => ['value' => '15', 'group' => 'auction', 'type' => 'number', 'label' => 'Buy Now Min Markup (%)', 'desc' => 'Minimum percentage Buy Now price must exceed starting price'],
            'reserve_price_enabled' => ['value' => '1', 'group' => 'auction', 'type' => 'boolean', 'label' => 'Enable Reserve Price', 'desc' => 'Allow hidden minimum reserve threshold on lots'],
            'reserve_price_min_pct' => ['value' => '10', 'group' => 'auction', 'type' => 'number', 'label' => 'Reserve Price Min Markup (%)', 'desc' => 'Minimum percentage reserve price must exceed starting price'],
            'auto_bid_enabled' => ['value' => '1', 'group' => 'auction', 'type' => 'boolean', 'label' => 'Enable Auto Bid', 'desc' => 'Allow automated proxy bidbots for buyers'],
            'bid_confirmation_required' => ['value' => '1', 'group' => 'auction', 'type' => 'boolean', 'label' => 'Require Bid Confirmation', 'desc' => 'Enforce confirmation step before submitting bids'],
            'bid_cancellation_allowed' => ['value' => '0', 'group' => 'auction', 'type' => 'boolean', 'label' => 'Allow Bid Cancellation', 'desc' => 'Allow users to retract or cancel submitted bids'],
            'seller_approval_required' => ['value' => '0', 'group' => 'auction', 'type' => 'boolean', 'label' => 'Seller Approval Required', 'desc' => 'Require administrator approval before users can list items'],
            'auction_approval_required' => ['value' => '0', 'group' => 'auction', 'type' => 'boolean', 'label' => 'Auction Approval Required', 'desc' => 'Require admin approval before listings go live'],
            'max_images_per_auction' => ['value' => '10', 'group' => 'auction', 'type' => 'number', 'label' => 'Maximum Images per Auction', 'desc' => 'Max allowed photo uploads per lot'],
            'max_active_auctions_per_seller' => ['value' => '25', 'group' => 'auction', 'type' => 'number', 'label' => 'Max Active Lots per Seller', 'desc' => 'Limit of concurrently live listings per seller'],
            'allowed_auction_conditions' => ['value' => 'new,used,refurbished', 'group' => 'auction', 'type' => 'text', 'label' => 'Allowed Item Conditions', 'desc' => 'Comma-separated allowed item condition tags'],
            'require_item_location' => ['value' => '1', 'group' => 'auction', 'type' => 'boolean', 'label' => 'Require Item Location', 'desc' => 'Require physical location for all lot submissions'],
            'default_auction_city' => ['value' => 'Hyderabad', 'group' => 'auction', 'type' => 'text', 'label' => 'Default Auction City', 'desc' => 'Default marketplace lot origin city'],
            'allowed_provinces' => ['value' => 'Sindh,Punjab,Khyber Pakhtunkhwa,Balochistan,Islamabad Capital Territory,Gilgit-Baltistan,Azad Kashmir', 'group' => 'auction', 'type' => 'text', 'label' => 'Allowed Provinces / Regions', 'desc' => 'Comma-separated geographic delivery zones'],

            // User Settings
            'enable_registration' => ['value' => '1', 'group' => 'users', 'type' => 'boolean', 'label' => 'User Registration', 'desc' => 'Allow public user sign-ups'],
            'require_email_verification' => ['value' => '0', 'group' => 'users', 'type' => 'boolean', 'label' => 'Email Verification', 'desc' => 'Require email confirmation before activation'],
            'user_approval_required' => ['value' => '0', 'group' => 'users', 'type' => 'boolean', 'label' => 'User Approval', 'desc' => 'Require manual administrator approval of new accounts'],
            'enable_seller_registration' => ['value' => '1', 'group' => 'users', 'type' => 'boolean', 'label' => 'Seller Registration', 'desc' => 'Allow members to register as sellers'],
            'enable_buyer_registration' => ['value' => '1', 'group' => 'users', 'type' => 'boolean', 'label' => 'Buyer Registration', 'desc' => 'Allow members to register as buyers'],
            'default_user_status' => ['value' => 'active', 'group' => 'users', 'type' => 'select', 'label' => 'Default User Status', 'desc' => 'Status applied to newly created accounts'],

            // Notification Settings
            'enable_email_notifications' => ['value' => '1', 'group' => 'notifications', 'type' => 'boolean', 'label' => 'Enable Email Notifications', 'desc' => 'Global email transmission switch'],
            'enable_bid_notifications' => ['value' => '1', 'group' => 'notifications', 'type' => 'boolean', 'label' => 'Enable Bid Notifications', 'desc' => 'Notify sellers when bids are placed'],
            'enable_outbid_notifications' => ['value' => '1', 'group' => 'notifications', 'type' => 'boolean', 'label' => 'Enable Outbid Notifications', 'desc' => 'Alert previous bidders when outbid'],
            'enable_ending_notifications' => ['value' => '1', 'group' => 'notifications', 'type' => 'boolean', 'label' => 'Enable Ending-Soon Alerts', 'desc' => 'Alert watchers when auctions near end'],
            'enable_winning_notifications' => ['value' => '1', 'group' => 'notifications', 'type' => 'boolean', 'label' => 'Enable Winning Notifications', 'desc' => 'Notify winners upon lot conclusion'],
            'enable_system_notifications' => ['value' => '1', 'group' => 'notifications', 'type' => 'boolean', 'label' => 'Enable System Notifications', 'desc' => 'In-app notification badge feed'],

            // Security Settings
            'login_attempt_limit' => ['value' => '5', 'group' => 'security', 'type' => 'number', 'label' => 'Login Attempt Limit', 'desc' => 'Max failed attempts before temporary lockout'],
            'session_timeout_minutes' => ['value' => '120', 'group' => 'security', 'type' => 'number', 'label' => 'Session Timeout', 'desc' => 'Idle session validity period in minutes'],
            'password_min_length' => ['value' => '8', 'group' => 'security', 'type' => 'number', 'label' => 'Password Minimum Length', 'desc' => 'Shortest allowed password length'],
            'require_strong_password' => ['value' => '1', 'group' => 'security', 'type' => 'boolean', 'label' => 'Require Strong Password', 'desc' => 'Enforce letters, numbers, and symbols'],
            'admin_login_security' => ['value' => '1', 'group' => 'security', 'type' => 'boolean', 'label' => 'Admin Login Security', 'desc' => 'Enforce strict IP/session checks for admins'],

            // Payment / Wallet Settings
            'wallet_enabled' => ['value' => '1', 'group' => 'payment', 'type' => 'boolean', 'label' => 'Wallet Enabled', 'desc' => 'Enable marketplace balance wallet'],
            'min_wallet_deposit' => ['value' => '500', 'group' => 'payment', 'type' => 'number', 'label' => 'Minimum Wallet Deposit', 'desc' => 'Smallest allowed top-up amount (PKR)'],
            'min_bid_amount' => ['value' => '100', 'group' => 'payment', 'type' => 'number', 'label' => 'Minimum Bid Amount', 'desc' => 'Absolute lowest opening bid allowed (PKR)'],
            'commission_rate' => ['value' => '5', 'group' => 'payment', 'type' => 'number', 'label' => 'Commission Rate (%)', 'desc' => 'Platform fee retained from winning transactions'],
            'transaction_settings' => ['value' => 'instant', 'group' => 'payment', 'type' => 'text', 'label' => 'Transaction Settlement Mode', 'desc' => 'Instant balance release vs Escrow holding']
        ];

        // Seed missing defaults via prepared statement
        try {
            $chkStmt = $db->prepare("SELECT `key` FROM `settings` WHERE `key` = :k LIMIT 1");
            $insStmt = $db->prepare("
                INSERT INTO `settings` (`key`, `value`, `group`, `type`, `label`, `description`, `created_at`, `updated_at`)
                VALUES (:k, :v, :g, :t, :l, :d, NOW(), NOW())
            ");

            foreach ($defaults as $key => $meta) {
                $chkStmt->execute([':k' => $key]);
                if (!$chkStmt->fetch()) {
                    $insStmt->execute([
                        ':k' => $key,
                        ':v' => $meta['value'],
                        ':g' => $meta['group'],
                        ':t' => $meta['type'],
                        ':l' => $meta['label'],
                        ':d' => $meta['desc'],
                    ]);
                }
            }
        } catch (\Throwable $e) {
            error_log('Settings seeding error: ' . $e->getMessage());
        }

        self::$initialized = true;
    }

    /**
     * Get a single setting by key
     */
    public static function get(string $key, $default = null)
    {
        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key];
        }

        if (!self::$initialized) {
            self::init();
        }

        try {
            $stmt = self::getPdo()->prepare("SELECT `value` FROM `settings` WHERE `key` = :k LIMIT 1");
            $stmt->execute([':k' => $key]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row && $row['value'] !== null) {
                self::$cache[$key] = $row['value'];
                return $row['value'];
            }
        } catch (\Throwable $e) {
            error_log("Settings::get failed for '$key': " . $e->getMessage());
        }

        self::$cache[$key] = $default;
        return $default;
    }

    /**
     * Set/update a setting
     */
    public static function set(string $key, $value, ?string $group = null, ?string $type = null, ?string $label = null, ?string $desc = null): bool
    {
        $db = self::getPdo();
        $strVal = is_bool($value) ? ($value ? '1' : '0') : (string)$value;

        try {
            $stmt = $db->prepare("
                INSERT INTO `settings` (`key`, `value`, `group`, `type`, `label`, `description`, `updated_at`)
                VALUES (:k, :v, :g, :t, :l, :d, NOW())
                ON DUPLICATE KEY UPDATE 
                    `value` = VALUES(`value`),
                    `group` = COALESCE(:g2, `group`),
                    `type`  = COALESCE(:t2, `type`),
                    `label` = COALESCE(:l2, `label`),
                    `description` = COALESCE(:d2, `description`),
                    `updated_at` = NOW()
            ");

            $res = $stmt->execute([
                ':k' => $key,
                ':v' => $strVal,
                ':g' => $group ?? 'general',
                ':t' => $type ?? 'text',
                ':l' => $label,
                ':d' => $desc,
                ':g2' => $group,
                ':t2' => $type,
                ':l2' => $label,
                ':d2' => $desc,
            ]);

            self::$cache[$key] = $strVal;
            return $res;
        } catch (\Throwable $e) {
            error_log("Settings::set error for '$key': " . $e->getMessage());
            return false;
        }
    }

    /**
     * Save multiple settings under a group in a transaction
     */
    public static function saveMultiple(array $keyValues, string $group = 'general'): bool
    {
        $db = self::getPdo();
        try {
            $db->beginTransaction();
            $stmt = $db->prepare("
                INSERT INTO `settings` (`key`, `value`, `group`, `updated_at`)
                VALUES (:k, :v, :g, NOW())
                ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `updated_at` = NOW()
            ");

            foreach ($keyValues as $k => $v) {
                $valStr = is_bool($v) ? ($v ? '1' : '0') : (string)$v;
                $stmt->execute([
                    ':k' => (string)$k,
                    ':v' => $valStr,
                    ':g' => $group,
                ]);
                self::$cache[(string)$k] = $valStr;
            }

            $db->commit();
            return true;
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('Settings::saveMultiple error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all settings in a group as key => value
     */
    public static function getGroup(string $group): array
    {
        if (!self::$initialized) {
            self::init();
        }

        try {
            $stmt = self::getPdo()->prepare("SELECT `key`, `value` FROM `settings` WHERE `group` = :g ORDER BY `sort_order` ASC, `id` ASC");
            $stmt->execute([':g' => $group]);
            $res = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $res[$row['key']] = $row['value'];
                self::$cache[$row['key']] = $row['value'];
            }
            return $res;
        } catch (\Throwable $e) {
            error_log("Settings::getGroup error for '$group': " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get all settings grouped
     */
    public static function getAll(): array
    {
        if (!self::$initialized) {
            self::init();
        }

        try {
            $stmt = self::getPdo()->query("SELECT * FROM `settings` ORDER BY `group` ASC, `sort_order` ASC, `id` ASC");
            $grouped = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $grouped[$row['group']][] = $row;
                self::$cache[$row['key']] = $row['value'];
            }
            return $grouped;
        } catch (\Throwable $e) {
            error_log('Settings::getAll error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Fetch user settings from `users.settings` JSON column
     */
    public static function getUserSettings(int $userId): array
    {
        $defaultUserPreferences = [
            'auction_preferences' => [
                'default_category_id' => 0,
                'bid_confirmation' => true,
                'auto_bid_preference' => false,
                'max_auto_bid_ceiling' => 50000,
            ],
            'notifications' => [
                'bid_notifications' => true,
                'outbid_notifications' => true,
                'auction_ending_notifications' => true,
                'winning_notifications' => true,
                'message_notifications' => true,
            ],
            'privacy' => [
                'profile_visibility' => 'public',
                'show_phone' => false,
                'show_email' => false,
            ],
            'security' => [
                'two_factor_auth' => false,
            ]
        ];

        if ($userId <= 0) {
            return $defaultUserPreferences;
        }

        try {
            $stmt = self::getPdo()->prepare("SELECT `settings`, `last_login_at`, `last_login_ip` FROM `users` WHERE `id` = :uid LIMIT 1");
            $stmt->execute([':uid' => $userId]);
            $userRow = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($userRow) {
                $decoded = !empty($userRow['settings']) ? json_decode($userRow['settings'], true) : [];
                if (is_array($decoded)) {
                    $merged = array_replace_recursive($defaultUserPreferences, $decoded);
                    $merged['security']['last_login_at'] = $userRow['last_login_at'] ?? 'Never';
                    $merged['security']['last_login_ip'] = $userRow['last_login_ip'] ?? 'N/A';
                    return $merged;
                }
            }
        } catch (\Throwable $e) {
            error_log("Settings::getUserSettings error for UID $userId: " . $e->getMessage());
        }

        return $defaultUserPreferences;
    }

    /**
     * Save user settings back to `users.settings` JSON
     */
    public static function saveUserSettings(int $userId, array $newSettings): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $current = self::getUserSettings($userId);
        $merged = array_replace_recursive($current, $newSettings);
        // Exclude transient DB fields from stored JSON
        unset($merged['security']['last_login_at'], $merged['security']['last_login_ip']);

        $json = json_encode($merged, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        try {
            $stmt = self::getPdo()->prepare("UPDATE `users` SET `settings` = :st, `updated_at` = NOW() WHERE `id` = :uid");
            return $stmt->execute([
                ':st'  => $json,
                ':uid' => $userId,
            ]);
        } catch (\Throwable $e) {
            error_log("Settings::saveUserSettings error for UID $userId: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Return comprehensive system information
     */
    public static function getSystemInfo(): array
    {
        $db = self::getPdo();
        $dbVersion = 'Unknown';
        $dbName = 'geo_auction_db';

        try {
            $dbVersion = $db->query("SELECT VERSION()")->fetchColumn() ?: 'Unknown';
            $dbName = $db->query("SELECT DATABASE()")->fetchColumn() ?: 'geo_auction_db';
        } catch (\Throwable $e) {}

        return [
            'php_version' => PHP_VERSION,
            'mysql_version' => (string)$dbVersion,
            'app_version' => 'Geo-Auction v2.4 (Enterprise)',
            'database_name' => (string)$dbName,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? php_uname('s'),
            'os' => PHP_OS . ' (' . php_uname('m') . ')',
            'upload_max_filesize' => ini_get('upload_max_filesize') ?: 'N/A',
            'post_max_size' => ini_get('post_max_size') ?: 'N/A',
            'memory_limit' => ini_get('memory_limit') ?: 'N/A',
            'max_execution_time' => (ini_get('max_execution_time') ?: '30') . ' seconds',
            'server_time' => date('Y-m-d H:i:s T'),
            'pdo_drivers' => implode(', ', PDO::getAvailableDrivers()),
        ];
    }

    /**
     * Generate CSRF Token for settings forms
     */
    public static function csrfToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['settings_csrf'])) {
            $_SESSION['settings_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['settings_csrf'];
    }

    /**
     * Alias for csrfToken()
     */
    public static function generateCsrf(): string
    {
        return self::csrfToken();
    }

    /**
     * Verify CSRF Token
     */
    public static function verifyCsrf(?string $token): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['settings_csrf']) || empty($token)) {
            return false;
        }
        return hash_equals($_SESSION['settings_csrf'], $token);
    }
}
