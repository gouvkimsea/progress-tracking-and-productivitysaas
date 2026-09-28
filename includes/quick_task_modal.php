<?php
$quickCourses = [];
if (isset($db, $userId)) {
    try {
        $stmtQC = $db->prepare("SELECT id, name, code FROM courses WHERE user_id = :uid ORDER BY name ASC");
        $stmtQC->execute(['uid' => $userId]);
        $quickCourses = $stmtQC->fetchAll();
    } catch (Throwable $e) {}
}
?>
<!-- Quick Add Task Modal -->
<div class="modal-overlay" id="quickTaskModal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="quickTaskTitle">
  <div class="modal-card" style="max-width: 580px;">
    
    <!-- Modal Header -->
    <div class="modal-header">
      <h3 class="modal-title" id="quickTaskTitle">Create Task</h3>
      <button type="button" class="modal-close-btn" id="btnCloseQuickTask" aria-label="Close modal">&times;</button>
    </div>

    <!-- Quick Task Input -->
    <div style="padding: 14px 18px; border-bottom: 1px solid var(--border-base);">
      <input 
        type="text" 
        id="omniTaskInput" 
        class="form-input" 
        placeholder="e.g. Implement user authentication #Urgent in 3 days" 
        autocomplete="off" 
        spellcheck="false" 
        style="font-size: 14px; padding: 9px 12px;"
      />
      <div style="display:flex; align-items:center; justify-content:space-between; margin-top:6px; font-size:11.5px; color:var(--text-muted);">
        <span>Enter task details. Use keywords like <code>#urgent</code> or <code>due tomorrow</code> to set values.</span>
        <button type="button" id="btnOmniClear" style="background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:11.5px; display:none;">Clear</button>
      </div>
    </div>

    <!-- Parsed Attribute Summary Strip -->
    <div style="padding: 8px 18px; background: var(--bg-subtle); border-bottom: 1px solid var(--border-base); display:flex; flex-wrap:wrap; gap:6px; font-size:11.5px;">
      <span class="badge" id="chipPriority" style="background:var(--bg-surface); border:1px solid var(--border-base); color:var(--text-secondary);">
        Priority: <strong id="chipPriorityVal" style="margin-left:2px;">Medium</strong>
      </span>
      <span class="badge" style="background:var(--bg-surface); border:1px solid var(--border-base); color:var(--text-secondary);">
        Project: <strong id="chipProjectVal" style="margin-left:2px;">General</strong>
      </span>
      <span class="badge" style="background:var(--bg-surface); border:1px solid var(--border-base); color:var(--text-secondary);">
        Due: <strong id="chipDueDateVal" style="margin-left:2px;">In 3 days</strong>
      </span>
      <span class="badge" style="background:var(--bg-surface); border:1px solid var(--border-base); color:var(--text-secondary);">
        Assignee: <strong id="chipAssigneeVal" style="margin-left:2px;"><?= htmlspecialchars($user['name'] ?? 'User'); ?></strong>
      </span>
      <!-- Hidden span for title parsing sync -->
      <span id="chipTitleVal" style="display:none;"></span>
    </div>

    <!-- Explicit Field Controls -->
    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; padding: 14px 18px;">
      <div class="form-group">
        <label class="form-label" for="omniSelectProject">Project</label>
        <select id="omniSelectProject" class="form-input">
          <option value="2dapp">General Tasks</option>
          <?php foreach ($quickCourses as $c): ?>
            <option value="<?= htmlspecialchars($c['name']); ?>">
              <?= htmlspecialchars($c['name']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label" for="omniSelectPriority">Priority</label>
        <select id="omniSelectPriority" class="form-input">
          <option value="Urgent">Urgent</option>
          <option value="High">High</option>
          <option value="Medium" selected>Medium</option>
          <option value="Low">Low</option>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label" for="omniInputAssignee">Assignee</label>
        <input type="text" id="omniInputAssignee" class="form-input" value="<?= htmlspecialchars($user['name'] ?? 'User'); ?>" />
      </div>

      <div class="form-group">
        <label class="form-label" for="omniInputDueDate">Due Date</label>
        <input type="date" id="omniInputDueDate" class="form-input" value="<?= date('Y-m-d', strtotime('+3 days')); ?>" />
      </div>
    </div>

    <!-- Modal Footer -->
    <div class="modal-footer">
      <button type="button" class="btn-cancel" id="btnCancelQuickTask">Cancel</button>
      <button type="button" class="btn-save" id="btnSubmitQuickTask">Create Task</button>
    </div>

  </div>
</div>
