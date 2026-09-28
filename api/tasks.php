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
        sendJsonResponse(['success' => true, 'tasks' => $tasks]);
    } elseif ($method === 'POST') {
        checkCsrfToken();
        $rawInput = file_get_contents('php://input');
        $data = json_decode($rawInput, true) ?? $_POST;
        $action = $data['action'] ?? 'create';

        if ($action === 'create') {
            $taskName = trim($data['task_name'] ?? '');
            $projectName = trim($data['project_name'] ?? '2dapp');
            $startDate = trim($data['start_date'] ?? date('d-m-Y'));
            $dueDate = !empty($data['due_date']) ? trim($data['due_date']) : date('d-m-Y', strtotime('+3 days'));
            $assignedTo = trim($data['assigned_to'] ?? 'unassigned');
            $status = trim($data['status'] ?? 'Open');
            $priority = trim($data['priority'] ?? 'Medium');
            if (!in_array($priority, ['Urgent', 'High', 'Medium', 'Low'], true)) {
                $priority = 'Medium';
            }
            $timeLog = (int)($data['time_log'] ?? 0);

            if (empty($taskName)) {
                sendJsonResponse(['success' => false, 'message' => 'Task name is required'], 400);
            }

            $stmtInsert = $db->prepare("INSERT INTO tasks (user_id, task_name, project_name, start_date, due_date, priority, assigned_to, status, time_log)
                VALUES (:uid, :tname, :pname, :sdate, :ddate, :prio, :assigned, :status, :tlog)");
            $stmtInsert->execute([
                'uid' => $userId,
                'tname' => $taskName,
                'pname' => $projectName,
                'sdate' => $startDate,
                'ddate' => $dueDate,
                'prio' => $priority,
                'assigned' => $assignedTo,
                'status' => $status,
                'tlog' => $timeLog
            ]);

            sendJsonResponse([
                'success' => true,
                'message' => 'Task created successfully!',
                'task_id' => $db->lastInsertId()
            ]);
        } elseif ($action === 'update_status') {
            $taskId = (int)($data['task_id'] ?? 0);
            $newStatus = trim($data['status'] ?? 'Done');

            $stmtUpdate = $db->prepare("UPDATE tasks SET status = :status WHERE id = :id AND user_id = :uid");
            $stmtUpdate->execute([
                'status' => $newStatus,
                'id' => $taskId,
                'uid' => $userId
            ]);

            sendJsonResponse([
                'success' => true,
                'message' => "Task status updated to {$newStatus}!",
                'new_status' => $newStatus
            ]);
        } elseif ($action === 'delete') {
            $taskId = (int)($data['task_id'] ?? 0);
            // Remove associated dependencies first to maintain integrity
            $db->prepare("DELETE FROM task_dependencies WHERE task_id = :tid OR depends_on_task_id = :did")
                ->execute(['tid' => $taskId, 'did' => $taskId]);
            $stmtDelete = $db->prepare("DELETE FROM tasks WHERE id = :id AND user_id = :uid");
            $stmtDelete->execute(['id' => $taskId, 'uid' => $userId]);

            sendJsonResponse([
                'success' => true,
                'message' => 'Task deleted successfully!'
            ]);
        } else {
            sendJsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
        }
    } else {
        sendJsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
    }
} catch (Throwable $e) {
    error_log('Tasks API error: ' . $e->getMessage());
    sendJsonResponse(['success' => false, 'message' => 'An error occurred managing tasks.'], 500);
}

