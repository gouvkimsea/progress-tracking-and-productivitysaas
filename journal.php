<?php
require_once __DIR__ . '/config/db.php';
startSecureSession();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$db = getDbConnection();
$userId = (int)$_SESSION['user_id'];
$today = date('Y-m-d');

// Fetch User Info
$stmtUser = $db->prepare("SELECT * FROM users WHERE id = :uid");
$stmtUser->execute(['uid' => $userId]);
$user = $stmtUser->fetch();

// Fetch today's entry
$stmtToday = $db->prepare("SELECT * FROM daily_journal WHERE user_id = :uid AND entry_date = :dt");
$stmtToday->execute(['uid' => $userId, 'dt' => $today]);
$todayEntry = $stmtToday->fetch();

// Fetch today's to-dos
$stmtTodos = $db->prepare("SELECT * FROM journal_todos WHERE user_id = :uid AND todo_date = :dt ORDER BY is_completed ASC, id ASC");
$stmtTodos->execute(['uid' => $userId, 'dt' => $today]);
$todayTodos = $stmtTodos->fetchAll();

// Fetch all journal entries (last 60 days)
$stmtEntries = $db->prepare("SELECT * FROM daily_journal WHERE user_id = :uid ORDER BY entry_date DESC LIMIT 60");
$stmtEntries->execute(['uid' => $userId]);
$journalEntries = $stmtEntries->fetchAll();

// Compute stats
$totalEntries = count($journalEntries);
$avgRating = 0;
$fiveStarCount = 0;
if ($totalEntries > 0) {
    $sum = 0;
    foreach ($journalEntries as $e) {
        $r = (int)$e['rating'];
        $sum += $r;
        if ($r === 5) $fiveStarCount++;
    }
    $avgRating = round($sum / $totalEntries, 1);
}

$todoTotal = count($todayTodos);
$todoCompleted = 0;
foreach ($todayTodos as $t) {
    if (!empty($t['is_completed'])) $todoCompleted++;
}
$todoPct = $todoTotal > 0 ? round(($todoCompleted / $todoTotal) * 100) : 0;

if (!function_exists('renderRatingStars')) {
    function renderRatingStars(int $rating): string {
        $rating = max(1, min(5, $rating));
        $html = '<span class="star-rating" title="' . $rating . ' out of 5 stars">';
        for ($i = 1; $i <= 5; $i++) {
            if ($i <= $rating) {
                $html .= '<span class="star filled">★</span>';
            } else {
                $html .= '<span class="star empty">☆</span>';
            }
        }
        $html .= '</span>';
        return $html;
    }
}

if (!function_exists('renderMoodBadge')) {
    function renderMoodBadge(int $rating, ?string $label = null): string {
        $map = [
            1 => ['label' => 'Rough Day', 'class' => 'mood-1', 'emoji' => '😞'],
            2 => ['label' => 'Slow Going', 'class' => 'mood-2', 'emoji' => '😐'],
            3 => ['label' => 'Steady & Solid', 'class' => 'mood-3', 'emoji' => '🙂'],
            4 => ['label' => 'Great Day', 'class' => 'mood-4', 'emoji' => '😊'],
            5 => ['label' => 'Phenomenal', 'class' => 'mood-5', 'emoji' => '🚀'],
        ];
        $rating = max(1, min(5, $rating));
        $info = $map[$rating] ?? $map[3];
        $displayLabel = !empty($label) ? $label : $info['label'];
        return '<span class="mood-pill ' . $info['class'] . '"><span class="mood-emoji">' . $info['emoji'] . '</span> ' . htmlspecialchars($displayLabel) . '</span>';
    }
}

