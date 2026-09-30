<?php
require_once __DIR__ . '/../config/db.php';
startSecureSession();

try {
    if (!isset($_SESSION['user_id'])) {
        sendJsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    $userId = (int)$_SESSION['user_id'];
    session_write_close();
    $db = getDbConnection();

    // 1. Fetch User Info
    $stmtUser = $db->prepare("SELECT id, name, email, avatar_url FROM users WHERE id = :uid");
    $stmtUser->execute(['uid' => $userId]);
    $user = $stmtUser->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        sendJsonResponse(['success' => false, 'message' => 'User not found'], 404);
    }

    // 2. Fetch User Stats Summary
    $stmtStats = $db->prepare("SELECT id, user_id, learning_streak, streak_delta, longest_streak, missed_days, inactive_pct, 
                                      course_progress_pct, progress_delta_pct, weekly_lessons_current, weekly_lessons_last, 
                                      study_hours, study_minutes, study_delta_pct, updated_at 
                               FROM user_stats 
                               WHERE user_id = :uid");
    $stmtStats->execute(['uid' => $userId]);
    $stats = $stmtStats->fetch(PDO::FETCH_ASSOC);

    // 3. Fetch Courses with explicit columns
    $stmtCourses = $db->prepare("SELECT id, user_id, code, name, category, level, total_modules, completed_modules, progress_pct, 
                                        bg_gradient, ring_color, is_starred, status, created_at 
                                 FROM courses 
                                 WHERE user_id = :uid 
                                 ORDER BY id ASC");
    $stmtCourses->execute(['uid' => $userId]);
    $courses = $stmtCourses->fetchAll(PDO::FETCH_ASSOC);

    // 4. Fetch Weekly Streaks
    $stmtStreaks = $db->prepare("SELECT day_index, day_name, is_completed 
                                 FROM weekly_streaks 
                                 WHERE user_id = :uid 
                                 ORDER BY day_index ASC");
    $stmtStreaks->execute(['uid' => $userId]);
    $streaks = $stmtStreaks->fetchAll(PDO::FETCH_ASSOC);

    if (count($streaks) < 7) {
        $dayNames = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        $existingIndices = array_column($streaks, 'day_index');
        $stmtInsertDay = $db->prepare("INSERT INTO weekly_streaks (user_id, day_index, day_name, is_completed) VALUES (:uid, :didx, :dname, 0)");
        foreach ($dayNames as $idx => $name) {
            if (!in_array($idx, $existingIndices, true)) {
                $stmtInsertDay->execute(['uid' => $userId, 'didx' => $idx, 'dname' => $name]);
            }
        }
        $stmtStreaks->execute(['uid' => $userId]);
        $streaks = $stmtStreaks->fetchAll(PDO::FETCH_ASSOC);
    }

    // 5. Fetch User Skills
    $stmtSkills = $db->prepare("SELECT label, score_pct FROM user_skills WHERE user_id = :uid ORDER BY id ASC");
    $stmtSkills->execute(['uid' => $userId]);
    $skills = $stmtSkills->fetchAll(PDO::FETCH_ASSOC);

    // 6. Fetch Heatmap Daily Activities (chronological order for fast charting)
    $stmtActivities = $db->prepare("SELECT activity_date, lessons_completed, study_minutes 
                                    FROM daily_activities 
                                    WHERE user_id = :uid 
                                    ORDER BY activity_date ASC");
    $stmtActivities->execute(['uid' => $userId]);
    $activities = $stmtActivities->fetchAll(PDO::FETCH_ASSOC);

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
