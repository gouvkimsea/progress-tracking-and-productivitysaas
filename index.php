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

$completedStreakDays = 0;
foreach ($weeklyStreaks as $ws) {
    if (!empty($ws['is_completed'])) $completedStreakDays++;
}

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

      <div class="card stat-card" style="display:flex; flex-direction:column; justify-content:space-between;">
        <div>
          <div class="stat-head" style="display:flex; align-items:center; justify-content:space-between;">
            <div style="display:flex; align-items:center; gap:8px;">
              <span class="dash" style="background:var(--brand-primary)"></span>
              <span>Focus timer</span>
            </div>
            <span id="pomoSessionsBadge" class="badge badge-neutral">0 completed today</span>
          </div>

          <!-- Pomodoro Mode Tabs -->
          <div class="pomo-mode-tabs">
            <button type="button" class="pomo-tab active" data-mode="focus" data-mins="25">25m Focus</button>
            <button type="button" class="pomo-tab" data-mode="short" data-mins="5">5m Break</button>
            <button type="button" class="pomo-tab" data-mode="long" data-mins="15">15m Long</button>
          </div>

          <!-- Timer Display & Controls -->
          <div style="display:flex; align-items:center; justify-content:space-between; margin-top:16px;">
            <div>
              <div id="timerDisplay" style="font-size:32px; font-weight:700; letter-spacing:-0.03em; color:var(--text-primary); font-family:ui-monospace, SFMono-Regular, Menlo, monospace; line-height:1;">25:00</div>
              <div style="font-size:11.5px; color:var(--text-secondary); margin-top:6px;">Total study: <b id="statTimeVal"><?= (int)$stats['study_hours']; ?>h <?= (int)$stats['study_minutes']; ?>m</b></div>
            </div>
            <div style="display:flex; gap:6px;">
              <button id="btnToggleTimer" class="btn btn-primary" style="padding:6px 14px; font-size:12px;">Start</button>
              <button id="btnResetTimer" class="btn btn-secondary" title="Reset Session" style="padding:6px 10px; font-size:12px;">Reset</button>
            </div>
          </div>
        </div>

        <!-- Mini Progress Bar -->
        <div style="margin-top:16px; background:var(--border-base); border-radius:var(--radius-xs); height:4px; overflow:hidden;">
          <div id="pomoProgressBar" style="width:0%; height:100%; background:var(--brand-primary); transition:width 0.3s ease;"></div>
        </div>
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
        <div class="week-days" id="weekDays"></div>

        <div class="streak-foot">
          <div class="fi"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg> Current: <b id="streakCount"><?= (int)$completedStreakDays; ?></b>/7</div>
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

<script src="assets/js/app.js"></script>
</body>
</html>
