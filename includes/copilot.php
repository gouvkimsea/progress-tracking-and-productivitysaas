<!-- Mindrift AI Productivity Copilot Drawer -->
<div class="copilot-drawer" id="copilotDrawer">
  <div class="copilot-header">
    <div style="display:flex; align-items:center; gap:8px;">
      <div class="copilot-avatar">🤖</div>
      <div>
        <h4 style="margin:0; font-size:15px; font-weight:800; color:var(--ink);">Mindrift Copilot</h4>
        <span style="font-size:11px; color:#10B981; font-weight:600;">● Active &amp; Ready</span>
      </div>
    </div>
    <button type="button" class="copilot-close-btn" id="btnCloseCopilot">&times;</button>
  </div>

  <div class="copilot-nav">
    <button type="button" class="copilot-tab active" data-copilot-tab="generator">Task Generator</button>
    <button type="button" class="copilot-tab" data-copilot-tab="coaching">Daily Coaching</button>
  </div>

  <div class="copilot-body">
    <!-- Generator Tab -->
    <div class="copilot-tab-content active" id="copilotTabGenerator">
      <p style="font-size:12.5px; color:var(--muted); margin-top:0; margin-bottom:12px;">
        Enter any project goal or study topic. Copilot will architect structured subtasks with estimated hours and priorities.
      </p>
      <form id="copilotBreakdownForm">
        <div style="margin-bottom:10px;">
          <input type="text" id="copilotGoalInput" class="form-input" placeholder="e.g. Build User Authentication" style="font-size:13px; padding:9px 12px;" required />
        </div>
        <button type="submit" id="btnCopilotGenerate" class="btn-save" style="width:100%; justify-content:center; padding:9px; font-size:13px;">
          ⚡ Break Down Goal
        </button>
      </form>

      <div id="copilotResultsArea" style="margin-top:14px; display:none;">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:8px;">
          <span style="font-size:12px; font-weight:700; color:var(--ink);">Generated Tasks:</span>
          <button type="button" id="btnAddCopilotTasks" class="btn-save" style="font-size:11.5px; padding:4px 10px; background:#10B981;">
            + Add All to Board
          </button>
        </div>
        <div id="copilotTaskList" style="display:flex; flex-direction:column; gap:6px; max-height:260px; overflow-y:auto;"></div>
      </div>
    </div>

    <!-- Coaching Tab -->
    <div class="copilot-tab-content" id="copilotTabCoaching" style="display:none;">
      <div style="background:var(--panel-bg); border:1px solid var(--border); border-radius:8px; padding:12px; margin-bottom:12px;">
        <div style="font-size:12px; font-weight:700; color:var(--ink); margin-bottom:4px;">🔥 Streak &amp; Momentum</div>
        <div style="font-size:12px; color:var(--muted); line-height:1.4;">
          Consistency is king. Complete 1 Pomodoro session (25 mins) today to safeguard your learning streak and unlock new progress badges!
        </div>
      </div>

      <div style="background:var(--panel-bg); border:1px solid var(--border); border-radius:8px; padding:12px; margin-bottom:12px;">
        <div style="font-size:12px; font-weight:700; color:var(--ink); margin-bottom:4px;">💡 Pro Tip for Focus</div>
        <div style="font-size:12px; color:var(--muted); line-height:1.4;">
          Use the <b>25m Focus</b> Pomodoro widget on the Dashboard. Studies show short, dedicated bursts with 5-minute breaks boost retention by up to 38%.
        </div>
      </div>

      <div style="background:var(--panel-bg); border:1px solid var(--border); border-radius:8px; padding:12px;">
        <div style="font-size:12px; font-weight:700; color:var(--ink); margin-bottom:4px;">🎯 Task Triage</div>
        <div style="font-size:12px; color:var(--muted); line-height:1.4;">
          Tag your high-stakes tasks with the <b>Urgent</b> priority badge so they stand out clearly in your Kanban and Gantt views.
        </div>
      </div>
    </div>
  </div>
</div>
