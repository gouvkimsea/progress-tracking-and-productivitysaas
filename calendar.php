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
  .cal-header { margin-bottom: 24px; }
  .cal-title { margin: 0; font-size: 22px; font-weight: 800; color: var(--ink); }
  .cal-grid { display: grid; grid-template-columns: 7fr 4fr; gap: 20px; }
  .calendar-days-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 8px; margin-top: 16px; }
  .cal-day-head { text-align: center; font-size: 12px; font-weight: 700; color: var(--muted); text-transform: uppercase; padding: 8px 0; }
  .cal-day-box { background: var(--panel-bg); border: 1px solid var(--border-soft); border-radius: 12px; min-height: 78px; padding: 8px; font-size: 13px; font-weight: 700; display: flex; flex-direction: column; }
  .cal-day-box.today { background: var(--purple-light); border-color: var(--purple-soft); color: var(--purple-deep); }
  [data-theme="dark"] .cal-day-box.today { background: #1E1B4B; border-color: #4338CA; color: #A5B4FC; }
  .event-dot { font-size: 10px; font-weight: 700; padding: 2px 6px; border-radius: 6px; color: #fff; margin-top: 4px; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .upcoming-item { padding: 12px 0; border-bottom: 1px solid var(--border-soft); display: flex; flex-direction: column; gap: 2px; }
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
        <h2 class="cal-title">Study Calendar &amp; Project Deadlines</h2>
        <p style="margin:4px 0 0; color:var(--muted); font-size:14px; font-weight:500;">Track real task due dates and synchronize your learning roadmap</p>
      </div>
      <a href="api/calendar_export.php" class="btn-save" style="display:inline-flex; align-items:center; gap:8px; text-decoration:none; font-size:13px; padding:8px 16px;">
        📅 Download .ics Calendar Feed
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
          <div style="text-align:center; padding:30px 10px; color:var(--muted); font-size:13px;">
            <div style="font-size:28px; margin-bottom:6px;">🎉</div>
            No pending task deadlines!
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

<div class="tooltip" id="tooltip"></div>

<!-- Log Activity Modal -->
<div class="modal-overlay" id="logModalOverlay">
  <div class="modal-card">
    <div class="modal-header">
      <h3 class="modal-title">Log Study Activity</h3>
      <button class="modal-close-btn" id="btnCloseLogModal">&times;</button>
    </div>
    <form id="logActivityForm">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label" for="logLessons">Lessons Completed</label>
          <input type="number" id="logLessons" class="form-input" min="1" max="50" value="1" required />
        </div>
        <div class="form-group">
          <label class="form-label" for="logMinutes">Study Time (Minutes)</label>
          <input type="number" id="logMinutes" class="form-input" min="5" max="600" step="5" value="30" required />
        </div>
        <div class="form-group">
          <label class="form-label" for="logCategory">Category</label>
          <select id="logCategory" class="form-input">
            <option value="General">General Study</option>
            <option value="Design">Design</option>
            <option value="Programming">Programming</option>
            <option value="Data Science">Data Science</option>
            <option value="Business">Business</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label" for="logDate">Date</label>
          <input type="date" id="logDate" class="form-input" value="<?= date('Y-m-d'); ?>" required />
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-cancel" id="btnCancelLogModal">Cancel</button>
        <button type="submit" class="btn-save" id="btnSubmitLogModal">Save Activity</button>
      </div>
    </form>
  </div>
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
