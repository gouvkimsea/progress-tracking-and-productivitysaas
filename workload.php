<?php
require_once __DIR__ . '/config/db.php';
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

// Fetch All Registered Users as Resources
$stmtMembers = $db->query("SELECT id, name, email FROM users ORDER BY id ASC");
$resources = $stmtMembers ? $stmtMembers->fetchAll() : [];

// Fetch All Tasks from Database
$stmtAllTasks = $db->query("SELECT id, user_id, task_name, project_name, start_date, due_date, assigned_to, status, time_log FROM tasks WHERE (deleted_at IS NULL) ORDER BY id ASC");
$allTasks = $stmtAllTasks ? $stmtAllTasks->fetchAll() : [];

// Helper to determine if task is assigned to a specific user
function isTaskAssignedToResource(array $task, array $res): bool {
    $assigned = strtolower(trim($task['assigned_to'] ?? ''));
    if ($assigned === 'unassigned' || empty($assigned)) {
        return false;
    }
    $userName = strtolower(trim($res['name'] ?? ''));
    $userEmail = strtolower(trim($res['email'] ?? ''));
    $firstName = explode(' ', $userName)[0];

    if ($assigned === $userName || $assigned === $userEmail || $assigned === $firstName) {
        return true;
    }
    if ((int)$task['user_id'] === (int)$res['id'] && in_array($assigned, ['me', 'myself', $firstName], true)) {
        return true;
    }
    return false;
}

// Helper to parse dates stored in various formats
function parseDateTs(?string $dateStr): ?int {
    if (empty($dateStr)) return null;
    $d = str_replace('/', '-', trim($dateStr));
    $ts = strtotime($d);
    return $ts ?: null;
}

// Build Dynamic 28-Day Timeline Window (starts from Monday of previous week)
$timelineDaysCount = 28;
$timelineStartTs = strtotime('monday this week - 7 days');
if (!$timelineStartTs) {
    $timelineStartTs = strtotime('today - 7 days');
}

$timelineDays = [];
$monthHeaders = [];

for ($i = 0; $i < $timelineDaysCount; $i++) {
    $ts = $timelineStartTs + ($i * 86400);
    $mKey = date('F Y', $ts);
    if (!isset($monthHeaders[$mKey])) {
        $monthHeaders[$mKey] = 0;
    }
    $monthHeaders[$mKey]++;

    $timelineDays[] = [
        'timestamp' => $ts,
        'dayNum'    => (int)date('j', $ts),
        'dayOfWeek' => (int)date('N', $ts), // 1 (Mon) to 7 (Sun)
        'isWeekend' => in_array((int)date('N', $ts), [6, 7], true),
        'isToday'   => (date('Y-m-d', $ts) === date('Y-m-d')),
        'dateStr'   => date('Y-m-d', $ts)
    ];
}

// Compute workload per resource
$resourceWorkload = [];
$unassignedTasks = [];

foreach ($resources as $res) {
    $rId = (int)$res['id'];
    $assignedList = [];
    $dailyHours = array_fill(0, $timelineDaysCount, 0);
    $dailyTasks = array_fill(0, $timelineDaysCount, 0);

    foreach ($allTasks as $t) {
        if (isTaskAssignedToResource($t, $res)) {
            $assignedList[] = $t;

            // Map task date range
            $sTs = parseDateTs($t['start_date'] ?? null);
            $dTs = parseDateTs($t['due_date'] ?? null);

            if ($sTs && !$dTs) $dTs = $sTs;
            if (!$sTs && $dTs) $sTs = $dTs;

            if ($sTs && $dTs) {
                $startDayIdx = max(0, (int)floor(($sTs - $timelineStartTs) / 86400));
                $endDayIdx = min($timelineDaysCount - 1, (int)floor(($dTs - $timelineStartTs) / 86400));

                if ($startDayIdx <= $endDayIdx && $endDayIdx >= 0 && $startDayIdx < $timelineDaysCount) {
                    $loggedHours = (int)($t['time_log'] ?? 0);
                    $span = max(1, ($endDayIdx - $startDayIdx + 1));
                    $hoursPerDay = ($loggedHours > 0) ? max(1, (int)round($loggedHours / $span)) : 0;

                    for ($d = $startDayIdx; $d <= $endDayIdx; $d++) {
                        $dailyHours[$d] += $hoursPerDay;
                        $dailyTasks[$d] += 1;
                    }
                }
            }
        }
    }

    $resourceWorkload[$rId] = [
        'tasks' => $assignedList,
        'dailyHours' => $dailyHours,
        'dailyTasks' => $dailyTasks,
        'totalAssigned' => count($assignedList)
    ];
}

// Collect unassigned tasks
foreach ($allTasks as $t) {
    $assigned = strtolower(trim($t['assigned_to'] ?? ''));
    if ($assigned === 'unassigned' || empty($assigned)) {
        $unassignedTasks[] = $t;
    }
}

