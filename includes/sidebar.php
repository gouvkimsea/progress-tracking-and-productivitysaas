<?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!-- ============ GANTTPRO-STYLE SIDEBAR ============ -->
<aside class="sidebar ganttpro-sidebar" id="sidebar">

  <!-- Top Brand (Website: MINDRIFT, Business: bakery) -->
  <div class="sidebar-header">
    <div class="brand-title-row">
      <div class="brand-title" title="Platform Website: MINDRIFT">
        <span class="brand-bold">MINDRIFT</span>
      </div>
      <button class="icon-btn sidebar-collapse-btn" id="sidebarToggle" title="Collapse sidebar">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <line x1="4" y1="12" x2="16" y2="12"/><polyline points="10 6 4 12 10 18"/><line x1="20" y1="4" x2="20" y2="20"/>
        </svg>
      </button>
    </div>
    <div class="org-subtitle" title="Business Account: bakery">bakery</div>
  </div>

  <!-- Primary Action Button -->
  <div class="sidebar-action-wrap">
    <button type="button" class="btn-create-project" id="sidebarCreateProjectBtn" onclick="openCreateProjectModal()">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round">
        <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
      </svg>
      Create new project
    </button>
  </div>

  <!-- Navigation Items -->
  <div class="nav-section-scroll">
    <nav class="gantt-nav-list">
      
      <!-- All projects -->
      <a href="courses.php" class="gantt-nav-item <?= ($currentPage === 'courses.php') ? 'active' : ''; ?>">
        <svg class="gantt-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
        <span class="gantt-nav-text">All projects</span>
        <svg class="gantt-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>
      </a>

      <!-- My tasks -->
      <a href="assignments.php" class="gantt-nav-item <?= ($currentPage === 'assignments.php' || $currentPage === 'index.php') ? 'active' : ''; ?>">
        <svg class="gantt-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
        <span class="gantt-nav-text">My tasks</span>
      </a>

      <!-- Gantt Chart -->
      <a href="gantt.php" class="gantt-nav-item <?= ($currentPage === 'gantt.php') ? 'active' : ''; ?>">
        <svg class="gantt-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M7 8h6"/><path d="M10 12h8"/><path d="M7 16h5"/></svg>
        <span class="gantt-nav-text">Gantt Chart</span>
      </a>

      <!-- Kanban Board -->
      <a href="kanban.php" class="gantt-nav-item <?= ($currentPage === 'kanban.php') ? 'active' : ''; ?>">
        <svg class="gantt-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="5" height="18" rx="1"/><rect x="11" y="3" width="5" height="12" rx="1"/><rect x="19" y="3" width="5" height="15" rx="1"/></svg>
        <span class="gantt-nav-text">Kanban Board</span>
      </a>

      <!-- My time log -->
      <a href="analytics.php" class="gantt-nav-item <?= ($currentPage === 'analytics.php') ? 'active' : ''; ?>">
        <svg class="gantt-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        <span class="gantt-nav-text">My time log</span>
      </a>

      <!-- Reports -->
      <a href="reports.php" class="gantt-nav-item <?= ($currentPage === 'reports.php') ? 'active' : ''; ?>">
        <svg class="gantt-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="18" x2="9" y2="15"/><line x1="15" y1="18" x2="15" y2="9"/></svg>
        <span class="gantt-nav-text">Reports</span>
      </a>

      <!-- Workload -->
      <a href="workload.php" class="gantt-nav-item <?= ($currentPage === 'workload.php') ? 'active' : ''; ?>">
        <svg class="gantt-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
        <span class="gantt-nav-text">Workload</span>
      </a>

      <!-- Goals & OKRs -->
      <a href="goals.php" class="gantt-nav-item <?= ($currentPage === 'goals.php') ? 'active' : ''; ?>">
        <svg class="gantt-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>
        <span class="gantt-nav-text">Goals &amp; OKRs</span>
      </a>

      <!-- Flashcards Studio -->
      <a href="flashcards.php" class="gantt-nav-item <?= ($currentPage === 'flashcards.php') ? 'active' : ''; ?>">
        <svg class="gantt-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M7 15h10"/><path d="M7 9h6"/></svg>
        <span class="gantt-nav-text">MindCards Studio</span>
      </a>

      <div class="gantt-divider"></div>

      <!-- Communication hub -->
      <a href="community.php" class="gantt-nav-item <?= ($currentPage === 'community.php') ? 'active' : ''; ?>">
        <svg class="gantt-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        <span class="gantt-nav-text">Communication hub</span>
      </a>

      <!-- Files -->
      <a href="files.php" class="gantt-nav-item <?= ($currentPage === 'files.php') ? 'active' : ''; ?>">
        <svg class="gantt-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
        <span class="gantt-nav-text">Files</span>
      </a>

    </nav>



    <!-- Bottom Secondary Items -->
    <div class="gantt-nav-list" style="margin-top: 14px;">
      
      <!-- Notifications -->
      <a href="javascript:void(0)" class="gantt-nav-item" onclick="toggleNotifDropdown(event)">
        <svg class="gantt-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        <span class="gantt-nav-text">Notifications</span>
        <span class="notif-badge" id="sidebarNotifBadge">0</span>
        <svg class="gantt-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>
      </a>

      <!-- Search -->
      <a href="javascript:void(0)" class="gantt-nav-item" onclick="openCmdPalette(event)">
        <svg class="gantt-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <span class="gantt-nav-text">Search (⌘K)</span>
        <svg class="gantt-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>
      </a>

    </div>

  </div>

  <!-- PWA Install App Button -->
  <div style="padding: 0 14px 10px;" id="pwaInstallContainer">
    <button type="button" id="btnPwaInstall" onclick="installPwaApp()" style="width:100%; display:flex; align-items:center; justify-content:center; gap:8px; padding:7px 12px; background:rgba(108,92,231,0.08); border:1px dashed var(--purple); color:var(--purple); border-radius:8px; font-size:11.5px; font-weight:700; cursor:pointer; transition:all 0.2s ease;">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
      <span>Install App</span>
    </button>
  </div>

  <!-- Bottom Settings & Help Dual Pill Row -->
  <div class="sidebar-bottom-row">
    <a href="settings.php" class="gantt-pill-btn <?= ($currentPage === 'settings.php') ? 'active' : ''; ?>">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 13.5a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.9 2.9l-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6V20a2 2 0 1 1-4 0v-.2a1.7 1.7 0 0 0-1.1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1a2 2 0 1 1-2.9-2.9l.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.6-1H4a2 2 0 1 1 0-4h.2a1.7 1.7 0 0 0 1.6-1.1 1.7 1.7 0 0 0-.3-1.9l-.1-.1a2 2 0 1 1 2.9-2.9l.1.1a1.7 1.7 0 0 0 1.9.3H10a1.7 1.7 0 0 0 1-1.6V4a2 2 0 1 1 4 0v.2a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1a2 2 0 1 1 2.9 2.9l-.1.1a1.7 1.7 0 0 0-.3 1.9V10a1.7 1.7 0 0 0 1.6 1H20a2 2 0 1 1 0 4h-.2a1.7 1.7 0 0 0-1.4 1Z"/></svg>
      <span>Settings</span>
    </a>
    <a href="help.php" class="gantt-pill-btn <?= ($currentPage === 'help.php') ? 'active' : ''; ?>">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      <span>Help</span>
    </a>
  </div>

</aside>
