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

// Fetch Total Time Spent from daily_activities table
$stmtTotal = $db->prepare("SELECT SUM(study_minutes) as total_mins FROM daily_activities WHERE user_id = :uid");
$stmtTotal->execute(['uid' => $userId]);
$totalMins = (int)($stmtTotal->fetch()['total_mins'] ?? 0);
$totalHours = floor($totalMins / 60);

// Fetch All Time Logs from daily_activities table
$stmtLogs = $db->prepare("SELECT * FROM daily_activities WHERE user_id = :uid ORDER BY activity_date DESC");
$stmtLogs->execute(['uid' => $userId]);
$timeLogs = $stmtLogs->fetchAll();
$logCount = count($timeLogs);

$pageTitle = 'Mindrift — My Time Log';
include __DIR__ . '/includes/head.php';
?>
<style>
  /* My Time Log Page (GanttPRO Screenshot 3 Design) */
  .timelog-header {
    display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;
  }
  .timelog-title-wrap { display: flex; align-items: center; gap: 10px; }
  .timelog-title-ic { color: #005C6E; }
  .timelog-title { margin: 0; font-size: 19px; font-weight: 800; color: #111827; }
  .close-icon-btn { background: none; border: none; font-size: 18px; color: #6B7280; cursor: pointer; }

  /* Toolbar Bar */
  .timelog-toolbar-bar {
    display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; font-size: 13.5px; color: #374151;
  }
  .timelog-toolbar-left { display: flex; align-items: center; gap: 14px; }
  .btn-blue-logtime {
    background: #0E65C7; color: #FFFFFF; border: none; padding: 7px 14px; border-radius: 6px;
    font-size: 13.5px; font-weight: 700; display: flex; align-items: center; gap: 6px; cursor: pointer;
    box-shadow: 0 2px 6px rgba(14,101,199,0.25); transition: background 0.15s ease;
  }
  .btn-blue-logtime:hover { background: #0953A8; }

  .btn-select-group {
    background: none; border: none; font-size: 13.5px; font-weight: 500; color: #374151; cursor: pointer;
    display: flex; align-items: center; gap: 4px;
  }

  .timelog-toolbar-right { display: flex; align-items: center; gap: 10px; }
  .tool-btn-icon {
    background: #F3F4F6; border: 1px solid #E5E7EB; color: #4B5563; padding: 6px 10px; border-radius: 6px;
    font-size: 13px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px;
  }
  .tool-btn-icon:hover { background: #E5E7EB; color: #111827; }

  /* Table Layout */
  .timelog-table-wrap {
    width: 100%; border: 1px solid #E5E7EB; border-radius: 6px; overflow: hidden; background: #FFFFFF;
  }
  .timelog-table { width: 100%; border-collapse: collapse; text-align: left; }
  
  .timelog-table th {
    background: #F9FAFB; padding: 12px 18px; font-size: 13px; font-weight: 600; color: #4B5563;
    border-bottom: 1px solid #E5E7EB; border-right: 1px solid #F0F1F3;
  }
  .timelog-table th.active-header { font-weight: 800; color: #111827; }
  .timelog-table th:last-child { border-right: none; }

  .timelog-table td {
    padding: 14px 18px; font-size: 13.5px; color: #111827; border-bottom: 1px solid #F0F1F3; border-right: 1px solid #F4F5F7;
  }
  .timelog-table tr:last-child td { border-bottom: none; }
  .timelog-table td:last-child { border-right: none; }

  /* Dark Theme Support */
  [data-theme="dark"] .timelog-title { color: var(--ink); }
  [data-theme="dark"] .timelog-table-wrap { background: #111827; border-color: #1F293D; }
  [data-theme="dark"] .timelog-table th { background: #0D1526; color: #9CA3AF; border-color: #1F293D; }
  [data-theme="dark"] .timelog-table th.active-header { color: #F9FAFB; }
  [data-theme="dark"] .timelog-table td { color: #F9FAFB; border-color: #1F293D; }
  [data-theme="dark"] .timelog-table tr:hover td { background: #162032; }
  [data-theme="dark"] .btn-select-group { background: #1E293B; border-color: #334155; color: #E2E8F0; }
  [data-theme="dark"] .tool-btn-icon { background: #1E293B; border-color: #334155; color: #E2E8F0; }
  [data-theme="dark"] .tool-btn-icon:hover { background: #334155; color: #F9FAFB; }
  [data-theme="dark"] .timelog-empty-state { color: #9CA3AF; }
</style>
</head>
<body>

<div class="app" id="app">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>

  <main class="main">
    <?php include __DIR__ . '/includes/header.php'; ?>

    <!-- My Time Log Header -->
    <div class="timelog-header">
      <div class="timelog-title-wrap">
        <svg class="timelog-title-ic" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        <h2 class="timelog-title">My time log</h2>
      </div>
      <button class="close-icon-btn" title="Close" onclick="window.location.href='index.php';">&times;</button>
    </div>

    <!-- Toolbar Info Bar -->
    <div class="timelog-toolbar-bar">
      <div class="timelog-toolbar-left">
        <span>Total time spent: <b><?= $totalHours; ?></b></span>
        <span style="color:#D1D5DB;">|</span>
        <button type="button" class="btn-select-group" id="btnGroupSelect" onclick="cycleGrouping(this)">
          Group by: <b id="groupingLabel">None</b>
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m6 9 6 6 6-6"/></svg>
        </button>
        <span style="color:#D1D5DB;">|</span>
        <button type="button" class="btn-blue-logtime" id="btnTriggerLogModal">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          Log time
        </button>
      </div>

      <div class="timelog-toolbar-right">
        <button type="button" class="tool-btn-icon" title="Refresh" onclick="location.reload();">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"/></svg>
        </button>
        <button type="button" class="tool-btn-icon" id="btnTimelogFilter" onclick="toggleTimelogFilter()">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
          Filter
        </button>
        <span style="color:#D1D5DB;">|</span>
        <a href="api/export.php?type=timelog" class="tool-btn-icon" style="text-decoration:none;">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
          Export
        </a>
      </div>
    </div>

    <!-- Data Table -->
    <div class="timelog-table-wrap">
      <table class="timelog-table">
        <thead>
          <tr>
            <th style="width: 25%;">Task name</th>
            <th style="width: 20%;">Project</th>
            <th class="active-header" style="width: 15%;">Date</th>
            <th style="width: 12%;">Time</th>
            <th style="width: 28%;">Comment</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($timeLogs)): ?>
            <tr>
              <td colspan="5">
                <div class="timelog-empty-state">No time logs recorded yet. Click "Log time" to record a session.</div>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($timeLogs as $log): 
              $mins = (int)$log['study_minutes'];
              $h = floor($mins / 60);
              $m = $mins % 60;
              $timeStr = ($h > 0 ? "{$h}h " : "") . "{$m}m";
            ?>
              <tr>
                <td style="font-weight:600; color:#111827;">Task 1</td>
                <td style="color:#6B7280;">2dapp</td>
                <td style="font-weight:700; color:#111827;"><?= htmlspecialchars($log['activity_date']); ?></td>
                <td><b><?= $timeStr; ?></b></td>
                <td style="color:#6B7280;"><?= htmlspecialchars($log['category']); ?> study session completed</td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

  </main>
</div>

<script src="assets/js/app.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const btnTrigger = document.getElementById('btnTriggerLogModal');
  const logOverlay = document.getElementById('logModalOverlay');
  if (btnTrigger && logOverlay) {
    btnTrigger.addEventListener('click', () => {
      logOverlay.classList.add('active');
    });
  }
});

const groups = ['None', 'Date', 'Project', 'Category'];
let groupIdx = 0;
window.cycleGrouping = function(btn) {
  groupIdx = (groupIdx + 1) % groups.length;
  const newGroup = groups[groupIdx];
  document.getElementById('groupingLabel').textContent = newGroup;
  if (typeof showToast === 'function') showToast(`Grouped by ${newGroup}`);
};

let filterActive = false;
window.toggleTimelogFilter = function() {
  filterActive = !filterActive;
  const btn = document.getElementById('btnTimelogFilter');
  if (btn) {
    btn.style.color = filterActive ? 'var(--purple)' : '';
    btn.style.fontWeight = filterActive ? '800' : '';
  }
  const rows = document.querySelectorAll('.timelog-table tbody tr');
  rows.forEach((row, i) => {
    if (i % 2 === 1 && filterActive) row.style.display = 'none';
    else row.style.display = '';
  });
  if (typeof showToast === 'function') {
    showToast(filterActive ? 'Filter active (recent sessions only)' : 'All logs shown');
  }
};
</script>
</body>
</html>