$pageTitle = 'Mindrift — Daily Journal & To-Do Check-in';
include __DIR__ . '/includes/head.php';
?>
<style>
  .journal-page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 12px;
  }
  .journal-title-wrap h2 {
    margin: 0;
    font-size: 22px;
    font-weight: 700;
    letter-spacing: -0.02em;
    color: var(--text-primary);
  }
  .journal-subtitle {
    margin: 4px 0 0;
    font-size: 13.5px;
    color: var(--text-secondary);
  }

  /* Stat Grid */
  .journal-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 14px;
    margin-bottom: 24px;
  }
  .jstat-card {
    background: var(--bg-surface);
    border: 1px solid var(--border-base);
    border-radius: var(--radius-md);
    padding: 16px 18px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    transition: transform 0.15s ease, box-shadow 0.15s ease;
  }
  .jstat-card:hover {
    border-color: var(--brand-primary);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.04);
  }
  .jstat-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 8px;
  }
  .jstat-label {
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--text-muted);
  }
  .jstat-icon-wrap {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: var(--bg-subtle);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--brand-primary);
  }
  .jstat-val {
    font-size: 24px;
    font-weight: 700;
    color: var(--text-primary);
    display: flex;
    align-items: baseline;
    gap: 6px;
  }
  .jstat-sub {
    font-size: 12px;
    color: var(--text-secondary);
    margin-top: 4px;
  }

  /* 2-Column Split Layout */
  .journal-main-grid {
    display: grid;
    grid-template-columns: 1fr 1.25fr;
    gap: 20px;
    margin-bottom: 30px;
  }
  @media (max-width: 992px) {
    .journal-main-grid {
      grid-template-columns: 1fr;
    }
  }

  /* Card Containers */
  .jpanel {
    background: var(--bg-surface);
    border: 1px solid var(--border-base);
    border-radius: var(--radius-md);
    padding: 20px;
    display: flex;
    flex-direction: column;
  }
  .jpanel-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 1px solid var(--border-base);
  }
  .jpanel-title {
    font-size: 16px;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
  }

  /* To-Do Form & List */
  .todo-input-wrap {
    display: flex;
    gap: 8px;
    margin-bottom: 14px;
  }
  .todo-input {
    flex: 1;
    font-size: 13.5px;
  }
  .todo-filter-nav {
    display: flex;
    gap: 8px;
    margin-bottom: 12px;
  }
  .todo-filter-pill {
    font-size: 11.5px;
    font-weight: 600;
    padding: 3px 10px;
    border-radius: var(--radius-full);
    border: 1px solid var(--border-base);
    background: var(--bg-subtle);
    color: var(--text-secondary);
    cursor: pointer;
    transition: all 0.15s ease;
  }
  .todo-filter-pill.active {
    background: var(--brand-primary);
    color: #fff;
    border-color: var(--brand-primary);
  }
  .todo-list-wrap {
    display: flex;
    flex-direction: column;
    gap: 8px;
    max-height: 380px;
    overflow-y: auto;
    padding-right: 4px;
  }
  .todo-item-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border-radius: var(--radius-sm);
    background: var(--bg-subtle);
    border: 1px solid var(--border-base);
    transition: all 0.15s ease;
  }
  .todo-item-row:hover {
    border-color: var(--brand-primary);
    background: var(--bg-surface);
  }
  .todo-item-row.completed {
    opacity: 0.7;
    background: rgba(16, 185, 129, 0.04);
  }
  .todo-check {
    width: 17px;
    height: 17px;
    accent-color: var(--brand-primary);
    cursor: pointer;
    margin: 0;
  }
  .todo-text {
    flex: 1;
    font-size: 13.5px;
    color: var(--text-primary);
    line-height: 1.4;
    transition: all 0.15s ease;
  }
  .todo-item-row.completed .todo-text {
    text-decoration: line-through;
    color: var(--text-muted);
  }
  .btn-todo-del {
    background: none;
    border: none;
    color: var(--text-muted);
    font-size: 14px;
    cursor: pointer;
    padding: 2px 6px;
    border-radius: var(--radius-xs);
    opacity: 0;
    transition: all 0.15s ease;
  }
  .todo-item-row:hover .btn-todo-del {
    opacity: 1;
  }
  .btn-todo-del:hover {
    color: var(--status-urgent-text);
    background: var(--status-urgent-bg);
  }

  /* Today Journal Display */
  .today-journal-empty {
    text-align: center;
    padding: 30px 20px;
    background: var(--bg-subtle);
    border: 1px dashed var(--border-base);
    border-radius: var(--radius-sm);
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
  }
  .quick-rate-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 8px;
    width: 100%;
    margin: 14px 0;
  }
  .quick-rate-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
    padding: 10px 6px;
    background: var(--bg-surface);
    border: 1px solid var(--border-base);
    border-radius: var(--radius-sm);
    cursor: pointer;
    transition: all 0.15s ease;
  }
  .quick-rate-btn:hover {
    border-color: var(--brand-primary);
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.06);
  }
  .quick-rate-num {
    font-size: 18px;
    font-weight: 700;
    color: var(--text-primary);
  }
  .quick-rate-emoji {
    font-size: 20px;
  }
  .quick-rate-label {
    font-size: 10px;
    font-weight: 600;
    color: var(--text-secondary);
    text-align: center;
  }

  /* Rating & Mood Badges */
  .star-rating {
    display: inline-flex;
    gap: 2px;
    color: #f59e0b;
    font-size: 14px;
    vertical-align: middle;
  }
  .star.empty {
    color: var(--border-base);
  }
  .mood-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    font-weight: 600;
    padding: 3px 10px;
    border-radius: var(--radius-full);
  }
  .mood-5 { background: rgba(16, 185, 129, 0.12); color: #059669; border: 1px solid rgba(16, 185, 129, 0.3); }
  .mood-4 { background: rgba(59, 130, 246, 0.12); color: #2563eb; border: 1px solid rgba(59, 130, 246, 0.3); }
  .mood-3 { background: rgba(245, 158, 11, 0.12); color: #d97706; border: 1px solid rgba(245, 158, 11, 0.3); }
  .mood-2 { background: rgba(249, 115, 22, 0.12); color: #ea580c; border: 1px solid rgba(249, 115, 22, 0.3); }
  .mood-1 { background: rgba(239, 68, 68, 0.12); color: #dc2626; border: 1px solid rgba(239, 68, 68, 0.3); }

  /* Past Entries Timeline */
  .journal-timeline {
    display: flex;
    flex-direction: column;
    gap: 14px;
  }
  .jentry-card {
    background: var(--bg-surface);
    border: 1px solid var(--border-base);
    border-radius: var(--radius-md);
    padding: 18px 20px;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
  }
  .jentry-card:hover {
    border-color: var(--brand-primary);
    box-shadow: 0 4px 14px rgba(0,0,0,0.03);
  }
  .jentry-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 12px;
    flex-wrap: wrap;
    gap: 8px;
  }
  .jentry-date-group {
    display: flex;
    align-items: center;
    gap: 10px;
  }
  .jentry-date {
    font-size: 14px;
    font-weight: 700;
    color: var(--text-primary);
  }
  .jentry-relative {
    font-size: 11.5px;
    font-weight: 600;
    color: var(--text-muted);
    background: var(--bg-subtle);
    padding: 2px 7px;
    border-radius: var(--radius-xs);
  }
  .jentry-section {
    margin-bottom: 10px;
    font-size: 13.5px;
    line-height: 1.6;
  }
  .jentry-label {
    font-size: 11.5px;
    font-weight: 700;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.03em;
    margin-bottom: 3px;
    display: flex;
    align-items: center;
    gap: 5px;
  }

  /* Quiz Step Modal */
  .quiz-score-btn {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 14px;
    background: var(--bg-subtle);
    border: 2px solid var(--border-base);
    border-radius: var(--radius-sm);
    cursor: pointer;
    transition: all 0.15s ease;
    text-align: left;
    width: 100%;
  }
  .quiz-score-btn:hover, .quiz-score-btn.selected {
    border-color: var(--brand-primary);
    background: var(--bg-surface);
  }
  .quiz-score-btn.selected {
    box-shadow: 0 0 0 2px var(--brand-primary);
  }
  .quiz-num-badge {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: var(--bg-surface);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 15px;
    font-weight: 700;
    border: 1px solid var(--border-base);
    color: var(--text-primary);
  }
  .quiz-score-btn.selected .quiz-num-badge {
    background: var(--brand-primary);
    color: #fff;
    border-color: var(--brand-primary);
  }
</style>
</head>
<body>

<div class="app" id="app">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>

  <main class="main">
    <?php include __DIR__ . '/includes/header.php'; ?>

    <!-- Page Header -->
    <div class="journal-page-header">
      <div class="journal-title-wrap">
        <h2>Daily Journal & To-Do Reflection</h2>
        <div class="journal-subtitle">Organize today's goals, answer the 1-minute daily reflection quiz (rate 1–5), and track your progress over time.</div>
      </div>
      <div style="display:flex; align-items:center; gap:10px;">
        <button type="button" class="btn-save" onclick="openJournalQuiz()" style="display:inline-flex; align-items:center; gap:6px;">
          <span>✨</span> Take Daily Reflection Quiz
        </button>
      </div>
    </div>

    <!-- Stat Cards -->
    <div class="journal-stats-grid">
      <div class="jstat-card">
        <div class="jstat-top">
          <span class="jstat-label">Average Day Rating</span>
          <div class="jstat-icon-wrap">★</div>
        </div>
        <div class="jstat-val">
          <?= $avgRating; ?> <span style="font-size:14px; color:var(--text-muted); font-weight:normal;">/ 5.0</span>
        </div>
        <div class="jstat-sub"><?= renderRatingStars((int)round($avgRating ?: 3)); ?> based on <?= $totalEntries; ?> days</div>
      </div>

      <div class="jstat-card">
        <div class="jstat-top">
          <span class="jstat-label">Today's To-Do Progress</span>
          <div class="jstat-icon-wrap">✓</div>
        </div>
        <div class="jstat-val" id="statTodoText">
          <?= $todoCompleted; ?> <span style="font-size:14px; color:var(--text-muted); font-weight:normal;">/ <?= $todoTotal; ?> tasks</span>
        </div>
        <div style="width:100%; height:6px; background:var(--bg-subtle); border-radius:99px; overflow:hidden; margin-top:6px; border:1px solid var(--border-base);">
          <div id="statTodoBar" style="width:<?= $todoPct; ?>%; height:100%; background:var(--brand-primary); transition:width 0.25s ease;"></div>
        </div>
      </div>

      <div class="jstat-card">
        <div class="jstat-top">
          <span class="jstat-label">Total Journal Logs</span>
          <div class="jstat-icon-wrap">📖</div>
        </div>
        <div class="jstat-val"><?= $totalEntries; ?> <span style="font-size:14px; color:var(--text-muted); font-weight:normal;">entries</span></div>
        <div class="jstat-sub">Building self-awareness & consistency</div>
      </div>

      <div class="jstat-card">
        <div class="jstat-top">
          <span class="jstat-label">Phenomenal Days (5★)</span>
          <div class="jstat-icon-wrap">🚀</div>
        </div>
        <div class="jstat-val"><?= $fiveStarCount; ?> <span style="font-size:14px; color:var(--text-muted); font-weight:normal;">days</span></div>
        <div class="jstat-sub">High momentum & peak energy</div>
      </div>
    </div>

    <!-- Main 2-Column Split: Today's To-Dos & Today's Reflection -->
    <div class="journal-main-grid">
      
      <!-- Left Column: Simple Daily To-Do List -->
      <div class="jpanel">
        <div class="jpanel-header">
          <h3 class="jpanel-title">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
            Today's To-Do List
          </h3>
          <span style="font-size:12px; font-weight:600; color:var(--brand-primary); background:var(--bg-subtle); padding:3px 8px; border-radius:var(--radius-xs); border:1px solid var(--border-base);">
            <?= date('D, M j'); ?>
          </span>
        </div>

        <!-- Add To-Do Input -->
        <form id="addTodoForm" onsubmit="submitAddTodo(event)" class="todo-input-wrap">
          <input type="text" id="todoTextInput" class="form-input todo-input" placeholder="+ Add a task or goal for today..." required autocomplete="off" />
          <button type="submit" class="btn-save" id="btnAddTodoBtn" style="padding:0 14px; font-size:12.5px;">Add</button>
        </form>

        <!-- Filter tabs -->
        <div class="todo-filter-nav">
          <button type="button" class="todo-filter-pill active" data-filter="all" onclick="filterTodoList('all', this)">All (<?= $todoTotal; ?>)</button>
          <button type="button" class="todo-filter-pill" data-filter="active" onclick="filterTodoList('active', this)">Active (<?= $todoTotal - $todoCompleted; ?>)</button>
          <button type="button" class="todo-filter-pill" data-filter="completed" onclick="filterTodoList('completed', this)">Completed (<?= $todoCompleted; ?>)</button>
          <?php if ($todoCompleted > 0): ?>
            <button type="button" class="tool-btn-action" onclick="clearCompletedTodos()" style="margin-left:auto; font-size:11.5px; color:var(--text-muted);">
              Clear Completed
            </button>
          <?php endif; ?>
        </div>

        <!-- Task List Items -->
        <div class="todo-list-wrap" id="todoListContainer">
          <?php if (empty($todayTodos)): ?>
            <div id="todoEmptyMsg" style="text-align:center; padding:32px 16px; color:var(--text-muted); font-size:13px;">
              No to-do tasks added for today yet. Type above to add your first priority!
            </div>
          <?php else: ?>
            <?php foreach ($todayTodos as $td): 
              $isDone = !empty($td['is_completed']);
            ?>
              <div class="todo-item-row <?= $isDone ? 'completed' : ''; ?>" id="todoRow-<?= $td['id']; ?>" data-status="<?= $isDone ? 'completed' : 'active'; ?>">
                <input type="checkbox" class="todo-check" <?= $isDone ? 'checked' : ''; ?> onchange="toggleTodoItem(<?= $td['id']; ?>, this.checked)" />
                <span class="todo-text"><?= htmlspecialchars($td['task_text']); ?></span>
                <button type="button" class="btn-todo-del" title="Delete task" onclick="deleteTodoItem(<?= $td['id']; ?>)">✕</button>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <div style="margin-top:14px; padding-top:10px; border-top:1px solid var(--border-base); font-size:11.5px; color:var(--text-muted); display:flex; align-items:center; gap:6px;">
          <span>💡 <b>Pro-tip:</b> As you complete tasks, they can be pulled into your evening reflection quiz with one click.</span>
        </div>
      </div>

      <!-- Right Column: Today's Reflection Quiz Status -->
      <div class="jpanel">
        <div class="jpanel-header">
          <h3 class="jpanel-title">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            Today's Check-in & Journal
          </h3>
          <?php if ($todayEntry): ?>
            <?= renderMoodBadge((int)$todayEntry['rating'], $todayEntry['mood_label']); ?>
          <?php else: ?>
            <span style="font-size:12px; font-weight:600; color:var(--status-high-text); background:var(--status-high-bg); padding:3px 8px; border-radius:var(--radius-xs); border:1px solid var(--status-high-border);">
              Pending Check-in
            </span>
          <?php endif; ?>
        </div>

        <?php if ($todayEntry): ?>
          <!-- Already completed today's reflection -->
          <div style="display:flex; flex-direction:column; gap:14px;">
            <div style="display:flex; align-items:center; justify-content:space-between; padding:12px 14px; background:var(--bg-subtle); border-radius:var(--radius-sm); border:1px solid var(--border-base);">
              <div style="display:flex; align-items:center; gap:10px;">
                <span style="font-size:24px; font-weight:700; color:var(--brand-primary);"><?= $todayEntry['rating']; ?>.0</span>
                <div>
                  <div style="font-size:13px; font-weight:700; color:var(--text-primary);"><?= htmlspecialchars($todayEntry['mood_label']); ?></div>
                  <div style="font-size:11.5px;"><?= renderRatingStars((int)$todayEntry['rating']); ?></div>
                </div>
              </div>
              <button type="button" class="btn-save" onclick="openJournalQuiz()" style="font-size:11.5px; padding:4px 10px; background:var(--bg-surface); color:var(--text-primary); border:1px solid var(--border-base);">
                Update Check-in
              </button>
            </div>

            <?php if (!empty($todayEntry['accomplishments'])): ?>
              <div class="jentry-section">
                <div class="jentry-label">🎯 Highlights & Wins</div>
                <div style="color:var(--text-primary);"><?= nl2br(htmlspecialchars($todayEntry['accomplishments'])); ?></div>
              </div>
            <?php endif; ?>

            <?php if (!empty($todayEntry['challenges']) || !empty($todayEntry['learning_notes'])): ?>
              <div class="jentry-section">
                <div class="jentry-label">🧠 Hurdles & Learnings</div>
                <div style="color:var(--text-primary);">
                  <?= nl2br(htmlspecialchars($todayEntry['challenges'] ?? $todayEntry['learning_notes'])); ?>
                </div>
              </div>
            <?php endif; ?>

            <?php if (!empty($todayEntry['journal_text'])): ?>
              <div class="jentry-section">
                <div class="jentry-label">📝 Daily Journal Thoughts</div>
                <div style="background:var(--bg-subtle); padding:10px 12px; border-radius:var(--radius-xs); border:1px solid var(--border-base); color:var(--text-primary); font-size:13px; line-height:1.5;">
                  <?= nl2br(htmlspecialchars($todayEntry['journal_text'])); ?>
                </div>
              </div>
            <?php endif; ?>
          </div>
        <?php else: ?>
          <!-- Not completed yet -> Interactive Quick Quiz Card -->
          <div class="today-journal-empty">
            <span style="font-size:32px;">📊</span>
            <div style="font-size:15px; font-weight:700; color:var(--text-primary);">How was your day so far?</div>
            <div style="font-size:13px; color:var(--text-secondary); max-width:320px;">
              Take 60 seconds to score your day from 1 to 5 and capture your journal reflection.
            </div>

            <!-- Quick Rating Buttons (1 to 5) -->
            <div class="quick-rate-grid">
              <button type="button" class="quick-rate-btn" onclick="openJournalQuiz(1)">
                <span class="quick-rate-num">1</span>
                <span class="quick-rate-emoji">😞</span>
                <span class="quick-rate-label">Rough</span>
              </button>
              <button type="button" class="quick-rate-btn" onclick="openJournalQuiz(2)">
                <span class="quick-rate-num">2</span>
                <span class="quick-rate-emoji">😐</span>
                <span class="quick-rate-label">Slow</span>
              </button>
              <button type="button" class="quick-rate-btn" onclick="openJournalQuiz(3)">
                <span class="quick-rate-num">3</span>
                <span class="quick-rate-emoji">🙂</span>
                <span class="quick-rate-label">Steady</span>
              </button>
              <button type="button" class="quick-rate-btn" onclick="openJournalQuiz(4)">
                <span class="quick-rate-num">4</span>
                <span class="quick-rate-emoji">😊</span>
                <span class="quick-rate-label">Great</span>
              </button>
              <button type="button" class="quick-rate-btn" onclick="openJournalQuiz(5)">
                <span class="quick-rate-num">5</span>
                <span class="quick-rate-emoji">🚀</span>
                <span class="quick-rate-label">Phenomenal</span>
              </button>
            </div>

            <button type="button" class="btn-save" onclick="openJournalQuiz()" style="font-size:13px; padding:8px 18px;">
              Start Full Check-in Quiz →
            </button>
          </div>
        <?php endif; ?>
      </div>

    </div><!-- /journal-main-grid -->

    <!-- Past Journal Entries Timeline -->
    <div class="jpanel" style="margin-top:10px;">
      <div class="jpanel-header" style="flex-wrap:wrap; gap:10px;">
        <h3 class="jpanel-title">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
          Past Journal Entries & Reflections
        </h3>

        <!-- Filter by rating -->
        <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
          <span style="font-size:12px; font-weight:600; color:var(--text-muted);">Filter:</span>
          <button type="button" class="todo-filter-pill active filter-rating-btn" data-rating="all" onclick="filterJournalRating('all', this)">All (<?= $totalEntries; ?>)</button>
          <button type="button" class="todo-filter-pill filter-rating-btn" data-rating="5" onclick="filterJournalRating('5', this)">5 ★</button>
          <button type="button" class="todo-filter-pill filter-rating-btn" data-rating="4" onclick="filterJournalRating('4', this)">4 ★</button>
          <button type="button" class="todo-filter-pill filter-rating-btn" data-rating="3" onclick="filterJournalRating('3', this)">3 ★</button>
          <button type="button" class="todo-filter-pill filter-rating-btn" data-rating="low" onclick="filterJournalRating('low', this)">1-2 ★</button>
        </div>
      </div>

      <div class="journal-timeline" id="journalTimeline">
        <?php if (empty($journalEntries)): ?>
          <div style="text-align:center; padding:40px 20px; color:var(--text-muted); font-size:13.5px;">
            No journal entries recorded yet. Click "Take Daily Reflection Quiz" to record your first day!
          </div>
        <?php else: ?>
          <?php foreach ($journalEntries as $entry): 
            $rating = (int)$entry['rating'];
            $entryTs = strtotime($entry['entry_date']);
            $diffDays = (int)round((time() - $entryTs) / 86400);
            $relText = ($diffDays === 0) ? 'Today' : (($diffDays === 1) ? 'Yesterday' : ($diffDays . ' days ago'));
          ?>
            <div class="jentry-card" id="entryCard-<?= $entry['id']; ?>" data-rating="<?= $rating; ?>">
              <div class="jentry-top">
                <div class="jentry-date-group">
                  <span class="jentry-date"><?= date('l, F j, Y', $entryTs); ?></span>
                  <span class="jentry-relative"><?= $relText; ?></span>
                </div>
                <div style="display:flex; align-items:center; gap:10px;">
                  <?= renderRatingStars($rating); ?>
                  <?= renderMoodBadge($rating, $entry['mood_label']); ?>
                  <button type="button" class="tool-btn-action" title="Delete entry" onclick="deleteJournalEntry(<?= $entry['id']; ?>)" style="color:var(--text-muted); font-size:13px; padding:2px 6px;">✕</button>
                </div>
              </div>

              <?php if (!empty($entry['accomplishments'])): ?>
                <div class="jentry-section">
                  <div class="jentry-label">🎯 What went well / Accomplishments</div>
                  <div style="color:var(--text-primary);"><?= nl2br(htmlspecialchars($entry['accomplishments'])); ?></div>
                </div>
              <?php endif; ?>

              <?php if (!empty($entry['challenges']) || !empty($entry['learning_notes'])): ?>
                <div class="jentry-section">
                  <div class="jentry-label">🧠 Challenges & Insights</div>
                  <div style="color:var(--text-primary);">
                    <?= nl2br(htmlspecialchars($entry['challenges'] ?? $entry['learning_notes'])); ?>
                  </div>
                </div>
              <?php endif; ?>

              <?php if (!empty($entry['journal_text'])): ?>
                <div class="jentry-section" style="margin-bottom:0;">
                  <div class="jentry-label">📝 Reflection Notes</div>
                  <div style="background:var(--bg-subtle); padding:10px 14px; border-radius:var(--radius-sm); border:1px solid var(--border-base); color:var(--text-primary); font-size:13px; line-height:1.55;">
                    <?= nl2br(htmlspecialchars($entry['journal_text'])); ?>
                  </div>
                </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

  </main>
</div>

<!-- Modal: Daily Reflection Check-in Quiz -->
<div class="modal-overlay" id="journalQuizModal">
  <div class="modal-card" style="max-width: 580px;">
    <div class="modal-header">
      <div style="display:flex; align-items:center; gap:8px;">
        <span style="font-size:18px;">✨</span>
        <h3 class="modal-title" style="margin:0;">Daily Reflection Quiz</h3>
      </div>
      <button class="modal-close-btn" aria-label="Close modal" onclick="closeJournalQuiz()">&times;</button>
    </div>
    <form id="journalQuizForm" onsubmit="submitJournalQuiz(event)">
      <input type="hidden" id="quizEntryDate" value="<?= $today; ?>" />
      <input type="hidden" id="quizSelectedRating" value="<?= $todayEntry ? (int)$todayEntry['rating'] : 4; ?>" />
      <input type="hidden" id="quizSelectedMood" value="<?= $todayEntry ? htmlspecialchars($todayEntry['mood_label']) : 'Great Day'; ?>" />

      <div class="modal-body" style="max-height:75vh; overflow-y:auto; display:flex; flex-direction:column; gap:18px;">
        
        <!-- Question 1: Rating (1 to 5) -->
        <div>
          <label class="form-label" style="font-size:14px; font-weight:700; margin-bottom:10px; color:var(--text-primary);">
            1. How was your day overall? <span style="font-size:12px; font-weight:normal; color:var(--text-secondary);">(Select 1 to 5)</span>
          </label>
          <div style="display:flex; flex-direction:column; gap:8px;">
            <div class="quiz-score-btn" data-score="5" onclick="selectQuizScore(5, 'Phenomenal')">
              <span class="quiz-num-badge">5</span>
              <span style="font-size:20px;">🚀</span>
              <div style="flex:1;">
                <div style="font-size:13.5px; font-weight:700; color:var(--text-primary);">Phenomenal!</div>
                <div style="font-size:11.5px; color:var(--text-muted);">Crushed all goals, peak focus, and high energy</div>
              </div>
              <span style="color:#10b981; font-size:14px;">★★★★★</span>
            </div>

            <div class="quiz-score-btn" data-score="4" onclick="selectQuizScore(4, 'Great Day')">
              <span class="quiz-num-badge">4</span>
              <span style="font-size:20px;">😊</span>
              <div style="flex:1;">
                <div style="font-size:13.5px; font-weight:700; color:var(--text-primary);">Great Day</div>
                <div style="font-size:11.5px; color:var(--text-muted);">Productive, made meaningful progress with good flow</div>
              </div>
              <span style="color:#3b82f6; font-size:14px;">★★★★☆</span>
            </div>

            <div class="quiz-score-btn" data-score="3" onclick="selectQuizScore(3, 'Steady & Solid')">
              <span class="quiz-num-badge">3</span>
              <span style="font-size:20px;">🙂</span>
              <div style="flex:1;">
                <div style="font-size:13.5px; font-weight:700; color:var(--text-primary);">Steady & Solid</div>
                <div style="font-size:11.5px; color:var(--text-muted);">Handled routine duties and kept things moving</div>
              </div>
              <span style="color:#f59e0b; font-size:14px;">★★★☆☆</span>
            </div>

            <div class="quiz-score-btn" data-score="2" onclick="selectQuizScore(2, 'Slow Going')">
              <span class="quiz-num-badge">2</span>
              <span style="font-size:20px;">😐</span>
              <div style="flex:1;">
                <div style="font-size:13.5px; font-weight:700; color:var(--text-primary);">Slow Going</div>
                <div style="font-size:11.5px; color:var(--text-muted);">Encountered hurdles, interruptions, or fatigue</div>
              </div>
              <span style="color:#f97316; font-size:14px;">★★☆☆☆</span>
            </div>

            <div class="quiz-score-btn" data-score="1" onclick="selectQuizScore(1, 'Rough Day')">
              <span class="quiz-num-badge">1</span>
              <span style="font-size:20px;">😞</span>
              <div style="flex:1;">
                <div style="font-size:13.5px; font-weight:700; color:var(--text-primary);">Rough Day</div>
                <div style="font-size:11.5px; color:var(--text-muted);">Felt drained, frustrated, or blocked completely</div>
              </div>
              <span style="color:#ef4444; font-size:14px;">★☆☆☆☆</span>
            </div>
          </div>
        </div>

        <!-- Question 2: Accomplishments / Highlights -->
        <div>
          <label class="form-label" for="quizAccomplishments" style="font-size:14px; font-weight:700; margin-bottom:6px; color:var(--text-primary);">
            2. What was your main highlight or accomplishment?
          </label>
          <!-- Quick insert pills from today's completed todos -->
          <?php if (!empty($todayTodos)): ?>
            <div style="display:flex; flex-wrap:wrap; gap:6px; margin-bottom:8px;">
              <span style="font-size:11px; font-weight:600; color:var(--text-muted); align-self:center;">Tap to insert:</span>
              <?php foreach ($todayTodos as $td): ?>
                <button type="button" class="plan-pill" onclick="insertTodoToQuiz(<?= json_encode($td['task_text']); ?>)">
                  + <?= htmlspecialchars($td['task_text']); ?>
                </button>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
          <textarea id="quizAccomplishments" class="form-input" rows="2" placeholder="e.g. Completed module 3 and solved race condition bug..."><?= $todayEntry ? htmlspecialchars($todayEntry['accomplishments'] ?? '') : ''; ?></textarea>
        </div>

        <!-- Question 3: Challenges & Learnings -->
        <div>
          <label class="form-label" for="quizChallenges" style="font-size:14px; font-weight:700; margin-bottom:6px; color:var(--text-primary);">
            3. Any challenges faced or key takeaways?
          </label>
          <textarea id="quizChallenges" class="form-input" rows="2" placeholder="e.g. Need to allocate more focus time in the mornings..."><?= $todayEntry ? htmlspecialchars($todayEntry['challenges'] ?? '') : ''; ?></textarea>
        </div>

        <!-- Question 4: Journal Notes -->
        <div>
          <label class="form-label" for="quizJournalText" style="font-size:14px; font-weight:700; margin-bottom:6px; color:var(--text-primary);">
            4. Freeform Journal Thoughts & Notes
          </label>
          <textarea id="quizJournalText" class="form-input" rows="3" placeholder="Write anything else on your mind, gratitude, or tomorrow's intentions..."><?= $todayEntry ? htmlspecialchars($todayEntry['journal_text'] ?? '') : ''; ?></textarea>
        </div>

      </div>
      <div class="modal-footer" style="display:flex; justify-content:space-between; align-items:center;">
        <button type="button" class="btn-cancel" onclick="closeJournalQuiz()">Cancel</button>
        <button type="submit" class="btn-save" id="btnSubmitQuiz" style="display:inline-flex; align-items:center; gap:6px;">
          <span>💾</span> Save Journal Entry
        </button>
      </div>
    </form>
  </div>
</div>

<script src="assets/js/app.js"></script>
<script>
// Open / Close Quiz Modal
window.openJournalQuiz = function(defaultRating = null) {
  const modal = document.getElementById('journalQuizModal');
  if (!modal) return;

  if (defaultRating) {
    const labels = { 1: 'Rough Day', 2: 'Slow Going', 3: 'Steady & Solid', 4: 'Great Day', 5: 'Phenomenal' };
    selectQuizScore(defaultRating, labels[defaultRating] || 'Great Day');
  } else {
    const currentRating = parseInt(document.getElementById('quizSelectedRating').value, 10) || 4;
    const currentMood = document.getElementById('quizSelectedMood').value || 'Great Day';
    selectQuizScore(currentRating, currentMood);
  }

  modal.classList.add('active');
};

window.closeJournalQuiz = function() {
  const modal = document.getElementById('journalQuizModal');
  if (modal) modal.classList.remove('active');
};

window.selectQuizScore = function(score, label) {
  document.getElementById('quizSelectedRating').value = score;
  document.getElementById('quizSelectedMood').value = label;

  document.querySelectorAll('.quiz-score-btn').forEach(btn => {
    const s = parseInt(btn.getAttribute('data-score'), 10);
    if (s === score) {
      btn.classList.add('selected');
    } else {
      btn.classList.remove('selected');
    }
  });
};

window.insertTodoToQuiz = function(text) {
  const area = document.getElementById('quizAccomplishments');
  if (!area) return;
  if (area.value.trim().length > 0) {
    area.value += '\n• ' + text;
  } else {
    area.value = '• ' + text;
  }
};

window.submitJournalQuiz = async function(e) {
  e.preventDefault();
  const btn = document.getElementById('btnSubmitQuiz');
  btn.disabled = true;
  btn.textContent = 'Saving...';

  const entry_date = document.getElementById('quizEntryDate').value;
  const rating = parseInt(document.getElementById('quizSelectedRating').value, 10);
  const mood_label = document.getElementById('quizSelectedMood').value;
  const accomplishments = document.getElementById('quizAccomplishments').value.trim();
  const challenges = document.getElementById('quizChallenges').value.trim();
  const journal_text = document.getElementById('quizJournalText').value.trim();

  try {
    const res = await secureFetch('api/journal.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'save_entry',
        entry_date,
        rating,
        mood_label,
        accomplishments,
        challenges,
        journal_text
      })
    });
    const data = await res.json();
    if (data.success) {
      if (typeof showToast === 'function') showToast(data.message || 'Daily journal saved!');
      closeJournalQuiz();
      setTimeout(() => location.reload(), 400);
    } else {
      alert(data.message || 'Failed to save journal entry');
    }
  } catch (err) {
    console.error(err);
  } finally {
    btn.disabled = false;
    btn.textContent = 'Save Journal Entry';
  }
};

// Simple To-Do Item Handlers
window.submitAddTodo = async function(e) {
  e.preventDefault();
  const input = document.getElementById('todoTextInput');
  const text = input.value.trim();
  if (!text) return;

  const btn = document.getElementById('btnAddTodoBtn');
  btn.disabled = true;

  try {
    const res = await secureFetch('api/journal.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'add_todo', task_text: text })
    });
    const data = await res.json();
    if (data.success) {
      input.value = '';
      if (typeof showToast === 'function') showToast('To-do added!');
      setTimeout(() => location.reload(), 300);
    } else {
      alert(data.message || 'Failed to add task');
    }
  } catch (err) {
    console.error(err);
  } finally {
    btn.disabled = false;
  }
};

