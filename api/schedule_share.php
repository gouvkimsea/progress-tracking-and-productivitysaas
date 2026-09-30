<?php
require_once __DIR__ . '/../config/db.php';
startSecureSession();

if (!function_exists('buildScheduleUrls')) {
    function buildScheduleUrls(string $token): array {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
        $scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
        $baseDir = preg_replace('/\/api$/', '', $scriptDir);

        $shareUrl = "{$protocol}{$host}{$baseDir}/schedule.php?share={$token}";
        $feedUrl = "{$protocol}{$host}{$baseDir}/api/calendar_export.php?token={$token}";
        $webcalUrl = "webcal://{$host}{$baseDir}/api/calendar_export.php?token={$token}";

        return [
            'share_url' => $shareUrl,
            'feed_url' => $feedUrl,
            'webcal_url' => $webcalUrl
        ];
    }
}

try {
    if (!isset($_SESSION['user_id'])) {
        sendJsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    $userId = (int)$_SESSION['user_id'];
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        session_write_close();
        $db = getDbConnection();
        $token = getUserScheduleToken($db, $userId);
        $urls = buildScheduleUrls($token);

        sendJsonResponse([
            'success' => true,
            'share_token' => $token,
            'share_url' => $urls['share_url'],
            'feed_url' => $urls['feed_url'],
            'webcal_url' => $urls['webcal_url']
        ]);
    } elseif ($method === 'POST') {
        checkCsrfToken();
        $body = getJsonRequestData();
        session_write_close();
        $db = getDbConnection();
        $action = $body['action'] ?? '';

        if ($action === 'regenerate') {
            $newToken = regenerateUserScheduleToken($db, $userId);
            $urls = buildScheduleUrls($newToken);

            sendJsonResponse([
                'success' => true,
                'message' => 'Schedule share link reset successfully. Previous links have been revoked.',
                'share_token' => $newToken,
                'share_url' => $urls['share_url'],
                'feed_url' => $urls['feed_url'],
                'webcal_url' => $urls['webcal_url']
            ]);
        } else {
            sendJsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
        }
    } else {
        sendJsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
    }
} catch (Throwable $e) {
    error_log("Schedule share API error: " . $e->getMessage());
    sendJsonResponse(['success' => false, 'message' => 'Server error occurred.'], 500);
}
