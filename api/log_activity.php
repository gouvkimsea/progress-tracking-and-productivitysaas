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

    $date = isset($data['activity_date']) ? trim($data['activity_date']) : date('Y-m-d');
    $lessons = isset($data['lessons_completed']) ? (int)$data['lessons_completed'] : 1;
    $minutes = isset($data['study_minutes']) ? (int)$data['study_minutes'] : 30;
    $category = isset($data['category']) ? trim($data['category']) : 'General';

    // Upsert into daily_activities
    $isSqlite = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';

    if ($isSqlite) {
        $stmt = $db->prepare("INSERT INTO daily_activities (user_id, activity_date, lessons_completed, study_minutes, category)
            VALUES (:uid, :adate, :lessons, :mins, :cat)
            ON CONFLICT(user_id, activity_date) DO UPDATE SET
                lessons_completed = daily_activities.lessons_completed + excluded.lessons_completed,
                study_minutes = daily_activities.study_minutes + excluded.study_minutes,
                category = excluded.category");
    } else {
        $stmt = $db->prepare("INSERT INTO daily_activities (user_id, activity_date, lessons_completed, study_minutes, category)
            VALUES (:uid, :adate, :lessons, :mins, :cat)
            ON DUPLICATE KEY UPDATE
                lessons_completed = lessons_completed + VALUES(lessons_completed),
                study_minutes = study_minutes + VALUES(study_minutes),
                category = VALUES(category)");
    }

    $stmt->execute([
        'uid' => $userId,
        'adate' => $date,
        'lessons' => $lessons,
        'mins' => $minutes,
        'cat' => $category
    ]);

    // Recalculate total study time & total lessons from daily_activities table
    $stmtTotals = $db->prepare("SELECT SUM(study_minutes) as total_mins, SUM(lessons_completed) as total_lessons FROM daily_activities WHERE user_id = :uid");
    $stmtTotals->execute(['uid' => $userId]);
    $totals = $stmtTotals->fetch();

    $totalMins = (int)($totals['total_mins'] ?? 0);
    $totalLessons = (int)($totals['total_lessons'] ?? 0);
    $hours = floor($totalMins / 60);
    $mins = $totalMins % 60;

    // Update user_stats in database
    $stmtUpdateStats = $db->prepare("UPDATE user_stats SET study_hours = :h, study_minutes = :m, weekly_lessons_current = :wl WHERE user_id = :uid");
    $stmtUpdateStats->execute([
        'h' => $hours,
        'm' => $mins,
        'wl' => $totalLessons,
        'uid' => $userId
    ]);

    sendJsonResponse([
        'success' => true,
        'message' => 'Activity logged successfully!',
        'data' => [
            'activity_date' => $date,
            'lessons_completed' => $lessons,
            'study_minutes' => $minutes,
            'total_hours' => $hours,
            'total_minutes' => $mins,
            'total_lessons' => $totalLessons
        ]
    ]);
} catch (Throwable $e) {
    error_log('Log activity error: ' . $e->getMessage());
    sendJsonResponse(['success' => false, 'message' => 'An error occurred logging activity.'], 500);
}
