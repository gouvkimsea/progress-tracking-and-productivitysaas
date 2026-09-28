<?php
require_once __DIR__ . '/../config/db.php';
startSecureSession();

try {
    if (!isset($_SESSION['user_id'])) {
        sendJsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    $db = getDbConnection();
    $userId = (int)$_SESSION['user_id'];

    // 1. Fetch User Info
    $stmtUser = $db->prepare("SELECT id, name, email, avatar_url FROM users WHERE id = :uid");
    $stmtUser->execute(['uid' => $userId]);
    $user = $stmtUser->fetch();
    if (!$user) {
        sendJsonResponse(['success' => false, 'message' => 'User not found'], 404);
    }

    // 2. Fetch User Stats Summary
    $stmtStats = $db->prepare("SELECT * FROM user_stats WHERE user_id = :uid");
    $stmtStats->execute(['uid' => $userId]);
    $stats = $stmtStats->fetch();

    // 3. Fetch Courses
    $stmtCourses = $db->prepare("SELECT * FROM courses WHERE user_id = :uid ORDER BY id ASC");
    $stmtCourses->execute(['uid' => $userId]);
    $courses = $stmtCourses->fetchAll();

    // 4. Fetch Weekly Streaks
    $stmtStreaks = $db->prepare("SELECT day_index, day_name, is_completed FROM weekly_streaks WHERE user_id = :uid ORDER BY day_index ASC");
    $stmtStreaks->execute(['uid' => $userId]);
    $streaks = $stmtStreaks->fetchAll();

    // 5. Fetch User Skills
    $stmtSkills = $db->prepare("SELECT label, score_pct FROM user_skills WHERE user_id = :uid ORDER BY id ASC");
    $stmtSkills->execute(['uid' => $userId]);
    $skills = $stmtSkills->fetchAll();

    // 6. Fetch Heatmap Daily Activities
    $stmtActivities = $db->prepare("SELECT activity_date, lessons_completed, study_minutes FROM daily_activities WHERE user_id = :uid");
    $stmtActivities->execute(['uid' => $userId]);
    $activities = $stmtActivities->fetchAll();

    sendJsonResponse([
        'success' => true,
        'data' => [
            'user' => $user,
            'stats' => $stats,
            'courses' => $courses,
            'weekly_streaks' => $streaks,
            'skills' => $skills,
            'activities' => $activities,
            'server_time' => date('Y-m-d H:i:s')
        ]
    ]);
} catch (Throwable $e) {
    error_log('Dashboard API error: ' . $e->getMessage());
    sendJsonResponse(['success' => false, 'message' => 'An error occurred fetching dashboard data.'], 500);
}
