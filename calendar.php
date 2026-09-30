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

// Request parameters: Month, Year, Country, Project Filter
$reqMonth = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
$reqYear = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$reqCountry = isset($_GET['country']) ? strtoupper(trim($_GET['country'])) : 'KH';
$reqProject = isset($_GET['project_id']) && $_GET['project_id'] !== 'all' ? (int)$_GET['project_id'] : null;

if ($reqMonth < 1 || $reqMonth > 12) $reqMonth = (int)date('n');
if ($reqYear < 2000 || $reqYear > 2100) $reqYear = (int)date('Y');
if (!in_array($reqCountry, ['KH', 'US', 'GLOBAL'], true)) $reqCountry = 'KH';

$prevMonth = ($reqMonth === 1) ? 12 : $reqMonth - 1;
$prevYear = ($reqMonth === 1) ? $reqYear - 1 : $reqYear;
$nextMonth = ($reqMonth === 12) ? 1 : $reqMonth + 1;
$nextYear = ($reqMonth === 12) ? $reqYear + 1 : $reqYear;

$monthTs = mktime(0, 0, 0, $reqMonth, 1, $reqYear);
$monthLabel = date('F Y', $monthTs);
$firstDayOfMonth = (int)date('N', $monthTs); // 1 (Mon) to 7 (Sun)
$daysInMonth = (int)date('t', $monthTs);

// 1. Fetch Government Days Off & Custom Holidays for this month
$holidaysByDay = getMonthHolidaysByDay($db, $userId, $reqYear, $reqMonth, $reqCountry);

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
                    'type' => 'project_due',
                    'project_id' => $p['id'],
                    'project_name' => $p['name'],
                    'project_code' => $p['code'],
                    'ring_color' => $p['ring_color'] ?? '#6C5CE7',
                    'progress_pct' => (int)$p['progress_pct'],
                    'status' => $p['status'] ?? 'In Progress',
                    'date_str' => date('d-m-Y', $tsDue),
                    'title' => "Project Deadline: {$p['name']}"
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
                'type' => 'project_start',
                'project_id' => $p['id'],
                'project_name' => $p['name'],
                'project_code' => $p['code'],
                'ring_color' => $p['ring_color'] ?? '#6C5CE7',
                'progress_pct' => (int)$p['progress_pct'],
                'status' => $p['status'] ?? 'In Progress',
                'date_str' => date('d-m-Y', $tsStart),
                'title' => "Project Kickoff: {$p['name']}"
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
            $t['ring_color'] = $matchedProj['ring_color'] ?? '#6C5CE7';
            $t['project_code'] = $matchedProj['code'] ?? 'PRJ';

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
    'KH' => '🇰🇭 Cambodia (Default)',
    'US' => '🇺🇸 United States',
    'GLOBAL' => '🌐 International'
];

