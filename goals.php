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

// Fetch Goals
$stmtGoals = $db->prepare("SELECT * FROM project_goals WHERE user_id = :uid ORDER BY id DESC");
$stmtGoals->execute(['uid' => $userId]);
$goals = $stmtGoals->fetchAll();

// Calculate High-Level Metrics
$totalGoals = count($goals);
$onTrackCount = 0;
$atRiskCount = 0;
$behindCount = 0;
$completedCount = 0;
$totalPctSum = 0;

foreach ($goals as $g) {
    $target = max(1, (int)$g['target_value']);
    $curr = max(0, (int)$g['current_value']);
    $pct = min(100, round(($curr / $target) * 100));
    $totalPctSum += $pct;
    
    $st = strtolower(trim($g['status'] ?? 'on track'));
    if ($st === 'completed' || $pct >= 100) {
        $completedCount++;
    } elseif ($st === 'at risk') {
        $atRiskCount++;
    } elseif ($st === 'behind') {
        $behindCount++;
    } else {
        $onTrackCount++;
    }
}
$avgProgress = $totalGoals > 0 ? round($totalPctSum / $totalGoals) : 0;

$pageTitle = 'Mindrift — Objectives & OKRs';
include __DIR__ . '/includes/head.php';
?>
<style>
  .goals-page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 16px;
  }
  .goals-title {
    margin: 0;
    font-size: 22px;
    font-weight: 700;
    color: var(--text-primary);
    letter-spacing: -0.02em;
  }
  .goals-subtitle {
    margin: 4px 0 0;
    color: var(--text-secondary);
    font-size: 13.5px;
    font-weight: 400;
  }

  /* Metric KPI Cards */
  .goals-kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 24px;
  }
  @media (max-width: 900px) {
    .goals-kpi-grid { grid-template-columns: repeat(2, 1fr); }
  }
  @media (max-width: 540px) {
    .goals-kpi-grid { grid-template-columns: 1fr; }
  }
  .kpi-card {
    background: var(--bg-surface);
    border: 1px solid var(--border-base);
    border-radius: var(--radius-md);
    padding: 16px 18px;
    display: flex;
    flex-direction: column;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02);
  }
  .kpi-label {
    font-size: 12px;
    font-weight: 600;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.04em;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    justify-content: space-between;
  }
  .kpi-val {
    font-size: 26px;
    font-weight: 800;
    color: var(--text-primary);
    line-height: 1.1;
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  }
  .kpi-sub {
    font-size: 11.5px;
    color: var(--text-secondary);
    margin-top: 6px;
  }

  /* Filter Tabs Bar */
  .goals-filter-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 12px;
  }
  .goals-tabs {
    display: flex;
    align-items: center;
    gap: 6px;
    background: var(--bg-subtle);
    padding: 4px;
    border-radius: var(--radius-sm);
    border: 1px solid var(--border-base);
    overflow-x: auto;
  }
  .goals-tab-btn {
    padding: 5px 12px;
    font-size: 12.5px;
    font-weight: 600;
    border-radius: var(--radius-xs);
    border: none;
    background: transparent;
    color: var(--text-secondary);
    cursor: pointer;
    transition: all 0.15s ease;
    white-space: nowrap;
    display: inline-flex;
    align-items: center;
    gap: 6px;
  }
  .goals-tab-btn:hover {
    color: var(--text-primary);
  }
  .goals-tab-btn.active {
    background: var(--bg-surface);
    color: var(--brand-primary);
    box-shadow: 0 1px 2px rgba(0,0,0,0.06);
  }
  .goals-tab-badge {
    font-size: 11px;
    padding: 1px 6px;
    border-radius: var(--radius-full);
    background: var(--border-base);
    color: var(--text-secondary);
  }
  .goals-tab-btn.active .goals-tab-badge {
    background: var(--brand-subtle);
    color: var(--brand-primary);
  }

  /* Cards Grid */
  .goals-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 18px;
  }
  .goal-card {
    background: var(--bg-surface);
    border: 1px solid var(--border-base);
    border-radius: var(--radius-md);
    padding: 18px 20px;
    display: flex;
    flex-direction: column;
    transition: transform 0.15s ease, border-color 0.15s ease, box-shadow 0.15s ease;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02);
  }
  .goal-card:hover {
    border-color: var(--brand-primary);
    box-shadow: 0 4px 14px rgba(0,0,0,0.04);
  }
  
  .goal-meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 12px;
  }
  .goal-category {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    padding: 3px 8px;
    border-radius: var(--radius-xs);
    background: var(--bg-subtle);
    border: 1px solid var(--border-base);
    color: var(--text-secondary);
    letter-spacing: 0.04em;
  }
  .goal-status-badge {
    font-size: 11.5px;
    font-weight: 600;
    padding: 3px 9px;
    border-radius: var(--radius-xs);
    display: inline-flex;
    align-items: center;
    gap: 5px;
  }
  .status-on-track {
    background: var(--status-done-bg);
    color: var(--status-done-text);
    border: 1px solid var(--status-done-border);
  }
  .status-at-risk {
    background: var(--status-high-bg);
    color: var(--status-high-text);
    border: 1px solid var(--status-high-border);
  }
  .status-behind {
    background: var(--status-urgent-bg);
    color: var(--status-urgent-text);
    border: 1px solid var(--status-urgent-border);
  }
  .status-completed {
    background: rgba(16, 185, 129, 0.15);
    color: #10B981;
    border: 1px solid rgba(16, 185, 129, 0.3);
  }

  .goal-title {
    margin: 0 0 6px;
    font-size: 15.5px;
    font-weight: 700;
    color: var(--text-primary);
    line-height: 1.35;
  }
  .goal-due {
    font-size: 12px;
    color: var(--text-muted);
    font-weight: 500;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 5px;
  }

  .goal-progress-wrap {
    margin-top: auto;
  }
  .goal-progress-nums {
    display: flex;
    justify-content: space-between;
    font-size: 12.5px;
    font-weight: 600;
    margin-bottom: 6px;
  }
  .goal-bar-bg {
    width: 100%;
    height: 7px;
    background: var(--border-base);
    border-radius: var(--radius-xs);
    overflow: hidden;
  }
  .goal-bar-fill {
    height: 100%;
    border-radius: var(--radius-xs);
    background: var(--brand-primary);
    transition: width 0.3s ease;
  }
  .goal-bar-fill.warning { background: #F59E0B; }
  .goal-bar-fill.danger { background: #EF4444; }
  .goal-bar-fill.completed { background: #10B981; }

  .goal-actions {
    display: flex;
    gap: 6px;
    align-items: center;
    margin-top: 14px;
    padding-top: 12px;
    border-top: 1px solid var(--border-base);
    flex-wrap: wrap;
  }
  .btn-step {
    padding: 3px 8px;
    font-size: 11.5px;
    font-weight: 600;
    border: 1px solid var(--border-base);
    background: var(--bg-subtle);
    border-radius: var(--radius-xs);
    cursor: pointer;
    color: var(--text-primary);
    transition: all 0.15s ease;
  }
  .btn-step:hover {
    background: var(--bg-surface);
    border-color: var(--brand-primary);
    color: var(--brand-primary);
  }
  .btn-icon-action {
    background: none;
    border: none;
    color: var(--text-muted);
    cursor: pointer;
    padding: 4px;
    border-radius: var(--radius-xs);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: color 0.15s ease;
  }
  .btn-icon-action:hover {
    color: var(--text-primary);
  }
  .btn-icon-action.delete:hover {
    color: var(--status-urgent-text);
  }
</style>
</head>
<body>

<div class="app" id="app">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>

  <main class="main">
    <?php include __DIR__ . '/includes/header.php'; ?>

    <!-- Page Header -->
    <div class="goals-page-header">
      <div>
        <h2 class="goals-title">Objectives &amp; Key Results</h2>
        <p class="goals-subtitle">Set clear deliverables, align milestones, and track targets with quantified key metrics.</p>
      </div>
      <button type="button" class="btn-save" style="display:inline-flex; align-items:center; gap:6px;" onclick="openGoalModal()">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        <span>New Objective</span>
      </button>
    </div>

    <!-- KPI Metric Cards -->
    <div class="goals-kpi-grid">
      <div class="kpi-card">
        <div class="kpi-label">
          <span>Total Objectives</span>
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--text-muted);"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>
        </div>
        <div class="kpi-val" id="kpiTotal"><?= $totalGoals; ?></div>
        <div class="kpi-sub">Across all active workspaces</div>
      </div>

      <div class="kpi-card">
        <div class="kpi-label">
          <span>On Track</span>
          <span style="color:#10B981;">●</span>
        </div>
        <div class="kpi-val" style="color:#10B981;" id="kpiOnTrack"><?= $onTrackCount; ?></div>
        <div class="kpi-sub">Pacing healthy for deadlines</div>
      </div>

      <div class="kpi-card">
        <div class="kpi-label">
          <span>Needs Attention</span>
          <span style="color:#F59E0B;">●</span>
        </div>
        <div class="kpi-val" style="color:#F59E0B;" id="kpiAttention"><?= ($atRiskCount + $behindCount); ?></div>
        <div class="kpi-sub"><?= $atRiskCount; ?> at risk &bull; <?= $behindCount; ?> behind</div>
      </div>

      <div class="kpi-card">
        <div class="kpi-label">
          <span>Average Completion</span>
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--brand-primary);"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        </div>
        <div class="kpi-val" style="color:var(--brand-primary);" id="kpiAvg"><?= $avgProgress; ?>%</div>
        <div class="kpi-sub"><?= $completedCount; ?> objectives completed</div>
      </div>
    </div>

    <!-- Filter Tabs Bar -->
    <div class="goals-filter-bar">
      <div class="goals-tabs">
        <button type="button" class="goals-tab-btn active" onclick="filterGoals('all', this)">
          <span>All</span>
          <span class="goals-tab-badge" id="filterCountAll"><?= $totalGoals; ?></span>
        </button>
        <button type="button" class="goals-tab-btn" onclick="filterGoals('on-track', this)">
          <span>On Track</span>
          <span class="goals-tab-badge" id="filterCountOnTrack"><?= $onTrackCount; ?></span>
        </button>
        <button type="button" class="goals-tab-btn" onclick="filterGoals('at-risk', this)">
          <span>At Risk</span>
          <span class="goals-tab-badge" id="filterCountAtRisk"><?= $atRiskCount; ?></span>
        </button>
        <button type="button" class="goals-tab-btn" onclick="filterGoals('behind', this)">
          <span>Behind</span>
          <span class="goals-tab-badge" id="filterCountBehind"><?= $behindCount; ?></span>
        </button>
        <button type="button" class="goals-tab-btn" onclick="filterGoals('completed', this)">
          <span>Completed</span>
          <span class="goals-tab-badge" id="filterCountCompleted"><?= $completedCount; ?></span>
        </button>
      </div>

      <div style="font-size:12.5px; color:var(--text-muted);">
        Click <kbd style="background:var(--bg-subtle); border:1px solid var(--border-base); border-radius:3px; padding:1px 5px; font-size:11px;">+5</kbd> / <kbd style="background:var(--bg-subtle); border:1px solid var(--border-base); border-radius:3px; padding:1px 5px; font-size:11px;">+10</kbd> to quick-log progress
      </div>
    </div>

    <!-- Goals Grid -->
    <div class="goals-grid" id="goalsGrid">
      <?php if (empty($goals)): ?>
        <div style="grid-column:1/-1; text-align:center; padding:48px 20px; background:var(--bg-surface); border:1px solid var(--border-base); border-radius:var(--radius-md);" id="goalsEmptyState">
          <div style="width:48px; height:48px; border-radius:50%; background:var(--brand-subtle); color:var(--brand-primary); display:flex; align-items:center; justify-content:center; margin:0 auto 12px; font-size:22px;">🎯</div>
          <div style="font-size:16px; font-weight:700; color:var(--text-primary); margin-bottom:4px;">No objectives created yet</div>
          <p style="color:var(--text-secondary); font-size:13px; margin:0 0 16px;">Set clear targets, track numerical progress, and drive deliverables forward.</p>
          <button type="button" class="btn-save" onclick="openGoalModal()">Create Objective</button>
        </div>
      <?php else: ?>
        <?php foreach ($goals as $g): 
          $target = max(1, (int)$g['target_value']);
          $curr = max(0, (int)$g['current_value']);
          $pct = min(100, round(($curr / $target) * 100));
          $rawStatus = trim($g['status'] ?? 'On Track');
          if ($curr >= $target && strtolower($rawStatus) !== 'completed') {
            $rawStatus = 'Completed';
          }
          $stSlug = strtolower(str_replace(' ', '-', $rawStatus));
          
          $barColorClass = '';
          if ($stSlug === 'completed' || $pct >= 100) $barColorClass = 'completed';
          elseif ($stSlug === 'at-risk') $barColorClass = 'warning';
          elseif ($stSlug === 'behind') $barColorClass = 'danger';
        ?>
          <div class="goal-card" id="goalCard-<?= (int)$g['id']; ?>" 
               data-id="<?= (int)$g['id']; ?>"
               data-status="<?= htmlspecialchars($stSlug); ?>"
               data-title="<?= htmlspecialchars($g['title']); ?>"
               data-category="<?= htmlspecialchars($g['category'] ?? 'Productivity'); ?>"
               data-target="<?= $target; ?>"
               data-current="<?= $curr; ?>"
               data-unit="<?= htmlspecialchars($g['unit'] ?? '%'); ?>"
               data-due="<?= htmlspecialchars($g['due_date'] ?? ''); ?>"
               data-raw-status="<?= htmlspecialchars($rawStatus); ?>">
            
            <div class="goal-meta">
              <span class="goal-category"><?= htmlspecialchars($g['category'] ?? 'Productivity'); ?></span>
              <span class="goal-status-badge status-<?= $stSlug; ?>">
                <?php if ($stSlug === 'completed'): ?>✓<?php elseif ($stSlug === 'behind'): ?>⚠<?php else: ?>●<?php endif; ?>
                <?= htmlspecialchars($rawStatus); ?>
              </span>
            </div>
            
            <h3 class="goal-title"><?= htmlspecialchars($g['title']); ?></h3>
            <div class="goal-due">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
              <span>Target: <?= htmlspecialchars($g['due_date'] ?: 'Ongoing'); ?></span>
            </div>

            <div class="goal-progress-wrap">
              <div class="goal-progress-nums">
                <span style="color:var(--text-secondary); font-size:12px;">Progress</span>
                <span style="color:var(--text-primary); font-family:ui-monospace, monospace;">
                  <b class="card-curr-val"><?= $curr; ?></b> / <?= $target; ?> <?= htmlspecialchars($g['unit'] ?? '%'); ?> (<span class="card-pct-val"><?= $pct; ?></span>%)
                </span>
              </div>
              <div class="goal-bar-bg">
                <div class="goal-bar-fill <?= $barColorClass; ?>" style="width:<?= $pct; ?>%;"></div>
              </div>
            </div>

            <div class="goal-actions">
              <span style="font-size:11px; color:var(--text-muted); font-weight:700; text-transform:uppercase;">Log:</span>
              <button type="button" class="btn-step" onclick="quickStepProgress(<?= (int)$g['id']; ?>, -5)">-5</button>
              <button type="button" class="btn-step" onclick="quickStepProgress(<?= (int)$g['id']; ?>, 5)">+5</button>
              <button type="button" class="btn-step" onclick="quickStepProgress(<?= (int)$g['id']; ?>, 10)">+10</button>
              <button type="button" class="btn-step" onclick="quickCompleteGoal(<?= (int)$g['id']; ?>)" style="color:#10B981; font-weight:700;">Max</button>

              <div style="margin-left:auto; display:flex; align-items:center; gap:4px;">
                <button type="button" class="btn-icon-action" title="Edit Objective" onclick="openEditGoalModal(<?= (int)$g['id']; ?>)">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                </button>
                <button type="button" class="btn-icon-action delete" title="Delete Objective" onclick="deleteGoal(<?= (int)$g['id']; ?>)">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                </button>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

  </main>
</div>

<!-- Add Goal Modal -->
<div class="modal-overlay" id="goalModal">
  <div class="modal-card">
    <div style="display:flex; align-items:center; justify-content:space-between; padding:16px 20px; border-bottom:1px solid var(--border-base);">
      <h3 style="margin:0; font-size:16px; font-weight:700; color:var(--text-primary); display:flex; align-items:center; gap:8px;">
        <span>🎯</span> Create Objective
      </h3>
      <button type="button" aria-label="Close modal" onclick="closeGoalModal()" style="background:none; border:none; font-size:20px; cursor:pointer; color:var(--text-muted);">&times;</button>
    </div>
    
    <form id="goalForm" onsubmit="handleCreateGoal(event)" style="padding:20px;">
      <div style="margin-bottom:14px;">
        <label class="form-label" style="display:block; font-size:12.5px; font-weight:700; color:var(--text-primary); margin-bottom:6px;">Objective Title *</label>
        <input type="text" id="goalTitle" required placeholder="e.g. Launch Mobile App Beta / Reach 99.9% Uptime" class="form-input" style="width:100%; border:1px solid var(--border-base); background:var(--bg-surface); color:var(--text-primary); border-radius:var(--radius-sm); padding:8px 12px; font-size:13.5px;" />
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:14px;">
        <div>
          <label class="form-label" style="display:block; font-size:12.5px; font-weight:700; color:var(--text-primary); margin-bottom:6px;">Category</label>
          <select id="goalCategory" class="form-select" style="width:100%; border:1px solid var(--border-base); background:var(--bg-surface); color:var(--text-primary); border-radius:var(--radius-sm); padding:8px 12px; font-size:13.5px;">
            <option value="Productivity">Productivity</option>
            <option value="Learning">Learning</option>
            <option value="Engineering">Engineering</option>
            <option value="Design">Design</option>
            <option value="Operations">Operations</option>
            <option value="Personal">Personal</option>
          </select>
        </div>
        <div>
          <label class="form-label" style="display:block; font-size:12.5px; font-weight:700; color:var(--text-primary); margin-bottom:6px;">Initial Status</label>
          <select id="goalStatus" class="form-select" style="width:100%; border:1px solid var(--border-base); background:var(--bg-surface); color:var(--text-primary); border-radius:var(--radius-sm); padding:8px 12px; font-size:13.5px;">
            <option value="On Track">On Track</option>
            <option value="At Risk">At Risk</option>
            <option value="Behind">Behind</option>
            <option value="Completed">Completed</option>
          </select>
        </div>
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px; margin-bottom:14px;">
        <div>
          <label class="form-label" style="display:block; font-size:12.5px; font-weight:700; color:var(--text-primary); margin-bottom:6px;">Current</label>
          <input type="number" id="goalCurrent" value="0" min="0" class="form-input" style="width:100%; border:1px solid var(--border-base); background:var(--bg-surface); color:var(--text-primary); border-radius:var(--radius-sm); padding:8px 12px; font-size:13.5px;" />
        </div>
        <div>
          <label class="form-label" style="display:block; font-size:12.5px; font-weight:700; color:var(--text-primary); margin-bottom:6px;">Target *</label>
          <input type="number" id="goalTarget" value="100" min="1" required class="form-input" style="width:100%; border:1px solid var(--border-base); background:var(--bg-surface); color:var(--text-primary); border-radius:var(--radius-sm); padding:8px 12px; font-size:13.5px;" />
        </div>
        <div>
          <label class="form-label" style="display:block; font-size:12.5px; font-weight:700; color:var(--text-primary); margin-bottom:6px;">Unit</label>
          <input type="text" id="goalUnit" value="%" class="form-input" style="width:100%; border:1px solid var(--border-base); background:var(--bg-surface); color:var(--text-primary); border-radius:var(--radius-sm); padding:8px 12px; font-size:13.5px;" />
        </div>
      </div>

      <div style="margin-bottom:20px;">
        <label class="form-label" style="display:block; font-size:12.5px; font-weight:700; color:var(--text-primary); margin-bottom:6px;">Target Deadline</label>
        <input type="text" id="goalDueDate" placeholder="e.g. End of Q4 / Dec 31 / Sprint 12" class="form-input" style="width:100%; border:1px solid var(--border-base); background:var(--bg-surface); color:var(--text-primary); border-radius:var(--radius-sm); padding:8px 12px; font-size:13.5px;" />
      </div>

      <div style="display:flex; justify-content:flex-end; gap:8px;">
        <button type="button" onclick="closeGoalModal()" class="btn-secondary" style="padding:8px 16px; border-radius:var(--radius-sm); font-size:13px; font-weight:600; cursor:pointer;">Cancel</button>
        <button type="submit" class="btn-save" style="padding:8px 18px; border-radius:var(--radius-sm); font-size:13px;">Create Objective</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Goal Modal -->
<div class="modal-overlay" id="editGoalModal">
  <div class="modal-card">
    <div style="display:flex; align-items:center; justify-content:space-between; padding:16px 20px; border-bottom:1px solid var(--border-base);">
      <h3 style="margin:0; font-size:16px; font-weight:700; color:var(--text-primary); display:flex; align-items:center; gap:8px;">
        <span>✏️</span> Edit Objective
      </h3>
      <button type="button" aria-label="Close modal" onclick="closeEditGoalModal()" style="background:none; border:none; font-size:20px; cursor:pointer; color:var(--text-muted);">&times;</button>
    </div>
    
    <form id="editGoalForm" onsubmit="handleEditGoalSubmit(event)" style="padding:20px;">
      <input type="hidden" id="editGoalId" />
      <div style="margin-bottom:14px;">
        <label class="form-label" style="display:block; font-size:12.5px; font-weight:700; color:var(--text-primary); margin-bottom:6px;">Objective Title *</label>
        <input type="text" id="editGoalTitle" required class="form-input" style="width:100%; border:1px solid var(--border-base); background:var(--bg-surface); color:var(--text-primary); border-radius:var(--radius-sm); padding:8px 12px; font-size:13.5px;" />
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:14px;">
        <div>
          <label class="form-label" style="display:block; font-size:12.5px; font-weight:700; color:var(--text-primary); margin-bottom:6px;">Category</label>
          <select id="editGoalCategory" class="form-select" style="width:100%; border:1px solid var(--border-base); background:var(--bg-surface); color:var(--text-primary); border-radius:var(--radius-sm); padding:8px 12px; font-size:13.5px;">
            <option value="Productivity">Productivity</option>
            <option value="Learning">Learning</option>
            <option value="Engineering">Engineering</option>
            <option value="Design">Design</option>
            <option value="Operations">Operations</option>
            <option value="Personal">Personal</option>
          </select>
        </div>
        <div>
          <label class="form-label" style="display:block; font-size:12.5px; font-weight:700; color:var(--text-primary); margin-bottom:6px;">Status</label>
          <select id="editGoalStatus" class="form-select" style="width:100%; border:1px solid var(--border-base); background:var(--bg-surface); color:var(--text-primary); border-radius:var(--radius-sm); padding:8px 12px; font-size:13.5px;">
            <option value="On Track">On Track</option>
            <option value="At Risk">At Risk</option>
            <option value="Behind">Behind</option>
            <option value="Completed">Completed</option>
          </select>
        </div>
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px; margin-bottom:14px;">
        <div>
          <label class="form-label" style="display:block; font-size:12.5px; font-weight:700; color:var(--text-primary); margin-bottom:6px;">Current</label>
          <input type="number" id="editGoalCurrent" min="0" class="form-input" style="width:100%; border:1px solid var(--border-base); background:var(--bg-surface); color:var(--text-primary); border-radius:var(--radius-sm); padding:8px 12px; font-size:13.5px;" />
        </div>
        <div>
          <label class="form-label" style="display:block; font-size:12.5px; font-weight:700; color:var(--text-primary); margin-bottom:6px;">Target *</label>
          <input type="number" id="editGoalTarget" min="1" required class="form-input" style="width:100%; border:1px solid var(--border-base); background:var(--bg-surface); color:var(--text-primary); border-radius:var(--radius-sm); padding:8px 12px; font-size:13.5px;" />
        </div>
        <div>
          <label class="form-label" style="display:block; font-size:12.5px; font-weight:700; color:var(--text-primary); margin-bottom:6px;">Unit</label>
          <input type="text" id="editGoalUnit" class="form-input" style="width:100%; border:1px solid var(--border-base); background:var(--bg-surface); color:var(--text-primary); border-radius:var(--radius-sm); padding:8px 12px; font-size:13.5px;" />
        </div>
      </div>

      <div style="margin-bottom:20px;">
        <label class="form-label" style="display:block; font-size:12.5px; font-weight:700; color:var(--text-primary); margin-bottom:6px;">Target Deadline</label>
        <input type="text" id="editGoalDueDate" class="form-input" style="width:100%; border:1px solid var(--border-base); background:var(--bg-surface); color:var(--text-primary); border-radius:var(--radius-sm); padding:8px 12px; font-size:13.5px;" />
      </div>

      <div style="display:flex; justify-content:flex-end; gap:8px;">
        <button type="button" onclick="closeEditGoalModal()" class="btn-secondary" style="padding:8px 16px; border-radius:var(--radius-sm); font-size:13px; font-weight:600; cursor:pointer;">Cancel</button>
        <button type="submit" class="btn-save" style="padding:8px 18px; border-radius:var(--radius-sm); font-size:13px;">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<script src="assets/js/app.js"></script>
<script>
// Modal Controls
window.openGoalModal = function() {
  const modal = document.getElementById('goalModal');
  if (modal) modal.classList.add('active');
};
window.closeGoalModal = function() {
  const modal = document.getElementById('goalModal');
  if (modal) modal.classList.remove('active');
};

window.openEditGoalModal = function(id) {
  const card = document.getElementById('goalCard-' + id);
  if (!card) return;

  document.getElementById('editGoalId').value = id;
  document.getElementById('editGoalTitle').value = card.getAttribute('data-title') || '';
  document.getElementById('editGoalCategory').value = card.getAttribute('data-category') || 'Productivity';
  document.getElementById('editGoalStatus').value = card.getAttribute('data-raw-status') || 'On Track';
  document.getElementById('editGoalCurrent').value = card.getAttribute('data-current') || 0;
  document.getElementById('editGoalTarget').value = card.getAttribute('data-target') || 100;
  document.getElementById('editGoalUnit').value = card.getAttribute('data-unit') || '%';
  document.getElementById('editGoalDueDate').value = card.getAttribute('data-due') || '';

  const modal = document.getElementById('editGoalModal');
  if (modal) modal.classList.add('active');
};

window.closeEditGoalModal = function() {
  const modal = document.getElementById('editGoalModal');
  if (modal) modal.classList.remove('active');
};

// Filter Goals Tab
window.filterGoals = function(status, btn) {
  document.querySelectorAll('.goals-tab-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');

  const cards = document.querySelectorAll('.goal-card');
  cards.forEach(c => {
    const cardStatus = c.getAttribute('data-status');
    if (status === 'all' || cardStatus === status) {
      c.style.display = 'flex';
    } else {
      c.style.display = 'none';
    }
  });
};

// Create Goal Handler
window.handleCreateGoal = async function(e) {
  e.preventDefault();
  const submitBtn = e.target.querySelector('button[type="submit"]');
  if (submitBtn) {
    submitBtn.disabled = true;
    submitBtn.textContent = 'Creating...';
  }

  const payload = {
    action: 'create',
    title: document.getElementById('goalTitle').value.trim(),
    category: document.getElementById('goalCategory').value,
    status: document.getElementById('goalStatus').value,
    current_value: parseInt(document.getElementById('goalCurrent').value, 10) || 0,
    target_value: parseInt(document.getElementById('goalTarget').value, 10) || 100,
    unit: document.getElementById('goalUnit').value.trim() || '%',
    due_date: document.getElementById('goalDueDate').value.trim() || 'Ongoing'
  };

  try {
    const res = await secureFetch('api/goals.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const data = await res.json();
    if (data.success) {
      if (typeof showToast === 'function') showToast(data.message || 'Objective created.');
      closeGoalModal();
      setTimeout(() => location.reload(), 400);
    } else {
      alert(data.message || 'Failed to create goal');
    }
  } catch (err) {
    console.error(err);
  } finally {
    if (submitBtn) {
      submitBtn.disabled = false;
      submitBtn.textContent = 'Create Objective';
    }
  }
};

// Edit Goal Handler
window.handleEditGoalSubmit = async function(e) {
  e.preventDefault();
  const submitBtn = e.target.querySelector('button[type="submit"]');
  if (submitBtn) {
    submitBtn.disabled = true;
    submitBtn.textContent = 'Saving...';
  }

  const payload = {
    action: 'update',
    goal_id: parseInt(document.getElementById('editGoalId').value, 10),
    title: document.getElementById('editGoalTitle').value.trim(),
    category: document.getElementById('editGoalCategory').value,
    status: document.getElementById('editGoalStatus').value,
    current_value: parseInt(document.getElementById('editGoalCurrent').value, 10) || 0,
    target_value: parseInt(document.getElementById('editGoalTarget').value, 10) || 100,
    unit: document.getElementById('editGoalUnit').value.trim() || '%',
    due_date: document.getElementById('editGoalDueDate').value.trim() || 'Ongoing'
  };

  try {
    const res = await secureFetch('api/goals.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const data = await res.json();
    if (data.success) {
      if (typeof showToast === 'function') showToast(data.message || 'Objective updated.');
      closeEditGoalModal();
      setTimeout(() => location.reload(), 400);
    } else {
      alert(data.message || 'Failed to update goal');
    }
  } catch (err) {
    console.error(err);
  } finally {
    if (submitBtn) {
      submitBtn.disabled = false;
      submitBtn.textContent = 'Save Changes';
    }
  }
};

// Quick Progress Adjuster (+5, -5, +10)
window.quickStepProgress = async function(goalId, step) {
  const card = document.getElementById('goalCard-' + goalId);
  if (!card) return;

  const target = parseInt(card.getAttribute('data-target'), 10) || 100;
  const current = parseInt(card.getAttribute('data-current'), 10) || 0;
  const rawStatus = card.getAttribute('data-raw-status') || 'On Track';

  const newCurrent = Math.max(0, Math.min(target, current + step));
  let newStatus = rawStatus;
  if (newCurrent >= target) {
    newStatus = 'Completed';
  } else if (rawStatus === 'Completed' && newCurrent < target) {
    newStatus = 'On Track';
  }

  try {
    const res = await secureFetch('api/goals.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'update_progress',
        goal_id: goalId,
        current_value: newCurrent,
        status: newStatus
      })
    });
    const data = await res.json();
    if (data.success) {
      if (typeof showToast === 'function') showToast('Progress updated.');
      setTimeout(() => location.reload(), 300);
    } else {
      alert(data.message || 'Failed to update progress');
    }
  } catch (err) {
    console.error(err);
  }
};

