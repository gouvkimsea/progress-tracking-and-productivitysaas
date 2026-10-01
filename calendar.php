<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/holidays.php';
startSecureSession();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$db = getDbConnection();
$userId = (int)$_SESSION['user_id'];

// Fetch User Info
$stmtUser = $db->prepare("SELECT * FROM users WHERE id = :uid");
$stmtUser->execute(['uid' => $userId]);
$user = $stmtUser->fetch();

// Request parameters: Month, Year, Day, Country, Project Filter, View
$reqMonth = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
$reqYear = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$reqDay = isset($_GET['day']) ? (int)$_GET['day'] : (int)date('j');
$reqCountry = isset($_GET['country']) ? strtoupper(trim($_GET['country'])) : 'KH';
$reqProject = isset($_GET['project_id']) && $_GET['project_id'] !== 'all' ? (int)$_GET['project_id'] : null;
$reqView = isset($_GET['view']) ? strtolower(trim($_GET['view'])) : 'month';

if ($reqMonth < 1 || $reqMonth > 12) $reqMonth = (int)date('n');
if ($reqYear < 2000 || $reqYear > 2100) $reqYear = (int)date('Y');
if ($reqDay < 1 || $reqDay > 31) $reqDay = (int)date('j');
if (!in_array($reqCountry, ['KH', 'US', 'GLOBAL'], true)) $reqCountry = 'KH';
if (!in_array($reqView, ['month', 'week', 'day', 'schedule'], true)) $reqView = 'month';

$prevMonth = ($reqMonth === 1) ? 12 : $reqMonth - 1;
$prevYear = ($reqMonth === 1) ? $reqYear - 1 : $reqYear;
$nextMonth = ($reqMonth === 12) ? 1 : $reqMonth + 1;
$nextYear = ($reqMonth === 12) ? $reqYear + 1 : $reqYear;

$monthTs = mktime(0, 0, 0, $reqMonth, 1, $reqYear);
$monthLabel = date('F Y', $monthTs);
$firstDayOfWeek = (int)date('w', $monthTs); // 0 (Sun) to 6 (Sat) - Google Calendar Sunday-first
$daysInMonth = (int)date('t', $monthTs);

// Days in previous month for grid leading padding
$prevMonthDays = (int)date('t', mktime(0, 0, 0, $prevMonth, 1, $prevYear));

// 1. Fetch Government Days Off & Custom Holidays for this month
$holidaysByDay = getMonthHolidaysByDay($db, $userId, $reqYear, $reqMonth, $reqCountry);

// Also fetch surrounding months for overflow display
$prevMonthHolidays = getMonthHolidaysByDay($db, $userId, $prevYear, $prevMonth, $reqCountry);
$nextMonthHolidays = getMonthHolidaysByDay($db, $userId, $nextYear, $nextMonth, $reqCountry);

// 2. Fetch User's Projects (courses)
$projSql = "SELECT id, code, name, category, level, total_modules, completed_modules, progress_pct, 
                   bg_gradient, ring_color, is_starred, status, start_date, due_date, created_at 
            FROM courses 
            WHERE user_id = :uid";
$projParams = ['uid' => $userId];
if ($reqProject !== null) {
    $projSql .= " AND id = :pid";
    $projParams['pid'] = $reqProject;
}
$projSql .= " ORDER BY is_starred DESC, id ASC";
$stmtProj = $db->prepare($projSql);
$stmtProj->execute($projParams);
$projects = $stmtProj->fetchAll(PDO::FETCH_ASSOC);

// Map projects by id and lowercase name for color styling
$projectsById = [];
$projectsByName = [];
foreach ($projects as $p) {
    $projectsById[$p['id']] = $p;
    $projectsByName[strtolower(trim($p['name']))] = $p;
}

// 3. Project Events for current month (Launch / Due Dates & Kickoffs)
$projectEventsByDay = [];
$upcomingDeadlines = [];

foreach ($projects as $p) {
    // Project Due Date
    if (!empty($p['due_date'])) {
        $tsDue = strtotime(str_replace('/', '-', $p['due_date']));
        if ($tsDue) {
            if ((int)date('n', $tsDue) === $reqMonth && (int)date('Y', $tsDue) === $reqYear) {
                $dayNum = (int)date('j', $tsDue);
                $projectEventsByDay[$dayNum][] = [
                    'id' => 'p_due_' . $p['id'],
                    'type' => 'project_due',
                    'project_id' => $p['id'],
                    'project_name' => $p['name'],
                    'project_code' => $p['code'],
                    'ring_color' => $p['ring_color'] ?? '#EA4335',
                    'progress_pct' => (int)$p['progress_pct'],
                    'status' => $p['status'] ?? 'In Progress',
                    'date_str' => date('d-m-Y', $tsDue),
                    'time_str' => '11:59 PM',
                    'title' => "Launch: {$p['name']}"
                ];
            }
            if ($tsDue >= strtotime('today')) {
                $upcomingDeadlines[] = [
                    'item_type' => 'project_deadline',
                    'project' => $p,
                    'timestamp' => $tsDue,
                    'formatted_date' => date('l, M j', $tsDue)
                ];
            }
        }
    }

    // Project Kickoff
    if (!empty($p['start_date'])) {
        $tsStart = strtotime(str_replace('/', '-', $p['start_date']));
        if ($tsStart && (int)date('n', $tsStart) === $reqMonth && (int)date('Y', $tsStart) === $reqYear) {
            $dayNum = (int)date('j', $tsStart);
            $projectEventsByDay[$dayNum][] = [
                'id' => 'p_start_' . $p['id'],
                'type' => 'project_start',
                'project_id' => $p['id'],
                'project_name' => $p['name'],
                'project_code' => $p['code'],
                'ring_color' => $p['ring_color'] ?? '#34A853',
                'progress_pct' => (int)$p['progress_pct'],
                'status' => $p['status'] ?? 'In Progress',
                'date_str' => date('d-m-Y', $tsStart),
                'time_str' => '9:00 AM',
                'title' => "Kickoff: {$p['name']}"
            ];
        }
    }
}

