<?php
require_once __DIR__ . '/../config/db.php';
startSecureSession();

try {
    if (!isset($_SESSION['user_id'])) {
        sendJsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    $userId = (int)$_SESSION['user_id'];
    checkCsrfToken();
    $data = getJsonRequestData();
    session_write_close();
    $db = getDbConnection();

    $dayNames = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

    $action = $data['action'] ?? '';
    if ($action === 'reset') {
        $db->beginTransaction();
        $db->prepare("UPDATE weekly_streaks SET is_completed = 0 WHERE user_id = :uid")->execute(['uid' => $userId]);
        $db->prepare("UPDATE user_stats SET learning_streak = 0 WHERE user_id = :uid")->execute(['uid' => $userId]);
        $db->commit();
        sendJsonResponse(['success' => true, 'message' => 'Week streak reset successfully']);
    }

    if (!isset($data['day_index'])) {
        sendJsonResponse(['success' => false, 'message' => 'day_index is required (0 to 6)'], 400);
    }
    $dayIndex = (int)$data['day_index'];
    if ($dayIndex < 0 || $dayIndex > 6) {
        sendJsonResponse(['success' => false, 'message' => 'day_index must be between 0 and 6'], 400);
    }

    $db->beginTransaction();

    // Determine target status
    if (isset($data['status'])) {
        $newStatus = (int)$data['status'] ? 1 : 0;
    } else {
        $stmtSelect = $db->prepare("SELECT is_completed FROM weekly_streaks WHERE user_id = :uid AND day_index = :didx");
        $stmtSelect->execute(['uid' => $userId, 'didx' => $dayIndex]);
        $current = $stmtSelect->fetch(PDO::FETCH_ASSOC);
        $newStatus = ($current && (int)$current['is_completed'] === 1) ? 0 : 1;
    }

    // Upsert streak day record directly in 1 query
    $dayName = $dayNames[$dayIndex] ?? 'Day';
    $isSqlite = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';

    if ($isSqlite) {
        $stmtUpsert = $db->prepare("INSERT INTO weekly_streaks (user_id, day_index, day_name, is_completed)
            VALUES (:uid, :didx, :dname, :st)
            ON CONFLICT(user_id, day_index) DO UPDATE SET
                is_completed = excluded.is_completed,
                updated_at = CURRENT_TIMESTAMP");
    } else {
        $stmtUpsert = $db->prepare("INSERT INTO weekly_streaks (user_id, day_index, day_name, is_completed)
            VALUES (:uid, :didx, :dname, :st)
            ON DUPLICATE KEY UPDATE
                is_completed = VALUES(is_completed),
                updated_at = CURRENT_TIMESTAMP");
    }
    $stmtUpsert->execute([
        'uid' => $userId,
        'didx' => $dayIndex,
        'dname' => $dayName,
        'st' => $newStatus
    ]);

    // Recalculate total completed streak days for this user in current week
    $stmtCount = $db->prepare("SELECT COUNT(*) as completed_count FROM weekly_streaks WHERE user_id = :uid AND is_completed = 1");
    $stmtCount->execute(['uid' => $userId]);
    $activeCount = (int)($stmtCount->fetch(PDO::FETCH_ASSOC)['completed_count'] ?? 0);

    // Update stats table with explicit minimal projection
    $stmtStatsCheck = $db->prepare("SELECT learning_streak, longest_streak FROM user_stats WHERE user_id = :uid");
    $stmtStatsCheck->execute(['uid' => $userId]);
    $currStats = $stmtStatsCheck->fetch(PDO::FETCH_ASSOC);

    $prevLearningStreak = $currStats ? (int)$currStats['learning_streak'] : 0;
    $prevLongestStreak = $currStats ? (int)$currStats['longest_streak'] : 0;

    if ($newStatus === 1) {
        $newLearningStreak = max($prevLearningStreak, $activeCount);
    } else {
        $newLearningStreak = max(0, min($prevLearningStreak, max($activeCount, $prevLearningStreak - 1)));
    }
    $newLongestStreak = max($prevLongestStreak, $newLearningStreak);

    if (!$currStats) {
        $db->prepare("INSERT INTO user_stats (user_id, learning_streak, longest_streak) VALUES (:uid, :streak, :lstreak)")
           ->execute(['uid' => $userId, 'streak' => $newLearningStreak, 'lstreak' => $newLongestStreak]);
    } else {
        $stmtUpdateStats = $db->prepare("UPDATE user_stats SET 
            learning_streak = :streak,
            longest_streak = :lstreak
            WHERE user_id = :uid");
        $stmtUpdateStats->execute([
            'streak' => $newLearningStreak,
            'lstreak' => $newLongestStreak,
            'uid' => $userId
        ]);
    }

    $db->commit();

    sendJsonResponse([
        'success' => true,
        'message' => 'Streak status updated successfully',
        'data' => [
            'day_index' => $dayIndex,
            'is_completed' => $newStatus,
            'weekly_streak_count' => $activeCount,
            'total_learning_streak' => $newLearningStreak,
            'longest_streak' => $newLongestStreak
        ]
    ]);
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Toggle streak error: ' . $e->getMessage());
    sendJsonResponse(['success' => false, 'message' => 'An error occurred updating streak: ' . $e->getMessage()], 500);
}
