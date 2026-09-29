<?php
require_once __DIR__ . '/config/db.php';
startSecureSession();

$db = getDbConnection();
$token = trim($_GET['share'] ?? '');
$targetUser = null;

if (!empty($token)) {
    $targetUser = getUserByScheduleToken($db, $token);
} elseif (isset($_SESSION['user_id'])) {
    $myId = (int)$_SESSION['user_id'];
    $myToken = getUserScheduleToken($db, $myId);
    header("Location: schedule.php?share=" . urlencode($myToken));
    exit;
}

$initialTheme = $_COOKIE['mindrift_theme'] ?? '';
$isDark = ($initialTheme === 'dark');

if (!$targetUser) {
    http_response_code(404);
?>
<!DOCTYPE html>
<html lang="en" <?= $isDark ? 'data-theme="dark"' : ''; ?>>
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Schedule Not Found — Mindrift</title>
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="assets/css/style.css?v=<?= assetVersion(); ?>" />
</head>
<body style="min-height:100vh; display:flex; align-items:center; justify-content:center; padding:20px; background:var(--bg-base);">
  <div class="card" style="max-width:440px; width:100%; text-align:center; padding:36px 24px;">
    <div style="width:48px; height:48px; border-radius:50%; background:var(--bg-subtle); display:inline-flex; align-items:center; justify-content:center; margin-bottom:16px;">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--text-muted);"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="10" y1="14" x2="14" y2="18"/><line x1="14" y1="14" x2="10" y2="18"/></svg>
    </div>
    <h2 style="font-size:18px; font-weight:700; color:var(--text-primary); margin:0 0 8px;">Schedule Link Invalid or Expired</h2>
    <p style="font-size:13.5px; color:var(--text-secondary); line-height:1.5; margin:0 0 20px;">
      This shared schedule link may have been revoked or reset by the owner. Please ask them for an updated link.
    </p>
    <a href="login.php" class="btn-save" style="display:inline-block; text-decoration:none; padding:8px 18px; font-size:13px;">Go to Mindrift</a>
  </div>
</body>
</html>
<?php
    exit;
}

$userId = (int)$targetUser['id'];
$ownerName = htmlspecialchars($targetUser['name'] ?? 'User');

// Fetch user's active tasks
$stmt = $db->prepare("SELECT * FROM tasks WHERE user_id = :uid AND (deleted_at IS NULL) ORDER BY id ASC");
$stmt->execute(['uid' => $userId]);
$allTasks = $stmt->fetchAll();

// Month/Year navigation
$reqMonth = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
$reqYear = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

if ($reqMonth < 1) { $reqMonth = 12; $reqYear--; }
if ($reqMonth > 12) { $reqMonth = 1; $reqYear++; }

$firstDayTimestamp = mktime(0, 0, 0, $reqMonth, 1, $reqYear);
$daysInMonth = (int)date('t', $firstDayTimestamp);
$firstDayOfMonth = (int)date('N', $firstDayTimestamp); // 1 (Mon) - 7 (Sun)
$monthLabel = date('F Y', $firstDayTimestamp);

$prevMonth = $reqMonth - 1; $prevYear = $reqYear;
if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }

$nextMonth = $reqMonth + 1; $nextYear = $reqYear;
if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }

// Map tasks by day
$tasksByDay = [];
$upcomingDeadlines = [];
$urgentCount = 0;
$nowTs = time();

foreach ($allTasks as $t) {
    $prio = strtolower($t['priority'] ?? 'medium');
    if ($prio === 'urgent' || $prio === 'high') $urgentCount++;

    if (!empty($t['due_date'])) {
        $cleanDate = str_replace('/', '-', $t['due_date']);
        $ts = strtotime($cleanDate);
        if ($ts !== false) {
            $tMonth = (int)date('n', $ts);
            $tYear = (int)date('Y', $ts);
            $tDay = (int)date('j', $ts);

            if ($tMonth === $reqMonth && $tYear === $reqYear) {
                $tasksByDay[$tDay][] = $t;
            }

            if ($ts >= ($nowTs - 86400)) {
                $upcomingDeadlines[] = [
                    'task' => $t,
                    'timestamp' => $ts
                ];
            }
        }
    }
}

usort($upcomingDeadlines, fn($a, $b) => $a['timestamp'] <=> $b['timestamp']);

