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
$pageTitle = 'Mindrift — Help Center & Support';
include __DIR__ . '/includes/head.php';
?>
<style>
  .help-header { margin-bottom: 20px; }
  .help-title { margin: 0; font-size: 18px; font-weight: 700; color: var(--ink); }
  .faq-item { padding: 16px 20px; margin-bottom: 10px; cursor: pointer; transition: border-color 0.15s ease; }
  .faq-item:hover { border-color: var(--blue); }
  .faq-q { font-size: 14px; font-weight: 600; color: var(--ink); margin: 0; display: flex; align-items: center; justify-content: space-between; }
  .faq-chevron { transition: transform 0.2s ease; font-size: 11px; color: var(--muted); }
  .faq-item.open .faq-chevron { transform: rotate(180deg); }
  .faq-a { font-size: 13px; color: var(--muted); line-height: 1.5; margin-top: 8px; display: none; }
  .faq-item.open .faq-a { display: block; }
</style>
</head>
<body>

<div class="app" id="app">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>

  <main class="main">
    <?php include __DIR__ . '/includes/header.php'; ?>

    <div class="help-header">
      <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;">
        <div>
          <h2 class="help-title">Help &amp; Guides</h2>
          <p style="margin:4px 0 0; color:var(--muted); font-size:13px; font-weight:400;">Answers to common questions and product guides</p>
        </div>
        <button type="button" class="btn btn-secondary" onclick="document.getElementById('supportModalOverlay').classList.add('active')" style="display:inline-flex; align-items:center; gap:6px;">
          Contact Support
        </button>
      </div>
    </div>

    <!-- Shortcuts -->
    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:12px; margin-bottom:20px;">
      <div class="card" style="padding:14px; cursor:pointer;" onclick="if(typeof openCmdPalette==='function')openCmdPalette();">
        <div style="font-weight:600; font-size:13.5px; color:var(--ink);">Command Palette</div>
        <div style="font-size:11.5px; color:var(--muted); margin-top:2px;">Press ⌘K or click here</div>
      </div>
      <div class="card" style="padding:14px; cursor:pointer;" onclick="window.location.href='index.php';">
        <div style="font-weight:600; font-size:13.5px; color:var(--ink);">Focus Timer</div>
        <div style="font-size:11.5px; color:var(--muted); margin-top:2px;">Start a 25m focus session</div>
      </div>
      <div class="card" style="padding:14px; cursor:pointer;" onclick="window.location.href='api/calendar_export.php';">
        <div style="font-weight:600; font-size:13.5px; color:var(--ink);">Calendar Feed</div>
        <div style="font-size:11.5px; color:var(--muted); margin-top:2px;">Download .ics feed</div>
      </div>
    </div>

    <div style="max-width:760px;">
      <div class="card faq-item open" onclick="toggleFaq(this)">
        <h3 class="faq-q">
          <span>How is my learning streak calculated?</span>
          <span class="faq-chevron">▼</span>
        </h3>
        <p class="faq-a">Your learning streak increases each day you complete at least 1 lesson module or log a study session. Click <b>"+ Log Activity"</b> at the top of any page to record your study progress.</p>
      </div>

      <div class="card faq-item" onclick="toggleFaq(this)">
        <h3 class="faq-q">
          <span>How do course progress calculations work?</span>
          <span class="faq-chevron">▼</span>
        </h3>
        <p class="faq-a">Each course tracks completed modules versus total modules. Clicking the <b>"+ Module"</b> button on any course card advances your completed count and updates your records automatically.</p>
      </div>

      <div class="card faq-item" onclick="toggleFaq(this)">
        <h3 class="faq-q">
          <span>Can I update my profile details and password?</span>
          <span class="faq-chevron">▼</span>
        </h3>
        <p class="faq-a">Yes. Navigate to <a href="settings.php" style="color:var(--blue); font-weight:600;">Settings</a> to update your name, email, or password.</p>
      </div>

      <div class="card faq-item" onclick="toggleFaq(this)">
        <h3 class="faq-q">
          <span>How does Gantt dependency auto-scheduling work?</span>
          <span class="faq-chevron">▼</span>
        </h3>
        <p class="faq-a">On the <b>Gantt Chart</b> page, tasks can depend on predecessor tasks. Dragging a parent task automatically shifts dependent tasks forward in time so deadlines remain synchronized.</p>
      </div>
    </div>

  </main>
</div>

<!-- Modal: Contact Support -->
<div class="modal-overlay" id="supportModalOverlay">
  <div class="modal-card">
    <div class="modal-header">
      <h3 class="modal-title">Contact Support</h3>
      <button class="modal-close-btn" onclick="document.getElementById('supportModalOverlay').classList.remove('active')">&times;</button>
    </div>
    <form onsubmit="handleSupportSubmit(event)">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label" for="supportSubject">Subject</label>
          <input type="text" id="supportSubject" class="form-input" placeholder="e.g. Question about Gantt export" required />
        </div>
        <div class="form-group">
          <label class="form-label" for="supportMessage">Message</label>
          <textarea id="supportMessage" class="form-input" rows="4" placeholder="Describe your issue or feature idea..." required></textarea>
        </div>
      </div>
      <div class="modal-footer" style="display:flex; justify-content:flex-end; gap:8px;">
        <button type="button" class="btn-cancel" onclick="document.getElementById('supportModalOverlay').classList.remove('active')">Cancel</button>
        <button type="submit" class="btn-save">Send Message</button>
      </div>
    </form>
  </div>
</div>

<div class="tooltip" id="tooltip"></div>

<!-- Log Activity Modal -->
<div class="modal-overlay" id="logModalOverlay">
  <div class="modal-card">
    <div class="modal-header">
      <h3 class="modal-title">Log Study Activity</h3>
      <button class="modal-close-btn" id="btnCloseLogModal">&times;</button>
    </div>
    <form id="logActivityForm">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label" for="logLessons">Lessons Completed</label>
          <input type="number" id="logLessons" class="form-input" min="1" max="50" value="1" required />
        </div>
        <div class="form-group">
          <label class="form-label" for="logMinutes">Study Time (Minutes)</label>
          <input type="number" id="logMinutes" class="form-input" min="5" max="600" step="5" value="30" required />
        </div>
        <div class="form-group">
          <label class="form-label" for="logCategory">Category</label>
          <select id="logCategory" class="form-input">
            <option value="General">General Study</option>
            <option value="Design">Design</option>
            <option value="Programming">Programming</option>
            <option value="Data Science">Data Science</option>
            <option value="Business">Business</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label" for="logDate">Date</label>
          <input type="date" id="logDate" class="form-input" value="<?= date('Y-m-d'); ?>" required />
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-cancel" id="btnCancelLogModal">Cancel</button>
        <button type="submit" class="btn-save" id="btnSubmitLogModal">Save Activity</button>
      </div>
    </form>
  </div>
</div>

<script src="assets/js/app.js"></script>
<script>
window.toggleFaq = function(el) {
  el.classList.toggle('open');
};

window.handleSupportSubmit = function(e) {
  e.preventDefault();
  const sub = document.getElementById('supportSubject').value.trim();
  const msg = document.getElementById('supportMessage').value.trim();
  if (!sub || !msg) return;

  if (typeof showToast === 'function') {
    showToast('Support request sent. We will respond by email.');
  } else {
    alert('Your message has been sent.');
  }
  document.getElementById('supportModalOverlay').classList.remove('active');
  e.target.reset();
};
</script>
</body>
</html>
