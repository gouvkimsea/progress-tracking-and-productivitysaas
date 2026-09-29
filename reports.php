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

// Fetch Real Course / Project Metrics
$stmtCourses = $db->prepare("SELECT * FROM courses WHERE user_id = :uid ORDER BY id ASC");
$stmtCourses->execute(['uid' => $userId]);
$courses = $stmtCourses->fetchAll();
$totalCourses = count($courses);
$completedCourses = 0;
$inProgressCourses = 0;
foreach ($courses as $c) {
    if ((int)$c['progress_pct'] >= 100) $completedCourses++;
    else $inProgressCourses++;
}

// Fetch Real Task Metrics by Status
$stmtTasks = $db->prepare("SELECT status, COUNT(*) as cnt FROM tasks WHERE user_id = :uid AND deleted_at IS NULL GROUP BY status");
$stmtTasks->execute(['uid' => $userId]);
$rawStatus = $stmtTasks->fetchAll(PDO::FETCH_KEY_PAIR);
$openTasks = (int)($rawStatus['Open'] ?? 0);
$inProgressTasks = (int)($rawStatus['In Progress'] ?? 0);
$reviewTasks = (int)($rawStatus['Review'] ?? 0);
$doneTasks = (int)($rawStatus['Done'] ?? 0);
$totalTasks = $openTasks + $inProgressTasks + $reviewTasks + $doneTasks;
$taskCompletionPct = $totalTasks > 0 ? round(($doneTasks / $totalTasks) * 100) : 0;

// Fetch Real Time Log Metrics
$stmtTotalTime = $db->prepare("SELECT SUM(study_minutes) as total_mins FROM daily_activities WHERE user_id = :uid");
$stmtTotalTime->execute(['uid' => $userId]);
$totalMins = (int)($stmtTotalTime->fetch()['total_mins'] ?? 0);
$totalHours = floor($totalMins / 60);
$remMins = $totalMins % 60;

$weekStart = date('Y-m-d', strtotime('monday this week'));
$stmtWeekTime = $db->prepare("SELECT SUM(study_minutes) as week_mins FROM daily_activities WHERE user_id = :uid AND activity_date >= :wstart");
$stmtWeekTime->execute(['uid' => $userId, 'wstart' => $weekStart]);
$weekMins = (int)($stmtWeekTime->fetch()['week_mins'] ?? 0);
$weekHours = floor($weekMins / 60);
$weekRemMins = $weekMins % 60;

// Fetch Real Upcoming Deadlines (Milestones)
$stmtUpcoming = $db->prepare("SELECT id, task_name, project_name, due_date, status, priority FROM tasks WHERE user_id = :uid AND deleted_at IS NULL AND due_date IS NOT NULL AND due_date != '' ORDER BY due_date ASC LIMIT 4");
$stmtUpcoming->execute(['uid' => $userId]);
$milestones = $stmtUpcoming->fetchAll();

// Fetch Real Objectives & Deliverables from project_goals
$stmtGoals = $db->prepare("SELECT * FROM project_goals WHERE user_id = :uid ORDER BY id DESC LIMIT 4");
$stmtGoals->execute(['uid' => $userId]);
$goals = $stmtGoals->fetchAll();

