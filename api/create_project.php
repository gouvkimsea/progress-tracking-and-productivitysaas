<?php
require_once __DIR__ . '/../config/db.php';
startSecureSession();

try {
    if (!isset($_SESSION['user_id'])) {
        sendJsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    $db = getDbConnection();
    $userId = (int)$_SESSION['user_id'];
    checkCsrfToken();
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true) ?? $_POST;

    $action = $data['action'] ?? 'create';
    if ($action === 'delete') {
        $projId = (int)($data['id'] ?? 0);
        $db->prepare("DELETE FROM courses WHERE id = :id AND user_id = :uid")->execute(['id' => $projId, 'uid' => $userId]);
        sendJsonResponse(['success' => true, 'message' => 'Project deleted successfully']);
    }

    $name = trim($data['name'] ?? '');
    $code = strtoupper(trim($data['code'] ?? 'PRJ'));
    $category = trim($data['category'] ?? 'Design');
    $level = trim($data['level'] ?? 'Intermediate');
    $totalModules = (int)($data['total_modules'] ?? 10);

    if (empty($name)) {
        sendJsonResponse(['success' => false, 'message' => 'Project Name is required.'], 400);
    }

    if (strlen($code) > 4) {
        $code = substr($code, 0, 4);
    }

    // Default gradient colors
    $gradients = [
        'linear-gradient(145deg,#8B7CF0,#5A46E0)',
        'linear-gradient(145deg,#FBAE68,#F2994A)',
        'linear-gradient(145deg,#6FC1F0,#4FA3E0)',
        'linear-gradient(145deg,#7FD9A5,#2FBE73)'
    ];
    $bgGradient = $gradients[array_rand($gradients)];

    $stmt = $db->prepare("INSERT INTO courses (user_id, code, name, category, level, total_modules, completed_modules, progress_pct, bg_gradient, ring_color)
        VALUES (:uid, :code, :name, :cat, :lvl, :tm, 0, 0, :bg, '#6C5CE7')");
    $stmt->execute([
        'uid' => $userId,
        'code' => $code,
        'name' => $name,
        'cat' => $category,
        'lvl' => $level,
        'tm' => $totalModules,
        'bg' => $bgGradient
    ]);

    sendJsonResponse([
        'success' => true,
        'message' => 'New project created successfully!',
        'project_id' => $db->lastInsertId()
    ]);
} catch (Throwable $e) {
    error_log('Create project error: ' . $e->getMessage());
    sendJsonResponse(['success' => false, 'message' => 'An error occurred creating the project.'], 500);
}
