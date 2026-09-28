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
    $status = trim($data['status'] ?? 'No status');

    $stmt = $db->prepare("UPDATE courses SET status = :status WHERE id = :id AND user_id = :uid");
    $stmt->execute([
        'status' => $status,
        'id' => $projectId,
        'uid' => $userId
    ]);

    sendJsonResponse([
        'success' => true,
        'message' => "Project status updated to {$status}",
        'status' => $status
    ]);
} catch (Throwable $e) {
    error_log('Update project status error: ' . $e->getMessage());
    sendJsonResponse(['success' => false, 'message' => 'An error occurred updating project status.'], 500);
}