// 4. Fetch Project Milestones
$milestonesByDay = [];
if (!empty($projects)) {
    $pIds = array_keys($projectsById);
    $inClause = implode(',', array_map('intval', $pIds));
    $stmtM = $db->query("SELECT m.id, m.project_id, m.name, m.due_date, m.status, 
                                c.name as project_name, c.ring_color, c.code as project_code 
                         FROM milestones m 
                         JOIN courses c ON m.project_id = c.id 
                         WHERE m.project_id IN ($inClause)");
    $milestones = $stmtM ? $stmtM->fetchAll(PDO::FETCH_ASSOC) : [];
    foreach ($milestones as $m) {
        if (!empty($m['due_date'])) {
            $ts = strtotime(str_replace('/', '-', $m['due_date']));
            if ($ts) {
                if ((int)date('n', $ts) === $reqMonth && (int)date('Y', $ts) === $reqYear) {
                    $dayNum = (int)date('j', $ts);
                    $m['time_str'] = '2:00 PM';
                    $milestonesByDay[$dayNum][] = $m;
                }
                if ($ts >= strtotime('today')) {
                    $upcomingDeadlines[] = [
                        'item_type' => 'milestone',
                        'milestone' => $m,
                        'timestamp' => $ts,
                        'formatted_date' => date('l, M j', $ts)
                    ];
                }
            }
        }
    }
}

// 5. Fetch Tasks for user
$taskSql = "SELECT id, user_id, task_name, project_name, start_date, due_date, priority, assigned_to, status, time_log, created_at 
            FROM tasks 
            WHERE user_id = :uid AND deleted_at IS NULL";
$taskParams = ['uid' => $userId];

if ($reqProject !== null && isset($projectsById[$reqProject])) {
    $taskSql .= " AND project_name = :pname";
    $taskParams['pname'] = $projectsById[$reqProject]['name'];
}
$taskSql .= " ORDER BY id ASC";
$stmtTasks = $db->prepare($taskSql);
$stmtTasks->execute($taskParams);
$allTasks = $stmtTasks->fetchAll(PDO::FETCH_ASSOC);

$tasksByDay = [];
foreach ($allTasks as $t) {
    $dStr = !empty($t['due_date']) ? $t['due_date'] : ($t['start_date'] ?? '');
    if (!empty($dStr)) {
        $ts = strtotime(str_replace('/', '-', $dStr));
        if ($ts) {
            $pNameKey = strtolower(trim($t['project_name'] ?? ''));
            $matchedProj = $projectsByName[$pNameKey] ?? null;
            $t['ring_color'] = $matchedProj['ring_color'] ?? '#1A73E8';
            $t['project_code'] = $matchedProj['code'] ?? 'TASK';
            $t['time_str'] = '10:00 AM';

            if ((int)date('n', $ts) === $reqMonth && (int)date('Y', $ts) === $reqYear) {
                $dayNum = (int)date('j', $ts);
                $tasksByDay[$dayNum][] = $t;
            }
            if ($ts >= strtotime('today')) {
                $upcomingDeadlines[] = [
                    'item_type' => 'task',
                    'task' => $t,
                    'timestamp' => $ts,
                    'formatted_date' => date('l, M j', $ts)
                ];
            }
        }
    }
}

usort($upcomingDeadlines, fn($a, $b) => $a['timestamp'] <=> $b['timestamp']);

// 6. Detect Schedule Conflicts on Government Days Off
$holidayConflicts = [];
foreach ($holidaysByDay as $dNum => $hList) {
    $hasDayOff = false;
    $hTitle = '';
    foreach ($hList as $h) {
        if ($h['is_day_off']) {
            $hasDayOff = true;
            $hTitle = $h['name'];
            break;
        }
    }
    if ($hasDayOff) {
        $cTasks = $tasksByDay[$dNum] ?? [];
        $cProjects = $projectEventsByDay[$dNum] ?? [];
        if (!empty($cTasks) || !empty($cProjects)) {
            $holidayConflicts[] = [
                'day' => $dNum,
                'holiday_name' => $hTitle,
                'tasks_count' => count($cTasks),
                'projects_count' => count($cProjects),
                'tasks' => $cTasks,
                'projects' => $cProjects
            ];
        }
    }
}

// Country flags and labels
$countryLabels = [
    'KH' => '🇰🇭 Cambodia (Prakas)',
    'US' => '🇺🇸 United States',
    'GLOBAL' => '🌐 International'
];

$pageTitle = 'Google Calendar — Mindrift Workspace';
include __DIR__ . '/includes/head.php';
?>
<style>
  /* ======================================================== */
  /* GOOGLE CALENDAR AUTHENTIC DESIGN SYSTEM & COLOR TOKENS   */
  /* ======================================================== */
  :root {
    --gcal-bg: #FFFFFF;
    --gcal-surface: #FFFFFF;
    --gcal-sidebar-bg: #FFFFFF;
    --gcal-border: #DADCE0;
    --gcal-border-subtle: #E8EAED;
    --gcal-text-primary: #3C4043;
    --gcal-text-secondary: #70757A;
    --gcal-text-muted: #80868B;
    --gcal-blue: #1A73E8;
    --gcal-blue-hover: #1765CC;
    --gcal-blue-light: #E8F0FE;
    --gcal-today-bg: #1A73E8;
    --gcal-today-text: #FFFFFF;
    --gcal-holiday-chip-bg: #FEF3C7;
    --gcal-holiday-chip-text: #92400E;
    --gcal-holiday-border: #F59E0B;
    --gcal-hover-bg: #F1F3F4;
    --gcal-cell-active: #F8F9FA;
    --gcal-chip-deadline: #D93025;
    --gcal-chip-kickoff: #1E8E3E;
    --gcal-chip-milestone: #1A73E8;
    --gcal-chip-task: #1A73E8;
    --gcal-shadow-sm: 0 1px 2px 0 rgba(60,64,67,0.3), 0 1px 3px 1px rgba(60,64,67,0.15);
    --gcal-shadow-md: 0 1px 3px 0 rgba(60,64,67,0.3), 0 4px 8px 3px rgba(60,64,67,0.15);
    --gcal-shadow-lg: 0 4px 16px rgba(0, 0, 0, 0.12);
  }

  [data-theme="dark"] {
    --gcal-bg: #1F1F1F;
    --gcal-surface: #28292A;
    --gcal-sidebar-bg: #1F1F1F;
    --gcal-border: #3C4043;
    --gcal-border-subtle: #2F3133;
    --gcal-text-primary: #E8EAED;
    --gcal-text-secondary: #9AA0A6;
    --gcal-text-muted: #80868B;
    --gcal-blue: #8AB4F8;
    --gcal-blue-hover: #AECBFA;
    --gcal-blue-light: rgba(138, 180, 248, 0.15);
    --gcal-today-bg: #8AB4F8;
    --gcal-today-text: #202124;
    --gcal-holiday-chip-bg: rgba(245, 158, 11, 0.2);
    --gcal-holiday-chip-text: #FCD34D;
    --gcal-holiday-border: rgba(245, 158, 11, 0.5);
    --gcal-hover-bg: #303134;
    --gcal-cell-active: #2D2E30;
    --gcal-chip-deadline: #F28B82;
    --gcal-chip-kickoff: #81C995;
    --gcal-chip-milestone: #8AB4F8;
    --gcal-chip-task: #8AB4F8;
    --gcal-shadow-sm: 0 1px 3px rgba(0,0,0,0.5);
    --gcal-shadow-md: 0 4px 12px rgba(0,0,0,0.4);
    --gcal-shadow-lg: 0 6px 20px rgba(0,0,0,0.6);
  }

  /* Full Screen Calendar Canvas Wrapper */
  .gcal-container {
    display: flex;
    flex-direction: column;
    height: calc(100vh - 84px);
    background: var(--gcal-bg);
    border: 1px solid var(--gcal-border);
    border-radius: 8px;
    overflow: hidden;
    margin-bottom: 24px;
    box-shadow: var(--gcal-shadow-sm);
  }

  /* -------------------------------------------------------- */
  /* GOOGLE CALENDAR TOPBAR HEADER                            */
  /* -------------------------------------------------------- */
  .gcal-header {
    height: 64px;
    padding: 8px 16px;
    border-bottom: 1px solid var(--gcal-border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: var(--gcal-surface);
    gap: 12px;
    user-select: none;
    flex-shrink: 0;
  }

  .gcal-header-left {
    display: flex;
    align-items: center;
    gap: 12px;
  }

  /* Hamburger Toggle */
  .gcal-icon-btn {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    border: none;
    background: transparent;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--gcal-text-secondary);
    cursor: pointer;
    transition: background 0.15s, color 0.15s;
  }
  .gcal-icon-btn:hover {
    background: var(--gcal-hover-bg);
    color: var(--gcal-text-primary);
  }

  /* Google Calendar Logo Badge */
  .gcal-logo {
    display: flex;
    align-items: center;
    gap: 10px;
    text-decoration: none;
    margin-right: 8px;
  }
  .gcal-logo-icon {
    width: 38px;
    height: 38px;
    border-radius: 8px;
    background: linear-gradient(135deg, #4285F4 0%, #1A73E8 100%);
    box-shadow: 0 2px 5px rgba(66, 133, 244, 0.35);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: #FFFFFF;
    font-weight: 800;
    line-height: 1;
    position: relative;
    overflow: hidden;
  }
  .gcal-logo-icon::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 10px;
    background: #EA4335;
  }
  .gcal-logo-num {
    font-size: 15px;
    font-weight: 900;
    margin-top: 6px;
    font-family: 'Inter', sans-serif;
  }
  .gcal-logo-title {
    font-size: 21px;
    font-weight: 600;
    color: var(--gcal-text-primary);
    letter-spacing: -0.02em;
  }
  .gcal-logo-badge {
    font-size: 10px;
    font-weight: 700;
    background: var(--gcal-blue-light);
    color: var(--gcal-blue);
    padding: 2px 6px;
    border-radius: 4px;
    margin-left: 2px;
  }

  /* "Today" Google Button */
  .gcal-btn-today {
    height: 36px;
    padding: 0 16px;
    border-radius: 4px;
    border: 1px solid var(--gcal-border);
    background: transparent;
    color: var(--gcal-text-primary);
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
  }
  .gcal-btn-today:hover {
    background: var(--gcal-hover-bg);
    border-color: var(--gcal-border);
  }

  /* Nav Chevrons */
  .gcal-nav-arrows {
    display: flex;
    align-items: center;
    gap: 2px;
  }
  .gcal-nav-arrow-btn {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    border: none;
    background: transparent;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--gcal-text-secondary);
    cursor: pointer;
    text-decoration: none;
    font-size: 18px;
    font-weight: 700;
    transition: background 0.15s, color 0.15s;
  }
  .gcal-nav-arrow-btn:hover {
    background: var(--gcal-hover-bg);
    color: var(--gcal-text-primary);
  }

  .gcal-header-date-title {
    font-size: 20px;
    font-weight: 600;
    color: var(--gcal-text-primary);
    margin-left: 8px;
    white-space: nowrap;
  }

  /* Header Right Section */
  .gcal-header-right {
    display: flex;
    align-items: center;
    gap: 10px;
  }

  /* Google Search Box */
  .gcal-search-box {
    position: relative;
    width: 240px;
    transition: width 0.2s;
  }
  .gcal-search-input {
    width: 100%;
    height: 38px;
    border-radius: 8px;
    background: var(--gcal-hover-bg);
    border: 1px solid transparent;
    padding: 0 12px 0 36px;
    font-size: 13px;
    color: var(--gcal-text-primary);
    outline: none;
    transition: all 0.15s;
  }
  .gcal-search-input:focus {
    background: var(--gcal-surface);
    border-color: var(--gcal-blue);
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    width: 280px;
  }
  .gcal-search-icon {
    position: absolute;
    left: 10px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--gcal-text-muted);
    pointer-events: none;
  }

  /* Country Selector Dropdown Pill */
  .gcal-select-pill {
    height: 36px;
    border-radius: 18px;
    border: 1px solid var(--gcal-border);
    background: var(--gcal-surface);
    color: var(--gcal-text-primary);
    font-size: 12.5px;
    font-weight: 600;
    padding: 0 12px;
    cursor: pointer;
    outline: none;
  }
  .gcal-select-pill:focus { border-color: var(--gcal-blue); }

  /* Google Calendar View Switcher Dropdown (Month / Week / Day / Schedule) */
  .gcal-view-dropdown {
    position: relative;
  }
  .gcal-view-btn {
    height: 36px;
    border-radius: 4px;
    border: 1px solid var(--gcal-border);
    background: var(--gcal-surface);
    color: var(--gcal-text-primary);
    font-size: 13.5px;
    font-weight: 600;
    padding: 0 12px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    transition: background 0.15s;
  }
  .gcal-view-btn:hover { background: var(--gcal-hover-bg); }

  .gcal-dropdown-menu {
    position: absolute;
    top: 42px;
    right: 0;
    background: var(--gcal-surface);
    border: 1px solid var(--gcal-border);
    border-radius: 6px;
    box-shadow: var(--gcal-shadow-md);
    min-width: 150px;
    z-index: 1000;
    display: none;
    padding: 6px 0;
  }
  .gcal-dropdown-menu.active { display: block; }
  .gcal-dropdown-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 16px;
    font-size: 13px;
    font-weight: 500;
    color: var(--gcal-text-primary);
    text-decoration: none;
    cursor: pointer;
  }
  .gcal-dropdown-item:hover { background: var(--gcal-hover-bg); }
  .gcal-dropdown-item.active { font-weight: 700; color: var(--gcal-blue); background: var(--gcal-blue-light); }
  .gcal-shortcut-kbd { font-size: 10.5px; color: var(--gcal-text-muted); font-family: monospace; background: var(--gcal-hover-bg); padding: 1px 4px; border-radius: 3px; }

  /* -------------------------------------------------------- */
  /* GCAL MAIN BODY SPLIT: SIDEBAR + CALENDAR CANVAS          */
  /* -------------------------------------------------------- */
  .gcal-body {
    display: flex;
    flex: 1;
    overflow: hidden;
  }

  /* Left Sidebar */
  .gcal-sidebar {
    width: 256px;
    background: var(--gcal-sidebar-bg);
    border-right: 1px solid var(--gcal-border);
    padding: 16px 14px;
    display: flex;
    flex-direction: column;
    gap: 18px;
    overflow-y: auto;
    overflow-x: hidden;
    flex-shrink: 0;
    transition: margin-left 0.25s cubic-bezier(0.4, 0, 0.2, 1);
  }
  .gcal-sidebar.collapsed {
    margin-left: -256px;
  }

  /* Google "+ Create" Pill Button */
  .gcal-btn-create {
    height: 48px;
    padding: 0 24px 0 16px;
    border-radius: 24px;
    background: var(--gcal-surface);
    border: 1px solid var(--gcal-border-subtle);
    box-shadow: 0 1px 3px 0 rgba(60,64,67,0.3), 0 4px 8px 3px rgba(60,64,67,0.15);
    display: inline-flex;
    align-items: center;
    gap: 12px;
    font-size: 14px;
    font-weight: 600;
    color: var(--gcal-text-primary);
    cursor: pointer;
    transition: box-shadow 0.2s, background 0.15s;
    user-select: none;
    align-self: flex-start;
  }
  .gcal-btn-create:hover {
    box-shadow: 0 2px 6px 2px rgba(60,64,67,0.25), 0 6px 14px 4px rgba(60,64,67,0.18);
    background: var(--gcal-hover-bg);
  }
  .gcal-btn-create-icon {
    width: 24px;
    height: 24px;
  }

  /* Mini Calendar (Date Picker) */
  .gcal-mini-cal {
    user-select: none;
  }
  .gcal-mini-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 8px;
    padding: 0 4px;
  }
  .gcal-mini-title {
    font-size: 13px;
    font-weight: 700;
    color: var(--gcal-text-primary);
  }
  .gcal-mini-nav {
    display: flex;
    gap: 2px;
  }
  .gcal-mini-nav-btn {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    border: none;
    background: transparent;
    color: var(--gcal-text-secondary);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    text-decoration: none;
    font-size: 14px;
  }
  .gcal-mini-nav-btn:hover { background: var(--gcal-hover-bg); }

  .gcal-mini-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    text-align: center;
    gap: 2px;
  }
  .gcal-mini-day-hdr {
    font-size: 10px;
    font-weight: 700;
    color: var(--gcal-text-muted);
    padding: 4px 0;
  }
  .gcal-mini-cell {
    font-size: 11px;
    font-weight: 500;
    height: 26px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    color: var(--gcal-text-primary);
    cursor: pointer;
    text-decoration: none;
    transition: all 0.15s;
  }
  .gcal-mini-cell:hover {
    background: var(--gcal-hover-bg);
  }
  .gcal-mini-cell.other-month {
    color: var(--gcal-text-muted);
    opacity: 0.4;
  }
  .gcal-mini-cell.today {
    background: var(--gcal-blue);
    color: #FFFFFF;
    font-weight: 700;
  }
  .gcal-mini-cell.has-events::after {
    content: '';
    position: absolute;
    bottom: 2px;
    width: 3px;
    height: 3px;
    border-radius: 50%;
    background: var(--gcal-blue);
  }

  /* Sidebar Collapsible Section */
  .gcal-sidebar-section {
    display: flex;
    flex-direction: column;
    gap: 8px;
  }
  .gcal-section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 12px;
    font-weight: 700;
    color: var(--gcal-text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.04em;
    cursor: pointer;
    padding: 4px 2px;
  }
  .gcal-section-items {
    display: flex;
    flex-direction: column;
    gap: 4px;
  }

  /* Google-Style Checkbox Item */
  .gcal-check-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 4px 6px;
    border-radius: 4px;
    font-size: 13px;
    font-weight: 500;
    color: var(--gcal-text-primary);
    cursor: pointer;
    transition: background 0.15s;
    user-select: none;
  }
  .gcal-check-item:hover { background: var(--gcal-hover-bg); }

  .gcal-custom-checkbox {
    width: 16px;
    height: 16px;
    border-radius: 3px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    flex-shrink: 0;
    transition: background 0.15s, border-color 0.15s;
  }

  /* -------------------------------------------------------- */
  /* MAIN CALENDAR CANVAS & GRID                              */
  /* -------------------------------------------------------- */
  .gcal-canvas {
    flex: 1;
    display: flex;
    flex-direction: column;
    background: var(--gcal-bg);
    overflow: hidden;
    position: relative;
  }

  /* Weekday Header Row (SUN to SAT) */
  .gcal-grid-header {
    height: 36px;
    border-bottom: 1px solid var(--gcal-border);
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    background: var(--gcal-surface);
    flex-shrink: 0;
  }
  .gcal-day-col-header {
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.05em;
    color: var(--gcal-text-muted);
    border-right: 1px solid var(--gcal-border);
    text-transform: uppercase;
  }
  .gcal-day-col-header:last-child { border-right: none; }
  .gcal-day-col-header.today { color: var(--gcal-blue); }

  /* Calendar Grid Body (Month View) */
  .gcal-month-grid {
    flex: 1;
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    grid-auto-rows: 1fr;
    overflow-y: auto;
    background: var(--gcal-bg);
  }

  /* Day Cell in Month Grid */
  .gcal-day-box {
    border-right: 1px solid var(--gcal-border);
    border-bottom: 1px solid var(--gcal-border);
    padding: 4px 6px;
    display: flex;
    flex-direction: column;
    gap: 3px;
    min-height: 100px;
    position: relative;
    cursor: pointer;
    transition: background 0.1s;
    overflow: hidden;
  }
  .gcal-day-box:nth-child(7n) { border-right: none; }
  .gcal-day-box:hover {
    background: var(--gcal-hover-bg);
  }
  .gcal-day-box.is-today {
    background: var(--gcal-cell-active);
  }
  .gcal-day-box.is-other-month {
    background: rgba(0, 0, 0, 0.015);
  }
  .gcal-day-box.is-holiday-day {
    background: var(--gcal-holiday-chip-bg) !important;
  }

  /* Day Number Top Circle */
  .gcal-day-box-header {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 28px;
    position: relative;
  }
  .gcal-day-num {
    font-size: 12px;
    font-weight: 500;
    color: var(--gcal-text-primary);
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.15s;
  }
  .gcal-day-box.is-today .gcal-day-num {
    background: var(--gcal-today-bg);
    color: var(--gcal-today-text);
    font-weight: 700;
    width: 26px;
    height: 26px;
  }
  .gcal-day-box.is-other-month .gcal-day-num {
    color: var(--gcal-text-muted);
    opacity: 0.45;
  }

  /* Floating quick add button on hover */
  .gcal-day-add-icon {
    position: absolute;
    right: 4px;
    top: 4px;
    opacity: 0;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    border: none;
    background: var(--gcal-blue);
    color: #FFF;
    font-size: 14px;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
    transition: opacity 0.15s, transform 0.15s;
  }
  .gcal-day-box:hover .gcal-day-add-icon {
    opacity: 1;
    transform: scale(1.05);
  }

  /* Events Container Inside Day Cell */
  .gcal-events-flow {
    display: flex;
    flex-direction: column;
    gap: 2px;
    overflow: hidden;
    flex: 1;
  }

  /* -------------------------------------------------------- */
  /* GOOGLE CALENDAR EVENT CHIPS                              */
  /* -------------------------------------------------------- */
  /* 1. All-Day / High-Priority Pill (Solid colored) */
  .gcal-chip-solid {
    height: 22px;
    line-height: 22px;
    border-radius: 4px;
    padding: 0 8px;
    font-size: 11.5px;
    font-weight: 600;
    color: #FFFFFF;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    display: flex;
    align-items: center;
    gap: 6px;
    cursor: pointer;
    box-shadow: 0 1px 2px rgba(0,0,0,0.1);
    transition: filter 0.15s, transform 0.08s;
  }
  .gcal-chip-solid:hover {
    filter: brightness(0.92);
    transform: translateY(-0.5px);
  }

  /* Government Holiday Chip (Iconic Google Calendar holiday styling) */
  .gcal-chip-holiday {
    height: 22px;
    line-height: 22px;
    border-radius: 4px;
    padding: 0 8px;
    font-size: 11px;
    font-weight: 700;
    background: var(--gcal-holiday-chip-bg);
    color: var(--gcal-holiday-chip-text);
    border-left: 3px solid var(--gcal-holiday-border);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    display: flex;
    align-items: center;
    gap: 5px;
    cursor: pointer;
    transition: filter 0.15s;
  }
  .gcal-chip-holiday:hover { filter: brightness(0.95); }

  /* 2. Timed Event / Task Pill (Colored dot on left, text on right) */
  .gcal-chip-timed {
    height: 20px;
    line-height: 20px;
    border-radius: 3px;
    padding: 0 6px;
    font-size: 11px;
    font-weight: 500;
    color: var(--gcal-text-primary);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    display: flex;
    align-items: center;
    gap: 5px;
    cursor: pointer;
    transition: background 0.15s;
  }
  .gcal-chip-timed:hover {
    background: var(--gcal-hover-bg);
  }
  .gcal-chip-timed-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    flex-shrink: 0;
  }
  .gcal-chip-time-lbl {
    font-size: 10px;
    font-weight: 700;
    color: var(--gcal-text-secondary);
  }

  /* "+X more" chip */
  .gcal-more-link {
    font-size: 11px;
    font-weight: 700;
    color: var(--gcal-text-secondary);
    padding: 1px 6px;
    border-radius: 3px;
    cursor: pointer;
    margin-top: 1px;
    display: inline-block;
  }
  .gcal-more-link:hover {
    background: var(--gcal-hover-bg);
    color: var(--gcal-blue);
  }

  /* -------------------------------------------------------- */
  /* GOOGLE CALENDAR WEEK & AGENDA / SCHEDULE VIEWS           */
  /* -------------------------------------------------------- */
  .gcal-schedule-view {
    padding: 24px 32px;
    overflow-y: auto;
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 18px;
    background: var(--gcal-bg);
  }
  .gcal-agenda-day-group {
    display: flex;
    gap: 24px;
    padding-bottom: 16px;
    border-bottom: 1px solid var(--gcal-border-subtle);
  }
  .gcal-agenda-date-badge {
    width: 110px;
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
  }
  .gcal-agenda-day-num {
    font-size: 26px;
    font-weight: 700;
    color: var(--gcal-text-primary);
    line-height: 1;
  }
  .gcal-agenda-day-num.today {
    color: var(--gcal-blue);
  }
  .gcal-agenda-day-name {
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    color: var(--gcal-text-muted);
    margin-top: 4px;
  }
  .gcal-agenda-events-col {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 8px;
  }
  .gcal-agenda-card {
    background: var(--gcal-surface);
    border: 1px solid var(--gcal-border);
    border-left-width: 4px;
    border-radius: 6px;
    padding: 12px 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    transition: transform 0.1s, box-shadow 0.15s;
    cursor: pointer;
  }
  .gcal-agenda-card:hover {
    transform: translateX(2px);
    box-shadow: var(--gcal-shadow-sm);
  }

  /* -------------------------------------------------------- */
  /* GOOGLE FLOATING EVENT POPOVER CARD                       */
  /* -------------------------------------------------------- */
  .gcal-event-popover {
    position: fixed;
    z-index: 2500;
    background: var(--gcal-surface);
    border: 1px solid var(--gcal-border);
    border-radius: 8px;
    box-shadow: var(--gcal-shadow-lg);
    width: 380px;
    max-width: 90vw;
    display: none;
    flex-direction: column;
    overflow: hidden;
    animation: gcalPopIn 0.15s cubic-bezier(0, 0, 0.2, 1);
  }
  .gcal-event-popover.active { display: flex; }
  @keyframes gcalPopIn {
    from { opacity: 0; transform: scale(0.95); }
    to { opacity: 1; transform: scale(1); }
  }

  .gcal-popover-header {
    height: 48px;
    background: var(--gcal-surface);
    border-bottom: 1px solid var(--gcal-border-subtle);
    display: flex;
    align-items: center;
    justify-content: flex-end;
    padding: 0 10px;
    gap: 4px;
  }
  .gcal-popover-body {
    padding: 18px 20px;
    display: flex;
    flex-direction: column;
    gap: 14px;
  }
  .gcal-popover-title-row {
    display: flex;
    align-items: flex-start;
    gap: 12px;
  }
  .gcal-popover-color-dot {
    width: 14px;
    height: 14px;
    border-radius: 4px;
    margin-top: 4px;
    flex-shrink: 0;
  }
  .gcal-popover-title {
    font-size: 18px;
    font-weight: 700;
    color: var(--gcal-text-primary);
    line-height: 1.25;
  }
  .gcal-popover-detail-row {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 13.5px;
    color: var(--gcal-text-secondary);
  }

  /* Quick conflict warning strip inside calendar */
  .gcal-conflict-banner {
    background: rgba(217, 48, 37, 0.08);
    border: 1px solid rgba(217, 48, 37, 0.25);
    border-radius: 6px;
    padding: 8px 14px;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 12.5px;
    color: #D93025;
    font-weight: 600;
  }

  /* Responsive Rules */
  @media (max-width: 900px) {
    .gcal-sidebar { position: fixed; top: 0; bottom: 0; left: 0; z-index: 1500; box-shadow: var(--gcal-shadow-lg); }
    .gcal-sidebar.collapsed { margin-left: -280px; }
    .gcal-search-box { display: none; }
  }
