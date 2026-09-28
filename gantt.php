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

// Fetch Tasks
$stmtTasks = $db->prepare("SELECT * FROM tasks WHERE user_id = :uid AND (deleted_at IS NULL) ORDER BY id ASC");
$stmtTasks->execute(['uid' => $userId]);
$tasks = $stmtTasks->fetchAll();

// Dynamic Timeline Range (Starts 3 days ago and runs 35 days)
$timelineStartTs = strtotime('today -3 days');
$totalTimelineDays = 35;
$todayTs = strtotime('today');
$todayDayIndex = max(0, round(($todayTs - $timelineStartTs) / 86400));
$todayLeftPx = $todayDayIndex * 44;

$pageTitle = 'Mindrift — Gantt Chart';
include __DIR__ . '/includes/head.php';
?>
<style>
  .gantt-page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
  .gantt-title-wrap { display: flex; align-items: center; gap: 10px; }
  .gantt-title { margin: 0; font-size: 20px; font-weight: 700; letter-spacing: -0.02em; color: var(--text-primary); }

  .gantt-controls-bar {
    display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; background: var(--panel-bg);
    padding: 8px 14px; border-radius: var(--radius-md); border: 1px solid var(--border);
  }
  .zoom-btn-group { display: flex; align-items: center; gap: 4px; }
  .zoom-btn {
    padding: 5px 10px; font-size: 12px; font-weight: 500; color: var(--muted); background: var(--panel-bg); border: 1px solid var(--border);
    border-radius: var(--radius-xs); cursor: pointer; transition: all 0.15s ease;
  }
  .zoom-btn.active { background: var(--blue); color: #FFF; border-color: var(--blue); font-weight: 600; }

  .gantt-container-wrap {
    display: flex; width: 100%; border: 1px solid var(--border); border-radius: var(--radius-md); overflow: hidden; background: var(--panel-bg);
  }
  .gantt-sidebar-col { width: 280px; flex-shrink: 0; border-right: 1px solid var(--border); background: var(--panel-bg); }
  .gantt-sidebar-head { padding: 10px 14px; font-weight: 600; font-size: 12.5px; color: var(--muted); border-bottom: 1px solid var(--border); }
  .gantt-task-row { padding: 10px 14px; font-size: 13px; font-weight: 500; color: var(--ink); border-bottom: 1px solid var(--border); }

  .gantt-timeline-col { flex: 1; overflow-x: auto; position: relative; }
  .gantt-dates-head { display: flex; border-bottom: 1px solid var(--border); background: var(--panel-bg); }
  .gantt-date-cell { width: 44px; min-width: 44px; text-align: center; padding: 8px 0; font-size: 11px; font-weight: 500; color: var(--muted); border-right: 1px solid var(--border); }
  
  .gantt-bar-row { display: flex; align-items: center; height: 45px; border-bottom: 1px solid var(--border); position: relative; }
  .gantt-task-bar {
    position: absolute; height: 24px; border-radius: 4px; background: var(--blue);
    color: #FFF; font-size: 11.5px; font-weight: 600; display: flex; align-items: center; padding: 0 8px;
    box-shadow: none; cursor: grab; user-select: none; z-index: 5;
    transition: background 0.15s ease;
  }
  .gantt-task-bar.dragging {
    cursor: grabbing; opacity: 0.9; z-index: 20;
    background: var(--blue-hover);
  }
  .gantt-resize-handle {
    position: absolute; right: 0; top: 0; bottom: 0; width: 8px; cursor: ew-resize;
    background: rgba(255,255,255,0.3); border-radius: 0 4px 4px 0; transition: background 0.15s ease;
  }
  .gantt-resize-handle:hover {
    background: rgba(255,255,255,0.6);
  }

  .today-indicator-line {
    position: absolute; top: 0; bottom: 0; width: 2px; background: var(--red); z-index: 10; pointer-events: none;
  }
  .today-badge-tag {
    position: absolute; top: 2px; left: -16px; background: var(--red); color: #FFF; font-size: 9px; font-weight: 700; padding: 1px 4px; border-radius: 3px;
  }
</style>
</head>
<body>

<div class="app" id="app">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>

  <main class="main">
    <?php include __DIR__ . '/includes/header.php'; ?>

    <div class="gantt-page-header">
      <div class="gantt-title-wrap">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--brand-primary)" stroke-width="2.2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M7 8h6"/><path d="M10 12h8"/><path d="M7 16h5"/></svg>
        <h2 class="gantt-title">Gantt Timeline</h2>
      </div>
    </div>

    <!-- Controls Bar -->
    <div class="gantt-controls-bar">
      <div class="zoom-level-toggle">
        <button class="zoom-btn active">Days</button>
        <button class="zoom-btn">Weeks</button>
        <button class="zoom-btn">Months</button>
      </div>

      <div style="display:flex; align-items:center; gap:12px;">
        <button type="button" class="btn-step" id="btnToggleCriticalPath" onclick="toggleCriticalPath()" style="display:inline-flex; align-items:center; gap:6px; font-weight:700;">
          Highlight Critical Path
        </button>
        <div style="font-size:13px; font-weight:600; color:var(--text-primary); cursor:pointer; user-select:none;" onclick="toggleAutoSchedule()" title="Click to toggle auto-scheduling">
          <span>Auto Scheduling: <b id="autoScheduleBadge" style="color:var(--status-done-text);">ON</b></span>
        </div>
      </div>
    </div>

    <!-- Gantt Chart Timeline View -->
    <div class="gantt-container-wrap">
      <!-- Left Task List Sidebar -->
      <div class="gantt-sidebar-col">
        <div class="gantt-sidebar-head">Task Name</div>
        <?php foreach ($tasks as $t): ?>
          <div class="gantt-task-row"><?= htmlspecialchars($t['task_name']); ?></div>
        <?php endforeach; ?>
      </div>

      <!-- Right Timeline Grid -->
      <div class="gantt-timeline-col" style="position:relative;">
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

        <div class="today-indicator-line" data-today-idx="<?= $todayDayIndex; ?>" style="left: <?= $todayLeftPx; ?>px;">
          <span class="today-badge-tag">TODAY</span>
        </div>

        <div class="gantt-dates-head">
          <?php for ($i = 0; $i < $totalTimelineDays; $i++): 
            $dayTs = $timelineStartTs + ($i * 86400);
            $dNum = date('j', $dayTs);
            $mName = date('M', $dayTs);
            $isToday = (date('Y-m-d', $dayTs) === date('Y-m-d', $todayTs));
          ?>
            <div class="gantt-date-cell" style="<?= $isToday ? 'background:var(--bg-subtle); font-weight:700; color:var(--brand-primary);' : ''; ?>">
              <?= $dNum; ?><br/><span style="font-size:10px; font-weight:600;"><?= $mName; ?></span>
            </div>
          <?php endfor; ?>
        </div>

        <!-- Task Bars Rows -->
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
        ?>
          <div class="gantt-bar-row">
            <div class="gantt-task-bar" data-task-id="<?= $t['id']; ?>" data-day-offset="<?= $dayOffset; ?>" data-duration-days="<?= $daySpan; ?>" style="left: <?= $leftPx; ?>px; width: <?= $widthPx; ?>px;" title="Drag bar to reschedule • Drag right edge to resize duration">
              <span style="pointer-events:none; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?= htmlspecialchars($t['task_name']); ?></span>
              <div class="gantt-resize-handle" title="Drag to adjust duration"></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

  </main>
