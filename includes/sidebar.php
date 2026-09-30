<?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar" id="sidebar">

  <!-- Brand Section -->
  <div class="sidebar-header">
    <div class="brand-title-row">
      <div class="brand-title">
        <span class="brand-bold">MINDRIFT</span>
      </div>
      <button class="sidebar-collapse-btn" id="sidebarToggle" title="Toggle sidebar" aria-label="Toggle sidebar">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <line x1="4" y1="12" x2="16" y2="12"/><polyline points="10 6 4 12 10 18"/><line x1="20" y1="4" x2="20" y2="20"/>
        </svg>
      </button>
    </div>
  </div>

  <!-- Primary Action -->
  <div class="sidebar-action-wrap">
    <button type="button" class="btn-create-project" id="sidebarCreateProjectBtn" onclick="openCreateProjectModal()">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
        <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
      </svg>
      <span>New project</span>
    </button>
  </div>

  <!-- Navigation List -->
  <div class="nav-section-scroll">
    <nav class="gantt-nav-list">

      <!-- Projects -->
      <a href="courses.php" class="gantt-nav-item <?= ($currentPage === 'courses.php') ? 'active' : ''; ?>">
        <svg class="gantt-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
        <span class="gantt-nav-text">Projects</span>
      </a>

      <!-- Tasks -->
      <a href="assignments.php" class="gantt-nav-item <?= ($currentPage === 'assignments.php') ? 'active' : ''; ?>">
        <svg class="gantt-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
        <span class="gantt-nav-text">Tasks</span>
      </a>

      <!-- Daily Journal & To-Do -->
      <a href="journal.php" class="gantt-nav-item <?= ($currentPage === 'journal.php') ? 'active' : ''; ?>">
        <svg class="gantt-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
        <span class="gantt-nav-text">Daily Journal</span>
      </a>

      <!-- Kanban Board -->
      <a href="kanban.php" class="gantt-nav-item <?= ($currentPage === 'kanban.php') ? 'active' : ''; ?>">
        <svg class="gantt-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="5" height="18" rx="1"/><rect x="11" y="3" width="5" height="12" rx="1"/><rect x="19" y="3" width="5" height="15" rx="1"/></svg>
        <span class="gantt-nav-text">Kanban</span>
      </a>

      <!-- Gantt Chart -->
      <a href="gantt.php" class="gantt-nav-item <?= ($currentPage === 'gantt.php') ? 'active' : ''; ?>">
        <svg class="gantt-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M7 8h6"/><path d="M10 12h8"/><path d="M7 16h5"/></svg>
        <span class="gantt-nav-text">Gantt</span>
      </a>

      <!-- Calendar -->
      <a href="calendar.php" class="gantt-nav-item <?= ($currentPage === 'calendar.php') ? 'active' : ''; ?>">
        <svg class="gantt-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        <span class="gantt-nav-text">Calendar</span>
      </a>

      <!-- Workload -->
      <a href="workload.php" class="gantt-nav-item <?= ($currentPage === 'workload.php') ? 'active' : ''; ?>">
        <svg class="gantt-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
        <span class="gantt-nav-text">Workload</span>
      </a>

      <!-- Time Log -->
      <a href="analytics.php" class="gantt-nav-item <?= ($currentPage === 'analytics.php') ? 'active' : ''; ?>">
        <svg class="gantt-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        <span class="gantt-nav-text">Time Log</span>
      </a>

      <!-- Reports -->
      <a href="reports.php" class="gantt-nav-item <?= ($currentPage === 'reports.php') ? 'active' : ''; ?>">
        <svg class="gantt-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="18" x2="9" y2="15"/><line x1="15" y1="18" x2="15" y2="9"/></svg>
        <span class="gantt-nav-text">Reports</span>
      </a>

      <!-- Goals / OKRs -->
      <a href="goals.php" class="gantt-nav-item <?= ($currentPage === 'goals.php') ? 'active' : ''; ?>">
        <svg class="gantt-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>
        <span class="gantt-nav-text">Objectives</span>
      </a>

      <!-- Files -->
      <a href="files.php" class="gantt-nav-item <?= ($currentPage === 'files.php') ? 'active' : ''; ?>">
        <svg class="gantt-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
        <span class="gantt-nav-text">Files</span>
      </a>

    </nav>
  </div>

  <!-- Bottom Navigation (Settings & Help) -->
  <div class="sidebar-bottom-row">
    <a href="settings.php" class="gantt-pill-btn <?= ($currentPage === 'settings.php') ? 'active' : ''; ?>">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 13.5a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.9 2.9l-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6V20a2 2 0 1 1-4 0v-.2a1.7 1.7 0 0 0-1.1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1a2 2 0 1 1-2.9-2.9l.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.6-1H4a2 2 0 1 1 0-4h.2a1.7 1.7 0 0 0 1.6-1.1 1.7 1.7 0 0 0-.3-1.9l-.1-.1a2 2 0 1 1 2.9-2.9l.1.1a1.7 1.7 0 0 0 1.9.3H10a1.7 1.7 0 0 0 1-1.6V4a2 2 0 1 1 4 0v.2a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1a2 2 0 1 1 2.9 2.9l-.1.1a1.7 1.7 0 0 0-.3 1.9V10a1.7 1.7 0 0 0 1.6 1H20a2 2 0 1 1 0 4h-.2a1.7 1.7 0 0 0-1.4 1Z"/></svg>
      <span>Settings</span>
    </a>
    <a href="help.php" class="gantt-pill-btn <?= ($currentPage === 'help.php') ? 'active' : ''; ?>">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      <span>Help</span>
    </a>
  </div>

</aside>
