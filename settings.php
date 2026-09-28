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
$pageTitle = 'Mindrift — Account Settings';
include __DIR__ . '/includes/head.php';
?>
<style>
  .settings-header { margin-bottom: 24px; }
  .settings-title { margin: 0; font-size: 22px; font-weight: 800; color: var(--ink); }
  .settings-card { padding: 28px; max-width: 600px; }
</style>
</head>
<body>

<div class="app" id="app">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>

  <main class="main">
    <?php include __DIR__ . '/includes/header.php'; ?>

    <div class="settings-header">
      <h2 class="settings-title">Account Settings</h2>
      <p style="margin:4px 0 0; color:var(--muted); font-size:14px; font-weight:500;">Manage your profile credentials and password</p>
    </div>

    <div class="card settings-card">
      <h3 style="margin:0 0 20px; font-size:17px; font-weight:800;">Profile Information</h3>

      <div id="settingsAlert" class="auth-alert" style="display:none;"></div>

      <form id="settingsForm">
        <div class="form-group">
          <label class="form-label" for="settingName">Full Name</label>
          <input type="text" id="settingName" class="form-input" value="<?= htmlspecialchars($user['name']); ?>" required />
        </div>

        <div class="form-group">
          <label class="form-label" for="settingEmail">Email Address</label>
          <input type="email" id="settingEmail" class="form-input" value="<?= htmlspecialchars($user['email']); ?>" required />
        </div>

        <div class="form-group">
          <label class="form-label" for="settingPassword">New Password (Leave blank to keep current)</label>
          <input type="password" id="settingPassword" class="form-input" placeholder="••••••••" />
        </div>

        <div class="form-group" style="margin-top:20px;">
          <label class="form-label">Theme Preference</label>
          <div style="display:flex; gap:10px; margin-top:6px;">
            <button type="button" class="btn btn-secondary" id="btnSetLight" onclick="setAppTheme('light')" style="flex:1; padding:6px 12px; font-size:12.5px;">Light</button>
            <button type="button" class="btn btn-secondary" id="btnSetDark" onclick="setAppTheme('dark')" style="flex:1; padding:6px 12px; font-size:12.5px;">Dark</button>
          </div>
        </div>

        <div style="margin-top:24px; display:flex; justify-content:flex-end;">
          <button type="submit" class="btn-save" id="btnSaveSettings">Save Changes</button>
        </div>
      </form>
    </div>

  </main>
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
window.setAppTheme = function(theme) {
  if (typeof applyTheme === 'function') {
    applyTheme(theme);
  } else {
    if (theme === 'dark') {
      document.documentElement.setAttribute('data-theme', 'dark');
    } else {
      document.documentElement.removeAttribute('data-theme');
    }
    try {
      localStorage.setItem('mindrift_theme', theme);
      document.cookie = "mindrift_theme=" + theme + "; path=/; max-age=31536000; SameSite=Lax";
    } catch(e) {}
  }
  if (typeof showToast === 'function') showToast(`Theme preference updated to ${theme} mode`);
};

document.getElementById('settingsForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const alert = document.getElementById('settingsAlert');
  const btn = document.getElementById('btnSaveSettings');
  alert.style.display = 'none';

  const name = document.getElementById('settingName').value.trim();
  const email = document.getElementById('settingEmail').value.trim();
  const password = document.getElementById('settingPassword').value.trim();

  btn.disabled = true;
  btn.textContent = 'Saving...';

  try {
    const res = await secureFetch('api/update_profile.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ name, email, password })
    });
    const data = await res.json();
    if (data.success) {
      alert.className = 'auth-alert success';
      alert.textContent = data.message;
      alert.style.display = 'block';
      if (typeof showToast === 'function') showToast('Profile settings saved successfully');
      setTimeout(() => location.reload(), 1200);
    } else {
      alert.className = 'auth-alert error';
      alert.textContent = data.message || data.error;
      alert.style.display = 'block';
    }
  } catch (err) {
    alert.className = 'auth-alert error';
    alert.textContent = 'Network error saving settings.';
    alert.style.display = 'block';
  } finally {
    btn.disabled = false;
    btn.textContent = 'Save Changes';
  }
});
</script>
</body>
</html>
