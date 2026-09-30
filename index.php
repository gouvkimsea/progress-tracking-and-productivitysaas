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
if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Fetch Stats
$stmtStats = $db->prepare("SELECT * FROM user_stats WHERE user_id = :uid");
$stmtStats->execute(['uid' => $userId]);
$stats = $stmtStats->fetch() ?: [
    'learning_streak' => 0,
    'longest_streak' => 0,
    'missed_days' => 0,
    'inactive_pct' => 0,
    'course_progress_pct' => 0.00,
    'study_hours' => 0,
    'study_minutes' => 0
];

// Fetch Weekly Streaks
$stmtStreaks = $db->prepare("SELECT * FROM weekly_streaks WHERE user_id = :uid ORDER BY day_index ASC");
$stmtStreaks->execute(['uid' => $userId]);
$weeklyStreaks = $stmtStreaks->fetchAll();

if (count($weeklyStreaks) < 7) {
    $dayNames = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    $existingIndices = array_column($weeklyStreaks, 'day_index');
    $stmtInsertDay = $db->prepare("INSERT INTO weekly_streaks (user_id, day_index, day_name, is_completed) VALUES (:uid, :didx, :dname, 0)");
    foreach ($dayNames as $idx => $name) {
        if (!in_array($idx, $existingIndices)) {
            $stmtInsertDay->execute(['uid' => $userId, 'didx' => $idx, 'dname' => $name]);
        }
    }
    $stmtStreaks->execute(['uid' => $userId]);
    $weeklyStreaks = $stmtStreaks->fetchAll();
}

$completedStreakDays = 0;
foreach ($weeklyStreaks as $ws) {
    if (!empty($ws['is_completed'])) $completedStreakDays++;
}

if ($completedStreakDays > 0 && (int)$stats['learning_streak'] === 0) {
    $stats['learning_streak'] = $completedStreakDays;
    $db->prepare("UPDATE user_stats SET learning_streak = :s WHERE user_id = :uid")
       ->execute(['s' => $completedStreakDays, 'uid' => $userId]);
}

$todayDayIndex = (int)date('N') - 1; // 0=Mon, ..., 6=Sun

// Fetch today's journal & todos
$todayDate = date('Y-m-d');
$stmtTodayJ = $db->prepare("SELECT * FROM daily_journal WHERE user_id = :uid AND entry_date = :dt");
$stmtTodayJ->execute(['uid' => $userId, 'dt' => $todayDate]);
$todayJournal = $stmtTodayJ->fetch();

$stmtTodayTodos = $db->prepare("SELECT COUNT(*) AS total, SUM(CASE WHEN is_completed = 1 THEN 1 ELSE 0 END) AS done FROM journal_todos WHERE user_id = :uid AND todo_date = :dt");
$stmtTodayTodos->execute(['uid' => $userId, 'dt' => $todayDate]);
$todayTodoCounts = $stmtTodayTodos->fetch();
$todayTodosTotal = (int)($todayTodoCounts['total'] ?? 0);
$todayTodosDone = (int)($todayTodoCounts['done'] ?? 0);

$pageTitle = 'Mindrift — Dashboard';
include __DIR__ . '/includes/head.php';
?>
</head>
<body>

