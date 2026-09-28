<?php
require_once __DIR__ . '/../config/db.php';
startSecureSession();

try {
    if (!isset($_SESSION['user_id'])) {
        sendJsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    $db = getDbConnection();
    $userId = (int)$_SESSION['user_id'];
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        $stmt = $db->prepare("SELECT * FROM tasks WHERE user_id = :uid AND (deleted_at IS NULL) ORDER BY id DESC");
        $stmt->execute(['uid' => $userId]);
        $tasks = $stmt->fetchAll();

        $columns = [
            'Open' => [],
            'In Progress' => [],
            'Review' => [],
            'Done' => []
        ];

        foreach ($tasks as $t) {
            $status = $t['status'] ?? 'Open';
            if (!isset($columns[$status])) {
                $columns[$status] = [];
            }
            $columns[$status][] = $t;
        }

        sendJsonResponse(['success' => true, 'kanban' => $columns]);
    } elseif ($method === 'POST') {
        checkCsrfToken();
        $rawInput = file_get_contents('php://input');
        $data = json_decode($rawInput, true) ?? $_POST;
        
        $taskId = (int)($data['task_id'] ?? 0);
        $newStatus = trim($data['status'] ?? 'Open');

        $stmt = $db->prepare("UPDATE tasks SET status = :status WHERE id = :id AND user_id = :uid");
        $stmt->execute(['status' => $newStatus, 'id' => $taskId, 'uid' => $userId]);

        sendJsonResponse(['success' => true, 'message' => "Task moved to {$newStatus}"]);
    } else {
        sendJsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
    }
} catch (Throwable $e) {
    error_log('Kanban API error: ' . $e->getMessage());
    sendJsonResponse(['success' => false, 'message' => 'An error occurred updating Kanban board.'], 500);
}

