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

    // Fetch Current User Name
    $stmtUser = $db->prepare("SELECT name FROM users WHERE id = :uid");
    $stmtUser->execute(['uid' => $userId]);
    $currentUser = $stmtUser->fetch();
    $currentUserName = $currentUser['name'] ?? 'User';

    if ($method === 'GET') {
        $stmt = $db->query("SELECT * FROM group_assignments ORDER BY id DESC");
        $assignments = $stmt->fetchAll();
        sendJsonResponse(['success' => true, 'assignments' => $assignments]);
    } elseif ($method === 'POST') {
        checkCsrfToken();
        $rawInput = file_get_contents('php://input');
        $data = json_decode($rawInput, true) ?? $_POST;
        $action = $data['action'] ?? 'create';

        if ($action === 'create') {
            $title = trim($data['title'] ?? '');
            $courseName = trim($data['course_name'] ?? 'General');
            $dueDate = trim($data['due_date'] ?? 'Next Week');

            if (empty($title)) {
                sendJsonResponse(['success' => false, 'message' => 'Title is required'], 400);
            }

            $stmtInsert = $db->prepare("INSERT INTO group_assignments (created_by_user_id, title, course_name, due_date, status) VALUES (:cb, :title, :cname, :ddate, 'In Progress')");
            $stmtInsert->execute([
                'cb' => $userId,
                'title' => $title,
                'cname' => $courseName,
                'ddate' => $dueDate
            ]);

            sendJsonResponse([
                'success' => true,
                'message' => 'Group assignment created successfully!',
                'assignment_id' => $db->lastInsertId()
            ]);
        } elseif ($action === 'complete') {
            $assignmentId = (int)($data['assignment_id'] ?? 0);
            if ($assignmentId <= 0) {
                sendJsonResponse(['success' => false, 'message' => 'Valid assignment_id is required'], 400);
            }

            $stmtUpdate = $db->prepare("UPDATE group_assignments SET status = 'Completed', completed_by_user_id = :uid, completed_by_user_name = :uname WHERE id = :id");
            $stmtUpdate->execute([
                'uid' => $userId,
                'uname' => $currentUserName,
                'id' => $assignmentId
            ]);

            sendJsonResponse([
                'success' => true,
                'message' => "Assignment marked as completed by {$currentUserName}!",
                'completed_by' => $currentUserName
            ]);
        } else {
            sendJsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
        }
    } else {
        sendJsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
    }
} catch (Throwable $e) {
    error_log('Assignments API error: ' . $e->getMessage());
    sendJsonResponse(['success' => false, 'message' => 'An error occurred managing assignments.'], 500);
}

