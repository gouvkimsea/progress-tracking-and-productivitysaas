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
        // Auto-detect overdue and due-today tasks to populate dynamic notifications
        $stmtTasks = $db->prepare("SELECT * FROM tasks WHERE user_id = :uid AND (deleted_at IS NULL) AND status != 'Done'");
        $stmtTasks->execute(['uid' => $userId]);
        $activeTasks = $stmtTasks->fetchAll();

        $todayStr = date('Y-m-d');
        $todayTs = strtotime('today');

        foreach ($activeTasks as $t) {
            $due = $t['due_date'] ?? '';
            if (!empty($due)) {
                $dueTs = strtotime(str_replace('/', '-', $due));
                if ($dueTs) {
                    $dueFormatted = date('Y-m-d', $dueTs);
                    $taskTitle = $t['task_name'] ?? 'Task';

                    if ($dueFormatted < $todayStr) {
                        // Overdue notification
                        $notifTitle = "Task Overdue: {$taskTitle}";
                        $checkStmt = $db->prepare("SELECT id FROM notifications WHERE user_id = :uid AND title = :title");
                        $checkStmt->execute(['uid' => $userId, 'title' => $notifTitle]);
                        if (!$checkStmt->fetch()) {
                            $db->prepare("INSERT INTO notifications (user_id, title, message, is_read) VALUES (:uid, :title, :msg, 0)")
                               ->execute(['uid' => $userId, 'title' => $notifTitle, 'msg' => "Deadline passed on " . date('M j', $dueTs) . ". Project: " . ($t['project_name'] ?? 'General')]);
                        }
                    } elseif ($dueFormatted === $todayStr) {
                        // Due today notification
                        $notifTitle = "Due Today: {$taskTitle}";
                        $checkStmt = $db->prepare("SELECT id FROM notifications WHERE user_id = :uid AND title = :title");
                        $checkStmt->execute(['uid' => $userId, 'title' => $notifTitle]);
                        if (!$checkStmt->fetch()) {
                            $db->prepare("INSERT INTO notifications (user_id, title, message, is_read) VALUES (:uid, :title, :msg, 0)")
                               ->execute(['uid' => $userId, 'title' => $notifTitle, 'msg' => "Scheduled for completion today! Project: " . ($t['project_name'] ?? 'General')]);
                        }
                    }
                }
            }
        }

        // Fetch user notifications
        $stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = :uid ORDER BY is_read ASC, id DESC LIMIT 20");
        $stmt->execute(['uid' => $userId]);
        $notifications = $stmt->fetchAll();

        $unreadCount = 0;
        foreach ($notifications as $n) {
            if (empty($n['is_read'])) $unreadCount++;
        }

        sendJsonResponse([
            'success' => true,
            'unread_count' => $unreadCount,
            'notifications' => $notifications
        ]);
    } elseif ($method === 'POST') {
        checkCsrfToken();
        $rawInput = file_get_contents('php://input');
        $data = json_decode($rawInput, true) ?? $_POST;
        $action = $data['action'] ?? 'mark_all_read';

        if ($action === 'mark_all_read') {
            $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = :uid");
            $stmt->execute(['uid' => $userId]);
            sendJsonResponse(['success' => true, 'message' => 'All notifications marked as read']);
        } elseif ($action === 'mark_read') {
            $notifId = (int)($data['id'] ?? 0);
            $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :uid");
            $stmt->execute(['id' => $notifId, 'uid' => $userId]);
            sendJsonResponse(['success' => true, 'message' => 'Notification marked as read']);
        } else {
            sendJsonResponse(['success' => false, 'message' => 'Unknown action'], 400);
        }
    } else {
        sendJsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
    }
} catch (Throwable $e) {
    error_log("Notifications API error: " . $e->getMessage());
    sendJsonResponse(['success' => false, 'message' => 'An error occurred.'], 500);
}
