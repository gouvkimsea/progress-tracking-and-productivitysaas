<?php
require_once __DIR__ . '/../config/db.php';
startSecureSession();

try {
    if (!isset($_SESSION['user_id'])) {
        sendJsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    $userId = (int)$_SESSION['user_id'];
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        session_write_close();
        $db = getDbConnection();

        $stmt = $db->prepare("SELECT id, user_id, code, name, category, level, total_modules, completed_modules, progress_pct, 
                                     bg_gradient, ring_color, is_starred, status, created_at 
                              FROM courses 
                              WHERE user_id = :uid 
                              ORDER BY id ASC");
        $stmt->execute(['uid' => $userId]);
        $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

        sendJsonResponse(['success' => true, 'courses' => $courses]);
    } elseif ($method === 'POST') {
        checkCsrfToken();
        $data = getJsonRequestData();
        session_write_close();
        $db = getDbConnection();

        if (!isset($data['course_id'])) {
            sendJsonResponse(['success' => false, 'message' => 'course_id is required'], 400);
        }

        $courseId = (int)$data['course_id'];

        // Fetch only the needed fields for progress calculation
        $stmtCourse = $db->prepare("SELECT total_modules, completed_modules FROM courses WHERE id = :id AND user_id = :uid");
        $stmtCourse->execute(['id' => $courseId, 'uid' => $userId]);
        $course = $stmtCourse->fetch(PDO::FETCH_ASSOC);

        if (!$course) {
            sendJsonResponse(['success' => false, 'message' => 'Course not found'], 404);
        }

        $totalModules = (int)$course['total_modules'];
        $currentCompleted = (int)$course['completed_modules'];

        if (isset($data['action']) && $data['action'] === 'increment') {
            $newCompleted = min($totalModules, $currentCompleted + 1);
        } elseif (isset($data['completed_modules'])) {
            $newCompleted = max(0, min($totalModules, (int)$data['completed_modules']));
        } else {
            $newCompleted = min($totalModules, $currentCompleted + 1);
        }

        $newPct = $totalModules > 0 ? (int)round(($newCompleted / $totalModules) * 100) : 0;

        $db->beginTransaction();

        // Update course record
        $stmtUpdate = $db->prepare("UPDATE courses SET completed_modules = :cm, progress_pct = :pct WHERE id = :id AND user_id = :uid");
        $stmtUpdate->execute(['cm' => $newCompleted, 'pct' => $newPct, 'id' => $courseId, 'uid' => $userId]);

        // Recalculate average progress across all user courses
        $stmtAvg = $db->prepare("SELECT AVG(progress_pct) as avg_pct FROM courses WHERE user_id = :uid");
        $stmtAvg->execute(['uid' => $userId]);
        $avgRow = $stmtAvg->fetch(PDO::FETCH_ASSOC);
        $overallProgress = round((float)($avgRow['avg_pct'] ?? 0), 2);

        // Update user_stats in database
        $stmtUpdateStats = $db->prepare("UPDATE user_stats SET course_progress_pct = :cpct WHERE user_id = :uid");
        $stmtUpdateStats->execute(['cpct' => $overallProgress, 'uid' => $userId]);

        $db->commit();

        sendJsonResponse([
            'success' => true,
            'message' => 'Course module progress updated!',
            'data' => [
                'course_id' => $courseId,
                'completed_modules' => $newCompleted,
                'total_modules' => $totalModules,
                'progress_pct' => $newPct,
                'overall_course_progress_pct' => $overallProgress
            ]
        ]);
    } else {
        sendJsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
    }
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Courses API error: ' . $e->getMessage());
    sendJsonResponse(['success' => false, 'message' => 'An error occurred updating course data.'], 500);
}