// Quick Complete Goal
window.quickCompleteGoal = async function(goalId) {
  const card = document.getElementById('goalCard-' + goalId);
  if (!card) return;
  const target = parseInt(card.getAttribute('data-target'), 10) || 100;

  try {
    const res = await secureFetch('api/goals.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'update_progress',
        goal_id: goalId,
        current_value: target,
        status: 'Completed'
      })
    });
    const data = await res.json();
    if (data.success) {
      if (typeof showToast === 'function') showToast('Objective marked Completed! 🎉');
      setTimeout(() => location.reload(), 300);
    } else {
      alert(data.message || 'Failed to complete goal');
    }
  } catch (err) {
    console.error(err);
  }
};

// Delete Goal
window.deleteGoal = async function(goalId) {
  if (!confirm('Are you sure you want to remove this objective?')) return;
  try {
    const res = await secureFetch('api/goals.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'delete', goal_id: goalId })
    });
    const data = await res.json();
    if (data.success) {
      const el = document.getElementById('goalCard-' + goalId);
      if (el) el.remove();
      if (typeof showToast === 'function') showToast('Objective removed.');
      setTimeout(() => location.reload(), 300);
    } else {
      alert(data.message || 'Failed to delete goal');
    }
  } catch (err) {
    console.error(err);
  }
};
</script>
</body>
</html>
