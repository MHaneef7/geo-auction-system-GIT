<?php
/**
 * Database Backup Service
 * Geo Auction System
 * Provides pure PHP MySQL backup generation, compression, storage, and retrieval.
 */

if (!function_exists('getBackupDirectory')) {
    function getBackupDirectory() {
        $dir = __DIR__ . '/../dbBackup/';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        // Protect directory from direct unauthorized HTTP access
        $htaccess = $dir . '.htaccess';
        if (!file_exists($htaccess)) {
            @file_put_contents($htaccess, "Order allow,deny\nDeny from all\n");
        }
        return realpath($dir) ?: $dir;
    }
}

if (!function_exists('getDatabaseStats')) {
    function getDatabaseStats($conn, $database) {
        $stats = [
            'database' => $database,
            'tables_count' => 0,
            'total_rows' => 0,
            'data_size_bytes' => 0,
            'index_size_bytes' => 0,
            'total_size_bytes' => 0,
            'size_formatted' => '0 KB',
            'last_backup_time' => null
        ];

        if (!$conn || $conn->connect_error) {
            return $stats;
        }

        $db_esc = mysqli_real_escape_string($conn, $database);
        $q = "SELECT 
                COUNT(table_name) AS table_count,
                SUM(data_length + index_length) AS total_size,
                SUM(data_length) AS data_size,
                SUM(index_length) AS index_size,
                SUM(table_rows) AS total_rows
              FROM information_schema.TABLES 
              WHERE table_schema = '{$db_esc}'";

        $res = mysqli_query($conn, $q);
        if ($res && $row = mysqli_fetch_assoc($res)) {
            $stats['tables_count'] = (int)($row['table_count'] ?? 0);
            $stats['total_rows'] = (int)($row['total_rows'] ?? 0);
            $stats['data_size_bytes'] = (float)($row['data_size'] ?? 0);
            $stats['index_size_bytes'] = (float)($row['index_size'] ?? 0);
            $stats['total_size_bytes'] = (float)($row['total_size'] ?? 0);
            $stats['size_formatted'] = formatBytes($stats['total_size_bytes']);
        }

        // Check latest file in dbBackup
        $backups = getExistingBackups();
        if (!empty($backups)) {
            $stats['last_backup_time'] = $backups[0]['modified_timestamp'];
            $stats['last_backup_formatted'] = $backups[0]['modified_formatted'];
        } else {
            $stats['last_backup_formatted'] = 'No backup yet';
        }

        return $stats;
    }
}

if (!function_exists('formatBytes')) {
    function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}