<div class="app" id="app">

  <?php include __DIR__ . '/includes/sidebar.php'; ?>

  <!-- ============ MAIN ============ -->
  <main class="main">
    <?php include __DIR__ . '/includes/header.php'; ?>

    <!-- Stat cards -->
    <section class="stat-grid">

      <div class="card stat-card">
        <div class="stat-head"><span class="dash" style="background:var(--brand-primary)"></span><span>Learning streak</span></div>
        <div class="stat-value-row"><span class="stat-value" id="statStreakVal"><?= (int)$stats['learning_streak']; ?></span></div>
        <div class="stat-delta-label"><span class="stat-delta up">+<?= (int)$stats['streak_delta']; ?> days</span> vs last week</div>
        <div class="stat-sub">
          <div><p class="k">Longest streak</p><p class="v" id="longestStreakVal"><?= (int)$stats['longest_streak']; ?> days</p></div>
          <div><p class="k">Missed days</p><p class="v" id="missedDaysVal"><?= (int)$stats['missed_days']; ?> day</p></div>
          <div><p class="k">Inactive</p><p class="v" id="inactiveVal"><?= (int)$stats['inactive_pct']; ?>%</p></div>
        </div>
        <div class="sparkbars" id="bars-streak"></div>
        <button class="view-link" data-scroll="timeline">View all details
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
        </button>
      </div>

      <div class="card stat-card">
        <div class="stat-head"><span class="dash" style="background:var(--status-high-border)"></span><span>Course progress</span></div>
        <div class="stat-value-row"><span class="stat-value" id="statProgressVal"><?= round($stats['course_progress_pct']); ?>%</span></div>
        <div class="stat-delta-label"><span class="stat-delta up">+<?= number_format($stats['progress_delta_pct'], 1); ?>%</span> this week</div>
        <div class="stat-sub">
          <div><p class="k">This week</p><p class="v" id="weeklyLessonsCurr"><?= (int)$stats['weekly_lessons_current']; ?> lessons</p></div>
          <div><p class="k">Last week</p><p class="v" id="weeklyLessonsLast"><?= (int)$stats['weekly_lessons_last']; ?> lessons</p></div>
        </div>
        <div class="sparkbars" id="bars-progress"></div>
        <button class="view-link" data-scroll="timeline">View all details
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
        </button>
      </div>

      <div class="card stat-card focus-timer-card" id="focusTimerCard" style="display:flex; flex-direction:column; justify-content:space-between;">
        <div class="focus-timer-content">
          <div class="stat-head focus-timer-header" style="display:flex; align-items:center; justify-content:space-between;">
            <div style="display:flex; align-items:center; gap:8px;">
              <span class="dash" style="background:var(--brand-primary)"></span>
              <span class="focus-timer-title">Focus timer</span>
            </div>
            <div style="display:flex; align-items:center; gap:8px;">
              <span id="pomoSessionsBadge" class="badge badge-neutral">0 completed today</span>
              <button type="button" class="btn-focus-fullscreen" id="btnFocusFullscreen" title="Full screen Focus Mode" aria-label="Full screen Focus Mode">
                <svg class="ic-expand" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"/></svg>
                <svg class="ic-compress" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;"><path d="M8 3v3a2 2 0 0 1-2 2H3m18 0h-3a2 2 0 0 1-2-2V3m0 18v-3a2 2 0 0 1 2-2h3M3 16h3a2 2 0 0 1 2 2v3"/></svg>
                <span class="fs-label" style="display:none;">Exit (Esc)</span>
              </button>
            </div>
          </div>

          <!-- Pomodoro Mode Tabs -->
          <div class="pomo-mode-tabs">
            <button type="button" class="pomo-tab active" data-mode="focus" data-mins="25">25m Focus</button>
            <button type="button" class="pomo-tab" data-mode="short" data-mins="5">5m Break</button>
            <button type="button" class="pomo-tab" data-mode="long" data-mins="15">15m Long</button>
          </div>

          <!-- Timer Display & Controls -->
          <div class="focus-timer-body" style="display:flex; align-items:center; justify-content:space-between; margin-top:16px;">
            <div class="focus-timer-time-wrap">
              <div id="timerDisplay" style="font-size:32px; font-weight:700; letter-spacing:-0.03em; color:var(--text-primary); font-family:ui-monospace, SFMono-Regular, Menlo, monospace; line-height:1;">25:00</div>
              <div class="focus-timer-subtext" style="font-size:11.5px; color:var(--text-secondary); margin-top:6px;">Total study: <b id="statTimeVal"><?= (int)$stats['study_hours']; ?>h <?= (int)$stats['study_minutes']; ?>m</b></div>
            </div>
            <div class="focus-timer-btn-wrap" style="display:flex; gap:6px;">
              <button id="btnToggleTimer" class="btn btn-primary" style="padding:6px 14px; font-size:12px;">Start</button>
              <button id="btnResetTimer" class="btn btn-secondary" title="Reset Session" style="padding:6px 10px; font-size:12px;">Reset</button>
            </div>
          </div>
        </div>

        <!-- Mini Progress Bar -->
        <div class="focus-timer-progress-wrap" style="margin-top:16px; background:var(--border-base); border-radius:var(--radius-xs); height:4px; overflow:hidden;">
          <div id="pomoProgressBar" style="width:0%; height:100%; background:var(--brand-primary); transition:width 0.3s ease;"></div>
        </div>
        <div class="focus-timer-zen-quote" style="display:none;">
          <span>Deep focus mode · Distractions muted</span>
        </div>
      </div>

    </section>

    <!-- Daily Reflection & To-Do Check-in -->
    <section class="card" style="margin-bottom: 24px; padding: 18px 22px; display: flex; align-items: center; justify-content: space-between; gap: 20px; flex-wrap: wrap; background: linear-gradient(135deg, var(--bg-surface) 0%, var(--bg-subtle) 100%); border-left: 4px solid var(--brand-primary); box-shadow: 0 2px 10px rgba(0,0,0,0.02);">
      <div style="display: flex; align-items: center; gap: 16px;">
        <div style="width: 44px; height: 44px; border-radius: var(--radius-sm); background: rgba(59, 130, 246, 0.12); color: var(--brand-primary); display: flex; align-items: center; justify-content: center; font-size: 22px; flex-shrink:0;">
          <?= $todayJournal ? '📔' : '✨'; ?>
        </div>
        <div>
          <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
            <h3 style="margin: 0; font-size: 15px; font-weight: 700; color: var(--text-primary);">
              <?= $todayJournal ? "Today's Reflection Logged" : "Daily Reflection & To-Do Check-in"; ?>
            </h3>
            <?php if ($todayJournal): ?>
              <span class="badge" style="background: rgba(16, 185, 129, 0.15); color: #10b981; font-weight: 700; font-size: 11.5px;">
                Rated <?= (int)$todayJournal['rating']; ?>/5 (<?= htmlspecialchars($todayJournal['mood_label']); ?>)
              </span>
            <?php else: ?>
              <span class="badge badge-neutral" style="font-size: 11px;">Pending Today</span>
            <?php endif; ?>
          </div>
          <p style="margin: 4px 0 0; font-size: 12.5px; color: var(--text-secondary); line-height: 1.5;">
            <?php if ($todayJournal): ?>
              <?= !empty($todayJournal['accomplishments']) ? htmlspecialchars(substr($todayJournal['accomplishments'], 0, 90)) . '...' : 'Your daily journal entry is saved.'; ?>
              &bull; <b><?= $todayTodosDone; ?>/<?= $todayTodosTotal; ?></b> daily to-dos completed today.
            <?php else: ?>
              Rate how your day went (1 to 5), record your accomplishments, and stay on top of your daily tasks.
            <?php endif; ?>
          </p>
        </div>
      </div>

      <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
        <?php if (!$todayJournal): ?>
          <div style="display: flex; align-items: center; gap: 5px; background: var(--bg-surface); padding: 4px 8px; border-radius: var(--radius-sm); border: 1px solid var(--border-base);">
            <span style="font-size: 11.5px; font-weight: 600; color: var(--text-muted); margin-right: 2px;">Rate day:</span>
            <a href="journal.php?action=quiz&rate=1" class="btn" style="padding: 3px 7px; font-size: 11.5px; background: transparent; border: 1px solid var(--border-base); text-decoration: none;" title="1 - Rough Day">1 🌧️</a>
            <a href="journal.php?action=quiz&rate=2" class="btn" style="padding: 3px 7px; font-size: 11.5px; background: transparent; border: 1px solid var(--border-base); text-decoration: none;" title="2 - Slow Going">2 ⛅</a>
            <a href="journal.php?action=quiz&rate=3" class="btn" style="padding: 3px 7px; font-size: 11.5px; background: transparent; border: 1px solid var(--border-base); text-decoration: none;" title="3 - Steady">3 🌤️</a>
            <a href="journal.php?action=quiz&rate=4" class="btn" style="padding: 3px 7px; font-size: 11.5px; background: transparent; border: 1px solid var(--border-base); text-decoration: none;" title="4 - Great">4 😊</a>
            <a href="journal.php?action=quiz&rate=5" class="btn" style="padding: 3px 7px; font-size: 11.5px; background: var(--brand-primary); color: #fff; font-weight: 700; border: none; text-decoration: none;" title="5 - Phenomenal">5 🚀</a>
          </div>
        <?php endif; ?>
        <a href="journal.php<?= $todayJournal ? '' : '?action=quiz'; ?>" class="btn <?= $todayJournal ? 'btn-secondary' : 'btn-primary'; ?>" style="font-size: 12.5px; padding: 7px 14px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
          <span><?= $todayJournal ? 'Open Daily Journal' : 'Take Daily Quiz ✨'; ?></span>
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
        </a>
      </div>
    </section>

    <!-- Timeline Heatmap -->
    <section class="card timeline-card" id="timeline">
      <div class="timeline-head">
        <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--text-secondary);"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
          <h2 style="margin:0;">Learning activity timeline</h2>
          <span class="badge badge-neutral" style="font-size:11px; font-weight:500;">Past 365 days</span>
        </div>
        <div style="display:flex; align-items:center; gap:12px;">
          <span id="heatmapTotalSummary" style="font-size:12px; color:var(--text-secondary); font-weight:500;"></span>
          <button type="button" class="btn-fullscreen-toggle" id="btnTimelineFullscreen" title="Toggle Fullscreen" aria-label="Toggle Fullscreen">
            <svg class="ic-expand" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"/></svg>
            <svg class="ic-compress" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;"><path d="M8 3v3a2 2 0 0 1-2 2H3m18 0h-3a2 2 0 0 1-2-2V3m0 18v-3a2 2 0 0 1 2-2h3M3 16h3a2 2 0 0 1 2 2v3"/></svg>
            <span class="btn-text">Fullscreen</span>
          </button>
        </div>
      </div>

      <div class="heatmap-scroll">
        <div id="heatmap"></div>
      </div>

      <div class="timeline-foot">
        <div style="display:flex; align-items:center; gap:18px; flex-wrap:wrap;">
          <div class="fi"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg> Total hours: <b id="totalHoursFooter"><?= (int)$stats['study_hours']; ?>h</b></div>
          <div class="fi"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg> Lessons finished: <b id="totalLessonsFooter"><?= (int)$stats['weekly_lessons_current']; ?></b></div>
          <div class="fi"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 2a10 10 0 1 0 10 10"/></svg> Overall progress: <b id="totalProgressFooter"><?= round($stats['course_progress_pct']); ?>%</b></div>
        </div>
        <div class="gh-legend">
          <span>Less</span>
          <span class="gh-legend-cell l0"></span>
          <span class="gh-legend-cell l1"></span>
          <span class="gh-legend-cell l2"></span>
          <span class="gh-legend-cell l3"></span>
          <span class="gh-legend-cell l4"></span>
          <span>More</span>
        </div>
      </div>
    </section>

    <!-- Bottom 3 Grid -->
    <section class="bottom-grid">

      <!-- Weekly streak -->
      <div class="card card-block">
        <div class="card-block-head">
          <div class="card-title">
            <div class="ic" style="background:var(--bg-subtle); color:var(--brand-primary)">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 3.5Z"/></svg>
            </div>
            <span>Weekly streak</span>
          </div>
        </div>

        <div class="streak-big" id="streakBig"><?= (int)$stats['learning_streak']; ?> days</div>
        <div class="week-days" id="weekDays">
          <?php foreach ($weeklyStreaks as $ws): 
            $isDone = !empty($ws['is_completed']);
            $didx = (int)$ws['day_index'];
            $dname = htmlspecialchars($ws['day_name']);
            $isToday = ($didx === $todayDayIndex);
          ?>
            <div class="day-col <?= $isToday ? 'is-today' : ''; ?>" title="<?= $dname; ?><?= $isToday ? ' (Today)' : ''; ?>">
              <span class="day-name">
                <?= $dname; ?><?= $isToday ? ' •' : ''; ?>
              </span>
              <div class="day-circle <?= $isDone ? 'checked' : ''; ?> <?= $isToday ? 'today-ring' : ''; ?>" 
                   role="button" 
                   tabindex="0" 
                   data-day-index="<?= $didx; ?>"
                   data-day-name="<?= $dname; ?>"
                   aria-label="<?= $dname; ?>: <?= $isDone ? 'Completed' : 'Not completed'; ?>"
                   onclick="window.toggleStreakDay(<?= $didx; ?>, this)"
                   onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();window.toggleStreakDay(<?= $didx; ?>, this);}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M5 12.5 10 17l9-10"/>
                </svg>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <div class="streak-foot">
          <div class="fi"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 3.5Z"/></svg> Current: <b id="streakCount"><?= (int)$completedStreakDays; ?></b>/7</div>
          <div class="fi"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg> Goal: <b>7 days</b></div>
        </div>
      </div>

      <!-- Learning progress -->
      <div class="card card-block">
        <div class="card-block-head">
          <div class="card-title">
            <div class="ic" style="background:var(--status-high-bg); color:var(--status-high-text)">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
            </div>
            <span>Learning progress</span>
          </div>
        </div>

        <div id="lessonList"></div>
      </div>

      <!-- Skill breakdown -->
      <div class="card card-block skill-card">
        <div class="card-block-head">
          <div class="card-title">
            <div class="ic" style="background:var(--bg-subtle); color:var(--brand-primary)">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 2a7 7 0 0 0 0 14 7 7 0 0 0 0-14z"/></svg>
            </div>
            <span>Skill breakdown</span>
          </div>
        </div>

        <div class="radar-wrap" id="radarWrap"></div>
      </div>

    </section>
  </main>

</div>

<script src="assets/js/app.js?v=<?= assetVersion(); ?>"></script>
</body>
</html>
