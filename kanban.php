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

// Fetch Tasks for Kanban Columns
$stmtTasks = $db->prepare("SELECT * FROM tasks WHERE user_id = :uid AND (deleted_at IS NULL) ORDER BY id DESC");
$stmtTasks->execute(['uid' => $userId]);
$allTasks = $stmtTasks->fetchAll();

$columns = [
    'Open' => [],
    'In Progress' => [],
    'Review' => [],
    'Done' => []
];

foreach ($allTasks as $t) {
    $st = $t['status'] ?? 'Open';
    if (!isset($columns[$st])) {
        $columns[$st] = [];
    }
    $columns[$st][] = $t;
}

if (!function_exists('renderPriorityBadge')) {
    function renderPriorityBadge(?string $prio): string {
        $p = ucfirst(strtolower($prio ?? 'Medium'));
        if (!in_array($p, ['Urgent', 'High', 'Medium', 'Low'])) $p = 'Medium';
        $cls = 'priority-' . strtolower($p);
        return '<span class="priority-badge ' . $cls . '">' . htmlspecialchars($p) . '</span>';
    }
}

$pageTitle = 'Mindrift — Kanban Board';
include __DIR__ . '/includes/head.php';
?>
<style>
  .kanban-page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
  .kanban-title-wrap { display: flex; align-items: center; gap: 10px; }
  .kanban-title { margin: 0; font-size: 20px; font-weight: 700; letter-spacing: -0.02em; color: var(--text-primary); }

  .move-status-select {
    font-size: 11.5px; font-weight: 500; padding: 4px 8px; border-radius: var(--radius-xs); border: 1px solid var(--border-base); background: var(--bg-surface); color: var(--text-primary); cursor: pointer;
  }
</style>
</head>
<body>

<div class="app" id="app">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>

  <main class="main">
    <?php include __DIR__ . '/includes/header.php'; ?>

    <div class="kanban-page-header">
      <div class="kanban-title-wrap">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--brand-primary)" stroke-width="2.2"><rect x="3" y="3" width="5" height="18" rx="1"/><rect x="11" y="3" width="5" height="12" rx="1"/><rect x="19" y="3" width="5" height="15" rx="1"/></svg>
        <h2 class="kanban-title">Kanban Board</h2>
      </div>
      <span style="font-size:13px; color:var(--text-secondary); font-weight:500;">Drag cards between columns or use the dropdown to change status.</span>
    </div>

    <!-- 4 Kanban Columns -->
    <div class="kanban-board-grid">
      <?php foreach (['Open', 'In Progress', 'Review', 'Done'] as $colName): 
        $colTasks = $columns[$colName] ?? [];
      ?>
        <div class="kanban-column" data-status="<?= $colName; ?>">
          <div class="column-head">
            <div class="column-title-flex" style="display:flex; align-items:center; justify-content:space-between; width:100%;">
              <div style="display:flex; align-items:center; gap:8px;">
                <span><?= $colName; ?></span>
                <span class="column-count-badge" id="count-<?= preg_replace('/\s+/', '', $colName); ?>"><?= count($colTasks); ?></span>
              </div>
              <button type="button" class="btn-step" onclick="openKanbanAddModal('<?= $colName; ?>')" title="Add Task to <?= $colName; ?>" style="padding:1px 7px; font-size:12px; font-weight:800; cursor:pointer; line-height:1.4;">+</button>
            </div>
          </div>

          <div class="kanban-card-list">
            <?php if (empty($colTasks)): ?>
              <div class="kanban-empty-hint" style="text-align:center; padding:32px 10px; color:var(--text-muted); font-size:12px; font-style:italic;">No tasks in <?= htmlspecialchars($colName); ?></div>
            <?php else: ?>
              <?php foreach ($colTasks as $t): ?>
                <div class="kanban-card" draggable="true" data-task-id="<?= $t['id']; ?>">
                  <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:6px;">
                    <div class="card-project-tag"><?= htmlspecialchars($t['project_name']); ?></div>
                    <?= renderPriorityBadge($t['priority'] ?? 'Medium'); ?>
                  </div>
                  <h4 class="card-task-title"><?= htmlspecialchars($t['task_name']); ?></h4>
                  <div class="card-footer-flex">
                    <span><?= htmlspecialchars($t['due_date'] ?? $t['start_date']); ?></span>
                    <select class="move-status-select" 
                            onchange="moveKanbanTask(<?= (int)$t['id']; ?>, this.value, this)"
                            onclick="event.stopPropagation();"
                            onmousedown="event.stopPropagation();"
                            draggable="false"
                            aria-label="Change status">
                      <?php foreach (['Open', 'In Progress', 'Review', 'Done'] as $opt): ?>
                        <option value="<?= $opt; ?>" <?= ($opt === $colName) ? 'selected' : ''; ?>><?= $opt; ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

  </main>
