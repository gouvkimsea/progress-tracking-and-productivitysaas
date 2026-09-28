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

// Ensure '2dapp' project exists in database for this user
$stmtCheck = $db->prepare("SELECT COUNT(*) as cnt FROM courses WHERE user_id = :uid AND name = '2dapp'");
$stmtCheck->execute(['uid' => $userId]);
if ($stmtCheck->fetch()['cnt'] == 0) {
    $db->prepare("INSERT INTO courses (user_id, code, name, category, level, total_modules, completed_modules, progress_pct, bg_gradient, ring_color) 
                  VALUES (:uid, '2D', '2dapp', 'Programming', 'Intermediate', 10, 0, 0, 'linear-gradient(145deg,#6FC1F0,#4FA3E0)', '#4FA3E0')")->execute(['uid' => $userId]);
}

// Fetch All Projects / Courses for user
$stmtCourses = $db->prepare("SELECT * FROM courses WHERE user_id = :uid ORDER BY id DESC");
$stmtCourses->execute(['uid' => $userId]);
$courses = $stmtCourses->fetchAll();

$pageTitle = 'Mindrift — All Projects';
include __DIR__ . '/includes/head.php';
?>
<style>
  /* All Projects Page Styling (GanttPRO Design) */
  .all-projects-header {
    display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;
  }
  .all-projects-title-wrap { display: flex; align-items: center; gap: 12px; }
  .back-chevron-link {
    color: #0E65C7; text-decoration: none; font-size: 18px; font-weight: 700; display: flex; align-items: center;
  }
  .all-projects-title { margin: 0; font-size: 20px; font-weight: 800; color: #111827; }
  
  .btn-outline-create {
    background: #FFFFFF; color: #0E65C7; border: 1.5px solid #0E65C7; padding: 7px 16px;
    border-radius: 6px; font-size: 14px; font-weight: 700; cursor: pointer; display: flex;
    align-items: center; gap: 6px; transition: all 0.15s ease;
  }
  .btn-outline-create:hover { background: #F0F6FE; }

  .search-box-wrap { position: relative; margin-bottom: 14px; }
  .search-box-wrap svg { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #9CA3AF; }
  .search-box-input {
    width: 100%; padding: 11px 14px 11px 40px; background: #F9FAFB; border: 1px solid #E5E7EB;
    border-radius: 6px; font-size: 14px; color: #111827; outline: none; transition: border-color 0.15s;
  }
  .search-box-input:focus { border-color: #0E65C7; background: #FFF; }

  .filter-tabs-row {
    display: flex; align-items: center; gap: 24px; border-bottom: 1px solid #E5E7EB; margin-bottom: 16px; padding-bottom: 8px;
  }
  .tab-btn {
    background: none; border: none; font-size: 14px; font-weight: 500; color: #4B5563; cursor: pointer;
    padding-bottom: 8px; position: relative; display: flex; align-items: center; gap: 4px;
  }
  .tab-btn.active { color: #111827; font-weight: 700; }
  .tab-btn.active::after {
    content: ''; position: absolute; bottom: -9px; left: 0; right: 0; height: 2px; background: #111827;
  }

  .projects-list-wrap { display: flex; flex-direction: column; gap: 1px; background: #E5E7EB; border-radius: 6px; overflow: hidden; }
  .project-item-row {
    display: flex; align-items: center; justify-content: space-between; padding: 14px 20px; background: #FFFFFF;
    transition: background 0.15s;
  }
  .project-item-row:hover { background: #FAFAFA; }
  
  .project-left-col { display: flex; align-items: center; gap: 16px; }
  .star-btn { background: none; border: none; color: #D1D5DB; font-size: 18px; cursor: pointer; transition: color 0.15s; }
  .star-btn.starred { color: #F59E0B; }
  .project-title-name { font-size: 15px; font-weight: 700; color: #111827; margin: 0 0 2px; }
  .project-sub-date { font-size: 12.5px; color: #6B7280; font-weight: 500; }

  .project-right-col { display: flex; align-items: center; gap: 14px; }
  .status-select-btn {
    background: #E5E7EB; color: #374151; border: none; padding: 6px 14px; border-radius: 6px;
    font-size: 13px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px;
  }
  .status-select-btn:hover { background: #D1D5DB; }
  .action-icon-btn { background: none; border: none; color: #9CA3AF; font-size: 16px; cursor: pointer; padding: 4px; border-radius: 4px; }
  .action-icon-btn:hover { color: #111827; background: #F3F4F6; }

  /* Dark Theme Support */
  [data-theme="dark"] .all-projects-title { color: var(--ink); }
  [data-theme="dark"] .btn-outline-create { background: #111827; border-color: #38BDF8; color: #38BDF8; }
  [data-theme="dark"] .btn-outline-create:hover { background: rgba(56, 189, 248, 0.1); }
  [data-theme="dark"] .search-box-input { background: #111827; border-color: #1F293D; color: #F9FAFB; }
  [data-theme="dark"] .search-box-input:focus { border-color: #38BDF8; background: #162032; }
  [data-theme="dark"] .filter-tabs-row { border-color: #1F293D; }
  [data-theme="dark"] .tab-btn { color: #9CA3AF; }
  [data-theme="dark"] .tab-btn.active { color: #F9FAFB; }
  [data-theme="dark"] .tab-btn.active::after { background: #38BDF8; }
  [data-theme="dark"] .projects-list-wrap { background: #1F293D; }
  [data-theme="dark"] .project-item-row { background: #111827; }
  [data-theme="dark"] .project-item-row:hover { background: #162032; }
  [data-theme="dark"] .project-title-name { color: #F9FAFB; }
  [data-theme="dark"] .project-sub-date { color: #9CA3AF; }
  [data-theme="dark"] .status-select-btn { background: #1E293B; color: #E2E8F0; }
  [data-theme="dark"] .status-select-btn:hover { background: #334155; }
  [data-theme="dark"] .action-icon-btn:hover { color: #F9FAFB; background: #1F293D; }
</style>
</head>
<body>

<div class="app" id="app">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>

  <main class="main">
    <?php include __DIR__ . '/includes/header.php'; ?>

    <div class="all-projects-header">
      <div class="all-projects-title-wrap">
        <a href="index.php" class="back-chevron-link" title="Back to Dashboard">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
        </a>
        <h2 class="all-projects-title">All projects</h2>
      </div>
      <button class="btn-outline-create" id="btnOpenCreateProjFromHeader">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Create new project
      </button>
    </div>

    <!-- Search input bar -->
    <div class="search-box-wrap">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input type="text" id="projSearchInput" class="search-box-input" placeholder="Search" />
    </div>

    <!-- Sort & Filter Tabs -->
    <div class="filter-tabs-row">
      <button class="tab-btn active" data-sort="last_change">Last change ↓</button>
      <button class="tab-btn" data-sort="name">Name</button>
      <button class="tab-btn" data-sort="overdue">Overdue</button>
      <button class="tab-btn" data-sort="progress">Progress</button>
      <button class="tab-btn" data-sort="unassigned">Unassigned</button>
    </div>

    <!-- Project List -->
    <div class="projects-list-wrap" id="projectListContainer">
      <?php foreach ($courses as $c): 
        $dateStr = date('d-m-Y');
        $statusLabel = ($c['progress_pct'] > 0) ? (($c['progress_pct'] >= 100) ? 'Completed' : 'In progress') : 'No status';
      ?>
        <div class="project-item-row" data-name="<?= strtolower(htmlspecialchars($c['name'])); ?>" data-progress="<?= (int)$c['progress_pct']; ?>" data-status="<?= $statusLabel; ?>" data-id="<?= $c['id']; ?>">
          <div class="project-left-col">
            <button class="star-btn <?= ($c['is_starred'] ?? 0) ? 'starred' : ''; ?>" title="Star Project" onclick="toggleProjFavorite(<?= $c['id']; ?>, this)"><?= ($c['is_starred'] ?? 0) ? '★' : '☆'; ?></button>
            <div>
              <h3 class="project-title-name" style="cursor:pointer;" onclick="openProjectInfoModal(<?= htmlspecialchars(json_encode($c)); ?>)"><?= htmlspecialchars($c['name']); ?></h3>
              <span class="project-sub-date">Category: <?= htmlspecialchars($c['category']); ?> · Progress: <?= (int)$c['progress_pct']; ?>%</span>
            </div>
          </div>

          <div class="project-right-col">
            <button class="status-select-btn" onclick="toggleProjStatus(<?= $c['id']; ?>, this)">
              <span class="status-txt"><?= $statusLabel; ?></span>
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m6 9 6 6 6-6"/></svg>
            </button>
            <button class="action-icon-btn" title="Project Details" onclick="openProjectInfoModal(<?= htmlspecialchars(json_encode($c)); ?>)">ⓘ</button>
            <div style="position:relative; display:inline-block;">
              <button class="action-icon-btn btn-proj-opts" title="Options" onclick="toggleProjectOptions(event, <?= $c['id']; ?>, '<?= addslashes($c['name']); ?>')">···</button>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

  </main>
</div>

<!-- Project Details Modal -->
<div class="modal-overlay" id="projectInfoModal">
  <div class="modal-card">
    <div class="modal-header">
      <h3 class="modal-title" id="infoModalTitle">Project Details</h3>
      <button class="modal-close-btn" onclick="document.getElementById('projectInfoModal').classList.remove('active')">&times;</button>
    </div>
    <div class="modal-body">
      <div style="display:flex; align-items:center; gap:12px; margin-bottom:16px;">
        <div id="infoModalBadge" style="width:48px; height:48px; border-radius:10px; display:flex; align-items:center; justify-content:center; color:#fff; font-size:18px; font-weight:800;">PR</div>
        <div>
          <h3 id="infoModalName" style="margin:0; font-size:18px; font-weight:800; color:var(--ink);">Project Name</h3>
          <span id="infoModalCategory" style="font-size:12.5px; color:var(--muted); font-weight:600;">Design · Intermediate</span>
        </div>
      </div>

      <div style="background:var(--card-bg); border:1px solid var(--border); border-radius:10px; padding:14px; margin-bottom:18px;">
        <div style="display:flex; justify-content:space-between; font-size:13px; font-weight:700; margin-bottom:6px;">
          <span style="color:var(--muted);">Modules Progress</span>
          <span id="infoModalProgressTxt" style="color:var(--ink);">0 / 10 (0%)</span>
        </div>
        <div style="width:100%; height:8px; background:rgba(0,0,0,0.06); border-radius:4px; overflow:hidden;">
          <div id="infoModalProgressBar" style="height:100%; width:0%; background:linear-gradient(90deg, #6C5CE7, #8B7CF0); border-radius:4px; transition:width 0.3s ease;"></div>
        </div>
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
        <a id="infoModalGanttBtn" href="gantt.php" class="btn-step" style="text-align:center; padding:10px; text-decoration:none; display:flex; align-items:center; justify-content:center; gap:6px; font-size:13px; font-weight:700;">
          📈 View in Gantt
        </a>
        <a id="infoModalKanbanBtn" href="kanban.php" class="btn-step" style="text-align:center; padding:10px; text-decoration:none; display:flex; align-items:center; justify-content:center; gap:6px; font-size:13px; font-weight:700;">
          📋 View in Kanban
        </a>
      </div>
    </div>
  </div>
</div>

<script src="assets/js/app.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const searchInput = document.getElementById('projSearchInput');
  const projectRows = document.querySelectorAll('.project-item-row');
  const container = document.getElementById('projectListContainer');

  // 1. Live Search Filter
  if (searchInput) {
    searchInput.addEventListener('input', (e) => {
      const q = e.target.value.toLowerCase().trim();
      projectRows.forEach(row => {
        const name = row.getAttribute('data-name') || '';
        row.style.display = name.includes(q) ? 'flex' : 'none';
      });
    });
  }

  // 2. Sort & Filter Tabs
  const tabBtns = document.querySelectorAll('.filter-tabs-row .tab-btn');
  tabBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      tabBtns.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      const sortType = btn.getAttribute('data-sort');
      const rowsArray = Array.from(document.querySelectorAll('.project-item-row'));

      if (sortType === 'name') {
        rowsArray.sort((a, b) => (a.getAttribute('data-name') || '').localeCompare(b.getAttribute('data-name') || ''));
        rowsArray.forEach(r => { r.style.display = 'flex'; container.appendChild(r); });
      } else if (sortType === 'progress') {
        rowsArray.sort((a, b) => (parseInt(b.getAttribute('data-progress')) || 0) - (parseInt(a.getAttribute('data-progress')) || 0));
        rowsArray.forEach(r => { r.style.display = 'flex'; container.appendChild(r); });
      } else if (sortType === 'overdue') {
        rowsArray.forEach(r => {
          const isDone = (r.getAttribute('data-status') || '').toLowerCase().includes('completed');
          r.style.display = isDone ? 'none' : 'flex';
        });
      } else if (sortType === 'unassigned') {
        rowsArray.forEach(r => {
          const prog = parseInt(r.getAttribute('data-progress')) || 0;
          r.style.display = (prog === 0) ? 'flex' : 'none';
        });
      } else { // last_change (default)
        rowsArray.forEach(r => { r.style.display = 'flex'; container.appendChild(r); });
      }
      if (typeof showToast === 'function') showToast(`Filtered by ${btn.textContent.trim()}`);
    });
  });

  // 3. Header "+ Create new project" button
  const btnCreateProj = document.getElementById('btnOpenCreateProjFromHeader');
  if (btnCreateProj) {
    btnCreateProj.addEventListener('click', () => {
      if (typeof openCreateProjectModal === 'function') {
        openCreateProjectModal();
      } else {
        const overlay = document.getElementById('createProjectModalOverlay');
        if (overlay) overlay.classList.add('active');
      }
    });
  }
});

function openProjectInfoModal(proj) {
  const modal = document.getElementById('projectInfoModal');
  if (!modal) return;
  document.getElementById('infoModalName').textContent = proj.name;
  document.getElementById('infoModalCategory').textContent = `${proj.category || 'General'} · ${proj.level || 'Intermediate'}`;
  document.getElementById('infoModalBadge').textContent = (proj.code || 'PR').slice(0, 2);
  document.getElementById('infoModalBadge').style.background = proj.bg_gradient || '#6C5CE7';
  
  const prog = Math.round(proj.progress_pct || 0);
  const comp = proj.completed_modules || 0;
  const tot = proj.total_modules || 10;
  document.getElementById('infoModalProgressTxt').textContent = `${comp} / ${tot} modules (${prog}%)`;
  document.getElementById('infoModalProgressBar').style.width = `${prog}%`;

  modal.classList.add('active');
}

function toggleProjectOptions(e, projId, projName) {
  e.stopPropagation();
  document.querySelectorAll('.proj-opts-dropdown').forEach(d => d.remove());

  const dropdown = document.createElement('div');
  dropdown.className = 'proj-opts-dropdown';
  dropdown.style.cssText = 'position:absolute; right:0; top:28px; background:var(--panel-bg); border:1px solid var(--border); border-radius:8px; box-shadow:0 10px 28px rgba(0,0,0,0.18); z-index:999; width:170px; overflow:hidden; animation:modalZoomIn 0.15s ease-out;';
  dropdown.innerHTML = `
    <div class="kebab-menu-item" onclick="window.location.href='gantt.php'">📈 Open in Gantt</div>
    <div class="kebab-menu-item" onclick="window.location.href='kanban.php'">📋 Open in Kanban</div>
    <div class="kebab-menu-item" onclick="window.location.href='goals.php'">🎯 Track as OKR</div>
    <div class="kebab-menu-item" style="color:#EF4444;" onclick="deleteProject(${projId})">🗑️ Delete Project</div>
  `;
  e.target.parentElement.appendChild(dropdown);

  document.addEventListener('click', function closeMenu(ev) {
    if (!dropdown.contains(ev.target)) {
      dropdown.remove();
      document.removeEventListener('click', closeMenu);
    }
  });
}

async function deleteProject(projId) {
  if (!confirm('Are you sure you want to delete this project?')) return;
  try {
    const res = await secureFetch('api/create_project.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'delete', id: projId })
    });
    const data = await res.json();
    if (data.success) {
      if (typeof showToast === 'function') showToast('Project deleted successfully');
      setTimeout(() => location.reload(), 400);
    } else {
      alert(data.message || 'Failed to delete project');
    }
  } catch (err) { console.error(err); }
}

async function toggleProjFavorite(id, btn) {
  const isStarred = btn.classList.contains('starred') ? 0 : 1;
  try {
    const res = await secureFetch('api/toggle_favorite.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ project_id: id, is_starred: isStarred })
    });
    const data = await res.json();
    if (data.success) {
      btn.classList.toggle('starred', isStarred === 1);
      btn.textContent = isStarred === 1 ? '★' : '☆';
      if (typeof showToast === 'function') showToast(isStarred ? 'Project starred' : 'Project unstarred');
    }
  } catch (err) {
    console.error(err);
  }
}

async function toggleProjStatus(id, btn) {
  const txt = btn.querySelector('.status-txt');
  let newStatus = 'No status';
  if (txt.textContent === 'No status') newStatus = 'In progress';
  else if (txt.textContent === 'In progress') newStatus = 'Completed';
  else newStatus = 'No status';

  try {
    const res = await secureFetch('api/update_project_status.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ project_id: id, status: newStatus })
    });
    const data = await res.json();
    if (data.success) {
      txt.textContent = newStatus;
      if (typeof showToast === 'function') showToast(`Status: ${newStatus}`);
    }
  } catch (err) {
    console.error(err);
  }
}
</script>
</body>
</html>
