<!-- ============ REUSABLE TOPBAR HEADER ============ -->
<header class="topbar">
  <div>
    <p class="greeting">Welcome back, <?= htmlspecialchars($user['name'] ?? 'Learner'); ?>!</p>
    <h1 class="date" id="todayDate"><?= date('D, M j'); ?></h1>
  </div>

  <!-- Global Real-Time Search Bar -->
  <div style="position:relative; width:280px;">
    <input type="text" id="globalSearchInput" placeholder="Global Search..." style="width:100%; padding:8px 12px 8px 34px; border:1px solid var(--border); border-radius:20px; font-size:13px; outline:none; background:var(--panel-bg); color:var(--ink);" />
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--muted)" stroke-width="2" style="position:absolute; left:12px; top:50%; transform:translateY(-50%);"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
    <div id="globalSearchResults" style="display:none; position:absolute; top:42px; left:0; right:0; background:var(--panel-bg); border:1px solid var(--border); border-radius:10px; box-shadow:0 10px 25px rgba(0,0,0,0.25); z-index:999; max-height:260px; overflow-y:auto; padding:6px 0;"></div>
  </div>

  <div class="topbar-right">
    <!-- Omni-Bar Quick Add Task Button -->
    <button class="quick-task-topbar-btn" id="quickTaskTriggerBtn" title="Quick Add Task (Press N)" type="button" style="display:flex; align-items:center; gap:6px; padding:6px 12px; background:linear-gradient(135deg, var(--purple), #4F46E5); border:none; border-radius:8px; font-size:12px; color:#fff; cursor:pointer; font-weight:700; box-shadow:0 2px 6px rgba(108,92,231,0.25);">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      <span>+ Task</span>
      <kbd style="padding:1px 5px; font-size:10px; background:rgba(255,255,255,0.25); border-radius:4px; font-family:inherit; color:#fff;">N</kbd>
    </button>

    <!-- Command Palette Shortcut Button -->
    <button class="cmd-palette-btn" id="cmdPaletteTriggerBtn" title="Press Ctrl+K or Cmd+K" type="button" style="display:flex; align-items:center; gap:6px; padding:6px 12px; background:var(--card-bg); border:1px solid var(--border); border-radius:8px; font-size:12px; color:var(--muted); cursor:pointer; font-weight:600;">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <span>Quick Actions</span>
      <kbd style="padding:1px 5px; font-size:10px; background:var(--panel-bg); border:1px solid var(--border); border-radius:4px; font-family:inherit;">⌘K</kbd>
    </button>

    <!-- Notification Bell Button & Drawer -->
    <div style="position:relative;">
      <button class="icon-btn" id="notifBellBtn" title="Notifications" type="button" aria-label="Notifications" style="position:relative; width:38px; height:38px; display:flex; align-items:center; justify-content:center; background:var(--panel-bg); border:1px solid var(--border); border-radius:8px; cursor:pointer;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        <span class="badge" id="notifBadge" style="display:none; position:absolute; top:-4px; right:-4px; background:#EF4444; color:#fff; font-size:10px; font-weight:800; padding:1px 5px; border-radius:10px;">0</span>
      </button>

      <!-- Dropdown Tray -->
      <div class="notif-dropdown" id="notifDropdown" style="display:none; position:absolute; right:0; top:46px; width:340px; background:var(--panel-bg); border:1px solid var(--border); border-radius:12px; box-shadow:0 12px 36px rgba(0,0,0,0.18); z-index:9999; overflow:hidden;">
        <div style="display:flex; align-items:center; justify-content:space-between; padding:12px 16px; border-bottom:1px solid var(--border-soft); background:var(--card-bg);">
          <span style="font-size:13px; font-weight:800; color:var(--ink);">🔔 Notifications &amp; Alerts</span>
          <button type="button" onclick="markAllNotificationsRead()" style="background:none; border:none; color:var(--purple); font-size:11px; font-weight:700; cursor:pointer;">Mark all read</button>
        </div>
        <div id="notifList" style="max-height:300px; overflow-y:auto; padding:4px 0;">
          <div style="text-align:center; padding:20px; color:var(--muted); font-size:12px;">Loading alerts...</div>
        </div>
      </div>
    </div>

    <button class="theme-toggle-btn" id="themeToggleBtn" title="Toggle Light / Dark Mode" aria-label="Toggle Theme">
      <svg class="sun-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
      <svg class="moon-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
    </button>
    <div class="user-profile-badge">
      <div class="avatar-circle"><?= strtoupper(substr($user['name'] ?? 'L', 0, 1)); ?></div>
      <div class="user-info">
        <span class="user-name"><?= htmlspecialchars($user['name'] ?? 'User'); ?></span>
        <span class="user-email"><?= htmlspecialchars($user['email'] ?? ''); ?></span>
      </div>
      <a href="api/logout.php" class="btn-logout" title="Sign Out">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        Logout
      </a>
    </div>
    <button class="icon-btn mobile-menu-btn" id="mobileMenuBtn">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="7" x2="20" y2="7"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="17" x2="20" y2="17"/></svg>
    </button>
  </div>
