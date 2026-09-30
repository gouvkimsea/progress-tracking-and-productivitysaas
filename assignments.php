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

if (!function_exists('renderTaskPlanningBadge')) {
    function renderTaskPlanningBadge(?string $plan): string {
        $p = trim($plan ?? '');
        if (empty($p)) {
            return '<span style="color:var(--text-muted); font-size:12px;">No task plan</span>';
        }
        $lines = array_values(array_filter(array_map('trim', explode("\n", $p))));
        $total = count($lines);
        if ($total === 0) {
            return '<span style="color:var(--text-muted); font-size:12px;">No task plan</span>';
        }
        $done = 0;
        foreach ($lines as $l) {
            if (preg_match('/^(\[x\]|- \[x\]|\(x\))/i', $l)) {
                $done++;
            }
        }
        $badgeClass = ($total > 0 && $done === $total) ? 'plan-pill-done' : ($done > 0 ? 'plan-pill-progress' : 'plan-pill-pending');
        $label = $total . ($total === 1 ? ' Step' : ' Steps');
        if ($done > 0) {
            $label .= " ({$done}/{$total})";
        }
        return '<span class="plan-pill ' . $badgeClass . '"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>' . htmlspecialchars($label) . '</span>';
    }
}

if (!function_exists('safeSnippet')) {
    function safeSnippet(?string $text, int $length = 75): string {
        $t = trim($text ?? '');
        if (function_exists('mb_strimwidth')) {
            return mb_strimwidth($t, 0, $length, '...');
        }
        return (strlen($t) > $length) ? substr($t, 0, $length) . '...' : $t;
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
  .tasks-header-ic { color: var(--brand-primary); }
  .my-tasks-title { margin: 0; font-size: 20px; font-weight: 700; letter-spacing: -0.02em; color: var(--text-primary); }
  .close-icon-btn { background: none; border: none; font-size: 18px; color: var(--text-muted); cursor: pointer; }

  /* Tab Navigation */
  .tasks-tab-nav {
    display: flex; align-items: center; gap: 20px; border-bottom: 1px solid var(--border-base); margin-bottom: 14px;
  }
  .tasks-tab-item {
    font-size: 13.5px; font-weight: 500; color: var(--text-secondary); padding-bottom: 8px; position: relative; cursor: pointer; transition: color 0.15s ease;
  }
  .tasks-tab-item:hover { color: var(--text-primary); }
  .tasks-tab-item.active {
    color: var(--brand-primary); font-weight: 600;
  }
  .tasks-tab-item.active::after {
    content: ''; position: absolute; bottom: -1px; left: 0; right: 0; height: 2px; background: var(--brand-primary);
  }

  /* Toolbar Info Bar */
  .tasks-toolbar-bar {
    display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; font-size: 13px; color: var(--text-secondary);
  }
  .toolbar-left { display: flex; align-items: center; gap: 16px; }
  .toolbar-link { color: var(--text-secondary); text-decoration: none; font-weight: 500; cursor: pointer; }
  .toolbar-link:hover { color: var(--text-primary); }
  
  .toolbar-right { display: flex; align-items: center; gap: 16px; }
  .tool-btn-action {
    display: flex; align-items: center; gap: 6px; color: var(--text-secondary); background: none; border: none; font-size: 13px; font-weight: 500; cursor: pointer;
  }
  .tool-btn-action:hover { color: var(--text-primary); }

  /* Task Table Layout */
  .tasks-data-table-wrap {
    width: 100%; border: 1px solid var(--border-base); border-radius: var(--radius-md); overflow: hidden; background: var(--bg-surface);
  }
  .tasks-data-table { width: 100%; border-collapse: collapse; text-align: left; }
  
  .tasks-data-table th {
    background: var(--bg-subtle); padding: 11px 16px; font-size: 12.5px; font-weight: 600; color: var(--text-secondary); border-bottom: 1px solid var(--border-base); border-right: 1px solid var(--border-base);
  }
  .tasks-data-table th:last-child { border-right: none; }
  
  .tasks-data-table td {
    padding: 11px 16px; font-size: 13px; color: var(--text-primary); border-bottom: 1px solid var(--border-base); border-right: 1px solid var(--border-base); vertical-align: middle;
  }
  .tasks-data-table tr:last-child td { border-bottom: none; }
  .tasks-data-table td:last-child { border-right: none; }
  .tasks-data-table tr:hover td { background: var(--bg-subtle); }

  .project-name-cell { color: var(--text-muted); font-weight: 500; }
  .assigned-cell { color: var(--text-secondary); }
  
  .task-status-pill {
    display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: var(--radius-sm); background: var(--bg-subtle);
    font-size: 12px; font-weight: 500; color: var(--text-primary); cursor: pointer; border: 1px solid var(--border-base); transition: all 0.15s ease;
  }
  .task-status-pill:hover { background: var(--border-base); }
  .status-dot { width: 7px; height: 7px; border-radius: 50%; background: var(--text-muted); flex-shrink: 0; }
  .task-status-pill.status-done .status-dot { background: var(--status-done-text); }
  .task-status-pill.status-progress .status-dot { background: var(--status-high-text); }

  .add-col-btn {
    background: none; border: none; font-size: 18px; color: var(--text-muted); cursor: pointer; font-weight: 400; padding: 0 4px;
  }
  .add-col-btn:hover { color: var(--brand-primary); }

  /* Assignment Task Planning & Description Styles */
  .assignment-desc-snippet {
    font-size: 12px;
    color: var(--text-muted);
    font-weight: 400;
    margin-top: 3px;
    max-width: 260px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    transition: color 0.15s ease;
  }
  .assignment-desc-snippet:hover {
    color: var(--brand-primary);
  }
  .plan-pill {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 9px;
    border-radius: var(--radius-full);
    font-size: 11.5px;
    font-weight: 600;
    border: 1px solid var(--border-base);
    background: var(--bg-subtle);
    color: var(--text-secondary);
    transition: all 0.15s ease;
    cursor: pointer;
  }
  .plan-pill:hover {
    border-color: var(--brand-primary);
    color: var(--brand-primary);
    background: var(--brand-subtle, rgba(99, 102, 241, 0.08));
  }
  .plan-pill-pending {
    background: var(--bg-subtle);
    color: var(--text-secondary);
  }
  .plan-pill-progress {
    background: rgba(245, 158, 11, 0.1);
    color: #d97706;
    border-color: rgba(245, 158, 11, 0.3);
  }
  .plan-pill-done {
    background: var(--status-done-bg);
    color: var(--status-done-text);
    border-color: var(--status-done-border);
  }
  .btn-ga-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: var(--radius-xs);
    border: 1px solid var(--border-base);
    background: var(--bg-surface);
    color: var(--text-secondary);
    cursor: pointer;
    transition: all 0.15s ease;
    padding: 0;
  }
  .btn-ga-action:hover {
    color: var(--brand-primary);
    border-color: var(--brand-primary);
    background: var(--bg-subtle);
  }
  .btn-ga-delete:hover {
    color: var(--status-urgent-text);
    border-color: var(--status-urgent-border);
    background: var(--status-urgent-bg);
  }
  .plan-check-item:hover {
    border-color: var(--brand-primary) !important;
  }
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
      <button class="close-icon-btn" title="Close" aria-label="Close" onclick="window.location.href='index.php';">&times;</button>
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
        <span style="color:var(--border-base);">|</span>
        <a href="javascript:void(0)" class="toolbar-link" id="btnToggleIncomplete" onclick="toggleIncompleteFilter()">My incomplete tasks</a>
      </div>

      <div class="toolbar-right">
        <button type="button" class="tool-btn-action" onclick="document.getElementById('customFieldsModal').classList.add('active')">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="20" x2="12" y2="10"/><line x1="18" y1="20" x2="18" y2="4"/><line x1="6" y1="20" x2="6" y2="16"/></svg>
          Custom fields
        </button>
        <span style="color:var(--border-base);">|</span>
        <button type="button" class="tool-btn-action" id="btnToggleTaskFilter" onclick="toggleTaskFilterBar()">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
          Filter
        </button>
        <span style="color:var(--border-base);">|</span>
        <a href="api/export.php?type=tasks" class="tool-btn-action" style="text-decoration:none;">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
          Export
        </a>
      </div>
    </div>

    <!-- Filter Bar Component -->
    <div id="taskFilterBar" style="display:none; padding:12px 16px; background:var(--bg-surface); border:1px solid var(--border-base); border-radius:var(--radius-md); margin-bottom:14px; gap:16px; flex-wrap:wrap; align-items:center;">
      <div style="display:flex; align-items:center; gap:6px;">
        <span style="font-size:12px; font-weight:700; color:var(--text-muted);">Priority:</span>
        <button type="button" class="btn-step filter-prio-btn active" data-prio="all" onclick="setTaskPrioFilter('all', this)">All</button>
        <button type="button" class="btn-step filter-prio-btn" data-prio="urgent" onclick="setTaskPrioFilter('urgent', this)">Urgent</button>
        <button type="button" class="btn-step filter-prio-btn" data-prio="high" onclick="setTaskPrioFilter('high', this)">High</button>
        <button type="button" class="btn-step filter-prio-btn" data-prio="medium" onclick="setTaskPrioFilter('medium', this)">Medium</button>
        <button type="button" class="btn-step filter-prio-btn" data-prio="low" onclick="setTaskPrioFilter('low', this)">Low</button>
      </div>
      <div style="display:flex; align-items:center; gap:6px;">
        <span style="font-size:12px; font-weight:700; color:var(--text-muted);">Status:</span>
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
              <td colspan="8" style="text-align: center; padding: 48px 20px; color: var(--text-secondary);">
                <div style="font-size: 15px; font-weight: 600; color: var(--text-primary); margin-bottom: 4px;">No tasks yet</div>
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
                <td style="font-weight: 600; color: var(--text-primary);"><?= htmlspecialchars($t['task_name']); ?></td>
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
                  <button type="button" class="btn-delete-task" title="Delete Task" onclick="deleteTask(<?= $t['id']; ?>)" style="background:none; border:none; color:var(--status-urgent-text); font-size:14px; cursor:pointer; padding:3px 7px; border-radius:var(--radius-xs); font-weight:bold;">✕</button>
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
        <span style="color:var(--border-base);">|</span>
        <span style="color:var(--text-secondary);">Shared team assignments and milestones</span>
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
            <th style="min-width:220px;">Assignment Title & Description</th>
            <th style="min-width:140px;">Task Planning</th>
            <th>Associated Course</th>
            <th>Due Date</th>
            <th>Status</th>
            <th>Completed By</th>
            <th style="width:160px; text-align:center;">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($groupAssignments)): ?>
            <tr>
              <td colspan="8" style="text-align:center; padding:36px 20px; color:var(--text-muted);">
                No group assignments found. Click "+ New Group Assignment" to create one.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($groupAssignments as $ga): 
              $isCompleted = (strtolower($ga['status'] ?? '') === 'completed');
            ?>
              <tr id="groupRow-<?= $ga['id']; ?>">
                <td style="text-align:center; color:var(--text-muted); font-size:12px;"><?= $ga['id']; ?></td>
                <td>
                  <div style="font-weight:600; color:var(--text-primary); cursor:pointer; display:inline-flex; align-items:center; gap:6px;" onclick="openAssignmentDetails(<?= $ga['id']; ?>)">
                    <span><?= htmlspecialchars($ga['title']); ?></span>
                  </div>
                  <?php if (!empty($ga['description'])): ?>
                    <div class="assignment-desc-snippet" title="<?= htmlspecialchars($ga['description']); ?>" onclick="openAssignmentDetails(<?= $ga['id']; ?>)" style="cursor:pointer;">
                      <?= htmlspecialchars(safeSnippet($ga['description'], 75)); ?>
                    </div>
                  <?php endif; ?>
                </td>
                <td>
                  <div style="cursor:pointer; display:inline-block;" onclick="openAssignmentDetails(<?= $ga['id']; ?>)" title="View & track task planning">
                    <?= renderTaskPlanningBadge($ga['task_planning'] ?? null); ?>
                  </div>
                </td>
                <td>
                  <span style="font-size:11.5px; font-weight:600; color:var(--brand-primary); background:var(--bg-subtle); padding:3px 8px; border-radius:var(--radius-xs); border:1px solid var(--border-base);">
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
                <td style="color:var(--text-secondary); font-size:12.5px;">
                  <?= !empty($ga['completed_by_user_name']) ? htmlspecialchars($ga['completed_by_user_name']) : '—'; ?>
                </td>
                <td style="text-align:center;">
                  <div style="display:inline-flex; align-items:center; gap:5px; justify-content:center;">
                    <button type="button" class="btn-ga-action" title="View Details & Plan" onclick="openAssignmentDetails(<?= $ga['id']; ?>)">
                      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                    <button type="button" class="btn-ga-action" title="Edit Assignment" onclick="openEditAssignment(<?= $ga['id']; ?>)">
                      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    </button>
                    <?php if ($isCompleted): ?>
                      <span style="color:var(--status-done-text); font-size:11px; font-weight:600; padding:2px 4px;">Done</span>
                    <?php else: ?>
                      <button type="button" class="btn-save" onclick="completeGroupAssignment(<?= $ga['id']; ?>)" style="padding:3px 7px; font-size:11px; background:var(--status-done-bg); color:var(--status-done-text); border:1px solid var(--status-done-border);">
                        Done
                      </button>
                    <?php endif; ?>
                    <button type="button" class="btn-ga-action btn-ga-delete" title="Delete Assignment" onclick="deleteGroupAssignment(<?= $ga['id']; ?>)">
                      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                    </button>
                  </div>
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
      <button class="modal-close-btn" id="btnCloseAddTaskModal" aria-label="Close modal">&times;</button>
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
      <button class="modal-close-btn" aria-label="Close modal" onclick="document.getElementById('customFieldsModal').classList.remove('active')">&times;</button>
    </div>
    <div class="modal-body">
      <p style="font-size:13px; color:var(--text-secondary); margin-top:0;">Toggle visibility of columns in your task table:</p>
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
  <div class="modal-card" style="max-width: 540px;">
    <div class="modal-header">
      <h3 class="modal-title">Create Group Assignment</h3>
      <button class="modal-close-btn" aria-label="Close modal" onclick="document.getElementById('createGroupModal').classList.remove('active')">&times;</button>
    </div>
    <form id="createGroupForm" onsubmit="submitGroupAssignment(event)">
      <div class="modal-body" style="max-height:75vh; overflow-y:auto;">
        <div class="form-group">
          <label class="form-label" for="gaTitle">Assignment Title</label>
          <input type="text" id="gaTitle" class="form-input" placeholder="e.g. Distributed Database Replication Lab" required />
        </div>
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
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
        <div class="form-group">
          <label class="form-label" for="gaDescription">Description</label>
          <textarea id="gaDescription" class="form-input" rows="3" placeholder="Explain the assignment scope, instructions, or deliverables..."></textarea>
        </div>
        <div class="form-group">
          <label class="form-label" for="gaTaskPlanning">Task Planning / Action Plan</label>
          <textarea id="gaTaskPlanning" class="form-input" rows="4" placeholder="Enter task plan steps (one per line):&#10;1. Research system architecture&#10;2. Draft schema and API specification&#10;3. Implement core features&#10;4. Review and deploy"></textarea>
          <div style="font-size:11px; color:var(--text-muted); margin-top:4px;">Tip: Enter each task on a new line to automatically create an interactive checklist. Prefix with [x] for completed tasks.</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-cancel" onclick="document.getElementById('createGroupModal').classList.remove('active')">Cancel</button>
        <button type="submit" class="btn-save" id="btnSubmitGA">Create Assignment</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal: Assignment Details & Task Planning -->