</div>

<!-- Modal: Add New Task from Kanban -->
<div class="modal-overlay" id="kanbanAddModal">
  <div class="modal-card">
    <div class="modal-header">
      <h3 class="modal-title">Add Task (<span id="kanbanColTitle">Open</span>)</h3>
      <button class="modal-close-btn" aria-label="Close modal" onclick="document.getElementById('kanbanAddModal').classList.remove('active')">&times;</button>
    </div>
    <form id="kanbanAddTaskForm" onsubmit="handleKanbanAddTask(event)">
      <input type="hidden" id="kanbanTaskStatus" value="Open" />
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label" for="kanbanTaskName">Task Name</label>
          <input type="text" id="kanbanTaskName" class="form-input" placeholder="e.g. Wire Navigation Headers" required />
        </div>
        <div class="form-group">
          <label class="form-label" for="kanbanTaskProject">Project</label>
          <input type="text" id="kanbanTaskProject" class="form-input" value="General" required />
        </div>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
          <div class="form-group">
            <label class="form-label" for="kanbanTaskPriority">Priority</label>
            <select id="kanbanTaskPriority" class="form-input">
              <option value="Urgent">Urgent</option>
              <option value="High">High</option>
              <option value="Medium" selected>Medium</option>
              <option value="Low">Low</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label" for="kanbanTaskDue">Due Date</label>
            <input type="text" id="kanbanTaskDue" class="form-input" value="<?= date('d-m-Y', strtotime('+3 days')); ?>" required />
          </div>
        </div>
      </div>
      <div class="modal-footer" style="display:flex; justify-content:flex-end; gap:8px;">
        <button type="button" class="btn-cancel" onclick="document.getElementById('kanbanAddModal').classList.remove('active')">Cancel</button>
        <button type="submit" class="btn-save">Add Task</button>
      </div>
    </form>
  </div>
</div>

<script src="assets/js/app.js"></script>
<script>
// Recount column badges & empty hints
function updateColumnCounts() {
  document.querySelectorAll('.kanban-column').forEach(c => {
    const colStatus = c.getAttribute('data-status') || '';
    const badge = c.querySelector('.column-count-badge');
    const list = c.querySelector('.kanban-card-list');
    const cards = list ? list.querySelectorAll('.kanban-card') : [];
    if (badge) badge.textContent = cards.length;

    // Show empty placeholder if no cards
    if (cards.length === 0) {
      let hint = list.querySelector('.kanban-empty-hint');
      if (!hint) {
        hint = document.createElement('div');
        hint.className = 'kanban-empty-hint';
        hint.style.cssText = 'text-align:center; padding:32px 10px; color:var(--text-muted); font-size:12px; font-style:italic;';
        hint.textContent = 'No tasks in ' + colStatus;
        list.appendChild(hint);
      }
    } else {
      const hint = list.querySelector('.kanban-empty-hint');
      if (hint) hint.remove();
    }
  });
}

// Move Task via Select Dropdown
window.moveKanbanTask = async function(taskId, newStatus, selectEl) {
  const card = selectEl ? selectEl.closest('.kanban-card') : document.querySelector(`.kanban-card[data-task-id="${taskId}"]`);
  const previousColumn = card ? card.closest('.kanban-column') : null;
  const previousStatus = previousColumn ? previousColumn.getAttribute('data-status') : null;
  const targetCol = document.querySelector(`.kanban-column[data-status="${newStatus}"]`);

  // Optimistic DOM Move
  if (card && targetCol && previousColumn !== targetCol) {
    const targetList = targetCol.querySelector('.kanban-card-list');
    if (targetList) {
      const hint = targetList.querySelector('.kanban-empty-hint');
      if (hint) hint.remove();
      targetList.appendChild(card);
      updateColumnCounts();
    }
  }

  try {
    const res = await secureFetch('api/kanban.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ task_id: taskId, status: newStatus })
    });
    const data = await res.json();
    if (data.success) {
      if (typeof showToast === 'function') {
        showToast(`Task moved to ${newStatus}`);
      }
    } else {
      alert(data.message || 'Failed to update task status');
      location.reload();
    }
  } catch (err) {
    console.error('Error moving task:', err);
    location.reload();
  }
};

