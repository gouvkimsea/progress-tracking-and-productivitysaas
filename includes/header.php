<!-- ============ REUSABLE TOPBAR HEADER ============ -->
<header class="topbar">
  <div>
    <p class="greeting">Welcome, <?= htmlspecialchars($user['name'] ?? 'User'); ?></p>
    <h1 class="date" id="todayDate"><?= date('l, M j'); ?></h1>
  </div>

  <!-- Global Real-Time Search Bar -->
  <div style="position:relative; width:240px;">
    <input type="text" id="globalSearchInput" placeholder="Search tasks, projects..." class="form-input" style="padding-left:32px; border-radius:6px; height:34px;" />
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="position:absolute; left:10px; top:50%; transform:translateY(-50%); color:var(--text-muted);"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
    <div id="globalSearchResults" style="display:none; position:absolute; top:38px; left:0; right:0; background:var(--bg-surface); border:1px solid var(--border-base); border-radius:6px; box-shadow:var(--shadow-md); z-index:999; max-height:260px; overflow-y:auto; padding:4px 0;"></div>
  </div>

  <div class="topbar-right">
    <!-- Quick Add Task Button -->
    <button class="btn-primary" id="quickTaskTriggerBtn" title="Quick Add Task (Press N)" type="button" style="padding:6px 12px; font-size:12.5px;">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      <span>New Task</span>
      <kbd style="padding:1px 5px; font-size:10px; background:rgba(255,255,255,0.2); border-radius:3px; font-family:inherit; color:#fff; margin-left:4px;">N</kbd>
    </button>

    <!-- Command Palette Shortcut Button -->
    <button class="btn-secondary" id="cmdPaletteTriggerBtn" title="Press Ctrl+K or Cmd+K" type="button" style="padding:6px 10px; font-size:12.5px;">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <kbd style="padding:1px 5px; font-size:10px; background:var(--bg-subtle); border:1px solid var(--border-base); border-radius:3px; font-family:inherit; color:var(--text-secondary);">⌘K</kbd>
    </button>

    <!-- Notification Bell Button & Drawer -->
    <div style="position:relative;">
      <button class="icon-btn" id="notifBellBtn" title="Notifications" type="button" aria-label="Notifications" style="width:32px; height:32px; border:1px solid var(--border-base); border-radius:6px; background:var(--bg-surface);">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        <span class="badge" id="notifBadge" style="display:none; position:absolute; top:-3px; right:-3px; background:var(--status-urgent-text); color:#fff; font-size:9.5px; padding:1px 4px; border-radius:6px;">0</span>
      </button>

      <!-- Dropdown Tray -->
      <div class="notif-dropdown" id="notifDropdown" style="display:none;">
        <div style="display:flex; align-items:center; justify-content:space-between; padding:10px 14px; border-bottom:1px solid var(--border-base); background:var(--bg-subtle);">
          <span style="font-size:12px; font-weight:600; color:var(--text-primary);">Notifications</span>
          <button type="button" onclick="markAllNotificationsRead()" style="background:none; border:none; color:var(--brand-primary); font-size:11.5px; font-weight:500; cursor:pointer;">Mark all read</button>
        </div>
        <div id="notifList" style="max-height:280px; overflow-y:auto;">
          <div style="text-align:center; padding:20px; color:var(--text-muted); font-size:12px;">Loading notifications...</div>
        </div>
      </div>
    </div>

    <!-- Theme Toggle -->
    <button class="theme-toggle-btn" id="themeToggleBtn" title="Toggle Light / Dark Mode" aria-label="Toggle Theme">
      <svg class="sun-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
      <svg class="moon-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
    </button>

    <!-- User Profile Badge -->
    <div class="user-profile-badge">
      <div class="avatar-circle"><?= strtoupper(substr($user['name'] ?? 'U', 0, 1)); ?></div>
      <div class="user-info">
        <span class="user-name"><?= htmlspecialchars($user['name'] ?? 'User'); ?></span>
      </div>
      <a href="api/logout.php" class="btn-logout" title="Sign Out">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        <span>Sign Out</span>
      </a>
    </div>

    <button class="icon-btn mobile-menu-btn" id="mobileMenuBtn">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="7" x2="20" y2="7"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="17" x2="20" y2="17"/></svg>
    </button>
  </div>
</header>

<?php require_once __DIR__ . '/command_palette.php'; ?>
<?php require_once __DIR__ . '/quick_task_modal.php'; ?>
<?php require_once __DIR__ . '/create_project_modal.php'; ?>
<?php require_once __DIR__ . '/log_activity_modal.php'; ?>
