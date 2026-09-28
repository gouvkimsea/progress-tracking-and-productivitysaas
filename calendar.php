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

// Fetch tasks for user and index by day of current month
$stmtTasks = $db->prepare("SELECT * FROM tasks WHERE user_id = :uid AND (deleted_at IS NULL) ORDER BY id ASC");
$stmtTasks->execute(['uid' => $userId]);
$allTasks = $stmtTasks->fetchAll();

$tasksByDay = [];
$reqMonth = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
$reqYear = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
if ($reqMonth < 1 || $reqMonth > 12) $reqMonth = (int)date('n');
if ($reqYear < 2000 || $reqYear > 2100) $reqYear = (int)date('Y');

$prevMonth = ($reqMonth === 1) ? 12 : $reqMonth - 1;
$prevYear = ($reqMonth === 1) ? $reqYear - 1 : $reqYear;
$nextMonth = ($reqMonth === 12) ? 1 : $reqMonth + 1;
$nextYear = ($reqMonth === 12) ? $reqYear + 1 : $reqYear;

$monthTs = mktime(0, 0, 0, $reqMonth, 1, $reqYear);
$monthLabel = date('F Y', $monthTs);
$firstDayOfMonth = (int)date('N', $monthTs); // 1 (Mon) to 7 (Sun)
$daysInMonth = (int)date('t', $monthTs);
$upcomingDeadlines = [];

foreach ($allTasks as $t) {
    $dStr = $t['due_date'] ?? $t['start_date'] ?? '';
    if (!empty($dStr)) {
        $ts = strtotime(str_replace('/', '-', $dStr));
        if ($ts) {
            if ((int)date('n', $ts) === $reqMonth && (int)date('Y', $ts) === $reqYear) {
                $dayNum = (int)date('j', $ts);
                $tasksByDay[$dayNum][] = $t;
            }
            if ($ts >= strtotime('today')) {
                $upcomingDeadlines[] = [
                    'task' => $t,
                    'timestamp' => $ts
                ];
            }
        }
    }
}

usort($upcomingDeadlines, fn($a, $b) => $a['timestamp'] <=> $b['timestamp']);

