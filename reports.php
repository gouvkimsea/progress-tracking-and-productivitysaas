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
?>
<?php
$pageTitle = 'Mindrift — Reports';
include __DIR__ . '/includes/head.php';
?>
<style>
  /* Reports Page (GanttPRO Screenshot 4 Design) */
  .reports-header-row {
    display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;
  }
  .reports-title-wrap { display: flex; align-items: center; gap: 10px; }
  .reports-title-ic { color: #10B981; }
  .reports-title { margin: 0; font-size: 20px; font-weight: 800; color: #111827; }
  .close-icon-btn { background: none; border: none; font-size: 18px; color: #6B7280; cursor: pointer; }

  /* Pill Navigation Bar */
  .reports-tab-nav {
    display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px;
  }
  .pill-group { display: flex; align-items: center; gap: 4px; background: #F3F4F6; padding: 4px; border-radius: 8px; }
  .pill-btn {
    padding: 7px 16px; font-size: 13.5px; font-weight: 600; color: #4B5563; border: none; background: none;
    border-radius: 6px; cursor: pointer; transition: all 0.15s ease;
  }
  .pill-btn.active { background: #FFFFFF; color: #111827; box-shadow: 0 1px 3px rgba(0,0,0,0.1); font-weight: 700; }
  .missing-feature-link { font-size: 13px; color: #4B5563; text-decoration: none; font-weight: 500; }

  .subhead-label { font-size: 12px; font-weight: 800; letter-spacing: 0.06em; color: #6B7280; text-transform: uppercase; margin-bottom: 14px; }

  /* Reports Grid Cards */
  .reports-cards-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
  .report-card { padding: 24px; background: #FFFFFF; border: 1px solid #E5E7EB; border-radius: 12px; }
  .report-card-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px; }
  .report-card-title { margin: 0; font-size: 16.5px; font-weight: 800; color: #111827; }
  .report-card-desc { font-size: 13px; color: #6B7280; margin: 0 0 20px; line-height: 1.45; }

  /* Milestone Timeline SVG Graphic */
  .milestone-timeline-wrap {
    background: #FAFAFA; border-radius: 10px; padding: 24px 20px 16px; border: 1px solid #F0F1F3; text-align: center;
  }
  
  /* Donut Chart Visual */
  .donut-chart-flex { display: flex; align-items: center; justify-content: space-between; gap: 20px; }
  .donut-chart-svg { width: 170px; height: 170px; transform: rotate(-90deg); }
  .legend-list { display: flex; flex-direction: column; gap: 10px; }
  .legend-item { display: flex; align-items: center; gap: 10px; font-size: 13px; font-weight: 600; color: #374151; }
  .legend-dot-sq { width: 14px; height: 14px; border-radius: 3px; flex-shrink: 0; }

  /* Dark Theme Support */
  [data-theme="dark"] .reports-title { color: var(--ink); }
  [data-theme="dark"] .pill-group { background: #1F293D; }
  [data-theme="dark"] .pill-btn { color: #9CA3AF; }
  [data-theme="dark"] .pill-btn.active { background: #111827; color: #F9FAFB; box-shadow: 0 1px 3px rgba(0,0,0,0.4); }
  [data-theme="dark"] .subhead-label { color: #9CA3AF; }
  [data-theme="dark"] .report-card { background: #111827; border-color: #1F293D; }
  [data-theme="dark"] .report-card-title { color: #F9FAFB; }
  [data-theme="dark"] .report-card-desc { color: #9CA3AF; }
  [data-theme="dark"] .milestone-timeline-wrap { background: #0D1526; border-color: #1F293D; }
  [data-theme="dark"] .legend-item { color: #E2E8F0; }
</style>
</head>
<body>

<div class="app" id="app">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>

  <main class="main">
    <?php include __DIR__ . '/includes/header.php'; ?>

    <!-- Reports Header -->
    <div class="reports-header-row">
      <div class="reports-title-wrap">
        <svg class="reports-title-ic" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="18" x2="9" y2="15"/><line x1="15" y1="18" x2="15" y2="9"/></svg>
        <h2 class="reports-title">Reports</h2>
      </div>
      <button class="close-icon-btn" title="Close" onclick="window.location.href='index.php';">&times;</button>
    </div>    <!-- Pill Tabs -->
    <div class="reports-tab-nav">
      <div class="pill-group">
        <button type="button" class="pill-btn active" data-filter="all" onclick="filterReports('all', this)">All</button>
        <button type="button" class="pill-btn" data-filter="progress" onclick="filterReports('progress', this)">Progress</button>
        <button type="button" class="pill-btn" data-filter="budget" onclick="filterReports('budget', this)">Budget</button>
        <button type="button" class="pill-btn" data-filter="time" onclick="filterReports('time', this)">Time on tasks</button>
      </div>
      <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
        <a href="portfolio.php" class="btn-save" style="display:inline-flex; align-items:center; gap:6px; text-decoration:none; font-size:12.5px; padding:6px 14px; background:var(--purple); color:#fff; border-radius:6px; font-weight:700;">
          Export Portfolio (PDF)
        </a>
        <a href="api/export.php?type=tasks" class="btn-save" style="display:inline-flex; align-items:center; gap:6px; text-decoration:none; font-size:12.5px; padding:6px 14px; background:#0E65C7; color:#fff; border-radius:6px; font-weight:700;">
          Export Tasks (CSV)
        </a>
        <a href="api/export.php?type=timelog" class="btn-save" style="display:inline-flex; align-items:center; gap:6px; text-decoration:none; font-size:12.5px; padding:6px 14px; background:#10B981; color:#fff; border-radius:6px; font-weight:700;">
          Export Time Logs (CSV)
        </a>
      </div>
    </div>

    <div class="subhead-label" id="reportsSectionLabel">ALL REPORTS</div>

    <!-- Reports Grid Cards -->
    <div class="reports-cards-grid">
      
      <!-- Card 1: Milestone timeline -->
      <div class="report-card" data-cat="progress">
        <h3 class="report-card-title">Milestone timeline</h3>
        <p class="report-card-desc">View milestone dates and timeline progress.</p>

        <div class="milestone-timeline-wrap">
          <svg viewBox="0 0 400 160" width="100%" height="160">
            <!-- Timeline Months Bar -->
            <rect x="20" y="110" width="360" height="30" fill="#E5E7EB" rx="4"/>
            <text x="50" y="130" font-size="13" font-weight="700" fill="#374151">Jan</text>
            <text x="190" y="130" font-size="13" font-weight="700" fill="#374151">Apr</text>
            <text x="330" y="130" font-size="13" font-weight="700" fill="#374151">Jul</text>

            <!-- Today Vertical Line -->
            <line x1="140" y1="50" x2="140" y2="140" stroke="#111827" stroke-width="2" stroke-dasharray="4,4"/>
            <rect x="120" y="30" width="40" height="20" fill="#111827" rx="4"/>
            <text x="140" y="44" font-size="10" font-weight="700" fill="#FFF" text-anchor="middle">Today</text>

            <!-- Milestone 1 Pin (Green) -->
            <line x1="90" y1="55" x2="90" y2="125" stroke="#84CC16" stroke-width="2"/>
            <rect x="85" y="50" width="10" height="10" fill="#84CC16" transform="rotate(45 90 55)"/>
            <circle cx="90" cy="125" r="5" fill="#84CC16"/>
            <text x="90" y="38" font-size="11" font-weight="700" fill="#111827" text-anchor="middle">Milestone 1</text>
            <text x="90" y="49" font-size="9" fill="#6B7280" text-anchor="middle">Feb 14, 2025</text>

            <!-- Milestone 2 Pin (Blue) -->
            <line x1="220" y1="55" x2="220" y2="125" stroke="#2563EB" stroke-width="2"/>
            <rect x="215" y="50" width="10" height="10" fill="#2563EB" transform="rotate(45 220 55)"/>
            <circle cx="220" cy="125" r="5" fill="#2563EB"/>
            <text x="220" y="38" font-size="11" font-weight="700" fill="#111827" text-anchor="middle">Milestone 2</text>
            <text x="220" y="49" font-size="9" fill="#6B7280" text-anchor="middle">Apr 02, 2025</text>

            <!-- Milestone 3 Pin (Dark Blue Diamond) -->
            <line x1="310" y1="75" x2="310" y2="125" stroke="#1D4ED8" stroke-width="2"/>
            <polygon points="310,70 316,77 310,84 304,77" fill="#1D4ED8"/>
            <circle cx="310" cy="125" r="5" fill="#1D4ED8"/>
            <text x="310" y="58" font-size="11" font-weight="700" fill="#111827" text-anchor="middle">Milestone 3</text>
            <text x="310" y="69" font-size="9" fill="#6B7280" text-anchor="middle">Jun 19, 2025</text>
          </svg>
        </div>
      </div>

      <!-- Card 2: Projects by status -->
      <div class="report-card" data-cat="progress">
        <div class="report-card-head">
          <h3 class="report-card-title">Projects by status</h3>
          <a href="courses.php" style="color:#0E65C7; text-decoration:none; font-size:14px; font-weight:700;">+ New</a>
        </div>
        <p class="report-card-desc">Project count categorized by status: on track, at risk, off track, or no status.</p>

        <div class="donut-chart-flex">
          <svg viewBox="0 0 100 100" class="donut-chart-svg">
            <circle cx="50" cy="50" r="32" fill="none" stroke="#9CA3AF" stroke-width="16" stroke-dasharray="105.5 100" stroke-dashoffset="0"/>
            <circle cx="50" cy="50" r="32" fill="none" stroke="#84CC16" stroke-width="16" stroke-dasharray="65 100" stroke-dashoffset="-105.5"/>
            <circle cx="50" cy="50" r="32" fill="none" stroke="#2563EB" stroke-width="16" stroke-dasharray="35 100" stroke-dashoffset="-170.5"/>
            <circle cx="50" cy="50" r="32" fill="none" stroke="#FF9500" stroke-width="16" stroke-dasharray="45 100" stroke-dashoffset="-205.5"/>
            <circle cx="50" cy="50" r="50" fill="none"/>
            <text x="65" y="32" font-size="9" font-weight="800" fill="#FFF" transform="rotate(90 65 32)">42%</text>
            <text x="35" y="32" font-size="9" font-weight="800" fill="#FFF" transform="rotate(90 35 32)">26%</text>
            <text x="25" y="65" font-size="9" font-weight="800" fill="#FFF" transform="rotate(90 25 65)">14%</text>
            <text x="60" y="72" font-size="9" font-weight="800" fill="#FFF" transform="rotate(90 60 72)">18%</text>
          </svg>

          <div class="legend-list">
            <div class="legend-item"><span class="legend-dot-sq" style="background:#9CA3AF;"></span><span>No status</span></div>
            <div class="legend-item"><span class="legend-dot-sq" style="background:#FF9500;"></span><span>On track</span></div>
            <div class="legend-item"><span class="legend-dot-sq" style="background:#2563EB;"></span><span>At risk</span></div>
            <div class="legend-item"><span class="legend-dot-sq" style="background:#84CC16;"></span><span>Off track</span></div>
          </div>
        </div>
      </div>

      <!-- Card 3: Project Budget & Expenses -->
      <div class="report-card" data-cat="budget">
        <div class="report-card-head">
          <h3 class="report-card-title">Project Budget Utilization</h3>
          <span style="font-size:12.5px; font-weight:700; color:#10B981;">On Target</span>
        </div>
        <p class="report-card-desc">Track project expenditure against the allocated budget.</p>
        <div style="margin-top:14px;">
          <div style="display:flex; justify-content:space-between; font-size:13px; font-weight:700; margin-bottom:6px;">
            <span style="color:var(--muted);">Utilized: $14,200</span>
            <span style="color:var(--ink);">Budget: $20,000 (71%)</span>
          </div>
          <div style="width:100%; height:8px; background:var(--border); border-radius:4px; overflow:hidden;">
            <div style="height:100%; width:71%; background:var(--green); border-radius:4px;"></div>
          </div>
        </div>
      </div>

      <!-- Card 4: Time on Tasks Logged -->
      <div class="report-card" data-cat="time">
        <div class="report-card-head">
          <h3 class="report-card-title">Time on Tasks Logged</h3>
          <a href="analytics.php" style="color:var(--blue); text-decoration:none; font-size:13px; font-weight:600;">View Logs →</a>
        </div>
        <p class="report-card-desc">Total hours logged on active tasks.</p>
        <div style="display:flex; align-items:center; gap:16px; margin-top:14px;">
          <div style="flex:1; background:var(--panel-bg); border:1px solid var(--border); padding:12px; border-radius:6px; text-align:center;">
            <div style="font-size:22px; font-weight:700; color:var(--ink);">184h</div>
            <div style="font-size:11px; font-weight:500; color:var(--muted);">Total Hours Logged</div>
          </div>
          <div style="flex:1; background:var(--panel-bg); border:1px solid var(--border); padding:12px; border-radius:6px; text-align:center;">
            <div style="font-size:22px; font-weight:700; color:var(--green);">32h</div>
            <div style="font-size:11px; font-weight:500; color:var(--muted);">Logged This Week</div>
          </div>
        </div>
      </div>

    </div>

  </main>
</div>


<script src="assets/js/app.js"></script>
<script>
window.filterReports = function(cat, btn) {
  document.querySelectorAll('.reports-tab-nav .pill-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');

  const label = document.getElementById('reportsSectionLabel');
  if (label) {
    label.textContent = (cat === 'all') ? 'ALL REPORTS' : cat.toUpperCase() + ' REPORTS';
  }

  const cards = document.querySelectorAll('.reports-cards-grid .report-card');
  cards.forEach(c => {
    if (cat === 'all') {
      c.style.display = 'block';
    } else {
      const cardCat = c.getAttribute('data-cat') || '';
      c.style.display = (cardCat === cat) ? 'block' : 'none';
    }
  });

  if (typeof showToast === 'function') {
    showToast(`Showing ${btn.textContent.trim()} reports`);
  }
};
</script>
</body>
</html>
