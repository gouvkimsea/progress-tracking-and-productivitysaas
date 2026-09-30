<!-- ============ COMMAND PALETTE (CMD+K / CTRL+K) ============ -->
<div class="cmd-palette-overlay" id="cmdPaletteModal" style="display:none;">
  <div class="cmd-palette-card">
    
    <!-- Input Header -->
    <div style="display:flex; align-items:center; gap:10px; padding:12px 16px; border-bottom:1px solid var(--border-base);">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--text-muted);"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input type="text" id="cmdPaletteInput" placeholder="Type a command or jump to page..." autocomplete="off" style="flex:1; border:none; background:transparent; font-size:13.5px; font-weight:500; color:var(--text-primary); outline:none;" />
      <kbd style="font-size:10px; background:var(--bg-subtle); border:1px solid var(--border-base); border-radius:3px; padding:2px 5px; color:var(--text-muted); font-weight:600;">ESC</kbd>
    </div>

    <!-- Command List -->
    <div id="cmdPaletteResults" style="max-height:340px; overflow-y:auto; padding:6px 0;">
      
      <!-- Actions Group -->
      <div style="padding:6px 14px 4px; font-size:11px; font-weight:600; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.04em;">Actions</div>
      
      <div class="cmd-item" onclick="executeCmdAction('quick_task')">
        <div class="cmd-text">
          <span class="cmd-title">Create New Task</span>
          <span class="cmd-sub">Add assignment with project and priority</span>
        </div>
        <kbd class="cmd-shortcut">N</kbd>
      </div>

      <div class="cmd-item" onclick="executeCmdAction('theme')">
        <div class="cmd-text">
          <span class="cmd-title">Toggle Dark / Light Mode</span>
          <span class="cmd-sub">Switch interface appearance</span>
        </div>
        <kbd class="cmd-shortcut">Theme</kbd>
      </div>

      <div class="cmd-item" onclick="executeCmdAction('export_tasks')">
        <div class="cmd-text">
          <span class="cmd-title">Export Tasks to CSV</span>
          <span class="cmd-sub">Download spreadsheet of all assignments</span>
        </div>
        <kbd class="cmd-shortcut">CSV</kbd>
      </div>

      <div class="cmd-item" onclick="executeCmdAction('calendar_ics')">
        <div class="cmd-text">
          <span class="cmd-title">Download Calendar Feed (.ics)</span>
          <span class="cmd-sub">Sync deadlines with external calendars</span>
        </div>
        <kbd class="cmd-shortcut">iCal</kbd>
      </div>

      <div class="cmd-item" onclick="location.href='journal.php?action=quiz'">
        <div class="cmd-text">
          <span class="cmd-title">Daily Reflection Quiz</span>
          <span class="cmd-sub">Rate your day (1–5) and log accomplishments</span>
        </div>
        <kbd class="cmd-shortcut">Quiz</kbd>
      </div>

      <!-- Navigation Group -->
      <div style="padding:10px 14px 4px; font-size:11px; font-weight:600; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.04em; border-top:1px solid var(--border-base); margin-top:4px;">Navigation</div>

      <div class="cmd-item" onclick="location.href='index.php'">
        <div class="cmd-text"><span class="cmd-title">Dashboard</span><span class="cmd-sub">Overview and statistics</span></div>
      </div>

      <div class="cmd-item" onclick="location.href='journal.php'">
        <div class="cmd-text"><span class="cmd-title">Daily Journal &amp; To-Do</span><span class="cmd-sub">Daily checklist &amp; 1–5 reflection quiz</span></div>
        <kbd class="cmd-shortcut">J</kbd>
      </div>

      <div class="cmd-item" onclick="location.href='courses.php'">
        <div class="cmd-text"><span class="cmd-title">Projects</span><span class="cmd-sub">All active projects and milestones</span></div>
      </div>

      <div class="cmd-item" onclick="location.href='assignments.php'">
        <div class="cmd-text"><span class="cmd-title">Tasks</span><span class="cmd-sub">Task table and status tracking</span></div>
      </div>

      <div class="cmd-item" onclick="location.href='kanban.php'">
        <div class="cmd-text"><span class="cmd-title">Kanban Board</span><span class="cmd-sub">Visual task workflow</span></div>
      </div>

      <div class="cmd-item" onclick="location.href='gantt.php'">
        <div class="cmd-text"><span class="cmd-title">Gantt Chart</span><span class="cmd-sub">Project timelines and schedules</span></div>
      </div>

      <div class="cmd-item" onclick="location.href='calendar.php'">
        <div class="cmd-text"><span class="cmd-title">Calendar</span><span class="cmd-sub">Monthly deadline schedule</span></div>
      </div>

      <div class="cmd-item" onclick="location.href='workload.php'">
        <div class="cmd-text"><span class="cmd-title">Workload</span><span class="cmd-sub">Resource allocation matrix</span></div>
      </div>

      <div class="cmd-item" onclick="location.href='analytics.php'">
        <div class="cmd-text"><span class="cmd-title">Time Log</span><span class="cmd-sub">Logged work hours</span></div>
      </div>

      <div class="cmd-item" onclick="location.href='reports.php'">
        <div class="cmd-text"><span class="cmd-title">Reports</span><span class="cmd-sub">Project metrics and data export</span></div>
      </div>

      <div class="cmd-item" onclick="location.href='goals.php'">
        <div class="cmd-text"><span class="cmd-title">Objectives &amp; OKRs</span><span class="cmd-sub">Target deliverables</span></div>
      </div>

      <div class="cmd-item" onclick="location.href='files.php'">
        <div class="cmd-text"><span class="cmd-title">Files</span><span class="cmd-sub">Documents and attachments</span></div>
      </div>

    </div>

    <!-- Footer Tip -->
    <div style="display:flex; align-items:center; justify-content:space-between; padding:8px 14px; border-top:1px solid var(--border-base); background:var(--bg-subtle); font-size:11.5px; color:var(--text-muted);">
      <span>Navigate with <kbd style="font-size:10px; background:var(--bg-surface); border:1px solid var(--border-base); border-radius:3px; padding:1px 4px;">↑</kbd> <kbd style="font-size:10px; background:var(--bg-surface); border:1px solid var(--border-base); border-radius:3px; padding:1px 4px;">↓</kbd> <kbd style="font-size:10px; background:var(--bg-surface); border:1px solid var(--border-base); border-radius:3px; padding:1px 4px;">↵</kbd></span>
      <span>Press <kbd style="font-size:10px; background:var(--bg-surface); border:1px solid var(--border-base); border-radius:3px; padding:1px 4px;">Esc</kbd> to close</span>
    </div>

  </div>
</div>
