<?php
require_once __DIR__ . '/../config/db.php';
startSecureSession();

$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

@session_destroy();

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
    || (isset($_GET['format']) && $_GET['format'] === 'json');

if ($isAjax) {
    sendJsonResponse(['success' => true, 'redirect' => 'login.php']);
}

header('Location: ../login.php');
exit;
