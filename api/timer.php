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

    $minutes = (int)($data['minutes'] ?? 0);
    $category = trim($data['category'] ?? 'General Study');
    $activityDate = trim($data['activity_date'] ?? date('Y-m-d'));

    if ($minutes <= 0) {
        $minutes = 1;
    }

    $db->beginTransaction();

    // Upsert into daily_activities supporting SQLite and MySQL
    $isSqlite = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';

    if ($isSqlite) {
        $stmtActivity = $db->prepare("INSERT INTO daily_activities (user_id, lessons_completed, study_minutes, category, activity_date)
            VALUES (:uid, 1, :mins, :cat, :adate)
            ON CONFLICT(user_id, activity_date) DO UPDATE SET
                study_minutes = daily_activities.study_minutes + excluded.study_minutes");
    } else {
        $stmtActivity = $db->prepare("INSERT INTO daily_activities (user_id, lessons_completed, study_minutes, category, activity_date)
            VALUES (:uid, 1, :mins, :cat, :adate)
            ON DUPLICATE KEY UPDATE
                study_minutes = study_minutes + VALUES(study_minutes)");
    }

    $stmtActivity->execute([
        'uid' => $userId,
        'mins' => $minutes,
        'cat' => $category,
        'adate' => $activityDate
    ]);

    // Recalculate total study time accurately from daily_activities table
    $stmtTotals = $db->prepare("SELECT SUM(study_minutes) as total_mins FROM daily_activities WHERE user_id = :uid");
    $stmtTotals->execute(['uid' => $userId]);
    $totalsRow = $stmtTotals->fetch(PDO::FETCH_ASSOC);
    $totalMins = (int)($totalsRow['total_mins'] ?? 0);
    $hours = (int)floor($totalMins / 60);
    $remMins = $totalMins % 60;

    $stmtStats = $db->prepare("UPDATE user_stats SET study_hours = :h, study_minutes = :m WHERE user_id = :uid");
    $stmtStats->execute(['h' => $hours, 'm' => $remMins, 'uid' => $userId]);

    $db->commit();

    sendJsonResponse([
        'success' => true,
        'message' => "Timer logged {$minutes} minutes!",
        'minutes_logged' => $minutes,
        'total_hours' => $hours,
        'total_minutes' => $remMins
    ]);
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Timer API error: ' . $e->getMessage());
    sendJsonResponse(['success' => false, 'message' => 'An error occurred logging timer session.'], 500);
}