window.toggleTodoItem = async function(id, isChecked) {
  const row = document.getElementById('todoRow-' + id);
  if (row) {
    if (isChecked) {
      row.classList.add('completed');
      row.setAttribute('data-status', 'completed');
    } else {
      row.classList.remove('completed');
      row.setAttribute('data-status', 'active');
    }
  }

  try {
    const res = await secureFetch('api/journal.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'toggle_todo', todo_id: id, is_completed: isChecked ? 1 : 0 })
    });
    const data = await res.json();
    if (data.success) {
      if (typeof showToast === 'function') showToast(data.message);
      updateTodoStatsLive();
    }
  } catch (err) {
    console.error(err);
  }
};

window.deleteTodoItem = async function(id) {
  const row = document.getElementById('todoRow-' + id);
  if (row) row.remove();

  try {
    const res = await secureFetch('api/journal.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'delete_todo', todo_id: id })
    });
    const data = await res.json();
    if (data.success) {
      if (typeof showToast === 'function') showToast('Task deleted');
      updateTodoStatsLive();
    }
  } catch (err) {
    console.error(err);
  }
};

window.clearCompletedTodos = async function() {
  if (!confirm('Clear all completed tasks for today?')) return;
  try {
    const res = await secureFetch('api/journal.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'clear_completed_todos' })
    });
    const data = await res.json();
    if (data.success) {
      if (typeof showToast === 'function') showToast('Cleared completed tasks');
      setTimeout(() => location.reload(), 300);
    }
  } catch (err) {
    console.error(err);
  }
};