</style>
</head>
<body>

<div class="app" id="app">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>

  <main class="main" style="padding: 16px 20px;">
    <?php include __DIR__ . '/includes/header.php'; ?>

    <!-- Schedule Notice Alert if Holiday Conflicts -->
    <?php if (!empty($holidayConflicts)): ?>
      <div class="gcal-conflict-banner">
        <div style="display:flex; align-items:center; gap:8px;">
          <span>⚠️</span>
          <span>
            <strong>Schedule Conflict:</strong> You have <?= count($holidayConflicts); ?> date(s) with project deadlines or tasks scheduled during official government days off (<?= htmlspecialchars($holidayConflicts[0]['holiday_name']); ?>).
          </span>
        </div>
        <button type="button" class="btn btn-secondary" onclick="openDayDetailModal(<?= (int)$holidayConflicts[0]['day']; ?>)" style="padding:3px 10px; font-size:11.5px; color:#D93025;">
          Review
        </button>
      </div>
    <?php endif; ?>

    <!-- ======================================================== -->
    <!-- GOOGLE CALENDAR CONTAINER CANVAS                         -->
    <!-- ======================================================== -->
    <div class="gcal-container">

      <!-- TOPBAR: Google Calendar App Bar -->
      <header class="gcal-header">
        <!-- Left: Hamburger, App Logo, Today, Chevrons, Date Heading -->
        <div class="gcal-header-left">
          <button type="button" class="gcal-icon-btn" id="btnToggleGcalSidebar" title="Main menu" onclick="toggleGcalSidebar()">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
          </button>

          <a href="calendar.php" class="gcal-logo" title="Google Calendar style Workspace">
            <div class="gcal-logo-icon">
              <span class="gcal-logo-num"><?= date('j'); ?></span>
            </div>
            <div style="display:flex; align-items:baseline; gap:4px;">
              <span class="gcal-logo-title">Calendar</span>
              <span class="gcal-logo-badge">PRO</span>
            </div>
          </a>

          <!-- "Today" Button -->
          <a href="?month=<?= (int)date('n'); ?>&year=<?= (int)date('Y'); ?>&day=<?= (int)date('j'); ?>&country=<?= $reqCountry; ?>&view=<?= $reqView; ?>" class="gcal-btn-today" title="Jump to Today (t)">
            Today
          </a>

          <!-- Previous / Next Chevrons -->
          <div class="gcal-nav-arrows">
            <a href="?month=<?= $prevMonth; ?>&year=<?= $prevYear; ?>&country=<?= $reqCountry; ?>&view=<?= $reqView; ?><?= $reqProject ? '&project_id=' . $reqProject : ''; ?>" class="gcal-nav-arrow-btn" title="Previous month (p)">
              ‹
            </a>
            <a href="?month=<?= $nextMonth; ?>&year=<?= $nextYear; ?>&country=<?= $reqCountry; ?>&view=<?= $reqView; ?><?= $reqProject ? '&project_id=' . $reqProject : ''; ?>" class="gcal-nav-arrow-btn" title="Next month (n)">
              ›
            </a>
          </div>

          <!-- Month & Year Title -->
          <h2 class="gcal-header-date-title" id="gcalHeaderDateTitle">
            <?= $monthLabel; ?>
          </h2>
        </div>

        <!-- Right: Search, Government Country Selector, View Switcher, Share/Export -->
        <div class="gcal-header-right">
          <!-- Real-Time Search Bar -->
          <div class="gcal-search-box">
            <svg class="gcal-search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" id="gcalSearchInput" class="gcal-search-input" placeholder="Search tasks, events..." oninput="handleGcalSearch(this.value)" />
          </div>

          <!-- Country Selector Dropdown -->
          <select class="gcal-select-pill" id="gcalCountrySelector" onchange="changeCountry(this.value)" title="Government Public Holidays Region">
            <option value="KH" <?= ($reqCountry === 'KH') ? 'selected' : ''; ?>>🇰🇭 Cambodia</option>
            <option value="US" <?= ($reqCountry === 'US') ? 'selected' : ''; ?>>🇺🇸 United States</option>
            <option value="GLOBAL" <?= ($reqCountry === 'GLOBAL') ? 'selected' : ''; ?>>🌐 International</option>
          </select>

          <!-- View Switcher Dropdown (Month / Week / Day / Schedule) -->
          <div class="gcal-view-dropdown">
            <button type="button" class="gcal-view-btn" id="btnGcalViewSelect" onclick="toggleViewDropdown()">
              <span id="currentViewLabel">
                <?= ucfirst($reqView); ?>
              </span>
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
            </button>
            <div class="gcal-dropdown-menu" id="gcalViewMenu">
              <a href="?month=<?= $reqMonth; ?>&year=<?= $reqYear; ?>&country=<?= $reqCountry; ?>&view=month" class="gcal-dropdown-item <?= ($reqView === 'month') ? 'active' : ''; ?>">
                <span>Month</span>
                <span class="gcal-shortcut-kbd">M</span>
              </a>
              <a href="?month=<?= $reqMonth; ?>&year=<?= $reqYear; ?>&country=<?= $reqCountry; ?>&view=week" class="gcal-dropdown-item <?= ($reqView === 'week') ? 'active' : ''; ?>">
                <span>Week</span>
                <span class="gcal-shortcut-kbd">W</span>
              </a>
              <a href="?month=<?= $reqMonth; ?>&year=<?= $reqYear; ?>&country=<?= $reqCountry; ?>&view=day" class="gcal-dropdown-item <?= ($reqView === 'day') ? 'active' : ''; ?>">
                <span>Day</span>
                <span class="gcal-shortcut-kbd">D</span>
              </a>
              <a href="?month=<?= $reqMonth; ?>&year=<?= $reqYear; ?>&country=<?= $reqCountry; ?>&view=schedule" class="gcal-dropdown-item <?= ($reqView === 'schedule') ? 'active' : ''; ?>">
                <span>Schedule</span>
                <span class="gcal-shortcut-kbd">S</span>
              </a>
            </div>
          </div>

          <!-- Share Schedule Action -->
          <button type="button" class="gcal-icon-btn" onclick="openShareScheduleModal()" title="Share Schedule & WebCal Feed">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
          </button>

          <!-- Export iCal (.ics) -->
          <a href="api/calendar_export.php?country=<?= htmlspecialchars($reqCountry); ?>" class="gcal-icon-btn" title="Export to iCalendar (.ics)" style="text-decoration:none;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
          </a>
        </div>
      </header>

      <!-- BODY: Left Sidebar + Calendar Canvas -->
      <div class="gcal-body">

        <!-- ==================================================== -->
        <!-- LEFT SIDEBAR: + Create, Mini Cal, Categories, Projs -->
        <!-- ==================================================== -->
        <aside class="gcal-sidebar" id="gcalSidebar">

          <!-- Google Calendar Iconic "+ Create" Pill Button -->
          <button type="button" class="gcal-btn-create" onclick="openUniversalScheduleModal()">
            <svg class="gcal-btn-create-icon" viewBox="0 0 36 36">
              <path fill="#4285F4" d="M16 16v14h4V16h14v-4H20V2h-4v10H2v4h14z"/>
            </svg>
            <span>Create</span>
          </button>

          <!-- Interactive Mini Datepicker Calendar -->
          <div class="gcal-mini-cal">
            <div class="gcal-mini-header">
              <span class="gcal-mini-title"><?= date('F Y', $monthTs); ?></span>
              <div class="gcal-mini-nav">
                <a href="?month=<?= $prevMonth; ?>&year=<?= $prevYear; ?>&country=<?= $reqCountry; ?>&view=<?= $reqView; ?>" class="gcal-mini-nav-btn" title="Previous Month">‹</a>
                <a href="?month=<?= $nextMonth; ?>&year=<?= $nextYear; ?>&country=<?= $reqCountry; ?>&view=<?= $reqView; ?>" class="gcal-mini-nav-btn" title="Next Month">›</a>
              </div>
            </div>

            <div class="gcal-mini-grid">
              <div class="gcal-mini-day-hdr">S</div>
              <div class="gcal-mini-day-hdr">M</div>
              <div class="gcal-mini-day-hdr">T</div>
              <div class="gcal-mini-day-hdr">W</div>
              <div class="gcal-mini-day-hdr">T</div>
              <div class="gcal-mini-day-hdr">F</div>
              <div class="gcal-mini-day-hdr">S</div>

              <?php
              // Leading padding from prev month
              for ($p = 0; $p < $firstDayOfWeek; $p++):
                $pDay = $prevMonthDays - ($firstDayOfWeek - 1 - $p);
              ?>
                <a href="?month=<?= $prevMonth; ?>&year=<?= $prevYear; ?>&day=<?= $pDay; ?>&country=<?= $reqCountry; ?>&view=<?= $reqView; ?>" class="gcal-mini-cell other-month"><?= $pDay; ?></a>
              <?php endfor; ?>

              <?php 
              $todayNum = (int)date('j');
              $isCurrentMonthAndYear = ($reqMonth === (int)date('n') && $reqYear === (int)date('Y'));
              for ($d = 1; $d <= $daysInMonth; $d++): 
                $isToday = ($isCurrentMonthAndYear && $d === $todayNum);
                $hasItems = !empty($holidaysByDay[$d]) || !empty($tasksByDay[$d]) || !empty($projectEventsByDay[$d]);
              ?>
                <a href="?month=<?= $reqMonth; ?>&year=<?= $reqYear; ?>&day=<?= $d; ?>&country=<?= $reqCountry; ?>&view=day" 
                   class="gcal-mini-cell <?= $isToday ? 'today' : ''; ?> <?= $hasItems ? 'has-events' : ''; ?>"
                   title="<?= $d; ?> <?= date('F', $monthTs); ?>">
                  <?= $d; ?>
                </a>
              <?php endfor; ?>
            </div>
          </div>

          <!-- Section: My Calendars (Toggles) -->
          <div class="gcal-sidebar-section">
            <div class="gcal-section-header">
              <span>My Calendars</span>
            </div>
            <div class="gcal-section-items">
              <!-- Government Holidays -->
              <label class="gcal-check-item">
                <input type="checkbox" id="layerHolidays" checked onchange="toggleCalendarLayer('holiday', this.checked)" style="accent-color: #D97706; cursor:pointer;" />
                <span>🏛️ Public Holidays</span>
              </label>

              <!-- Project Launch / Kickoffs -->
              <label class="gcal-check-item">
                <input type="checkbox" id="layerProjects" checked onchange="toggleCalendarLayer('project', this.checked)" style="accent-color: #D93025; cursor:pointer;" />
                <span>🚀 Project Deadlines</span>
              </label>

              <!-- Milestones -->
              <label class="gcal-check-item">
                <input type="checkbox" id="layerMilestones" checked onchange="toggleCalendarLayer('milestone', this.checked)" style="accent-color: #1A73E8; cursor:pointer;" />
                <span>🎯 Milestones</span>
              </label>

              <!-- Tasks -->
              <label class="gcal-check-item">
                <input type="checkbox" id="layerTasks" checked onchange="toggleCalendarLayer('task', this.checked)" style="accent-color: #6C5CE7; cursor:pointer;" />
                <span>📋 Tasks & Schedules</span>
              </label>
            </div>
          </div>

          <!-- Section: Projects Filter -->
          <div class="gcal-sidebar-section">
            <div class="gcal-section-header">
              <span>Filter Projects (<?= count($projects); ?>)</span>
              <a href="?month=<?= $reqMonth; ?>&year=<?= $reqYear; ?>&country=<?= $reqCountry; ?>&view=<?= $reqView; ?>" style="font-size:11px; text-decoration:none; color:var(--gcal-blue); font-weight:600;">All</a>
            </div>
            <div class="gcal-section-items" style="max-height: 220px; overflow-y: auto;">
              <?php foreach ($projects as $p): 
                $isSelected = ($reqProject === (int)$p['id']);
                $ring = $p['ring_color'] ?? '#6C5CE7';
              ?>
                <a href="?month=<?= $reqMonth; ?>&year=<?= $reqYear; ?>&country=<?= $reqCountry; ?>&view=<?= $reqView; ?>&project_id=<?= $isSelected ? 'all' : $p['id']; ?>" 
                   class="gcal-check-item" 
                   style="<?= $isSelected ? 'background: var(--gcal-hover-bg); font-weight: 700;' : ''; ?> text-decoration:none;">
                  <span class="gcal-custom-checkbox" style="background:<?= htmlspecialchars($ring); ?>; color:#FFF; font-size:10px;">✓</span>
                  <span style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis; flex:1;">
                    [<?= htmlspecialchars($p['code']); ?>] <?= htmlspecialchars($p['name']); ?>
                  </span>
                </a>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- Quick Day Off Link -->
          <div style="margin-top:auto; padding-top:12px; border-top:1px solid var(--gcal-border-subtle);">
            <button type="button" class="btn btn-secondary" onclick="openAddCustomHolidayModal()" style="width:100%; font-size:12px; justify-content:center;">
              + Record Day Off
            </button>
          </div>

        </aside>

        <!-- ==================================================== -->
        <!-- MAIN CANVAS: Month View / Week View / Agenda View    -->
        <!-- ==================================================== -->
        <main class="gcal-canvas" id="gcalCanvas">

          <?php if ($reqView === 'schedule'): ?>
            <!-- ------------------------------------------------ -->
            <!-- VIEW: SCHEDULE / AGENDA LIST VIEW                -->
            <!-- ------------------------------------------------ -->
            <div class="gcal-schedule-view" id="gcalScheduleView">
              <?php 
              $hasAnyEvent = false;
              for ($d = 1; $d <= $daysInMonth; $d++): 
                $dTasks = $tasksByDay[$d] ?? [];
                $dProj = $projectEventsByDay[$d] ?? [];
                $dMiles = $milestonesByDay[$d] ?? [];
                $dHols = $holidaysByDay[$d] ?? [];

                if (empty($dTasks) && empty($dProj) && empty($dMiles) && empty($dHols)) continue;
                $hasAnyEvent = true;

                $curDateTs = mktime(0, 0, 0, $reqMonth, $d, $reqYear);
                $isToday = ($isCurrentMonthAndYear && $d === $todayNum);
              ?>
                <div class="gcal-agenda-day-group">
                  <div class="gcal-agenda-date-badge">
                    <span class="gcal-agenda-day-num <?= $isToday ? 'today' : ''; ?>"><?= $d; ?></span>
                    <span class="gcal-agenda-day-name"><?= date('D • M', $curDateTs); ?></span>
                  </div>

                  <div class="gcal-agenda-events-col">
                    <!-- Holidays -->
                    <?php foreach ($dHols as $h): ?>
                      <div class="gcal-agenda-card" style="border-left-color:#D97706; background:var(--gcal-holiday-chip-bg);" onclick="openDayDetailModal(<?= $d; ?>)">
                        <div>
                          <div style="font-size:11px; font-weight:800; color:#D97706; text-transform:uppercase;">🏛️ Government Public Holiday</div>
                          <div style="font-size:14.5px; font-weight:700; color:var(--gcal-text-primary); margin-top:2px;"><?= htmlspecialchars($h['name']); ?></div>
                          <div style="font-size:12px; color:var(--gcal-text-secondary);"><?= htmlspecialchars($h['description']); ?></div>
                        </div>
                        <span class="gcal-logo-badge" style="background:#FDE68A; color:#92400E;">OFFICIAL DAY OFF</span>
                      </div>
                    <?php endforeach; ?>

                    <!-- Project Deadlines -->
                    <?php foreach ($dProj as $pe): ?>
                      <div class="gcal-agenda-card" style="border-left-color:<?= htmlspecialchars($pe['ring_color']); ?>;" onclick="openDayDetailModal(<?= $d; ?>)">
                        <div>
                          <div style="font-size:11px; font-weight:800; color:#DC2626; text-transform:uppercase;">🚀 <?= htmlspecialchars($pe['title']); ?></div>
                          <div style="font-size:14.5px; font-weight:700; color:var(--gcal-text-primary); margin-top:2px;">[<?= htmlspecialchars($pe['project_code']); ?>] <?= htmlspecialchars($pe['project_name']); ?></div>
                          <div style="font-size:12px; color:var(--gcal-text-muted);">Status: <?= htmlspecialchars($pe['status']); ?> • <?= $pe['progress_pct']; ?>% Complete</div>
                        </div>
                        <span class="gcal-shortcut-kbd" style="font-weight:700; color:#DC2626;"><?= $pe['time_str']; ?></span>
                      </div>
                    <?php endforeach; ?>

                    <!-- Milestones -->
                    <?php foreach ($dMiles as $m): ?>
                      <div class="gcal-agenda-card" style="border-left-color:#1A73E8;" onclick="openDayDetailModal(<?= $d; ?>)">
                        <div>
                          <div style="font-size:11px; font-weight:800; color:#1A73E8; text-transform:uppercase;">🎯 Milestone</div>
                          <div style="font-size:14.5px; font-weight:700; color:var(--gcal-text-primary); margin-top:2px;"><?= htmlspecialchars($m['name']); ?></div>
                          <div style="font-size:12px; color:var(--gcal-text-muted);">Project: <?= htmlspecialchars($m['project_name']); ?></div>
                        </div>
                        <span class="gcal-shortcut-kbd"><?= $m['time_str']; ?></span>
                      </div>
                    <?php endforeach; ?>

                    <!-- Tasks -->
                    <?php foreach ($dTasks as $t): ?>
                      <div class="gcal-agenda-card" style="border-left-color:<?= htmlspecialchars($t['ring_color']); ?>;" onclick="openDayDetailModal(<?= $d; ?>)">
                        <div style="display:flex; align-items:center; gap:12px;">
                          <input type="checkbox" <?= ($t['status'] === 'Done') ? 'checked' : ''; ?> onclick="event.stopPropagation(); toggleTaskDoneStatus(<?= $t['id']; ?>, this)" style="width:18px; height:18px; cursor:pointer;" />
                          <div>
                            <div style="font-size:14px; font-weight:600; color:var(--gcal-text-primary); <?= ($t['status'] === 'Done') ? 'text-decoration:line-through; opacity:0.6;' : ''; ?>">
                              <?= htmlspecialchars($t['task_name']); ?>
                            </div>
                            <div style="font-size:12px; color:var(--gcal-text-muted);">
                              [<?= htmlspecialchars($t['project_name']); ?>] • Priority: <strong><?= htmlspecialchars($t['priority'] ?? 'Medium'); ?></strong>
                            </div>
                          </div>
                        </div>
                        <span class="gcal-shortcut-kbd"><?= $t['time_str']; ?></span>
                      </div>
                    <?php endforeach; ?>

                  </div>
                </div>
              <?php endfor; ?>

              <?php if (!$hasAnyEvent): ?>
                <div style="text-align:center; padding:60px 20px; color:var(--gcal-text-muted);">
                  <div style="font-size:36px; margin-bottom:8px;">📅</div>
                  <div style="font-size:16px; font-weight:600; color:var(--gcal-text-primary);">No events scheduled in <?= $monthLabel; ?></div>
                  <div style="font-size:13px; margin-top:4px;">Click the "+ Create" button to schedule a task or project deadline.</div>
                </div>
              <?php endif; ?>
            </div>

          <?php elseif ($reqView === 'day'): ?>
            <!-- ------------------------------------------------ -->
            <!-- VIEW: SINGLE DAY HOURLY TIMELINE                 -->
            <!-- ------------------------------------------------ -->
            <div style="display:flex; flex-direction:column; flex:1; overflow-y:auto; padding:16px 24px; background:var(--gcal-bg);">
              <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:16px; border-bottom:1px solid var(--gcal-border-subtle); padding-bottom:12px;">
                <div style="display:flex; align-items:center; gap:12px;">
                  <span style="font-size:32px; font-weight:800; color:var(--gcal-blue);"><?= $reqDay; ?></span>
                  <div>
                    <h3 style="margin:0; font-size:18px; font-weight:700; color:var(--gcal-text-primary);"><?= date('l, F j, Y', mktime(0,0,0,$reqMonth,$reqDay,$reqYear)); ?></h3>
                    <span style="font-size:12.5px; color:var(--gcal-text-secondary);">Single Day Focus View</span>
                  </div>
                </div>
                <button type="button" class="btn btn-save" onclick="quickAddEventOnDate('<?= sprintf('%02d-%02d-%04d', $reqDay, $reqMonth, $reqYear); ?>')">
                  + Add Item on this Day
                </button>
              </div>

              <!-- Day Timeline Slots -->
              <div style="display:flex; flex-direction:column; gap:8px;">
                <?php 
                $dayTasks = $tasksByDay[$reqDay] ?? [];
                $dayHols = $holidaysByDay[$reqDay] ?? [];
                $dayProj = $projectEventsByDay[$reqDay] ?? [];
                $dayMiles = $milestonesByDay[$reqDay] ?? [];
                ?>
                <!-- All-Day Section -->
                <?php if (!empty($dayHols) || !empty($dayProj)): ?>
                  <div style="background:var(--gcal-hover-bg); border-radius:6px; padding:10px 14px; margin-bottom:12px;">
                    <span style="font-size:11px; font-weight:800; color:var(--gcal-text-muted); text-transform:uppercase;">All-Day & Milestones</span>
                    <div style="display:flex; flex-direction:column; gap:6px; margin-top:6px;">
                      <?php foreach ($dayHols as $h): ?>
                        <div class="gcal-chip-holiday" style="font-size:12.5px; height:26px;">
                          <span><?= $h['flag'] ?? '🏛️'; ?></span>
                          <span><strong><?= htmlspecialchars($h['name']); ?></strong> (Official Government Day Off)</span>
                        </div>
                      <?php endforeach; ?>
                      <?php foreach ($dayProj as $pe): ?>
                        <div class="gcal-chip-solid" style="background:<?= htmlspecialchars($pe['ring_color']); ?>; font-size:12.5px; height:26px;">
                          <span>🚀</span>
                          <span><strong><?= htmlspecialchars($pe['title']); ?></strong></span>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  </div>
                <?php endif; ?>

                <!-- Hours List (8 AM - 7 PM) -->
                <?php for ($hr = 8; $hr <= 19; $hr++): 
                  $hrStr = ($hr > 12) ? ($hr - 12) . ' PM' : (($hr === 12) ? '12 PM' : $hr . ' AM');
                ?>
                  <div style="display:flex; gap:16px; border-top:1px solid var(--gcal-border-subtle); padding:10px 0; min-height:50px;">
                    <div style="width:65px; font-size:11px; font-weight:700; color:var(--gcal-text-muted); text-align:right;">
                      <?= $hrStr; ?>
                    </div>
                    <div style="flex:1; display:flex; flex-direction:column; gap:6px;" onclick="quickAddEventOnDate('<?= sprintf('%02d-%02d-%04d', $reqDay, $reqMonth, $reqYear); ?>')">
                      <?php if ($hr === 10 && !empty($dayTasks)): ?>
                        <?php foreach ($dayTasks as $t): ?>
                          <div class="gcal-chip-solid" style="background:<?= htmlspecialchars($t['ring_color']); ?>; max-width:400px; height:28px;" onclick="event.stopPropagation(); openDayDetailModal(<?= $reqDay; ?>)">
                            <span>📋</span>
                            <span><?= htmlspecialchars($t['task_name']); ?></span>
                          </div>
                        <?php endforeach; ?>
                      <?php endif; ?>
                    </div>
                  </div>
                <?php endfor; ?>
              </div>
            </div>

          <?php else: ?>
            <!-- ------------------------------------------------ -->
            <!-- VIEW: ICONIC MONTH VIEW (DEFAULT GOOGLE GRID)    -->
            <!-- ------------------------------------------------ -->
            <!-- Weekday Header Row -->
            <div class="gcal-grid-header">
              <div class="gcal-day-col-header">Sun</div>
              <div class="gcal-day-col-header">Mon</div>
              <div class="gcal-day-col-header">Tue</div>
              <div class="gcal-day-col-header">Wed</div>
              <div class="gcal-day-col-header">Thu</div>
              <div class="gcal-day-col-header">Fri</div>
              <div class="gcal-day-col-header">Sat</div>
            </div>

            <!-- Month 7x6 Grid -->
            <div class="gcal-month-grid" id="gcalMonthGrid">
              <?php
              // 1. Leading overflow days from previous month
              for ($p = 0; $p < $firstDayOfWeek; $p++):
                $prevDayNum = $prevMonthDays - ($firstDayOfWeek - 1 - $p);
                $prevDateStr = sprintf('%02d-%02d-%04d', $prevDayNum, $prevMonth, $prevYear);
                $prevHols = $prevMonthHolidays[$prevDayNum] ?? [];
              ?>
                <div class="gcal-day-box is-other-month" 
                     data-date="<?= $prevDateStr; ?>" 
                     onclick="window.location.href='?month=<?= $prevMonth; ?>&year=<?= $prevYear; ?>&day=<?= $prevDayNum; ?>&country=<?= $reqCountry; ?>'">
                  <div class="gcal-day-box-header">
                    <span class="gcal-day-num"><?= $prevDayNum; ?></span>
                  </div>
                  <div class="gcal-events-flow">
                    <?php if (!empty($prevHols)): ?>
                      <div class="gcal-chip-holiday" style="opacity:0.6;">
                        <span>🏛️</span>
                        <span><?= htmlspecialchars($prevHols[0]['name']); ?></span>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endfor; ?>

              <?php
              // 2. Active Month Days (1 to $daysInMonth)
              for ($d = 1; $d <= $daysInMonth; $d++):
                $isToday = ($isCurrentMonthAndYear && $d === $todayNum);
                $dateFormatted = sprintf('%02d-%02d-%04d', $d, $reqMonth, $reqYear);

                $dTasks = $tasksByDay[$d] ?? [];
                $dProj = $projectEventsByDay[$d] ?? [];
                $dMiles = $milestonesByDay[$d] ?? [];
                $dHols = $holidaysByDay[$d] ?? [];

                $hasDayOff = false;
                foreach ($dHols as $h) {
                    if ($h['is_day_off']) { $hasDayOff = true; break; }
                }

                $totalItems = count($dHols) + count($dProj) + count($dMiles) + count($dTasks);
                $maxDisplay = 3;
                $renderedCount = 0;
              ?>
                <div class="gcal-day-box <?= $isToday ? 'is-today' : ''; ?> <?= $hasDayOff ? 'is-holiday-day' : ''; ?>"
                     id="gcalDayCell_<?= $d; ?>"
                     data-day="<?= $d; ?>"
                     data-date="<?= $dateFormatted; ?>"
                     onclick="handleDayCellClick(<?= $d; ?>, '<?= $dateFormatted; ?>', event)">

                  <!-- Cell Header (Day Number + Add Trigger) -->
                  <div class="gcal-day-box-header">
                    <span class="gcal-day-num"><?= $d; ?></span>
                    <button type="button" class="gcal-day-add-icon" title="Add event on this date" onclick="event.stopPropagation(); quickAddEventOnDate('<?= $dateFormatted; ?>')">+</button>
                  </div>

                  <!-- Events Flow (Google Calendar chips) -->
                  <div class="gcal-events-flow">

                    <!-- Government Public Holidays (Amber pill) -->
                    <?php if (!empty($dHols)): ?>
                      <?php foreach ($dHols as $h): 
                        if ($renderedCount >= $maxDisplay) break;
                        $renderedCount++;
                      ?>
                        <div class="gcal-chip-holiday gcal-layer-holiday" 
                             onclick="event.stopPropagation(); openEventDetailModal('holiday', <?= htmlspecialchars(json_encode($h)); ?>, '<?= $dateFormatted; ?>')"
                             title="🏛️ Official Public Holiday: <?= htmlspecialchars($h['name']); ?> (<?= htmlspecialchars($h['description']); ?>)">
                          <span><?= $h['flag'] ?? '🏛️'; ?></span>
                          <span><?= htmlspecialchars($h['name']); ?></span>
                        </div>
                      <?php endforeach; ?>
                    <?php endif; ?>

                    <!-- Project Due Dates / Kickoffs (Solid colored banner) -->
                    <?php if (!empty($dProj)): ?>
                      <?php foreach ($dProj as $pe): 
                        if ($renderedCount >= $maxDisplay) break;
                        $renderedCount++;
                        $isDue = ($pe['type'] === 'project_due');
                      ?>
                        <div class="gcal-chip-solid gcal-layer-project" 
                             style="background:<?= htmlspecialchars($pe['ring_color']); ?>;"
                             onclick="event.stopPropagation(); openEventDetailModal('project', <?= htmlspecialchars(json_encode($pe)); ?>, '<?= $dateFormatted; ?>')"
                             title="<?= htmlspecialchars($pe['title']); ?> • <?= $pe['progress_pct']; ?>% Complete">
                          <span><?= $isDue ? '🚀' : '🏁'; ?></span>
                          <span>[<?= htmlspecialchars($pe['project_code']); ?>] <?= htmlspecialchars($pe['title']); ?></span>
                        </div>
                      <?php endforeach; ?>
                    <?php endif; ?>

                    <!-- Milestones (Sky blue solid chip) -->
                    <?php if (!empty($dMiles)): ?>
                      <?php foreach ($dMiles as $m): 
                        if ($renderedCount >= $maxDisplay) break;
                        $renderedCount++;
                      ?>
                        <div class="gcal-chip-solid gcal-layer-milestone" 
                             style="background:#1A73E8;"
                             onclick="event.stopPropagation(); openEventDetailModal('milestone', <?= htmlspecialchars(json_encode($m)); ?>, '<?= $dateFormatted; ?>')"
                             title="Milestone: <?= htmlspecialchars($m['name']); ?> (<?= htmlspecialchars($m['project_name']); ?>)">
                          <span>🎯</span>
                          <span><?= htmlspecialchars($m['name']); ?></span>
                        </div>
                      <?php endforeach; ?>
                    <?php endif; ?>

                    <!-- Tasks (Timed / Pill chips) -->
                    <?php if (!empty($dTasks)): ?>
                      <?php foreach ($dTasks as $t): 
                        if ($renderedCount >= $maxDisplay) break;
                        $renderedCount++;
                        $isDone = ($t['status'] === 'Done');
                      ?>
                        <div class="gcal-chip-timed gcal-layer-task" 
                             onclick="event.stopPropagation(); openEventDetailModal('task', <?= htmlspecialchars(json_encode($t)); ?>, '<?= $dateFormatted; ?>')"
                             title="<?= htmlspecialchars($t['task_name']); ?> [<?= htmlspecialchars($t['project_name']); ?>]">
                          <span class="gcal-chip-timed-dot" style="background:<?= htmlspecialchars($t['ring_color']); ?>;"></span>
                          <span class="gcal-chip-time-lbl"><?= $t['time_str']; ?></span>
                          <span style="<?= $isDone ? 'text-decoration:line-through; opacity:0.6;' : ''; ?>"><?= htmlspecialchars($t['task_name']); ?></span>
                        </div>
                      <?php endforeach; ?>
                    <?php endif; ?>

                    <!-- "+X more" Pill if overflowing -->
                    <?php if ($totalItems > $maxDisplay): ?>
                      <span class="gcal-more-link" onclick="event.stopPropagation(); openDayDetailModal(<?= $d; ?>)">
                        +<?= $totalItems - $maxDisplay; ?> more
                      </span>
                    <?php endif; ?>

                  </div>
                </div>
              <?php endfor; ?>

              <?php
              // 3. Trailing overflow days from next month to complete 35 or 42 grid cells
              $totalCellsSoFar = $firstDayOfWeek + $daysInMonth;
              $gridTargetCells = ($totalCellsSoFar <= 35) ? 35 : 42;
              $nextDaysNeeded = $gridTargetCells - $totalCellsSoFar;

              for ($n = 1; $n <= $nextDaysNeeded; $n++):
                $nextDateStr = sprintf('%02d-%02d-%04d', $n, $nextMonth, $nextYear);
                $nextHols = $nextMonthHolidays[$n] ?? [];
              ?>
                <div class="gcal-day-box is-other-month" 
                     data-date="<?= $nextDateStr; ?>" 
                     onclick="window.location.href='?month=<?= $nextMonth; ?>&year=<?= $nextYear; ?>&day=<?= $n; ?>&country=<?= $reqCountry; ?>'">
                  <div class="gcal-day-box-header">
                    <span class="gcal-day-num"><?= $n; ?></span>
                  </div>
                  <div class="gcal-events-flow">
                    <?php if (!empty($nextHols)): ?>
                      <div class="gcal-chip-holiday" style="opacity:0.6;">
                        <span>🏛️</span>
                        <span><?= htmlspecialchars($nextHols[0]['name']); ?></span>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endfor; ?>
            </div>
          <?php endif; ?>

        </main>
      </div>
    </div>

  </main>