$pageTitle = 'Mindrift — Workload';
include __DIR__ . '/includes/head.php';
?>
<style>
  /* Workload Page Styling */
  .workload-header-row {
    display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;
  }
  .workload-title-wrap { display: flex; align-items: center; gap: 10px; }
  .workload-title-ic { color: var(--brand-primary); }
  .workload-title { margin: 0; font-size: 20px; font-weight: 700; letter-spacing: -0.02em; color: var(--text-primary); }
  .close-icon-btn { background: none; border: none; font-size: 18px; color: var(--text-muted); cursor: pointer; }

  /* Toolbar Controls Bar */
  .workload-toolbar-bar {
    display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; font-size: 13px; color: var(--text-secondary);
  }
  .workload-toolbar-left { display: flex; align-items: center; gap: 14px; }
  .btn-mode-select {
    background: none; border: none; font-size: 13px; font-weight: 500; color: var(--text-secondary); cursor: pointer;
    display: flex; align-items: center; gap: 4px;
  }
  .btn-mode-select b { color: var(--brand-primary); font-weight: 700; }

  .workload-toolbar-right { display: flex; align-items: center; gap: 16px; }
  .tool-btn-icon {
    background: var(--bg-surface); border: 1px solid var(--border-base); color: var(--text-secondary); padding: 6px 10px; border-radius: var(--radius-sm);
    font-size: 13px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px;
  }
  .tool-btn-icon:hover { background: var(--bg-subtle); color: var(--text-primary); }

  .slider-zoom-control { display: flex; align-items: center; gap: 6px; font-size: 12.5px; color: var(--text-muted); font-weight: 500; }

  /* Workload Matrix Table */
  .workload-matrix-wrap {
    width: 100%; border: 1px solid var(--border-base); border-radius: var(--radius-md); overflow-x: auto; background: var(--bg-surface);
  }
  .workload-matrix-table { border-collapse: collapse; width: 100%; min-width: 1100px; text-align: left; }
  
  .workload-matrix-table th, .workload-matrix-table td {
    border-bottom: 1px solid var(--border-base); border-right: 1px solid var(--border-base); padding: 8px; font-size: 12.5px;
  }
  
  .resource-th-col { width: 240px; min-width: 240px; background: var(--bg-subtle); font-weight: 600; color: var(--text-secondary); padding-left: 14px !important; }
  .month-th { background: var(--bg-subtle); font-weight: 600; color: var(--text-secondary); font-size: 12px; text-align: center; }
  .day-th { background: var(--bg-subtle); font-weight: 500; color: var(--text-muted); text-align: center; width: 28px; min-width: 28px; }
  .day-th.weekend-th { background: var(--bg-app); opacity: 0.75; }
  .day-th.today-th { background: var(--bg-subtle); color: var(--brand-primary); font-weight: 700; border-bottom: 2px solid var(--brand-primary); }
  
  .resource-td-cell {
    display: flex; align-items: center; justify-content: space-between; padding: 8px 12px !important; font-weight: 600; color: var(--text-primary);
  }
  .resource-user-flex { display: flex; align-items: center; gap: 10px; }
  .resource-avatar {
    width: 26px; height: 26px; border-radius: 50%; background: var(--brand-primary); color: #FFFFFF;
    font-size: 10.5px; font-weight: 800; display: flex; align-items: center; justify-content: center;
  }
  .resource-avatar.unassigned-avatar { background: var(--text-muted); }

  .hour-cell { text-align: center; font-weight: 700; color: var(--brand-primary); font-size: 12px; }
  .hour-cell.weekend-cell { background: rgba(0,0,0,0.015); }
  .hour-cell.today-cell { background: rgba(14,101,199,0.04); }
</style>
</head>
<body>