$pageTitle = 'Mindrift — Reports';
include __DIR__ . '/includes/head.php';
?>
<style>
  /* Reports Page Styling */
  .reports-header-row {
    display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;
  }
  .reports-title-wrap { display: flex; align-items: center; gap: 10px; }
  .reports-title-ic { color: var(--brand-primary); }
  .reports-title { margin: 0; font-size: 20px; font-weight: 700; letter-spacing: -0.02em; color: var(--text-primary); }
  .close-icon-btn { background: none; border: none; font-size: 18px; color: var(--text-muted); cursor: pointer; }

  /* Pill Navigation Bar */
  .reports-tab-nav {
    display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; flex-wrap: wrap; gap: 12px;
  }
  .pill-group { display: flex; align-items: center; gap: 4px; background: var(--bg-subtle); padding: 4px; border-radius: var(--radius-md); }
  .pill-btn {
    padding: 6px 14px; font-size: 13px; font-weight: 500; color: var(--text-secondary); border: none; background: none;
    border-radius: var(--radius-sm); cursor: pointer; transition: all 0.15s ease;
  }
  .pill-btn.active { background: var(--bg-surface); color: var(--text-primary); box-shadow: var(--shadow-sm); font-weight: 600; }

  .subhead-label { font-size: 11px; font-weight: 700; letter-spacing: 0.05em; color: var(--text-muted); text-transform: uppercase; margin-bottom: 14px; }

  /* Reports Grid Cards */
  .reports-cards-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
  .report-card { padding: 20px; background: var(--bg-surface); border: 1px solid var(--border-base); border-radius: var(--radius-md); box-shadow: var(--shadow-sm); }
  .report-card-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px; }
  .report-card-title { margin: 0; font-size: 16px; font-weight: 700; color: var(--text-primary); }
  .report-card-desc { font-size: 13px; color: var(--text-secondary); margin: 4px 0 16px; line-height: 1.45; }

  /* Timeline list items */
  .deadline-item {
    display: flex; align-items: center; justify-content: space-between; padding: 10px 12px;
    background: var(--bg-subtle); border: 1px solid var(--border-base); border-radius: var(--radius-sm); margin-bottom: 8px;
  }
  .deadline-item:last-child { margin-bottom: 0; }
  .deadline-name { font-size: 13px; font-weight: 600; color: var(--text-primary); }
  .deadline-project { font-size: 11.5px; color: var(--text-muted); }
  .deadline-badge { font-size: 11px; font-weight: 600; padding: 2px 7px; border-radius: var(--radius-xs); }
</style>
</head>
<body>

