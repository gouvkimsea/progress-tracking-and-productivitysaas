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

// Request parameters
$reqCountry = isset($_GET['country']) ? strtoupper(trim($_GET['country'])) : 'KH';
$reqProject = isset($_GET['project_id']) && $_GET['project_id'] !== 'all' ? (int)$_GET['project_id'] : null;

if (!in_array($reqCountry, ['KH', 'US', 'GLOBAL'], true)) $reqCountry = 'KH';

// Fetch User's Projects
$stmtProjects = $db->prepare("SELECT id, code, name, category, level, total_modules, completed_modules, progress_pct, 
                                     bg_gradient, ring_color, is_starred, status, start_date, due_date 
                              FROM courses 
                              WHERE user_id = :uid 
                              ORDER BY is_starred DESC, id ASC");
$stmtProjects->execute(['uid' => $userId]);
$projects = $stmtProjects->fetchAll(PDO::FETCH_ASSOC);

$projectsById = [];
$projectsByName = [];
foreach ($projects as $p) {
    $projectsById[$p['id']] = $p;
    $projectsByName[strtolower(trim($p['name']))] = $p;
}

// Fetch Tasks for user (filtered by project if specified)
$taskSql = "SELECT id, user_id, task_name, project_name, start_date, due_date, priority, assigned_to, status, time_log, created_at 
            FROM tasks 
            WHERE user_id = :uid AND (deleted_at IS NULL)";
$taskParams = ['uid' => $userId];

if ($reqProject !== null && isset($projectsById[$reqProject])) {
    $taskSql .= " AND project_name = :pname";
    $taskParams['pname'] = $projectsById[$reqProject]['name'];
}
$taskSql .= " ORDER BY id ASC";
$stmtTasks = $db->prepare($taskSql);
$stmtTasks->execute($taskParams);
$rawTasks = $stmtTasks->fetchAll(PDO::FETCH_ASSOC);

// Attach parent project attributes to tasks
$tasks = [];
foreach ($rawTasks as $t) {
    $pKey = strtolower(trim($t['project_name'] ?? ''));
    $matchedP = $projectsByName[$pKey] ?? null;
    $t['project_code'] = $matchedP['code'] ?? 'PRJ';
    $t['ring_color'] = $matchedP['ring_color'] ?? '#6C5CE7';
    $tasks[] = $t;
}

// Fetch Milestones for user's projects
$milestones = [];
if (!empty($projects)) {
    $pIds = array_keys($projectsById);
    $inClause = implode(',', array_map('intval', $pIds));
    $stmtMilestones = $db->query("SELECT m.id, m.project_id, m.name, m.due_date, m.status, 
                                         c.name as project_name, c.code as project_code, c.ring_color 
                                  FROM milestones m 
                                  JOIN courses c ON m.project_id = c.id 
                                  WHERE m.project_id IN ($inClause) 
                                  ORDER BY m.id ASC");
    $milestones = $stmtMilestones ? $stmtMilestones->fetchAll(PDO::FETCH_ASSOC) : [];
}

// Dynamic Timeline Range (Starts 3 days ago and runs 35 days)
$timelineStartTs = strtotime('today -3 days');
$totalTimelineDays = 35;
$todayTs = strtotime('today');
$todayDayIndex = max(0, round(($todayTs - $timelineStartTs) / 86400));
$todayLeftPx = $todayDayIndex * 44;

// Calculate Government Public Holidays across timeline range
$timelineEndTs = $timelineStartTs + ($totalTimelineDays * 86400);
$startYear = (int)date('Y', $timelineStartTs);
$endYear = (int)date('Y', $timelineEndTs);
$yearsToCheck = array_unique([$startYear, $endYear]);

$allHolidays = [];
foreach ($yearsToCheck as $y) {
    $gov = getOfficialGovernmentHolidays($y, $reqCountry);
    foreach ($gov as $dStr => $h) {
        $allHolidays[$dStr] = $h;
    }
}

// Pre-index timeline days with holiday and weekend data
$timelineDaysData = [];
$timelineHolidaysCount = 0;

for ($i = 0; $i < $totalTimelineDays; $i++) {
    $dayTs = $timelineStartTs + ($i * 86400);
    $isoDate = date('Y-m-d', $dayTs);
    $isHoliday = isset($allHolidays[$isoDate]);
    $dayOfWeek = (int)date('N', $dayTs);
    $isWeekend = ($dayOfWeek >= 6);

    if ($isHoliday) $timelineHolidaysCount++;

    $timelineDaysData[$i] = [
        'day_index' => $i,
        'timestamp' => $dayTs,
        'iso_date' => $isoDate,
        'day_num' => date('j', $dayTs),
        'month_name' => date('M', $dayTs),
        'day_name' => date('D', $dayTs),
        'is_today' => ($isoDate === date('Y-m-d', $todayTs)),
        'is_weekend' => $isWeekend,
        'is_holiday' => $isHoliday,
        'holiday' => $allHolidays[$isoDate] ?? null
    ];
}

$pageTitle = 'Mindrift — Gantt Timeline & Schedule';
include __DIR__ . '/includes/head.php';
?>
<style>
  .gantt-page-header { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin-bottom: 16px; }
  .gantt-title-wrap { display: flex; align-items: center; gap: 10px; }
  .gantt-title { margin: 0; font-size: 20px; font-weight: 700; letter-spacing: -0.02em; color: var(--text-primary); }
  .gantt-subtitle { margin: 3px 0 0; color: var(--text-secondary); font-size: 13px; font-weight: 400; }

  /* Controls Bar */
  .gantt-controls-bar {
    display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin-bottom: 14px; background: var(--bg-surface);
    padding: 10px 14px; border-radius: var(--radius-sm); border: 1px solid var(--border-base);
  }
  .zoom-btn-group { display: flex; align-items: center; gap: 4px; }
  .zoom-btn {
    padding: 5px 10px; font-size: 12px; font-weight: 600; color: var(--text-muted); background: var(--bg-surface); border: 1px solid var(--border-base);
    border-radius: var(--radius-xs); cursor: pointer; transition: all 0.15s ease;
  }
  .zoom-btn.active { background: var(--brand-primary); color: #FFF; border-color: var(--brand-primary); font-weight: 700; }

  /* Project Pills Row */
  .gantt-pills-row {
    display: flex; align-items: center; gap: 8px; overflow-x: auto; padding-bottom: 8px; margin-bottom: 14px;
    scrollbar-width: thin;
  }
  .gantt-pill-btn {
    white-space: nowrap; border-radius: 20px; padding: 4px 12px; font-size: 12px; font-weight: 600;
    background: var(--bg-surface); border: 1px solid var(--border-base); color: var(--text-secondary);
    cursor: pointer; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; transition: all 0.15s;
  }
  .gantt-pill-btn:hover { border-color: var(--brand-primary); color: var(--brand-primary); }
  .gantt-pill-btn.active {
    background: var(--brand-primary); color: #FFF; border-color: var(--brand-primary); box-shadow: 0 2px 6px rgba(108,92,231,0.25);
  }
  .gantt-pill-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }

  /* Gantt Holiday Columns */
  .gantt-date-cell.is-holiday {
    background: rgba(245, 158, 11, 0.12) !important;
    border-bottom: 2px solid #D97706;
    color: #D97706 !important;
    font-weight: 700;
  }
  .gantt-date-cell.weekend { background: rgba(148, 163, 184, 0.05); }

  .gantt-holiday-stripe {
    position: absolute; top: 0; bottom: 0; width: 44px;
    background: rgba(245, 158, 11, 0.06);
    border-left: 1px dashed rgba(245, 158, 11, 0.3);
    border-right: 1px dashed rgba(245, 158, 11, 0.3);
    pointer-events: none; z-index: 1;
  }

  .holiday-flag-icon {
    font-size: 11px; margin-left: 2px; vertical-align: middle;
  }

  /* Task Bar Interactions */
  .gantt-task-bar.dragging {
    cursor: grabbing; opacity: 0.9; z-index: 20; filter: brightness(1.1);
  }
  .gantt-resize-handle {
    position: absolute; right: 0; top: 0; bottom: 0; width: 8px; cursor: ew-resize;
    background: rgba(255,255,255,0.3); border-radius: 0 4px 4px 0; transition: background 0.15s ease;
  }
  .gantt-resize-handle:hover {
    background: rgba(255,255,255,0.6);
  }
  .today-badge-tag {
    position: absolute; top: 2px; left: -16px; background: var(--status-urgent-text); color: #FFF; font-size: 9px; font-weight: 700; padding: 1px 4px; border-radius: 3px;
  }

  /* Critical Task Highlight */
  .critical-task {
    box-shadow: 0 0 0 2px #F59E0B, 0 4px 8px rgba(245, 158, 11, 0.35) !important;
  }

  /* Project Badge Chip inside task sidebar */
  .proj-code-badge {
    font-size: 9px; font-weight: 800; color: #FFF; padding: 1px 5px; border-radius: 3px; margin-right: 6px; flex-shrink: 0;
  }
</style>
</head>
<body>

<div class="app" id="app">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>

  <main class="main">
    <?php include __DIR__ . '/includes/header.php'; ?>

    <div class="gantt-page-header">
      <div>
        <div class="gantt-title-wrap">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--brand-primary)" stroke-width="2.2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M7 8h6"/><path d="M10 12h8"/><path d="M7 16h5"/></svg>
          <h2 class="gantt-title">Gantt Timeline</h2>
        </div>
        <p class="gantt-subtitle">Drag bars to reschedule, resize durations, inspect task dependencies, and track official government days off.</p>
      </div>
      <div style="display:flex; align-items:center; gap:8px;">
        <select class="form-control" style="width:auto; height:34px; font-size:12px; border-radius:6px; padding:0 10px; cursor:pointer;" onchange="window.location.href='?country='+this.value+'<?= $reqProject ? '&project_id=' . $reqProject : ''; ?>'" title="Filter Government Days Off by Region">
          <option value="KH" <?= ($reqCountry === 'KH') ? 'selected' : ''; ?>>🇰🇭 Cambodia</option>
          <option value="US" <?= ($reqCountry === 'US') ? 'selected' : ''; ?>>🇺🇸 United States</option>
          <option value="GLOBAL" <?= ($reqCountry === 'GLOBAL') ? 'selected' : ''; ?>>🌐 International</option>
        </select>
        <a href="calendar.php?country=<?= htmlspecialchars($reqCountry); ?><?= $reqProject ? '&project_id=' . $reqProject : ''; ?>" class="btn btn-secondary" style="display:inline-flex; align-items:center; gap:6px; font-size:12.5px; text-decoration:none;" title="Open Google Calendar View (Press M)">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
          Google Calendar
        </a>
        <a href="assignments.php" class="btn btn-save" style="display:inline-flex; align-items:center; gap:6px; font-size:12.5px; text-decoration:none;">
          + New Task
        </a>
      </div>
    </div>

    <!-- Project Filter Pills Row -->
    <div class="gantt-pills-row">
      <a href="?country=<?= htmlspecialchars($reqCountry); ?>" class="gantt-pill-btn <?= ($reqProject === null) ? 'active' : ''; ?>">
        <span>All Projects (<?= count($projects); ?>)</span>
      </a>
      <?php foreach ($projects as $p): 
        $isActive = ($reqProject === (int)$p['id']);
        $ring = $p['ring_color'] ?? '#6C5CE7';
      ?>
        <a href="?project_id=<?= $p['id']; ?>&country=<?= htmlspecialchars($reqCountry); ?>" class="gantt-pill-btn <?= $isActive ? 'active' : ''; ?>">
          <span class="gantt-pill-dot" style="background:<?= htmlspecialchars($ring); ?>;"></span>
          <span><?= htmlspecialchars($p['name']); ?></span>
          <?php if (!empty($p['due_date'])): ?>
            <span style="font-size:10px; opacity:0.8;">• Due <?= htmlspecialchars($p['due_date']); ?></span>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>

    <!-- Controls Bar -->
    <div class="gantt-controls-bar">
      <!-- Zoom Controls -->
      <div style="display:flex; align-items:center; gap:8px;">
        <span style="font-size:12px; font-weight:700; color:var(--text-secondary);">Zoom:</span>
        <div class="zoom-btn-group">
          <button type="button" class="zoom-btn active">Days</button>
          <button type="button" class="zoom-btn">Weeks</button>
          <button type="button" class="zoom-btn">Months</button>
        </div>
      </div>

      <!-- Government Holidays Selector -->
      <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
        <div style="display:flex; align-items:center; gap:6px;">
          <label style="font-size:12px; font-weight:700; color:var(--text-secondary);">Holidays:</label>
          <select class="cal-select" onchange="changeGanttCountry(this.value)" style="padding:4px 8px; font-size:12px; border-radius:var(--radius-xs);">
            <option value="KH" <?= ($reqCountry === 'KH') ? 'selected' : ''; ?>>🇰🇭 Cambodia</option>
            <option value="US" <?= ($reqCountry === 'US') ? 'selected' : ''; ?>>🇺🇸 United States</option>
            <option value="GLOBAL" <?= ($reqCountry === 'GLOBAL') ? 'selected' : ''; ?>>🌐 International</option>
          </select>
        </div>

        <!-- Toggle Holiday Highlight Stripes -->
        <label style="display:flex; align-items:center; gap:6px; font-size:12px; font-weight:600; color:var(--text-secondary); cursor:pointer;">
          <input type="checkbox" id="chkShowGanttHolidays" checked onchange="toggleGanttHolidayStripes(this.checked)" />
          <span>Shade Days Off</span>
        </label>
      </div>

      <!-- Critical Path & Auto Scheduling -->
      <div style="display:flex; align-items:center; gap:12px;">
        <button type="button" class="btn-step" id="btnToggleCriticalPath" onclick="toggleCriticalPath()" style="display:inline-flex; align-items:center; gap:6px; font-weight:700;">
          Highlight Critical Path
        </button>
        <div style="font-size:12.5px; font-weight:600; color:var(--text-primary); cursor:pointer; user-select:none;" onclick="toggleAutoSchedule()" title="Click to toggle auto-scheduling">
          <span>Auto Scheduling: <b id="autoScheduleBadge" style="color:var(--status-done-text);">ON</b></span>
        </div>
      </div>
    </div>

    <!-- Gantt Chart Timeline View -->
    <div class="gantt-container-wrap">
      <!-- Left Task List Sidebar -->
      <div class="gantt-sidebar-col">
        <div class="gantt-sidebar-head" style="display:flex; align-items:center; justify-content:space-between;">
          <span>Tasks (<?= count($tasks); ?>)</span>
          <span style="font-size:10.5px; color:var(--text-muted); font-weight:500;">Project / Priority</span>
        </div>
        <?php if (empty($tasks)): ?>
          <div style="padding:24px 16px; font-size:12px; color:var(--text-muted); font-style:italic;">No tasks scheduled</div>
        <?php else: ?>
          <?php foreach ($tasks as $t): 
            $ring = $t['ring_color'] ?? '#6C5CE7';
            $code = $t['project_code'] ?? 'PRJ';
          ?>
            <div class="gantt-task-row" title="<?= htmlspecialchars($t['task_name']); ?> • Project: <?= htmlspecialchars($t['project_name']); ?>">
              <span class="proj-code-badge" style="background:<?= htmlspecialchars($ring); ?>;"><?= htmlspecialchars($code); ?></span>
              <span style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?= htmlspecialchars($t['task_name']); ?></span>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <!-- Right Timeline Grid -->
      <div class="gantt-timeline-col" id="ganttTimelineCol" style="position:relative;">
        <!-- SVG Dependency Arrow Layer -->
        <svg class="gantt-svg-overlay" id="ganttSvgOverlay">
          <defs>
            <marker id="arrowhead" markerWidth="6" markerHeight="6" refX="5" refY="3" orient="auto">
              <polygon points="0 0, 6 3, 0 6" fill="#94A3B8" id="arrowMarkerPoly" />
            </marker>
            <marker id="arrowhead-crit" markerWidth="7" markerHeight="7" refX="6" refY="3.5" orient="auto">
              <polygon points="0 0, 7 3.5, 0 7" fill="#F59E0B" />
            </marker>
          </defs>
        </svg>

        <!-- Red Today Indicator Line -->
        <div class="today-indicator-line" data-today-idx="<?= $todayDayIndex; ?>" style="left: <?= $todayLeftPx; ?>px;">
          <span class="today-badge-tag">TODAY</span>
        </div>

        <!-- Vertical Holiday Shading Stripes -->
        <div id="ganttHolidayStripesContainer">
          <?php foreach ($timelineDaysData as $td): ?>
            <?php if ($td['is_holiday']): 
              $hLeft = $td['day_index'] * 44;
            ?>
              <div class="gantt-holiday-stripe" 
                   data-day-idx="<?= $td['day_index']; ?>" 
                   style="left: <?= $hLeft; ?>px;" 
                   title="🏛️ Official Government Day Off: <?= htmlspecialchars($td['holiday']['name']); ?>">
              </div>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>

        <!-- Dates Header Row -->
        <div class="gantt-dates-head">
          <?php foreach ($timelineDaysData as $td): 
            $isToday = $td['is_today'];
            $isHoliday = $td['is_holiday'];
            $isWeekend = $td['is_weekend'];
          ?>
            <div class="gantt-date-cell <?= $isToday ? 'today' : ''; ?> <?= $isWeekend ? 'weekend' : ''; ?> <?= $isHoliday ? 'is-holiday' : ''; ?>" 
                 title="<?= $isHoliday ? '🏛️ Official Government Day Off: ' . htmlspecialchars($td['holiday']['name']) : date('l, M j, Y', $td['timestamp']); ?>">
              <span><?= $td['day_num']; ?></span>
              <?php if ($isHoliday): ?>
                <span class="holiday-flag-icon"><?= $td['holiday']['flag'] ?? '🏛️'; ?></span>
              <?php endif; ?>
              <br/>
              <span style="font-size:9.5px; font-weight:600;"><?= $td['month_name']; ?></span>
            </div>
          <?php endforeach; ?>
        </div>

        <!-- Task Bars Rows -->
        <?php if (empty($tasks)): ?>
          <div style="padding:40px 20px; text-align:center; font-size:13px; color:var(--text-muted);">
            No tasks scheduled on timeline. Create tasks in <a href="assignments.php" style="color:var(--brand-primary); font-weight:600;">Tasks</a> or <a href="kanban.php" style="color:var(--brand-primary); font-weight:600;">Kanban</a> to visualize your project schedule.
          </div>
        <?php else: ?>
          <?php 
          foreach ($tasks as $idx => $t): 
            $sRaw = $t['start_date'] ?? null;
            $dRaw = $t['due_date'] ?? null;

            if (!empty($sRaw)) {
                $taskStartTs = strtotime(str_replace('/', '-', $sRaw));
                if (!$taskStartTs) $taskStartTs = $todayTs + ($idx * 86400);
            } else {
                $taskStartTs = $todayTs + ($idx * 86400);
            }

            if (!empty($dRaw)) {
                $taskDueTs = strtotime(str_replace('/', '-', $dRaw));
                if (!$taskDueTs || $taskDueTs <= $taskStartTs) $taskDueTs = $taskStartTs + (3 * 86400);
            } else {
                $taskDueTs = $taskStartTs + (3 * 86400);
            }

            $dayOffset = max(0, round(($taskStartTs - $timelineStartTs) / 86400));
            $daySpan = max(1, round(($taskDueTs - $taskStartTs) / 86400));

            $leftPx = $dayOffset * 44;
            $widthPx = $daySpan * 44;
            $barColor = $t['ring_color'] ?? '#6C5CE7';
          ?>
            <div class="gantt-bar-row">
              <div class="gantt-task-bar" 
                   data-task-id="<?= $t['id']; ?>" 
                   data-day-offset="<?= $dayOffset; ?>" 
                   data-duration-days="<?= $daySpan; ?>" 
                   style="left: <?= $leftPx; ?>px; width: <?= $widthPx; ?>px; background: <?= htmlspecialchars($barColor); ?>;" 
                   title="<?= htmlspecialchars($t['task_name']); ?> • Project: <?= htmlspecialchars($t['project_name']); ?> (Drag bar to reschedule • Drag edge to adjust duration)">
                <span style="pointer-events:none; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                  [<?= htmlspecialchars($t['project_code']); ?>] <?= htmlspecialchars($t['task_name']); ?>
                </span>
                <div class="gantt-resize-handle" title="Drag to adjust duration"></div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

  </main>
</div>

<script src="assets/js/app.js"></script>
<script>
window.changeGanttCountry = function(newCountry) {
  const url = new URL(window.location.href);
  url.searchParams.set('country', newCountry);
  window.location.href = url.toString();
};

window.toggleGanttHolidayStripes = function(show) {
  const container = document.getElementById('ganttHolidayStripesContainer');
  if (container) {
    container.style.display = show ? 'block' : 'none';
  }
};

document.addEventListener('DOMContentLoaded', () => {
  const bars = document.querySelectorAll('.gantt-task-bar');
  let dayCellWidth = 44;
  const timelineStartTs = <?= $timelineStartTs; ?>;
  let activeAction = null; // 'drag' or 'resize'
  let currentBar = null;
  let startX = 0;
  let startLeft = 0;
  let startWidth = 0;
  let isCriticalPathActive = false;
  let loadedDependencies = [];

  const svgOverlay = document.getElementById('ganttSvgOverlay');

  // Load Dependencies from backend
  async function loadDependencies() {
    try {
      const url = new URL('api/gantt.php', window.location.href);
      const res = await secureFetch(url.toString());
      const data = await res.json();
      if (data.success && data.dependencies) {
        loadedDependencies = data.dependencies;
        renderDependencyArrows();
      }
    } catch (err) {
      console.error('Failed to load dependencies:', err);
    }
  }

  // Draw smooth SVG bezier arrows between task bars
  function renderDependencyArrows() {
    if (!svgOverlay) return;

    const oldPaths = svgOverlay.querySelectorAll('.gantt-dep-arrow');
    oldPaths.forEach(p => p.remove());

    const timelineRect = svgOverlay.parentElement.getBoundingClientRect();

    loadedDependencies.forEach((dep) => {
      const predBar = document.querySelector(`.gantt-task-bar[data-task-id="${dep.depends_on_task_id}"]`);
      const succBar = document.querySelector(`.gantt-task-bar[data-task-id="${dep.task_id}"]`);

      if (!predBar || !succBar) return;

      const pRect = predBar.getBoundingClientRect();
      const sRect = succBar.getBoundingClientRect();

      const x1 = (pRect.right - timelineRect.left);
      const y1 = (pRect.top - timelineRect.top) + (pRect.height / 2);
      const x2 = (sRect.left - timelineRect.left);
      const y2 = (sRect.top - timelineRect.top) + (sRect.height / 2);

      const dx = Math.max(20, Math.abs(x2 - x1) / 2);
      const pathData = `M ${x1} ${y1} C ${x1 + dx} ${y1}, ${x2 - dx} ${y2}, ${x2} ${y2}`;

      const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
      path.setAttribute('d', pathData);
      path.setAttribute('class', `gantt-dep-arrow ${isCriticalPathActive ? 'critical' : ''}`);
      path.setAttribute('marker-end', isCriticalPathActive ? 'url(#arrowhead-crit)' : 'url(#arrowhead)');
      svgOverlay.appendChild(path);

      if (isCriticalPathActive) {
        predBar.classList.add('critical-task');
        succBar.classList.add('critical-task');
      } else {
        predBar.classList.remove('critical-task');
        succBar.classList.remove('critical-task');
      }
    });
  }

  // Toggle Critical Path Mode
  window.toggleCriticalPath = function() {
    isCriticalPathActive = !isCriticalPathActive;
    const btn = document.getElementById('btnToggleCriticalPath');
    if (btn) {
      if (isCriticalPathActive) {
        btn.style.background = 'var(--status-high-bg)';
        btn.style.color = 'var(--status-high-text)';
        btn.style.borderColor = 'var(--status-high-border)';
        if (typeof showToast === 'function') showToast('Critical path highlighted');
      } else {
        btn.style.background = '';
        btn.style.color = '';
        btn.style.borderColor = '';
      }
    }
    renderDependencyArrows();
  };

  bars.forEach(bar => {
    bar.addEventListener('mousedown', (e) => {
      if (e.target.classList.contains('gantt-resize-handle')) {
        activeAction = 'resize';
      } else {
        activeAction = 'drag';
        bar.classList.add('dragging');
      }
      currentBar = bar;
      startX = e.clientX;
      startLeft = parseInt(bar.style.left, 10) || 0;
      startWidth = parseInt(bar.style.width, 10) || 100;
      e.preventDefault();
    });
  });

  document.addEventListener('mousemove', (e) => {
    if (!activeAction || !currentBar) return;
    const dx = e.clientX - startX;

    if (activeAction === 'drag') {
      const newLeft = Math.max(0, startLeft + dx);
      const snappedLeft = Math.round(newLeft / dayCellWidth) * dayCellWidth;
      currentBar.style.left = `${snappedLeft}px`;

      const curId = currentBar.dataset.taskId;
      const curRight = snappedLeft + (parseInt(currentBar.style.width, 10) || 0);
      loadedDependencies.forEach(dep => {
        if (String(dep.depends_on_task_id) === String(curId)) {
          const succBar = document.querySelector(`.gantt-task-bar[data-task-id="${dep.task_id}"]`);
          if (succBar) {
            const succLeft = parseInt(succBar.style.left, 10) || 0;
            if (curRight > succLeft) {
              succBar.style.left = `${curRight + dayCellWidth}px`;
            }
          }
        }
      });

    } else if (activeAction === 'resize') {
      const newWidth = Math.max(dayCellWidth, startWidth + dx);
      const snappedWidth = Math.round(newWidth / dayCellWidth) * dayCellWidth;
      currentBar.style.width = `${snappedWidth}px`;
    }

    renderDependencyArrows();
  });

  document.addEventListener('mouseup', async () => {
    if (!activeAction || !currentBar) return;
    const bar = currentBar;
    bar.classList.remove('dragging');
    const taskId = bar.dataset.taskId;
    const finalLeft = parseInt(bar.style.left, 10);
    const finalWidth = parseInt(bar.style.width, 10);
    const dayOffset = Math.round(finalLeft / dayCellWidth);
    const durationDays = Math.max(1, Math.round(finalWidth / dayCellWidth));

    activeAction = null;
    currentBar = null;

    const startDate = new Date((timelineStartTs + (dayOffset * 86400)) * 1000);
    const dueDate = new Date(startDate.getTime() + (durationDays * 86400 * 1000));

    const pad = (n) => String(n).padStart(2, '0');
    const fmtStart = `${pad(startDate.getDate())}-${pad(startDate.getMonth() + 1)}-${startDate.getFullYear()}`;
    const fmtDue = `${pad(dueDate.getDate())}-${pad(dueDate.getMonth() + 1)}-${dueDate.getFullYear()}`;

    renderDependencyArrows();

    try {
      const res = await secureFetch('api/gantt.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'update_dates',
          task_id: taskId,
          start_date: fmtStart,
          due_date: fmtDue
        })
      });
      const data = await res.json();
      if (data.success && typeof showToast === 'function') {
        showToast(`Rescheduled to ${fmtStart} (${durationDays} days)`);
      }
    } catch (err) {
      console.error(err);
    }
  });

  // Zoom Level Controls
  document.querySelectorAll('.zoom-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.zoom-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      const zoomMode = btn.textContent.trim();
      if (zoomMode === 'Days') dayCellWidth = 44;
      else if (zoomMode === 'Weeks') dayCellWidth = 28;
      else if (zoomMode === 'Months') dayCellWidth = 18;

      document.querySelectorAll('.gantt-date-cell').forEach(cell => {
        cell.style.minWidth = dayCellWidth + 'px';
        cell.style.width = dayCellWidth + 'px';
      });

      // Recalibrate holiday stripes
      document.querySelectorAll('.gantt-holiday-stripe').forEach(stripe => {
        const dIdx = parseInt(stripe.dataset.dayIdx || 0, 10);
        stripe.style.left = (dIdx * dayCellWidth) + 'px';
        stripe.style.width = dayCellWidth + 'px';
      });

      // Recalibrate task bars
      document.querySelectorAll('.gantt-task-bar').forEach(bar => {
        const dOffset = parseInt(bar.dataset.dayOffset || 0, 10);
        const dLen = parseInt(bar.dataset.durationDays || 2, 10);
        bar.style.left = (dOffset * dayCellWidth) + 'px';
        bar.style.width = Math.max(dayCellWidth, dLen * dayCellWidth) + 'px';
      });

      // Recalibrate today indicator
      const todayLine = document.querySelector('.today-indicator-line');
      if (todayLine) {
        const todayIdx = parseInt(todayLine.dataset.todayIdx || 3, 10);
        todayLine.style.left = (todayIdx * dayCellWidth + (dayCellWidth / 2)) + 'px';
      }

      renderDependencyArrows();
      if (typeof showToast === 'function') showToast(`Zoom: ${zoomMode} view`);
    });
  });

  // Auto-Scheduling Toggle
  let autoScheduleEnabled = true;
  window.toggleAutoSchedule = function() {
    autoScheduleEnabled = !autoScheduleEnabled;
    const badge = document.getElementById('autoScheduleBadge');
    if (badge) {
      badge.textContent = autoScheduleEnabled ? 'ON' : 'OFF';
      badge.style.color = autoScheduleEnabled ? '#10B981' : '#EF4444';
    }
    if (typeof showToast === 'function') {
      showToast(`Auto-scheduling ${autoScheduleEnabled ? 'Enabled' : 'Disabled'}`);
    }
  };

  loadDependencies();
  window.addEventListener('resize', () => renderDependencyArrows());

  // Keyboard Shortcuts: M/C = Google Calendar, T = Scroll to Today
  document.addEventListener('keydown', function(e) {
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT') return;
    if (e.key === 'm' || e.key === 'M' || e.key === 'c' || e.key === 'C') {
      window.location.href = 'calendar.php?country=<?= $reqCountry; ?><?= $reqProject ? '&project_id=' . $reqProject : ''; ?>';
    } else if (e.key === 't' || e.key === 'T') {
      const scrollWrap = document.getElementById('ganttScrollable');
      const todayLine = document.querySelector('.today-indicator-line');
      if (scrollWrap && todayLine) {
        scrollWrap.scrollTo({ left: Math.max(0, parseInt(todayLine.style.left || '130', 10) - 200), behavior: 'smooth' });
        if (typeof showToast === 'function') showToast('Scrolled to Today');
      }
    }
  });
});
</script>
</body>
</html>
