<?php
function startAuthSession(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1', '::1'], true)
    ]);
    session_start();
}

function getAuthenticatedUser(): ?array {
    startAuthSession();
    return isset($_SESSION['user']) ? $_SESSION['user'] : null;
}

function requireAuthenticatedUser(): array {
    $user = getAuthenticatedUser();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'กรุณาเข้าสู่ระบบ'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    return $user;
}
?>
