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
    background: var(--bg-app);
    color: var(--text-primary);
    text-align: center;
    font-family: inherit;
  }
  .error-card {
    max-width: 480px;
    width: 100%;
    padding: 40px 32px;
    background: var(--bg-surface);
    border: 1px solid var(--border-base);
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-sm);
  }
  .error-code {
    font-size: 72px;
    font-weight: 800;
    line-height: 1;
    margin: 0 0 12px;
    color: var(--brand-primary);
    letter-spacing: -0.03em;
  }
  .error-title {
    font-size: 20px;
    font-weight: 700;
    margin: 0 0 8px;
    color: var(--text-primary);
  }
  .error-desc {
    font-size: 14px;
    color: var(--text-secondary);
    margin: 0 0 24px;
    line-height: 1.5;
  }
  .error-actions {
    display: flex;
    gap: 10px;
    justify-content: center;
    flex-wrap: wrap;
  }
  .btn-primary {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 9px 16px;
    background: var(--brand-primary);
    color: #FFFFFF;
    font-weight: 600;
    font-size: 13.5px;
    border-radius: var(--radius-sm);
    text-decoration: none;
    transition: background 0.15s ease;
  }
  .btn-primary:hover {
    background: var(--brand-hover);
  }
  .btn-outline {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 9px 16px;
    background: var(--bg-surface);
    color: var(--text-primary);
    border: 1px solid var(--border-base);
    font-weight: 600;
    font-size: 13.5px;
    border-radius: var(--radius-sm);
    text-decoration: none;
    transition: background 0.15s ease, border-color 0.15s ease;
  }
  .btn-outline:hover {
    background: var(--bg-subtle);
    border-color: var(--border-focus);
  }
  .search-result-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 12px;
    border-radius: var(--radius-sm);
    text-decoration: none;
    color: var(--text-primary);
    font-size: 13px;
    transition: background 0.12s ease;
  }
  .search-result-item:hover {
    background: var(--bg-subtle);
  }
</style>
</head>
<body>

<div class="error-container">
  <div class="error-card">
    <div style="font-weight: 800; font-size: 18px; letter-spacing: -0.02em; color: var(--text-primary); margin-bottom: 20px;">
      MINDRIFT
    </div>
    <div class="error-code">404</div>
    <h1 class="error-title">Page Not Found</h1>
    <p class="error-desc">
      The requested page does not exist or has been moved.
    </p>

    <!-- Quick In-Page Search -->
    <div style="margin: 0 0 24px; position: relative; text-align: left;">
      <div style="position: relative;">
        <input type="text" id="quick404Search" placeholder="Search tasks, projects, courses..." 
               style="width: 100%; padding: 10px 14px 10px 36px; border-radius: var(--radius-sm); border: 1px solid var(--border-base); background: var(--bg-surface); color: var(--text-primary); font-size: 13.5px; outline: none; box-sizing: border-box; transition: border-color 0.15s ease;" 
               onfocus="this.style.borderColor='var(--brand-primary)'" onblur="this.style.borderColor='var(--border-base)'" />
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="position: absolute; left: 12px; top: 12px; color: var(--text-secondary);"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      </div>
      <div id="quick404Results" style="display: none; position: absolute; top: calc(100% + 4px); left: 0; right: 0; background: var(--bg-surface); border: 1px solid var(--border-base); border-radius: var(--radius-sm); max-height: 220px; overflow-y: auto; z-index: 50; box-shadow: var(--shadow-sm); padding: 4px;"></div>
    </div>

    <div class="error-actions">
      <a href="index.php" class="btn-primary">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
        Return to Dashboard
      </a>
      <a href="assignments.php" class="btn-outline">
        View Tasks
      </a>
      <a href="courses.php" class="btn-outline">
        All Courses
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
          <a href="${r.url}" class="search-result-item">
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
