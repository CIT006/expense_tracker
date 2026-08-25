<?php
/**
 * database.php — Database Connection Class
 * รองรับ environment variables สำหรับ production
 */
class Database {
    private string $host;
    private string $db_name;
    private string $username;
    private string $password;
    private ?PDO $conn = null;

    public function __construct() {
        $isLocalhost = in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1', '::1']);
        
        if ($isLocalhost) {
            $this->host     = 'localhost';
            $this->db_name  = 'expense_tracker';
            $this->username = 'root';
            $this->password = '';
        } else {
            // รองรับ environment variables (ปลอดภัยกว่า hardcode)
            $this->host     = getenv('DB_HOST')     ?: 'sql100.infinityfree.com';
            $this->db_name  = getenv('DB_NAME')     ?: 'if0_41301171_expense_tracker1';
            $this->username = getenv('DB_USER')     ?: 'if0_41301171';
            $this->password = getenv('DB_PASSWORD') ?: 'uTP7MF8HABb';
        }
    }

    public function getConnection(): PDO {
        if ($this->conn !== null) {
            return $this->conn;
        }
        try {
            $this->conn = new PDO(
                "mysql:host={$this->host};dbname={$this->db_name};charset=utf8mb4",
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
