<?php
require_once __DIR__ . '/../config/db.php';
startSecureSession();

try {
    $db = getDbConnection();
    
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true) ?? $_POST;

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

    // Check if email already registered
    $stmtCheck = $db->prepare("SELECT id FROM users WHERE LOWER(email) = :email");
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

    // Initialize user default stats with parameterized query
    $stmtStats = $db->prepare("INSERT INTO user_stats (user_id, learning_streak, streak_delta, longest_streak, missed_days, inactive_pct, course_progress_pct, progress_delta_pct, weekly_lessons_current, weekly_lessons_last, study_hours, study_minutes, study_delta_pct)
                               VALUES (:uid, 0, 0, 0, 0, 0, 0.00, 0.00, 0, 0, 0, 0, 0.00)");
    $stmtStats->execute(['uid' => $userId]);

    // Initialize starter courses
    $courses = [
        ['UI', 'UI Design Mastery', 'Design', 'Intermediate', 12, 'linear-gradient(145deg,#8B7CF0,#5A46E0)', '#6C5CE7'],
        ['JS', 'Learn JavaScript', 'Programming', 'Beginner', 8, 'linear-gradient(145deg,#FBAE68,#F2994A)', '#F2994A'],
        ['PS', 'Learn Photoshop', 'Design', 'Advanced', 32, 'linear-gradient(145deg,#6FC1F0,#4FA3E0)', '#4FA3E0'],
        ['PY', 'Python for Data', 'Data Science', 'Intermediate', 20, 'linear-gradient(145deg,#7FD9A5,#2FBE73)', '#2FBE73']
    ];
    $stmtCourse = $db->prepare("INSERT INTO courses (user_id, code, name, category, level, total_modules, completed_modules, progress_pct, bg_gradient, ring_color) VALUES (?, ?, ?, ?, ?, ?, 0, 0, ?, ?)");
    foreach ($courses as $c) {
        $stmtCourse->execute([$userId, $c[0], $c[1], $c[2], $c[3], $c[4], $c[5], $c[6]]);
    }

    // Initialize weekly streak check-in days
    $stmtStreak = $db->prepare("INSERT INTO weekly_streaks (user_id, day_index, day_name, is_completed) VALUES (?, ?, ?, 0)");
    $days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    foreach ($days as $idx => $d) {
        $stmtStreak->execute([$userId, $idx, $d]);
    }

    // Initialize user skills radar
    $stmtSkill = $db->prepare("INSERT INTO user_skills (user_id, label, score_pct) VALUES (?, ?, 0.50)");
    $skills = ['Productivity', 'Data & Analysis', 'Communication', 'Creativity', 'Technical'];
    foreach ($skills as $s) {
        $stmtSkill->execute([$userId, $s]);
    }

    // Initialize starter tasks for Kanban, Gantt, and Assignments
    $stmtTask = $db->prepare("INSERT INTO tasks (user_id, task_name, project_name, start_date, due_date, priority, assigned_to, status, time_log) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmtTask->execute([$userId, 'Design System Component Tokens', 'UI Design Mastery', date('d-m-Y'), date('d-m-Y', strtotime('+5 days')), 'High', $name, 'In Progress', 4]);
    $stmtTask->execute([$userId, 'Modern Frontend Architecture & State', 'Learn JavaScript', date('d-m-Y'), date('d-m-Y', strtotime('+2 days')), 'Urgent', $name, 'Open', 2]);
    $stmtTask->execute([$userId, 'RESTful API & Database Integration', 'Python for Data', date('d-m-Y', strtotime('-4 days')), date('d-m-Y', strtotime('-1 day')), 'Medium', $name, 'Done', 6]);

    // Initialize starter OKRs & Goals
    $stmtGoal = $db->prepare("INSERT INTO project_goals (user_id, title, category, target_value, current_value, unit, due_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmtGoal->execute([$userId, 'Complete Q3 Design System Roadmap', 'Productivity', 100, 75, '%', 'End of Quarter', 'On Track']);
    $stmtGoal->execute([$userId, 'Log 40 Hours of Deep Focus Work', 'Learning', 40, 28, 'hrs', 'This Month', 'On Track']);
    $stmtGoal->execute([$userId, 'Publish Multi-Cloud API Integration', 'Engineering', 100, 30, '%', 'Next Month', 'At Risk']);

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

