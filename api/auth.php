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

startAuthSession();
$action = $_GET['action'] ?? '';

try {
    if ($action === 'check' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        echo json_encode([
            'success' => true,
            'logged_in' => isset($_SESSION['user']),
            'user' => $_SESSION['user'] ?? null
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'logout' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true) ?: [];
        $identity = trim((string)($data['identity'] ?? ''));
        $password = (string)($data['password'] ?? '');

        if ($identity === '' || $password === '') {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'กรุณากรอกชื่อผู้ใช้หรืออีเมล และรหัสผ่าน'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $db = (new Database())->getConnection();
        $stmt = $db->prepare(
            'SELECT id, username, email, password, full_name FROM users
               WHERE (username = :username OR email = :email) AND is_active = 1 LIMIT 1'
        );
           $stmt->execute([':username' => $identity, ':email' => $identity]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        session_regenerate_id(true);
        unset($user['password']);
        $_SESSION['user'] = $user;
        $update = $db->prepare('UPDATE users SET last_login = NOW() WHERE id = :id');
        $update->execute([':id' => $user['id']]);

        echo json_encode(['success' => true, 'user' => $user], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true) ?: [];
        $username = trim((string)($data['username'] ?? ''));
        $email = trim((string)($data['email'] ?? ''));
        $password = (string)($data['password'] ?? '');
        $fullName = trim((string)($data['full_name'] ?? ''));

        if (!preg_match('/^[A-Za-z0-9_]{3,50}$/', $username)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'ชื่อผู้ใช้ต้องเป็นภาษาอังกฤษ ตัวเลข หรือ _ และยาว 3-50 ตัวอักษร'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'รูปแบบอีเมลไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if (strlen($password) < 6 || strlen($password) > 255) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if (strlen($fullName) > 100) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'ชื่อ-นามสกุลยาวเกินไป'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $db = (new Database())->getConnection();
        $check = $db->prepare('SELECT id FROM users WHERE username = :username OR email = :email LIMIT 1');
        $check->execute([':username' => $username, ':email' => $email]);
        if ($check->fetch()) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'ชื่อผู้ใช้หรืออีเมลนี้ถูกใช้งานแล้ว'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmt = $db->prepare(
            'INSERT INTO users (username, email, password, full_name)
             VALUES (:username, :email, :password, :full_name)'
        );
        $stmt->execute([
            ':username' => $username,
            ':email' => $email,
            ':password' => password_hash($password, PASSWORD_DEFAULT),
            ':full_name' => $fullName !== '' ? $fullName : null
        ]);

        $user = [
            'id' => (int)$db->lastInsertId(),
            'username' => $username,
            'email' => $email,
            'full_name' => $fullName !== '' ? $fullName : null
        ];
        session_regenerate_id(true);
        $_SESSION['user'] = $user;
        echo json_encode(['success' => true, 'user' => $user], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'คำขอไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดของระบบ'], JSON_UNESCAPED_UNICODE);
}
?>