</header>

<!-- Modal: Create New Project -->
<div class="modal-overlay" id="createProjectModalOverlay">
  <div class="modal-card">
    <div class="modal-header">
      <h3 class="modal-title">Create New Project</h3>
      <button class="modal-close-btn" id="btnCloseProjectModal">&times;</button>
    </div>
    <form id="createProjectForm">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label" for="projName">Project Name</label>
          <input type="text" id="projName" class="form-input" placeholder="e.g. E-Commerce Redesign" required />
        </div>
        <div class="form-group">
          <label class="form-label" for="projCode">Project Code (Short 2-4 letters)</label>
          <input type="text" id="projCode" class="form-input" placeholder="e.g. EC" maxlength="4" value="EC" required />
        </div>
        <div class="form-group">
          <label class="form-label" for="projCategory">Category</label>
          <select id="projCategory" class="form-input">
            <option value="Design">Design</option>
            <option value="Programming">Programming</option>
            <option value="Data Science">Data Science</option>
            <option value="Business">Business</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label" for="projLevel">Difficulty Level</label>
          <select id="projLevel" class="form-input">
            <option value="Beginner">Beginner</option>
            <option value="Intermediate" selected>Intermediate</option>
            <option value="Advanced">Advanced</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label" for="projModules">Total Modules</label>
          <input type="number" id="projModules" class="form-input" min="1" max="100" value="10" required />
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-cancel" id="btnCancelProjectModal">Cancel</button>
        <button type="submit" class="btn-save" id="btnSubmitProjectModal">Create Project</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal: Upgrade Account -->
<div class="modal-overlay" id="upgradeModalOverlay">
  <div class="modal-card">
    <div class="modal-header">
      <h3 class="modal-title">Upgrade Account</h3>
      <button class="modal-close-btn" id="btnCloseUpgradeModal">&times;</button>
    </div>
    <div class="modal-body" style="text-align:center; padding:30px 20px;">
      <div style="font-size:42px; margin-bottom:10px;">⚡</div>
      <h2 style="margin:0; font-size:20px; font-weight:800; color:#111827;">Business Plan Active</h2>
      <button type="button" class="btn-upgrade-account" style="width:100%; padding:12px; font-size:16px;" onclick="if(typeof showToast==='function'){ showToast('🎉 Subscription upgraded to Enterprise Plan!'); } else { alert('Subscription upgraded!'); } document.getElementById('upgradeModalOverlay').classList.remove('active');">Confirm Upgrade ($19/mo)</button>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/copilot.php'; ?>
<?php require_once __DIR__ . '/command_palette.php'; ?>
<?php require_once __DIR__ . '/quick_task_modal.php'; ?>
