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

// Seed baseline task if user has 0 tasks in database
$stmtCheck = $db->prepare("SELECT COUNT(*) as cnt FROM tasks WHERE user_id = :uid");
$stmtCheck->execute(['uid' => $userId]);
if ($stmtCheck->fetch()['cnt'] == 0) {
    $db->prepare("INSERT INTO tasks (user_id, task_name, project_name, start_date, assigned_to, status, time_log)
                  VALUES (:uid, 'Task 1', '2dapp', '31-07-2026', 'unassigned', 'Open', 0)")->execute(['uid' => $userId]);
}

// Fetch All Tasks for User from SQL Database
$stmtTasks = $db->prepare("SELECT * FROM tasks WHERE user_id = :uid ORDER BY id ASC");
$stmtTasks->execute(['uid' => $userId]);
$tasks = $stmtTasks->fetchAll();
$taskCount = count($tasks);

// Fetch Group Assignments and Courses
$stmtGroup = $db->query("SELECT * FROM group_assignments ORDER BY id DESC");
$groupAssignments = $stmtGroup ? $stmtGroup->fetchAll() : [];

$stmtCourses = $db->prepare("SELECT id, name FROM courses WHERE user_id = :uid ORDER BY id ASC");
$stmtCourses->execute(['uid' => $userId]);
$userCourses = $stmtCourses ? $stmtCourses->fetchAll() : [];

if (!function_exists('renderPriorityBadge')) {
    function renderPriorityBadge(?string $prio): string {
        $p = ucfirst(strtolower($prio ?? 'Medium'));
        if (!in_array($p, ['Urgent', 'High', 'Medium', 'Low'])) $p = 'Medium';
        $cls = 'priority-' . strtolower($p);
        return '<span class="priority-badge ' . $cls . '">' . htmlspecialchars($p) . '</span>';
    }
}

if (!function_exists('renderDueBadge')) {
    function renderDueBadge(?string $dueDate): string {
        if (empty($dueDate)) return '<span style="color:var(--muted); font-size:12px;">No deadline</span>';
        $ts = strtotime(str_replace('/', '-', $dueDate));
        if (!$ts) return '<span class="due-badge normal">' . htmlspecialchars($dueDate) . '</span>';
        $diffDays = (int)round(($ts - time()) / 86400);
        if ($diffDays < 0) {
            return '<span class="due-badge overdue">' . abs($diffDays) . 'd overdue</span>';
        } elseif ($diffDays === 0) {
            return '<span class="due-badge today">Due today</span>';
        } elseif ($diffDays === 1) {
            return '<span class="due-badge soon">Tomorrow</span>';
        } else {
            return '<span class="due-badge normal">' . date('M j', $ts) . ' (' . $diffDays . 'd)</span>';
        }
    }
}

$pageTitle = 'Mindrift — My Tasks';
include __DIR__ . '/includes/head.php';
?>
<style>
  /* My Tasks View */
  .my-tasks-page-header {
    display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;
  }
  .tasks-header-title-wrap { display: flex; align-items: center; gap: 8px; }
  .tasks-header-ic { color: #005C8A; }
  .my-tasks-title { margin: 0; font-size: 19px; font-weight: 800; color: #111827; }
  .close-icon-btn { background: none; border: none; font-size: 18px; color: #6B7280; cursor: pointer; }

  /* Tab Navigation */
  .tasks-tab-nav {
    display: flex; align-items: center; gap: 20px; border-bottom: 1px solid #E5E7EB; margin-bottom: 14px;
  }
  .tasks-tab-item {
    font-size: 14px; font-weight: 600; color: #6B7280; padding-bottom: 8px; position: relative; cursor: pointer; transition: color 0.15s ease;
  }
  .tasks-tab-item:hover { color: #111827; }
  .tasks-tab-item.active {
    color: #0E65C7; font-weight: 700;
  }
  .tasks-tab-item.active::after {
    content: ''; position: absolute; bottom: -1px; left: 0; right: 0; height: 2px; background: #0E65C7;
  }
  [data-theme="dark"] .tasks-tab-item { color: #9CA3AF; }
  [data-theme="dark"] .tasks-tab-item:hover { color: #F9FAFB; }
  [data-theme="dark"] .tasks-tab-item.active { color: #60A5FA; }
  [data-theme="dark"] .tasks-tab-item.active::after { background: #60A5FA; }

  /* Toolbar Info Bar */
  .tasks-toolbar-bar {
    display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; font-size: 13px; color: #374151;
  }
  .toolbar-left { display: flex; align-items: center; gap: 16px; }
  .toolbar-link { color: #4B5563; text-decoration: none; font-weight: 500; cursor: pointer; }
  .toolbar-link:hover { color: #111827; }
  
  .toolbar-right { display: flex; align-items: center; gap: 16px; }
  .tool-btn-action {
    display: flex; align-items: center; gap: 6px; color: #4B5563; background: none; border: none; font-size: 13px; font-weight: 500; cursor: pointer;
  }
  .tool-btn-action:hover { color: #111827; }

  /* Task Table Layout */
  .tasks-data-table-wrap {
    width: 100%; border: 1px solid #E5E7EB; border-radius: 6px; overflow: hidden; background: #FFFFFF;
  }
  .tasks-data-table { width: 100%; border-collapse: collapse; text-align: left; }
  
  .tasks-data-table th {
    background: #F9FAFB; padding: 12px 16px; font-size: 13px; font-weight: 600; color: #4B5563; border-bottom: 1px solid #E5E7EB; border-right: 1px solid #F0F1F3;
  }
  .tasks-data-table th:last-child { border-right: none; }
  
  .tasks-data-table td {
    padding: 12px 16px; font-size: 13.5px; color: #111827; border-bottom: 1px solid #F0F1F3; border-right: 1px solid #F4F5F7; vertical-align: middle;
  }
  .tasks-data-table tr:last-child td { border-bottom: none; }
  .tasks-data-table td:last-child { border-right: none; }

  .project-name-cell { color: #9CA3AF; font-weight: 500; }
  .assigned-cell { color: #6B7280; }
  
  .task-status-pill {
    display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 6px; background: #F3F4F6;
    font-size: 12.5px; font-weight: 600; color: #374151; cursor: pointer; border: 1px solid #E5E7EB; transition: all 0.15s ease;
  }
  .task-status-pill:hover { background: #E5E7EB; }
  .status-dot { width: 7px; height: 7px; border-radius: 50%; background: #9CA3AF; flex-shrink: 0; }
  .task-status-pill.status-done .status-dot { background: #10B981; }
  .task-status-pill.status-progress .status-dot { background: #F59E0B; }

  .add-col-btn {
    background: none; border: none; font-size: 18px; color: #6B7280; cursor: pointer; font-weight: 400; padding: 0 4px;
  }
  .add-col-btn:hover { color: #0E65C7; }

  /* Dark Theme Table Support */
  [data-theme="dark"] .my-tasks-title { color: var(--ink); }
  [data-theme="dark"] .tasks-tab-nav { border-color: #1F293D; }
  [data-theme="dark"] .toolbar-link { color: #9CA3AF; }
  [data-theme="dark"] .toolbar-link:hover { color: #F9FAFB; }
  [data-theme="dark"] .tool-btn-action { color: #9CA3AF; }
  [data-theme="dark"] .tool-btn-action:hover { color: #F9FAFB; }
  [data-theme="dark"] .tasks-data-table-wrap { background: #111827; border-color: #1F293D; }
  [data-theme="dark"] .tasks-data-table th { background: #0D1526; color: #9CA3AF; border-color: #1F293D; }
  [data-theme="dark"] .tasks-data-table td { color: #F9FAFB; border-color: #1F293D; }
  [data-theme="dark"] .tasks-data-table tr:hover td { background: #162032; }
  [data-theme="dark"] .task-status-pill { background: #1E293B; border-color: #334155; color: #E2E8F0; }
  [data-theme="dark"] .task-status-pill:hover { background: #334155; }
  [data-theme="dark"] .assigned-cell { color: #9CA3AF; }
</style>
</head>
<body>

<div class="app" id="app">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>

  <main class="main">
    <?php include __DIR__ . '/includes/header.php'; ?>

    <!-- My Tasks Header -->
    <div class="my-tasks-page-header">
      <div class="tasks-header-title-wrap">
        <svg class="tasks-header-ic" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
        <h2 class="my-tasks-title">My tasks</h2>
      </div>
      <button class="close-icon-btn" title="Close" onclick="window.location.href='index.php';">&times;</button>
    </div>

    <!-- Tab Strip -->
    <div class="tasks-tab-nav">
      <div class="tasks-tab-item active" id="tabPersonalTasks" onclick="switchTaskTab('personal')">
        My Tasks (<?= $taskCount; ?>)
      </div>
      <div class="tasks-tab-item" id="tabGroupAssignments" onclick="switchTaskTab('group')">
        Group Assignments (<?= count($groupAssignments); ?>)
      </div>
    </div>

    <!-- Personal Tasks View -->
    <div id="personalTasksView">
      <div class="tasks-toolbar-bar">
        <div class="toolbar-left">
        <span>Number of tasks: <b><?= $taskCount; ?></b></span>
        <span style="color:#D1D5DB;">|</span>
        <a href="javascript:void(0)" class="toolbar-link" id="btnToggleIncomplete" onclick="toggleIncompleteFilter()">My incomplete tasks</a>
      </div>

      <div class="toolbar-right">
        <button type="button" class="tool-btn-action" onclick="document.getElementById('customFieldsModal').classList.add('active')">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="20" x2="12" y2="10"/><line x1="18" y1="20" x2="18" y2="4"/><line x1="6" y1="20" x2="6" y2="16"/></svg>
          Custom fields
        </button>
        <span style="color:#D1D5DB;">|</span>
        <button type="button" class="tool-btn-action" id="btnToggleTaskFilter" onclick="toggleTaskFilterBar()">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
          Filter
        </button>
        <span style="color:#D1D5DB;">|</span>
        <a href="api/export.php?type=tasks" class="tool-btn-action" style="text-decoration:none;">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
          Export
        </a>
      </div>
    </div>

    <!-- Filter Bar Component -->
    <div id="taskFilterBar" style="display:none; padding:12px 16px; background:var(--card-bg); border:1px solid var(--border); border-radius:10px; margin-bottom:14px; gap:16px; flex-wrap:wrap; align-items:center;">
      <div style="display:flex; align-items:center; gap:6px;">
        <span style="font-size:12px; font-weight:700; color:var(--muted);">Priority:</span>
        <button type="button" class="btn-step filter-prio-btn active" data-prio="all" onclick="setTaskPrioFilter('all', this)">All</button>
        <button type="button" class="btn-step filter-prio-btn" data-prio="urgent" onclick="setTaskPrioFilter('urgent', this)">Urgent</button>
        <button type="button" class="btn-step filter-prio-btn" data-prio="high" onclick="setTaskPrioFilter('high', this)">High</button>
        <button type="button" class="btn-step filter-prio-btn" data-prio="medium" onclick="setTaskPrioFilter('medium', this)">Medium</button>
        <button type="button" class="btn-step filter-prio-btn" data-prio="low" onclick="setTaskPrioFilter('low', this)">Low</button>
      </div>
      <div style="display:flex; align-items:center; gap:6px;">
        <span style="font-size:12px; font-weight:700; color:var(--muted);">Status:</span>
        <button type="button" class="btn-step filter-status-btn active" data-status="all" onclick="setTaskStatusFilter('all', this)">All</button>
        <button type="button" class="btn-step filter-status-btn" data-status="open" onclick="setTaskStatusFilter('open', this)">Open</button>
        <button type="button" class="btn-step filter-status-btn" data-status="in progress" onclick="setTaskStatusFilter('in progress', this)">In Progress</button>
        <button type="button" class="btn-step filter-status-btn" data-status="done" onclick="setTaskStatusFilter('done', this)">Done</button>
      </div>
    </div>

    <!-- Data Table -->
    <div class="tasks-data-table-wrap">
      <table class="tasks-data-table">
        <thead>
          <tr>
            <th style="width: 22%;">Task name</th>
            <th style="width: 14%;">Project</th>
            <th style="width: 11%;">Priority</th>
            <th style="width: 13%;">Due date</th>
            <th style="width: 12%;">Assigned</th>
            <th style="width: 14%;">Status</th>
            <th style="width: 8%;">Time log</th>
            <th style="width: 6%; text-align: center;">
              <button class="add-col-btn" id="btnOpenAddTaskFromHeader" title="Add Task">+</button>
            </th>
          </tr>
        </thead>
        <tbody id="taskTableBody">
          <?php if (empty($tasks)): ?>
            <tr>
              <td colspan="8" style="text-align: center; padding: 48px 20px; color: #6B7280;">
                <div style="font-size: 15px; font-weight: 600; color: #111827; margin-bottom: 4px;">No tasks yet</div>
                <div style="font-size: 13px; margin-bottom: 16px;">Create a task to track your work.</div>
                <button type="button" class="btn-save" style="margin: 0 auto; display: inline-flex;" onclick="document.getElementById('btnOpenAddTaskFromHeader')?.click();">Create Task</button>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($tasks as $t): 
              $status = $t['status'] ?? 'Open';
              $statusClass = ($status === 'Done' || $status === 'Completed') ? 'status-done' : (($status === 'In Progress') ? 'status-progress' : '');
            ?>
              <tr id="taskRow-<?= $t['id']; ?>" data-prio="<?= strtolower($t['priority'] ?? 'medium'); ?>" data-status="<?= strtolower($status); ?>">
                <td style="font-weight: 600; color: #111827;"><?= htmlspecialchars($t['task_name']); ?></td>
                <td class="project-name-cell"><?= htmlspecialchars($t['project_name']); ?></td>
                <td><?= renderPriorityBadge($t['priority'] ?? 'Medium'); ?></td>
                <td><?= renderDueBadge($t['due_date'] ?? null); ?></td>
                <td class="assigned-cell"><?= htmlspecialchars($t['assigned_to']); ?></td>
                <td>
                  <div class="task-status-pill <?= $statusClass; ?>" onclick="toggleTaskStatus(<?= $t['id']; ?>, this)">
                    <span class="status-dot"></span>
                    <span class="status-name"><?= htmlspecialchars($status); ?></span>
                  </div>
                </td>
                <td><?= (int)$t['time_log']; ?></td>
                <td style="text-align: center;">
                  <button type="button" class="btn-delete-task" title="Delete Task" onclick="deleteTask(<?= $t['id']; ?>)" style="background:none; border:none; color:#EF4444; font-size:14px; cursor:pointer; padding:3px 7px; border-radius:4px; font-weight:bold;">✕</button>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div><!-- /personalTasksView -->

  <!-- Group Assignments View (Team Collaboration) -->
  <div id="groupAssignmentsView" style="display:none;">
    <div class="tasks-toolbar-bar">
      <div class="toolbar-left">
        <span>Total Deliverables: <b><?= count($groupAssignments); ?></b></span>
        <span style="color:#D1D5DB;">|</span>
        <span style="color:var(--muted);">Shared team assignments and milestones</span>
      </div>
      <div class="toolbar-right">
        <button type="button" class="btn-save" onclick="document.getElementById('createGroupModal').classList.add('active')" style="display:inline-flex; align-items:center; gap:6px; font-size:12.5px; padding:6px 14px;">
          + New Group Assignment
        </button>
      </div>
    </div>

    <div class="tasks-data-table-wrap">
      <table class="tasks-data-table" id="groupAssignmentsTable">
        <thead>
          <tr>
            <th style="width:40px; text-align:center;">#</th>
            <th>Assignment Title</th>
            <th>Associated Course</th>
            <th>Due Date</th>
            <th>Status</th>
            <th>Completed By</th>
            <th style="width:140px; text-align:center;">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($groupAssignments)): ?>
            <tr>
              <td colspan="7" style="text-align:center; padding:36px 20px; color:var(--muted);">
                No group assignments found. Click "+ New Group Assignment" to create one.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($groupAssignments as $ga): 
              $isCompleted = (strtolower($ga['status'] ?? '') === 'completed');
            ?>
              <tr id="groupRow-<?= $ga['id']; ?>">
                <td style="text-align:center; color:var(--muted); font-size:12px;"><?= $ga['id']; ?></td>
                <td style="font-weight:600; color:var(--ink);">
                  <?= htmlspecialchars($ga['title']); ?>
                </td>
                <td>
                  <span style="font-size:11.5px; font-weight:600; color:#0E65C7; background:rgba(14,101,199,0.08); padding:3px 8px; border-radius:4px;">
                    <?= htmlspecialchars($ga['course_name']); ?>
                  </span>
                </td>
                <td>
                  <span class="due-badge normal"><?= htmlspecialchars($ga['due_date']); ?></span>
                </td>
                <td>
                  <span class="priority-badge <?= $isCompleted ? 'priority-low' : 'priority-medium'; ?>" id="gaStatus-<?= $ga['id']; ?>">
                    <?= $isCompleted ? 'Completed' : 'In Progress'; ?>
                  </span>
                </td>
                <td style="color:var(--muted); font-size:12.5px;">
                  <?= !empty($ga['completed_by_user_name']) ? htmlspecialchars($ga['completed_by_user_name']) : '—'; ?>
                </td>
                <td style="text-align:center;">
                  <?php if ($isCompleted): ?>
                    <span style="color:#10B981; font-size:12px; font-weight:600;">Completed</span>
                  <?php else: ?>
                    <button type="button" class="btn-save" onclick="completeGroupAssignment(<?= $ga['id']; ?>)" style="padding:4px 10px; font-size:11.5px; background:#10B981;">
                      Mark Done
                    </button>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div><!-- /groupAssignmentsView -->
</div>

<!-- Modal: Add New Task -->
<div class="modal-overlay" id="addTaskModalOverlay">
  <div class="modal-card">
    <div class="modal-header">
      <h3 class="modal-title">Add Task</h3>
      <button class="modal-close-btn" id="btnCloseAddTaskModal">&times;</button>
    </div>
    <form id="addTaskForm">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label" for="taskNameInput">Task Name</label>
          <input type="text" id="taskNameInput" class="form-input" placeholder="e.g. Implement OAuth Flow" required />
        </div>
        <div class="form-group">
          <label class="form-label" for="taskProjectInput">Project</label>
          <input type="text" id="taskProjectInput" class="form-input" value="2dapp" required />
        </div>
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
          <div class="form-group">
            <label class="form-label" for="taskPriorityInput">Priority</label>
            <select id="taskPriorityInput" class="form-input">
              <option value="Urgent">Urgent</option>
              <option value="High">High</option>
              <option value="Medium" selected>Medium</option>
              <option value="Low">Low</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label" for="taskDueDateInput">Due Date</label>
            <input type="text" id="taskDueDateInput" class="form-input" value="<?= date('d-m-Y', strtotime('+3 days')); ?>" required />
          </div>
        </div>
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
          <div class="form-group">
            <label class="form-label" for="taskStartDateInput">Start Date</label>
            <input type="text" id="taskStartDateInput" class="form-input" value="<?= date('d-m-Y'); ?>" required />
          </div>
          <div class="form-group">
            <label class="form-label" for="taskAssignedInput">Assigned To</label>
            <input type="text" id="taskAssignedInput" class="form-input" value="Alex Morgan" required />
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-cancel" id="btnCancelAddTaskModal">Cancel</button>
        <button type="submit" class="btn-save" id="btnSubmitAddTask">Add Task</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal: Custom Fields Visibility -->
<div class="modal-overlay" id="customFieldsModal">
  <div class="modal-card">
    <div class="modal-header">
      <h3 class="modal-title">Customize Table Columns</h3>
      <button class="modal-close-btn" onclick="document.getElementById('customFieldsModal').classList.remove('active')">&times;</button>
    </div>
    <div class="modal-body">
      <p style="font-size:13px; color:var(--muted); margin-top:0;">Toggle visibility of columns in your task table:</p>
      <div style="display:flex; flex-direction:column; gap:12px;">
        <label style="display:flex; align-items:center; gap:10px; font-size:13.5px; font-weight:600; cursor:pointer;">
          <input type="checkbox" id="colToggleProject" checked onchange="toggleColVisibility(2, this.checked)" /> Project Name
        </label>
        <label style="display:flex; align-items:center; gap:10px; font-size:13.5px; font-weight:600; cursor:pointer;">
          <input type="checkbox" id="colTogglePriority" checked onchange="toggleColVisibility(3, this.checked)" /> Priority Badge
        </label>
        <label style="display:flex; align-items:center; gap:10px; font-size:13.5px; font-weight:600; cursor:pointer;">
          <input type="checkbox" id="colToggleDueDate" checked onchange="toggleColVisibility(4, this.checked)" /> Due Date Countdown
        </label>
        <label style="display:flex; align-items:center; gap:10px; font-size:13.5px; font-weight:600; cursor:pointer;">
          <input type="checkbox" id="colToggleAssigned" checked onchange="toggleColVisibility(5, this.checked)" /> Assigned Member
        </label>
        <label style="display:flex; align-items:center; gap:10px; font-size:13.5px; font-weight:600; cursor:pointer;">
          <input type="checkbox" id="colToggleTimeLog" checked onchange="toggleColVisibility(7, this.checked)" /> Time Log Counter
        </label>
      </div>
    </div>
    <div class="modal-footer" style="display:flex; justify-content:flex-end;">
      <button type="button" class="btn-save" onclick="document.getElementById('customFieldsModal').classList.remove('active')">Done</button>
    </div>
  </div>
</div>

<!-- Modal: Create Group Assignment -->
<div class="modal-overlay" id="createGroupModal">
  <div class="modal-card">
    <div class="modal-header">
      <h3 class="modal-title">Create Group Assignment</h3>
      <button class="modal-close-btn" onclick="document.getElementById('createGroupModal').classList.remove('active')">&times;</button>
    </div>
    <form id="createGroupForm" onsubmit="submitGroupAssignment(event)">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label" for="gaTitle">Assignment Title</label>
          <input type="text" id="gaTitle" class="form-input" placeholder="e.g. Distributed Database Replication Lab" required />
        </div>
        <div class="form-group">
          <label class="form-label" for="gaCourse">Associated Course</label>
          <select id="gaCourse" class="form-input">
            <?php foreach ($userCourses as $uc): ?>
              <option value="<?= htmlspecialchars($uc['name']); ?>"><?= htmlspecialchars($uc['name']); ?></option>
            <?php endforeach; ?>
            <option value="General Engineering">General Engineering</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label" for="gaDueDate">Due Date / Timeline</label>
          <input type="text" id="gaDueDate" class="form-input" value="Due in 5 days" required />
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-cancel" onclick="document.getElementById('createGroupModal').classList.remove('active')">Cancel</button>
        <button type="submit" class="btn-save" id="btnSubmitGA">Create Assignment</button>
      </div>
    </form>
  </div>
</div>

<script src="assets/js/app.js"></script>
<script>


// Tab Switching Controller
window.switchTaskTab = function(tab) {
  const tabPersonal = document.getElementById('tabPersonalTasks');
  const tabGroup = document.getElementById('tabGroupAssignments');
  const viewPersonal = document.getElementById('personalTasksView');
  const viewGroup = document.getElementById('groupAssignmentsView');

  if (tab === 'group') {
    if (tabPersonal) tabPersonal.classList.remove('active');
    if (tabGroup) tabGroup.classList.add('active');
    if (viewPersonal) viewPersonal.style.display = 'none';
    if (viewGroup) viewGroup.style.display = 'block';
  } else {
    if (tabGroup) tabGroup.classList.remove('active');
    if (tabPersonal) tabPersonal.classList.add('active');
    if (viewGroup) viewGroup.style.display = 'none';
    if (viewPersonal) viewPersonal.style.display = 'block';
  }
};

window.submitGroupAssignment = async function(e) {
  e.preventDefault();
  const title = document.getElementById('gaTitle').value.trim();
  const course_name = document.getElementById('gaCourse').value;
  const due_date = document.getElementById('gaDueDate').value.trim();
  const btn = document.getElementById('btnSubmitGA');
  btn.disabled = true;
  btn.textContent = 'Creating...';

  try {
    const res = await secureFetch('api/assignments.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'create', title, course_name, due_date })
    });
    const data = await res.json();
    if (data.success) {
      if (typeof showToast === 'function') showToast('Group assignment created.');
      setTimeout(() => location.reload(), 400);
    } else {
      alert(data.message || 'Failed to create group assignment');
    }
  } catch (err) {
    console.error(err);
  } finally {
    btn.disabled = false;
    btn.textContent = 'Create Assignment';
  }
};

window.completeGroupAssignment = async function(id) {
  try {
    const res = await secureFetch('api/assignments.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'complete', assignment_id: id })
    });
    const data = await res.json();
    if (data.success) {
      if (typeof showToast === 'function') showToast(data.message);
      setTimeout(() => location.reload(), 400);
    } else {
      alert(data.message || 'Failed to update assignment');
    }
  } catch (err) {
    console.error(err);
  }
};

let activePrioFilter = 'all';
let activeStatusFilter = 'all';
let onlyIncomplete = false;

function toggleTaskFilterBar() {
  const bar = document.getElementById('taskFilterBar');
  if (bar) {
    const isFlex = bar.style.display === 'flex';
    bar.style.display = isFlex ? 'none' : 'flex';
  }
}

function setTaskPrioFilter(prio, btn) {
  activePrioFilter = prio;
  document.querySelectorAll('.filter-prio-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  applyTaskFilters();
}

function setTaskStatusFilter(st, btn) {
  activeStatusFilter = st;
  document.querySelectorAll('.filter-status-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  applyTaskFilters();
}

function toggleIncompleteFilter() {
  onlyIncomplete = !onlyIncomplete;
  const link = document.getElementById('btnToggleIncomplete');
  if (link) {
    link.style.fontWeight = onlyIncomplete ? '800' : '';
    link.style.color = onlyIncomplete ? 'var(--purple)' : '';
    link.textContent = onlyIncomplete ? 'Showing: Incomplete tasks' : 'My incomplete tasks';
  }
  applyTaskFilters();
}

function applyTaskFilters() {
  const rows = document.querySelectorAll('#taskTableBody tr[data-prio]');
  rows.forEach(row => {
    const p = row.getAttribute('data-prio') || '';
    const s = row.getAttribute('data-status') || '';

    const matchPrio = (activePrioFilter === 'all' || p === activePrioFilter);
    const matchStatus = (activeStatusFilter === 'all' || s === activeStatusFilter);
    const matchIncomplete = (!onlyIncomplete || (s !== 'done' && s !== 'completed'));

    row.style.display = (matchPrio && matchStatus && matchIncomplete) ? '' : 'none';
  });
}

function toggleColVisibility(colIdx, isVisible) {
  const th = document.querySelector(`.tasks-data-table th:nth-child(${colIdx})`);
  if (th) th.style.display = isVisible ? '' : 'none';
  const tds = document.querySelectorAll(`.tasks-data-table td:nth-child(${colIdx})`);
  tds.forEach(td => td.style.display = isVisible ? '' : 'none');
}

document.addEventListener('DOMContentLoaded', () => {
  const btnAdd = document.getElementById('btnOpenAddTaskFromHeader');
  const modal = document.getElementById('addTaskModalOverlay');
  const btnClose = document.getElementById('btnCloseAddTaskModal');
  const btnCancel = document.getElementById('btnCancelAddTaskModal');
  const form = document.getElementById('addTaskForm');

  if (btnAdd && modal) {
    btnAdd.addEventListener('click', () => modal.classList.add('active'));
  }
  if (btnClose && modal) {
    btnClose.addEventListener('click', () => modal.classList.remove('active'));
  }
  if (btnCancel && modal) {
    btnCancel.addEventListener('click', () => modal.classList.remove('active'));
  }

  if (form && modal) {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const payload = {
        action: 'create',
        task_name: document.getElementById('taskNameInput').value.trim(),
        project_name: document.getElementById('taskProjectInput').value.trim(),
        priority: document.getElementById('taskPriorityInput').value,
        due_date: document.getElementById('taskDueDateInput').value.trim(),
        start_date: document.getElementById('taskStartDateInput').value.trim(),
        assigned_to: document.getElementById('taskAssignedInput').value.trim(),
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
          showToast('Task created.');
          modal.classList.remove('active');
          form.reset();
          setTimeout(() => location.reload(), 400);
        } else {
          alert(data.message || 'Error adding task');
        }
      } catch (err) {
        console.error(err);
      }
    });
  }
});

async function toggleTaskStatus(taskId, element) {
  const statusName = element.querySelector('.status-name');
  let newStatus = 'Open';
  if (statusName.textContent === 'Open') newStatus = 'In Progress';
  else if (statusName.textContent === 'In Progress') newStatus = 'Done';
  else newStatus = 'Open';

  try {
    const res = await secureFetch('api/tasks.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'update_status', task_id: taskId, status: newStatus })
    });
    const data = await res.json();
    if (data.success) {
      statusName.textContent = newStatus;
      element.className = 'task-status-pill ' + (newStatus === 'Done' ? 'status-done' : (newStatus === 'In Progress' ? 'status-progress' : ''));
      const row = document.getElementById('taskRow-' + taskId);
      if (row) row.setAttribute('data-status', newStatus.toLowerCase());
      if (typeof showToast === 'function') showToast(`Status: ${newStatus}`);
    }
  } catch (err) {
    console.error(err);
  }
}

async function deleteTask(taskId) {
  if (!confirm('Are you sure you want to delete this task?')) return;
  try {
    const res = await secureFetch('api/tasks.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'delete', task_id: taskId })
    });
    const data = await res.json();
    if (data.success) {
      const row = document.getElementById('taskRow-' + taskId);
      if (row) row.remove();
      if (typeof showToast === 'function') showToast('Task deleted.');
    } else {
      alert('Failed to delete task: ' + data.message);
    }
  } catch (err) {
    console.error(err);
  }
}
</script>
</body>
</html>