<div class="modal-overlay" id="viewAssignmentModal">
  <div class="modal-card" style="max-width: 600px;">
    <div class="modal-header">
      <div style="display:flex; align-items:center; gap:8px;">
        <span style="font-size:16px;">📋</span>
        <h3 class="modal-title" id="viewGaTitle" style="margin:0;">Assignment Details</h3>
      </div>
      <button class="modal-close-btn" aria-label="Close modal" onclick="document.getElementById('viewAssignmentModal').classList.remove('active')">&times;</button>
    </div>
    <div class="modal-body" style="display:flex; flex-direction:column; gap:16px; max-height:75vh; overflow-y:auto;">
      <!-- Metadata Chips -->
      <div style="display:flex; flex-wrap:wrap; gap:10px; align-items:center; padding:10px 14px; background:var(--bg-subtle); border-radius:var(--radius-sm); border:1px solid var(--border-base);">
        <div><span style="font-size:11px; font-weight:700; color:var(--text-muted); text-transform:uppercase;">Course:</span> <span id="viewGaCourse" style="font-size:12.5px; font-weight:600; color:var(--brand-primary); margin-left:4px;"></span></div>
        <span style="color:var(--border-base);">•</span>
        <div><span style="font-size:11px; font-weight:700; color:var(--text-muted); text-transform:uppercase;">Due:</span> <span id="viewGaDueDate" style="font-size:12.5px; font-weight:500; margin-left:4px;"></span></div>
        <span style="color:var(--border-base);">•</span>
        <div><span style="font-size:11px; font-weight:700; color:var(--text-muted); text-transform:uppercase;">Status:</span> <span id="viewGaStatus" style="margin-left:4px;"></span></div>
      </div>

      <!-- Description Section -->
      <div>
        <label class="form-label" style="font-weight:700; margin-bottom:6px; display:flex; align-items:center; gap:6px;">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
          Description
        </label>
        <div id="viewGaDescBox" style="background:var(--bg-subtle); border:1px solid var(--border-base); border-radius:var(--radius-sm); padding:12px 14px; font-size:13px; line-height:1.6; color:var(--text-primary); white-space:pre-wrap; min-height:48px;"></div>
      </div>

      <!-- Task Planning Section -->
      <div>
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:6px;">
          <label class="form-label" style="font-weight:700; margin:0; display:flex; align-items:center; gap:6px;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
            Task Planning Roadmap
          </label>
          <span id="viewGaPlanProgressText" style="font-size:12px; font-weight:600; color:var(--brand-primary);"></span>
        </div>

        <!-- Progress bar -->
        <div style="width:100%; height:6px; background:var(--bg-subtle); border-radius:99px; overflow:hidden; margin-bottom:12px; border:1px solid var(--border-base);">
          <div id="viewGaProgressBar" style="width:0%; height:100%; background:var(--brand-primary); transition:width 0.25s ease;"></div>
        </div>

        <!-- Checklist -->
        <div id="viewGaPlanChecklist" style="display:flex; flex-direction:column; gap:8px;"></div>
      </div>
    </div>
    <div class="modal-footer" style="display:flex; justify-content:space-between; align-items:center;">
      <button type="button" class="btn-cancel" onclick="document.getElementById('viewAssignmentModal').classList.remove('active')">Close</button>
      <div style="display:flex; gap:8px;">
        <button type="button" class="btn-save" id="btnEditFromView" style="display:inline-flex; align-items:center; gap:6px; background:var(--bg-subtle); color:var(--text-primary); border:1px solid var(--border-base);">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
          Edit
        </button>
        <button type="button" class="btn-save" id="btnCompleteFromView" style="display:inline-flex; align-items:center; gap:6px;">
          Mark Done
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Edit Group Assignment -->
<div class="modal-overlay" id="editAssignmentModal">
  <div class="modal-card" style="max-width: 560px;">
    <div class="modal-header">
      <h3 class="modal-title">Edit Assignment</h3>
      <button class="modal-close-btn" aria-label="Close modal" onclick="document.getElementById('editAssignmentModal').classList.remove('active')">&times;</button>
    </div>
    <form id="editAssignmentForm" onsubmit="submitEditAssignment(event)">
      <input type="hidden" id="editGaId" />
      <div class="modal-body" style="max-height:75vh; overflow-y:auto;">
        <div class="form-group">
          <label class="form-label" for="editGaTitle">Assignment Title</label>
          <input type="text" id="editGaTitle" class="form-input" required />
        </div>
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
          <div class="form-group">
            <label class="form-label" for="editGaCourse">Associated Course</label>
            <select id="editGaCourse" class="form-input">
              <?php foreach ($userCourses as $uc): ?>
                <option value="<?= htmlspecialchars($uc['name']); ?>"><?= htmlspecialchars($uc['name']); ?></option>
              <?php endforeach; ?>
              <option value="General Engineering">General Engineering</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label" for="editGaDueDate">Due Date / Timeline</label>
            <input type="text" id="editGaDueDate" class="form-input" required />
          </div>
        </div>
        <div class="form-group">
          <label class="form-label" for="editGaStatus">Status</label>
          <select id="editGaStatus" class="form-input">
            <option value="In Progress">In Progress</option>
            <option value="Completed">Completed</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label" for="editGaDescription">Description</label>
          <textarea id="editGaDescription" class="form-input" rows="3" placeholder="Explain the assignment scope, instructions, or deliverables..."></textarea>
        </div>
        <div class="form-group">
          <label class="form-label" for="editGaTaskPlanning">Task Planning / Action Plan</label>
          <textarea id="editGaTaskPlanning" class="form-input" rows="5" placeholder="Enter task plan steps (one per line):&#10;1. Research & design&#10;2. Build prototype&#10;3. Testing & validation&#10;4. Final submission"></textarea>
          <div style="font-size:11px; color:var(--text-muted); margin-top:4px;">Each line becomes a checklist item in the assignment details view. Prefix with [x] for completed tasks.</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-cancel" onclick="document.getElementById('editAssignmentModal').classList.remove('active')">Cancel</button>
        <button type="submit" class="btn-save" id="btnSubmitEditGA">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<script src="assets/js/app.js"></script>
