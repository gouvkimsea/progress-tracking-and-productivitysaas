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

    $name = isset($data['name']) ? trim($data['name']) : '';
    $email = isset($data['email']) ? strtolower(trim($data['email'])) : '';
    $password = isset($data['password']) ? trim($data['password']) : '';

    if (empty($name) || empty($email)) {
        sendJsonResponse(['success' => false, 'message' => 'Name and Email are required.'], 400);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendJsonResponse(['success' => false, 'message' => 'Please provide a valid email address.'], 400);
    }

    $db = getDbConnection();

    // Check if email taken by another user (uses idx_users_email index directly)
    $stmtCheck = $db->prepare("SELECT id FROM users WHERE email = :email AND id != :uid");
    $stmtCheck->execute(['email' => $email, 'uid' => $userId]);
    if ($stmtCheck->fetch()) {
        sendJsonResponse(['success' => false, 'message' => 'This email address is already in use by another account.'], 400);
    }

    if (!empty($password)) {
        if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
            sendJsonResponse(['success' => false, 'message' => 'New password must be at least 8 characters long and contain both letters and numbers.'], 400);
        }
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmtUpdate = $db->prepare("UPDATE users SET name = :name, email = :email, password = :pass WHERE id = :uid");
        $stmtUpdate->execute(['name' => $name, 'email' => $email, 'pass' => $hash, 'uid' => $userId]);
    } else {
        $stmtUpdate = $db->prepare("UPDATE users SET name = :name, email = :email WHERE id = :uid");
        $stmtUpdate->execute(['name' => $name, 'email' => $email, 'uid' => $userId]);
    }

    // Update active session values & write close
    $_SESSION['user_name'] = $name;
    $_SESSION['user_email'] = $email;
    session_write_close();

    sendJsonResponse([
        'success' => true,
        'message' => 'Profile updated successfully!',
        'user' => [
            'name' => htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
            'email' => htmlspecialchars($email, ENT_QUOTES, 'UTF-8')
        ]
    ]);
} catch (Throwable $e) {
    error_log('Update profile error: ' . $e->getMessage());
    sendJsonResponse(['success' => false, 'message' => 'An error occurred updating profile.'], 500);
}
