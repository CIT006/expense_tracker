<?php
/**
 * database.php — Database Connection Class
 * รองรับ environment variables สำหรับตั้งค่าฐานข้อมูล
 */
class Database {
    private string $host;
    private string $port;
    private string $db_name;
    private string $username;
    private string $password;
    private ?PDO $conn = null;

    public function __construct() {
        $isLocalhost = in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1', '::1']);
        $localConfigPath = __DIR__ . '/database.local.php';
        $localConfig = [];
        if (is_file($localConfigPath)) {
            $localConfig = require $localConfigPath;
            if (!is_array($localConfig)) {
                throw new RuntimeException('config/database.local.php must return an array');
            }
        }

        $defaultHost = $isLocalhost ? 'localhost' : 'sql100.infinityfree.com';
        $defaultPort = $isLocalhost ? '3307' : '3306';
        $defaultDatabase = $isLocalhost ? 'expense_tracker' : 'if0_41301171_expense_tracker1';
        $defaultUsername = $isLocalhost ? 'root' : 'if0_41301171';
        $defaultPassword = $isLocalhost ? '' : 'uTP7MF8HABb';

        $this->host     = getenv('DB_HOST') ?: ($localConfig['host'] ?? $defaultHost);
        $this->port     = getenv('DB_PORT') ?: (string)($localConfig['port'] ?? $defaultPort);
        $this->db_name  = getenv('DB_NAME') ?: ($localConfig['database'] ?? $defaultDatabase);
        $this->username = getenv('DB_USER') ?: ($localConfig['username'] ?? $defaultUsername);
        $password = getenv('DB_PASSWORD');
        $this->password = $password !== false ? $password : ($localConfig['password'] ?? $defaultPassword);
    }

    public function getConnection(): PDO {
        if ($this->conn !== null) {
            return $this->conn;
        }
        try {
            $this->conn = new PDO(
                "mysql:host={$this->host};port={$this->port};dbname={$this->db_name};charset=utf8mb4",
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
                ]
            );
        } catch (PDOException $e) {
            // ไม่แสดง error message จริงใน production
            $isDev = ($_SERVER['SERVER_NAME'] ?? '') === 'localhost';
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $isDev ? 'DB Error: ' . $e->getMessage() : 'ไม่สามารถเชื่อมต่อฐานข้อมูลได้',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        return $this->conn;
    }
}