<script>

let currentAssignments = <?= json_encode($groupAssignments, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?> || [];

window.getAssignmentById = function(id) {
  return currentAssignments.find(a => parseInt(a.id, 10) === parseInt(id, 10));
};

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
  const description = document.getElementById('gaDescription') ? document.getElementById('gaDescription').value.trim() : '';
  const task_planning = document.getElementById('gaTaskPlanning') ? document.getElementById('gaTaskPlanning').value.trim() : '';
  const btn = document.getElementById('btnSubmitGA');
  btn.disabled = true;
  btn.textContent = 'Creating...';

  try {
    const res = await secureFetch('api/assignments.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'create', title, course_name, due_date, description, task_planning })
    });
    const data = await res.json();
    if (data.success) {
      if (typeof showToast === 'function') showToast('Assignment created successfully!');
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

window.openAssignmentDetails = function(id) {
  const ga = getAssignmentById(id);
  if (!ga) return;

  document.getElementById('viewGaTitle').textContent = ga.title || 'Assignment Details';
  document.getElementById('viewGaCourse').textContent = ga.course_name || 'General';
  document.getElementById('viewGaDueDate').textContent = ga.due_date || 'No deadline';
  
  const isDone = (ga.status || '').toLowerCase() === 'completed';
  const statusEl = document.getElementById('viewGaStatus');
  statusEl.className = 'priority-badge ' + (isDone ? 'priority-low' : 'priority-medium');
  statusEl.textContent = isDone ? 'Completed' : 'In Progress';

  const descBox = document.getElementById('viewGaDescBox');
  if (ga.description && ga.description.trim()) {
    descBox.textContent = ga.description;
    descBox.style.color = 'var(--text-primary)';
    descBox.style.fontStyle = 'normal';
  } else {
    descBox.textContent = 'No description provided for this assignment.';
    descBox.style.color = 'var(--text-muted)';
    descBox.style.fontStyle = 'italic';
  }

  // Render Task Planning Checklist
  renderChecklistInView(ga);

  // Setup action buttons
  const editBtn = document.getElementById('btnEditFromView');
  editBtn.onclick = () => {
    document.getElementById('viewAssignmentModal').classList.remove('active');
    openEditAssignment(ga.id);
  };

  const compBtn = document.getElementById('btnCompleteFromView');
  compBtn.textContent = isDone ? 'Mark In Progress' : 'Mark Done';
  compBtn.onclick = async () => {
    await completeGroupAssignment(ga.id);
  };

  document.getElementById('viewAssignmentModal').classList.add('active');
};

function renderChecklistInView(ga) {
  const container = document.getElementById('viewGaPlanChecklist');
  container.innerHTML = '';

  const rawPlan = ga.task_planning ? ga.task_planning.trim() : '';
  const lines = rawPlan ? rawPlan.split(/\r?\n/).filter(l => l.trim().length > 0) : [];

  const progBar = document.getElementById('viewGaProgressBar');
  const progText = document.getElementById('viewGaPlanProgressText');

  if (lines.length === 0) {
    progBar.style.width = '0%';
    progText.textContent = '0 tasks planned';
    container.innerHTML = `
      <div style="padding:16px; text-align:center; background:var(--bg-subtle); border:1px dashed var(--border-base); border-radius:var(--radius-sm); color:var(--text-muted); font-size:12.5px;">
        No task planning steps yet. Click <b>Edit</b> to add a task plan roadmap.
      </div>
    `;
    return;
  }

  let completedCount = 0;
  lines.forEach((line, idx) => {
    const isChecked = /^(\[x\]|- \[x\]|\(x\))/i.test(line.trim());
    if (isChecked) completedCount++;

    const cleanText = line.replace(/^(\[x\]|\[ \]|-\s*\[x\]|-\s*\[ \]|-\s*|\d+[\.\)]\s*)/i, '').trim();

    const itemEl = document.createElement('label');
    itemEl.className = 'plan-check-item' + (isChecked ? ' checked' : '');
    itemEl.style.cssText = 'display:flex; align-items:flex-start; gap:10px; padding:9px 12px; background:var(--bg-surface); border:1px solid var(--border-base); border-radius:var(--radius-sm); cursor:pointer; font-size:13px; transition:all 0.15s ease;';
    
    itemEl.innerHTML = `
      <input type="checkbox" ${isChecked ? 'checked' : ''} style="margin-top:2px; accent-color:var(--brand-primary); cursor:pointer;" />
      <span style="flex:1; line-height:1.4; color:var(--text-primary); ${isChecked ? 'text-decoration:line-through; opacity:0.6;' : ''}">${escapeHtml(cleanText || line)}</span>
    `;

    const checkbox = itemEl.querySelector('input');
    checkbox.onchange = (e) => {
      togglePlanItem(ga.id, idx, e.target.checked);
    };

    container.appendChild(itemEl);
  });

  const pct = Math.round((completedCount / lines.length) * 100);
  progBar.style.width = pct + '%';
  progText.textContent = `${completedCount} of ${lines.length} done (${pct}%)`;
}

window.togglePlanItem = async function(id, lineIndex, isChecked) {
  const ga = getAssignmentById(id);
  if (!ga || !ga.task_planning) return;

  const lines = ga.task_planning.split(/\r?\n/).filter(l => l.trim().length > 0);
  if (lineIndex < 0 || lineIndex >= lines.length) return;

  const origLine = lines[lineIndex];
  const cleanText = origLine.replace(/^(\[x\]|\[ \]|-\s*\[x\]|-\s*\[ \])/i, '').trim();
  lines[lineIndex] = isChecked ? `[x] ${cleanText}` : `[ ] ${cleanText}`;

  ga.task_planning = lines.join('\n');
  renderChecklistInView(ga);

  try {
    const res = await secureFetch('api/assignments.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'update_plan', assignment_id: id, task_planning: ga.task_planning })
    });
    const data = await res.json();
    if (data.success && typeof showToast === 'function') {
      showToast('Plan progress updated');
    }
  } catch (err) {
    console.error('Failed to update plan:', err);
  }
};