</div>

<!-- ======================================================== -->
<!-- GOOGLE CALENDAR EVENT POPOVER / PREVIEW CARD             -->
<!-- ======================================================== -->
<div class="gcal-event-popover" id="gcalEventPopover">
  <div class="gcal-popover-header">
    <button type="button" class="gcal-icon-btn" id="btnPopoverEdit" title="Edit Item" style="width:32px; height:32px;">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
    </button>
    <button type="button" class="gcal-icon-btn" onclick="closeEventPopover()" title="Close (Esc)" style="width:32px; height:32px;">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </button>
  </div>
  <div class="gcal-popover-body">
    <div class="gcal-popover-title-row">
      <span class="gcal-popover-color-dot" id="popoverDot"></span>
      <div>
        <div class="gcal-popover-title" id="popoverTitle">Event Title</div>
        <span id="popoverTypeBadge" style="font-size:11px; font-weight:700; text-transform:uppercase; color:var(--gcal-blue);"></span>
      </div>
    </div>

    <!-- Date & Time -->
    <div class="gcal-popover-detail-row">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      <span id="popoverDateTime">Wednesday, Oct 1 • 10:00 AM</span>
    </div>

    <!-- Project Association -->
    <div class="gcal-popover-detail-row" id="popoverProjectRow">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
      <span id="popoverProjectName">Marketing Redesign</span>
    </div>

    <!-- Description / Extra details -->
    <div style="font-size:12.5px; color:var(--gcal-text-secondary); line-height:1.4; border-top:1px solid var(--gcal-border-subtle); padding-top:10px;" id="popoverDescription">
      Description...
    </div>
  </div>