<div class="app" id="app">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>

  <main class="main">
    <?php include __DIR__ . '/includes/header.php'; ?>

    <!-- Workload Header -->
    <div class="workload-header-row">
      <div class="workload-title-wrap">
        <svg class="workload-title-ic" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
        <h2 class="workload-title">Workload</h2>
      </div>
      <button class="close-icon-btn" title="Close" aria-label="Close" onclick="window.location.href='index.php';">&times;</button>
    </div>

    <!-- Toolbar Controls Bar -->
    <div class="workload-toolbar-bar">
      <div class="workload-toolbar-left">
        <button type="button" class="btn-mode-select" id="btnModeSelect" onclick="cycleWorkloadMode()">
          Mode: <b id="modeLabel">Hours</b>
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m6 9 6 6 6-6"/></svg>
        </button>
        <span style="color:var(--border-base);">|</span>
        <button type="button" class="btn-mode-select" id="btnRangeSelect" onclick="cycleWorkloadRange()">
          Range: <b id="rangeLabel">4 weeks</b>
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m6 9 6 6 6-6"/></svg>
        </button>
      </div>

      <div class="workload-toolbar-right">
        <button type="button" class="tool-btn-icon" id="btnWorkloadFilter" onclick="toggleWorkloadFilter()">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
          Filter
        </button>
        <span style="color:var(--border-base);">|</span>
        <div class="slider-zoom-control" style="cursor:pointer;" onclick="cycleWorkloadMode()" title="Toggle view mode">
          <span>●---○---○</span>
          <span id="zoomScaleLabel">Hours</span>
        </div>
        <span style="color:var(--border-base);">|</span>
        <a href="api/export.php?type=workload" class="tool-btn-icon" style="text-decoration:none;">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
          Export
        </a>
      </div>
    </div>

    <!-- Resource Workload Matrix Grid Table -->
    <div class="workload-matrix-wrap">
      <table class="workload-matrix-table">
        <thead>
          <!-- Super Month Headers -->
          <tr class="month-row-header">
            <th class="resource-th-col"></th>
            <?php foreach ($monthHeaders as $mTitle => $mSpan): ?>
              <th colspan="<?= $mSpan; ?>" class="month-th"><?= htmlspecialchars($mTitle); ?></th>
            <?php endforeach; ?>
          </tr>

          <!-- Day of Month Headers -->
          <tr class="days-row-header">
            <th class="resource-th-col">Resource</th>
            <?php foreach ($timelineDays as $d): ?>
              <th class="day-th <?= $d['isWeekend'] ? 'weekend-th' : ''; ?> <?= $d['isToday'] ? 'today-th' : ''; ?>" title="<?= $d['dateStr']; ?>">
                <?= $d['dayNum']; ?>
              </th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($resources as $res): 
            $rId = (int)$res['id'];
            $wData = $resourceWorkload[$rId] ?? ['tasks' => [], 'dailyHours' => [], 'dailyTasks' => [], 'totalAssigned' => 0];
            $tasksAssigned = $wData['tasks'];
            $hasAssigned = count($tasksAssigned) > 0;
            
            // Extract initials
            $nameParts = preg_split('/\s+/', trim($res['name'] ?? 'User'));
            $initials = strtoupper(substr($nameParts[0] ?? 'U', 0, 1) . substr($nameParts[1] ?? '', 0, 1));
            if (empty($initials)) $initials = 'U';
          ?>
            <tr class="workload-member-row" data-assigned="<?= $hasAssigned ? '1' : '0'; ?>">
              <td style="padding:0;">
                <div class="resource-td-cell">
                  <div class="resource-user-flex">
                    <button type="button" class="btn-expand-res" onclick="toggleMemberBreakdown(this, 'breakdown-<?= $rId; ?>')" style="background:none; border:none; color:var(--text-muted); font-size:11px; cursor:pointer; padding:2px 4px;">▾</button>
                    <div class="resource-avatar"><?= htmlspecialchars($initials); ?></div>
                    <span><?= htmlspecialchars($res['name']); ?></span>
                  </div>
                  <span style="font-size:11px; color:var(--text-muted); font-weight:500;"><?= count($tasksAssigned); ?> tasks</span>
                </div>
              </td>
              <?php for ($d = 0; $d < $timelineDaysCount; $d++): 
                $h = $wData['dailyHours'][$d] ?? 0;
                $tCount = $wData['dailyTasks'][$d] ?? 0;
                $isWe = $timelineDays[$d]['isWeekend'];
                $isTd = $timelineDays[$d]['isToday'];
                $cls = 'hour-cell' . ($isWe ? ' weekend-cell' : '') . ($isTd ? ' today-cell' : '');
              ?>
                <td class="<?= $cls; ?>" data-hours="<?= $h; ?>" data-tasks="<?= $tCount; ?>">
                  <?= ($h > 0) ? $h : (($tCount > 0) ? '•' : ''); ?>
                </td>
              <?php endfor; ?>
            </tr>
            <!-- Member Breakdown Sub-row -->
            <tr id="breakdown-<?= $rId; ?>" style="display:none; background:var(--bg-subtle);">
              <td colspan="<?= $timelineDaysCount + 1; ?>" style="padding:10px 16px; font-size:12px; color:var(--text-secondary);">
                <?php if ($hasAssigned): ?>
                  <span>Assigned tasks:</span>
                  <?php 
                  $items = [];
                  foreach ($tasksAssigned as $t) {
                      $items[] = '<b>' . htmlspecialchars($t['task_name']) . '</b> (' . (int)($t['time_log'] ?? 0) . 'h, ' . htmlspecialchars($t['status'] ?? 'Open') . ')';
                  }
                  echo implode(' · ', $items);
                  ?>
                <?php else: ?>
                  <span style="color:var(--text-muted); font-style:italic;">No tasks currently assigned to <?= htmlspecialchars($res['name']); ?>.</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>

          <!-- Unassigned Tasks Row -->
          <tr class="workload-member-row" data-assigned="<?= !empty($unassignedTasks) ? '1' : '0'; ?>">
            <td style="padding:0;">
              <div class="resource-td-cell">
                <div class="resource-user-flex">
                  <button type="button" class="btn-expand-res" onclick="toggleMemberBreakdown(this, 'breakdown-unassigned')" style="background:none; border:none; color:var(--text-muted); font-size:11px; cursor:pointer; padding:2px 4px;">▾</button>
                  <div class="resource-avatar unassigned-avatar" style="font-size:11px; font-weight:700;">UA</div>
                  <span style="color:var(--text-secondary);">Unassigned</span>
                </div>
                <span style="font-size:11px; color:var(--text-muted); font-weight:500;"><?= count($unassignedTasks); ?> tasks</span>
              </div>
            </td>
            <?php for ($c = 0; $c < $timelineDaysCount; $c++): ?>
              <td></td>
            <?php endfor; ?>
          </tr>
          <!-- Unassigned Breakdown Sub-row -->
          <tr id="breakdown-unassigned" style="display:none; background:var(--bg-subtle);">
            <td colspan="<?= $timelineDaysCount + 1; ?>" style="padding:10px 16px; font-size:12px; color:var(--text-secondary);">
              <?php if (!empty($unassignedTasks)): ?>
                <span>Unassigned tasks:</span>
                <?php 
                $uItems = [];
                foreach ($unassignedTasks as $ut) {
                    $uItems[] = '<b>' . htmlspecialchars($ut['task_name']) . '</b> (' . htmlspecialchars($ut['status'] ?? 'Open') . ')';
                }
                echo implode(' · ', $uItems);
                ?>
              <?php else: ?>
                <span style="color:var(--text-muted); font-style:italic;">No unassigned tasks in the system.</span>
              <?php endif; ?>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

  </main>