// Build Subscription URLs
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
$scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
$feedUrl = "{$protocol}{$host}{$scriptDir}/api/calendar_export.php?token=" . urlencode($token);
$webcalUrl = "webcal://{$host}{$scriptDir}/api/calendar_export.php?token=" . urlencode($token);

$pageTitle = "{$ownerName}'s Schedule — Mindrift";
?>
<!DOCTYPE html>
<html lang="en" <?= $isDark ? 'data-theme="dark"' : ''; ?>>
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title><?= $pageTitle; ?></title>
<!-- Instant Theme Bootstrap -->
<script>
  (function() {
    try {
      var saved = localStorage.getItem('mindrift_theme');
      var cookieMatch = document.cookie.match(/(?:^|;\s*)mindrift_theme=([^;]+)/);
      var theme = saved || (cookieMatch ? cookieMatch[1] : null);
      if (!theme && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
        theme = 'dark';
      }
      if (theme === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
      else if (theme === 'light') document.documentElement.removeAttribute('data-theme');
    } catch(e) {}
  })();
</script>
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="assets/css/style.css?v=<?= assetVersion(); ?>" />
<style>
  .public-schedule-wrap {
    max-width: 1180px;
    margin: 0 auto;
    padding: 24px 20px 48px;
  }
  .pub-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 16px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--border-base);
    margin-bottom: 24px;
  }
  .pub-brand {
    display: flex;
    align-items: center;
    gap: 10px;
    text-decoration: none;
    color: var(--text-primary);
  }
  .pub-brand-logo {
    width: 28px;
    height: 28px;
    border-radius: var(--radius-sm);
    background: var(--brand-primary);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
  }
  .stat-grid-summary {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 14px;
    margin-bottom: 24px;
  }
  .stat-box {
    background: var(--bg-surface);
    border: 1px solid var(--border-base);
    border-radius: var(--radius-md);
    padding: 16px 18px;
    box-shadow: var(--shadow-sm);
  }
  .stat-val {
    font-size: 24px;
    font-weight: 700;
    color: var(--text-primary);
    margin-top: 4px;
  }
  .stat-lbl {
    font-size: 12px;
    font-weight: 600;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.03em;
  }
  .schedule-layout-grid {
    display: grid;
    grid-template-columns: 7fr 5fr;
    gap: 20px;
  }
  .cal-day-head {
    text-align: center;
    font-size: 11px;
    font-weight: 600;
    color: var(--text-muted);
    text-transform: uppercase;
    padding: 6px 0;
  }
  .cal-day-box {
    background: var(--bg-surface);
    border: 1px solid var(--border-base);
    border-radius: var(--radius-sm);
    min-height: 76px;
    padding: 6px;
    font-size: 12px;
    font-weight: 600;
    display: flex;
    flex-direction: column;
    transition: border-color 0.15s ease;
  }
  .cal-day-box.has-tasks {
    cursor: pointer;
  }
  .cal-day-box.has-tasks:hover {
    border-color: var(--brand-primary);
  }
  .cal-day-box.today {
    background: var(--bg-subtle);
    border-color: var(--brand-primary);
    color: var(--brand-primary);
  }
  .event-dot {
    font-size: 10px;
    font-weight: 600;
    padding: 2px 5px;
    border-radius: var(--radius-xs);
    color: #fff;
    margin-top: 3px;
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
  .agenda-card {
    background: var(--bg-surface);
    border: 1px solid var(--border-base);
    border-radius: var(--radius-md);
    padding: 14px 16px;
    margin-bottom: 10px;
    box-shadow: var(--shadow-sm);
  }
  .agenda-card:last-child {
    margin-bottom: 0;
  }

  @media (max-width: 960px) {
    .schedule-layout-grid { grid-template-columns: 1fr; }
    .stat-grid-summary { grid-template-columns: 1fr; }
  }
</style>
</head>
<body style="background:var(--bg-base); color:var(--text-primary); min-height:100vh;">

<div class="public-schedule-wrap">
  <!-- Top Navigation Header -->
  <header class="pub-header">
    <div style="display:flex; align-items:center; gap:14px; flex-wrap:wrap;">
      <a href="index.php" class="pub-brand">
        <div class="pub-brand-logo">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M12 3 L13.6 9.2 L20 12 L13.6 14.8 L12 21 L10.4 14.8 L4 12 L10.4 9.2 Z" fill="#fff"/></svg>
        </div>
        <span style="font-size:15px; font-weight:700; letter-spacing:-0.01em;">Mindrift</span>
      </a>
      <span style="color:var(--border-base);">/</span>
      <div>
        <h1 style="font-size:16px; font-weight:700; margin:0;"><?= $ownerName; ?>'s Schedule</h1>
        <span style="font-size:11.5px; color:var(--text-secondary);">Public Read-Only View</span>
      </div>
      <span style="font-size:11px; font-weight:600; background:rgba(16, 185, 129, 0.12); color:#10B981; border:1px solid rgba(16, 185, 129, 0.25); padding:2px 8px; border-radius:100px;">
        Read-Only
      </span>
    </div>

    <div style="display:flex; align-items:center; gap:10px;">
      <button type="button" class="btn btn-secondary" onclick="openSubscribeModal()" style="font-size:12.5px; display:inline-flex; align-items:center; gap:6px;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        Subscribe to Calendar
      </button>

      <button type="button" class="icon-btn" onclick="toggleTheme()" aria-label="Toggle theme" title="Toggle theme">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
      </button>
    </div>
  </header>

  <!-- Metric Statistics -->
  <div class="stat-grid-summary">
    <div class="stat-box">
      <div class="stat-lbl">Active Schedule Tasks</div>
      <div class="stat-val"><?= count($allTasks); ?></div>
    </div>
    <div class="stat-box">
      <div class="stat-lbl">Upcoming Deadlines</div>
      <div class="stat-val" style="color:var(--brand-primary);"><?= count($upcomingDeadlines); ?></div>
    </div>
    <div class="stat-box">
      <div class="stat-lbl">High & Urgent Priority</div>
      <div class="stat-val" style="color:<?= $urgentCount > 0 ? '#DC2626' : 'var(--text-primary)'; ?>;"><?= $urgentCount; ?></div>
    </div>
  </div>

  <!-- Main Grid: Calendar & Agenda -->
  <div class="schedule-layout-grid">
    <!-- Monthly Calendar -->
    <div class="card card-block">
      <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:14px;">
        <div style="display:flex; align-items:center; gap:10px;">
          <a href="?share=<?= urlencode($token); ?>&month=<?= $prevMonth; ?>&year=<?= $prevYear; ?>" class="btn-step" style="padding:4px 10px; text-decoration:none; font-weight:800;" title="Previous Month">‹</a>
          <h2 style="margin:0; font-size:16.5px; font-weight:700;"><?= $monthLabel; ?></h2>
          <a href="?share=<?= urlencode($token); ?>&month=<?= $nextMonth; ?>&year=<?= $nextYear; ?>" class="btn-step" style="padding:4px 10px; text-decoration:none; font-weight:800;" title="Next Month">›</a>
        </div>
        <span style="font-size:12px; color:var(--text-muted); font-weight:500;">Click a day to view tasks</span>
      </div>

      <div class="calendar-days-grid" style="display:grid; grid-template-columns:repeat(7, 1fr); gap:6px;">
        <div class="cal-day-head">Mon</div>
        <div class="cal-day-head">Tue</div>
        <div class="cal-day-head">Wed</div>
        <div class="cal-day-head">Thu</div>
        <div class="cal-day-head">Fri</div>
        <div class="cal-day-head">Sat</div>
        <div class="cal-day-head">Sun</div>

        <?php for ($pad = 1; $pad < $firstDayOfMonth; $pad++): ?>
          <div class="cal-day-box" style="background:transparent; border-color:transparent;"></div>
        <?php endfor; ?>

        <?php 
        $todayNum = (int)date('j');
        for ($d = 1; $d <= $daysInMonth; $d++): 
          $isToday = ($d === $todayNum && $reqMonth === (int)date('n') && $reqYear === (int)date('Y'));
          $hasTasks = !empty($tasksByDay[$d]);
        ?>
          <div class="cal-day-box <?= $isToday ? 'today' : ''; ?> <?= $hasTasks ? 'has-tasks' : ''; ?>" 
               <?= $hasTasks ? 'onclick="showDayTasks(' . $d . ')"' : ''; ?>>
            <div style="display:flex; align-items:center; justify-content:space-between;">
              <span><?= $d; ?></span>
              <?php if ($isToday): ?>
                <span style="font-size:9px; background:var(--brand-primary); color:#fff; padding:1px 4px; border-radius:var(--radius-xs);">TODAY</span>
              <?php endif; ?>
            </div>

            <?php if ($hasTasks): ?>
              <?php foreach (array_slice($tasksByDay[$d], 0, 2) as $t): 
                $prio = strtolower($t['priority'] ?? 'medium');
                $color = ($prio === 'urgent') ? '#DC2626' : (($prio === 'high') ? '#EA580C' : 'var(--brand-primary)');
              ?>
                <span class="event-dot" style="background:<?= $color; ?>;" title="<?= htmlspecialchars($t['task_name']); ?>">
                  <?= htmlspecialchars($t['task_name']); ?>
                </span>
              <?php endforeach; ?>
              <?php if (count($tasksByDay[$d]) > 2): ?>
                <span style="font-size:10px; color:var(--text-muted); font-weight:700; margin-top:2px;">+<?= count($tasksByDay[$d]) - 2; ?> more</span>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        <?php endfor; ?>
      </div>

      <!-- Selected Day Preview Box -->
      <div id="selectedDayBox" style="display:none; margin-top:16px; padding:12px 14px; background:var(--bg-subtle); border:1px solid var(--border-base); border-radius:var(--radius-sm);">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:8px;">
          <h4 id="selectedDayTitle" style="margin:0; font-size:13px; font-weight:700;">Tasks on day</h4>
          <button type="button" onclick="document.getElementById('selectedDayBox').style.display='none'" style="background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:16px;">&times;</button>
        </div>
        <div id="selectedDayList"></div>
      </div>
    </div>

    <!-- Upcoming Agenda List -->
    <div>
      <div class="card card-block">
        <h3 style="margin:0 0 16px; font-size:16px; font-weight:700;">Upcoming Deadlines & Agenda</h3>
        <?php if (empty($upcomingDeadlines)): ?>
          <div style="text-align:center; padding:32px 16px; color:var(--text-muted); font-size:13px;">
            No upcoming deadlines scheduled.
          </div>
        <?php else: ?>
          <?php foreach (array_slice($upcomingDeadlines, 0, 10) as $ud): 
            $t = $ud['task'];
            $prio = strtolower($t['priority'] ?? 'medium');
            $prioColor = ($prio === 'urgent') ? '#DC2626' : (($prio === 'high') ? '#EA580C' : '#0284C7');
            $dateLabel = date('D, M j, Y', $ud['timestamp']);
          ?>
            <div class="agenda-card">
              <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:6px;">
                <span style="font-size:11px; font-weight:700; color:<?= $prioColor; ?>; text-transform:uppercase;">
                  <?= $dateLabel; ?>
                </span>
                <span style="font-size:11px; font-weight:600; padding:1px 6px; border-radius:4px; background:var(--bg-subtle); color:var(--text-secondary);">
                  <?= htmlspecialchars($t['priority'] ?? 'Medium'); ?>
                </span>
              </div>
              <div style="font-size:14px; font-weight:700; color:var(--text-primary); margin-bottom:4px;">
                <?= htmlspecialchars($t['task_name']); ?>
              </div>
              <div style="font-size:12px; color:var(--text-secondary); display:flex; gap:12px;">
                <span>Project: <b><?= htmlspecialchars($t['project_name']); ?></b></span>
                <span>Status: <b><?= htmlspecialchars($t['status'] ?? 'Open'); ?></b></span>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <footer style="margin-top:40px; text-align:center; padding-top:20px; border-top:1px solid var(--border-base); font-size:12px; color:var(--text-muted);">
    Powered by Mindrift &bull; Read-only public schedule view
  </footer>
</div>

<!-- Modal: Calendar Subscription -->
<div class="modal-overlay" id="subscribeModal">
  <div class="modal-card" style="max-width:520px;">
    <div class="modal-header">
      <h3 class="modal-title">Subscribe to <?= $ownerName; ?>'s Calendar</h3>
      <button class="modal-close-btn" aria-label="Close modal" onclick="closeSubscribeModal()">&times;</button>
    </div>
    <div class="modal-body" style="padding-top:14px;">
      <p style="font-size:13px; color:var(--text-secondary); margin:0 0 16px;">
        Subscribe to keep this schedule synced inside your favorite calendar software:
      </p>

      <div style="margin-bottom:14px;">
        <label style="display:block; font-size:12px; font-weight:700; color:var(--text-primary); margin-bottom:6px;">
          iCalendar / WebCal Feed URL
        </label>
        <div style="display:flex; gap:8px;">
          <input type="text" id="publicFeedUrl" readonly value="<?= htmlspecialchars($webcalUrl); ?>" class="form-input" style="font-size:12.5px; background:var(--bg-surface);" />
          <button type="button" class="btn-save" id="btnCopyPublicFeed" onclick="copyFeedUrl()" style="white-space:nowrap; padding:6px 14px; font-size:12px;">Copy URL</button>
        </div>
      </div>

      <div style="display:flex; flex-direction:column; gap:8px; margin-top:16px; font-size:12px; color:var(--text-secondary);">
        <div>&bull; <b>Google Calendar</b>: Click <i>Other calendars (+)</i> &rarr; <i>From URL</i> &rarr; paste the URL.</div>
        <div>&bull; <b>Apple Calendar</b>: Click <i>File</i> &rarr; <i>New Calendar Subscription</i> &rarr; paste the URL.</div>
        <div>&bull; <b>Outlook</b>: Click <i>Add Calendar</i> &rarr; <i>Subscribe from web</i> &rarr; paste the URL.</div>
      </div>
    </div>
    <div class="modal-footer" style="display:flex; justify-content:flex-end;">
      <a href="<?= htmlspecialchars($feedUrl); ?>" download="mindrift-tasks.ics" class="btn btn-secondary" style="font-size:12px; text-decoration:none; margin-right:8px;">Download .ics File</a>
      <button type="button" class="btn-save" onclick="closeSubscribeModal()">Done</button>
    </div>
  </div>
</div>

<script>
const dayTasksData = <?= json_encode($tasksByDay); ?>;
const monthName = "<?= date('F', $firstDayTimestamp); ?>";
const currentYear = "<?= $reqYear; ?>";

function showDayTasks(day) {
  const box = document.getElementById('selectedDayBox');
  const title = document.getElementById('selectedDayTitle');
  const list = document.getElementById('selectedDayList');
  if (!box || !title || !list) return;

  const tasks = dayTasksData[day] || [];
  title.textContent = `Tasks on ${monthName} ${day}, ${currentYear} (${tasks.length})`;

  if (tasks.length === 0) {
    list.innerHTML = '<span style="font-size:12px; color:var(--text-muted);">No tasks scheduled.</span>';
  } else {
    list.innerHTML = tasks.map(t => {
      const prio = (t.priority || 'Medium').toLowerCase();
      const color = prio === 'urgent' ? '#DC2626' : (prio === 'high' ? '#EA580C' : '#0284C7');
      return `
        <div style="padding:6px 0; border-bottom:1px solid var(--border-base); font-size:12.5px;">
          <div style="font-weight:700; color:var(--text-primary);">${escapeHtml(t.task_name)}</div>
          <div style="font-size:11.5px; color:var(--text-secondary); margin-top:2px;">
            <span style="color:${color}; font-weight:700;">${escapeHtml(t.priority || 'Medium')}</span> &bull;
            Project: ${escapeHtml(t.project_name || 'General')} &bull;
            Status: ${escapeHtml(t.status || 'Open')}
          </div>
        </div>
      `;
    }).join('');
  }
  box.style.display = 'block';
}

function escapeHtml(str) {
  return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function openSubscribeModal() {
  const modal = document.getElementById('subscribeModal');
  if (modal) modal.classList.add('active');
}
function closeSubscribeModal() {
  const modal = document.getElementById('subscribeModal');
  if (modal) modal.classList.remove('active');
}

function copyFeedUrl() {
  const input = document.getElementById('publicFeedUrl');
  const btn = document.getElementById('btnCopyPublicFeed');
  if (!input) return;
  input.select();
  navigator.clipboard.writeText(input.value).then(() => {
    if (btn) {
      const orig = btn.textContent;
      btn.textContent = 'Copied!';
      setTimeout(() => btn.textContent = orig, 1800);
    }
  });
}

function toggleTheme() {
  const current = document.documentElement.getAttribute('data-theme');
  const target = current === 'dark' ? 'light' : 'dark';
  if (target === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
  else document.documentElement.removeAttribute('data-theme');
  try {
    localStorage.setItem('mindrift_theme', target);
    document.cookie = "mindrift_theme=" + target + "; path=/; max-age=31536000; SameSite=Lax";
  } catch(e) {}
}
</script>
</body>
</html>
