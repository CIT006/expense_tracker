<?php
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

try {
    $user = requireAuthenticatedUser();
    $db = (new Database())->getConnection();
    $userId = (int)$user['id'];

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $month = (int)($_GET['month'] ?? date('n'));
        $year = (int)($_GET['year'] ?? date('Y'));
        if ($month < 1 || $month > 12 || $year < 2000 || $year > 2100) {
            throw new InvalidArgumentException('ช่วงเวลาไม่ถูกต้อง');
        }

        $stmt = $db->prepare(
            'SELECT c.id AS category_id, c.name_th, c.icon, c.color,
                    COALESCE(b.amount, 0) AS budget_amount,
                    COALESCE(SUM(CASE WHEN t.type = "expense" THEN t.amount ELSE 0 END), 0) AS spent_amount
             FROM categories c
             LEFT JOIN budgets b ON b.category_id = c.id AND b.user_id = :budget_user
                AND b.budget_month = :budget_month AND b.budget_year = :budget_year
             LEFT JOIN transactions t ON t.category_id = c.id AND t.user_id = :transaction_user
                AND t.type = "expense" AND MONTH(t.transaction_date) = :transaction_month
                AND YEAR(t.transaction_date) = :transaction_year
             GROUP BY c.id, c.name_th, c.icon, c.color, b.amount
             ORDER BY c.id'
        );
        $stmt->execute([
            ':budget_user' => $userId,
            ':budget_month' => $month,
            ':budget_year' => $year,
            ':transaction_user' => $userId,
            ':transaction_month' => $month,
            ':transaction_year' => $year
        ]);
        echo json_encode(['success' => true, 'month' => $month, 'year' => $year, 'data' => $stmt->fetchAll()], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true) ?: [];
        $categoryId = (int)($data['category_id'] ?? 0);
        $month = (int)($data['month'] ?? 0);
        $year = (int)($data['year'] ?? 0);
        $amount = round((float)($data['amount'] ?? 0), 2);
        if ($categoryId <= 0 || $month < 1 || $month > 12 || $year < 2000 || $year > 2100 || $amount < 0) {
            throw new InvalidArgumentException('ข้อมูลงบประมาณไม่ถูกต้อง');
        }

        $category = $db->prepare('SELECT id FROM categories WHERE id = :id LIMIT 1');
        $category->execute([':id' => $categoryId]);
        if (!$category->fetch()) {
            throw new InvalidArgumentException('ไม่พบหมวดหมู่');
        }

        $stmt = $db->prepare(
            'INSERT INTO budgets (user_id, category_id, budget_month, budget_year, amount)
             VALUES (:user_id, :category_id, :month, :year, :amount)
             ON DUPLICATE KEY UPDATE amount = VALUES(amount)'
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':category_id' => $categoryId,
            ':month' => $month,
            ':year' => $year,
            ':amount' => $amount
        ]);
        echo json_encode(['success' => true, 'message' => 'บันทึกงบประมาณแล้ว'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการจัดการงบประมาณ'], JSON_UNESCAPED_UNICODE);
}
?>
