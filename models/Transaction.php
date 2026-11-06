<?php
/**
 * Transaction.php — Transaction Model
 * เพิ่ม input validation, sanitization, และ pagination
 */
class Transaction {
    private PDO $conn;
    private string $table = 'transactions';

    public function __construct(PDO $db) {
        $this->conn = $db;
    }

    // ── CREATE ────────────────────────────────────
    public function create(array $data): array {
        $this->validate($data);
        try {
            $stmt = $this->conn->prepare(
                "INSERT INTO {$this->table}
                 (user_id, type, category_id, amount, description, transaction_date)
                 VALUES (:user_id, :type, :category_id, :amount, :description, :transaction_date)"
            );
            $stmt->execute([
                ':user_id'          => intval($data['user_id'] ?? 1),
                ':type'             => $data['type'],
                ':category_id'      => intval($data['category_id']),
                ':amount'           => round(floatval($data['amount']), 2),
                ':description'      => mb_substr(strip_tags($data['description']), 0, 500),
                ':transaction_date' => $data['transaction_date'],
            ]);
            $id = (int)$this->conn->lastInsertId();
            $created = $this->readOne($id, (int)($data['user_id'] ?? 0));
            return $created
                ? ['success' => true, 'id' => $id, 'data' => $created]
                : ['success' => false, 'message' => 'บันทึกไม่สำเร็จ กรุณาลองใหม่อีกครั้ง'];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ── READ ALL ──────────────────────────────────
    public function readAll(int $user_id, int $limit = 100, int $offset = 0): array {
        try {
            $limit  = min(max(1, $limit), 1000);
            $offset = max(0, $offset);
            $stmt = $this->conn->prepare(
                "SELECT t.*,
                        c.name           AS category_name,
                        c.name_th        AS category_name_th,
                        c.icon           AS category_icon,
                        c.color          AS category_color
                 FROM {$this->table} t
                 LEFT JOIN categories c ON t.category_id = c.id
                 WHERE t.user_id = :user_id
                 ORDER BY t.transaction_date DESC, t.created_at DESC
                 LIMIT :limit OFFSET :offset"
            );
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->bindParam(':limit',   $limit,   PDO::PARAM_INT);
            $stmt->bindParam(':offset',  $offset,  PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    // ── READ ONE ──────────────────────────────────
    public function readOne(int $id, int $user_id): ?array {
        try {
            $stmt = $this->conn->prepare(
                "SELECT t.*,
                        c.name    AS category_name,
                        c.name_th AS category_name_th,
                        c.icon    AS category_icon,
                        c.color   AS category_color
                 FROM {$this->table} t
                 LEFT JOIN categories c ON t.category_id = c.id
                 WHERE t.id = :id AND t.user_id = :user_id LIMIT 1"
            );
            $stmt->execute([':id' => $id, ':user_id' => $user_id]);
            return $stmt->fetch() ?: null;
        } catch (PDOException $e) {
            return null;
        }
    }

    // ── UPDATE ────────────────────────────────────
    public function update(array $data): array {
        $this->validate($data);
        try {
            $stmt = $this->conn->prepare(
                "UPDATE {$this->table}
                 SET type = :type, category_id = :category_id, amount = :amount,
                     description = :description, transaction_date = :transaction_date
                 WHERE id = :id AND user_id = :user_id"
            );
            $stmt->execute([
                ':id'               => intval($data['id']),
                ':user_id'          => intval($data['user_id']),
                ':type'             => $data['type'],
                ':category_id'      => intval($data['category_id']),
                ':amount'           => round(floatval($data['amount']), 2),
                ':description'      => mb_substr(strip_tags($data['description']), 0, 500),
                ':transaction_date' => $data['transaction_date'],
            ]);
            $updated = $this->readOne((int)$data['id'], (int)$data['user_id']);
            return $updated
                ? ['success' => true, 'data' => $updated]
                : ['success' => false, 'message' => 'ไม่พบรายการหรือไม่มีสิทธิ์แก้ไข'];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ── DELETE ────────────────────────────────────
    public function delete(int $id, int $user_id): array {
        try {
            $stmt = $this->conn->prepare(
                "DELETE FROM {$this->table} WHERE id = :id AND user_id = :user_id"
            );
            $stmt->execute([':id' => $id, ':user_id' => $user_id]);
            $check = $this->conn->prepare("SELECT id FROM {$this->table} WHERE id = :id AND user_id = :user_id LIMIT 1");
            $check->execute([':id' => $id, ':user_id' => $user_id]);
            return !$check->fetch()
                ? ['success' => true]
                : ['success' => false, 'message' => 'ไม่สามารถลบรายการได้'];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ── SUMMARY ───────────────────────────────────
    public function getSummary(int $user_id, ?string $startDate = null, ?string $endDate = null): array {
        try {
            $sql = "SELECT
                        COALESCE(SUM(CASE WHEN type='income'  THEN amount ELSE 0 END), 0) AS total_income,
                        COALESCE(SUM(CASE WHEN type='expense' THEN amount ELSE 0 END), 0) AS total_expense,
                        COUNT(*) AS total_transactions
                    FROM {$this->table}
                    WHERE user_id = :user_id";
            $params = [':user_id' => $user_id];
            if ($startDate && $endDate) {
                $sql .= " AND transaction_date BETWEEN :start AND :end";
                $params[':start'] = $startDate;
                $params[':end']   = $endDate;
            }
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetch() ?: ['total_income' => 0, 'total_expense' => 0, 'total_transactions' => 0];
        } catch (PDOException $e) {
            return ['total_income' => 0, 'total_expense' => 0, 'total_transactions' => 0];
        }
    }

    // ── MONTHLY SUMMARY ───────────────────────────
    public function getMonthlySummary(int $user_id, ?int $year = null): array {
        try {
            $sql = "SELECT
                        YEAR(transaction_date)  AS year,
                        MONTH(transaction_date) AS month,
                        COALESCE(SUM(CASE WHEN type='income'  THEN amount ELSE 0 END), 0) AS total_income,
                        COALESCE(SUM(CASE WHEN type='expense' THEN amount ELSE 0 END), 0) AS total_expense
                    FROM {$this->table}
                    WHERE user_id = :user_id";
            $params = [':user_id' => $user_id];
            if ($year) {
                $sql .= " AND YEAR(transaction_date) = :year";
                $params[':year'] = $year;
            }
            $sql .= " GROUP BY YEAR(transaction_date), MONTH(transaction_date)
                      ORDER BY year DESC, month DESC
                      LIMIT 24";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    // ── BY CATEGORY ───────────────────────────────
    public function getByCategory(int $user_id, string $startDate, string $endDate): array {
        try {
            $stmt = $this->conn->prepare(
                "SELECT c.name, c.name_th, c.icon, c.color,
                        COALESCE(SUM(t.amount), 0) AS total_amount,
                        COUNT(t.id) AS transaction_count
                 FROM {$this->table} t
                 LEFT JOIN categories c ON t.category_id = c.id
                 WHERE t.user_id = :user_id
                   AND t.type = 'expense'
                   AND t.transaction_date BETWEEN :start AND :end
                 GROUP BY t.category_id
                 ORDER BY total_amount DESC
                 LIMIT 10"
            );
            $stmt->execute([':user_id' => $user_id, ':start' => $startDate, ':end' => $endDate]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    // ── VALIDATE ──────────────────────────────────
    private function validate(array $data): void {
        if (!in_array($data['type'] ?? '', ['income', 'expense'])) {
            throw new InvalidArgumentException('ประเภทรายการไม่ถูกต้อง');
        }
        if (floatval($data['amount'] ?? 0) <= 0) {
            throw new InvalidArgumentException('จำนวนเงินต้องมากกว่า 0');
        }
        if (empty($data['description'])) {
            throw new InvalidArgumentException('กรุณากรอกรายละเอียด');
        }
        if (empty($data['transaction_date']) || !strtotime($data['transaction_date'])) {
            throw new InvalidArgumentException('วันที่ไม่ถูกต้อง');
        }
    }
}
?>