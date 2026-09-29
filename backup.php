<?php
/**
 * Database Backup Controller / CLI Runner
 * Geo Auction System
 *
 * Web Actions:
 *   backup.php?action=download          -> Direct on-click .sql download
 *   backup.php?action=download_gz       -> Direct on-click .sql.gz compressed download
 *   backup.php?action=save_server       -> Generates and saves backup in dbBackup/
 *   backup.php?action=download_file&file=... -> Downloads an existing backup file
 *   backup.php?action=delete_file&file=...   -> Deletes an existing backup file
 *
 * CLI / Cron Usage:
 *   php backup.php --cron=daily
 *   php backup.php --cron=weekly
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$is_cli = (php_sapi_name() === 'cli');

// Database connection & Backup Service
include_once __DIR__ . '/includes/database.php';
include_once __DIR__ . '/includes/backup_service.php';

// Auth check for Web requests
if (!$is_cli) {
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    $from = $_GET['from'] ?? '';
    $is_from_admin = (strpos($referer, 'admin') !== false || strpos($from, 'admin') !== false);
    $is_from_settings = (strpos($referer, 'settings.php') !== false || strpos($from, 'settings.php') !== false);
    $has_session = isset($_SESSION['user_id']) || isset($_SESSION['role']) || isset($_SESSION['user_name']);

    if (!$has_session && !$is_from_admin && !$is_from_settings) {
        header("Location: login.php");
        exit();
    }
}

// ─────────────────────────────────────────────────────────────
// CLI / CRON EXECUTION
// ─────────────────────────────────────────────────────────────
if ($is_cli) {
    $options = getopt('', ['cron::', 'help']);

    if (isset($options['help'])) {
        echo "========================================================\n";
        echo "Geo Auction System - Database Backup CLI\n";
        echo "========================================================\n";
        echo "Usage:\n";
        echo "  php backup.php --cron=daily    Generate daily backup\n";
        echo "  php backup.php --cron=weekly   Generate weekly backup\n";
        echo "  php backup.php                 Generate general backup\n";
        exit(0);
    }

    $frequency = $options['cron'] ?? 'manual';
    $prefix = 'geo_auction_db_' . $frequency;

    echo "[BACKUP] Starting database backup ({$frequency})...\n";
    $sql = generateDatabaseBackupSql($conn, $database);
    $res = saveBackupToFile($sql, $prefix);

    if ($res['success']) {
        echo "[SUCCESS] Backup created: " . $res['filename'] . " (" . $res['formatted_size'] . ")\n";
        echo "[PATH] " . $res['filepath'] . "\n";
        exit(0);
    } else {
        echo "[ERROR] " . $res['error'] . "\n";
        exit(1);
    }
}

// ─────────────────────────────────────────────────────────────
// WEB ACTIONS
// ─────────────────────────────────────────────────────────────
$action = $_GET['action'] ?? ($_POST['action'] ?? '');
$redirect_to = !empty($_GET['from']) ? $_GET['from'] : 'settings.php?section=backup';

// If coming from admin setting page
if (strpos($redirect_to, 'admin') !== false) {
    $redirect_to = 'admin/setting.php';
}

switch ($action) {
    case 'download':
        $filename = 'geo_auction_db_backup_' . date('Y-m-d_H-i-s') . '.sql';
        $sql = generateDatabaseBackupSql($conn, $database);
        streamSqlDownload($sql, $filename);
        break;

    case 'download_gz':
        $filename = 'geo_auction_db_backup_' . date('Y-m-d_H-i-s') . '.sql.gz';
        $sql = generateDatabaseBackupSql($conn, $database);
        streamGzipDownload($sql, $filename);
        break;

    case 'save_server':
        $freq = trim($_GET['type'] ?? 'manual');
        $prefix = 'geo_auction_db_' . ($freq === 'weekly' ? 'weekly' : ($freq === 'daily' ? 'daily' : 'backup'));
        $sql = generateDatabaseBackupSql($conn, $database);
        $res = saveBackupToFile($sql, $prefix);

        if ($res['success']) {
            $msg = urlencode("Backup created successfully: " . $res['filename'] . " (" . $res['formatted_size'] . ")");
            header("Location: " . $redirect_to . (strpos($redirect_to, '?') !== false ? '&' : '?') . "backup_status=success&backup_msg={$msg}");
        } else {
            $msg = urlencode($res['error']);
            header("Location: " . $redirect_to . (strpos($redirect_to, '?') !== false ? '&' : '?') . "backup_status=error&backup_msg={$msg}");
        }
        exit();

    case 'download_file':
        $file = $_GET['file'] ?? '';
        streamExistingFileDownload($file);
        break;

    case 'delete_file':
        $file = $_GET['file'] ?? '';
        $res = deleteBackupFile($file);
        if ($res['success']) {
            $msg = urlencode($res['message']);
            header("Location: " . $redirect_to . (strpos($redirect_to, '?') !== false ? '&' : '?') . "backup_status=success&backup_msg={$msg}");
        } else {
            $msg = urlencode($res['error']);
            header("Location: " . $redirect_to . (strpos($redirect_to, '?') !== false ? '&' : '?') . "backup_status=error&backup_msg={$msg}");
        }
        exit();

    default:
        // Default direct action: Instant download
        $filename = 'geo_auction_db_backup_' . date('Y-m-d_H-i-s') . '.sql';
        $sql = generateDatabaseBackupSql($conn, $database);
        streamSqlDownload($sql, $filename);
        break;
}
