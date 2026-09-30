<?php
require_once __DIR__ . '/../config/db.php';
startSecureSession();

$userId = null;
$userName = 'Mindrift Tasks';

if (isset($_SESSION['user_id'])) {
    $userId = (int)$_SESSION['user_id'];
    $userName = ($_SESSION['user_name'] ?? 'Mindrift') . ' Tasks';
    session_write_close();
    $db = getDbConnection();
} elseif (!empty($_GET['token'])) {
    session_write_close();
    $db = getDbConnection();
    $tokenUser = getUserByScheduleToken($db, trim($_GET['token']));
    if ($tokenUser) {
        $userId = (int)$tokenUser['id'];
        $userName = ($tokenUser['name'] ?? 'Mindrift') . ' Tasks';
    }
} else {
    session_write_close();
}

if (!$userId) {
    http_response_code(401);
    die('Unauthorized: A valid login session or calendar share token is required.');
}

// Fetch all active tasks with explicit columns
$stmt = $db->prepare("SELECT id, task_name, project_name, assigned_to, priority, status, start_date, due_date 
                      FROM tasks 
                      WHERE user_id = :uid AND deleted_at IS NULL 
                      ORDER BY id ASC");
$stmt->execute(['uid' => $userId]);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

function formatDateToIcs(?string $dStr, string $fallback = 'now'): string {
    if (empty($dStr)) {
        $ts = strtotime($fallback);
    } else {
        $ts = strtotime(str_replace('/', '-', $dStr));
        if (!$ts) $ts = strtotime($fallback);
    }
    return gmdate('Ymd\THis\Z', $ts ?: time());
}

function escapeIcsText(string $str): string {
    $str = str_replace('\\', '\\\\', $str);
    $str = str_replace(',', '\,', $str);
    $str = str_replace(';', '\;', $str);
    $str = str_replace("\n", '\n', $str);
    return trim($str);
}

// Generate RFC-5545 iCalendar content
$lines = [
    'BEGIN:VCALENDAR',
    'VERSION:2.0',
    'PRODID:-//Mindrift//Mindrift Productivity Tasks//EN',
    'CALSCALE:GREGORIAN',
    'METHOD:PUBLISH',
    'X-WR-CALNAME:' . escapeIcsText($userName),
    'X-WR-TIMEZONE:UTC'
];

$nowIcs = gmdate('Ymd\THis\Z');

foreach ($tasks as $t) {
    $taskId = (int)$t['id'];
    $summary = escapeIcsText($t['task_name'] ?? 'Task');
    $projectName = escapeIcsText($t['project_name'] ?? 'General');
    $assignedTo = escapeIcsText($t['assigned_to'] ?? 'Me');
    $prio = strtolower($t['priority'] ?? 'medium');
    $status = escapeIcsText($t['status'] ?? 'Open');

    $icsPrio = ($prio === 'urgent') ? 1 : (($prio === 'high') ? 3 : (($prio === 'low') ? 9 : 5));

    $startIcs = formatDateToIcs($t['start_date'] ?? null, 'today 09:00');
    $dueIcs = formatDateToIcs($t['due_date'] ?? null, '+1 day 17:00');

    $description = escapeIcsText("Project: {$projectName}\nStatus: {$status}\nAssigned: {$assignedTo}\nPriority: " . ucfirst($prio));

    $lines[] = 'BEGIN:VEVENT';
    $lines[] = "UID:mindrift-task-{$taskId}@mindrift.io";
    $lines[] = "DTSTAMP:{$nowIcs}";
    $lines[] = "DTSTART:{$startIcs}";
    $lines[] = "DTEND:{$dueIcs}";
    $lines[] = "SUMMARY:{$summary}";
    $lines[] = "DESCRIPTION:{$description}";
    $lines[] = "PRIORITY:{$icsPrio}";
    $lines[] = 'STATUS:CONFIRMED';
    // Add reminder alarm 2 hours prior
    $lines[] = 'BEGIN:VALARM';
    $lines[] = 'ACTION:DISPLAY';
    $lines[] = 'DESCRIPTION:Task Deadline Reminder';
    $lines[] = 'TRIGGER:-PT2H';
    $lines[] = 'END:VALARM';
    $lines[] = 'END:VEVENT';
}

$lines[] = 'END:VCALENDAR';
$icsContent = implode("\r\n", $lines) . "\r\n";

header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="mindrift-tasks.ics"');
header('Content-Length: ' . strlen($icsContent));
header('Cache-Control: no-cache, no-store, must-revalidate');

echo $icsContent;
