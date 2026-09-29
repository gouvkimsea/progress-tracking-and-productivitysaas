<?php
require_once __DIR__ . '/../config/db.php';
startSecureSession();

// Helper to prevent CSV Formula Injection
function sanitizeCsvRow(array $row): array {
    return array_map(function($val) {
        if (is_string($val) && strlen($val) > 0) {
            $firstChar = $val[0];
            if (in_array($firstChar, ['=', '+', '-', '@', "\t", "\r"], true)) {
                return "'" . $val;
            }
        }
        return $val;
    }, $row);
}

try {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ../login.php');
        exit;
    }

    $db = getDbConnection();
    $userId = (int)$_SESSION['user_id'];
    $type = trim($_GET['type'] ?? 'tasks');

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . preg_replace('/[^a-zA-Z0-9_-]/', '', $type) . '_export_' . date('Ymd') . '.csv');

    $output = fopen('php://output', 'w');

    if ($type === 'tasks') {
        fputcsv($output, ['Task ID', 'Task Name', 'Project', 'Start Date', 'Assigned To', 'Status', 'Time Log (mins)']);
        $stmt = $db->prepare("SELECT id, task_name, project_name, start_date, assigned_to, status, time_log FROM tasks WHERE user_id = :uid");
        $stmt->execute(['uid' => $userId]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, sanitizeCsvRow($row));
        }
    } elseif ($type === 'timelog') {
        fputcsv($output, ['Log ID', 'Category', 'Activity Date', 'Lessons Completed', 'Study Minutes']);
        $stmt = $db->prepare("SELECT id, category, activity_date, lessons_completed, study_minutes FROM daily_activities WHERE user_id = :uid");
        $stmt->execute(['uid' => $userId]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, sanitizeCsvRow($row));
        }
    } elseif ($type === 'projects') {
        fputcsv($output, ['Project ID', 'Code', 'Project Name', 'Category', 'Level', 'Progress %', 'Status']);
        $stmt = $db->prepare("SELECT id, code, name, category, level, progress_pct, status FROM courses WHERE user_id = :uid");
        $stmt->execute(['uid' => $userId]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, sanitizeCsvRow($row));
        }
    } elseif ($type === 'workload') {
        fputcsv($output, ['Resource ID', 'Name', 'Email', 'Assigned Tasks Count', 'Total Hours Logged', 'Active Tasks']);
        $stmtUsers = $db->query("SELECT id, name, email FROM users ORDER BY id ASC");
        $users = $stmtUsers ? $stmtUsers->fetchAll(PDO::FETCH_ASSOC) : [];

        $stmtTasks = $db->query("SELECT id, user_id, task_name, assigned_to, status, time_log FROM tasks WHERE (deleted_at IS NULL)");
        $allTasks = $stmtTasks ? $stmtTasks->fetchAll(PDO::FETCH_ASSOC) : [];

        foreach ($users as $u) {
            $uTasks = [];
            $totalHours = 0;
            $userName = strtolower(trim($u['name'] ?? ''));
            $userEmail = strtolower(trim($u['email'] ?? ''));
            $firstName = explode(' ', $userName)[0];

            foreach ($allTasks as $t) {
                $assigned = strtolower(trim($t['assigned_to'] ?? ''));
                if ($assigned === 'unassigned' || empty($assigned)) continue;

                if ($assigned === $userName || $assigned === $userEmail || $assigned === $firstName || ((int)$t['user_id'] === (int)$u['id'] && in_array($assigned, ['me', 'myself', $firstName], true))) {
                    $uTasks[] = $t['task_name'] . ' (' . ($t['status'] ?? 'Open') . ')';
                    $totalHours += (int)($t['time_log'] ?? 0);
                }
            }

            fputcsv($output, sanitizeCsvRow([
                $u['id'],
                $u['name'],
                $u['email'],
                count($uTasks),
                $totalHours,
                implode('; ', $uTasks)
            ]));
        }

        // Unassigned Row
        $unassigned = [];
        foreach ($allTasks as $t) {
            $assigned = strtolower(trim($t['assigned_to'] ?? ''));
            if ($assigned === 'unassigned' || empty($assigned)) {
                $unassigned[] = $t['task_name'] . ' (' . ($t['status'] ?? 'Open') . ')';
            }
        }
        fputcsv($output, sanitizeCsvRow([
            'N/A',
            'Unassigned',
            'N/A',
            count($unassigned),
            0,
            implode('; ', $unassigned)
        ]));
    } else {
        fputcsv($output, ['Type', 'Export Date']);
        fputcsv($output, [$type, date('Y-m-d H:i:s')]);
    }

    fclose($output);
    exit;
} catch (Throwable $e) {
    error_log('Export Error: ' . $e->getMessage());
    http_response_code(500);
    echo "An error occurred generating the export.";
}

