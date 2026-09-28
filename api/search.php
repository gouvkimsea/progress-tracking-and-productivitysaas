<?php
require_once __DIR__ . '/../config/db.php';
startSecureSession();

try {
    if (!isset($_SESSION['user_id'])) {
        sendJsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    $db = getDbConnection();
    $userId = (int)$_SESSION['user_id'];
    $query = trim($_GET['q'] ?? '');

    if (strlen($query) < 1) {
        sendJsonResponse(['success' => true, 'results' => []]);
    }

    $qLike = "%{$query}%";

    // 1. Search Courses / Projects
    $stmtProjects = $db->prepare("SELECT id, name as title, 'project' as type, category as subtext FROM courses WHERE user_id = :uid AND name LIKE :q");
    $stmtProjects->execute(['uid' => $userId, 'q' => $qLike]);
    $projects = $stmtProjects->fetchAll();

    // 2. Search Tasks
    $stmtTasks = $db->prepare("SELECT id, task_name as title, 'task' as type, status as subtext FROM tasks WHERE user_id = :uid AND (deleted_at IS NULL) AND task_name LIKE :q");
    $stmtTasks->execute(['uid' => $userId, 'q' => $qLike]);
    $tasks = $stmtTasks->fetchAll();

    // 3. Search Users
    $stmtUsers = $db->prepare("SELECT id, name as title, 'user' as type, email as subtext FROM users WHERE name LIKE :q1 OR email LIKE :q2");
    $stmtUsers->execute(['q1' => $qLike, 'q2' => $qLike]);
    $users = $stmtUsers->fetchAll();

    $results = array_merge($projects, $tasks, $users);

    sendJsonResponse(['success' => true, 'results' => $results]);
} catch (Throwable $e) {
    error_log('Search API error: ' . $e->getMessage());
    sendJsonResponse(['success' => false, 'message' => 'An error occurred performing search.'], 500);
}

