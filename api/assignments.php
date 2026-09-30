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

        $stmt = $db->query("SELECT id, created_by_user_id, title, course_name, due_date, description, task_planning, status, completed_by_user_id, completed_by_user_name, created_at 
                            FROM group_assignments 
                            ORDER BY id DESC 
                            LIMIT 100");
        $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        sendJsonResponse(['success' => true, 'assignments' => $assignments]);
    } elseif ($method === 'POST') {
        checkCsrfToken();
        $data = getJsonRequestData();
        $userName = $_SESSION['user_name'] ?? null;
        session_write_close();
        $db = getDbConnection();

        $action = $data['action'] ?? 'create';

        if ($action === 'create') {
            $title = trim($data['title'] ?? '');
            $courseName = trim($data['course_name'] ?? 'General');
            $dueDate = trim($data['due_date'] ?? 'Next Week');
            $description = trim($data['description'] ?? '');
            $taskPlanning = trim($data['task_planning'] ?? '');

            if (empty($title)) {
                sendJsonResponse(['success' => false, 'message' => 'Title is required'], 400);
            }

            $stmtInsert = $db->prepare("INSERT INTO group_assignments (created_by_user_id, title, course_name, due_date, description, task_planning, status) 
                                       VALUES (:cb, :title, :cname, :ddate, :desc, :plan, 'In Progress')");
            $stmtInsert->execute([
                'cb' => $userId,
                'title' => $title,
                'cname' => $courseName,
                'ddate' => $dueDate,
                'desc' => ($description !== '' ? $description : null),
                'plan' => ($taskPlanning !== '' ? $taskPlanning : null)
            ]);

            sendJsonResponse([
                'success' => true,
                'message' => 'Assignment created successfully!',
                'assignment_id' => (int)$db->lastInsertId()
            ]);
        } elseif ($action === 'update') {
            $assignmentId = (int)($data['assignment_id'] ?? 0);
            if ($assignmentId <= 0) {
                sendJsonResponse(['success' => false, 'message' => 'Valid assignment_id is required'], 400);
            }

            $title = trim($data['title'] ?? '');
            $courseName = trim($data['course_name'] ?? 'General');
            $dueDate = trim($data['due_date'] ?? 'Next Week');
            $description = trim($data['description'] ?? '');
            $taskPlanning = trim($data['task_planning'] ?? '');
            $status = trim($data['status'] ?? 'In Progress');

            if (empty($title)) {
                sendJsonResponse(['success' => false, 'message' => 'Title is required'], 400);
            }

            $stmtUpdate = $db->prepare("UPDATE group_assignments SET title = :title, course_name = :cname, due_date = :ddate, description = :desc, task_planning = :plan, status = :status WHERE id = :id");
            $stmtUpdate->execute([
                'title' => $title,
                'cname' => $courseName,
                'ddate' => $dueDate,
                'desc' => ($description !== '' ? $description : null),
                'plan' => ($taskPlanning !== '' ? $taskPlanning : null),
                'status' => $status,
                'id' => $assignmentId
            ]);

            sendJsonResponse([
                'success' => true,
                'message' => 'Assignment updated successfully!'
            ]);
        } elseif ($action === 'update_plan') {
            $assignmentId = (int)($data['assignment_id'] ?? 0);
            if ($assignmentId <= 0) {
                sendJsonResponse(['success' => false, 'message' => 'Valid assignment_id is required'], 400);
            }

            $taskPlanning = trim($data['task_planning'] ?? '');
            $stmtUpdate = $db->prepare("UPDATE group_assignments SET task_planning = :plan WHERE id = :id");
            $stmtUpdate->execute([
                'plan' => ($taskPlanning !== '' ? $taskPlanning : null),
                'id' => $assignmentId
            ]);

            sendJsonResponse([
                'success' => true,
                'message' => 'Task planning updated successfully!'
            ]);
        } elseif ($action === 'delete') {
            $assignmentId = (int)($data['assignment_id'] ?? 0);
            if ($assignmentId <= 0) {
                sendJsonResponse(['success' => false, 'message' => 'Valid assignment_id is required'], 400);
            }

            $stmtDelete = $db->prepare("DELETE FROM group_assignments WHERE id = :id");
            $stmtDelete->execute(['id' => $assignmentId]);

            sendJsonResponse([
                'success' => true,
                'message' => 'Assignment deleted successfully!'
            ]);
        } elseif ($action === 'complete') {
            $assignmentId = (int)($data['assignment_id'] ?? 0);
            if ($assignmentId <= 0) {
                sendJsonResponse(['success' => false, 'message' => 'Valid assignment_id is required'], 400);
            }

            if (!$userName) {
                $stmtUser = $db->prepare("SELECT name FROM users WHERE id = :uid");
                $stmtUser->execute(['uid' => $userId]);
                $currentUser = $stmtUser->fetch();
                $userName = $currentUser['name'] ?? 'User';
            }

            $stmtUpdate = $db->prepare("UPDATE group_assignments SET status = 'Completed', completed_by_user_id = :uid, completed_by_user_name = :uname WHERE id = :id");
            $stmtUpdate->execute([
                'uid' => $userId,
                'uname' => $userName,
                'id' => $assignmentId
            ]);

            sendJsonResponse([
                'success' => true,
                'message' => "Assignment marked as completed by {$userName}!",
                'completed_by' => $userName
            ]);
        } else {
            sendJsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
        }
    } else {
        sendJsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
    }
} catch (Throwable $e) {
    error_log("Assignments API error: " . $e->getMessage());
    sendJsonResponse(['success' => false, 'message' => 'An error occurred.'], 500);
}
