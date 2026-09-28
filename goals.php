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

// Fetch Goals
$stmtGoals = $db->prepare("SELECT * FROM project_goals WHERE user_id = :uid ORDER BY id DESC");
$stmtGoals->execute(['uid' => $userId]);
$goals = $stmtGoals->fetchAll();

$pageTitle = 'Mindrift — Goals & OKRs';
include __DIR__ . '/includes/head.php';
?>
<style>
  .goals-header-row { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; }
  .goals-title { margin: 0; font-size: 20px; font-weight: 700; color: var(--ink); }
  .goals-subtitle { margin: 4px 0 0; color: var(--muted); font-size: 13.5px; font-weight: 400; }

  .goals-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 16px; }
  .goal-card {
    background: var(--panel-bg); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 18px;
    display: flex; flex-direction: column; transition: border-color 0.15s ease;
  }
  .goal-card:hover { border-color: var(--border-focus); }
  
  .goal-meta { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
  .goal-category {
    font-size: 11px; font-weight: 600; text-transform: uppercase; padding: 2px 8px; border-radius: var(--radius-xs);
    background: var(--bg); border: 1px solid var(--border); color: var(--ink); letter-spacing: 0.03em;
  }
  .goal-status-badge {
    font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: var(--radius-xs);
  }
  .status-on-track { background: #DCFCE7; color: #16A34A; }
  .status-at-risk { background: #FEF9C3; color: #CA8A04; }
  .status-behind { background: #FEE2E2; color: #DC2626; }
  [data-theme="dark"] .status-on-track { background: #064E3B; color: #4ADE80; }
  [data-theme="dark"] .status-at-risk { background: #713F12; color: #FACC15; }
  [data-theme="dark"] .status-behind { background: #7F1D1D; color: #F87171; }

  .goal-title { margin: 0 0 6px; font-size: 15px; font-weight: 600; color: var(--ink); line-height: 1.35; }
  .goal-due { font-size: 12px; color: var(--muted); font-weight: 500; margin-bottom: 16px; }

  .goal-progress-wrap { margin-top: auto; }
  .goal-progress-nums { display: flex; justify-content: space-between; font-size: 12.5px; font-weight: 600; margin-bottom: 6px; }
  .goal-bar-bg { width: 100%; height: 6px; background: var(--border); border-radius: var(--radius-xs); overflow: hidden; }
  .goal-bar-fill { height: 100%; border-radius: var(--radius-xs); background: var(--blue); transition: width 0.3s ease; }
  .goal-bar-fill.warning { background: #F59E0B; }

  .goal-actions { display: flex; gap: 8px; align-items: center; margin-top: 14px; padding-top: 12px; border-top: 1px solid var(--border); }
  .btn-step {
    padding: 3px 8px; font-size: 11.5px; font-weight: 600; border: 1px solid var(--border); background: var(--panel-bg);
    border-radius: var(--radius-xs); cursor: pointer; color: var(--ink); transition: background 0.15s ease;
  }
  .btn-step:hover { background: var(--bg); border-color: var(--border-focus); }
</style>
</head>
<body>

<div class="app" id="app">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>

  <main class="main">
    <?php include __DIR__ . '/includes/header.php'; ?>

    <div class="goals-header-row">
      <div>
        <h2 class="goals-title">Objectives &amp; Key Results</h2>
        <p class="goals-subtitle">Track project targets and deliverables</p>
      </div>
      <button type="button" class="btn-save" style="display:inline-flex; align-items:center; gap:6px;" onclick="openGoalModal()">
        New Objective
      </button>
    </div>

    <!-- Goals Grid -->
    <div class="goals-grid">
      <?php if (empty($goals)): ?>
        <div style="grid-column:1/-1; text-align:center; padding:48px 20px; background:var(--panel-bg); border:1px solid var(--border); border-radius:var(--radius-sm);">
          <div style="font-size:15px; font-weight:600; color:var(--ink); margin-bottom:4px;">No objectives created yet</div>
          <p style="color:var(--muted); font-size:13px; margin:0 0 16px;">Set targets and log progress toward your project milestones.</p>
          <button type="button" class="btn-save" onclick="openGoalModal()">Create Objective</button>
        </div>
      <?php else: ?>
        <?php foreach ($goals as $g): 
          $target = max(1, (int)$g['target_value']);
          $curr = min($target, (int)$g['current_value']);
          $pct = min(100, round(($curr / $target) * 100));
          $stClass = strtolower(str_replace(' ', '-', $g['status'] ?? 'on-track'));
        ?>
          <div class="goal-card" id="goalCard-<?= $g['id']; ?>">
            <div class="goal-meta">
              <span class="goal-category"><?= htmlspecialchars($g['category'] ?? 'Productivity'); ?></span>
              <span class="goal-status-badge status-<?= $stClass; ?>"><?= htmlspecialchars($g['status'] ?? 'On Track'); ?></span>
            </div>
            
            <h3 class="goal-title"><?= htmlspecialchars($g['title']); ?></h3>
            <div class="goal-due">Target: <?= htmlspecialchars($g['due_date'] ?? 'End of Quarter'); ?></div>

            <div class="goal-progress-wrap">
              <div class="goal-progress-nums">
                <span style="color:var(--muted);">Progress</span>
                <span style="color:var(--ink);"><?= $curr; ?> / <?= $target; ?> <?= htmlspecialchars($g['unit'] ?? '%'); ?> (<?= $pct; ?>%)</span>
              </div>
              <div class="goal-bar-bg">
                <div class="goal-bar-fill <?= $stClass === 'at-risk' ? 'warning' : ''; ?>" style="width:<?= $pct; ?>%;"></div>
              </div>
            </div>

            <div class="goal-actions">
              <span style="font-size:11.5px; color:var(--muted); font-weight:600;">Log:</span>
              <button type="button" class="btn-step" onclick="updateGoalProgress(<?= $g['id']; ?>, <?= min($target, $curr + 5); ?>, '<?= $g['status']; ?>')">+5</button>
              <button type="button" class="btn-step" onclick="updateGoalProgress(<?= $g['id']; ?>, <?= min($target, $curr + 10); ?>, '<?= $g['status']; ?>')">+10</button>
              <button type="button" class="btn-step" onclick="updateGoalProgress(<?= $g['id']; ?>, <?= $target; ?>, 'Completed')" style="color:#10B981; font-weight:600;">Max</button>
              <button type="button" title="Delete Goal" onclick="deleteGoal(<?= $g['id']; ?>)" style="margin-left:auto; background:none; border:none; color:var(--muted); cursor:pointer; font-size:13px; display:inline-flex; align-items:center;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
              </button>
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
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:16px;">
      <h3 style="margin:0; font-size:16px; font-weight:700; color:var(--ink);">Create Objective</h3>
      <button type="button" onclick="closeGoalModal()" style="background:none; border:none; font-size:20px; cursor:pointer; color:var(--muted);">&times;</button>
    </div>
    
    <form id="goalForm" onsubmit="handleCreateGoal(event)">
      <div style="margin-bottom:12px;">
        <label style="display:block; font-size:12.5px; font-weight:700; color:var(--ink); margin-bottom:4px;">Objective Title</label>
        <input type="text" id="goalTitle" required placeholder="e.g. Complete Q4 Cloud Migration" style="width:100%; padding:9px 12px; border:1px solid var(--border); border-radius:8px; background:var(--card-bg); color:var(--ink); font-size:13.5px;" />
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;">
        <div>
          <label style="display:block; font-size:12.5px; font-weight:700; color:var(--ink); margin-bottom:4px;">Category</label>
          <select id="goalCategory" style="width:100%; padding:9px 12px; border:1px solid var(--border); border-radius:8px; background:var(--card-bg); color:var(--ink); font-size:13.5px;">
            <option value="Productivity">Productivity</option>
            <option value="Learning">Learning</option>
            <option value="Engineering">Engineering</option>
            <option value="Design">Design</option>
          </select>
        </div>
        <div>
          <label style="display:block; font-size:12.5px; font-weight:700; color:var(--ink); margin-bottom:4px;">Status</label>
          <select id="goalStatus" style="width:100%; padding:9px 12px; border:1px solid var(--border); border-radius:8px; background:var(--card-bg); color:var(--ink); font-size:13.5px;">
            <option value="On Track">On Track</option>
            <option value="At Risk">At Risk</option>
            <option value="Behind">Behind</option>
          </select>
        </div>
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px; margin-bottom:12px;">
        <div>
          <label style="display:block; font-size:12.5px; font-weight:700; color:var(--ink); margin-bottom:4px;">Current</label>
          <input type="number" id="goalCurrent" value="0" min="0" style="width:100%; padding:9px 12px; border:1px solid var(--border); border-radius:8px; background:var(--card-bg); color:var(--ink); font-size:13.5px;" />
        </div>
        <div>
          <label style="display:block; font-size:12.5px; font-weight:700; color:var(--ink); margin-bottom:4px;">Target</label>
          <input type="number" id="goalTarget" value="100" min="1" style="width:100%; padding:9px 12px; border:1px solid var(--border); border-radius:8px; background:var(--card-bg); color:var(--ink); font-size:13.5px;" />
        </div>
        <div>
          <label style="display:block; font-size:12.5px; font-weight:700; color:var(--ink); margin-bottom:4px;">Unit</label>
          <input type="text" id="goalUnit" value="%" style="width:100%; padding:9px 12px; border:1px solid var(--border); border-radius:8px; background:var(--card-bg); color:var(--ink); font-size:13.5px;" />
        </div>
      </div>

      <div style="margin-bottom:18px;">
        <label style="display:block; font-size:12.5px; font-weight:700; color:var(--ink); margin-bottom:4px;">Target Deadline</label>
        <input type="text" id="goalDueDate" placeholder="e.g. End of Q4 / Dec 31" style="width:100%; padding:9px 12px; border:1px solid var(--border); border-radius:8px; background:var(--card-bg); color:var(--ink); font-size:13.5px;" />
      </div>

      <div style="display:flex; justify-content:flex-end; gap:8px;">
        <button type="button" onclick="closeGoalModal()" style="padding:8px 16px; border:1px solid var(--border); border-radius:6px; background:none; color:var(--ink); font-weight:600; cursor:pointer;">Cancel</button>
        <button type="submit" class="btn-save">Create Objective</button>
      </div>
    </form>
  </div>
</div>

<script src="assets/js/app.js"></script>
<script>
function openGoalModal() {
  document.getElementById('goalModal').classList.add('active');
}
function closeGoalModal() {
  document.getElementById('goalModal').classList.remove('active');
}

async function handleCreateGoal(e) {
  e.preventDefault();
  const payload = {
    action: 'create',
    title: document.getElementById('goalTitle').value.trim(),
    category: document.getElementById('goalCategory').value,
    status: document.getElementById('goalStatus').value,
    current_value: parseInt(document.getElementById('goalCurrent').value) || 0,
    target_value: parseInt(document.getElementById('goalTarget').value) || 100,
    unit: document.getElementById('goalUnit').value.trim() || '%',
    due_date: document.getElementById('goalDueDate').value.trim() || 'End of Quarter'
  };

  try {
    const res = await secureFetch('api/goals.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const data = await res.json();
    if (data.success) {
      if (typeof showToast === 'function') showToast('Objective created.');
      setTimeout(() => location.reload(), 400);
    } else {
      alert(data.message || 'Failed to create goal');
    }
  } catch (err) {
    console.error(err);
  }
}

async function updateGoalProgress(goalId, newCurrent, status) {
  try {
    const res = await secureFetch('api/goals.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'update_progress', goal_id: goalId, current_value: newCurrent, status: status })
    });
    const data = await res.json();
    if (data.success) {
      if (typeof showToast === 'function') showToast('Progress updated.');
      setTimeout(() => location.reload(), 300);
    }
  } catch (err) {
    console.error(err);
  }
}

async function deleteGoal(goalId) {
  if (!confirm('Are you sure you want to remove this objective?')) return;
  try {
    const res = await secureFetch('api/goals.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'delete', goal_id: goalId })
    });
    const data = await res.json();
    if (data.success) {
      const el = document.getElementById(`goalCard-${goalId}`);
      if (el) el.remove();
      if (typeof showToast === 'function') showToast('Objective removed.');
    }
  } catch (err) {
    console.error(err);
  }
}
</script>
</body>
</html>
