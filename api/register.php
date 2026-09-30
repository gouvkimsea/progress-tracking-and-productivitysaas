<?php
require_once __DIR__ . '/../config/db.php';
startSecureSession();

try {
    $data = getJsonRequestData();

    $name = isset($data['name']) ? trim($data['name']) : '';
    $email = isset($data['email']) ? strtolower(trim($data['email'])) : '';
    $password = isset($data['password']) ? trim($data['password']) : '';

    if (empty($name) || empty($email) || empty($password)) {
        sendJsonResponse(['success' => false, 'message' => 'Please fill in all required fields (Name, Email, Password).'], 400);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendJsonResponse(['success' => false, 'message' => 'Please provide a valid email address.'], 400);
    }

    if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        sendJsonResponse(['success' => false, 'message' => 'Password must be at least 8 characters long and contain both letters and numbers.'], 400);
    }

    $db = getDbConnection();

    // Check if email already registered (uses unique index idx_users_email)
    $stmtCheck = $db->prepare("SELECT id FROM users WHERE email = :email");
    $stmtCheck->execute(['email' => $email]);
    if ($stmtCheck->fetch()) {
        sendJsonResponse(['success' => false, 'message' => 'An account with this email address already exists.'], 409);
    }

    // Begin atomic transaction for user creation & starter seed
    $db->beginTransaction();

    // Hash password & create user
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $stmtInsert = $db->prepare("INSERT INTO users (name, email, password) VALUES (:name, :email, :pass)");
    $stmtInsert->execute([
        'name' => $name,
        'email' => $email,
        'pass' => $hashedPassword
    ]);

    $userId = (int)$db->lastInsertId();

    // Initialize user default stats
    $stmtStats = $db->prepare("INSERT INTO user_stats (user_id, learning_streak, streak_delta, longest_streak, missed_days, inactive_pct, course_progress_pct, progress_delta_pct, weekly_lessons_current, weekly_lessons_last, study_hours, study_minutes, study_delta_pct)
                               VALUES (:uid, 0, 0, 0, 0, 0, 0.00, 0.00, 0, 0, 0, 0, 0.00)");
    $stmtStats->execute(['uid' => $userId]);

    // Initialize starter courses in 1 multi-row batch insert
    $stmtCourse = $db->prepare("INSERT INTO courses (user_id, code, name, category, level, total_modules, completed_modules, progress_pct, bg_gradient, ring_color) VALUES 
        (?, 'UI', 'UI Design Mastery', 'Design', 'Intermediate', 12, 0, 0, 'linear-gradient(145deg,#8B7CF0,#5A46E0)', '#6C5CE7'),
        (?, 'JS', 'Learn JavaScript', 'Programming', 'Beginner', 8, 0, 0, 'linear-gradient(145deg,#FBAE68,#F2994A)', '#F2994A'),
        (?, 'PS', 'Learn Photoshop', 'Design', 'Advanced', 32, 0, 0, 'linear-gradient(145deg,#6FC1F0,#4FA3E0)', '#4FA3E0'),
        (?, 'PY', 'Python for Data', 'Data Science', 'Intermediate', 20, 0, 0, 'linear-gradient(145deg,#7FD9A5,#2FBE73)', '#2FBE73')");
    $stmtCourse->execute([$userId, $userId, $userId, $userId]);

    // Initialize weekly streak check-in days in 1 multi-row batch insert
    $stmtStreak = $db->prepare("INSERT INTO weekly_streaks (user_id, day_index, day_name, is_completed) VALUES 
        (?, 0, 'Mon', 0),
        (?, 1, 'Tue', 0),
        (?, 2, 'Wed', 0),
        (?, 3, 'Thu', 0),
        (?, 4, 'Fri', 0),
        (?, 5, 'Sat', 0),
        (?, 6, 'Sun', 0)");
    $stmtStreak->execute([$userId, $userId, $userId, $userId, $userId, $userId, $userId]);

    // Initialize user skills radar in 1 multi-row batch insert
    $stmtSkill = $db->prepare("INSERT INTO user_skills (user_id, label, score_pct) VALUES 
        (?, 'Productivity', 0.50),
        (?, 'Data & Analysis', 0.50),
        (?, 'Communication', 0.50),
        (?, 'Creativity', 0.50),
        (?, 'Technical', 0.50)");
    $stmtSkill->execute([$userId, $userId, $userId, $userId, $userId]);

    // Initialize starter tasks in 1 multi-row batch insert
    $todayStr = date('d-m-Y');
    $duePlus5 = date('d-m-Y', strtotime('+5 days'));
    $duePlus2 = date('d-m-Y', strtotime('+2 days'));
    $dueMinus1 = date('d-m-Y', strtotime('-1 day'));
    $startMinus4 = date('d-m-Y', strtotime('-4 days'));

    $stmtTask = $db->prepare("INSERT INTO tasks (user_id, task_name, project_name, start_date, due_date, priority, assigned_to, status, time_log) VALUES 
        (?, 'Design System Component Tokens', 'UI Design Mastery', ?, ?, 'High', ?, 'In Progress', 4),
        (?, 'Modern Frontend Architecture & State', 'Learn JavaScript', ?, ?, 'Urgent', ?, 'Open', 2),
        (?, 'RESTful API & Database Integration', 'Python for Data', ?, ?, 'Medium', ?, 'Done', 6)");
    $stmtTask->execute([
        $userId, $todayStr, $duePlus5, $name,
        $userId, $todayStr, $duePlus2, $name,
        $userId, $startMinus4, $dueMinus1, $name
    ]);

    // Initialize starter OKRs & Goals in 1 multi-row batch insert
    $stmtGoal = $db->prepare("INSERT INTO project_goals (user_id, title, category, target_value, current_value, unit, due_date, status) VALUES 
        (?, 'Complete Q3 Design System Roadmap', 'Productivity', 100, 75, '%', 'End of Quarter', 'On Track'),
        (?, 'Log 40 Hours of Deep Focus Work', 'Learning', 40, 28, 'hrs', 'This Month', 'On Track'),
        (?, 'Publish Multi-Cloud API Integration', 'Engineering', 100, 30, '%', 'Next Month', 'At Risk')");
    $stmtGoal->execute([$userId, $userId, $userId]);

    $db->commit();

    // Regenerate session ID and log user in automatically
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    $_SESSION['user_name'] = $name;
    $_SESSION['user_email'] = $email;

    sendJsonResponse([
        'success' => true,
        'message' => 'Registration successful! Welcome to Mindrift.',
        'redirect' => 'index.php',
        'user' => [
            'id' => $userId,
            'name' => $name,
            'email' => $email
        ]
    ], 201);

} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Registration Error: ' . $e->getMessage());
    sendJsonResponse(['success' => false, 'message' => 'An error occurred during registration. Please try again.'], 500);
}