if (!function_exists('generateDatabaseBackupSql')) {
    function generateDatabaseBackupSql($conn, $database_name, $include_drop = true) {
        $out = "-- ========================================================\n";
        $out .= "-- Geo Auction System - Complete Database Backup\n";
        $out .= "-- Database: `" . $database_name . "`\n";
        $out .= "-- Export Date: " . date('Y-m-d H:i:s') . "\n";
        $out .= "-- Server OS: " . PHP_OS . "\n";
        $out .= "-- PHP Version: " . PHP_VERSION . "\n";
        $out .= "-- MySQL Version: " . mysqli_get_server_info($conn) . "\n";
        $out .= "-- ========================================================\n\n";

        $out .= "SET FOREIGN_KEY_CHECKS = 0;\n";
        $out .= "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n";
        $out .= "SET AUTOCOMMIT = 0;\n";
        $out .= "START TRANSACTION;\n";
        $out .= "SET time_zone = '+00:00';\n\n";

        $tables = [];
        $res = mysqli_query($conn, "SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
        if ($res) {
            while ($row = mysqli_fetch_row($res)) {
                $tables[] = $row[0];
            }
        }

        foreach ($tables as $table) {
            $out .= "\n-- --------------------------------------------------------\n";
            $out .= "-- Structure for table `" . $table . "`\n";
            $out .= "-- --------------------------------------------------------\n\n";

            if ($include_drop) {
                $out .= "DROP TABLE IF EXISTS `" . $table . "`;\n";
            }

            $create_res = mysqli_query($conn, "SHOW CREATE TABLE `" . $table . "`");
            if ($create_res && $create_row = mysqli_fetch_row($create_res)) {
                $out .= $create_row[1] . ";\n\n";
            }

            $out .= "-- Dumping data for table `" . $table . "`\n";
            $data_res = mysqli_query($conn, "SELECT * FROM `" . $table . "`");
            if ($data_res) {
                $num_rows = mysqli_num_rows($data_res);
                if ($num_rows > 0) {
                    $fields = [];
                    $fields_res = mysqli_fetch_fields($data_res);
                    foreach ($fields_res as $f) {
                        $fields[] = "`" . $f->name . "`";
                    }
                    $field_str = implode(", ", $fields);

                    $chunk_size = 100;
                    $current_chunk = 0;
                    $row_values = [];

                    while ($row = mysqli_fetch_assoc($data_res)) {
                        $vals = [];
                        foreach ($row as $val) {
                            if ($val === null) {
                                $vals[] = "NULL";
                            } elseif (is_numeric($val) && !preg_match('/^0[0-9]+/', $val)) {
                                $vals[] = $val;
                            } else {
                                $vals[] = "'" . mysqli_real_escape_string($conn, $val) . "'";
                            }
                        }
                        $row_values[] = "(" . implode(", ", $vals) . ")";
                        $current_chunk++;

                        if ($current_chunk >= $chunk_size) {
                            $out .= "INSERT INTO `" . $table . "` (" . $field_str . ") VALUES\n" . implode(",\n", $row_values) . ";\n";
                            $row_values = [];
                            $current_chunk = 0;
                        }
                    }

                    if (!empty($row_values)) {
                        $out .= "INSERT INTO `" . $table . "` (" . $field_str . ") VALUES\n" . implode(",\n", $row_values) . ";\n";
                    }
                    $out .= "\n";
                }
            }
        }

        $out .= "COMMIT;\n";
        $out .= "SET FOREIGN_KEY_CHECKS = 1;\n";
        $out .= "-- ========================================================\n";
        $out .= "-- End of Database Backup\n";
        $out .= "-- ========================================================\n";
        return $out;
    }
}

if (!function_exists('saveBackupToFile')) {
    function saveBackupToFile($sql, $prefix = 'geo_auction_db_backup') {
        $dir = getBackupDirectory();
        $filename = $prefix . '_' . date('Y-m-d_H-i-s') . '.sql';
        $filepath = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $filename;
        $bytes = @file_put_contents($filepath, $sql);
        if ($bytes !== false) {
            return [
                'success' => true,
                'filename' => $filename,
                'filepath' => $filepath,
                'bytes' => $bytes,
                'formatted_size' => formatBytes($bytes)
            ];
        }
        return [
            'success' => false,
            'error' => 'Failed to write backup file to: ' . $filepath
        ];
    }
}

if (!function_exists('getExistingBackups')) {
    function getExistingBackups() {
        $dir = getBackupDirectory();
        $backups = [];
        if (!is_dir($dir)) {
            return $backups;
        }

        $files = scandir($dir);
        foreach ($files as $f) {
            if ($f === '.' || $f === '..' || $f === '.htaccess') continue;
            if (!preg_match('/\.(sql|gz|zip)$/i', $f)) continue;

            $filepath = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $f;
            if (is_file($filepath)) {
                $size = filesize($filepath);
                $mtime = filemtime($filepath);
                $backups[] = [
                    'filename' => $f,
                    'filepath' => $filepath,
                    'size_bytes' => $size,
                    'size_formatted' => formatBytes($size),
                    'modified_timestamp' => $mtime,
                    'modified_formatted' => date('M d, Y - h:i A', $mtime),
                    'type' => preg_match('/\.gz$/i', $f) ? 'Compressed (GZ)' : 'SQL Dump'
                ];
            }
        }

        // Sort newest first
        usort($backups, function($a, $b) {
            return $b['modified_timestamp'] <=> $a['modified_timestamp'];
        });

        return $backups;
    }
}

if (!function_exists('deleteBackupFile')) {
    function deleteBackupFile($filename) {
        // Sanitize filename to prevent directory traversal
        $clean_name = basename($filename);
        if (!preg_match('/^[a-zA-Z0-9_\-\.]+\.(sql|gz|zip)$/i', $clean_name)) {
            return ['success' => false, 'error' => 'Invalid file name.'];
        }

        $dir = getBackupDirectory();
        $filepath = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $clean_name;

        if (file_exists($filepath) && is_file($filepath)) {
            if (@unlink($filepath)) {
                return ['success' => true, 'message' => "Backup file '{$clean_name}' deleted successfully."];
            }
            return ['success' => false, 'error' => "Failed to delete file '{$clean_name}'."];
        }
        return ['success' => false, 'error' => 'File not found.'];
    }
}

if (!function_exists('streamSqlDownload')) {
    function streamSqlDownload($sql, $filename = null) {
        if (empty($filename)) {
            $filename = 'geo_auction_db_backup_' . date('Y-m-d_H-i-s') . '.sql';
        }

        // Clear output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: application/sql; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($sql));
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('Expires: 0');

        echo $sql;
        exit();
    }
}

if (!function_exists('streamGzipDownload')) {
    function streamGzipDownload($sql, $filename = null) {
        if (!function_exists('gzencode')) {
            // Fallback to regular SQL if gz not enabled
            return streamSqlDownload($sql, $filename);
        }

        if (empty($filename)) {
            $filename = 'geo_auction_db_backup_' . date('Y-m-d_H-i-s') . '.sql.gz';
        }

        $compressed = gzencode($sql, 9);

        while (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: application/x-gzip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($compressed));
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('Expires: 0');

        echo $compressed;
        exit();
    }
}

if (!function_exists('streamExistingFileDownload')) {
    function streamExistingFileDownload($filename) {
        $clean_name = basename($filename);
        if (!preg_match('/^[a-zA-Z0-9_\-\.]+\.(sql|gz|zip)$/i', $clean_name)) {
            die("Invalid filename.");
        }

        $dir = getBackupDirectory();
        $filepath = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $clean_name;

        if (!file_exists($filepath) || !is_file($filepath)) {
            die("Backup file not found.");
        }

        while (ob_get_level()) {
            ob_end_clean();
        }

        $mime = preg_match('/\.gz$/i', $clean_name) ? 'application/x-gzip' : 'application/sql';
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . $clean_name . '"');
        header('Content-Length: ' . filesize($filepath));
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('Expires: 0');

        readfile($filepath);
        exit();
    }
}
