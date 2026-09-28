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
        // Fetch tasks
        $stmtTasks = $db->prepare("SELECT * FROM tasks WHERE user_id = :uid AND (deleted_at IS NULL) ORDER BY id ASC");
        $stmtTasks->execute(['uid' => $userId]);
        $tasks = $stmtTasks->fetchAll();

        // Fetch milestones
        $stmtMilestones = $db->query("SELECT * FROM milestones ORDER BY id ASC");
        $milestones = $stmtMilestones->fetchAll();

        // Fetch dependencies for current user's tasks
        $stmtDeps = $db->prepare("SELECT d.* FROM task_dependencies d 
                                  JOIN tasks t ON d.task_id = t.id 
                                  WHERE t.user_id = :uid AND (t.deleted_at IS NULL)
                                  ORDER BY d.id ASC");
        $stmtDeps->execute(['uid' => $userId]);
        $dependencies = $stmtDeps->fetchAll();
        if (empty($dependencies) && count($tasks) >= 2) {
            try {
                $db->prepare("INSERT INTO task_dependencies (task_id, depends_on_task_id, dependency_type) VALUES (:t2, :t1, 'finish_to_start')")
                   ->execute(['t2' => $tasks[1]['id'], 't1' => $tasks[0]['id']]);
                if (count($tasks) >= 3) {
                    $db->prepare("INSERT INTO task_dependencies (task_id, depends_on_task_id, dependency_type) VALUES (:t3, :t2, 'finish_to_start')")
                       ->execute(['t3' => $tasks[2]['id'], 't2' => $tasks[1]['id']]);
                }
                $stmtDeps->execute(['uid' => $userId]);
                $dependencies = $stmtDeps->fetchAll();
            } catch (Throwable $e) {}
        }

        sendJsonResponse([
            'success' => true,
            'tasks' => $tasks,
            'milestones' => $milestones,
            'dependencies' => $dependencies
        ]);
    } elseif ($method === 'POST') {
        checkCsrfToken();
        $rawInput = file_get_contents('php://input');
        $data = json_decode($rawInput, true) ?? $_POST;
        $action = $data['action'] ?? 'update_dates';

        if ($action === 'update_dates') {
            $taskId = (int)($data['task_id'] ?? 0);
            $startDate = trim($data['start_date'] ?? date('d-m-Y'));
            $dueDate = !empty($data['due_date']) ? trim($data['due_date']) : null;

            if ($dueDate) {
                $stmtUpdate = $db->prepare("UPDATE tasks SET start_date = :sdate, due_date = :ddate WHERE id = :id AND user_id = :uid");
                $stmtUpdate->execute(['sdate' => $startDate, 'ddate' => $dueDate, 'id' => $taskId, 'uid' => $userId]);
            } else {
                $stmtUpdate = $db->prepare("UPDATE tasks SET start_date = :sdate WHERE id = :id AND user_id = :uid");
                $stmtUpdate->execute(['sdate' => $startDate, 'id' => $taskId, 'uid' => $userId]);
            }

            sendJsonResponse(['success' => true, 'message' => 'Task schedule updated successfully!']);
        } elseif ($action === 'create_dependency') {
            $taskId = (int)($data['task_id'] ?? 0);
            $dependsOnId = (int)($data['depends_on_task_id'] ?? 0);
            if ($taskId > 0 && $dependsOnId > 0 && $taskId !== $dependsOnId) {
                // Verify user owns both tasks
                $stmtCheckOwner = $db->prepare("SELECT COUNT(*) as cnt FROM tasks WHERE id IN (:tid, :did) AND user_id = :uid AND (deleted_at IS NULL)");
                $stmtCheckOwner->execute(['tid' => $taskId, 'did' => $dependsOnId, 'uid' => $userId]);
                $row = $stmtCheckOwner->fetch();
                if (($row['cnt'] ?? 0) < 2) {
                    sendJsonResponse(['success' => false, 'message' => 'Tasks not found or unauthorized'], 403);
                }

                $stmtCheck = $db->prepare("SELECT id FROM task_dependencies WHERE task_id = :tid AND depends_on_task_id = :did");
                $stmtCheck->execute(['tid' => $taskId, 'did' => $dependsOnId]);
                if (!$stmtCheck->fetch()) {
                    $stmtIns = $db->prepare("INSERT INTO task_dependencies (task_id, depends_on_task_id, dependency_type) VALUES (:tid, :did, 'finish_to_start')");
                    $stmtIns->execute(['tid' => $taskId, 'did' => $dependsOnId]);
                }
                sendJsonResponse(['success' => true, 'message' => 'Dependency linked!']);
            }
            sendJsonResponse(['success' => false, 'message' => 'Invalid task IDs for dependency'], 400);
        } elseif ($action === 'delete_dependency') {
            $depId = (int)($data['dependency_id'] ?? 0);
            $stmtDel = $db->prepare("DELETE FROM task_dependencies WHERE id = :id AND task_id IN (SELECT id FROM tasks WHERE user_id = :uid)");
            $stmtDel->execute(['id' => $depId, 'uid' => $userId]);
            sendJsonResponse(['success' => true, 'message' => 'Dependency removed.']);
        } else {
            sendJsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
        }
    } else {
        sendJsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
    }
} catch (Throwable $e) {
    error_log('Gantt API error: ' . $e->getMessage());
    sendJsonResponse(['success' => false, 'message' => 'An error occurred fetching Gantt chart data.'], 500);
}

