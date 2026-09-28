<?php
// Retrieve user courses for project dropdown if database connection is available
$quickCourses = [];
if (isset($db, $userId)) {
    try {
        $stmtQC = $db->prepare("SELECT id, name, code FROM courses WHERE user_id = :uid ORDER BY name ASC");
        $stmtQC->execute(['uid' => $userId]);
        $quickCourses = $stmtQC->fetchAll();
    } catch (Throwable $e) {}
}
?>
<!-- ============ OMNI-BAR QUICK-ADD TASK MODAL ============ -->
<div class="quick-task-overlay" id="quickTaskModal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="quickTaskTitle">
  <div class="quick-task-card">
    
    <!-- Modal Header -->
    <div class="quick-task-header">
      <div class="quick-task-header-left">
        <span class="quick-task-badge">⚡ Omni-Bar</span>
        <h3 class="quick-task-title" id="quickTaskTitle">Quick Add Task</h3>
      </div>
      <div class="quick-task-header-right">
        <span class="quick-task-kbd-hint">Press <kbd>Esc</kbd> to close</span>
        <button type="button" class="modal-close-btn" id="btnCloseQuickTask" aria-label="Close modal">&times;</button>
      </div>
    </div>

    <!-- Main Omni Input Box -->
    <div class="quick-task-input-wrapper">
      <svg class="quick-task-input-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="10"/>
        <line x1="12" y1="8" x2="12" y2="16"/>
        <line x1="8" y1="12" x2="16" y2="12"/>
      </svg>
      <input 
        type="text" 
        id="omniTaskInput" 
        class="omni-task-input" 
        placeholder="e.g. Redesign user checkout flow ^UI #Urgent @Alex due tomorrow" 
        autocomplete="off" 
        spellcheck="false" 
      />
      <button type="button" class="omni-clear-btn" id="btnOmniClear" title="Clear input" style="display:none;">&times;</button>
    </div>

    <!-- Live Natural Language Parser Preview Strip -->
    <div class="omni-preview-strip" id="omniPreviewStrip">
      <div class="omni-preview-label">Parsed Attributes:</div>
      <div class="omni-preview-chips">
        <div class="omni-chip chip-title" id="chipTitle" title="Clean Task Title">
          <span class="chip-icon">📌</span>
          <span class="chip-val" id="chipTitleVal">Type a task above...</span>
        </div>
        <div class="omni-chip chip-project" id="chipProject" title="Course / Project">
          <span class="chip-icon">📂</span>
          <span class="chip-val" id="chipProjectVal">General</span>
        </div>
        <div class="omni-chip chip-priority priority-medium" id="chipPriority" title="Priority Level">
          <span class="chip-icon">⚡</span>
          <span class="chip-val" id="chipPriorityVal">Medium</span>
        </div>
        <div class="omni-chip chip-assignee" id="chipAssignee" title="Assignee">
          <span class="chip-icon">👤</span>
          <span class="chip-val" id="chipAssigneeVal"><?= htmlspecialchars($user['name'] ?? 'Alex'); ?></span>
        </div>
        <div class="omni-chip chip-date" id="chipDueDate" title="Due Date">
          <span class="chip-icon">📅</span>
          <span class="chip-val" id="chipDueDateVal">In 3 days</span>
        </div>
      </div>
    </div>

    <!-- Manual Adjusters Bar (Collapse/Expandable or Inline) -->
    <div class="omni-adjusters-row">
      <div class="omni-adjuster-item">
        <label class="omni-adj-label">Project</label>
        <select id="omniSelectProject" class="omni-adj-select">
          <option value="2dapp">2dapp (Default)</option>
          <?php foreach ($quickCourses as $c): ?>
            <option value="<?= htmlspecialchars($c['name']); ?>" <?= (strpos(strtolower($c['name']), 'ui') !== false) ? 'selected' : ''; ?>>
              <?= htmlspecialchars($c['name']); ?> (<?= htmlspecialchars($c['code'] ?? 'CS'); ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="omni-adjuster-item">
        <label class="omni-adj-label">Priority</label>
        <select id="omniSelectPriority" class="omni-adj-select">
          <option value="Urgent">🔴 Urgent</option>
          <option value="High">🟠 High</option>
          <option value="Medium" selected>🔵 Medium</option>
          <option value="Low">🟢 Low</option>
        </select>
      </div>

      <div class="omni-adjuster-item">
        <label class="omni-adj-label">Assignee</label>
        <input type="text" id="omniInputAssignee" class="omni-adj-input" value="<?= htmlspecialchars($user['name'] ?? 'Alex'); ?>" placeholder="Assignee" />
      </div>

      <div class="omni-adjuster-item">
        <label class="omni-adj-label">Due Date</label>
        <input type="date" id="omniInputDueDate" class="omni-adj-input" value="<?= date('Y-m-d', strtotime('+3 days')); ?>" />
      </div>
    </div>

    <!-- Syntax Quick Helpers -->
    <div class="omni-syntax-helpers">
      <span class="syntax-guide-label">Smart Syntax Tags:</span>
      <button type="button" class="syntax-tag" data-tag="#Urgent">#Urgent</button>
      <button type="button" class="syntax-tag" data-tag="#High">#High</button>
      <button type="button" class="syntax-tag" data-tag="#Low">#Low</button>
      <button type="button" class="syntax-tag" data-tag="due tomorrow">due tomorrow</button>
      <button type="button" class="syntax-tag" data-tag="due Friday">due Friday</button>
      <button type="button" class="syntax-tag" data-tag="in 2 days">in 2 days</button>
      <button type="button" class="syntax-tag" data-tag="@<?= htmlspecialchars($user['name'] ?? 'Alex'); ?>">@<?= htmlspecialchars($user['name'] ?? 'Alex'); ?></button>
      <?php if (!empty($quickCourses)): ?>
        <button type="button" class="syntax-tag" data-tag="^<?= htmlspecialchars($quickCourses[0]['name']); ?>">^<?= htmlspecialchars($quickCourses[0]['name']); ?></button>
      <?php endif; ?>
    </div>

    <!-- Modal Footer -->
    <div class="quick-task-footer">
      <div class="quick-task-footer-tip">
        <span>💡 Tip: Type naturally — tags like <code>#high</code>, <code>@alex</code>, <code>due tomorrow</code> parse automatically!</span>
      </div>
      <div class="quick-task-footer-actions">
        <button type="button" class="btn-cancel" id="btnCancelQuickTask">Cancel</button>
        <button type="button" class="btn-save" id="btnSubmitQuickTask" style="display:flex; align-items:center; gap:6px;">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
          <span>Create Task</span>
          <kbd class="btn-kbd-hint">↵</kbd>
        </button>
      </div>
    </div>

  </div>
</div>
