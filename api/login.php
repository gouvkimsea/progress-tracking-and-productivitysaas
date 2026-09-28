<?php
require_once __DIR__ . '/../config/db.php';
startSecureSession();

try {
    $db = getDbConnection();

    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true) ?? $_POST;

    $email = isset($data['email']) ? strtolower(trim($data['email'])) : '';
    $password = isset($data['password']) ? trim($data['password']) : '';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    // Enforce brute-force attack rate limiting (5 attempts per 15 minutes)
    if (!checkLoginRateLimit($db, $ip, 5, 900)) {
        sendJsonResponse(['success' => false, 'message' => 'Too many failed login attempts. Please wait 15 minutes before trying again.'], 429);
    }

    if (empty($email) || empty($password)) {
        sendJsonResponse(['success' => false, 'message' => 'Please enter both email and password.'], 400);
    }

    $stmt = $db->prepare("SELECT id, name, email, password FROM users WHERE LOWER(email) = :email");
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if (!$user || empty($user['password'])) {
        // Enforce constant-time comparison or rejection to mitigate user enumeration
        password_verify($password, '$2y$10$dummyhashforenumerationpreventiondummyhash12345678901');
        recordLoginAttempt($db, $ip, $email);
        sendJsonResponse(['success' => false, 'message' => 'Invalid email or password.'], 401);
    }

    if (!password_verify($password, $user['password'])) {
        recordLoginAttempt($db, $ip, $email);
        sendJsonResponse(['success' => false, 'message' => 'Invalid email or password.'], 401);
    }

    // Reset failed attempts on successful login
    clearLoginAttempts($db, $ip);

    // Regenerate session to eliminate session fixation vulnerabilities
    session_regenerate_id(true);

    // Log user in
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];

    sendJsonResponse([
        'success' => true,
        'message' => 'Welcome back, ' . htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') . '!',
        'redirect' => 'index.php',
        'user' => [
            'id' => (int)$user['id'],
            'name' => $user['name'],
            'email' => $user['email']
        ]
    ]);

} catch (Throwable $e) {
    error_log('Login Error: ' . $e->getMessage());
    sendJsonResponse(['success' => false, 'message' => 'An error occurred during login. Please try again.'], 500);
}

