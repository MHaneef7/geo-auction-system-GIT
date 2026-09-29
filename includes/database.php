<?php
/**
 * OOP Database Service & MySQLi Connection
 * File: includes/database.php
 * 
 * Provides:
 * 1. Global $conn (mysqli) connection for legacy & procedural scripts
 * 2. OOP PDO Database Service (class Database) for modern services
 */

class Database
{
    private string $host;
    private string $dbName;
    private string $username;
    private string $password;
    private string $charset;
    private int $port;
    private ?PDO $pdo = null;
    private ?mysqli $mysqli = null;
    private static ?Database $instance = null;

    public function __construct(
        string $host = 'localhost',
        string $dbName = 'geo_auction_db',
        string $username = 'root',
        string $password = '',
        string $charset = 'utf8mb4',
        int $port = 3306
    ) {
        // Attempt to load from config/database.php if available
        $configFile = __DIR__ . '/../config/database.php';
        if (file_exists($configFile)) {
            $config = include $configFile;
            if (is_array($config)) {
                $host     = $config['host'] ?? $host;
                $dbName   = $config['database'] ?? $dbName;
                $username = $config['username'] ?? $username;
                $password = $config['password'] ?? $password;
                $charset  = $config['charset'] ?? $charset;
                $port     = (int)($config['port'] ?? $port);
            }
        }

        $this->host     = $host;
        $this->dbName   = $dbName;
        $this->username = $username;
        $this->password = $password;
        $this->charset  = $charset;
        $this->port     = $port;
    }

    /**
     * Get or establish the PDO connection
     */
    public function connect(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        $dsn = "mysql:host={$this->host};port={$this->port};dbname={$this->dbName};charset={$this->charset}";

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
        ];

        try {
            $this->pdo = new PDO($dsn, $this->username, $this->password, $options);
            return $this->pdo;
        } catch (PDOException $e) {
            error_log("Database PDO connection error: " . $e->getMessage());
            throw new RuntimeException("Database connection failure. Please check server availability.");
        }
    }

    /**
     * Get or establish the MySQLi connection
     */
    public function connectMysqli(): mysqli
    {
        if ($this->mysqli instanceof mysqli && @$this->mysqli->ping()) {
            return $this->mysqli;
        }

        $driver = new mysqli_driver();
        $old_report_mode = $driver->report_mode;
        $driver->report_mode = MYSQLI_REPORT_OFF;

        try {
            $mysqli = @new mysqli($this->host, $this->username, $this->password, $this->dbName, $this->port);

            if ($mysqli->connect_error) {
                // Fallback attempt without port
                $mysqli = @new mysqli($this->host, $this->username, $this->password, $this->dbName);
            }

            if ($mysqli->connect_error) {
                error_log("Database MySQLi connection error: " . $mysqli->connect_error);
                die("Database Connection Failed: " . $mysqli->connect_error);
            }

            $mysqli->set_charset($this->charset);
            $this->mysqli = $mysqli;
            return $this->mysqli;
        } catch (\Throwable $e) {
            error_log("Database MySQLi connection exception: " . $e->getMessage());
            die("Database Connection Failed: " . $e->getMessage());
        } finally {
            $driver->report_mode = $old_report_mode;
        }
    }

    /**
     * Singleton instance helper
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Direct helper to get PDO connection
     */
    public static function getPdo(): PDO
    {
        return self::getInstance()->connect();
    }

    /**
     * Direct helper to get MySQLi connection
     */
    public static function getMysqli(): mysqli
    {
        return self::getInstance()->connectMysqli();
    }

    /**
     * Alias for getMysqli
     */
    public static function getConn(): mysqli
    {
        return self::getMysqli();
    }
}

// Provide global $conn for all mysqli scripts across the system
global $conn;
if (!isset($conn) || !($conn instanceof mysqli) || !@$conn->ping()) {
    $conn = Database::getMysqli();
}
$GLOBALS['conn'] = $conn;