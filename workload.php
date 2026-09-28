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
$resources = $stmtMembers->fetchAll();

$pageTitle = 'Mindrift — Workload';
include __DIR__ . '/includes/head.php';
?>
<style>
  /* Workload Page (GanttPRO Screenshot 5 Design) */
  .workload-header-row {
    display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;
  }
  .workload-title-wrap { display: flex; align-items: center; gap: 10px; }
  .workload-title-ic { color: #10B981; }
  .workload-title { margin: 0; font-size: 20px; font-weight: 800; color: #111827; }
  .close-icon-btn { background: none; border: none; font-size: 18px; color: #6B7280; cursor: pointer; }

  /* Toolbar Controls Bar */
  .workload-toolbar-bar {
    display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; font-size: 13.5px; color: #374151;
  }
  .workload-toolbar-left { display: flex; align-items: center; gap: 14px; }
  .btn-mode-select {
    background: none; border: none; font-size: 13.5px; font-weight: 500; color: #374151; cursor: pointer;
    display: flex; align-items: center; gap: 4px;
  }
  .btn-mode-select b { color: #0E65C7; font-weight: 700; }

  .workload-toolbar-right { display: flex; align-items: center; gap: 16px; }
  .tool-btn-icon {
    background: #F3F4F6; border: 1px solid #E5E7EB; color: #4B5563; padding: 6px 10px; border-radius: 6px;
    font-size: 13px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px;
  }
  .tool-btn-icon:hover { background: #E5E7EB; color: #111827; }

  .slider-zoom-control { display: flex; align-items: center; gap: 6px; font-size: 12.5px; color: #6B7280; font-weight: 500; }

  /* Workload Matrix Table */
  .workload-matrix-wrap {
    width: 100%; border: 1px solid #E5E7EB; border-radius: 6px; overflow-x: auto; background: #FFFFFF;
  }
  .workload-matrix-table { border-collapse: collapse; width: 100%; min-width: 1100px; text-align: left; }
  
  .workload-matrix-table th, .workload-matrix-table td {
    border-bottom: 1px solid #E5E7EB; border-right: 1px solid #F0F1F3; padding: 8px; font-size: 12.5px;
  }
  
  .resource-th-col { width: 240px; min-width: 240px; background: #F9FAFB; font-weight: 600; color: #4B5563; padding-left: 14px !important; }
  .date-header-super { background: #F9FAFB; font-weight: 600; color: #4B5563; font-size: 12px; text-align: center; }
  .date-day-col { background: #F9FAFB; font-weight: 500; color: #6B7280; text-anchor: middle; text-align: center; width: 28px; min-width: 28px; }
  
  .resource-td-cell {
    display: flex; align-items: center; justify-content: space-between; padding: 8px 12px !important; font-weight: 600; color: #111827;
  }
  .resource-user-flex { display: flex; align-items: center; gap: 10px; }
  .resource-avatar {
    width: 26px; height: 26px; border-radius: 50%; background: #10B981; color: #FFFFFF;
    font-size: 10.5px; font-weight: 800; display: flex; align-items: center; justify-content: center;
  }
  .resource-avatar.unassigned-avatar { background: #9CA3AF; }

  .hour-cell { text-align: center; font-weight: 700; color: #10B981; font-size: 12px; }

  /* Dark Theme Support */
  [data-theme="dark"] .workload-title { color: var(--ink); }
  [data-theme="dark"] .workload-matrix-wrap { background: #111827; border-color: #1F293D; }
  [data-theme="dark"] .resource-th-col, [data-theme="dark"] .date-header-super, [data-theme="dark"] .date-day-col { background: #0D1526; color: #9CA3AF; border-color: #1F293D; }
  [data-theme="dark"] .workload-matrix-table th, [data-theme="dark"] .workload-matrix-table td { border-color: #1F293D; }
  [data-theme="dark"] .resource-td-cell { color: #F9FAFB; }
  [data-theme="dark"] .tool-btn-icon { background: #1E293B; border-color: #334155; color: #E2E8F0; }
  [data-theme="dark"] .tool-btn-icon:hover { background: #334155; color: #F9FAFB; }
  [data-theme="dark"] .btn-mode-select { color: #9CA3AF; }
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
      <button class="close-icon-btn" title="Close" onclick="window.location.href='index.php';">&times;</button>
    </div>    <!-- Toolbar Controls Bar -->
    <div class="workload-toolbar-bar">
      <div class="workload-toolbar-left">
        <button type="button" class="btn-mode-select" id="btnModeSelect" onclick="cycleWorkloadMode()">
          Mode: <b id="modeLabel">Hours</b>
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m6 9 6 6 6-6"/></svg>
        </button>
        <span style="color:#D1D5DB;">|</span>
        <button type="button" class="btn-mode-select" id="btnRangeSelect" onclick="cycleWorkloadRange()">
          Range: <b id="rangeLabel">3 months</b>
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m6 9 6 6 6-6"/></svg>
        </button>
      </div>

      <div class="workload-toolbar-right">
        <button type="button" class="tool-btn-icon" id="btnWorkloadFilter" onclick="toggleWorkloadFilter()">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
          Filter
        </button>
        <span style="color:#D1D5DB;">|</span>
        <div class="slider-zoom-control" style="cursor:pointer;" onclick="cycleWorkloadMode()" title="Toggle zoom scale">
          <span>●---○---○</span>
          <span id="zoomScaleLabel">Days</span>
        </div>
        <span style="color:#D1D5DB;">|</span>
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
            <th colspan="12" class="month-th">September 2024</th>
            <th colspan="15" class="month-th">October 2024</th>
          </tr>

          <!-- Day of Month Headers -->
          <tr class="days-row-header">
            <th class="resource-th-col">Resource</th>
            <?php 
            for ($d = 16; $d <= 30; $d++): 
              $isWe = in_array($d, [21, 22, 28, 29]);
            ?>
              <th class="day-th <?= $isWe ? 'weekend-th' : ''; ?>"><?= $d; ?></th>
            <?php endfor; ?>

            <?php 
            for ($d = 1; $d <= 12; $d++): 
              $isWe = in_array($d, [5, 6, 12]);
            ?>
              <th class="day-th <?= $isWe ? 'weekend-th' : ''; ?>"><?= $d; ?></th>
            <?php endfor; ?>
          </tr>
        </thead>
        <tbody>
          <!-- Member 1: Sea Sea -->
          <tr class="workload-member-row" data-assigned="1">
            <td style="padding:0;">
              <div class="resource-td-cell">
                <div class="resource-user-flex">
                  <button type="button" class="btn-expand-res" onclick="toggleMemberBreakdown(this, 'breakdown-1')" style="background:none; border:none; color:#6B7280; font-size:11px; cursor:pointer; padding:2px 4px;">▾</button>
                  <div class="resource-avatar">SS</div>
                  <span>Sea Sea</span>
                </div>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#9CA3AF" stroke-width="2"><rect x="3" y="4" width="18" height="16" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/></svg>
              </div>
            </td>
            <td class="hour-cell">8</td>
            <td class="hour-cell">8</td>
            <td class="hour-cell">6</td>
            <td class="hour-cell">8</td>
            <td class="hour-cell"></td>
            <td class="hour-cell"></td>
            <td class="hour-cell">8</td>
            <td class="hour-cell">7</td>
            <td class="hour-cell">8</td>
            <td class="hour-cell">8</td>
            <td class="hour-cell">6</td>
            <td class="hour-cell"></td>
            <td class="hour-cell"></td>
            <td class="hour-cell">8</td>
            <td class="hour-cell">8</td>
            <td class="hour-cell">8</td>
            <td class="hour-cell">7</td>
            <td class="hour-cell">8</td>
            <td class="hour-cell"></td>
            <td class="hour-cell"></td>
            <td class="hour-cell">8</td>
            <td class="hour-cell">8</td>
            <td class="hour-cell">8</td>
            <td class="hour-cell">6</td>
            <td class="hour-cell">8</td>
            <td class="hour-cell"></td>
            <td class="hour-cell"></td>
          </tr>
          <!-- Member 1 Breakdown Sub-row -->
          <tr id="breakdown-1" style="display:none; background:rgba(108,92,231,0.03);">
            <td colspan="28" style="padding:10px 16px; font-size:12px; color:var(--muted);">
              Assigned to: <b>Core Dashboard Redesign</b> (34h) · <b>Gantt Auto-Scheduler</b> (22h)
            </td>
          </tr>

          <!-- Member 2: unassigned -->
          <tr class="workload-member-row" data-assigned="0">
            <td style="padding:0;">
              <div class="resource-td-cell">
                <div class="resource-user-flex">
                  <button type="button" class="btn-expand-res" onclick="toggleMemberBreakdown(this, 'breakdown-2')" style="background:none; border:none; color:#6B7280; font-size:11px; cursor:pointer; padding:2px 4px;">▾</button>
                  <div class="resource-avatar unassigned-avatar" style="font-size:11px; font-weight:700;">UA</div>
                  <span style="color:#6B7280;">unassigned</span>
                </div>
              </div>
            </td>
            <?php for ($c = 0; $c < 27; $c++): ?>
              <td></td>
            <?php endfor; ?>
          </tr>
          <!-- Member 2 Breakdown Sub-row -->
          <tr id="breakdown-2" style="display:none; background:rgba(0,0,0,0.02);">
            <td colspan="28" style="padding:10px 16px; font-size:12px; color:var(--muted);">
              No unassigned tasks.
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
    if (!c.textContent.trim()) return;
    if (newMode === 'Hours') c.textContent = (parseInt(c.textContent) > 0) ? '8' : '';
    else if (newMode === 'Days') c.textContent = (parseInt(c.textContent) > 0) ? '1d' : '';
    else c.textContent = (parseInt(c.textContent) > 0) ? '2' : '';
  });

  if (typeof showToast === 'function') showToast(`Workload view: ${newMode}`);
};

const ranges = ['1 month', '3 months', '6 months'];
let rangeIdx = 1;
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
    btn.style.color = filterActiveOnly ? 'var(--purple)' : '';
    btn.style.fontWeight = filterActiveOnly ? '800' : '';
  }
  const unassignedRow = document.querySelector('.workload-member-row[data-assigned="0"]');
  if (unassignedRow) {
    unassignedRow.style.display = filterActiveOnly ? 'none' : '';
  }
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
