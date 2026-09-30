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
        $stmt = $db->prepare("SELECT id, file_name, file_path, file_size, uploaded_at FROM files WHERE user_id = :uid ORDER BY id DESC LIMIT 100");
        $stmt->execute(['uid' => $userId]);
        $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
        sendJsonResponse(['success' => true, 'files' => $files]);
    } elseif ($method === 'POST') {
        checkCsrfToken();
        $json = getJsonRequestData();
        session_write_close();
        $db = getDbConnection();

        if (isset($json['action']) && $json['action'] === 'delete') {
            $fileId = (int)($json['file_id'] ?? 0);
            $stmt = $db->prepare("SELECT file_path FROM files WHERE id = :id AND user_id = :uid");
            $stmt->execute(['id' => $fileId, 'uid' => $userId]);
            $f = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($f) {
                $diskFile = __DIR__ . '/../' . $f['file_path'];
                if (file_exists($diskFile) && is_file($diskFile)) {
                    @unlink($diskFile);
                }
                $db->prepare("DELETE FROM files WHERE id = :id AND user_id = :uid")->execute(['id' => $fileId, 'uid' => $userId]);
                sendJsonResponse(['success' => true, 'message' => 'File deleted successfully']);
            } else {
                sendJsonResponse(['success' => false, 'message' => 'File not found'], 404);
            }
        }

        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            sendJsonResponse(['success' => false, 'message' => 'No file uploaded or upload error occurred'], 400);
        }

        $file = $_FILES['file'];
        $originalName = basename($file['name']);
        $fileSize = (int)$file['size'];
        $maxBytes = 10 * 1024 * 1024; // 10 MB

        if ($fileSize > $maxBytes) {
            sendJsonResponse(['success' => false, 'message' => 'File size exceeds maximum allowed limit (10MB)'], 400);
        }

        // Whitelist safe file extensions
        $allowedExtensions = ['pdf', 'png', 'jpg', 'jpeg', 'gif', 'zip', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'csv'];
        $fileParts = pathinfo($originalName);
        $ext = strtolower($fileParts['extension'] ?? '');

        if (!in_array($ext, $allowedExtensions, true) || preg_match('/(php|phtml|phar|exe|sh|pl|cgi|asp|jsp)/i', $originalName)) {
            sendJsonResponse(['success' => false, 'message' => 'File type not permitted for security reasons.'], 400);
        }

        $uploadDir = __DIR__ . '/../uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Ensure .htaccess exists in uploads/ to block execution
        $htaccessPath = $uploadDir . '.htaccess';
        if (!file_exists($htaccessPath)) {
            @file_put_contents($htaccessPath, "# Block script execution\n<FilesMatch \"\.(php|phtml|phar|cgi|pl|exe)$\">\nOrder Deny,Allow\nDeny from all\n</FilesMatch>\nOptions -ExecCGI\n");
        }

        // Generate non-guessable random filename on disk
        $safeStorageName = bin2hex(random_bytes(16)) . '.' . $ext;
        $targetPath = $uploadDir . $safeStorageName;
        $relPath = 'uploads/' . $safeStorageName;

        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            $stmtInsert = $db->prepare("INSERT INTO files (user_id, file_name, file_path, file_size) VALUES (:uid, :fname, :fpath, :fsize)");
            $stmtInsert->execute([
                'uid' => $userId,
                'fname' => $originalName,
                'fpath' => $relPath,
                'fsize' => $fileSize
            ]);

            sendJsonResponse([
                'success' => true,
                'message' => 'File uploaded successfully!',
                'file_id' => (int)$db->lastInsertId(),
                'file_name' => htmlspecialchars($originalName, ENT_QUOTES, 'UTF-8')
            ]);
        } else {
            sendJsonResponse(['success' => false, 'message' => 'Failed to save uploaded file'], 500);
        }
    } else {
        sendJsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
    }
} catch (Throwable $e) {
    error_log('File upload error: ' . $e->getMessage());
    sendJsonResponse(['success' => false, 'message' => 'An error occurred processing the file.'], 500);
}