<div class="app" id="app">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>

  <main class="main">
    <?php include __DIR__ . '/includes/header.php'; ?>

    <!-- Reports Header -->
    <div class="reports-header-row">
      <div class="reports-title-wrap">
        <svg class="reports-title-ic" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="18" x2="9" y2="15"/><line x1="15" y1="18" x2="15" y2="9"/></svg>
        <h2 class="reports-title">Reports</h2>
      </div>
      <button class="close-icon-btn" title="Close" aria-label="Close" onclick="window.location.href='index.php';">&times;</button>
    </div>

    <!-- Pill Tabs -->
    <div class="reports-tab-nav">
      <div class="pill-group">
        <button type="button" class="pill-btn active" data-filter="all" onclick="filterReports('all', this)">All</button>
        <button type="button" class="pill-btn" data-filter="progress" onclick="filterReports('progress', this)">Progress</button>
        <button type="button" class="pill-btn" data-filter="goals" onclick="filterReports('goals', this)">Objectives</button>
        <button type="button" class="pill-btn" data-filter="time" onclick="filterReports('time', this)">Time Logged</button>
      </div>
      <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
        <a href="portfolio.php" class="btn-secondary" style="font-size:12.5px; padding:6px 12px; text-decoration:none;">
          Export Portfolio (PDF)
        </a>
        <a href="api/export.php?type=tasks" class="btn-secondary" style="font-size:12.5px; padding:6px 12px; text-decoration:none;">
          Export Tasks (CSV)
        </a>
        <a href="api/export.php?type=timelog" class="btn-secondary" style="font-size:12.5px; padding:6px 12px; text-decoration:none;">
          Export Time Logs (CSV)
        </a>
      </div>
    </div>

    <div class="subhead-label" id="reportsSectionLabel">ALL REPORTS</div>

    <!-- Reports Grid Cards -->
    <div class="reports-cards-grid">
      
      <!-- Card 1: Upcoming Deadlines & Milestones -->
      <div class="report-card" data-cat="progress">
        <div class="report-card-head">
          <h3 class="report-card-title">Upcoming Deadlines</h3>
          <a href="calendar.php" style="color:var(--brand-primary); text-decoration:none; font-size:13px; font-weight:600;">Calendar →</a>
        </div>
        <p class="report-card-desc">Scheduled task deadlines and upcoming deliverable dates.</p>

        <?php if (!empty($milestones)): ?>
          <div style="display:flex; flex-direction:column; gap:8px;">
            <?php foreach ($milestones as $m): ?>
              <div class="deadline-item">
                <div>
                  <div class="deadline-name"><?= htmlspecialchars($m['task_name']); ?></div>
                  <div class="deadline-project"><?= htmlspecialchars($m['project_name'] ?? 'General'); ?></div>
                </div>
                <div style="display:flex; align-items:center; gap:8px;">
                  <span class="deadline-badge" style="background:var(--bg-surface); border:1px solid var(--border-base); color:var(--text-secondary);">
                    <?= htmlspecialchars($m['due_date']); ?>
                  </span>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div style="text-align:center; padding:24px 16px; background:var(--bg-subtle); border-radius:var(--radius-sm); border:1px solid var(--border-base); color:var(--text-muted); font-size:13px;">
            No upcoming deadlines scheduled. Set due dates on tasks to populate milestone reports.
          </div>
        <?php endif; ?>
      </div>

      <!-- Card 2: Task Status Distribution -->
      <div class="report-card" data-cat="progress">
        <div class="report-card-head">
          <h3 class="report-card-title">Task Completion Progress</h3>
          <a href="assignments.php" style="color:var(--brand-primary); text-decoration:none; font-size:13px; font-weight:600;">All Tasks →</a>
        </div>
        <p class="report-card-desc">Breakdown of tasks across workflow states.</p>

        <?php if ($totalTasks > 0): ?>
          <div style="margin-bottom:14px;">
            <div style="display:flex; justify-content:space-between; font-size:13px; font-weight:600; margin-bottom:6px;">
              <span style="color:var(--text-secondary);">Completed: <?= $doneTasks; ?> of <?= $totalTasks; ?></span>
              <span style="color:var(--text-primary); font-weight:700;"><?= $taskCompletionPct; ?>%</span>
            </div>
            <div style="width:100%; height:8px; background:var(--bg-subtle); border:1px solid var(--border-base); border-radius:var(--radius-xs); overflow:hidden;">
              <div style="height:100%; width:<?= $taskCompletionPct; ?>%; background:var(--brand-primary); border-radius:var(--radius-xs); transition:width 0.3s ease;"></div>
            </div>
          </div>

          <div style="display:grid; grid-template-columns:repeat(4, 1fr); gap:8px;">
            <div style="padding:10px 8px; background:var(--bg-subtle); border:1px solid var(--border-base); border-radius:var(--radius-sm); text-align:center;">
              <div style="font-size:16px; font-weight:700; color:var(--text-primary);"><?= $openTasks; ?></div>
              <div style="font-size:10.5px; font-weight:600; color:var(--text-muted); text-transform:uppercase;">Open</div>
            </div>
            <div style="padding:10px 8px; background:var(--bg-subtle); border:1px solid var(--border-base); border-radius:var(--radius-sm); text-align:center;">
              <div style="font-size:16px; font-weight:700; color:var(--brand-primary);"><?= $inProgressTasks; ?></div>
              <div style="font-size:10.5px; font-weight:600; color:var(--text-muted); text-transform:uppercase;">In Progress</div>
            </div>
            <div style="padding:10px 8px; background:var(--bg-subtle); border:1px solid var(--border-base); border-radius:var(--radius-sm); text-align:center;">
              <div style="font-size:16px; font-weight:700; color:var(--status-high-text);"><?= $reviewTasks; ?></div>
              <div style="font-size:10.5px; font-weight:600; color:var(--text-muted); text-transform:uppercase;">Review</div>
            </div>
            <div style="padding:10px 8px; background:var(--bg-subtle); border:1px solid var(--border-base); border-radius:var(--radius-sm); text-align:center;">
              <div style="font-size:16px; font-weight:700; color:var(--status-done-text);"><?= $doneTasks; ?></div>
              <div style="font-size:10.5px; font-weight:600; color:var(--text-muted); text-transform:uppercase;">Done</div>
            </div>
          </div>
        <?php else: ?>
          <div style="text-align:center; padding:24px 16px; background:var(--bg-subtle); border-radius:var(--radius-sm); border:1px solid var(--border-base); color:var(--text-muted); font-size:13px;">
            No tasks created yet. Add tasks to see status progress.
          </div>
        <?php endif; ?>
      </div>

      <!-- Card 3: Objectives & Key Deliverables -->
      <div class="report-card" data-cat="goals">
        <div class="report-card-head">
          <h3 class="report-card-title">Key Objectives (OKRs)</h3>
          <a href="goals.php" style="color:var(--brand-primary); text-decoration:none; font-size:13px; font-weight:600;">Manage OKRs →</a>
        </div>
        <p class="report-card-desc">Progress toward key project milestones and targets.</p>

        <?php if (!empty($goals)): ?>
          <div style="display:flex; flex-direction:column; gap:10px;">
            <?php foreach ($goals as $g): 
              $target = max(1, (int)$g['target_value']);
              $curr = min($target, (int)$g['current_value']);
              $pct = min(100, round(($curr / $target) * 100));
            ?>
              <div>
                <div style="display:flex; justify-content:space-between; font-size:12.5px; font-weight:600; margin-bottom:4px;">
                  <span style="color:var(--text-primary);"><?= htmlspecialchars($g['title']); ?></span>
                  <span style="color:var(--text-secondary);"><?= $curr; ?> / <?= $target; ?> (<?= $pct; ?>%)</span>
                </div>
                <div style="width:100%; height:6px; background:var(--bg-subtle); border:1px solid var(--border-base); border-radius:var(--radius-xs); overflow:hidden;">
                  <div style="height:100%; width:<?= $pct; ?>%; background:var(--brand-primary); border-radius:var(--radius-xs);"></div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div style="text-align:center; padding:24px 16px; background:var(--bg-subtle); border-radius:var(--radius-sm); border:1px solid var(--border-base); color:var(--text-muted); font-size:13px;">
            No objectives created yet. Set targets in Objectives &amp; OKRs to track key results.
          </div>
        <?php endif; ?>
      </div>

      <!-- Card 4: Time Logged on Tasks -->
      <div class="report-card" data-cat="time">
        <div class="report-card-head">
          <h3 class="report-card-title">Time Logged on Tasks</h3>
          <a href="analytics.php" style="color:var(--brand-primary); text-decoration:none; font-size:13px; font-weight:600;">View Logs →</a>
        </div>
        <p class="report-card-desc">Total logged work and study hours from recorded activities.</p>
        
        <div style="display:flex; align-items:center; gap:14px; margin-top:6px;">
          <div style="flex:1; background:var(--bg-subtle); border:1px solid var(--border-base); padding:16px; border-radius:var(--radius-sm); text-align:center;">
            <div style="font-size:24px; font-weight:700; color:var(--text-primary);"><?= $totalHours; ?>h <?= $remMins; ?>m</div>
            <div style="font-size:11.5px; font-weight:600; color:var(--text-secondary); margin-top:2px;">Total Time Logged</div>
          </div>
          <div style="flex:1; background:var(--bg-subtle); border:1px solid var(--border-base); padding:16px; border-radius:var(--radius-sm); text-align:center;">
            <div style="font-size:24px; font-weight:700; color:var(--brand-primary);"><?= $weekHours; ?>h <?= $weekRemMins; ?>m</div>
            <div style="font-size:11.5px; font-weight:600; color:var(--text-secondary); margin-top:2px;">Logged This Week</div>
          </div>
        </div>
      </div>

    </div>

  </main>
</div>

<script src="assets/js/app.js"></script>
<script>
window.filterReports = function(cat, btn) {
  document.querySelectorAll('.reports-tab-nav .pill-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');

  const label = document.getElementById('reportsSectionLabel');
  if (label) {
    label.textContent = (cat === 'all') ? 'ALL REPORTS' : cat.toUpperCase() + ' REPORTS';
  }

  const cards = document.querySelectorAll('.reports-cards-grid .report-card');
  cards.forEach(c => {
    if (cat === 'all') {
      c.style.display = 'block';
    } else {
      const cardCat = c.getAttribute('data-cat') || '';
      c.style.display = (cardCat === cat) ? 'block' : 'none';
    }
  });

  if (typeof showToast === 'function') {
    showToast(`Showing ${btn.textContent.trim()} reports`);
  }
};
</script>
</body>
</html>
