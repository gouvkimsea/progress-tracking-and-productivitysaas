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
        <div class="stat-head"><span class="dash" style="background:var(--purple)"></span><span>Learning streak</span></div>
        <div class="stat-value-row"><span class="stat-value" id="statStreakVal"><?= (int)$stats['learning_streak']; ?></span></div>
        <div class="stat-delta-label"><span class="stat-delta up">+<?= (int)$stats['streak_delta']; ?> days</span> vs last week</div>
        <div class="stat-sub">
          <div><p class="k">Longest streak</p><p class="v" id="longestStreakVal"><?= (int)$stats['longest_streak']; ?> days</p></div>
          <div><p class="k">Missed days</p><p class="v" id="missedDaysVal"><?= (int)$stats['missed_days']; ?> day</p></div>
          <div><p class="k">Inactive</p><p class="v" id="inactiveVal"><?= (int)$stats['inactive_pct']; ?>%</p></div>
        </div>
        <div class="sparkbars" id="bars-streak"></div>
        <button class="view-link" data-scroll="timeline">View all details
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
        </button>
      </div>

      <div class="card stat-card">
        <div class="stat-head"><span class="dash" style="background:var(--orange)"></span><span>Course progress</span></div>
        <div class="stat-value-row"><span class="stat-value" id="statProgressVal"><?= round($stats['course_progress_pct']); ?>%</span></div>
        <div class="stat-delta-label"><span class="stat-delta up">+<?= number_format($stats['progress_delta_pct'], 1); ?>%</span> this week</div>
        <div class="stat-sub">
          <div><p class="k">This week</p><p class="v" id="weeklyLessonsCurr"><?= (int)$stats['weekly_lessons_current']; ?> lessons</p></div>
          <div><p class="k">Last week</p><p class="v" id="weeklyLessonsLast"><?= (int)$stats['weekly_lessons_last']; ?> lessons</p></div>
        </div>
        <div class="sparkbars" id="bars-progress"></div>
        <button class="view-link" data-scroll="timeline">View all details
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
        </button>
      </div>

      <div class="card stat-card" style="display:flex; flex-direction:column; justify-content:space-between;">
        <div>
          <div class="stat-head" style="display:flex; align-items:center; justify-content:space-between;">
            <div style="display:flex; align-items:center; gap:8px;">
              <span class="dash" style="background:var(--blue)"></span>
              <span>Focus timer</span>
            </div>
            <span id="pomoSessionsBadge" class="badge badge-neutral">0 completed today</span>
          </div>

          <!-- Pomodoro Mode Tabs -->
          <div class="pomo-mode-tabs" style="display:flex; gap:4px; margin-top:10px; background:var(--panel-bg); padding:3px; border-radius:6px; border:1px solid var(--border);">
            <button type="button" class="pomo-tab active" data-mode="focus" data-mins="25" style="flex:1; border:none; background:var(--blue); color:#fff; font-size:11.5px; font-weight:600; padding:5px 0; border-radius:4px; cursor:pointer;">25m Focus</button>
            <button type="button" class="pomo-tab" data-mode="short" data-mins="5" style="flex:1; border:none; background:transparent; color:var(--muted); font-size:11.5px; font-weight:500; padding:5px 0; border-radius:4px; cursor:pointer;">5m Break</button>
            <button type="button" class="pomo-tab" data-mode="long" data-mins="15" style="flex:1; border:none; background:transparent; color:var(--muted); font-size:11.5px; font-weight:500; padding:5px 0; border-radius:4px; cursor:pointer;">15m Long</button>
          </div>

          <!-- Timer Display & Controls -->
          <div style="display:flex; align-items:center; justify-content:space-between; margin-top:16px;">
            <div>
              <div id="timerDisplay" style="font-size:32px; font-weight:700; letter-spacing:-0.03em; color:var(--ink); font-family:ui-monospace, SFMono-Regular, Menlo, monospace; line-height:1;">25:00</div>
              <div style="font-size:11.5px; color:var(--muted); margin-top:6px;">Total study: <b id="statTimeVal"><?= (int)$stats['study_hours']; ?>h <?= (int)$stats['study_minutes']; ?>m</b></div>
            </div>
            <div style="display:flex; gap:6px;">
              <button id="btnToggleTimer" class="btn btn-primary" style="padding:6px 14px; font-size:12px;">Start</button>
              <button id="btnResetTimer" class="btn btn-secondary" title="Reset Session" style="padding:6px 10px; font-size:12px;">Reset</button>
            </div>
          </div>
        </div>

        <!-- Mini Progress Bar -->
        <div style="margin-top:16px; background:var(--border); border-radius:3px; height:4px; overflow:hidden;">
          <div id="pomoProgressBar" style="width:0%; height:100%; background:var(--blue); transition:width 0.3s ease;"></div>
        </div>
      </div>

    </section>

    <!-- Timeline Heatmap -->
    <section class="card timeline-card" id="timeline">
      <div class="timeline-head">
        <h2>Learning activity timeline</h2>
        <div style="position:relative;">
          <button class="filter-btn" id="filterBtn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
            Filter view
          </button>
          <div class="filter-menu" id="filterMenu">
            <button class="sel" data-cat="all">All courses</button>
            <button data-cat="Design">Design</button>
            <button data-cat="Programming">JavaScript</button>
            <button data-cat="Design">Photoshop</button>
            <button data-cat="Data Science">Python for Data</button>
          </div>
        </div>
      </div>

      <div class="heatmap-scroll">
        <div class="heatmap-months" id="heatmap"></div>
      </div>

      <div class="timeline-foot">
        <div class="fi"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg> Total hours: <b id="totalHoursFooter"><?= (int)$stats['study_hours']; ?>h</b></div>
        <div class="fi"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg> Lessons finished: <b id="totalLessonsFooter"><?= (int)$stats['weekly_lessons_current']; ?></b></div>
        <div class="fi"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/><path d="M4 22h16"/><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"/><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"/><path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"/></svg> Quiz score: <b>100%</b></div>
      </div>
    </section>

    <!-- Bottom 3 Grid -->
    <section class="bottom-grid">

      <!-- Weekly streak -->
      <div class="card card-block">
        <div class="card-block-head">
          <div class="card-title">
            <div class="ic" style="background:var(--purple-light); color:var(--purple-deep)">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 3.5Z"/></svg>
            </div>
            <span>Weekly streak</span>
          </div>
          <button class="kebab" title="Options">
            <svg viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2"><circle cx="12" cy="5" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="12" cy="19" r="1"/></svg>
          </button>
        </div>

        <div class="streak-big" id="streakBig"><?= (int)$stats['learning_streak']; ?> days</div>
        <div class="week-days" id="weekDays"></div>

        <div class="streak-foot">
          <div class="fi"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg> Current: <b id="streakCount">4</b>/7</div>
          <div class="fi"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg> Goal: <b>7 days</b></div>
        </div>
      </div>

      <!-- Learning progress -->
      <div class="card card-block">
        <div class="card-block-head">
          <div class="card-title">
            <div class="ic" style="background:var(--orange-light); color:var(--orange)">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
            </div>
            <span>Learning progress</span>
          </div>
          <button class="kebab" title="Options">
            <svg viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2"><circle cx="12" cy="5" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="12" cy="19" r="1"/></svg>
          </button>
        </div>

        <div id="lessonList"></div>
      </div>

      <!-- Skill breakdown -->
      <div class="card card-block skill-card">
        <div class="card-block-head">
          <div class="card-title">
            <div class="ic" style="background:var(--blue-light); color:var(--blue)">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 2a7 7 0 0 0 0 14 7 7 0 0 0 0-14z"/></svg>
            </div>
            <span>Skill breakdown</span>
          </div>
          <button class="kebab" title="Options">
            <svg viewBox="0 0 24 24" stroke="currentColor" fill="none" stroke-width="2"><circle cx="12" cy="5" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="12" cy="19" r="1"/></svg>
          </button>
        </div>

        <div class="radar-wrap" id="radarWrap"></div>
      </div>

    </section>
  </main>

</div>

<script src="assets/js/app.js"></script>
</body>
</html>