</div>

<script src="assets/js/app.js"></script>
<script>
const modes = ['Hours', 'Days', 'Task Count'];
let modeIdx = 0;

window.cycleWorkloadMode = function() {
  modeIdx = (modeIdx + 1) % modes.length;
  const newMode = modes[modeIdx];
  const label = document.getElementById('modeLabel');
  if (label) label.textContent = newMode;
  const zoomLabel = document.getElementById('zoomScaleLabel');
  if (zoomLabel) zoomLabel.textContent = newMode;

  const cells = document.querySelectorAll('.hour-cell');
  cells.forEach(c => {
    const hours = parseFloat(c.getAttribute('data-hours') || '0');
    const tasks = parseInt(c.getAttribute('data-tasks') || '0', 10);
    
    if (newMode === 'Hours') {
      c.textContent = hours > 0 ? hours : (tasks > 0 ? '•' : '');
    } else if (newMode === 'Days') {
      if (hours > 0) {
        c.textContent = (hours / 8).toFixed(1) + 'd';
      } else if (tasks > 0) {
        c.textContent = '1d';
      } else {
        c.textContent = '';
      }
    } else if (newMode === 'Task Count') {
      c.textContent = tasks > 0 ? tasks : '';
    }
  });

  if (typeof showToast === 'function') showToast(`Workload view: ${newMode}`);
};

const ranges = ['4 weeks', '8 weeks', '12 weeks'];
let rangeIdx = 0;
window.cycleWorkloadRange = function() {
  rangeIdx = (rangeIdx + 1) % ranges.length;
  const label = document.getElementById('rangeLabel');
  if (label) label.textContent = ranges[rangeIdx];
  if (typeof showToast === 'function') showToast(`Timeline range: ${ranges[rangeIdx]}`);
};

let filterActiveOnly = false;
window.toggleWorkloadFilter = function() {
  filterActiveOnly = !filterActiveOnly;
  const btn = document.getElementById('btnWorkloadFilter');
  if (btn) {
    btn.style.color = filterActiveOnly ? 'var(--brand-primary)' : '';
    btn.style.fontWeight = filterActiveOnly ? '800' : '';
  }
  const zeroRows = document.querySelectorAll('.workload-member-row[data-assigned="0"]');
  zeroRows.forEach(row => {
    row.style.display = filterActiveOnly ? 'none' : '';
  });
  if (typeof showToast === 'function') {
    showToast(filterActiveOnly ? 'Showing active resources only' : 'Showing all resources');
  }
};

window.toggleMemberBreakdown = function(btn, rowId) {
  const row = document.getElementById(rowId);
  if (!row) return;
  const isHidden = row.style.display === 'none';
  row.style.display = isHidden ? 'table-row' : 'none';
  btn.textContent = isHidden ? '▴' : '▾';
};
</script>
</body>
</html>