$pageTitle = 'Mindrift — Study & Project Calendar';
include __DIR__ . '/includes/head.php';
?>
<style>
  :root {
    --cal-holiday-bg: rgba(245, 158, 11, 0.08);
    --cal-holiday-border: rgba(245, 158, 11, 0.35);
    --cal-holiday-badge-bg: #D97706;
    --cal-holiday-badge-text: #FFFFFF;
    --cal-weekend-bg: rgba(148, 163, 184, 0.04);
  }

  [data-theme="dark"] {
    --cal-holiday-bg: rgba(245, 158, 11, 0.12);
    --cal-holiday-border: rgba(245, 158, 11, 0.4);
    --cal-weekend-bg: rgba(255, 255, 255, 0.015);
  }

  /* Layout and Shell */
  .cal-topbar { margin-bottom: 18px; }
  .cal-title-row { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px; margin-bottom: 16px; }
  .cal-title { margin: 0; font-size: 22px; font-weight: 800; letter-spacing: -0.025em; color: var(--text-primary); }
  .cal-subtitle { margin: 3px 0 0; color: var(--text-secondary); font-size: 13px; font-weight: 400; }
  
  /* Quick Metrics Strip */
  .cal-metrics-strip {
    display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 16px;
  }
  .cal-stat-card {
    background: var(--bg-surface); border: 1px solid var(--border-base); border-radius: var(--radius-sm);
    padding: 12px 14px; display: flex; align-items: center; gap: 12px; transition: transform 0.15s, border-color 0.15s;
  }
  .cal-stat-card:hover { border-color: var(--brand-primary); }
  .cal-stat-icon {
    width: 38px; height: 38px; border-radius: 8px; display: flex; align-items: center; justify-content: center;
    font-size: 18px; flex-shrink: 0;
  }
  .cal-stat-val { font-size: 18px; font-weight: 800; color: var(--text-primary); line-height: 1.1; }
  .cal-stat-lbl { font-size: 11.5px; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.02em; margin-top: 2px; }

  /* Conflict Alert Banner */
  .conflict-alert-banner {
    background: rgba(239, 68, 68, 0.08); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: var(--radius-sm);
    padding: 10px 14px; margin-bottom: 16px; display: flex; align-items: center; justify-content: space-between; gap: 12px;
  }
  .conflict-alert-text { font-size: 12.5px; color: #DC2626; font-weight: 600; }

  /* Filter and Navigation Toolbar */
  .cal-toolbar {
    background: var(--bg-surface); border: 1px solid var(--border-base); border-radius: var(--radius-sm);
    padding: 10px 14px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin-bottom: 14px;
  }
  .cal-nav-group { display: flex; align-items: center; gap: 8px; }
  .btn-nav-cal {
    background: var(--bg-subtle); border: 1px solid var(--border-base); border-radius: var(--radius-xs);
    color: var(--text-primary); padding: 5px 11px; font-size: 13px; font-weight: 700; cursor: pointer; text-decoration: none;
    transition: background 0.15s, border-color 0.15s;
  }
  .btn-nav-cal:hover { background: var(--border-base); border-color: var(--brand-primary); }
  .cal-current-label { font-size: 17px; font-weight: 800; color: var(--text-primary); min-width: 140px; text-align: center; }

  .cal-filters-group { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
  .cal-select {
    background: var(--bg-subtle); border: 1px solid var(--border-base); border-radius: var(--radius-xs);
    color: var(--text-primary); padding: 5px 10px; font-size: 12.5px; font-weight: 600; outline: none; cursor: pointer;
  }
  .cal-select:focus { border-color: var(--brand-primary); }

  /* Project Pill Filters */
  .project-pills-row {
    display: flex; align-items: center; gap: 8px; overflow-x: auto; padding-bottom: 10px; margin-bottom: 14px;
    scrollbar-width: thin;
  }
  .project-pill-btn {
    white-space: nowrap; border-radius: 20px; padding: 4px 12px; font-size: 12px; font-weight: 600;
    background: var(--bg-surface); border: 1px solid var(--border-base); color: var(--text-secondary);
    cursor: pointer; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; transition: all 0.15s;
  }
  .project-pill-btn:hover { border-color: var(--brand-primary); color: var(--brand-primary); }
  .project-pill-btn.active {
    background: var(--brand-primary); color: #FFF; border-color: var(--brand-primary); box-shadow: 0 2px 6px rgba(108,92,231,0.25);
  }
  .project-pill-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }

  /* Main Grid vs Sidebar Split */
  .cal-layout-grid { display: grid; grid-template-columns: 8fr 4fr; gap: 16px; }

  /* Calendar Month Grid */
  .cal-month-card { background: var(--bg-surface); border: 1px solid var(--border-base); border-radius: var(--radius-sm); padding: 16px; }
  .cal-grid-header { display: grid; grid-template-columns: repeat(7, 1fr); gap: 6px; margin-bottom: 8px; }
  .cal-day-name-col { text-align: center; font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; padding: 6px 0; }
  .cal-day-name-col.weekend { color: #DC2626; opacity: 0.8; }

  .calendar-days-container { display: grid; grid-template-columns: repeat(7, 1fr); gap: 6px; }
  .cal-day-cell {
    background: var(--bg-surface); border: 1px solid var(--border-base); border-radius: var(--radius-sm);
    min-height: 98px; padding: 6px 8px; font-size: 12px; display: flex; flex-direction: column;
    position: relative; transition: all 0.15s ease; cursor: pointer;
  }
  .cal-day-cell:hover {
    border-color: var(--brand-primary); transform: translateY(-1px); box-shadow: 0 4px 10px rgba(0,0,0,0.04);
  }
  .cal-day-cell.weekend { background: var(--cal-weekend-bg); }
  .cal-day-cell.today {
    border: 2px solid var(--brand-primary); background: var(--bg-subtle);
  }
  .cal-day-cell.is-holiday {
    background: var(--cal-holiday-bg) !important;
    border-color: var(--cal-holiday-border) !important;
  }

  .cal-cell-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 4px; }
  .cal-num { font-weight: 700; font-size: 12.5px; color: var(--text-primary); }
  .cal-today-badge {
    font-size: 8.5px; font-weight: 800; background: var(--brand-primary); color: #FFF; padding: 1px 4px; border-radius: 3px;
  }
  .cal-cell-add-btn {
    opacity: 0; background: none; border: none; font-size: 14px; font-weight: 700; color: var(--brand-primary);
    cursor: pointer; padding: 0 2px; line-height: 1; transition: opacity 0.15s;
  }
  .cal-day-cell:hover .cal-cell-add-btn { opacity: 1; }

  /* Badges inside day cell */
  .cell-events-list { display: flex; flex-direction: column; gap: 3px; overflow: hidden; flex: 1; }
  
  /* Government Holiday Badge */
  .badge-gov-holiday {
    background: var(--cal-holiday-badge-bg); color: var(--cal-holiday-badge-text);
    font-size: 9.5px; font-weight: 700; padding: 2px 5px; border-radius: 3px;
    display: flex; align-items: center; gap: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    box-shadow: 0 1px 2px rgba(217, 119, 6, 0.2);
  }

  /* Project Badges */
  .badge-proj-deadline {
    background: linear-gradient(135deg, #DC2626, #B91C1C); color: #FFF;
    font-size: 9.5px; font-weight: 700; padding: 2px 5px; border-radius: 3px;
    display: flex; align-items: center; gap: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  }
  .badge-proj-start {
    background: linear-gradient(135deg, #059669, #047857); color: #FFF;
    font-size: 9.5px; font-weight: 700; padding: 2px 5px; border-radius: 3px;
    display: flex; align-items: center; gap: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  }
  .badge-milestone {
    background: #0284C7; color: #FFF;
    font-size: 9.5px; font-weight: 700; padding: 2px 5px; border-radius: 3px;
    display: flex; align-items: center; gap: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  }

  /* Task Pill */
  .badge-task-item {
    font-size: 9.5px; font-weight: 600; padding: 2px 5px; border-radius: 3px; color: #FFF;
    display: flex; align-items: center; gap: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  }
  .badge-task-item.priority-urgent { background: #E11D48; }
  .badge-task-item.priority-high { background: #EA580C; }
  .badge-task-item.priority-medium { background: var(--brand-primary); }
  .badge-task-item.priority-low { background: #64748B; }

  .more-events-tag { font-size: 9px; font-weight: 700; color: var(--text-muted); margin-top: 1px; }

  /* Right Side Panel & Tabs */
  .cal-side-card { background: var(--bg-surface); border: 1px solid var(--border-base); border-radius: var(--radius-sm); padding: 16px; }
  .cal-side-tabs { display: flex; border-bottom: 1px solid var(--border-base); margin-bottom: 14px; gap: 6px; }
  .side-tab-btn {
    background: none; border: none; padding: 6px 12px; font-size: 12.5px; font-weight: 600; color: var(--text-secondary);
    cursor: pointer; position: relative; border-radius: 4px 4px 0 0;
  }
  .side-tab-btn.active { color: var(--brand-primary); font-weight: 700; }
  .side-tab-btn.active::after {
    content: ''; position: absolute; bottom: -1px; left: 0; right: 0; height: 2px; background: var(--brand-primary);
  }

  /* List items in panels */
  .side-item-card {
    padding: 10px 12px; background: var(--bg-subtle); border: 1px solid var(--border-base); border-radius: var(--radius-xs);
    margin-bottom: 10px; transition: border-color 0.15s;
  }
  .side-item-card:hover { border-color: var(--brand-primary); }

  .holiday-flag-pill {
    display: inline-flex; align-items: center; gap: 4px; font-size: 10.5px; font-weight: 700;
    color: #D97706; background: rgba(245, 158, 11, 0.12); padding: 2px 6px; border-radius: 3px;
  }

  /* Responsive Rules */
  @media (max-width: 1080px) {
    .cal-layout-grid { grid-template-columns: 1fr; }
    .cal-metrics-strip { grid-template-columns: repeat(2, 1fr); }
  }
  @media (max-width: 640px) {
    .calendar-days-container { gap: 3px; }
    .cal-day-cell { min-height: 64px; padding: 3px 4px; font-size: 10px; }
    .cal-metrics-strip { grid-template-columns: 1fr; }
    .cal-toolbar { flex-direction: column; align-items: stretch; }
  }
</style>
</head>
<body>

<div class="app" id="app">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>

  <main class="main">
    <?php include __DIR__ . '/includes/header.php'; ?>

    <div class="cal-topbar">
      <!-- Title & Action Buttons -->
      <div class="cal-title-row">
        <div>
          <h2 class="cal-title">Calendar & Project Schedule</h2>
          <p class="cal-subtitle">Track project deadlines, upcoming milestones, tasks, and official government days off.</p>
        </div>
        <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
          <button type="button" class="btn btn-save" onclick="openUniversalScheduleModal()" style="display:inline-flex; align-items:center; gap:6px; font-size:12.5px;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Schedule Event
          </button>
          <button type="button" class="btn btn-secondary" onclick="openShareScheduleModal()" style="display:inline-flex; align-items:center; gap:6px; font-size:12.5px;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
            Share Schedule
          </button>
          <a href="api/calendar_export.php?country=<?= htmlspecialchars($reqCountry); ?>" class="btn btn-secondary" style="display:inline-flex; align-items:center; gap:6px; text-decoration:none; font-size:12.5px;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            Export (.ics)
          </a>
        </div>
      </div>

      <!-- Quick Metrics Strip -->
      <div class="cal-metrics-strip">
        <div class="cal-stat-card">
          <div class="cal-stat-icon" style="background:rgba(245,158,11,0.12); color:#D97706;">🏛️</div>
          <div>
            <div class="cal-stat-val"><?= count($holidaysByDay); ?></div>
            <div class="cal-stat-lbl">Government Days Off</div>
          </div>
        </div>

        <div class="cal-stat-card">
          <div class="cal-stat-icon" style="background:rgba(108,92,231,0.12); color:var(--brand-primary);">🚀</div>
          <div>
            <div class="cal-stat-val"><?= count($projects); ?></div>
            <div class="cal-stat-lbl">Active Projects</div>
          </div>
        </div>

        <div class="cal-stat-card">
          <div class="cal-stat-icon" style="background:rgba(239,68,68,0.1); color:#DC2626;">🎯</div>
          <div>
            <div class="cal-stat-val"><?= count($projectEventsByDay) + count($milestonesByDay); ?></div>
            <div class="cal-stat-lbl">Project Deadlines</div>
          </div>
        </div>

        <div class="cal-stat-card">
          <div class="cal-stat-icon" style="background:rgba(14,165,233,0.1); color:#0284C7;">📋</div>
          <div>
            <div class="cal-stat-val"><?= count($allTasks); ?></div>
            <div class="cal-stat-lbl">Scheduled Tasks</div>
          </div>
        </div>
      </div>

      <!-- Schedule Intelligence Conflict Alert -->
      <?php if (!empty($holidayConflicts)): ?>
        <div class="conflict-alert-banner">
          <div style="display:flex; align-items:center; gap:10px;">
            <span style="font-size:18px;">⚠️</span>
            <div>
              <div class="conflict-alert-text">Schedule Notice: Work Scheduled on Official Government Day Off</div>
              <div style="font-size:11.5px; color:var(--text-secondary); margin-top:2px;">
                You have <?= count($holidayConflicts); ?> date(s) where tasks or project milestones land on an official day off (e.g. 
                <?php 
                  $previewNames = array_map(fn($c) => htmlspecialchars($c['holiday_name']), array_slice($holidayConflicts, 0, 2));
                  echo implode(', ', $previewNames);
                ?>).
              </div>
            </div>
          </div>
          <button type="button" class="btn btn-secondary" onclick="openDayDetailModal(<?= (int)$holidayConflicts[0]['day']; ?>)" style="font-size:11.5px; padding:4px 10px; color:#DC2626; border-color:rgba(220,38,38,0.3);">
            Review Dates
          </button>
        </div>
      <?php endif; ?>

      <!-- Filter and Month Navigation Toolbar -->
      <div class="cal-toolbar">
        <div class="cal-nav-group">
          <a href="?month=<?= $prevMonth; ?>&year=<?= $prevYear; ?>&country=<?= $reqCountry; ?><?= $reqProject ? '&project_id=' . $reqProject : ''; ?>" class="btn-nav-cal" title="Previous Month">‹</a>
          <span class="cal-current-label"><?= $monthLabel; ?></span>
          <a href="?month=<?= $nextMonth; ?>&year=<?= $nextYear; ?>&country=<?= $reqCountry; ?><?= $reqProject ? '&project_id=' . $reqProject : ''; ?>" class="btn-nav-cal" title="Next Month">›</a>
          <a href="?month=<?= (int)date('n'); ?>&year=<?= (int)date('Y'); ?>&country=<?= $reqCountry; ?>" class="btn-nav-cal" style="font-size:11.5px; font-weight:600;">Today</a>
        </div>

        <div class="cal-filters-group">
          <!-- Country Selector for Government Holidays -->
          <div style="display:flex; align-items:center; gap:6px;">
            <label style="font-size:12px; font-weight:600; color:var(--text-secondary);">Government:</label>
            <select class="cal-select" id="countrySelector" onchange="changeCountry(this.value)">
              <option value="KH" <?= ($reqCountry === 'KH') ? 'selected' : ''; ?>>🇰🇭 Cambodia (Prakas)</option>
              <option value="US" <?= ($reqCountry === 'US') ? 'selected' : ''; ?>>🇺🇸 United States</option>
              <option value="GLOBAL" <?= ($reqCountry === 'GLOBAL') ? 'selected' : ''; ?>>🌐 International</option>
            </select>
          </div>

          <!-- Project Filter Dropdown -->
          <div style="display:flex; align-items:center; gap:6px;">
            <label style="font-size:12px; font-weight:600; color:var(--text-secondary);">Project:</label>
            <select class="cal-select" id="projectFilterSelect" onchange="filterByProject(this.value)">
              <option value="all">All Projects (<?= count($projects); ?>)</option>
              <?php foreach ($projects as $p): ?>
                <option value="<?= $p['id']; ?>" <?= ($reqProject === (int)$p['id']) ? 'selected' : ''; ?>>
                  [<?= htmlspecialchars($p['code']); ?>] <?= htmlspecialchars($p['name']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Show/Hide Holidays Toggle -->
          <label style="display:flex; align-items:center; gap:6px; font-size:12px; font-weight:600; color:var(--text-secondary); cursor:pointer;">
            <input type="checkbox" id="chkShowHolidays" checked onchange="toggleHolidayHighlight(this.checked)" />
            <span>Show Days Off</span>
          </label>
        </div>
      </div>

      <!-- Quick Project Horizontal Pill Bar -->
      <div class="project-pills-row">
        <a href="?month=<?= $reqMonth; ?>&year=<?= $reqYear; ?>&country=<?= $reqCountry; ?>" class="project-pill-btn <?= ($reqProject === null) ? 'active' : ''; ?>">
          <span>All Projects</span>
        </a>
        <?php foreach ($projects as $p): 
          $isActive = ($reqProject === (int)$p['id']);
          $ring = $p['ring_color'] ?? '#6C5CE7';
        ?>
          <a href="?month=<?= $reqMonth; ?>&year=<?= $reqYear; ?>&country=<?= $reqCountry; ?>&project_id=<?= $p['id']; ?>" class="project-pill-btn <?= $isActive ? 'active' : ''; ?>">
            <span class="project-pill-dot" style="background:<?= htmlspecialchars($ring); ?>;"></span>
            <span><?= htmlspecialchars($p['name']); ?></span>
            <?php if (!empty($p['due_date'])): ?>
              <span style="font-size:10px; opacity:0.8;">• Due <?= htmlspecialchars($p['due_date']); ?></span>
            <?php endif; ?>
          </a>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Main Layout: Calendar Grid + Side Panel -->
    <div class="cal-layout-grid">

      <!-- Calendar Month View Card -->
      <div class="cal-month-card">
        <!-- Weekday Headers -->
        <div class="cal-grid-header">
          <div class="cal-day-name-col">Mon</div>
          <div class="cal-day-name-col">Tue</div>
          <div class="cal-day-name-col">Wed</div>
          <div class="cal-day-name-col">Thu</div>
          <div class="cal-day-name-col">Fri</div>
          <div class="cal-day-name-col weekend">Sat</div>
          <div class="cal-day-name-col weekend">Sun</div>
        </div>

        <!-- Days Grid -->
        <div class="calendar-days-container" id="calendarDaysContainer">
          <?php 
          // Preceding blank day pads
          for ($pad = 1; $pad < $firstDayOfMonth; $pad++): ?>
            <div class="cal-day-cell" style="background:transparent; border-color:transparent; cursor:default;"></div>
          <?php endfor; ?>

          <?php 
          $todayNum = (int)date('j');
          $isCurrentMonthAndYear = ($reqMonth === (int)date('n') && $reqYear === (int)date('Y'));

          for ($d = 1; $d <= $daysInMonth; $d++): 
            $isToday = ($isCurrentMonthAndYear && $d === $todayNum);
            $dayOfWeek = (int)date('N', mktime(0, 0, 0, $reqMonth, $d, $reqYear));
            $isWeekend = ($dayOfWeek >= 6);
            $hasHolidays = !empty($holidaysByDay[$d]);
            $dateFormatted = sprintf('%02d-%02d-%04d', $d, $reqMonth, $reqYear);

            $dayTasks = $tasksByDay[$d] ?? [];
            $dayProjEvents = $projectEventsByDay[$d] ?? [];
            $dayMilestones = $milestonesByDay[$d] ?? [];
            $totalDayItems = count($dayTasks) + count($dayProjEvents) + count($dayMilestones);
          ?>
            <div class="cal-day-cell <?= $isToday ? 'today' : ''; ?> <?= $isWeekend ? 'weekend' : ''; ?> <?= $hasHolidays ? 'is-holiday' : ''; ?>" 
                 data-day="<?= $d; ?>" 
                 data-date="<?= $dateFormatted; ?>" 
                 onclick="openDayDetailModal(<?= $d; ?>)"
                 title="<?= $hasHolidays ? '🏛️ Official Government Day Off: ' . htmlspecialchars($holidaysByDay[$d][0]['name']) : 'Click to view day schedule & add items'; ?>">
              
              <div class="cal-cell-head">
                <span class="cal-num"><?= $d; ?></span>
                <div style="display:flex; align-items:center; gap:4px;">
                  <?php if ($isToday): ?>
                    <span class="cal-today-badge">TODAY</span>
                  <?php endif; ?>
                  <button type="button" class="cal-cell-add-btn" title="Add event on this date" onclick="event.stopPropagation(); quickAddEventOnDate('<?= $dateFormatted; ?>')">+</button>
                </div>
              </div>

              <div class="cell-events-list">
                <!-- 1. Government Holiday Badges -->
                <?php if ($hasHolidays): ?>
                  <?php foreach ($holidaysByDay[$d] as $h): ?>
                    <div class="badge-gov-holiday" title="<?= htmlspecialchars($h['name']); ?> (<?= htmlspecialchars($h['description']); ?>)">
                      <span><?= $h['flag'] ?? '🏛️'; ?></span>
                      <span><?= htmlspecialchars($h['name']); ?></span>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>

                <!-- 2. Project Deadline / Kickoff Badges -->
                <?php if (!empty($dayProjEvents)): ?>
                  <?php foreach ($dayProjEvents as $pe): ?>
                    <?php if ($pe['type'] === 'project_due'): ?>
                      <div class="badge-proj-deadline" title="Project Launch Deadline: <?= htmlspecialchars($pe['project_name']); ?> (<?= $pe['progress_pct']; ?>% completed)">
                        <span>🚀</span>
                        <span>[<?= htmlspecialchars($pe['project_code']); ?>] <?= htmlspecialchars($pe['project_name']); ?></span>
                      </div>
                    <?php else: ?>
                      <div class="badge-proj-start" title="Project Kickoff: <?= htmlspecialchars($pe['project_name']); ?>">
                        <span>🏁</span>
                        <span>[<?= htmlspecialchars($pe['project_code']); ?>] <?= htmlspecialchars($pe['project_name']); ?></span>
                      </div>
                    <?php endif; ?>
                  <?php endforeach; ?>
                <?php endif; ?>

                <!-- 3. Milestones -->
                <?php if (!empty($dayMilestones)): ?>
                  <?php foreach ($dayMilestones as $m): ?>
                    <div class="badge-milestone" title="Milestone: <?= htmlspecialchars($m['name']); ?> (<?= htmlspecialchars($m['project_name']); ?>)">
                      <span>🎯</span>
                      <span><?= htmlspecialchars($m['name']); ?></span>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>

                <!-- 4. Tasks -->
                <?php if (!empty($dayTasks)): ?>
                  <?php foreach (array_slice($dayTasks, 0, 2) as $t): 
                    $prio = strtolower($t['priority'] ?? 'medium');
                    $prioClass = 'priority-' . $prio;
                  ?>
                    <div class="badge-task-item <?= $prioClass; ?>" title="<?= htmlspecialchars($t['task_name']); ?> [<?= htmlspecialchars($t['project_name']); ?>] (Priority: <?= htmlspecialchars($t['priority'] ?? 'Medium'); ?>)">
                      <span style="font-weight:700;">[<?= htmlspecialchars($t['project_code'] ?? 'T'); ?>]</span>
                      <span><?= htmlspecialchars($t['task_name']); ?></span>
                    </div>
                  <?php endforeach; ?>

                  <?php if (count($dayTasks) > 2): ?>
                    <span class="more-events-tag">+<?= count($dayTasks) - 2; ?> more tasks</span>
                  <?php endif; ?>
                <?php endif; ?>
              </div>

            </div>
          <?php endfor; ?>
        </div>
      </div>

      <!-- Right Side Panel: Tabbed Insights & Management -->
      <div class="cal-side-card">
        <div class="cal-side-tabs">
          <button type="button" class="side-tab-btn active" id="tabBtnHolidays" onclick="switchSideTab('holidays')">
            🏛️ Days Off (<?= count($holidaysByDay); ?>)
          </button>
          <button type="button" class="side-tab-btn" id="tabBtnProjects" onclick="switchSideTab('projects')">
            🚀 Projects (<?= count($projects); ?>)
          </button>
          <button type="button" class="side-tab-btn" id="tabBtnDeadlines" onclick="switchSideTab('deadlines')">
            ⏰ Upcoming
          </button>
        </div>

        <!-- Tab 1: Official Government Public Holidays -->
        <div id="tabContentHolidays">
          <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:12px;">
            <span style="font-size:12.5px; font-weight:700; color:var(--text-primary);">
              <?= htmlspecialchars($countryLabels[$reqCountry] ?? 'Government Holidays'); ?>
            </span>
            <button type="button" class="btn btn-secondary" onclick="openAddCustomHolidayModal()" style="font-size:11px; padding:3px 8px;">
              + Custom Day Off
            </button>
          </div>

          <?php if (empty($holidaysByDay)): ?>
            <div style="text-align:center; padding:30px 10px; color:var(--text-muted); font-size:12.5px;">
              No official government public holidays in <?= $monthLabel; ?>.
            </div>
          <?php else: ?>
            <div style="display:flex; flex-direction:column; gap:8px;">
              <?php foreach ($holidaysByDay as $dNum => $hList): ?>
                <?php foreach ($hList as $h): ?>
                  <div class="side-item-card" style="border-left:3px solid #D97706; cursor:pointer;" onclick="openDayDetailModal(<?= $dNum; ?>)">
                    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:4px;">
                      <span class="holiday-flag-pill">
                        <?= $h['flag'] ?? '🏛️'; ?> <?= date('l, M j', strtotime($h['iso_date'])); ?>
                      </span>
                      <span style="font-size:10.5px; font-weight:700; color:#D97706; text-transform:uppercase;">Official Day Off</span>
                    </div>
                    <div style="font-size:13.5px; font-weight:700; color:var(--text-primary); margin-bottom:2px;">
                      <?= htmlspecialchars($h['name']); ?>
                    </div>
                    <?php if (!empty($h['local_name']) && $h['local_name'] !== $h['name']): ?>
                      <div style="font-size:11.5px; color:#D97706; font-weight:600; margin-bottom:4px;">
                        <?= htmlspecialchars($h['local_name']); ?>
                      </div>
                    <?php endif; ?>
                    <div style="font-size:11.5px; color:var(--text-muted); line-height:1.4;">
                      <?= htmlspecialchars($h['description']); ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <!-- Tab 2: User's Projects & Deadlines -->
        <div id="tabContentProjects" style="display:none;">
          <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:12px;">
            <span style="font-size:12.5px; font-weight:700; color:var(--text-primary);">Tracked Projects (<?= count($projects); ?>)</span>
            <button type="button" class="btn btn-secondary" onclick="openCreateProjectDeadlineModal()" style="font-size:11px; padding:3px 8px;">
              + Set Deadline
            </button>
          </div>

          <?php if (empty($projects)): ?>
            <div style="text-align:center; padding:30px 10px; color:var(--text-muted); font-size:12.5px;">
              No projects created yet.
            </div>
          <?php else: ?>
            <div style="display:flex; flex-direction:column; gap:8px;">
              <?php foreach ($projects as $p): 
                $prog = (int)$p['progress_pct'];
                $ring = $p['ring_color'] ?? '#6C5CE7';
              ?>
                <div class="side-item-card" style="border-left:3px solid <?= htmlspecialchars($ring); ?>;">
                  <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:4px;">
                    <div style="display:flex; align-items:center; gap:6px;">
                      <span style="font-size:10px; font-weight:800; background:<?= htmlspecialchars($ring); ?>; color:#FFF; padding:1px 5px; border-radius:3px;">
                        <?= htmlspecialchars($p['code']); ?>
                      </span>
                      <span style="font-size:13px; font-weight:700; color:var(--text-primary);"><?= htmlspecialchars($p['name']); ?></span>
                    </div>
                    <span style="font-size:11px; font-weight:700; color:var(--text-primary);"><?= $prog; ?>%</span>
                  </div>

                  <!-- Progress Bar -->
                  <div style="width:100%; height:4px; background:var(--border-base); border-radius:2px; overflow:hidden; margin:6px 0;">
                    <div style="height:100%; width:<?= $prog; ?>%; background:<?= htmlspecialchars($ring); ?>; border-radius:2px;"></div>
                  </div>

                  <div style="display:flex; align-items:center; justify-content:space-between; font-size:11.5px; color:var(--text-muted); margin-top:4px;">
                    <span>Launch / Due: <?= !empty($p['due_date']) ? htmlspecialchars($p['due_date']) : 'Not set'; ?></span>
                    <button type="button" onclick="openEditProjectDatesModal(<?= htmlspecialchars(json_encode($p)); ?>)" style="background:none; border:none; color:var(--brand-primary); font-weight:600; cursor:pointer; padding:0;">Edit</button>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <!-- Tab 3: Upcoming Deadlines Timeline -->
        <div id="tabContentDeadlines" style="display:none;">
          <div style="margin-bottom:12px; font-size:12.5px; font-weight:700; color:var(--text-primary);">
            Upcoming Deadlines & Schedules
          </div>

          <?php if (empty($upcomingDeadlines)): ?>
            <div style="text-align:center; padding:30px 10px; color:var(--text-muted); font-size:12.5px;">
              No pending upcoming deadlines.
            </div>
          <?php else: ?>
            <div style="display:flex; flex-direction:column; gap:8px;">
              <?php foreach (array_slice($upcomingDeadlines, 0, 8) as $ud): 
                $itemType = $ud['item_type'];
              ?>
                <?php if ($itemType === 'project_deadline'): 
                  $p = $ud['project'];
                ?>
                  <div class="side-item-card" style="border-left:3px solid #DC2626;">
                    <div style="display:flex; align-items:center; justify-content:space-between;">
                      <span style="font-size:10.5px; font-weight:800; color:#DC2626; text-transform:uppercase;">
                        🚀 Project Launch • <?= $ud['formatted_date']; ?>
                      </span>
                      <span style="font-size:11px; font-weight:700; color:var(--text-primary);"><?= (int)$p['progress_pct']; ?>%</span>
                    </div>
                    <div style="font-size:13.5px; font-weight:700; color:var(--text-primary); margin-top:2px;">
                      <?= htmlspecialchars($p['name']); ?>
                    </div>
                    <div style="font-size:11.5px; color:var(--text-muted);">Category: <?= htmlspecialchars($p['category']); ?></div>
                  </div>

                <?php elseif ($itemType === 'milestone'): 
                  $m = $ud['milestone'];
                ?>
                  <div class="side-item-card" style="border-left:3px solid #0284C7;">
                    <span style="font-size:10.5px; font-weight:800; color:#0284C7; text-transform:uppercase;">
                      🎯 Milestone • <?= $ud['formatted_date']; ?>
                    </span>
                    <div style="font-size:13.5px; font-weight:700; color:var(--text-primary); margin-top:2px;">
                      <?= htmlspecialchars($m['name']); ?>
                    </div>
                    <div style="font-size:11.5px; color:var(--text-muted);">Project: <?= htmlspecialchars($m['project_name']); ?></div>
                  </div>

                <?php else: 
                  $t = $ud['task'];
                  $prio = strtolower($t['priority'] ?? 'medium');
                  $prioColor = ($prio === 'urgent') ? '#DC2626' : (($prio === 'high') ? '#EA580C' : 'var(--brand-primary)');
                ?>
                  <div class="side-item-card" style="border-left:3px solid <?= $prioColor; ?>;">
                    <span style="font-size:10.5px; font-weight:800; color:<?= $prioColor; ?>; text-transform:uppercase;">
                      <?= $ud['formatted_date']; ?> • <?= htmlspecialchars($t['priority'] ?? 'Medium'); ?>
                    </span>
                    <div style="font-size:13.5px; font-weight:700; color:var(--text-primary); margin-top:2px;">
                      <?= htmlspecialchars($t['task_name']); ?>
                    </div>
                    <div style="font-size:11.5px; color:var(--text-muted);">
                      Project: <?= htmlspecialchars($t['project_name']); ?> • Assigned: <?= htmlspecialchars($t['assigned_to'] ?? 'Me'); ?>
                    </div>
                  </div>
                <?php endif; ?>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

      </div>
    </div>

  </main>
</div>

<!-- ======================================================== -->
<!-- MODAL 1: Day Details & Quick Actions Modal                -->
<!-- ======================================================== -->
<div class="modal-overlay" id="dayDetailModal">
  <div class="modal-card" style="max-width:580px;">
    <div class="modal-header">
      <div>
        <h3 class="modal-title" id="dayModalDateTitle">Schedule for Day</h3>
        <span id="dayModalSubTitle" style="font-size:12px; color:var(--text-secondary);"></span>
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
    <div style="display:flex; gap:6px; border-bottom:1px solid var(--border-base); padding:0 20px 8px; margin-top:8px;">
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
        <p style="font-size:12px; color:var(--text-muted); margin:0;">
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
      <p style="font-size:13px; color:var(--text-secondary); margin:0 0 16px;">
        Share your upcoming task deadlines, project schedules, and government holidays with team members or sync directly into calendar apps.
      </p>

      <!-- Web Link Box -->
      <div style="background:var(--bg-subtle); border:1px solid var(--border-base); border-radius:var(--radius-sm); padding:14px; margin-bottom:14px;">
        <label style="display:block; font-size:12px; font-weight:700; color:var(--text-primary); margin-bottom:6px;">
          Public Read-Only Web Link
        </label>
        <div style="display:flex; gap:8px;">
          <input type="text" id="shareWebUrlInput" readonly class="form-input" style="font-size:12.5px; background:var(--bg-surface);" />
          <button type="button" class="btn-save" id="btnCopyWebUrl" onclick="copyShareUrl('shareWebUrlInput', 'btnCopyWebUrl')" style="white-space:nowrap; padding:6px 14px; font-size:12px;">Copy Link</button>
        </div>
        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:8px;">
          <span style="font-size:11.5px; color:var(--text-muted);">Viewable in browser without logging in.</span>
          <a id="btnPreviewSharePage" href="#" target="_blank" style="font-size:11.5px; font-weight:600; color:var(--brand-primary); text-decoration:none;">Preview Page &rarr;</a>
        </div>
      </div>

      <!-- Live WebCal Calendar Feed -->
      <div style="background:var(--bg-subtle); border:1px solid var(--border-base); border-radius:var(--radius-sm); padding:14px; margin-bottom:14px;">
        <label style="display:block; font-size:12px; font-weight:700; color:var(--text-primary); margin-bottom:6px;">
          Live Calendar Feed (Google Calendar / Apple Calendar / Outlook)
        </label>
        <div style="display:flex; gap:8px;">
          <input type="text" id="shareFeedUrlInput" readonly class="form-input" style="font-size:12.5px; background:var(--bg-surface);" />
          <button type="button" class="btn-save" id="btnCopyFeedUrl" onclick="copyShareUrl('shareFeedUrlInput', 'btnCopyFeedUrl')" style="white-space:nowrap; padding:6px 14px; font-size:12px;">Copy Feed</button>
        </div>
        <span style="display:block; font-size:11.5px; color:var(--text-muted); margin-top:8px;">
          Subscribe to this URL in your phone or calendar client to automatically keep all deadlines, projects, and holidays in sync.
        </span>
      </div>

      <!-- Revocation Box -->
      <div style="display:flex; align-items:center; justify-content:space-between; padding-top:10px; border-top:1px solid var(--border-base);">
        <div>
          <span style="display:block; font-size:12px; font-weight:600; color:var(--text-primary);">Revoke Share Link</span>
          <span style="font-size:11px; color:var(--text-muted);">Instantly regenerates secret token and disables previous link.</span>
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
  country: '<?= htmlspecialchars($reqCountry); ?>',
  projectId: <?= $reqProject !== null ? $reqProject : 'null'; ?>,
  holidays: <?= json_encode($holidaysByDay); ?>,
  projects: <?= json_encode($projects); ?>,
  projectEvents: <?= json_encode($projectEventsByDay); ?>,
  milestones: <?= json_encode($milestonesByDay); ?>,
  tasks: <?= json_encode($tasksByDay); ?>
};
</script>

<script src="assets/js/app.js"></script>
<script>
// Tab Switching in Side Panel
window.switchSideTab = function(tabName) {
  document.getElementById('tabContentHolidays').style.display = (tabName === 'holidays') ? 'block' : 'none';
  document.getElementById('tabContentProjects').style.display = (tabName === 'projects') ? 'block' : 'none';
  document.getElementById('tabContentDeadlines').style.display = (tabName === 'deadlines') ? 'block' : 'none';

  document.getElementById('tabBtnHolidays').classList.toggle('active', tabName === 'holidays');
  document.getElementById('tabBtnProjects').classList.toggle('active', tabName === 'projects');
  document.getElementById('tabBtnDeadlines').classList.toggle('active', tabName === 'deadlines');
};

// Country & Project URL Navigation
window.changeCountry = function(newCountry) {
  const url = new URL(window.location.href);
  url.searchParams.set('country', newCountry);
  window.location.href = url.toString();
};

window.filterByProject = function(projId) {
  const url = new URL(window.location.href);
  if (projId === 'all') {
    url.searchParams.delete('project_id');
  } else {
    url.searchParams.set('project_id', projId);
  }
  window.location.href = url.toString();
};

window.toggleHolidayHighlight = function(show) {
  const cells = document.querySelectorAll('.cal-day-cell.is-holiday');
  cells.forEach(c => {
    if (show) {
      c.style.removeProperty('background');
      c.style.removeProperty('border-color');
    } else {
      c.style.background = 'var(--bg-surface)';
      c.style.borderColor = 'var(--border-base)';
    }
  });
};

// Day Detail Modal
let currentSelectedDayDate = '';

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
        <div style="background:rgba(245,158,11,0.1); border:1px solid rgba(245,158,11,0.3); border-radius:6px; padding:12px; margin-bottom:8px;">
          <div style="display:flex; align-items:center; gap:8px;">
            <span style="font-size:20px;">${h.flag || '🏛️'}</span>
            <div>
              <div style="font-size:14px; font-weight:800; color:var(--text-primary);">${escapeHtml(h.name)}</div>
              ${h.local_name ? `<div style="font-size:12px; font-weight:600; color:#D97706;">${escapeHtml(h.local_name)}</div>` : ''}
            </div>
          </div>
          <p style="font-size:12px; color:var(--text-secondary); margin:8px 0 0; line-height:1.4;">${escapeHtml(h.description)}</p>
        </div>
      `;
    });
    html += '</div>';
  }

  // 2. Project Events Section
  if (projEvents.length > 0) {
    html += '<div style="margin-bottom:14px;">';
    html += '<div style="font-size:11px; font-weight:700; color:var(--brand-primary); text-transform:uppercase; margin-bottom:6px;">Project Timelines</div>';
    projEvents.forEach(pe => {
      const isDue = pe.type === 'project_due';
      html += `
        <div style="background:var(--bg-subtle); border-left:3px solid ${pe.ring_color}; border-radius:6px; padding:10px 12px; margin-bottom:8px; border-top:1px solid var(--border-base); border-right:1px solid var(--border-base); border-bottom:1px solid var(--border-base);">
          <div style="display:flex; align-items:center; justify-content:space-between;">
            <span style="font-size:11px; font-weight:800; color:${isDue ? '#DC2626' : '#059669'}; text-transform:uppercase;">
              ${isDue ? '🚀 Project Launch Deadline' : '🏁 Project Kickoff'}
            </span>
            <span style="font-size:11px; font-weight:700; color:var(--text-primary);">${pe.progress_pct}% Completed</span>
          </div>
          <div style="font-size:14px; font-weight:700; color:var(--text-primary); margin-top:2px;">[${escapeHtml(pe.project_code)}] ${escapeHtml(pe.project_name)}</div>
        </div>
      `;
    });
    html += '</div>';
  }

  // 3. Milestones Section
  if (milestones.length > 0) {
    html += '<div style="margin-bottom:14px;">';
    html += '<div style="font-size:11px; font-weight:700; color:#0284C7; text-transform:uppercase; margin-bottom:6px;">Project Milestones</div>';
    milestones.forEach(m => {
      html += `
        <div style="background:var(--bg-subtle); border-left:3px solid #0284C7; border-radius:6px; padding:10px 12px; margin-bottom:8px; border-top:1px solid var(--border-base); border-right:1px solid var(--border-base); border-bottom:1px solid var(--border-base);">
          <div style="font-size:13.5px; font-weight:700; color:var(--text-primary);">${escapeHtml(m.name)}</div>
          <div style="font-size:12px; color:var(--text-muted); margin-top:2px;">Project: ${escapeHtml(m.project_name)}</div>
        </div>
      `;
    });
    html += '</div>';
  }

  // 4. Tasks Section
  if (tasks.length > 0) {
    html += '<div style="margin-bottom:14px;">';
    html += '<div style="font-size:11px; font-weight:700; color:var(--text-secondary); text-transform:uppercase; margin-bottom:6px;">Tasks Scheduled (${tasks.length})</div>';
    tasks.forEach(t => {
      const isDone = (t.status === 'Done');
      const prio = (t.priority || 'Medium').toLowerCase();
      const prioColor = (prio === 'urgent') ? '#DC2626' : ((prio === 'high') ? '#EA580C' : 'var(--brand-primary)');
      html += `
        <div style="background:var(--bg-subtle); border:1px solid var(--border-base); border-radius:6px; padding:10px 12px; margin-bottom:8px; display:flex; align-items:center; justify-content:space-between; gap:10px;">
          <div style="display:flex; align-items:center; gap:10px;">
            <input type="checkbox" ${isDone ? 'checked' : ''} onchange="toggleTaskDoneStatus(${t.id}, this)" style="cursor:pointer; width:16px; height:16px;" />
            <div>
              <div style="font-size:13.5px; font-weight:700; color:var(--text-primary); ${isDone ? 'text-decoration:line-through; opacity:0.6;' : ''}">${escapeHtml(t.task_name)}</div>
              <div style="font-size:11.5px; color:var(--text-muted);">[${escapeHtml(t.project_name)}] • Priority: <span style="font-weight:700; color:${prioColor};">${escapeHtml(t.priority || 'Medium')}</span></div>
            </div>
          </div>
          <span style="font-size:11px; font-weight:700; background:var(--bg-surface); padding:2px 6px; border-radius:4px; border:1px solid var(--border-base);">${escapeHtml(t.status || 'Open')}</span>
        </div>
      `;
    });
    html += '</div>';
  }

  if (holidays.length === 0 && projEvents.length === 0 && milestones.length === 0 && tasks.length === 0) {
    html = `
      <div style="text-align:center; padding:32px 10px; color:var(--text-muted);">
        <div style="font-size:24px; margin-bottom:6px;">📅</div>
        <div style="font-size:13.5px; font-weight:600; color:var(--text-primary); margin-bottom:4px;">No events scheduled for this day</div>
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

window.openCreateProjectDeadlineModal = function() {
  openUniversalScheduleModal();
  switchSchedTab('project');
};

window.openEditProjectDatesModal = function(p) {
  openUniversalScheduleModal();
  switchSchedTab('project');
  const sel = document.getElementById('projSelect');
  if (sel) {
    sel.value = p.id;
    document.getElementById('projStartDate').value = p.start_date || '';
    document.getElementById('projDueDate').value = p.due_date || '';
  }
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
      if (typeof showToast === 'function') showToast('Task scheduled successfully!');
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
      if (typeof showToast === 'function') showToast('Project milestone added!');
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
</script>
</body>
</html>
