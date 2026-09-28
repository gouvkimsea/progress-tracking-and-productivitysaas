<?php
require_once __DIR__ . '/config/db.php';
startSecureSession();
http_response_code(404);

$pageTitle = 'Mindrift — 404 Page Not Found';
include __DIR__ . '/includes/head.php';
?>
<style>
  .error-container {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
    background: var(--white);
    color: var(--ink);
    text-align: center;
    font-family: inherit;
  }
  .error-card {
    max-width: 520px;
    width: 100%;
    padding: 48px 36px;
    background: var(--panel-bg);
    border: 1px solid var(--border);
    border-radius: var(--radius-xl);
    box-shadow: var(--shadow-card);
    animation: fadeInUp 0.3s cubic-bezier(0.16, 1, 0.3, 1);
  }
  .error-code {
    font-size: 88px;
    font-weight: 900;
    line-height: 1;
    margin: 0 0 12px;
    background: linear-gradient(135deg, #6C5CE7 0%, #A5B4FC 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    letter-spacing: -0.04em;
  }
  .error-title {
    font-size: 22px;
    font-weight: 800;
    margin: 0 0 10px;
    color: var(--ink);
  }
  .error-desc {
    font-size: 14px;
    color: var(--muted);
    margin: 0 0 28px;
    line-height: 1.5;
  }
  .error-actions {
    display: flex;
    gap: 12px;
    justify-content: center;
    flex-wrap: wrap;
  }
  .btn-primary {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    background: var(--purple);
    color: #FFFFFF;
    font-weight: 700;
    font-size: 13.5px;
    border-radius: 10px;
    text-decoration: none;
    transition: transform 0.15s ease, background 0.15s ease;
  }
  .btn-primary:hover {
    background: var(--purple-deep);
    transform: translateY(-1px);
  }
  .btn-outline {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 18px;
    background: var(--panel-bg);
    color: var(--ink);
    border: 1px solid var(--border);
    font-weight: 600;
    font-size: 13.5px;
    border-radius: 10px;
    text-decoration: none;
    transition: background 0.15s ease, border-color 0.15s ease;
  }
  .btn-outline:hover {
    background: rgba(108, 92, 231, 0.08);
    border-color: var(--purple);
    color: var(--purple);
  }
</style>
</head>
<body>

<div class="error-container">
  <div class="error-card">
    <a href="index.php" style="display:inline-flex; align-items:center; gap:8px; text-decoration:none; margin-bottom:12px;">
      <span style="font-weight:900; font-size:22px; letter-spacing:-0.03em; color:var(--ink);">MINDRIFT</span>
    </a>
    <div style="font-size: 42px; margin-bottom: 4px;">🧭</div>
    <h1 class="error-code">404</h1>
    <h2 class="error-title">Page or Project Not Found</h2>
    <p class="error-desc">
      The destination you requested doesn't exist, has been moved, or is temporarily unavailable. Let's get you back on track!
    </p>

    <!-- Quick In-Page Search -->
    <div style="margin: 0 0 24px; position: relative; text-align: left;">
      <div style="position: relative;">
        <input type="text" id="quick404Search" placeholder="Search tasks, projects, courses..." 
               style="width: 100%; padding: 11px 14px 11px 38px; border-radius: 10px; border: 1px solid var(--border); background: var(--panel-bg); color: var(--ink); font-size: 13.5px; outline: none; box-sizing: border-box; transition: border-color 0.15s ease;" 
               onfocus="this.style.borderColor='var(--purple)'" onblur="this.style.borderColor='var(--border)'" />
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="position: absolute; left: 13px; top: 13px; color: var(--muted);"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      </div>
      <div id="quick404Results" style="display: none; position: absolute; top: calc(100% + 4px); left: 0; right: 0; background: var(--panel-bg); border: 1px solid var(--border); border-radius: 10px; max-height: 220px; overflow-y: auto; z-index: 50; box-shadow: var(--shadow-shell); padding: 4px;"></div>
    </div>

    <div class="error-actions">
      <a href="index.php" class="btn-primary">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
        Return to Dashboard
      </a>
      <a href="assignments.php" class="btn-outline">
        📋 View Tasks
      </a>
      <a href="courses.php" class="btn-outline">
        📁 All Projects
      </a>
    </div>
  </div>
</div>

<script>
const searchInput = document.getElementById('quick404Search');
const searchResults = document.getElementById('quick404Results');
if (searchInput && searchResults) {
  searchInput.addEventListener('input', async (e) => {
    const q = e.target.value.trim();
    if (q.length < 1) {
      searchResults.style.display = 'none';
      return;
    }
    try {
      const res = await fetch(`api/search.php?q=${encodeURIComponent(q)}`);
      const data = await res.json();
      if (data.success && data.results && data.results.length > 0) {
        searchResults.innerHTML = data.results.map(r => `
          <a href="${r.url}" style="display:flex; align-items:center; justify-content:space-between; padding:8px 12px; border-radius:6px; text-decoration:none; color:var(--ink); font-size:13px; transition:background 0.15s ease;" onmouseover="this.style.background='rgba(108,92,231,0.08)'" onmouseout="this.style.background='transparent'">
            <span style="font-weight:600;">${r.title}</span>
            <span style="font-size:11px; color:var(--muted); text-transform:capitalize;">${r.type}</span>
          </a>
        `).join('');
        searchResults.style.display = 'block';
      } else {
        searchResults.innerHTML = '<div style="padding:10px; font-size:12.5px; color:var(--muted); text-align:center;">No matching items found</div>';
        searchResults.style.display = 'block';
      }
    } catch(err) {
      console.error(err);
    }
  });

  document.addEventListener('click', (e) => {
    if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
      searchResults.style.display = 'none';
    }
  });
}
</script>

</body>
</html>