</div>

<!-- ======================================================== -->
<!-- MODAL 1: Day Details & All Events Overlay Modal          -->
<!-- ======================================================== -->
<div class="modal-overlay" id="dayDetailModal">
  <div class="modal-card" style="max-width:560px;">
    <div class="modal-header">
      <div>
        <h3 class="modal-title" id="dayModalDateTitle">Schedule for Day</h3>
        <span id="dayModalSubTitle" style="font-size:12px; color:var(--gcal-text-secondary);"></span>
      </div>
      <button class="modal-close-btn" aria-label="Close modal" onclick="closeDayDetailModal()">&times;</button>
    </div>
    <div class="modal-body" id="dayModalBody" style="max-height:450px; overflow-y:auto; padding-top:14px;">
      <!-- Dynamic list inserted by JS -->
    </div>
    <div class="modal-footer" style="display:flex; justify-content:space-between; align-items:center;">
      <button type="button" class="btn btn-save" id="btnDayAddEvent" onclick="openScheduleForCurrentDay()">+ Schedule on This Day</button>
      <button type="button" class="btn-cancel" onclick="closeDayDetailModal()">Close</button>
    </div>
  </div>
</div>

<!-- ======================================================== -->
<!-- MODAL 2: Universal Schedule Modal (Task / Project / Holiday) -->
<!-- ======================================================== -->
<div class="modal-overlay" id="universalScheduleModal">
  <div class="modal-card" style="max-width:540px;">
    <div class="modal-header">
      <h3 class="modal-title">Schedule Calendar Item</h3>
      <button class="modal-close-btn" aria-label="Close modal" onclick="closeUniversalScheduleModal()">&times;</button>
    </div>
    
    <!-- Modal Navigation Tabs -->
    <div style="display:flex; gap:6px; border-bottom:1px solid var(--gcal-border); padding:0 20px 8px; margin-top:8px;">
      <button type="button" class="side-tab-btn active" id="schedTabTask" onclick="switchSchedTab('task')">📋 Task</button>
      <button type="button" class="side-tab-btn" id="schedTabProject" onclick="switchSchedTab('project')">🚀 Project Deadline</button>
      <button type="button" class="side-tab-btn" id="schedTabMilestone" onclick="switchSchedTab('milestone')">🎯 Milestone</button>
      <button type="button" class="side-tab-btn" id="schedTabHoliday" onclick="switchSchedTab('holiday')">🏖️ Day Off</button>
    </div>

    <!-- Form 1: Task Scheduling -->
    <form id="formSchedTask" onsubmit="handleTaskScheduleSubmit(event)">
      <div class="modal-body" style="padding-top:14px;">
        <div class="form-group">
          <label class="form-label" for="taskInputName">Task Name</label>
          <input type="text" id="taskInputName" class="form-input" placeholder="e.g. Design UI components for release" required />
        </div>
        <div class="form-group">
          <label class="form-label" for="taskSelectProject">Assign to Project</label>
          <select id="taskSelectProject" class="form-input" required>
            <?php foreach ($projects as $p): ?>
              <option value="<?= htmlspecialchars($p['name']); ?>"><?= htmlspecialchars($p['name']); ?> (<?= htmlspecialchars($p['code']); ?>)</option>
            <?php endforeach; ?>
            <option value="General">General / Other</option>
          </select>
        </div>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
          <div class="form-group">
            <label class="form-label" for="taskInputDueDate">Due Date</label>
            <input type="text" id="taskInputDueDate" class="form-input" placeholder="DD-MM-YYYY" value="<?= date('d-m-Y'); ?>" required />
          </div>
          <div class="form-group">
            <label class="form-label" for="taskSelectPriority">Priority</label>
            <select id="taskSelectPriority" class="form-input">
              <option value="Urgent">Urgent</option>
              <option value="High">High</option>
              <option value="Medium" selected>Medium</option>
              <option value="Low">Low</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label" for="taskInputAssignee">Assignee</label>
          <input type="text" id="taskInputAssignee" class="form-input" value="<?= htmlspecialchars($user['name'] ?? 'Me'); ?>" />
        </div>
      </div>
      <div class="modal-footer" style="display:flex; justify-content:flex-end; gap:8px;">
        <button type="button" class="btn-cancel" onclick="closeUniversalScheduleModal()">Cancel</button>
        <button type="submit" class="btn-save" id="btnSubmitTask">Schedule Task</button>
      </div>
    </form>

    <!-- Form 2: Project Deadline & Kickoff Setting -->
    <form id="formSchedProject" style="display:none;" onsubmit="handleProjectDatesSubmit(event)">
      <div class="modal-body" style="padding-top:14px;">
        <div class="form-group">
          <label class="form-label" for="projSelect">Choose Project</label>
          <select id="projSelect" class="form-input" onchange="onProjectSelectChange(this.value)" required>
            <?php foreach ($projects as $p): ?>
              <option value="<?= $p['id']; ?>" data-start="<?= htmlspecialchars($p['start_date'] ?? ''); ?>" data-due="<?= htmlspecialchars($p['due_date'] ?? ''); ?>">
                <?= htmlspecialchars($p['name']); ?> (<?= htmlspecialchars($p['code']); ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
          <div class="form-group">
            <label class="form-label" for="projStartDate">Kickoff / Start Date</label>
            <input type="text" id="projStartDate" class="form-input" placeholder="DD-MM-YYYY" />
          </div>
          <div class="form-group">
            <label class="form-label" for="projDueDate">Target Launch / Due Date</label>
            <input type="text" id="projDueDate" class="form-input" placeholder="DD-MM-YYYY" required />
          </div>
        </div>
        <p style="font-size:12px; color:var(--gcal-text-muted); margin:0;">
          Setting a launch deadline displays a high-priority Project Deadline Banner directly on the calendar grid.
        </p>
      </div>
      <div class="modal-footer" style="display:flex; justify-content:flex-end; gap:8px;">
        <button type="button" class="btn-cancel" onclick="closeUniversalScheduleModal()">Cancel</button>
        <button type="submit" class="btn-save" id="btnSubmitProjDates">Save Project Dates</button>
      </div>
    </form>

    <!-- Form 3: Milestone Creation -->
    <form id="formSchedMilestone" style="display:none;" onsubmit="handleMilestoneSubmit(event)">
      <div class="modal-body" style="padding-top:14px;">
        <div class="form-group">
          <label class="form-label" for="msProjectSelect">Project</label>
          <select id="msProjectSelect" class="form-input" required>
            <?php foreach ($projects as $p): ?>
              <option value="<?= $p['id']; ?>"><?= htmlspecialchars($p['name']); ?> (<?= htmlspecialchars($p['code']); ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label" for="msInputName">Milestone Name</label>
          <input type="text" id="msInputName" class="form-input" placeholder="e.g. Beta Release 1.0" required />
        </div>
        <div class="form-group">
          <label class="form-label" for="msDueDate">Target Date</label>
          <input type="text" id="msDueDate" class="form-input" placeholder="DD-MM-YYYY" value="<?= date('d-m-Y'); ?>" required />
        </div>
      </div>
      <div class="modal-footer" style="display:flex; justify-content:flex-end; gap:8px;">
        <button type="button" class="btn-cancel" onclick="closeUniversalScheduleModal()">Cancel</button>
        <button type="submit" class="btn-save" id="btnSubmitMilestone">Save Milestone</button>
      </div>
    </form>

    <!-- Form 4: Custom Day Off / Holiday -->
    <form id="formSchedHoliday" style="display:none;" onsubmit="handleCustomHolidaySubmit(event)">
      <div class="modal-body" style="padding-top:14px;">
        <div class="form-group">
          <label class="form-label" for="holidayInputName">Day Off / Holiday Title</label>
          <input type="text" id="holidayInputName" class="form-input" placeholder="e.g. Company Retreat or Special Day Off" required />
        </div>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
          <div class="form-group">
            <label class="form-label" for="holidayInputDate">Date</label>
            <input type="text" id="holidayInputDate" class="form-input" placeholder="DD-MM-YYYY" value="<?= date('d-m-Y'); ?>" required />
          </div>
          <div class="form-group">
            <label class="form-label" for="holidayInputType">Type</label>
            <select id="holidayInputType" class="form-input">
              <option value="company">Company Day Off</option>
              <option value="personal">Personal Leave</option>
              <option value="government">Government Compensatory</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label" for="holidayInputNotes">Notes / Description</label>
          <textarea id="holidayInputNotes" class="form-input" rows="2" placeholder="Optional notes..."></textarea>
        </div>
      </div>
      <div class="modal-footer" style="display:flex; justify-content:flex-end; gap:8px;">
        <button type="button" class="btn-cancel" onclick="closeUniversalScheduleModal()">Cancel</button>
        <button type="submit" class="btn-save" id="btnSubmitHoliday">Record Day Off</button>
      </div>
    </form>
  </div>
</div>

<!-- ======================================================== -->
<!-- MODAL 3: Share Schedule Modal (Public Web & Live WebCal) -->
<!-- ======================================================== -->
<div class="modal-overlay" id="shareScheduleModal">
  <div class="modal-card" style="max-width:540px;">
    <div class="modal-header">
      <h3 class="modal-title">Share Your Schedule</h3>
      <button class="modal-close-btn" aria-label="Close modal" onclick="closeShareScheduleModal()">&times;</button>
    </div>
    <div class="modal-body" style="padding-top:14px;">
      <p style="font-size:13px; color:var(--gcal-text-secondary); margin:0 0 16px;">
        Share your upcoming task deadlines, project schedules, and government holidays with team members or sync directly into calendar apps.
      </p>

      <!-- Web Link Box -->
      <div style="background:var(--gcal-hover-bg); border:1px solid var(--gcal-border); border-radius:6px; padding:14px; margin-bottom:14px;">
        <label style="display:block; font-size:12px; font-weight:700; color:var(--gcal-text-primary); margin-bottom:6px;">
          Public Read-Only Web Link
        </label>
        <div style="display:flex; gap:8px;">
          <input type="text" id="shareWebUrlInput" readonly class="form-input" style="font-size:12.5px; background:var(--gcal-surface);" />
          <button type="button" class="btn-save" id="btnCopyWebUrl" onclick="copyShareUrl('shareWebUrlInput', 'btnCopyWebUrl')" style="white-space:nowrap; padding:6px 14px; font-size:12px;">Copy Link</button>
        </div>
        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:8px;">
          <span style="font-size:11.5px; color:var(--gcal-text-muted);">Viewable in browser without logging in.</span>
          <a id="btnPreviewSharePage" href="#" target="_blank" style="font-size:11.5px; font-weight:600; color:var(--gcal-blue); text-decoration:none;">Preview Page &rarr;</a>
        </div>
      </div>

      <!-- Live WebCal Calendar Feed -->
      <div style="background:var(--gcal-hover-bg); border:1px solid var(--gcal-border); border-radius:6px; padding:14px; margin-bottom:14px;">
        <label style="display:block; font-size:12px; font-weight:700; color:var(--gcal-text-primary); margin-bottom:6px;">
          Live Calendar Feed (Google Calendar / Apple Calendar / Outlook)
        </label>
        <div style="display:flex; gap:8px;">
          <input type="text" id="shareFeedUrlInput" readonly class="form-input" style="font-size:12.5px; background:var(--gcal-surface);" />
          <button type="button" class="btn-save" id="btnCopyFeedUrl" onclick="copyShareUrl('shareFeedUrlInput', 'btnCopyFeedUrl')" style="white-space:nowrap; padding:6px 14px; font-size:12px;">Copy Feed</button>
        </div>
        <span style="display:block; font-size:11.5px; color:var(--gcal-text-muted); margin-top:8px;">
          Subscribe to this URL in your Google Calendar app to automatically keep all deadlines, projects, and holidays in sync.
        </span>
      </div>

      <!-- Revocation Box -->
      <div style="display:flex; align-items:center; justify-content:space-between; padding-top:10px; border-top:1px solid var(--gcal-border-subtle);">
        <div>
          <span style="display:block; font-size:12px; font-weight:600; color:var(--gcal-text-primary);">Revoke Share Link</span>
          <span style="font-size:11px; color:var(--gcal-text-muted);">Instantly regenerates secret token and disables previous link.</span>
        </div>
        <button type="button" class="btn btn-secondary" id="btnRegenerateShare" onclick="regenerateShareToken()" style="font-size:11.5px; padding:6px 12px; color:#DC2626; border-color:rgba(220,38,38,0.3);">
          Reset Link
        </button>
      </div>
    </div>
    <div class="modal-footer" style="display:flex; justify-content:flex-end;">
      <button type="button" class="btn-save" onclick="closeShareScheduleModal()">Done</button>
    </div>
  </div>
</div>

<!-- Serialized Page State for Dynamic Interactions -->
<script>
window.__CAL_DATA = {
  month: <?= $reqMonth; ?>,
  year: <?= $reqYear; ?>,
  day: <?= $reqDay; ?>,
  country: '<?= htmlspecialchars($reqCountry); ?>',
  projectId: <?= $reqProject !== null ? $reqProject : 'null'; ?>,
  view: '<?= $reqView; ?>',
  holidays: <?= json_encode($holidaysByDay); ?>,
  projects: <?= json_encode($projects); ?>,
  projectEvents: <?= json_encode($projectEventsByDay); ?>,
  milestones: <?= json_encode($milestonesByDay); ?>,
  tasks: <?= json_encode($tasksByDay); ?>
};
</script>

<script src="assets/js/app.js"></script>
<script>
// Toggle Google Calendar Left Sidebar
window.toggleGcalSidebar = function() {
  const sb = document.getElementById('gcalSidebar');
  if (sb) sb.classList.toggle('collapsed');
};

// View Switcher Dropdown
window.toggleViewDropdown = function() {
  const menu = document.getElementById('gcalViewMenu');
  if (menu) menu.classList.toggle('active');
};

document.addEventListener('click', function(e) {
  const btn = document.getElementById('btnGcalViewSelect');
  const menu = document.getElementById('gcalViewMenu');
  if (menu && btn && !btn.contains(e.target) && !menu.contains(e.target)) {
    menu.classList.remove('active');
  }

  // Close Event Popover if clicked outside
  const popover = document.getElementById('gcalEventPopover');
  if (popover && popover.classList.contains('active')) {
    if (!popover.contains(e.target) && !e.target.closest('.gcal-chip-solid') && !e.target.closest('.gcal-chip-timed') && !e.target.closest('.gcal-chip-holiday')) {
      closeEventPopover();
    }
  }
});

// Real-Time Search in Calendar
window.handleGcalSearch = function(query) {
  const q = query.toLowerCase().trim();
  const chips = document.querySelectorAll('.gcal-chip-solid, .gcal-chip-timed, .gcal-chip-holiday, .gcal-agenda-card');
  chips.forEach(chip => {
    if (!q) {
      chip.style.display = '';
    } else {
      const txt = chip.textContent.toLowerCase();
      chip.style.display = txt.includes(q) ? '' : 'none';
    }
  });
};

// Filter Toggles for "My Calendars" Layers
window.toggleCalendarLayer = function(layerType, isVisible) {
  const elements = document.querySelectorAll('.gcal-layer-' + layerType);
  elements.forEach(el => {
    el.style.display = isVisible ? '' : 'none';
  });
};

// Country & Project URL Navigation
window.changeCountry = function(newCountry) {
  const url = new URL(window.location.href);
  url.searchParams.set('country', newCountry);
  window.location.href = url.toString();
};

// Day Cell Click & Quick Add Event
let currentSelectedDayDate = '';

window.handleDayCellClick = function(dayNum, dateStr, evt) {
  // If clicked directly on the cell (not an event chip)
  if (evt.target.closest('.gcal-chip-solid') || evt.target.closest('.gcal-chip-timed') || evt.target.closest('.gcal-chip-holiday') || evt.target.closest('.gcal-more-link')) {
    return;
  }
  currentSelectedDayDate = dateStr;
  openUniversalScheduleModal(dateStr);
};

// Google Calendar Event Popover
window.openEventDetailModal = function(type, data, dateStr) {
  const popover = document.getElementById('gcalEventPopover');
  if (!popover) return;

  const dot = document.getElementById('popoverDot');
  const title = document.getElementById('popoverTitle');
  const typeBadge = document.getElementById('popoverTypeBadge');
  const dateTime = document.getElementById('popoverDateTime');
  const projRow = document.getElementById('popoverProjectRow');
  const projName = document.getElementById('popoverProjectName');
  const desc = document.getElementById('popoverDescription');

  if (type === 'holiday') {
    dot.style.background = '#D97706';
    title.textContent = data.name;
    typeBadge.textContent = '🏛️ Official Government Day Off';
    typeBadge.style.color = '#D97706';
    dateTime.textContent = dateStr + ' • Full Day';
    projRow.style.display = 'none';
    desc.innerHTML = `<strong>${escapeHtml(data.local_name || '')}</strong><br>${escapeHtml(data.description || 'Public holiday by government law.')}`;
  } else if (type === 'project') {
    dot.style.background = data.ring_color || '#D93025';
    title.textContent = data.title;
    typeBadge.textContent = '🚀 Project Target';
    typeBadge.style.color = '#D93025';
    dateTime.textContent = dateStr + ' • ' + (data.time_str || '11:59 PM');
    projRow.style.display = 'flex';
    projName.textContent = '[' + (data.project_code || 'PRJ') + '] ' + data.project_name;
    desc.innerHTML = `Progress: <strong>${data.progress_pct}%</strong><br>Status: <strong>${data.status}</strong>`;
  } else if (type === 'milestone') {
    dot.style.background = '#1A73E8';
    title.textContent = data.name;
    typeBadge.textContent = '🎯 Project Milestone';
    typeBadge.style.color = '#1A73E8';
    dateTime.textContent = dateStr + ' • ' + (data.time_str || '2:00 PM');
    projRow.style.display = 'flex';
    projName.textContent = data.project_name || 'Project';
    desc.innerHTML = `Status: <strong>${data.status || 'Pending'}</strong>`;
  } else { // task
    dot.style.background = data.ring_color || '#1A73E8';
    title.textContent = data.task_name;
    typeBadge.textContent = '📋 Task (' + (data.priority || 'Medium') + ')';
    typeBadge.style.color = 'var(--gcal-blue)';
    dateTime.textContent = dateStr + ' • ' + (data.time_str || '10:00 AM');
    projRow.style.display = 'flex';
    projName.textContent = data.project_name || 'General';
    desc.innerHTML = `Assigned: <strong>${escapeHtml(data.assigned_to || 'Me')}</strong><br>Status: <strong>${data.status || 'Open'}</strong>`;
  }

  // Position popover near mouse or center
  popover.style.top = '50%';
  popover.style.left = '50%';
  popover.style.transform = 'translate(-50%, -50%)';
  popover.classList.add('active');
};

window.closeEventPopover = function() {
  const popover = document.getElementById('gcalEventPopover');
  if (popover) popover.classList.remove('active');
};

// Day Detail Modal (List of all items on that date)
window.openDayDetailModal = function(dayNum) {
  const data = window.__CAL_DATA;
  const holidays = data.holidays[dayNum] || [];
  const projEvents = data.projectEvents[dayNum] || [];
  const milestones = data.milestones[dayNum] || [];
  const tasks = data.tasks[dayNum] || [];

  const dateObj = new Date(data.year, data.month - 1, dayNum);
  const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
  const formattedTitle = dateObj.toLocaleDateString('en-US', options);
  currentSelectedDayDate = String(dayNum).padStart(2, '0') + '-' + String(data.month).padStart(2, '0') + '-' + data.year;

  document.getElementById('dayModalDateTitle').textContent = formattedTitle;
  document.getElementById('dayModalSubTitle').textContent = `Day ${dayNum} of ${data.month}/${data.year}`;

  const container = document.getElementById('dayModalBody');
  let html = '';

  // 1. Government Holiday Section
  if (holidays.length > 0) {
    html += '<div style="margin-bottom:14px;">';
    html += '<div style="font-size:11px; font-weight:700; color:#D97706; text-transform:uppercase; margin-bottom:6px;">Official Government Day Off</div>';
    holidays.forEach(h => {
      html += `
        <div style="background:var(--gcal-holiday-chip-bg); border-left:3px solid #D97706; border-radius:6px; padding:12px; margin-bottom:8px;">
          <div style="display:flex; align-items:center; gap:8px;">
            <span style="font-size:20px;">${h.flag || '🏛️'}</span>
            <div>
              <div style="font-size:14px; font-weight:800; color:var(--gcal-text-primary);">${escapeHtml(h.name)}</div>
              ${h.local_name ? `<div style="font-size:12px; font-weight:600; color:#D97706;">${escapeHtml(h.local_name)}</div>` : ''}
            </div>
          </div>
          <p style="font-size:12px; color:var(--gcal-text-secondary); margin:8px 0 0; line-height:1.4;">${escapeHtml(h.description)}</p>
        </div>
      `;
    });
    html += '</div>';
  }

  // 2. Project Events Section
  if (projEvents.length > 0) {
    html += '<div style="margin-bottom:14px;">';
    html += '<div style="font-size:11px; font-weight:700; color:var(--gcal-blue); text-transform:uppercase; margin-bottom:6px;">Project Timelines</div>';
    projEvents.forEach(pe => {
      const isDue = pe.type === 'project_due';
      html += `
        <div style="background:var(--gcal-hover-bg); border-left:3px solid ${pe.ring_color}; border-radius:6px; padding:10px 12px; margin-bottom:8px;">
          <div style="display:flex; align-items:center; justify-content:space-between;">
            <span style="font-size:11px; font-weight:800; color:${isDue ? '#DC2626' : '#059669'}; text-transform:uppercase;">
              ${isDue ? '🚀 Project Launch Deadline' : '🏁 Project Kickoff'}
            </span>
            <span style="font-size:11px; font-weight:700; color:var(--gcal-text-primary);">${pe.progress_pct}% Completed</span>
          </div>
          <div style="font-size:14px; font-weight:700; color:var(--gcal-text-primary); margin-top:2px;">[${escapeHtml(pe.project_code)}] ${escapeHtml(pe.project_name)}</div>
        </div>
      `;
    });
    html += '</div>';
  }

  // 3. Milestones Section
  if (milestones.length > 0) {
    html += '<div style="margin-bottom:14px;">';
    html += '<div style="font-size:11px; font-weight:700; color:#1A73E8; text-transform:uppercase; margin-bottom:6px;">Project Milestones</div>';
    milestones.forEach(m => {
      html += `
        <div style="background:var(--gcal-hover-bg); border-left:3px solid #1A73E8; border-radius:6px; padding:10px 12px; margin-bottom:8px;">
          <div style="font-size:13.5px; font-weight:700; color:var(--gcal-text-primary);">${escapeHtml(m.name)}</div>
          <div style="font-size:12px; color:var(--gcal-text-muted); margin-top:2px;">Project: ${escapeHtml(m.project_name)}</div>
        </div>
      `;
    });
    html += '</div>';
  }

  // 4. Tasks Section
  if (tasks.length > 0) {
    html += '<div style="margin-bottom:14px;">';
    html += '<div style="font-size:11px; font-weight:700; color:var(--gcal-text-secondary); text-transform:uppercase; margin-bottom:6px;">Tasks Scheduled (${tasks.length})</div>';
    tasks.forEach(t => {
      const isDone = (t.status === 'Done');
      const prio = (t.priority || 'Medium').toLowerCase();
      const prioColor = (prio === 'urgent') ? '#DC2626' : ((prio === 'high') ? '#EA580C' : 'var(--gcal-blue)');
      html += `
        <div style="background:var(--gcal-hover-bg); border:1px solid var(--gcal-border-subtle); border-radius:6px; padding:10px 12px; margin-bottom:8px; display:flex; align-items:center; justify-content:space-between; gap:10px;">
          <div style="display:flex; align-items:center; gap:10px;">
            <input type="checkbox" ${isDone ? 'checked' : ''} onchange="toggleTaskDoneStatus(${t.id}, this)" style="cursor:pointer; width:16px; height:16px;" />
            <div>
              <div style="font-size:13.5px; font-weight:700; color:var(--gcal-text-primary); ${isDone ? 'text-decoration:line-through; opacity:0.6;' : ''}">${escapeHtml(t.task_name)}</div>
              <div style="font-size:11.5px; color:var(--gcal-text-muted);">[${escapeHtml(t.project_name)}] • Priority: <span style="font-weight:700; color:${prioColor};">${escapeHtml(t.priority || 'Medium')}</span></div>
            </div>
          </div>
          <span style="font-size:11px; font-weight:700; background:var(--gcal-surface); padding:2px 6px; border-radius:4px; border:1px solid var(--gcal-border);">${escapeHtml(t.status || 'Open')}</span>
        </div>
      `;
    });
    html += '</div>';
  }

  if (holidays.length === 0 && projEvents.length === 0 && milestones.length === 0 && tasks.length === 0) {
    html = `
      <div style="text-align:center; padding:32px 10px; color:var(--gcal-text-muted);">
        <div style="font-size:24px; margin-bottom:6px;">📅</div>
        <div style="font-size:13.5px; font-weight:600; color:var(--gcal-text-primary); margin-bottom:4px;">No events scheduled for this day</div>
        <div style="font-size:12px;">Click below to add a task, set a project deadline, or mark a custom day off.</div>
      </div>
    `;
  }

  container.innerHTML = html;
  document.getElementById('dayDetailModal').classList.add('active');
};

window.closeDayDetailModal = function() {
  document.getElementById('dayDetailModal').classList.remove('active');
};

window.quickAddEventOnDate = function(dateFormatted) {
  currentSelectedDayDate = dateFormatted;
  openScheduleForCurrentDay();
};

window.openScheduleForCurrentDay = function() {
  closeDayDetailModal();
  openUniversalScheduleModal(currentSelectedDayDate);
};

// Universal Schedule Modal Tabs
window.openUniversalScheduleModal = function(defaultDate = null) {
  const modal = document.getElementById('universalScheduleModal');
  if (!modal) return;

  const d = defaultDate || new Date().toISOString().slice(0, 10).split('-').reverse().join('-');
  document.getElementById('taskInputDueDate').value = d;
  document.getElementById('projDueDate').value = d;
  document.getElementById('msDueDate').value = d;
  document.getElementById('holidayInputDate').value = d;

  switchSchedTab('task');
  modal.classList.add('active');
  setTimeout(() => document.getElementById('taskInputName')?.focus(), 100);
};

window.closeUniversalScheduleModal = function() {
  document.getElementById('universalScheduleModal').classList.remove('active');
};

window.openAddCustomHolidayModal = function() {
  openUniversalScheduleModal();
  switchSchedTab('holiday');
};

window.switchSchedTab = function(tab) {
  const tabs = ['task', 'project', 'milestone', 'holiday'];
  tabs.forEach(t => {
    const btn = document.getElementById('schedTab' + t.charAt(0).toUpperCase() + t.slice(1));
    const form = document.getElementById('formSched' + t.charAt(0).toUpperCase() + t.slice(1));
    if (btn) btn.classList.toggle('active', t === tab);
    if (form) form.style.display = (t === tab) ? 'block' : 'none';
  });
};

window.onProjectSelectChange = function(projId) {
  const sel = document.getElementById('projSelect');
  const opt = sel.options[sel.selectedIndex];
  if (opt) {
    document.getElementById('projStartDate').value = opt.getAttribute('data-start') || '';
    document.getElementById('projDueDate').value = opt.getAttribute('data-due') || '';
  }
};

// Form Submissions
window.handleTaskScheduleSubmit = async function(e) {
  e.preventDefault();
  const btn = document.getElementById('btnSubmitTask');
  btn.disabled = true;
  btn.textContent = 'Scheduling...';

  const payload = {
    action: 'create',
    task_name: document.getElementById('taskInputName').value.trim(),
    project_name: document.getElementById('taskSelectProject').value,
    priority: document.getElementById('taskSelectPriority').value,
    due_date: document.getElementById('taskInputDueDate').value.trim(),
    start_date: document.getElementById('taskInputDueDate').value.trim(),
    assigned_to: document.getElementById('taskInputAssignee').value.trim() || 'Me',
    status: 'Open'
  };

  try {
    const res = await secureFetch('api/tasks.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const data = await res.json();
    if (data.success) {
      if (typeof showToast === 'function') showToast('Task scheduled on Google Calendar!');
      closeUniversalScheduleModal();
      setTimeout(() => location.reload(), 400);
    } else {
      alert(data.message || 'Failed to schedule task');
    }
  } catch (err) {
    console.error(err);
    alert('Network error while scheduling task.');
  } finally {
    btn.disabled = false;
    btn.textContent = 'Schedule Task';
  }
};

window.handleProjectDatesSubmit = async function(e) {
  e.preventDefault();
  const btn = document.getElementById('btnSubmitProjDates');
  btn.disabled = true;
  btn.textContent = 'Saving...';

  const payload = {
    action: 'update_project_dates',
    project_id: parseInt(document.getElementById('projSelect').value),
    start_date: document.getElementById('projStartDate').value.trim(),
    due_date: document.getElementById('projDueDate').value.trim()
  };

  try {
    const res = await secureFetch('api/calendar_events.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const data = await res.json();
    if (data.success) {
      if (typeof showToast === 'function') showToast('Project deadline updated on calendar!');
      closeUniversalScheduleModal();
      setTimeout(() => location.reload(), 400);
    } else {
      alert(data.message || 'Failed to update project deadline');
    }
  } catch (err) {
    console.error(err);
    alert('Error saving project dates.');
  } finally {
    btn.disabled = false;
    btn.textContent = 'Save Project Dates';
  }
};

window.handleMilestoneSubmit = async function(e) {
  e.preventDefault();
  const btn = document.getElementById('btnSubmitMilestone');
  btn.disabled = true;
  btn.textContent = 'Saving...';

  const payload = {
    action: 'create_milestone',
    project_id: parseInt(document.getElementById('msProjectSelect').value),
    name: document.getElementById('msInputName').value.trim(),
    due_date: document.getElementById('msDueDate').value.trim()
  };

  try {
    const res = await secureFetch('api/calendar_events.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const data = await res.json();
    if (data.success) {
      if (typeof showToast === 'function') showToast('Milestone saved on calendar!');
      closeUniversalScheduleModal();
      setTimeout(() => location.reload(), 400);
    } else {
      alert(data.message || 'Failed to create milestone');
    }
  } catch (err) {
    console.error(err);
    alert('Error saving milestone.');
  } finally {
    btn.disabled = false;
    btn.textContent = 'Save Milestone';
  }
};

window.handleCustomHolidaySubmit = async function(e) {
  e.preventDefault();
  const btn = document.getElementById('btnSubmitHoliday');
  btn.disabled = true;
  btn.textContent = 'Recording...';

  const payload = {
    action: 'create_custom_holiday',
    holiday_name: document.getElementById('holidayInputName').value.trim(),
    holiday_date: document.getElementById('holidayInputDate').value.trim(),
    holiday_type: document.getElementById('holidayInputType').value,
    country_code: window.__CAL_DATA.country,
    is_day_off: 1,
    notes: document.getElementById('holidayInputNotes').value.trim()
  };

  try {
    const res = await secureFetch('api/calendar_events.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const data = await res.json();
    if (data.success) {
      if (typeof showToast === 'function') showToast('Custom day off added to your calendar!');
      closeUniversalScheduleModal();
      setTimeout(() => location.reload(), 400);
    } else {
      alert(data.message || 'Failed to add day off');
    }
  } catch (err) {
    console.error(err);
    alert('Error recording day off.');
  } finally {
    btn.disabled = false;
    btn.textContent = 'Record Day Off';
  }
};

window.toggleTaskDoneStatus = async function(taskId, checkbox) {
  const newStatus = checkbox.checked ? 'Done' : 'Open';
  try {
    const res = await secureFetch('api/tasks.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'update_status', task_id: taskId, status: newStatus })
    });
    const data = await res.json();
    if (data.success) {
      if (typeof showToast === 'function') showToast(`Task marked as ${newStatus}`);
      setTimeout(() => location.reload(), 300);
    }
  } catch (err) {
    console.error(err);
  }
};

// Share Schedule Functions
window.openShareScheduleModal = async function() {
  const modal = document.getElementById('shareScheduleModal');
  if (!modal) return;
  modal.classList.add('active');

  const webInput = document.getElementById('shareWebUrlInput');
  const feedInput = document.getElementById('shareFeedUrlInput');
  const previewLink = document.getElementById('btnPreviewSharePage');

  if (webInput) webInput.value = 'Loading link...';
  if (feedInput) feedInput.value = 'Loading feed...';

  try {
    const res = await secureFetch('api/schedule_share.php');
    const data = await res.json();
    if (data.success) {
      if (webInput) webInput.value = data.share_url;
      if (feedInput) feedInput.value = data.webcal_url;
      if (previewLink) previewLink.href = data.share_url;
    } else {
      if (webInput) webInput.value = 'Failed to load link';
    }
  } catch (err) {
    console.error(err);
    if (webInput) webInput.value = 'Network error';
  }
};

window.closeShareScheduleModal = function() {
  const modal = document.getElementById('shareScheduleModal');
  if (modal) modal.classList.remove('active');
};

window.copyShareUrl = function(inputId, btnId) {
  const input = document.getElementById(inputId);
  const btn = document.getElementById(btnId);
  if (!input) return;
  input.select();
  navigator.clipboard.writeText(input.value).then(() => {
    if (typeof showToast === 'function') showToast('Copied to clipboard!');
    if (btn) {
      const orig = btn.textContent;
      btn.textContent = 'Copied!';
      setTimeout(() => btn.textContent = orig, 1800);
    }
  }).catch(() => {
    document.execCommand('copy');
    if (typeof showToast === 'function') showToast('Copied to clipboard!');
  });
};

window.regenerateShareToken = async function() {
  if (!confirm('Are you sure you want to reset your schedule share link? Anyone using the previous link will lose access.')) {
    return;
  }
  const btn = document.getElementById('btnRegenerateShare');
  if (btn) {
    btn.disabled = true;
    btn.textContent = 'Resetting...';
  }

  try {
    const res = await secureFetch('api/schedule_share.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'regenerate' })
    });
    const data = await res.json();
    if (data.success) {
      const webInput = document.getElementById('shareWebUrlInput');
      const feedInput = document.getElementById('shareFeedUrlInput');
      const previewLink = document.getElementById('btnPreviewSharePage');
      if (webInput) webInput.value = data.share_url;
      if (feedInput) feedInput.value = data.webcal_url;
      if (previewLink) previewLink.href = data.share_url;
      if (typeof showToast === 'function') showToast(data.message);
    } else {
      alert(data.message || 'Failed to reset link');
    }
  } catch (err) {
    console.error(err);
  } finally {
    if (btn) {
      btn.disabled = false;
      btn.textContent = 'Reset Link';
    }
  }
};

// Keyboard Shortcuts (Google Calendar Standard: t=today, p=prev, n=next, m=month, w=week, d=day, s=schedule, c=create)
document.addEventListener('keydown', function(e) {
  // Ignore inside inputs or textareas
  if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT') {
    return;
  }

  if (e.key === 'Escape') {
    closeEventPopover();
    closeDayDetailModal();
    closeUniversalScheduleModal();
    closeShareScheduleModal();
  } else if (e.key === 't' || e.key === 'T') {
    window.location.href = '?month=<?= (int)date('n'); ?>&year=<?= (int)date('Y'); ?>&day=<?= (int)date('j'); ?>&country=<?= $reqCountry; ?>&view=<?= $reqView; ?>';
  } else if (e.key === 'p' || e.key === 'k') {
    window.location.href = '?month=<?= $prevMonth; ?>&year=<?= $prevYear; ?>&country=<?= $reqCountry; ?>&view=<?= $reqView; ?><?= $reqProject ? '&project_id=' . $reqProject : ''; ?>';
  } else if (e.key === 'n' || e.key === 'j') {
    window.location.href = '?month=<?= $nextMonth; ?>&year=<?= $nextYear; ?>&country=<?= $reqCountry; ?>&view=<?= $reqView; ?><?= $reqProject ? '&project_id=' . $reqProject : ''; ?>';
  } else if (e.key === 'm' || e.key === 'M') {
    window.location.href = '?month=<?= $reqMonth; ?>&year=<?= $reqYear; ?>&country=<?= $reqCountry; ?>&view=month';
  } else if (e.key === 'w' || e.key === 'W') {
    window.location.href = '?month=<?= $reqMonth; ?>&year=<?= $reqYear; ?>&country=<?= $reqCountry; ?>&view=week';
  } else if (e.key === 'd' || e.key === 'D') {
    window.location.href = '?month=<?= $reqMonth; ?>&year=<?= $reqYear; ?>&country=<?= $reqCountry; ?>&view=day';
  } else if (e.key === 's' || e.key === 'S') {
    window.location.href = '?month=<?= $reqMonth; ?>&year=<?= $reqYear; ?>&country=<?= $reqCountry; ?>&view=schedule';
  } else if (e.key === 'c' || e.key === 'C') {
    openUniversalScheduleModal();
  } else if (e.key === '/') {
    e.preventDefault();
    document.getElementById('gcalSearchInput')?.focus();
  }
});
</script>
</body>
</html>
