<?php
require_once __DIR__ . '/../config/db.php';
startSecureSession();

try {
    if (!isset($_SESSION['user_id'])) {
        sendJsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    $userId = (int)$_SESSION['user_id'];
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        session_write_close();
        $db = getDbConnection();

        $stmt = $db->prepare("SELECT id, user_id, task_name, project_name, start_date, due_date, priority, assigned_to, status, time_log, created_at 
                              FROM tasks 
                              WHERE user_id = :uid AND deleted_at IS NULL 
                              ORDER BY id DESC");
        $stmt->execute(['uid' => $userId]);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
        $data = getJsonRequestData();
        session_write_close();
        $db = getDbConnection();
        
        $taskId = (int)($data['task_id'] ?? 0);
        $newStatus = trim($data['status'] ?? '');
        $validStatuses = ['Open', 'In Progress', 'Review', 'Done'];

        if ($taskId <= 0 || !in_array($newStatus, $validStatuses, true)) {
            sendJsonResponse(['success' => false, 'message' => 'Invalid task or status selection.'], 400);
        }

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
