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

    $projectId = (int)($data['project_id'] ?? 0);
    $isStarred = isset($data['is_starred']) ? (int)$data['is_starred'] : 1;

    $stmt = $db->prepare("UPDATE courses SET is_starred = :starred WHERE id = :id AND user_id = :uid");
    $stmt->execute([
        'starred' => $isStarred,
        'id' => $projectId,
        'uid' => $userId
    ]);

    sendJsonResponse([
        'success' => true,
        'message' => 'Project favorite updated',
        'is_starred' => $isStarred
    ]);
} catch (Throwable $e) {
    error_log('Toggle favorite error: ' . $e->getMessage());
    sendJsonResponse(['success' => false, 'message' => 'An error occurred updating favorite status.'], 500);
}

