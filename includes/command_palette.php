<!-- ============ SPOTLIGHT COMMAND PALETTE (CMD+K / CTRL+K) ============ -->
<div class="cmd-palette-overlay" id="cmdPaletteModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); backdrop-filter:blur(6px); z-index:10000; align-items:flex-start; justify-content:center; padding-top:100px;">
  <div class="cmd-palette-card" style="background:var(--panel-bg); border:1px solid var(--border); border-radius:14px; width:560px; max-width:92vw; box-shadow:0 24px 60px rgba(0,0,0,0.3); overflow:hidden; animation:modalZoomIn 0.15s cubic-bezier(0.16, 1, 0.3, 1);">
    
    <!-- Input Header -->
    <div style="display:flex; align-items:center; gap:10px; padding:14px 18px; border-bottom:1px solid var(--border-soft);">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--muted)" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input type="text" id="cmdPaletteInput" placeholder="Type a command, jump to page, or search tasks..." autocomplete="off" style="flex:1; border:none; background:transparent; font-size:15px; font-weight:600; color:var(--ink); outline:none;" />
      <kbd style="font-size:10.5px; background:var(--card-bg); border:1px solid var(--border); border-radius:4px; padding:2px 6px; color:var(--muted); font-weight:700;">ESC</kbd>
    </div>

    <!-- Command List -->
    <div id="cmdPaletteResults" style="max-height:360px; overflow-y:auto; padding:8px 0;">
      
      <!-- Actions Group -->
      <div style="padding:6px 16px; font-size:11px; font-weight:800; color:var(--muted); text-transform:uppercase; letter-spacing:0.04em;">Quick Actions</div>
      
      <div class="cmd-item" onclick="executeCmdAction('quick_task')">
        <span class="cmd-icon">⚡</span>
        <div class="cmd-text">
          <span class="cmd-title">Quick Add Task (Natural Language)</span>
          <span class="cmd-sub">Parse #priority, @assignee, ^project, and due date</span>
        </div>
        <kbd class="cmd-shortcut">N</kbd>
      </div>

      <div class="cmd-item" onclick="executeCmdAction('pomodoro')">
        <span class="cmd-icon">🍅</span>
        <div class="cmd-text">
          <span class="cmd-title">Start 25m Pomodoro Focus</span>
          <span class="cmd-sub">Launch deep work interval with chimes</span>
        </div>
        <kbd class="cmd-shortcut">Focus</kbd>
      </div>

      <div class="cmd-item" onclick="executeCmdAction('copilot')">
        <span class="cmd-icon">🤖</span>
        <div class="cmd-text">
          <span class="cmd-title">Open AI Productivity Copilot</span>
          <span class="cmd-sub">Break down goals into actionable subtasks</span>
        </div>
        <kbd class="cmd-shortcut">AI</kbd>
      </div>

      <div class="cmd-item" onclick="executeCmdAction('theme')">
        <span class="cmd-icon">🌗</span>
        <div class="cmd-text">
          <span class="cmd-title">Toggle Dark / Light Theme</span>
          <span class="cmd-sub">Switch visual appearance system-wide</span>
        </div>
        <kbd class="cmd-shortcut">Theme</kbd>
      </div>

      <div class="cmd-item" onclick="executeCmdAction('export_tasks')">
        <span class="cmd-icon">📥</span>
        <div class="cmd-text">
          <span class="cmd-title">Export Tasks to CSV</span>
          <span class="cmd-sub">Download spreadsheet of all assignments</span>
        </div>
        <kbd class="cmd-shortcut">CSV</kbd>
      </div>

      <div class="cmd-item" onclick="executeCmdAction('calendar_ics')">
        <span class="cmd-icon">📅</span>
        <div class="cmd-text">
          <span class="cmd-title">Sync to Calendar (.ics feed)</span>
          <span class="cmd-sub">Apple Calendar, Google Calendar, Outlook</span>
        </div>
        <kbd class="cmd-shortcut">iCal</kbd>
      </div>

      <!-- Navigation Group -->
      <div style="padding:10px 16px 6px; font-size:11px; font-weight:800; color:var(--muted); text-transform:uppercase; letter-spacing:0.04em; border-top:1px solid var(--border-soft); margin-top:6px;">Navigation</div>

      <div class="cmd-item" onclick="location.href='index.php'">
        <span class="cmd-icon">📊</span>
        <div class="cmd-text"><span class="cmd-title">Dashboard</span><span class="cmd-sub">Overview, Streaks &amp; Pomodoro</span></div>
      </div>

      <div class="cmd-item" onclick="location.href='kanban.php'">
        <span class="cmd-icon">📋</span>
        <div class="cmd-text"><span class="cmd-title">Kanban Board</span><span class="cmd-sub">Drag-and-drop task workflow</span></div>
      </div>

      <div class="cmd-item" onclick="location.href='gantt.php'">
        <span class="cmd-icon">📈</span>
        <div class="cmd-text"><span class="cmd-title">Gantt Timeline</span><span class="cmd-sub">Interactive schedules &amp; dependencies</span></div>
      </div>

      <div class="cmd-item" onclick="location.href='calendar.php'">
        <span class="cmd-icon">📆</span>
        <div class="cmd-text"><span class="cmd-title">Study Calendar</span><span class="cmd-sub">Monthly deadlines &amp; events</span></div>
      </div>

      <div class="cmd-item" onclick="location.href='goals.php'">
        <span class="cmd-icon">🎯</span>
        <div class="cmd-text"><span class="cmd-title">Objectives &amp; OKRs</span><span class="cmd-sub">Quarterly milestones &amp; target metrics</span></div>
      </div>

      <div class="cmd-item" onclick="location.href='assignments.php'">
        <span class="cmd-icon">✅</span>
        <div class="cmd-text"><span class="cmd-title">My Tasks &amp; Assignments</span><span class="cmd-sub">Priority and countdown badges</span></div>
      </div>

      <div class="cmd-item" onclick="location.href='files.php'">
        <span class="cmd-icon">📁</span>
        <div class="cmd-text"><span class="cmd-title">File Manager</span><span class="cmd-sub">Documents, uploads &amp; attachments</span></div>
      </div>

      <div class="cmd-item" onclick="location.href='flashcards.php'">
        <span class="cmd-icon">🗂️</span>
        <div class="cmd-text"><span class="cmd-title">MindCards Flashcards</span><span class="cmd-sub">Spaced repetition revision studio</span></div>
      </div>

      <div class="cmd-item" onclick="location.href='portfolio.php'">
        <span class="cmd-icon">🎓</span>
        <div class="cmd-text"><span class="cmd-title">Executive Learning Portfolio</span><span class="cmd-sub">Verified credential &amp; competency transcript</span></div>
      </div>

      <div class="cmd-item" onclick="location.href='reports.php'">
        <span class="cmd-icon">📑</span>
        <div class="cmd-text"><span class="cmd-title">Reports Hub</span><span class="cmd-sub">Analytics &amp; CSV data exports</span></div>
      </div>

    </div>

    <!-- Footer Tip -->
    <div style="display:flex; align-items:center; justify-content:space-between; padding:10px 16px; border-top:1px solid var(--border-soft); background:var(--card-bg); font-size:11.5px; color:var(--muted);">
      <span>Navigate with <kbd style="font-size:10px; background:var(--panel-bg); border:1px solid var(--border); border-radius:3px; padding:1px 4px;">↑</kbd> <kbd style="font-size:10px; background:var(--panel-bg); border:1px solid var(--border); border-radius:3px; padding:1px 4px;">↓</kbd> and <kbd style="font-size:10px; background:var(--panel-bg); border:1px solid var(--border); border-radius:3px; padding:1px 4px;">↵</kbd></span>
      <span>Press <kbd style="font-size:10px; background:var(--panel-bg); border:1px solid var(--border); border-radius:3px; padding:1px 4px;">Esc</kbd> to close</span>
    </div>

  </div>
</div>
