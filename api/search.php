<?php
require_once __DIR__ . '/../config/db.php';
startSecureSession();

try {
    if (!isset($_SESSION['user_id'])) {
        sendJsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    $userId = (int)$_SESSION['user_id'];
    $query = trim($_GET['q'] ?? '');

    // Early exit for short queries BEFORE establishing database connection!
    if (strlen($query) < 2) {
        session_write_close();
        sendJsonResponse(['success' => true, 'results' => []]);
    }

    session_write_close();
    $db = getDbConnection();

    $qLike = "%{$query}%";

    // 1. Search Courses / Projects with limit
    $stmtProjects = $db->prepare("SELECT id, name as title, 'project' as type, category as subtext 
                                  FROM courses 
                                  WHERE user_id = :uid AND name LIKE :q 
                                  LIMIT 8");
    $stmtProjects->execute(['uid' => $userId, 'q' => $qLike]);
    $projects = $stmtProjects->fetchAll(PDO::FETCH_ASSOC);

    // 2. Search Tasks with limit
    $stmtTasks = $db->prepare("SELECT id, task_name as title, 'task' as type, status as subtext 
                               FROM tasks 
                               WHERE user_id = :uid AND deleted_at IS NULL AND task_name LIKE :q 
                               LIMIT 8");
    $stmtTasks->execute(['uid' => $userId, 'q' => $qLike]);
    $tasks = $stmtTasks->fetchAll(PDO::FETCH_ASSOC);

    // 3. Search Users with limit
    $stmtUsers = $db->prepare("SELECT id, name as title, 'user' as type, email as subtext 
                               FROM users 
                               WHERE name LIKE :q1 OR email LIKE :q2 
                               LIMIT 5");
    $stmtUsers->execute(['q1' => $qLike, 'q2' => $qLike]);
    $users = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

    $results = array_merge($projects, $tasks, $users);

    sendJsonResponse(['success' => true, 'results' => $results]);
} catch (Throwable $e) {
    error_log('Search API error: ' . $e->getMessage());
    sendJsonResponse(['success' => false, 'message' => 'An error occurred performing search.'], 500);
}