window.openEditAssignment = function(id) {
  const ga = getAssignmentById(id);
  if (!ga) return;

  document.getElementById('editGaId').value = ga.id;
  document.getElementById('editGaTitle').value = ga.title || '';
  document.getElementById('editGaCourse').value = ga.course_name || 'General Engineering';
  document.getElementById('editGaDueDate').value = ga.due_date || '';
  document.getElementById('editGaStatus').value = ga.status || 'In Progress';
  document.getElementById('editGaDescription').value = ga.description || '';
  document.getElementById('editGaTaskPlanning').value = ga.task_planning || '';

  document.getElementById('editAssignmentModal').classList.add('active');
};

window.submitEditAssignment = async function(e) {
  e.preventDefault();
  const id = parseInt(document.getElementById('editGaId').value, 10);
  const title = document.getElementById('editGaTitle').value.trim();
  const course_name = document.getElementById('editGaCourse').value;
  const due_date = document.getElementById('editGaDueDate').value.trim();
  const status = document.getElementById('editGaStatus').value;
  const description = document.getElementById('editGaDescription').value.trim();
  const task_planning = document.getElementById('editGaTaskPlanning').value.trim();

  const btn = document.getElementById('btnSubmitEditGA');
  btn.disabled = true;
  btn.textContent = 'Saving...';

  try {
    const res = await secureFetch('api/assignments.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'update', assignment_id: id, title, course_name, due_date, status, description, task_planning })
    });
    const data = await res.json();
    if (data.success) {
      if (typeof showToast === 'function') showToast('Assignment updated successfully!');
      setTimeout(() => location.reload(), 400);
    } else {
      alert(data.message || 'Failed to update assignment');
    }
  } catch (err) {
    console.error(err);
  } finally {
    btn.disabled = false;
    btn.textContent = 'Save Changes';
  }
};

window.deleteGroupAssignment = async function(id) {
  if (!confirm('Are you sure you want to delete this assignment?')) return;
  try {
    const res = await secureFetch('api/assignments.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'delete', assignment_id: id })
    });
    const data = await res.json();
    if (data.success) {
      if (typeof showToast === 'function') showToast('Assignment deleted.');
      const row = document.getElementById('groupRow-' + id);
      if (row) row.remove();
      setTimeout(() => location.reload(), 400);
    } else {
      alert(data.message || 'Failed to delete assignment');
    }
  } catch (err) {
    console.error(err);
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

function escapeHtml(str) {
  return (str || '').replace(/[&<>"']/g, function(m) {
    return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m];
  });
}

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
      const btnSubmit = document.getElementById('btnSubmitAddTask');
      if (btnSubmit) {
        btnSubmit.disabled = true;
        btnSubmit.textContent = 'Adding...';
      }

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
      } finally {
        if (btnSubmit) {
          btnSubmit.disabled = false;
          btnSubmit.textContent = 'Add Task';
        }
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