</div>

<script src="assets/js/app.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const bars = document.querySelectorAll('.gantt-task-bar');
  const dayCellWidth = 44;
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
      const res = await secureFetch('api/gantt.php');
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

    // Remove existing paths
    const oldPaths = svgOverlay.querySelectorAll('.gantt-dep-arrow');
    oldPaths.forEach(p => p.remove());

    const timelineRect = svgOverlay.parentElement.getBoundingClientRect();

    loadedDependencies.forEach((dep, idx) => {
      const predBar = document.querySelector(`.gantt-task-bar[data-task-id="${dep.depends_on_task_id}"]`);
      const succBar = document.querySelector(`.gantt-task-bar[data-task-id="${dep.task_id}"]`);

      if (!predBar || !succBar) return;

      const pRect = predBar.getBoundingClientRect();
      const sRect = succBar.getBoundingClientRect();

      // Relative coordinates inside the timeline
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

      // Auto-cascade: shift dependent tasks if predecessor pushes past them
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
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          action: 'update_dates',
          task_id: taskId,
          start_date: fmtStart,
          due_date: fmtDue
        })
      });
      const data = await res.json();
      if (data.success) {
        if (typeof showToast === 'function') {
          showToast(`Rescheduled to ${fmtStart} (${durationDays} days)`);
        }
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

  // Initial draw
  loadDependencies();
  window.addEventListener('resize', () => renderDependencyArrows());
});
</script>
</body>
</html>
