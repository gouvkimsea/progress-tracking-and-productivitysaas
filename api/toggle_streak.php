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

    // Parse input
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true) ?? $_POST;

    $action = $data['action'] ?? '';
    if ($action === 'reset') {
        $db->prepare("UPDATE weekly_streaks SET is_completed = 0 WHERE user_id = :uid")->execute(['uid' => $userId]);
        $db->prepare("UPDATE user_stats SET learning_streak = 0 WHERE user_id = :uid")->execute(['uid' => $userId]);
        sendJsonResponse(['success' => true, 'message' => 'Week streak reset successfully']);
    }

    if (!isset($data['day_index'])) {
        sendJsonResponse(['success' => false, 'message' => 'day_index is required (0 to 6)'], 400);
    }
    $dayIndex = (int)$data['day_index'];
    if ($dayIndex < 0 || $dayIndex > 6) {
        sendJsonResponse(['success' => false, 'message' => 'day_index must be between 0 and 6'], 400);
    }

    // Get current completion status
    $stmtSelect = $db->prepare("SELECT is_completed FROM weekly_streaks WHERE user_id = :uid AND day_index = :didx");
    $stmtSelect->execute(['uid' => $userId, 'didx' => $dayIndex]);
    $current = $stmtSelect->fetch();

    $newStatus = ($current && (int)$current['is_completed'] === 1) ? 0 : 1;
    if (isset($data['status'])) {
        $newStatus = (int)$data['status'] ? 1 : 0;
    }

    // Update streak record
    $stmtUpdate = $db->prepare("UPDATE weekly_streaks SET is_completed = :status, updated_at = CURRENT_TIMESTAMP WHERE user_id = :uid AND day_index = :didx");
    $stmtUpdate->execute(['status' => $newStatus, 'uid' => $userId, 'didx' => $dayIndex]);

    // Recalculate total completed streak days for this user
    $stmtCount = $db->prepare("SELECT COUNT(*) as completed_count FROM weekly_streaks WHERE user_id = :uid AND is_completed = 1");
    $stmtCount->execute(['uid' => $userId]);
    $activeCount = (int)($stmtCount->fetch()['completed_count'] ?? 0);

    // Update stats table cleanly
    $stmtUpdateStats = $db->prepare("UPDATE user_stats SET 
        learning_streak = :streak,
        longest_streak = CASE WHEN :streak > longest_streak THEN :streak ELSE longest_streak END
        WHERE user_id = :uid");
    $stmtUpdateStats->execute(['streak' => $activeCount, 'uid' => $userId]);

    sendJsonResponse([
        'success' => true,
        'message' => 'Streak status updated successfully',
        'data' => [
            'day_index' => $dayIndex,
            'is_completed' => $newStatus,
            'weekly_streak_count' => $activeCount,
            'total_learning_streak' => $activeCount
        ]
    ]);
} catch (Throwable $e) {
    error_log('Toggle streak error: ' . $e->getMessage());
    sendJsonResponse(['success' => false, 'message' => 'An error occurred updating streak.'], 500);
}