function updateTodoStatsLive() {
  const allRows = document.querySelectorAll('.todo-item-row');
  const compRows = document.querySelectorAll('.todo-item-row.completed');
  const total = allRows.length;
  const done = compRows.length;
  const pct = total > 0 ? Math.round((done / total) * 100) : 0;

  const statText = document.getElementById('statTodoText');
  const statBar = document.getElementById('statTodoBar');
  if (statText) statText.innerHTML = `${done} <span style="font-size:14px; color:var(--text-muted); font-weight:normal;">/ ${total} tasks</span>`;
  if (statBar) statBar.style.width = pct + '%';
}

window.filterTodoList = function(filter, btn) {
  document.querySelectorAll('.todo-filter-pill[data-filter]').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');

  const rows = document.querySelectorAll('.todo-item-row');
  rows.forEach(r => {
    const st = r.getAttribute('data-status');
    if (filter === 'all' || st === filter) {
      r.style.display = 'flex';
    } else {
      r.style.display = 'none';
    }
  });
};

window.filterJournalRating = function(rating, btn) {
  document.querySelectorAll('.filter-rating-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');

  const cards = document.querySelectorAll('.jentry-card');
  cards.forEach(c => {
    const r = parseInt(c.getAttribute('data-rating'), 10);
    let match = false;
    if (rating === 'all') match = true;
    else if (rating === 'low') match = (r <= 2);
    else match = (r === parseInt(rating, 10));

    c.style.display = match ? 'block' : 'none';
  });
};

window.deleteJournalEntry = async function(id) {
  if (!confirm('Are you sure you want to remove this journal entry?')) return;
  try {
    const res = await secureFetch('api/journal.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'delete_entry', entry_id: id })
    });
    const data = await res.json();
    if (data.success) {
      if (typeof showToast === 'function') showToast('Journal entry deleted');
      const card = document.getElementById('entryCard-' + id);
      if (card) card.remove();
    }
  } catch (err) {
    console.error(err);
  }
};

// Check URL params on load (e.g. journal.php?action=quiz&rate=5)
document.addEventListener('DOMContentLoaded', () => {
  const params = new URLSearchParams(window.location.search);
  if (params.get('action') === 'quiz') {
    const rate = params.get('rate') ? parseInt(params.get('rate'), 10) : null;
    openJournalQuiz(rate);
  }
});
</script>
</body>
</html>