$pageTitle = 'Mindrift — Study Calendar & Schedule';
include __DIR__ . '/includes/head.php';
?>
<style>
  .cal-header { margin-bottom: 20px; }
  .cal-title { margin: 0; font-size: 20px; font-weight: 700; letter-spacing: -0.02em; color: var(--text-primary); }
  .cal-grid { display: grid; grid-template-columns: 7fr 4fr; gap: 16px; }
  .calendar-days-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 6px; margin-top: 14px; }
  .cal-day-head { text-align: center; font-size: 11px; font-weight: 600; color: var(--muted); text-transform: uppercase; padding: 6px 0; }
  .cal-day-box { background: var(--panel-bg); border: 1px solid var(--border); border-radius: var(--radius-sm); min-height: 72px; padding: 6px; font-size: 12px; font-weight: 600; display: flex; flex-direction: column; }
  .cal-day-box.today { background: var(--bg-subtle); border-color: var(--brand-primary); color: var(--brand-primary); }
  .event-dot { font-size: 10px; font-weight: 600; padding: 2px 4px; border-radius: var(--radius-xs); color: #fff; margin-top: 3px; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .upcoming-item { padding: 10px 0; border-bottom: 1px solid var(--border); display: flex; flex-direction: column; gap: 2px; }
  .upcoming-item:last-child { border-bottom: none; }
</style>
</head>
<body>

<div class="app" id="app">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>

  <main class="main">
    <?php include __DIR__ . '/includes/header.php'; ?>

    <div class="cal-header" style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;">
      <div>
        <h2 class="cal-title">Calendar</h2>
        <p style="margin:4px 0 0; color:var(--muted); font-size:13px; font-weight:400;">View upcoming task deadlines and schedule items.</p>
      </div>
      <a href="api/calendar_export.php" class="btn btn-secondary" style="display:inline-flex; align-items:center; gap:6px; text-decoration:none; font-size:12.5px;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        Export iCalendar (.ics)
      </a>
    </div>

    <div class="cal-grid">
      <!-- Calendar View -->
      <div class="card card-block">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:12px;">
          <div style="display:flex; align-items:center; gap:10px;">
            <a href="?month=<?= $prevMonth; ?>&year=<?= $prevYear; ?>" class="btn-step" style="padding:4px 10px; text-decoration:none; font-weight:800;" title="Previous Month">‹</a>
            <h3 style="margin:0; font-size:16.5px; font-weight:700;"><?= $monthLabel; ?></h3>
            <a href="?month=<?= $nextMonth; ?>&year=<?= $nextYear; ?>" class="btn-step" style="padding:4px 10px; text-decoration:none; font-weight:800;" title="Next Month">›</a>
          </div>
          <span style="font-size:12px; font-weight:600; color:var(--muted);"><?= count($allTasks); ?> Active Tasks</span>
        </div>
        <div class="calendar-days-grid">
          <div class="cal-day-head">Mon</div>
          <div class="cal-day-head">Tue</div>
          <div class="cal-day-head">Wed</div>
          <div class="cal-day-head">Thu</div>
          <div class="cal-day-head">Fri</div>
          <div class="cal-day-head">Sat</div>
          <div class="cal-day-head">Sun</div>

          <?php 
          // Fill preceding blank days
          for ($pad = 1; $pad < $firstDayOfMonth; $pad++): ?>
            <div class="cal-day-box" style="background:transparent; border-color:transparent;"></div>
          <?php endfor; ?>

          <?php 
          $todayNum = (int)date('j');
          for ($d = 1; $d <= $daysInMonth; $d++): 
            $isToday = ($d === $todayNum && $reqMonth === (int)date('n') && $reqYear === (int)date('Y'));
            $dateFormatted = sprintf('%02d-%02d-%04d', $d, $reqMonth, $reqYear);
          ?>
            <div class="cal-day-box <?= $isToday ? 'today' : ''; ?>" style="cursor:pointer;" onclick="openCalendarDateModal('<?= $dateFormatted; ?>')" title="Click to add task on <?= $dateFormatted; ?>">
              <div style="display:flex; align-items:center; justify-content:space-between;">
                <span><?= $d; ?></span>
                <?php if ($isToday): ?>
                  <span style="font-size:9px; background:var(--purple); color:#fff; padding:1px 4px; border-radius:4px;">TODAY</span>
                <?php endif; ?>
              </div>

              <?php if (!empty($tasksByDay[$d])): ?>
                <?php foreach (array_slice($tasksByDay[$d], 0, 2) as $t): 
                  $prio = strtolower($t['priority'] ?? 'medium');
                  $color = ($prio === 'urgent') ? '#EF4444' : (($prio === 'high') ? '#F59E0B' : '#3B82F6');
                ?>
                  <span class="event-dot" style="background:<?= $color; ?>;" title="<?= htmlspecialchars($t['task_name']); ?> (<?= htmlspecialchars($t['priority'] ?? 'Medium'); ?>)">
                    <?= htmlspecialchars($t['task_name']); ?>
                  </span>
                <?php endforeach; ?>
                <?php if (count($tasksByDay[$d]) > 2): ?>
                  <span style="font-size:10px; color:var(--muted); font-weight:700; margin-top:2px;">+<?= count($tasksByDay[$d]) - 2; ?> more</span>
                <?php endif; ?>
              <?php endif; ?>
            </div>
          <?php endfor; ?>
        </div>
      </div>

      <!-- Upcoming Deadlines & Sessions -->
      <div class="card card-block">
        <h3 style="margin:0 0 16px; font-size:16.5px; font-weight:700;">Upcoming Task Deadlines</h3>
        
        <?php if (empty($upcomingDeadlines)): ?>
          <div style="text-align:center; padding:24px 10px; color:var(--muted); font-size:13px;">
            No pending task deadlines
          </div>
        <?php else: ?>
          <?php foreach (array_slice($upcomingDeadlines, 0, 5) as $ud): 
            $t = $ud['task'];
            $prio = strtolower($t['priority'] ?? 'medium');
            $prioColor = ($prio === 'urgent') ? '#DC2626' : (($prio === 'high') ? '#EA580C' : '#0284C7');
            $dateFormatted = date('l, M j', $ud['timestamp']);
          ?>
            <div class="upcoming-item">
              <span style="font-size:11px; font-weight:700; color:<?= $prioColor; ?>; text-transform:uppercase;">
                <?= $dateFormatted; ?> • <?= htmlspecialchars($t['priority'] ?? 'Medium'); ?>
              </span>
              <div style="font-size:13.5px; font-weight:700; color:var(--ink);"><?= htmlspecialchars($t['task_name']); ?></div>
              <div style="font-size:12px; color:var(--muted);">Project: <?= htmlspecialchars($t['project_name']); ?> • Assigned: <?= htmlspecialchars($t['assigned_to']); ?></div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

  </main>
</div>

<!-- Calendar Quick Date Task Modal -->
<div class="modal-overlay" id="calTaskModal">
  <div class="modal-card">
    <div class="modal-header">
      <h3 class="modal-title">Schedule Task for <span id="calTaskDateLabel" style="color:var(--purple);"></span></h3>
      <button class="modal-close-btn" onclick="document.getElementById('calTaskModal').classList.remove('active')">&times;</button>
    </div>
    <form id="calTaskForm" onsubmit="handleCalTaskSubmit(event)">
      <input type="hidden" id="calTaskDate" />
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label" for="calTaskName">Task Name</label>
          <input type="text" id="calTaskName" class="form-input" placeholder="e.g. Prepare Presentation" required />
        </div>
        <div class="form-group">
          <label class="form-label" for="calTaskProject">Project</label>
          <input type="text" id="calTaskProject" class="form-input" value="General" required />
        </div>
        <div class="form-group">
          <label class="form-label" for="calTaskPriority">Priority</label>
          <select id="calTaskPriority" class="form-input">
            <option value="Urgent">🔥 Urgent</option>
            <option value="High">⚡ High</option>
            <option value="Medium" selected>📌 Medium</option>
            <option value="Low">☕ Low</option>
          </select>
        </div>
      </div>
      <div class="modal-footer" style="display:flex; justify-content:flex-end; gap:8px;">
        <button type="button" class="btn-cancel" onclick="document.getElementById('calTaskModal').classList.remove('active')">Cancel</button>
        <button type="submit" class="btn-save">Schedule Task</button>
      </div>
    </form>
  </div>
</div>

<script src="assets/js/app.js"></script>
<script>
window.openCalendarDateModal = function(dateStr) {
  const modal = document.getElementById('calTaskModal');
  if (!modal) return;
  document.getElementById('calTaskDateLabel').textContent = dateStr;
  document.getElementById('calTaskDate').value = dateStr;
  modal.classList.add('active');
  setTimeout(() => document.getElementById('calTaskName')?.focus(), 100);
};

window.handleCalTaskSubmit = async function(e) {
  e.preventDefault();
  const dateStr = document.getElementById('calTaskDate').value;
  const payload = {
    action: 'create',
    task_name: document.getElementById('calTaskName').value.trim(),
    project_name: document.getElementById('calTaskProject').value.trim(),
    priority: document.getElementById('calTaskPriority').value,
    due_date: dateStr,
    start_date: dateStr,
    assigned_to: 'Alex Morgan',
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
      showToast('Task scheduled for ' + dateStr);
      document.getElementById('calTaskModal').classList.remove('active');
      document.getElementById('calTaskForm').reset();
      setTimeout(() => location.reload(), 400);
    } else {
      alert(data.message || 'Failed to schedule task');
    }
  } catch (err) {
    console.error(err);
  }
};
</script>
</body>
</html>
