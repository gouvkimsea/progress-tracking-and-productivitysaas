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

// Fetch ALL Registered Users from SQL Database ordered by progress & streak
$stmtLeaderboard = $db->query("
    SELECT 
        u.id, 
        u.name, 
        u.email, 
        COALESCE(s.learning_streak, 0) AS learning_streak, 
        COALESCE(s.longest_streak, 0) AS longest_streak, 
        COALESCE(s.course_progress_pct, 0) AS course_progress_pct, 
        COALESCE(s.study_hours, 0) AS study_hours, 
        COALESCE(s.study_minutes, 0) AS study_minutes, 
        COALESCE(s.weekly_lessons_current, 0) AS weekly_lessons_current
    FROM users u
    LEFT JOIN user_stats s ON u.id = s.user_id
    ORDER BY s.learning_streak DESC, s.course_progress_pct DESC, u.id ASC
");
$leaderboard = $stmtLeaderboard->fetchAll();
$topUser = $leaderboard[0] ?? null;

$pageTitle = 'Mindrift — Community & Leaderboards';
include __DIR__ . '/includes/head.php';
?>
<style>
  .comm-header { margin-bottom: 20px; }
  .comm-title { margin: 0; font-size: 18px; font-weight: 700; color: var(--ink); }
  
  /* Top Spot Spotlight */
  .top-spotlight-card {
    background: var(--panel-bg);
    border: 1px solid var(--border); padding: 18px 20px; border-radius: 8px;
    display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;
  }
  .top-spotlight-left { display: flex; align-items: center; gap: 14px; }
  .spotlight-rank-badge {
    width: 40px; height: 40px; border-radius: 6px; background: var(--blue);
    color: #fff; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 700;
    flex-shrink: 0;
  }
  .spotlight-rank { font-size: 11px; font-weight: 600; text-transform: uppercase; color: var(--blue); letter-spacing: 0.04em; }
  .spotlight-name { font-size: 16px; font-weight: 700; color: var(--ink); margin: 2px 0 4px; }
  .spotlight-meta { font-size: 12.5px; color: var(--muted); font-weight: 500; }

  .comm-grid { display: grid; grid-template-columns: 7fr 4fr; gap: 16px; }
  .leader-item {
    display: flex; align-items: center; justify-content: space-between; padding: 10px 12px;
    border-bottom: 1px solid var(--border); border-radius: 6px; transition: background 0.15s ease;
  }
  .leader-item:hover { background: var(--panel-bg); }
  .leader-item.is-me { background: rgba(37, 99, 235, 0.05); }
  
  .rank-badge {
    width: 24px; height: 24px; border-radius: 4px; display: flex; align-items: center; justify-content: center;
    font-size: 11.5px; font-weight: 600; flex-shrink: 0;
  }
  .rank-1 { background: var(--blue); color: #fff; }
  .rank-2 { background: var(--border); color: var(--ink); }
  .rank-3 { background: var(--border); color: var(--muted); }
  .rank-other { background: transparent; color: var(--muted); }

  .user-stats-pill { display: flex; align-items: center; gap: 14px; }
  .stat-badge { font-size: 12px; font-weight: 500; color: var(--muted); }
  .stat-badge b { color: var(--ink); font-weight: 600; }
  .btn-join { background: var(--panel-bg); color: var(--ink); border: 1px solid var(--border); padding: 5px 12px; border-radius: 4px; font-size: 12px; font-weight: 500; cursor: pointer; }
  .btn-join:hover { border-color: var(--blue); color: var(--blue); }
</style>
</head>
<body>

<div class="app" id="app">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>

  <main class="main">
    <?php include __DIR__ . '/includes/header.php'; ?>

    <div class="comm-header">
      <h2 class="comm-title">Community Rankings</h2>
      <p style="margin:4px 0 0; color:var(--muted); font-size:13px; font-weight:400;">Member rankings by study streak and course progress</p>
    </div>

    <!-- Top Spotlight Banner -->
    <?php if ($topUser): ?>
      <div class="top-spotlight-card">
        <div class="top-spotlight-left">
          <div class="spotlight-rank-badge">#1</div>
          <div>
            <span class="spotlight-rank">Top Learner</span>
            <h3 class="spotlight-name"><?= htmlspecialchars($topUser['name']); ?> <?= ($topUser['id'] == $userId) ? '(You)' : ''; ?></h3>
            <span class="spotlight-meta"><b><?= (int)$topUser['learning_streak']; ?> days streak</b> · <b><?= round($topUser['course_progress_pct']); ?>% progress</b> · <b><?= (int)$topUser['study_hours']; ?>h <?= (int)$topUser['study_minutes']; ?>m study time</b></span>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <div class="comm-grid">
      <!-- Full Leaderboard -->
      <div class="card card-block">
        <h3 style="margin:0 0 14px; font-size:15px; font-weight:600;">All Active Members</h3>
        
        <?php 
        $rank = 1;
        foreach ($leaderboard as $member): 
          $isMe = ($member['id'] == $userId);
          $rankClass = ($rank == 1) ? 'rank-1' : (($rank == 2) ? 'rank-2' : (($rank == 3) ? 'rank-3' : 'rank-other'));
          $initial = strtoupper(substr($member['name'], 0, 1));
        ?>
          <div class="leader-item <?= $isMe ? 'is-me' : ''; ?>">
            <div style="display:flex; align-items:center; gap:12px;">
              <span class="rank-badge <?= $rankClass; ?>"><?= $rank; ?></span>
              <div class="avatar-circle" style="width:28px; height:28px; font-size:11px;"><?= $initial; ?></div>
              <div>
                <div style="font-size:13.5px; font-weight:600; color:var(--ink);">
                  <?= htmlspecialchars($member['name']); ?> <?= $isMe ? '<span style="color:var(--blue); font-weight:600;">(You)</span>' : ''; ?>
                </div>
                <div style="font-size:11px; color:var(--muted);"><?= htmlspecialchars($member['email']); ?></div>
              </div>
            </div>

            <div class="user-stats-pill">
              <span class="stat-badge"><b><?= (int)$member['learning_streak']; ?></b> days</span>
              <span class="stat-badge"><b><?= round($member['course_progress_pct']); ?>%</b></span>
              <span class="stat-badge"><b><?= (int)$member['study_hours']; ?>h</b></span>
            </div>
          </div>
        <?php 
        $rank++;
        endforeach; 
        ?>
      </div>

      <!-- Study Groups -->
      <div class="card card-block">
        <h3 style="margin:0 0 16px; font-size:16.5px; font-weight:700;">Study Groups</h3>

        <div class="leader-item" style="padding:10px 0;">
          <div>
            <div style="font-size:14px; font-weight:700;">UI/UX Design Circle</div>
            <div class="circle-count" style="font-size:12px; color:var(--muted);"><span class="cnt"><?= count($leaderboard); ?></span> members active</div>
          </div>
          <button type="button" class="btn-join" data-group="UI/UX Design Circle" onclick="toggleJoinGroup(this, 'UI/UX Design Circle')">Join</button>
        </div>

        <div class="leader-item" style="padding:10px 0;">
          <div>
            <div style="font-size:14px; font-weight:700;">Full-Stack JS Developers</div>
            <div class="circle-count" style="font-size:12px; color:var(--muted);"><span class="cnt"><?= count($leaderboard); ?></span> members active</div>
          </div>
          <button type="button" class="btn-join" data-group="Full-Stack JS Developers" onclick="toggleJoinGroup(this, 'Full-Stack JS Developers')">Join</button>
        </div>

        <div class="leader-item" style="padding:10px 0;">
          <div>
            <div style="font-size:14px; font-weight:700;">Data Science &amp; Machine Learning</div>
            <div class="circle-count" style="font-size:12px; color:var(--muted);"><span class="cnt"><?= count($leaderboard); ?></span> members active</div>
          </div>
          <button type="button" class="btn-join" data-group="Data Science & Machine Learning" onclick="toggleJoinGroup(this, 'Data Science & Machine Learning')">Join</button>
        </div>
      </div>
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
window.toggleJoinGroup = function(btn, groupName) {
  const isJoined = btn.classList.contains('joined');
  const countSpan = btn.parentElement.querySelector('.circle-count .cnt');
  let currentCnt = countSpan ? parseInt(countSpan.textContent, 10) : 1;

  if (isJoined) {
    btn.classList.remove('joined');
    btn.textContent = 'Join';
    btn.style.background = '';
    btn.style.borderColor = '';
    btn.style.color = '';
    if (countSpan) countSpan.textContent = Math.max(0, currentCnt - 1);
    localStorage.removeItem('joined_group_' + groupName);
    if (typeof showToast === 'function') showToast(`Left ${groupName}`);
  } else {
    btn.classList.add('joined');
    btn.textContent = 'Joined';
    btn.style.background = '#10B981';
    btn.style.borderColor = '#10B981';
    btn.style.color = '#FFFFFF';
    if (countSpan) countSpan.textContent = currentCnt + 1;
    localStorage.setItem('joined_group_' + groupName, '1');
    if (typeof showToast === 'function') showToast(`Joined ${groupName}`);
  }
};

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.btn-join').forEach(btn => {
    const gName = btn.getAttribute('data-group');
    if (localStorage.getItem('joined_group_' + gName) === '1') {
      btn.classList.add('joined');
      btn.textContent = 'Joined';
      btn.style.background = '#10B981';
      btn.style.borderColor = '#10B981';
      btn.style.color = '#FFFFFF';
    }
  });
});
</script>
</body>
</html>