// Interactive HTML5 Drag-and-Drop
document.addEventListener('DOMContentLoaded', () => {
  let draggedCard = null;

  document.querySelectorAll('.kanban-card').forEach(card => {
    card.addEventListener('dragstart', (e) => {
      // Don't drag if user is clicking on select or button
      if (e.target.closest('select') || e.target.closest('button')) {
        e.preventDefault();
        return;
      }
      draggedCard = card;
      card.classList.add('dragging');
      e.dataTransfer.effectAllowed = 'move';
      e.dataTransfer.setData('text/plain', card.dataset.taskId);
    });

    card.addEventListener('dragend', () => {
      card.classList.remove('dragging');
      document.querySelectorAll('.kanban-column').forEach(c => c.classList.remove('drag-over'));
      draggedCard = null;
    });
  });

  document.querySelectorAll('.kanban-column').forEach(col => {
    col.addEventListener('dragover', (e) => {
      e.preventDefault();
      e.dataTransfer.dropEffect = 'move';
      col.classList.add('drag-over');
    });

    col.addEventListener('dragleave', (e) => {
      if (!col.contains(e.relatedTarget)) {
        col.classList.remove('drag-over');
      }
    });

    col.addEventListener('drop', async (e) => {
      e.preventDefault();
      col.classList.remove('drag-over');

      if (!draggedCard) return;

      const newStatus = col.dataset.status;
      const taskId = parseInt(draggedCard.dataset.taskId, 10);
      const targetList = col.querySelector('.kanban-card-list');

      // Update dropdown in card
      const sel = draggedCard.querySelector('.move-status-select');
      if (sel) sel.value = newStatus;

      // Move element in DOM immediately
      const hint = targetList.querySelector('.kanban-empty-hint');
      if (hint) hint.remove();
      targetList.appendChild(draggedCard);

      // Recount column badges
      updateColumnCounts();

      // Persist to backend API
      try {
        const res = await secureFetch('api/kanban.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ task_id: taskId, status: newStatus })
        });
        const data = await res.json();
        if (data.success) {
          if (typeof showToast === 'function') {
            showToast(`Task moved to ${newStatus}`);
          }
        } else {
          location.reload();
        }
      } catch (err) {
        console.error(err);
        location.reload();
      }
    });
  });
});

// Kanban Task Modal Functions
window.openKanbanAddModal = function(colStatus) {
  const modal = document.getElementById('kanbanAddModal');
  if (!modal) return;
  document.getElementById('kanbanColTitle').textContent = colStatus;
  document.getElementById('kanbanTaskStatus').value = colStatus;
  modal.classList.add('active');
  setTimeout(() => document.getElementById('kanbanTaskName')?.focus(), 100);
};

window.handleKanbanAddTask = async function(e) {
  e.preventDefault();
  const submitBtn = e.target.querySelector('button[type="submit"]');
  if (submitBtn) {
    submitBtn.disabled = true;
    submitBtn.textContent = 'Adding...';
  }

  const payload = {
    action: 'create',
    task_name: document.getElementById('kanbanTaskName').value.trim(),
    project_name: document.getElementById('kanbanTaskProject').value.trim(),
    priority: document.getElementById('kanbanTaskPriority').value,
    due_date: document.getElementById('kanbanTaskDue').value.trim(),
    start_date: '<?= date('d-m-Y'); ?>',
    assigned_to: 'Alex Morgan',
    status: document.getElementById('kanbanTaskStatus').value
  };

  try {
    const res = await secureFetch('api/tasks.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const data = await res.json();
    if (data.success) {
      if (typeof showToast === 'function') {
        showToast('Task added to ' + payload.status);
      }
      document.getElementById('kanbanAddModal').classList.remove('active');
      document.getElementById('kanbanAddTaskForm').reset();
      setTimeout(() => location.reload(), 400);
    } else {
      alert(data.message || 'Failed to add task');
    }
  } catch (err) {
    console.error(err);
  } finally {
    if (submitBtn) {
      submitBtn.disabled = false;
      submitBtn.textContent = 'Add Task';
    }
  }
};
</script>
</body>
</html>
