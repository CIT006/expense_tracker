<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
// Use the enhanced class that the user originally saved in api/transactions.php
require_once __DIR__ . '/../models/Transaction.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    $user = requireAuthenticatedUser();
    $transaction = new Transaction($db);
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'GET') {
        $user_id = (int)$user['id'];
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 100;
        $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
        
        $data = $transaction->readAll($user_id, $limit, $offset);
        echo json_encode(['success' => true, 'data' => $data]);
        
    } elseif ($method === 'POST') {
        $data = json_decode(file_get_contents("php://input"), true);
        if (!$data) {
            throw new Exception("Invalid JSON input");
        }
        $data['user_id'] = (int)$user['id'];
        $result = $transaction->create($data);
        echo json_encode($result);
        
    } elseif ($method === 'PUT') {
        $data = json_decode(file_get_contents("php://input"), true);
        if (!$data) {
            throw new Exception("Invalid JSON input");
        }
        $data['user_id'] = (int)$user['id'];
        $result = $transaction->update($data);
        echo json_encode($result);
        
    } elseif ($method === 'DELETE') {
        $user_id = (int)$user['id'];
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        if ($id <= 0) {
            throw new Exception("Invalid ID");
        }
        
        $result = $transaction->delete($id, $user_id);
        echo json_encode($result);
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
