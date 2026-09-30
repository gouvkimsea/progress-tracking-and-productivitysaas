<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/holidays.php';
startSecureSession();

$userId = null;
$userName = 'Mindrift Calendar';
$country = 'KH';

if (isset($_SESSION['user_id'])) {
    $userId = (int)$_SESSION['user_id'];
    $userName = ($_SESSION['user_name'] ?? 'Mindrift') . ' Schedule & Projects';
    session_write_close();
    $db = getDbConnection();
} elseif (!empty($_GET['token'])) {
    session_write_close();
    $db = getDbConnection();
    $tokenUser = getUserByScheduleToken($db, trim($_GET['token']));
    if ($tokenUser) {
        $userId = (int)$tokenUser['id'];
        $userName = ($tokenUser['name'] ?? 'Mindrift') . ' Schedule & Projects';
    }
} else {
    session_write_close();
}

if (!$userId) {
    http_response_code(401);
    die('Unauthorized: A valid login session or calendar share token is required.');
}

if (!empty($_GET['country'])) {
    $cReq = strtoupper(trim($_GET['country']));
    if (in_array($cReq, ['KH', 'US', 'GLOBAL'], true)) {
        $country = $cReq;
    }
}

// 1. Fetch all active tasks with explicit columns
$stmt = $db->prepare("SELECT id, task_name, project_name, assigned_to, priority, status, start_date, due_date 
                      FROM tasks 
                      WHERE user_id = :uid AND deleted_at IS NULL 
                      ORDER BY id ASC");
$stmt->execute(['uid' => $userId]);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 2. Fetch all user projects
$stmtProj = $db->prepare("SELECT id, code, name, category, progress_pct, status, start_date, due_date 
                          FROM courses 
                          WHERE user_id = :uid 
                          ORDER BY id ASC");
$stmtProj->execute(['uid' => $userId]);
$projects = $stmtProj->fetchAll(PDO::FETCH_ASSOC);

// 3. Fetch project milestones
$milestones = [];
if (!empty($projects)) {
    $pIds = array_column($projects, 'id');
    $inClause = implode(',', array_map('intval', $pIds));
    $stmtM = $db->query("SELECT m.id, m.project_id, m.name, m.due_date, m.status, c.name as project_name 
                         FROM milestones m 
                         JOIN courses c ON m.project_id = c.id 
                         WHERE m.project_id IN ($inClause)");
    $milestones = $stmtM ? $stmtM->fetchAll(PDO::FETCH_ASSOC) : [];
}

// 4. Fetch official government holidays for current year +/- 1 year
$currentYear = (int)date('Y');
$yearsToFetch = [$currentYear - 1, $currentYear, $currentYear + 1];
$allHolidays = [];
foreach ($yearsToFetch as $y) {
    $gov = getOfficialGovernmentHolidays($y, $country);
    foreach ($gov as $d => $h) {
        $allHolidays[$d] = $h;
    }
}

function formatDateToIcs(?string $dStr, string $fallback = 'now'): string {
    if (empty($dStr)) {
        $ts = strtotime($fallback);
    } else {
        $ts = strtotime(str_replace('/', '-', $dStr));
        if (!$ts) $ts = strtotime($fallback);
    }
    return gmdate('Ymd\THis\Z', $ts ?: time());
}

function formatDateDayOnly(string $isoDate): string {
    $ts = strtotime($isoDate);
    return date('Ymd', $ts ?: time());
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
    'PRODID:-//Mindrift//Mindrift Productivity Schedule//EN',
    'CALSCALE:GREGORIAN',
    'METHOD:PUBLISH',
    'X-WR-CALNAME:' . escapeIcsText($userName),
    'X-WR-TIMEZONE:UTC'
];

$nowIcs = gmdate('Ymd\THis\Z');

// 1. Export Government Public Holidays / Days Off
foreach ($allHolidays as $isoDate => $h) {
    $dayIcs = formatDateDayOnly($isoDate);
    $nextDayIcs = date('Ymd', strtotime($isoDate . ' +1 day'));
    $summary = escapeIcsText(($h['flag'] ?? '🏛️') . ' ' . $h['name'] . ' (Official Day Off)');
    $desc = escapeIcsText($h['description'] . (!empty($h['local_name']) ? "\nLocal: " . $h['local_name'] : ''));

    $lines[] = 'BEGIN:VEVENT';
    $lines[] = "UID:mindrift-gov-holiday-{$country}-{$isoDate}@mindrift.io";
    $lines[] = "DTSTAMP:{$nowIcs}";
    $lines[] = "DTSTART;VALUE=DATE:{$dayIcs}";
    $lines[] = "DTEND;VALUE=DATE:{$nextDayIcs}";
    $lines[] = "SUMMARY:{$summary}";
    $lines[] = "DESCRIPTION:{$desc}";
    $lines[] = 'STATUS:CONFIRMED';
    $lines[] = 'TRANSP:TRANSPARENT';
    $lines[] = 'END:VEVENT';
}

// 2. Export Projects (Kickoff & Due Dates)
foreach ($projects as $p) {
    $projId = (int)$p['id'];
    $projName = escapeIcsText($p['name']);
    $code = escapeIcsText($p['code'] ?? 'PRJ');
    $status = escapeIcsText($p['status'] ?? 'Active');
    $prog = (int)$p['progress_pct'];

    // Project Due Date
    if (!empty($p['due_date'])) {
        $tsDue = strtotime(str_replace('/', '-', $p['due_date']));
        if ($tsDue) {
            $dueDayIcs = date('Ymd', $tsDue);
            $nextDueDayIcs = date('Ymd', strtotime('+1 day', $tsDue));
            $summary = escapeIcsText("🚀 [{$code}] {$p['name']} - Project Deadline");
            $desc = escapeIcsText("Project: {$projName}\nCode: {$code}\nCategory: {$p['category']}\nProgress: {$prog}%\nStatus: {$status}");

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = "UID:mindrift-project-due-{$projId}@mindrift.io";
            $lines[] = "DTSTAMP:{$nowIcs}";
            $lines[] = "DTSTART;VALUE=DATE:{$dueDayIcs}";
            $lines[] = "DTEND;VALUE=DATE:{$nextDueDayIcs}";
            $lines[] = "SUMMARY:{$summary}";
            $lines[] = "DESCRIPTION:{$desc}";
            $lines[] = 'PRIORITY:1';
            $lines[] = 'STATUS:CONFIRMED';
            $lines[] = 'BEGIN:VALARM';
            $lines[] = 'ACTION:DISPLAY';
            $lines[] = 'DESCRIPTION:Project Launch / Deadline Today!';
            $lines[] = 'TRIGGER:-PT4H';
            $lines[] = 'END:VALARM';
            $lines[] = 'END:VEVENT';
        }
    }

    // Project Kickoff Date
    if (!empty($p['start_date'])) {
        $tsStart = strtotime(str_replace('/', '-', $p['start_date']));
        if ($tsStart) {
            $startDayIcs = date('Ymd', $tsStart);
            $nextStartDayIcs = date('Ymd', strtotime('+1 day', $tsStart));
            $summary = escapeIcsText("🏁 [{$code}] {$p['name']} - Project Kickoff");
            $desc = escapeIcsText("Project Kickoff for {$projName} ({$code})");

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = "UID:mindrift-project-start-{$projId}@mindrift.io";
            $lines[] = "DTSTAMP:{$nowIcs}";
            $lines[] = "DTSTART;VALUE=DATE:{$startDayIcs}";
            $lines[] = "DTEND;VALUE=DATE:{$nextStartDayIcs}";
            $lines[] = "SUMMARY:{$summary}";
            $lines[] = "DESCRIPTION:{$desc}";
            $lines[] = 'STATUS:CONFIRMED';
            $lines[] = 'TRANSP:TRANSPARENT';
            $lines[] = 'END:VEVENT';
        }
    }
}

// 3. Export Project Milestones
foreach ($milestones as $m) {
    $mId = (int)$m['id'];
    $mName = escapeIcsText($m['name']);
    $projName = escapeIcsText($m['project_name'] ?? 'Project');
    $tsM = !empty($m['due_date']) ? strtotime(str_replace('/', '-', $m['due_date'])) : null;
    if ($tsM) {
        $mDayIcs = date('Ymd', $tsM);
        $nextMDayIcs = date('Ymd', strtotime('+1 day', $tsM));
        $summary = escapeIcsText("🎯 Milestone: {$m['name']} ({$projName})");

        $lines[] = 'BEGIN:VEVENT';
        $lines[] = "UID:mindrift-milestone-{$mId}@mindrift.io";
        $lines[] = "DTSTAMP:{$nowIcs}";
        $lines[] = "DTSTART;VALUE=DATE:{$mDayIcs}";
        $lines[] = "DTEND;VALUE=DATE:{$nextMDayIcs}";
        $lines[] = "SUMMARY:{$summary}";
        $lines[] = "DESCRIPTION:Milestone for project {$projName}";
        $lines[] = 'STATUS:CONFIRMED';
        $lines[] = 'END:VEVENT';
    }
}

// 4. Export Tasks
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
header('Content-Disposition: attachment; filename="mindrift-calendar-schedule.ics"');
header('Content-Length: ' . strlen($icsContent));
header('Cache-Control: no-cache, no-store, must-revalidate');

echo $icsContent;
