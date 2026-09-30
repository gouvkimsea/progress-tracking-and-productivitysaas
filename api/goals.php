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
        $stmt = $db->prepare("SELECT id, user_id, title, category, target_value, current_value, unit, due_date, status, created_at 
                              FROM project_goals 
                              WHERE user_id = :uid 
                              ORDER BY id DESC");
        $stmt->execute(['uid' => $userId]);
        $goals = $stmt->fetchAll(PDO::FETCH_ASSOC);
        sendJsonResponse(['success' => true, 'goals' => $goals]);
    } elseif ($method === 'POST') {
        checkCsrfToken();
        $data = getJsonRequestData();
        session_write_close();
        $db = getDbConnection();

        $action = $data['action'] ?? 'create';

        if ($action === 'create') {
            $title = trim($data['title'] ?? '');
            $category = trim($data['category'] ?? 'Productivity');
            $targetValue = max(1, (int)($data['target_value'] ?? 100));
            $currentValue = max(0, (int)($data['current_value'] ?? 0));
            $unit = trim($data['unit'] ?? '%');
            $dueDate = trim($data['due_date'] ?? 'End of Quarter');
            $status = trim($data['status'] ?? 'On Track');

            if (empty($title)) {
                sendJsonResponse(['success' => false, 'message' => 'Goal title is required.'], 400);
            }

            $stmt = $db->prepare("INSERT INTO project_goals (user_id, title, category, target_value, current_value, unit, due_date, status)
                                  VALUES (:uid, :title, :cat, :tval, :cval, :unit, :ddate, :st)");
            $stmt->execute([
                'uid' => $userId,
                'title' => $title,
                'cat' => $category,
                'tval' => $targetValue,
                'cval' => $currentValue,
                'unit' => $unit,
                'ddate' => $dueDate,
                'st' => $status
            ]);

            sendJsonResponse([
                'success' => true,
                'message' => 'Goal created successfully!',
                'goal_id' => (int)$db->lastInsertId()
            ]);
        } elseif ($action === 'update') {
            $goalId = (int)($data['goal_id'] ?? 0);
            $title = trim($data['title'] ?? '');
            $category = trim($data['category'] ?? 'Productivity');
            $targetValue = max(1, (int)($data['target_value'] ?? 100));
            $currentValue = max(0, (int)($data['current_value'] ?? 0));
            $unit = trim($data['unit'] ?? '%');
            $dueDate = trim($data['due_date'] ?? 'End of Quarter');
            $status = trim($data['status'] ?? 'On Track');

            if (empty($title)) {
                sendJsonResponse(['success' => false, 'message' => 'Objective title is required.'], 400);
            }

            $stmt = $db->prepare("UPDATE project_goals SET 
                                    title = :title, 
                                    category = :cat, 
                                    target_value = :tval, 
                                    current_value = :cval, 
                                    unit = :unit, 
                                    due_date = :ddate, 
                                    status = :st 
                                  WHERE id = :id AND user_id = :uid");
            $stmt->execute([
                'title' => $title,
                'cat' => $category,
                'tval' => $targetValue,
                'cval' => $currentValue,
                'unit' => $unit,
                'ddate' => $dueDate,
                'st' => $status,
                'id' => $goalId,
                'uid' => $userId
            ]);

            sendJsonResponse(['success' => true, 'message' => 'Objective updated successfully!']);
        } elseif ($action === 'update_progress') {
            $goalId = (int)($data['goal_id'] ?? 0);
            $currentValue = max(0, (int)($data['current_value'] ?? 0));
            $status = trim($data['status'] ?? 'On Track');

            $stmt = $db->prepare("UPDATE project_goals SET current_value = :cval, status = :st WHERE id = :id AND user_id = :uid");
            $stmt->execute([
                'cval' => $currentValue,
                'st' => $status,
                'id' => $goalId,
                'uid' => $userId
            ]);

            sendJsonResponse(['success' => true, 'message' => 'Goal progress updated!']);
        } elseif ($action === 'delete') {
            $goalId = (int)($data['goal_id'] ?? 0);
            $stmt = $db->prepare("DELETE FROM project_goals WHERE id = :id AND user_id = :uid");
            $stmt->execute(['id' => $goalId, 'uid' => $userId]);
            sendJsonResponse(['success' => true, 'message' => 'Goal deleted.']);
        } else {
            sendJsonResponse(['success' => false, 'message' => 'Unknown action'], 400);
        }
    } else {
        sendJsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
    }
} catch (Throwable $e) {
    error_log("Goals API error: " . $e->getMessage());
    sendJsonResponse(['success' => false, 'message' => 'An error occurred.'], 500);
}
