(function() {
  'use strict';

  // Global State Storage
  let appState = {
    user: null,
    stats: null,
    courses: [],
    weekly_streaks: [],
    skills: [],
    activities: []
  };

  // Toast Notification Helper
  function showToast(message) {
    let toast = document.getElementById('toast');
    if (!toast) {
      toast = document.createElement('div');
      toast.id = 'toast';
      toast.className = 'toast-notification';
      document.body.appendChild(toast);
    }
    toast.innerHTML = `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2FBE73" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> ${message}`;
    toast.classList.add('active');
    setTimeout(() => toast.classList.remove('active'), 3200);
  }

  // HTML Entity Escaping Helper to Prevent XSS
  function escapeHtml(str) {
    if (!str) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  // CSRF Token Retrieval Helper
  function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
  }

  // Wrapper for fetch requests attaching CSRF token to state-changing requests
  async function secureFetch(url, options = {}) {
    options = options || {};
    options.headers = options.headers || {};
    const method = (options.method || 'GET').toUpperCase();
    if (['POST', 'PUT', 'DELETE', 'PATCH'].includes(method)) {
      const token = getCsrfToken();
      if (token) {
        options.headers['X-CSRF-Token'] = token;
      }
    }
    return fetch(url, options);
  }
  window.showToast = showToast;
  window.escapeHtml = escapeHtml;
  window.secureFetch = secureFetch;
  window.getCsrfToken = getCsrfToken;

  // Init App & Load Dashboard Data from PHP API
  async function loadDashboardData() {
    try {
      const response = await fetch('api/dashboard.php');
      const result = await response.json();
      if (result.success) {
        appState = result.data;
        renderAllComponents();
      } else {
        console.warn('Failed to load DB data:', result.message);
        renderAllComponents();
      }
    } catch (err) {
      console.warn('Backend API connection offline:', err);
      renderAllComponents();
    }
  }

  function renderAllComponents() {
    renderStats();
    renderSparkbars();
    buildHeatmap();
    renderWeekDays();
    renderCourses();
    renderRadarChart();
    renderDate();
  }

  // Render High Level Statistics
  function renderStats() {
    if (!appState.stats) return;
    const s = appState.stats;
    
    // Learning Streak Card
    const streakVal = document.getElementById('statStreakVal');
    if (streakVal) streakVal.textContent = s.learning_streak || 0;
    
    const streakBig = document.getElementById('streakBig');
    if (streakBig) streakBig.textContent = (s.learning_streak || 0) + ' days';

    const longestVal = document.getElementById('longestStreakVal');
    if (longestVal) longestVal.textContent = (s.longest_streak || 0) + ' days';

    // Course Progress Card
    const progressVal = document.getElementById('statProgressVal');
    if (progressVal) progressVal.textContent = Math.round(s.course_progress_pct || 0) + '%';

    const weeklyCurr = document.getElementById('weeklyLessonsCurr');
    if (weeklyCurr) weeklyCurr.textContent = (s.weekly_lessons_current || 0) + ' lessons';

    const weeklyLast = document.getElementById('weeklyLessonsLast');
    if (weeklyLast) weeklyLast.textContent = (s.weekly_lessons_last || 0) + ' lessons';

    // Study Time Card
    const timeVal = document.getElementById('statTimeVal');
    if (timeVal) timeVal.textContent = `${s.study_hours || 0}h ${s.study_minutes || 0}m`;

    // Timeline Footer Totals
    const totalHoursFooter = document.getElementById('totalHoursFooter');
    if (totalHoursFooter) totalHoursFooter.textContent = `${s.study_hours || 0}h`;

    const totalLessonsFooter = document.getElementById('totalLessonsFooter');
    if (totalLessonsFooter) totalLessonsFooter.textContent = s.weekly_lessons_current || 0;
  }

  // Render Dynamic Sparkbars based on Activity Data
  function renderSparkbars() {
    const activities = appState.activities || [];
    let streakHeights = [20, 30, 25, 40, 35, 50, 45, 60, 55, 70, 65, 80];
    let progressHeights = [15, 25, 35, 45, 55, 65, 75, 85, 90, 95, 100, 95];
    let timeHeights = [30, 40, 50, 45, 60, 70, 65, 80, 85, 90, 75, 85];

    if (activities.length > 0) {
      streakHeights = activities.slice(-12).map(a => Math.min(100, (a.lessons_completed || 1) * 25));
      timeHeights = activities.slice(-12).map(a => Math.min(100, (a.study_minutes || 10) * 1.5));
    }

    const barsData = {
      'bars-streak': streakHeights,
      'bars-progress': progressHeights,
      'bars-time': timeHeights
    };

    Object.keys(barsData).forEach(id => {
      const el = document.getElementById(id);
      if (!el) return;
      el.innerHTML = '';
      const isTime = id === 'bars-time';
      barsData[id].forEach((h, idx) => {
        const i = document.createElement('i');
        i.style.height = Math.max(15, h) + '%';
        let bg = 'var(--purple)';
        if (id === 'bars-progress') bg = 'var(--orange)';
        if (isTime) {
          bg = idx % 3 === 0 ? 'var(--purple)' : (idx % 3 === 1 ? 'var(--orange)' : 'var(--blue)');
        }
        i.style.background = bg;
        i.style.opacity = (0.4 + (h / 180)).toString();
        el.appendChild(i);
      });
    });
  }

  // Build Activity Heatmap from Real Database Activity Logs
  const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
  const daysInMonth = [31,28,31,30,31,30,31,31,30,31,30,31];

  function buildHeatmap(filterCategory = 'all') {
    const wrap = document.getElementById('heatmap');
    if (!wrap) return;
    wrap.innerHTML = '';
    const tooltip = document.getElementById('tooltip');

    const activityMap = {};
    (appState.activities || []).forEach(act => {
      if (filterCategory === 'all' || act.category === filterCategory) {
        activityMap[act.activity_date] = act.lessons_completed || 1;
      }
    });

    const currentYear = new Date().getFullYear();

    months.forEach((m, mi) => {
      const col = document.createElement('div');
      col.className = 'month-col';
      const label = document.createElement('div');
      label.className = 'month-label';
      label.textContent = m;
      col.appendChild(label);

      const totalDays = daysInMonth[mi];
      const cols = Math.ceil(totalDays / 5);

      for (let r = 0; r < 5; r++) {
        const row = document.createElement('div');
        row.className = 'week-row';

        for (let c = 0; c < cols; c++) {
          const dayNum = c * 5 + r + 1;
          const cell = document.createElement('div');
          
          if (dayNum > totalDays) {
            cell.style.visibility = 'hidden';
            cell.className = 'cell';
          } else {
            const mStr = String(mi + 1).padStart(2, '0');
            const dStr = String(dayNum).padStart(2, '0');
            const dateKey = `${currentYear}-${mStr}-${dStr}`;

            const count = activityMap[dateKey] || 0;
            let lvl = 0;
            if (count >= 4) lvl = 4;
            else if (count === 3) lvl = 3;
            else if (count === 2) lvl = 2;
            else if (count === 1) lvl = 1;

            cell.className = 'cell l' + lvl;
            
            cell.addEventListener('mouseenter', (ev) => {
              if (!tooltip) return;
              const rect = ev.target.getBoundingClientRect();
              tooltip.textContent = `${m} ${dayNum} — ${lvl === 0 ? 'no activity logged' : count + ' lesson' + (count > 1 ? 's' : '') + ' completed'}`;
              tooltip.style.left = (rect.left + rect.width / 2) + 'px';
              tooltip.style.top = rect.top + 'px';
              tooltip.classList.add('show');
            });
            cell.addEventListener('mouseleave', () => tooltip && tooltip.classList.remove('show'));
          }
          row.appendChild(cell);
        }
        col.appendChild(row);
      }
      wrap.appendChild(col);
    });
  }

  // Interactive Weekly Streak Days (Mon - Sun Check-in)
  function renderWeekDays() {
    const weekDaysEl = document.getElementById('weekDays');
    if (!weekDaysEl) return;
    weekDaysEl.innerHTML = '';

    const defaultDays = [
      { day_index: 0, day_name: 'Mon', is_completed: 0 },
      { day_index: 1, day_name: 'Tue', is_completed: 0 },
      { day_index: 2, day_name: 'Wed', is_completed: 0 },
      { day_index: 3, day_name: 'Thu', is_completed: 0 },
      { day_index: 4, day_name: 'Fri', is_completed: 0 },
      { day_index: 5, day_name: 'Sat', is_completed: 0 },
      { day_index: 6, day_name: 'Sun', is_completed: 0 }
    ];

    const streaks = (appState.weekly_streaks && appState.weekly_streaks.length) ? appState.weekly_streaks : defaultDays;

    streaks.forEach((item) => {
      const col = document.createElement('div');
      col.className = 'day-col';

      const name = document.createElement('span');
      name.className = 'day-name';
      name.textContent = item.day_name;

      const circle = document.createElement('div');
      circle.className = 'day-circle' + (item.is_completed == 1 ? ' checked' : '');
      circle.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5 10 17l9-10"/></svg>';
      
      // Interactive Toggle Click with SQL Database Sync
      circle.addEventListener('click', async () => {
        const newStatus = item.is_completed == 1 ? 0 : 1;
        item.is_completed = newStatus;
        renderWeekDays();
        updateStreakCountUI();

        try {
          const res = await secureFetch('api/toggle_streak.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ day_index: item.day_index, status: newStatus })
          });
          const data = await res.json();
          if (data.success) {
            showToast(`${item.day_name} check-in updated! Streak: ${data.data.total_learning_streak} days`);
            if (appState.stats) {
              appState.stats.learning_streak = data.data.total_learning_streak;
              renderStats();
            }
          }
        } catch (e) {
          console.error('Error toggling streak:', e);
        }
      });

      col.appendChild(name);
      col.appendChild(circle);
      weekDaysEl.appendChild(col);
    });

    updateStreakCountUI();
  }

  function updateStreakCountUI() {
    const streaks = appState.weekly_streaks || [];
    let count = 0;
    for (let i = 0; i < streaks.length; i++) {
      if (streaks[i].is_completed == 1) count++;
      else break;
    }
    const countEl = document.getElementById('streakCount');
    if (countEl) countEl.textContent = count;
  }

  // Render Course Radial Progress Rows with Interactive "+ Module" Buttons
  function renderCourses() {
    const lessonList = document.getElementById('lessonList');
    if (!lessonList) return;
    lessonList.innerHTML = '';

    const coursesToRender = appState.courses || [];
    const R = 17, C = 2 * Math.PI * R;

    coursesToRender.forEach(c => {
      const row = document.createElement('div');
      row.className = 'lesson-row';
      const pct = c.progress_pct || 0;
      const bg = c.bg_gradient || 'linear-gradient(145deg,#8B7CF0,#5A46E0)';
      const ring = c.ring_color || '#6C5CE7';
      const completed = c.completed_modules || 0;
      const total = c.total_modules || 10;

      row.innerHTML = `
        <div class="lesson-icon" style="background:${bg}">${c.code}</div>
        <div class="lesson-info">
          <div class="lesson-name">${c.name}</div>
          <div class="lesson-meta">${c.level || 'Intermediate'} · <span class="mod-count">${completed}/${total}</span> Modules</div>
        </div>
        <button class="btn-add-module" title="Increment completed module">+ Module</button>
        <div class="ring-wrap">
          <svg viewBox="0 0 44 44">
            <circle cx="22" cy="22" r="${R}" fill="none" stroke="#F0F0F4" stroke-width="4"/>
            <circle class="ring-fg" cx="22" cy="22" r="${R}" fill="none" stroke="${ring}" stroke-width="4" stroke-linecap="round"
              stroke-dasharray="${C}" stroke-dashoffset="${C}"/>
          </svg>
          <span class="pct-span">${pct}%</span>
        </div>`;

      // "+ Module" Button Click Handler -> Syncs to PHP API & SQL DB
      const addBtn = row.querySelector('.btn-add-module');
      addBtn.addEventListener('click', async () => {
        addBtn.disabled = true;
        try {
          const res = await secureFetch('api/courses.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'increment', course_id: c.id })
          });
          const result = await res.json();
          if (result.success) {
            c.completed_modules = result.data.completed_modules;
            c.progress_pct = result.data.progress_pct;

            if (appState.stats) {
              appState.stats.course_progress_pct = result.data.overall_course_progress_pct;
              renderStats();
            }

            row.querySelector('.mod-count').textContent = `${c.completed_modules}/${c.total_modules}`;
            row.querySelector('.pct-span').textContent = `${c.progress_pct}%`;

            const fg = row.querySelector('.ring-fg');
            if (fg) {
              fg.style.transition = 'stroke-dashoffset 0.8s cubic-bezier(.4,0,.2,1)';
              fg.setAttribute('stroke-dashoffset', (C - (C * c.progress_pct / 100)).toString());
            }

            showToast(`Progress saved for ${c.name}! (${c.completed_modules}/${c.total_modules} modules)`);
          }
        } catch (e) {
          console.error('Error updating course progress:', e);
        } finally {
          addBtn.disabled = false;
        }
      });

      lessonList.appendChild(row);

      // Initial SVG Ring Offset
      const fg = row.querySelector('.ring-fg');
      requestAnimationFrame(() => {
        setTimeout(() => {
          if (fg) {
            fg.style.transition = 'stroke-dashoffset 1s cubic-bezier(.4,0,.2,1)';
            fg.setAttribute('stroke-dashoffset', (C - (C * pct / 100)).toString());
          }
        }, 120);
      });
    });
  }

  // Render SVG Radar Skill Chart
  function renderRadarChart() {
    const radarWrap = document.getElementById('radarWrap');
    if (!radarWrap) return;

    const skills = appState.skills || [];
    const size = 240, cx = size / 2, cy = size / 2 - 4, maxR = 78;
    const n = skills.length || 5;
    const angle = i => -Math.PI / 2 + i * (2 * Math.PI / n);
    const pt = (r, i) => [cx + r * Math.cos(angle(i)), cy + r * Math.sin(angle(i))];

    let grid = '';
    [0.25, 0.5, 0.75, 1].forEach(f => {
      const pts = Array.from({ length: n }, (_, i) => pt(maxR * f, i).join(',')).join(' ');
      grid += `<polygon points="${pts}" fill="none" stroke="#EFEFF3" stroke-width="1"/>`;
    });

    let axes = '';
    let labels = '';
    skills.forEach((s, i) => {
      const [x, y] = pt(maxR, i);
      axes += `<line x1="${cx}" y1="${cy}" x2="${x}" y2="${y}" stroke="#EFEFF3" stroke-width="1"/>`;
      const [lx, ly] = pt(maxR + 22, i);
      let anchor = 'middle';
      if (lx > cx + 8) anchor = 'start';
      else if (lx < cx - 8) anchor = 'end';
      labels += `<text x="${lx}" y="${ly}" text-anchor="${anchor}" dominant-baseline="middle" class="radar-label">${s.label}</text>`;
    });

    const dataPts = skills.map((s, i) => pt(maxR * (parseFloat(s.score_pct) || 0.5), i));
    const dataPath = dataPts.map(p => p.join(',')).join(' ');
    let dots = '';
    dataPts.forEach((p) => {
      dots += `<circle cx="${p[0]}" cy="${p[1]}" r="3.5" fill="#fff" stroke="#6C5CE7" stroke-width="2"/>`;
    });

    radarWrap.innerHTML = `<svg viewBox="0 0 ${size} ${size}">
      ${grid}${axes}
      <polygon points="${dataPath}" fill="rgba(108,92,231,0.28)" stroke="#6C5CE7" stroke-width="2"/>
      ${dots}
      ${labels}
    </svg>`;
  }

  // Format Current Date
  function renderDate() {
    const el = document.getElementById('todayDate');
    if (el) {
      const d = new Date();
      const opts = { weekday: 'short', month: 'short', day: 'numeric' };
      el.textContent = d.toLocaleDateString('en-US', opts);
    }
  }

  // Navigation & Interactive Component Event Listeners
  function initEventListeners() {
    // 1. Sidebar Nav Link Click Handlers
    const navItems = document.querySelectorAll('.sidebar .nav-item');
    navItems.forEach(item => {
      item.addEventListener('click', (e) => {
        navItems.forEach(i => i.classList.remove('active'));
        item.classList.add('active');
        const navName = item.getAttribute('data-nav') || 'Dashboard';
        showToast(`Navigated to ${navName}`);
      });
    });

    // 2. Sidebar collapse toggle button
    const sidebarToggle = document.getElementById('sidebarToggle');
    const appEl = document.getElementById('app');
    if (sidebarToggle && appEl) {
      sidebarToggle.addEventListener('click', () => {
        appEl.classList.toggle('collapsed');
      });
    }

    // 3. Mobile menu button toggle
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    if (mobileMenuBtn && appEl) {
      mobileMenuBtn.addEventListener('click', () => {
        appEl.classList.toggle('mobile-open');
      });
    }

    // 4. Live Sessions accordion expand button
    const liveToggle = document.getElementById('liveSessionsToggle');
    const liveSub = document.getElementById('liveSessionsSub');
    if (liveToggle && liveSub) {
      liveToggle.addEventListener('click', () => {
        liveToggle.classList.toggle('open');
        liveSub.classList.toggle('open');
      });
    }

    // Sidebar Collapse Toggle (Desktop)
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    if (sidebar) {
      if (localStorage.getItem('sidebar_collapsed') === 'true') {
        sidebar.classList.add('collapsed');
      }
      if (sidebarToggle) {
        sidebarToggle.addEventListener('click', () => {
          sidebar.classList.toggle('collapsed');
          localStorage.setItem('sidebar_collapsed', sidebar.classList.contains('collapsed'));
        });
      }
    }

    // Mobile Menu Drawer Toggle
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const appContainer = document.getElementById('app');
    if (mobileMenuBtn && appContainer) {
      mobileMenuBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        appContainer.classList.toggle('mobile-open');
      });
      document.addEventListener('click', (e) => {
        if (appContainer.classList.contains('mobile-open')) {
          if (!sidebar || (!sidebar.contains(e.target) && !mobileMenuBtn.contains(e.target))) {
            appContainer.classList.remove('mobile-open');
          }
        }
      });
    }

    // 5. Timeline Filter Menu Toggle & Option Click
    const filterBtn = document.getElementById('filterBtn');
    const filterMenu = document.getElementById('filterMenu');
    if (filterBtn && filterMenu) {
      filterBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        filterMenu.classList.toggle('open');
      });
      document.addEventListener('click', () => filterMenu.classList.remove('open'));

      const filterButtons = filterMenu.querySelectorAll('button');
      filterButtons.forEach(btn => {
        btn.addEventListener('click', (e) => {
          e.stopPropagation();
          filterButtons.forEach(b => b.classList.remove('sel'));
          btn.classList.add('sel');
          const category = btn.getAttribute('data-cat') || 'all';
          buildHeatmap(category);
          filterMenu.classList.remove('open');
          showToast(`Filtered view: ${btn.textContent}`);
        });
      });
    }

    // 6. Smooth Scroll Links
    const scrollLinks = document.querySelectorAll('[data-scroll]');
    scrollLinks.forEach(link => {
      link.addEventListener('click', (e) => {
        e.preventDefault();
        const targetId = link.getAttribute('data-scroll');
        const targetEl = document.getElementById(targetId);
        if (targetEl) {
          targetEl.scrollIntoView({ behavior: 'smooth' });
        }
      });
    });

    // 7. Kebab Options Dropdowns
    const kebabs = document.querySelectorAll('.kebab');
    kebabs.forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        showToast('Options menu clicked');
      });
    });

    // 8. Log Activity Modal Control Buttons
    const btnOpenLogModal = document.getElementById('btnOpenLogModal');
    const btnCloseLogModal = document.getElementById('btnCloseLogModal');
    const btnCancelLogModal = document.getElementById('btnCancelLogModal');
    const logModalOverlay = document.getElementById('logModalOverlay');
    const logActivityForm = document.getElementById('logActivityForm');

    function openModal() {
      if (logModalOverlay) logModalOverlay.classList.add('active');
    }
    function closeModal() {
      if (logModalOverlay) logModalOverlay.classList.remove('active');
    }

    if (btnOpenLogModal) btnOpenLogModal.addEventListener('click', openModal);
    if (btnCloseLogModal) btnCloseLogModal.addEventListener('click', closeModal);
    if (btnCancelLogModal) btnCancelLogModal.addEventListener('click', closeModal);
    if (logModalOverlay) {
      logModalOverlay.addEventListener('click', (e) => {
        if (e.target === logModalOverlay) closeModal();
      });
    }

    // Submit Log Activity Form -> Syncs to PHP API & SQL Database
    if (logActivityForm) {
      logActivityForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const lessons = parseInt(document.getElementById('logLessons').value, 10);
        const minutes = parseInt(document.getElementById('logMinutes').value, 10);
        const category = document.getElementById('logCategory').value;
        const activity_date = document.getElementById('logDate').value;

        const btnSave = document.getElementById('btnSubmitLogModal');
        btnSave.disabled = true;
        btnSave.textContent = 'Saving...';

        try {
          const res = await secureFetch('api/log_activity.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ lessons_completed: lessons, study_minutes: minutes, category, activity_date })
          });
          const data = await res.json();
          if (data.success) {
            closeModal();
            showToast(`Logged ${lessons} lesson(s), ${minutes} mins!`);
            
            // Reload fresh stats from database
            await loadDashboardData();
          } else {
            alert('Error logging activity: ' + (data.message || data.error));
          }
        } catch (err) {
          console.error('Failed to log activity:', err);
        } finally {
          btnSave.disabled = false;
          btnSave.textContent = 'Save Activity';
        }
      });
    }
    // 9. Create Project Modal Controls
    const btnOpenProjectModal = document.getElementById('btnOpenCreateProjectModal');
    const btnCloseProjectModal = document.getElementById('btnCloseProjectModal');
    const btnCancelProjectModal = document.getElementById('btnCancelProjectModal');
    const createProjectModalOverlay = document.getElementById('createProjectModalOverlay');
    const createProjectForm = document.getElementById('createProjectForm');

    function openProjectModal() {
      if (createProjectModalOverlay) createProjectModalOverlay.classList.add('active');
    }
    function closeProjectModal() {
      if (createProjectModalOverlay) createProjectModalOverlay.classList.remove('active');
    }

    if (btnOpenProjectModal) btnOpenProjectModal.addEventListener('click', openProjectModal);
    if (btnCloseProjectModal) btnCloseProjectModal.addEventListener('click', closeProjectModal);
    if (btnCancelProjectModal) btnCancelProjectModal.addEventListener('click', closeProjectModal);
    if (createProjectModalOverlay) {
      createProjectModalOverlay.addEventListener('click', (e) => {
        if (e.target === createProjectModalOverlay) closeProjectModal();
      });
    }

    if (createProjectForm) {
      createProjectForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const name = document.getElementById('projName').value.trim();
        const code = document.getElementById('projCode').value.trim();
        const category = document.getElementById('projCategory').value;
        const level = document.getElementById('projLevel').value;
        const total_modules = parseInt(document.getElementById('projModules').value, 10);

        const btnSubmit = document.getElementById('btnSubmitProjectModal');
        btnSubmit.disabled = true;
        btnSubmit.textContent = 'Creating...';

        try {
          const res = await secureFetch('api/create_project.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name, code, category, level, total_modules })
          });
          const data = await res.json();
          if (data.success) {
            closeProjectModal();
            showToast(`New Project "${name}" created!`);
            setTimeout(() => { window.location.href = 'courses.php'; }, 1000);
          } else {
            alert('Error creating project: ' + (data.message || data.error));
          }
        } catch (err) {
          console.error(err);
        } finally {
          btnSubmit.disabled = false;
          btnSubmit.textContent = 'Create Project';
        }
      });
    }

    // 10. Upgrade Account Modal Controls
    const btnUpgradeAccount = document.querySelector('.btn-upgrade-account');
    const upgradeModalOverlay = document.getElementById('upgradeModalOverlay');
    const btnCloseUpgradeModal = document.getElementById('btnCloseUpgradeModal');

    if (btnUpgradeAccount && upgradeModalOverlay) {
      btnUpgradeAccount.addEventListener('click', () => {
        upgradeModalOverlay.classList.add('active');
      });
    }
    if (btnCloseUpgradeModal && upgradeModalOverlay) {
      btnCloseUpgradeModal.addEventListener('click', () => {
        upgradeModalOverlay.classList.remove('active');
      });
    }
    // 11. Pomodoro Focus Studio Engine
    const btnToggleTimer = document.getElementById('btnToggleTimer');
    const btnResetTimer = document.getElementById('btnResetTimer');
    const timerDisplay = document.getElementById('timerDisplay');
    const pomoProgressBar = document.getElementById('pomoProgressBar');
    const pomoSessionsBadge = document.getElementById('pomoSessionsBadge');
    const pomoTabs = document.querySelectorAll('.pomo-tab');

    let pomoInterval = null;
    let pomoMode = 'focus';
    let pomoDuration = 25 * 60;
    let pomoRemaining = 25 * 60;
    let pomoSessionsCount = parseInt(localStorage.getItem('pomo_today_count') || '0', 10);

    if (pomoSessionsBadge) {
      pomoSessionsBadge.textContent = `🍅 ${pomoSessionsCount} today`;
    }

    function playChime(type = 'finish') {
      try {
        const AudioContext = window.AudioContext || window.webkitAudioContext;
        if (!AudioContext) return;
        const ctx = new AudioContext();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.connect(gain);
        gain.connect(ctx.destination);
        if (type === 'finish') {
          osc.frequency.setValueAtTime(587.33, ctx.currentTime);
          osc.frequency.setValueAtTime(880, ctx.currentTime + 0.15);
          gain.gain.setValueAtTime(0.25, ctx.currentTime);
          gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.8);
          osc.start(ctx.currentTime);
          osc.stop(ctx.currentTime + 0.8);
        } else {
          osc.frequency.setValueAtTime(523.25, ctx.currentTime);
          gain.gain.setValueAtTime(0.15, ctx.currentTime);
          gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.25);
          osc.start(ctx.currentTime);
          osc.stop(ctx.currentTime + 0.25);
        }
      } catch (e) {}
    }

    function updatePomoDisplay() {
      if (!timerDisplay) return;
      const m = Math.floor(pomoRemaining / 60);
      const s = pomoRemaining % 60;
      timerDisplay.textContent = `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
      if (pomoProgressBar) {
        const pct = Math.min(100, Math.max(0, ((pomoDuration - pomoRemaining) / pomoDuration) * 100));
        pomoProgressBar.style.width = `${pct}%`;
      }
      if (pomoInterval) {
        document.title = `(${timerDisplay.textContent}) 🍅 ${pomoMode.toUpperCase()} — Mindrift`;
      }
    }

    if (pomoTabs && pomoTabs.length > 0) {
      pomoTabs.forEach(tab => {
        tab.addEventListener('click', () => {
          if (pomoInterval) {
            clearInterval(pomoInterval);
            pomoInterval = null;
            if (btnToggleTimer) {
              btnToggleTimer.textContent = '▶ Start';
              btnToggleTimer.style.background = '#10B981';
            }
          }
          pomoTabs.forEach(t => {
            t.classList.remove('active');
            t.style.background = 'transparent';
            t.style.color = 'var(--muted)';
          });
          tab.classList.add('active');
          tab.style.background = 'var(--blue)';
          tab.style.color = '#fff';

          pomoMode = tab.dataset.mode;
          pomoDuration = parseInt(tab.dataset.mins, 10) * 60;
          pomoRemaining = pomoDuration;
          updatePomoDisplay();
        });
      });
    }

    if (btnResetTimer) {
      btnResetTimer.addEventListener('click', () => {
        if (pomoInterval) {
          clearInterval(pomoInterval);
          pomoInterval = null;
        }
        if (btnToggleTimer) {
          btnToggleTimer.textContent = '▶ Start';
          btnToggleTimer.style.background = '#10B981';
        }
        pomoRemaining = pomoDuration;
        document.title = 'Mindrift — Project & Learning Tracker';
        updatePomoDisplay();
        if (typeof stopAmbientSoundscapes === 'function') stopAmbientSoundscapes();
        showToast('Session timer reset');
      });
    }

    if (btnToggleTimer && timerDisplay) {
      btnToggleTimer.addEventListener('click', async () => {
        if (!pomoInterval) {
          // Start Timer
          playChime('start');
          pomoInterval = setInterval(async () => {
            pomoRemaining--;
            updatePomoDisplay();

            if (pomoRemaining <= 0) {
              clearInterval(pomoInterval);
              pomoInterval = null;
              playChime('finish');
              if (typeof stopAmbientSoundscapes === 'function') stopAmbientSoundscapes();
              document.title = 'Mindrift — Session Complete!';
              btnToggleTimer.textContent = '▶ Start';
              btnToggleTimer.style.background = '#10B981';

              if (pomoMode === 'focus') {
                pomoSessionsCount++;
                localStorage.setItem('pomo_today_count', pomoSessionsCount);
                if (pomoSessionsBadge) pomoSessionsBadge.textContent = `🍅 ${pomoSessionsCount} today`;
                const mins = Math.round(pomoDuration / 60);
                showToast(`🎉 Focus session complete! Logged ${mins} mins.`);
                try {
                  await secureFetch('api/timer.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ minutes: mins, category: 'Deep Work Pomodoro' })
                  });
                } catch (err) { console.error(err); }
              } else {
                showToast('Break finished! Ready to refocus?');
              }
              pomoRemaining = pomoDuration;
              updatePomoDisplay();
            }
          }, 1000);

          btnToggleTimer.textContent = '⏸ Pause';
          btnToggleTimer.style.background = '#EF4444';
          showToast(`${pomoMode.charAt(0).toUpperCase() + pomoMode.slice(1)} session started!`);
        } else {
          // Pause Timer
          clearInterval(pomoInterval);
          pomoInterval = null;
          btnToggleTimer.textContent = '▶ Resume';
          btnToggleTimer.style.background = '#10B981';
          document.title = 'Mindrift — Project & Learning Tracker';
          showToast('Session paused');
        }
      });
    }

    // 11b. Ambient Focus Soundscapes Engine (Procedural Web Audio)
    const btnToggleAmbientSound = document.getElementById('btnToggleAmbientSound');
    const ambientSoundName = document.getElementById('ambientSoundName');
    const ambientSoundSelect = document.getElementById('ambientSoundSelect');
    const ambientVolume = document.getElementById('ambientVolume');

    let ambientAudioCtx = null;
    let ambientNodes = [];
    let ambientMasterGain = null;
    let isAmbientActive = false;

    function getAmbientContext() {
      if (!ambientAudioCtx) {
        ambientAudioCtx = new (window.AudioContext || window.webkitAudioContext)();
        ambientMasterGain = ambientAudioCtx.createGain();
        const vol = ambientVolume ? parseFloat(ambientVolume.value) : 0.3;
        ambientMasterGain.gain.setValueAtTime(vol, ambientAudioCtx.currentTime);
        ambientMasterGain.connect(ambientAudioCtx.destination);
      }
      if (ambientAudioCtx.state === 'suspended') {
        ambientAudioCtx.resume();
      }
      return ambientAudioCtx;
    }

    function stopAmbientSoundscapes() {
      ambientNodes.forEach(node => {
        try {
          if (node.stop) node.stop();
          node.disconnect();
        } catch (e) {}
      });
      ambientNodes = [];
      isAmbientActive = false;
      if (btnToggleAmbientSound) {
        btnToggleAmbientSound.style.background = 'transparent';
        btnToggleAmbientSound.style.borderColor = 'var(--border)';
        btnToggleAmbientSound.style.boxShadow = 'none';
      }
      if (ambientSoundName) ambientSoundName.textContent = 'Ambient Sounds: Off';
    }

    function playAmbientSoundscapes(type) {
      const ctx = getAmbientContext();
      stopAmbientSoundscapes();
      isAmbientActive = true;

      if (ambientVolume && ambientMasterGain) {
        ambientMasterGain.gain.setValueAtTime(parseFloat(ambientVolume.value), ctx.currentTime);
      }

      if (btnToggleAmbientSound) {
        btnToggleAmbientSound.style.background = 'rgba(108, 92, 231, 0.15)';
        btnToggleAmbientSound.style.borderColor = 'var(--purple)';
        btnToggleAmbientSound.style.boxShadow = '0 0 12px rgba(108, 92, 231, 0.3)';
      }

      const soundTitles = {
        rain: '🌧️ Gentle Rain',
        waves: '🌊 Ocean Waves',
        brown: '📻 Deep Brown Noise',
        binaural: '🧠 Alpha Waves (432Hz)'
      };
      if (ambientSoundName) {
        ambientSoundName.textContent = soundTitles[type] || 'Playing Ambient Sound';
      }

      try {
        if (type === 'rain') {
          // Pink noise through lowpass filter
          const bufferSize = ctx.sampleRate * 2;
          const buffer = ctx.createBuffer(1, bufferSize, ctx.sampleRate);
          const data = buffer.getChannelData(0);
          let b0 = 0, b1 = 0, b2 = 0, b3 = 0, b4 = 0, b5 = 0, b6 = 0;
          for (let i = 0; i < bufferSize; i++) {
            const white = Math.random() * 2 - 1;
            b0 = 0.99886 * b0 + white * 0.0555179;
            b1 = 0.99332 * b1 + white * 0.0750759;
            b2 = 0.96900 * b2 + white * 0.1538520;
            b3 = 0.86650 * b3 + white * 0.3104856;
            b4 = 0.55000 * b4 + white * 0.5329522;
            b5 = -0.7616 * b5 - white * 0.0168980;
            data[i] = (b0 + b1 + b2 + b3 + b4 + b5 + b6 + white * 0.5362) * 0.12;
            b6 = white * 0.115926;
          }
          const noise = ctx.createBufferSource();
          noise.buffer = buffer;
          noise.loop = true;

          const filter = ctx.createBiquadFilter();
          filter.type = 'lowpass';
          filter.frequency.setValueAtTime(1100, ctx.currentTime);

          noise.connect(filter);
          filter.connect(ambientMasterGain);
          noise.start();
          ambientNodes.push(noise, filter);
        } else if (type === 'waves') {
          // Modulated brown noise with slow 0.12Hz LFO swell
          const bufferSize = ctx.sampleRate * 2;
          const buffer = ctx.createBuffer(1, bufferSize, ctx.sampleRate);
          const data = buffer.getChannelData(0);
          let lastOut = 0.0;
          for (let i = 0; i < bufferSize; i++) {
            const white = Math.random() * 2 - 1;
            data[i] = (lastOut + (0.02 * white)) / 1.02;
            lastOut = data[i];
            data[i] *= 3.5;
          }
          const noise = ctx.createBufferSource();
          noise.buffer = buffer;
          noise.loop = true;

          const lfo = ctx.createOscillator();
          lfo.frequency.setValueAtTime(0.12, ctx.currentTime);
          const lfoGain = ctx.createGain();
          lfoGain.gain.setValueAtTime(320, ctx.currentTime);

          const filter = ctx.createBiquadFilter();
          filter.type = 'lowpass';
          filter.frequency.setValueAtTime(450, ctx.currentTime);

          lfo.connect(lfoGain);
          lfoGain.connect(filter.frequency);

          noise.connect(filter);
          filter.connect(ambientMasterGain);
          lfo.start();
          noise.start();
          ambientNodes.push(noise, filter, lfo, lfoGain);
        } else if (type === 'brown') {
          // Deep Brown Noise
          const bufferSize = ctx.sampleRate * 2;
          const buffer = ctx.createBuffer(1, bufferSize, ctx.sampleRate);
          const data = buffer.getChannelData(0);
          let last = 0.0;
          for (let i = 0; i < bufferSize; i++) {
            const white = Math.random() * 2 - 1;
            data[i] = (last + (0.02 * white)) / 1.02;
            last = data[i];
            data[i] *= 3.0;
          }
          const noise = ctx.createBufferSource();
          noise.buffer = buffer;
          noise.loop = true;
          noise.connect(ambientMasterGain);
          noise.start();
          ambientNodes.push(noise);
        } else if (type === 'binaural') {
          // 432 Hz carrier + 10 Hz alpha wave (442 Hz) in stereo
          const oscL = ctx.createOscillator();
          const oscR = ctx.createOscillator();
          oscL.frequency.setValueAtTime(432, ctx.currentTime);
          oscR.frequency.setValueAtTime(442, ctx.currentTime);

          const merger = ctx.createChannelMerger(2);
          oscL.connect(merger, 0, 0);
          oscR.connect(merger, 0, 1);

          const subGain = ctx.createGain();
          subGain.gain.setValueAtTime(0.25, ctx.currentTime);

          merger.connect(subGain);
          subGain.connect(ambientMasterGain);

          oscL.start();
          oscR.start();
          ambientNodes.push(oscL, oscR, merger, subGain);
        }
      } catch (err) {
        console.error('Ambient sound error:', err);
      }
    }

    if (btnToggleAmbientSound) {
      btnToggleAmbientSound.addEventListener('click', () => {
        if (isAmbientActive) {
          stopAmbientSoundscapes();
          showToast('🎧 Ambient sounds paused');
        } else {
          const type = ambientSoundSelect ? ambientSoundSelect.value : 'rain';
          playAmbientSoundscapes(type);
          showToast(`🎧 Started ${type} focus sound`);
        }
      });
    }

    if (ambientSoundSelect) {
      ambientSoundSelect.addEventListener('change', (e) => {
        if (isAmbientActive) {
          playAmbientSoundscapes(e.target.value);
        }
      });
    }

    if (ambientVolume) {
      ambientVolume.addEventListener('input', (e) => {
        const val = parseFloat(e.target.value);
        if (ambientMasterGain && ambientAudioCtx) {
          ambientMasterGain.gain.setValueAtTime(val, ambientAudioCtx.currentTime);
        }
      });
    }

    // 11c. Progressive Web App (PWA) Handler
    let deferredPrompt = null;
    const pwaContainer = document.getElementById('pwaInstallContainer');
    
    window.addEventListener('beforeinstallprompt', (e) => {
      e.preventDefault();
      deferredPrompt = e;
      if (pwaContainer) pwaContainer.style.display = 'block';
    });

    window.installPwaApp = async function() {
      if (deferredPrompt) {
        deferredPrompt.prompt();
        const choice = await deferredPrompt.userChoice;
        if (choice.outcome === 'accepted') {
          showToast('🎉 Mindrift App installed successfully!');
          if (pwaContainer) pwaContainer.style.display = 'none';
        }
        deferredPrompt = null;
      } else {
        showToast('💡 To install, open your browser menu and choose "Install App" or "Add to Home Screen"');
      }
    };

    // 12. Global Search Controls
    const globalSearchInput = document.getElementById('globalSearchInput');
    const globalSearchResults = document.getElementById('globalSearchResults');

    if (globalSearchInput && globalSearchResults) {
      globalSearchInput.addEventListener('input', async (e) => {
        const q = e.target.value.trim();
        if (q.length < 1) {
          globalSearchResults.style.display = 'none';
          return;
        }

        try {
          const res = await fetch(`api/search.php?q=${encodeURIComponent(q)}`);
          const data = await res.json();
          if (data.success && data.results.length > 0) {
            globalSearchResults.innerHTML = data.results.map(r => {
              const safeType = escapeHtml(r.type);
              const safeTitle = escapeHtml(r.title);
              const safeSub = escapeHtml(r.subtext);
              const targetUrl = r.type === 'project' ? 'courses.php' : (r.type === 'task' ? 'assignments.php' : 'community.php');
              return `
                <div class="search-result-row" onclick="window.location.href='${targetUrl}'">
                  <span style="font-size:10px; font-weight:800; text-transform:uppercase; color:#0E65C7;">[${safeType}]</span>
                  <span class="search-result-title">${safeTitle}</span>
                  <span class="search-result-sub">(${safeSub})</span>
                </div>
              `;
            }).join('');
            globalSearchResults.style.display = 'block';
          } else {
            const safeQuery = escapeHtml(q);
            globalSearchResults.innerHTML = `<div class="search-result-empty">No results found for "${safeQuery}"</div>`;
            globalSearchResults.style.display = 'block';
          }
        } catch (err) {
          console.error(err);
        }
      });

      document.addEventListener('click', (e) => {
        if (!globalSearchInput.contains(e.target) && !globalSearchResults.contains(e.target)) {
          globalSearchResults.style.display = 'none';
        }
      });
    }

    // 13. Sidebar Collapse Toggle
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    if (sidebar) {
      if (localStorage.getItem('mindrift_sidebar_collapsed') === '1') {
        sidebar.classList.add('collapsed');
      }
      if (sidebarToggle) {
        sidebarToggle.addEventListener('click', (e) => {
          e.preventDefault();
          sidebar.classList.toggle('collapsed');
          const isCollapsed = sidebar.classList.contains('collapsed');
          localStorage.setItem('mindrift_sidebar_collapsed', isCollapsed ? '1' : '0');
        });
      }
    }

    // 14. Mobile Menu Toggle
    const mobileBtn = document.getElementById('mobileMenuBtn');
    const appContainer = document.getElementById('app') || document.querySelector('.app');
    if (mobileBtn && appContainer) {
      mobileBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        appContainer.classList.toggle('mobile-open');
      });
      document.addEventListener('click', (e) => {
        if (!e.target.closest('#sidebar') && !mobileBtn.contains(e.target)) {
          appContainer.classList.remove('mobile-open');
        }
      });
    }

    // 15. Create Project Modal Controller
    window.openCreateProjectModal = function() {
      const modal = document.getElementById('createProjectModalOverlay');
      if (modal) modal.classList.add('active');
    };
    const btnCloseProj = document.getElementById('btnCloseProjectModal');
    const projModal = document.getElementById('createProjectModalOverlay');
    if (btnCloseProj && projModal) {
      btnCloseProj.addEventListener('click', () => projModal.classList.remove('active'));
      projModal.addEventListener('click', (e) => {
        if (e.target === projModal) projModal.classList.remove('active');
      });
    }
    const createProjForm = document.getElementById('createProjectForm');
    if (createProjForm && projModal) {
      createProjForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const pName = document.getElementById('projName')?.value.trim();
        const pCode = document.getElementById('projCode')?.value.trim() || 'PRJ';
        const pCat = document.getElementById('projCategory')?.value || 'Design';
        if (!pName) return;

        try {
          const res = await secureFetch('api/create_project.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name: pName, code: pCode, category: pCat })
          });
          const data = await res.json();
          if (data.success) {
            showToast('Project created successfully!');
            projModal.classList.remove('active');
            createProjForm.reset();
            setTimeout(() => {
              if (window.location.pathname.includes('courses')) location.reload();
              else window.location.href = 'courses.php';
            }, 500);
          } else {
            alert(data.message || 'Error creating project');
          }
        } catch (err) {
          console.error(err);
        }
      });
    }

    // 16. Sidebar Notifications & Search Bridges
    window.toggleNotifDropdown = function(e) {
      if (e) e.preventDefault();
      const notifBtn = document.getElementById('notifBellBtn');
      if (notifBtn) notifBtn.click();
    };
    window.openCmdPalette = function(e) {
      if (e) e.preventDefault();
      const cmdBtn = document.getElementById('cmdPaletteTriggerBtn');
      if (cmdBtn) cmdBtn.click();
    };

    // 17. Dashboard Category Filter Dropdown
    const filterBtn = document.getElementById('filterBtn');
    const filterMenu = document.querySelector('.filter-menu');
    if (filterBtn && filterMenu) {
      filterBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        filterMenu.classList.toggle('active');
      });
      document.addEventListener('click', (e) => {
        if (!filterMenu.contains(e.target) && !filterBtn.contains(e.target)) {
          filterMenu.classList.remove('active');
        }
      });
      const filterOptions = filterMenu.querySelectorAll('button');
      filterOptions.forEach(btn => {
        btn.addEventListener('click', (e) => {
          e.stopPropagation();
          filterOptions.forEach(b => b.classList.remove('sel'));
          btn.classList.add('sel');
          filterMenu.classList.remove('active');
          const cat = btn.getAttribute('data-cat') || 'all';
          const courseCards = document.querySelectorAll('.course-card');
          courseCards.forEach(c => {
            if (cat === 'all') {
              c.style.display = 'flex';
            } else {
              const cardCat = c.querySelector('.cat-tag')?.textContent.trim() || '';
              c.style.display = (cardCat.toLowerCase().includes(cat.toLowerCase())) ? 'flex' : 'none';
            }
          });
          showToast(`Filtered by ${btn.textContent.trim()}`);
        });
      });
    }

    // 18. Dashboard Kebab Menus
    const kebabs = document.querySelectorAll('.kebab');
    kebabs.forEach((kebab, idx) => {
      kebab.addEventListener('click', (e) => {
        e.stopPropagation();
        document.querySelectorAll('.kebab-menu-dropdown').forEach(m => m.remove());

        const menu = document.createElement('div');
        menu.className = 'kebab-menu-dropdown';
        menu.style.cssText = 'position:absolute; right:0; top:30px; background:var(--panel-bg); border:1px solid var(--border); border-radius:8px; box-shadow:0 8px 24px rgba(0,0,0,0.15); z-index:999; width:160px; overflow:hidden; animation:modalZoomIn 0.15s ease-out;';

        let itemsHtml = '';
        if (idx === 0) { // Weekly streak
          itemsHtml = `
            <div class="kebab-menu-item" onclick="handleStreakReset()">↺ Reset Week</div>
            <div class="kebab-menu-item" onclick="window.location.href='goals.php'">🎯 Set 7-Day Goal</div>
            <div class="kebab-menu-item" onclick="window.location.href='api/export.php?type=tasks'">📥 Export Streak</div>
          `;
        } else if (idx === 1) { // Learning progress
          itemsHtml = `
            <div class="kebab-menu-item" onclick="window.location.href='courses.php'">📚 View All Courses</div>
            <div class="kebab-menu-item" onclick="handleSortCoursesProgress()">📊 Sort by Progress</div>
            <div class="kebab-menu-item" onclick="window.location.href='api/export.php?type=tasks'">📥 Export CSV</div>
          `;
        } else { // Skills breakdown
          itemsHtml = `
            <div class="kebab-menu-item" onclick="window.location.href='goals.php'">🎯 View Objectives</div>
            <div class="kebab-menu-item" onclick="window.location.href='workload.php'">👥 View Workload</div>
            <div class="kebab-menu-item" onclick="window.location.href='reports.php'">📑 View Reports</div>
          `;
        }
        menu.innerHTML = itemsHtml;
        kebab.style.position = 'relative';
        kebab.appendChild(menu);

        document.addEventListener('click', function closeKebab(ev) {
          if (!menu.contains(ev.target)) {
            menu.remove();
            document.removeEventListener('click', closeKebab);
          }
        });
      });
    });

    // 19. Stat Cards View Links
    const viewLinks = document.querySelectorAll('.view-link');
    viewLinks.forEach((link, idx) => {
      link.addEventListener('click', (e) => {
        e.preventDefault();
        if (idx === 0) {
          window.location.href = 'courses.php';
        } else {
          const heatmap = document.getElementById('heatmap');
          if (heatmap) {
            heatmap.scrollIntoView({ behavior: 'smooth', block: 'center' });
            showToast('Showing full learning timeline');
          }
        }
      });
    });
  }

  window.handleStreakReset = async function() {
    try {
      const res = await secureFetch('api/toggle_streak.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'reset' })
      });
      showToast('Weekly streak progress reset');
      setTimeout(() => location.reload(), 400);
    } catch (err) { console.error(err); }
  };

  window.handleSortCoursesProgress = function() {
    const list = document.getElementById('lessonList');
    if (!list) return;
    const items = Array.from(list.children);
    items.sort((a, b) => {
      const pA = parseInt(a.querySelector('.lesson-pct')?.textContent) || 0;
      const pB = parseInt(b.querySelector('.lesson-pct')?.textContent) || 0;
      return pB - pA;
    });
    items.forEach(it => list.appendChild(it));
    showToast('Sorted courses by highest progress');
  };

  // Dark / Light Theme Engine
  function applyTheme(theme) {
    if (theme === 'dark') {
      document.documentElement.setAttribute('data-theme', 'dark');
      const sun = document.querySelector('.sun-icon');
      const moon = document.querySelector('.moon-icon');
      if (sun && moon) { sun.style.display = 'none'; moon.style.display = 'block'; }
    } else {
      document.documentElement.removeAttribute('data-theme');
      const sun = document.querySelector('.sun-icon');
      const moon = document.querySelector('.moon-icon');
      if (sun && moon) { sun.style.display = 'block'; moon.style.display = 'none'; }
    }
    try {
      localStorage.setItem('mindrift_theme', theme);
      document.cookie = "mindrift_theme=" + theme + "; path=/; max-age=31536000; SameSite=Lax";
    } catch(e) {}
  }
  window.applyTheme = applyTheme;

  function initTheme() {
    const cookieMatch = document.cookie.match(/(?:^|;\s*)mindrift_theme=([^;]+)/);
    const cookieTheme = cookieMatch ? cookieMatch[1] : null;
    const savedTheme = localStorage.getItem('mindrift_theme') || cookieTheme;
    const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    const currentTheme = savedTheme || (prefersDark ? 'dark' : 'light');
    applyTheme(currentTheme);

    const themeToggleBtn = document.getElementById('themeToggleBtn');
    if (themeToggleBtn) {
      themeToggleBtn.addEventListener('click', () => {
        const activeTheme = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        applyTheme(activeTheme);
        showToast(`Theme switched to ${activeTheme} mode`);
      });
    }
  }

  // 12. Productivity Copilot Controller
  function initCopilot() {
    const copilotDrawer = document.getElementById('copilotDrawer');
    const btnCloseCopilot = document.getElementById('btnCloseCopilot');
    const floatingChatBtns = document.querySelectorAll('.floating-chat-btn');
    const copilotTabs = document.querySelectorAll('.copilot-tab');
    const tabGenerator = document.getElementById('copilotTabGenerator');
    const tabCoaching = document.getElementById('copilotTabCoaching');
    const breakdownForm = document.getElementById('copilotBreakdownForm');
    const goalInput = document.getElementById('copilotGoalInput');
    const resultsArea = document.getElementById('copilotResultsArea');
    const taskList = document.getElementById('copilotTaskList');
    const btnAddTasks = document.getElementById('btnAddCopilotTasks');

    let currentGeneratedTasks = [];

    floatingChatBtns.forEach(btn => {
      btn.removeAttribute('onclick');
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        if (copilotDrawer) {
          copilotDrawer.classList.toggle('active');
          if (copilotDrawer.classList.contains('active') && goalInput) {
            setTimeout(() => goalInput.focus(), 150);
          }
        }
      });
    });

    if (btnCloseCopilot && copilotDrawer) {
      btnCloseCopilot.addEventListener('click', () => {
        copilotDrawer.classList.remove('active');
      });
    }

    if (copilotTabs) {
      copilotTabs.forEach(tab => {
        tab.addEventListener('click', () => {
          copilotTabs.forEach(t => t.classList.remove('active'));
          tab.classList.add('active');
          const target = tab.dataset.copilotTab;
          if (target === 'generator') {
            if (tabGenerator) tabGenerator.style.display = 'block';
            if (tabCoaching) tabCoaching.style.display = 'none';
          } else {
            if (tabGenerator) tabGenerator.style.display = 'none';
            if (tabCoaching) tabCoaching.style.display = 'block';
          }
        });
      });
    }

    if (breakdownForm && goalInput) {
      breakdownForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const goal = goalInput.value.trim();
        if (!goal) return;

        const btnGen = document.getElementById('btnCopilotGenerate');
        if (btnGen) { btnGen.disabled = true; btnGen.textContent = 'Analyzing & Generating...'; }

        try {
          const res = await secureFetch('api/copilot.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'breakdown', goal })
          });
          const data = await res.json();
          if (data.success && data.subtasks) {
            currentGeneratedTasks = data.subtasks;
            if (taskList) {
              taskList.innerHTML = data.subtasks.map(t => `
                <div class="copilot-task-item">
                  <div>
                    <div style="font-weight:600; color:var(--ink);">${escapeHtml(t.task_name)}</div>
                    <div style="font-size:11px; color:var(--muted); margin-top:2px;">Due: ${escapeHtml(t.due_date)} • Est: ${t.time_log}h</div>
                  </div>
                  <span class="priority-badge priority-${t.priority.toLowerCase()}">${escapeHtml(t.priority)}</span>
                </div>
              `).join('');
            }
            if (resultsArea) resultsArea.style.display = 'block';
            showToast('Goal broken down into subtasks!');
          }
        } catch (err) {
          console.error(err);
        } finally {
          if (btnGen) { btnGen.disabled = false; btnGen.textContent = '⚡ Break Down Goal'; }
        }
      });
    }

    if (btnAddTasks) {
      btnAddTasks.addEventListener('click', async () => {
        if (!currentGeneratedTasks.length) return;
        btnAddTasks.disabled = true;
        btnAddTasks.textContent = 'Adding...';

        try {
          const res = await secureFetch('api/copilot.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'add_subtasks', tasks: currentGeneratedTasks, project_name: goalInput.value.trim() })
          });
          const data = await res.json();
          if (data.success) {
            showToast(data.message);
            if (copilotDrawer) copilotDrawer.classList.remove('active');
            if (window.location.pathname.includes('assignments') || window.location.pathname.includes('kanban')) {
              setTimeout(() => location.reload(), 600);
            }
          }
        } catch (err) {
          console.error(err);
        } finally {
          btnAddTasks.disabled = false;
          btnAddTasks.textContent = '+ Add All to Board';
        }
      });
    }
  // ==========================================
  // COMMAND PALETTE (CMD+K / CTRL+K) CONTROLLER
  // ==========================================
  function initCommandPalette() {
    const modal = document.getElementById('cmdPaletteModal');
    const input = document.getElementById('cmdPaletteInput');
    const triggerBtn = document.getElementById('cmdPaletteTriggerBtn');
    const resultsContainer = document.getElementById('cmdPaletteResults');
    if (!modal || !input) return;

    function openPalette() {
      modal.classList.add('active');
      input.value = '';
      filterCommands('');
      setTimeout(() => input.focus(), 50);
    }

    function closePalette() {
      modal.classList.remove('active');
    }

    if (triggerBtn) {
      triggerBtn.addEventListener('click', (e) => {
        e.preventDefault();
        openPalette();
      });
    }

    modal.addEventListener('click', (e) => {
      if (e.target === modal) closePalette();
    });

    document.addEventListener('keydown', (e) => {
      if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        if (modal.classList.contains('active')) {
          closePalette();
        } else {
          openPalette();
        }
      } else if (e.key === 'Escape' && modal.classList.contains('active')) {
        closePalette();
      }
    });

    function filterCommands(query) {
      const q = query.toLowerCase().trim();
      const items = resultsContainer.querySelectorAll('.cmd-item');
      items.forEach(item => {
        const text = item.textContent.toLowerCase();
        item.style.display = (!q || text.includes(q)) ? 'flex' : 'none';
      });
    }

    input.addEventListener('input', (e) => {
      filterCommands(e.target.value);
    });

    window.executeCmdAction = function(action) {
      closePalette();
      if (action === 'quick_task') {
        if (typeof window.openQuickTaskModal === 'function') {
          window.openQuickTaskModal();
        }
      } else if (action === 'pomodoro') {
        const pBtn = document.getElementById('btnToggleTimer') || document.getElementById('startTimerBtn');
        if (pBtn) {
          pBtn.click();
          showToast('🍅 Pomodoro Focus session started!');
        } else {
          window.location.href = 'index.php';
        }
      } else if (action === 'copilot') {
        const drawer = document.getElementById('copilotDrawer');
        if (drawer) {
          drawer.classList.add('active');
          const goalIn = document.getElementById('copilotGoalInput');
          if (goalIn) goalIn.focus();
        }
      } else if (action === 'theme') {
        const tBtn = document.getElementById('themeToggleBtn');
        if (tBtn) tBtn.click();
      } else if (action === 'export_tasks') {
        window.location.href = 'api/export.php?type=tasks';
      } else if (action === 'calendar_ics') {
        window.location.href = 'api/calendar_export.php';
      }
    };
  }

  // ==========================================
  // IN-APP NOTIFICATIONS CONTROLLER
  // ==========================================
  async function initNotifications() {
    const bellBtn = document.getElementById('notifBellBtn');
    const badge = document.getElementById('notifBadge');
    const dropdown = document.getElementById('notifDropdown');
    const list = document.getElementById('notifList');
    if (!bellBtn || !dropdown || !list) return;

    const toggleDropdown = (forceState) => {
      const isVisible = forceState !== undefined ? !forceState : (dropdown.style.display === 'block');
      if (isVisible) {
        dropdown.style.display = 'none';
        dropdown.classList.remove('active');
      } else {
        dropdown.style.display = 'block';
        dropdown.classList.add('active');
      }
    };

    bellBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      toggleDropdown();
    });

    document.addEventListener('click', (e) => {
      if (!dropdown.contains(e.target) && !bellBtn.contains(e.target)) {
        toggleDropdown(false);
      }
    });

    window.toggleNotifDropdown = function() {
      toggleDropdown();
    };

    try {
      const res = await secureFetch('api/notifications.php');
      const data = await res.json();
      if (data.success) {
        if (data.unread_count > 0 && badge) {
          badge.textContent = data.unread_count;
          badge.style.display = 'inline-block';
        } else if (badge) {
          badge.style.display = 'none';
        }

        if (data.notifications && data.notifications.length > 0) {
          list.innerHTML = data.notifications.map(n => `
            <div class="notif-item ${n.is_read ? '' : 'unread'}" onclick="handleNotifClick(${n.id})">
              <div style="flex:1;">
                <div class="notif-item-title">${n.title}</div>
                <div class="notif-item-desc">${n.message}</div>
              </div>
              ${n.is_read ? '' : '<span style="width:7px; height:7px; background:#6C5CE7; border-radius:50%; margin-top:4px; flex-shrink:0;"></span>'}
            </div>
          `).join('');
        } else {
          list.innerHTML = '<div style="text-align:center; padding:20px; color:var(--muted); font-size:12px;">🎉 All caught up! No active alerts.</div>';
        }
      }
    } catch (err) {
      console.error('Failed to load notifications:', err);
    }

    window.markAllNotificationsRead = async function() {
      try {
        const res = await secureFetch('api/notifications.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'mark_all_read' })
        });
        const data = await res.json();
        if (data.success) {
          if (badge) badge.style.display = 'none';
          document.querySelectorAll('.notif-item.unread').forEach(el => el.classList.remove('unread'));
          showToast('All notifications marked as read');
        }
      } catch (err) {
        console.error(err);
      }
    };

    window.handleNotifClick = async function(id) {
      try {
        await secureFetch('api/notifications.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'mark_read', id: id })
        });
        window.location.href = 'calendar.php';
      } catch (err) {
        console.error(err);
      }
    };
  }

  // ==========================================
  // OMNI-BAR QUICK-ADD TASK CONTROLLER (NLP)
  // ==========================================
  function initOmniQuickTask() {
    const modal = document.getElementById('quickTaskModal');
    if (!modal) return;

    const triggerBtn = document.getElementById('quickTaskTriggerBtn');
    const closeBtn = document.getElementById('btnCloseQuickTask');
    const cancelBtn = document.getElementById('btnCancelQuickTask');
    const submitBtn = document.getElementById('btnSubmitQuickTask');
    const input = document.getElementById('omniTaskInput');
    const clearBtn = document.getElementById('btnOmniClear');
    const projectSelect = document.getElementById('omniSelectProject');
    const prioritySelect = document.getElementById('omniSelectPriority');
    const assigneeInput = document.getElementById('omniInputAssignee');
    const dueDateInput = document.getElementById('omniInputDueDate');

    const chipTitleVal = document.getElementById('chipTitleVal');
    const chipProjectVal = document.getElementById('chipProjectVal');
    const chipPriority = document.getElementById('chipPriority');
    const chipPriorityVal = document.getElementById('chipPriorityVal');
    const chipAssigneeVal = document.getElementById('chipAssigneeVal');
    const chipDueDate = document.getElementById('chipDueDate');
    const chipDueDateVal = document.getElementById('chipDueDateVal');

    function playSuccessChime() {
      try {
        const AudioContext = window.AudioContext || window.webkitAudioContext;
        if (!AudioContext) return;
        const ctx = new AudioContext();
        const now = ctx.currentTime;
        const osc1 = ctx.createOscillator();
        const osc2 = ctx.createOscillator();
        const gain = ctx.createGain();

        osc1.type = 'sine';
        osc1.frequency.setValueAtTime(587.33, now); // D5
        osc1.frequency.exponentialRampToValueAtTime(880, now + 0.12); // A5

        osc2.type = 'triangle';
        osc2.frequency.setValueAtTime(880, now + 0.08);

        gain.gain.setValueAtTime(0.06, now);
        gain.gain.exponentialRampToValueAtTime(0.001, now + 0.32);

        osc1.connect(gain);
        osc2.connect(gain);
        gain.connect(ctx.destination);

        osc1.start(now);
        osc2.start(now + 0.08);
        osc1.stop(now + 0.32);
        osc2.stop(now + 0.32);
      } catch (e) {}
    }

    function formatDdMmYyyy(dateObj) {
      const dd = String(dateObj.getDate()).padStart(2, '0');
      const mm = String(dateObj.getMonth() + 1).padStart(2, '0');
      const yyyy = dateObj.getFullYear();
      return `${dd}-${mm}-${yyyy}`;
    }

    function formatYyyyMmDd(dateObj) {
      const dd = String(dateObj.getDate()).padStart(2, '0');
      const mm = String(dateObj.getMonth() + 1).padStart(2, '0');
      const yyyy = dateObj.getFullYear();
      return `${yyyy}-${mm}-${dd}`;
    }

    function getUpcomingWeekday(dayName) {
      const days = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
      const targetIndex = days.indexOf(dayName.toLowerCase());
      if (targetIndex === -1) return null;
      const d = new Date();
      const currentDay = d.getDay();
      let diff = targetIndex - currentDay;
      if (diff <= 0) diff += 7;
      d.setDate(d.getDate() + diff);
      return d;
    }

    function matchProjectOption(queryStr) {
      if (!projectSelect || !queryStr) return null;
      const q = queryStr.toLowerCase().replace(/['"]/g, '').trim();
      const options = Array.from(projectSelect.options);
      for (const opt of options) {
        if (opt.value.toLowerCase() === q || opt.textContent.toLowerCase() === q) return opt.value;
      }
      for (const opt of options) {
        if (opt.value.toLowerCase().startsWith(q) || opt.textContent.toLowerCase().startsWith(q)) return opt.value;
      }
      for (const opt of options) {
        if (opt.value.toLowerCase().includes(q) || opt.textContent.toLowerCase().includes(q)) return opt.value;
      }
      return null;
    }

    function parseNLP(rawText) {
      let text = rawText || '';
      let priority = prioritySelect ? prioritySelect.value : 'Medium';
      let assignee = assigneeInput ? assigneeInput.value : 'Alex';
      let project = projectSelect ? projectSelect.value : '2dapp';
      let dueDateObj = null;
      let dueDisplay = 'In 3 days';

      // 1. Priority Detection
      if (/(?:^|\s)#(?:urgent|p1|crit(?:ical)?)(?:\b|$)/i.test(text)) {
        priority = 'Urgent';
        text = text.replace(/(?:^|\s)#(?:urgent|p1|crit(?:ical)?)(?:\b|$)/gi, ' ');
      } else if (/(?:^|\s)#(?:high|p2|important)(?:\b|$)/i.test(text)) {
        priority = 'High';
        text = text.replace(/(?:^|\s)#(?:high|p2|important)(?:\b|$)/gi, ' ');
      } else if (/(?:^|\s)#(?:med(?:ium)?|p3|normal)(?:\b|$)/i.test(text)) {
        priority = 'Medium';
        text = text.replace(/(?:^|\s)#(?:med(?:ium)?|p3|normal)(?:\b|$)/gi, ' ');
      } else if (/(?:^|\s)#(?:low|p4|minor)(?:\b|$)/i.test(text)) {
        priority = 'Low';
        text = text.replace(/(?:^|\s)#(?:low|p4|minor)(?:\b|$)/gi, ' ');
      }

      // 2. Assignee Detection (@username)
      const assigneeMatch = text.match(/(?:^|\s)@([a-zA-Z0-9_\.\-]+)/i);
      if (assigneeMatch) {
        assignee = assigneeMatch[1];
        text = text.replace(assigneeMatch[0], ' ');
      }

      // 3. Project Detection (^Project or in "Project")
      const projCaretMatch = text.match(/(?:^|\s)\^([a-zA-Z0-9_\.\-\s]+?)(?=\s+[#@^]|due|\bin\b|$)/i);
      const projInMatch = text.match(/(?:^|\s)in\s+"([^"]+)"/i) || text.match(/(?:^|\s)in\s+([a-zA-Z0-9_\-]+)/i);
      const projMatch = projCaretMatch || projInMatch;
      if (projMatch) {
        const matchedVal = matchProjectOption(projMatch[1]);
        if (matchedVal) {
          project = matchedVal;
        }
        text = text.replace(projMatch[0], ' ');
      }

      // 4. Due Date Detection
      const today = new Date();
      if (/(?:^|\s)(?:due\s+)?today(?:\b|$)/i.test(text)) {
        dueDateObj = new Date(today);
        dueDisplay = 'Today (' + formatDdMmYyyy(dueDateObj) + ')';
        text = text.replace(/(?:^|\s)(?:due\s+)?today(?:\b|$)/gi, ' ');
      } else if (/(?:^|\s)(?:due\s+)?tomorrow(?:\b|$)/i.test(text)) {
        dueDateObj = new Date(today);
        dueDateObj.setDate(dueDateObj.getDate() + 1);
        dueDisplay = 'Tomorrow (' + formatDdMmYyyy(dueDateObj) + ')';
        text = text.replace(/(?:^|\s)(?:due\s+)?tomorrow(?:\b|$)/gi, ' ');
      } else if (/(?:^|\s)(?:due\s+)?next\s+week(?:\b|$)/i.test(text)) {
        dueDateObj = new Date(today);
        dueDateObj.setDate(dueDateObj.getDate() + 7);
        dueDisplay = 'Next Week (' + formatDdMmYyyy(dueDateObj) + ')';
        text = text.replace(/(?:^|\s)(?:due\s+)?next\s+week(?:\b|$)/gi, ' ');
      } else {
        const inDaysMatch = text.match(/(?:^|\s)in\s+(\d+)\s*(?:days?|d)(?:\b|$)/i);
        if (inDaysMatch) {
          const daysNum = parseInt(inDaysMatch[1], 10);
          dueDateObj = new Date(today);
          dueDateObj.setDate(dueDateObj.getDate() + daysNum);
          dueDisplay = `In ${daysNum} days (${formatDdMmYyyy(dueDateObj)})`;
          text = text.replace(inDaysMatch[0], ' ');
        } else {
          const weekdayMatch = text.match(/(?:^|\s)(?:due\s+)?(?:next\s+)?(monday|tuesday|wednesday|thursday|friday|saturday|sunday)(?:\b|$)/i);
          if (weekdayMatch) {
            const nextDay = getUpcomingWeekday(weekdayMatch[1]);
            if (nextDay) {
              dueDateObj = nextDay;
              dueDisplay = weekdayMatch[1].charAt(0).toUpperCase() + weekdayMatch[1].slice(1) + ' (' + formatDdMmYyyy(dueDateObj) + ')';
              text = text.replace(weekdayMatch[0], ' ');
            }
          } else {
            const dateRegex = text.match(/(?:^|\s)due\s+(\d{4}-\d{2}-\d{2})(?:\b|$)/i);
            if (dateRegex) {
              const dParts = dateRegex[1].split('-');
              dueDateObj = new Date(parseInt(dParts[0]), parseInt(dParts[1]) - 1, parseInt(dParts[2]));
              dueDisplay = formatDdMmYyyy(dueDateObj);
              text = text.replace(dateRegex[0], ' ');
            }
          }
        }
      }

      if (!dueDateObj) {
        dueDateObj = new Date(today);
        dueDateObj.setDate(dueDateObj.getDate() + 3);
        dueDisplay = 'In 3 days (' + formatDdMmYyyy(dueDateObj) + ')';
      }

      // Clean remaining title
      let title = text.replace(/\s+/g, ' ').trim();
      if (!title) {
        title = rawText.trim() || 'New Task';
      }

      return {
        title,
        priority,
        assignee,
        project,
        dueDateObj,
        dueDateFormatted: formatDdMmYyyy(dueDateObj),
        dueDateIso: formatYyyyMmDd(dueDateObj),
        dueDisplay
      };
    }

    function updatePreview() {
      const raw = input.value;
      if (clearBtn) clearBtn.style.display = raw.length > 0 ? 'inline-block' : 'none';

      const parsed = parseNLP(raw);

      if (chipTitleVal) {
        chipTitleVal.textContent = raw.trim().length > 0 ? parsed.title : 'Type a task above...';
      }
      if (chipProjectVal) {
        chipProjectVal.textContent = parsed.project;
      }
      if (chipPriorityVal && chipPriority) {
        chipPriorityVal.textContent = parsed.priority;
        chipPriority.className = 'omni-chip chip-priority priority-' + parsed.priority.toLowerCase();
      }
      if (chipAssigneeVal) {
        chipAssigneeVal.textContent = parsed.assignee;
      }
      if (chipDueDateVal) {
        chipDueDateVal.textContent = parsed.dueDisplay;
      }

      // Sync with adjuster inputs if user hasn't explicitly overridden them while typing
      if (projectSelect && projectSelect.value !== parsed.project) {
        const opt = Array.from(projectSelect.options).find(o => o.value === parsed.project);
        if (opt) projectSelect.value = parsed.project;
      }
      if (prioritySelect && prioritySelect.value !== parsed.priority) {
        prioritySelect.value = parsed.priority;
      }
      if (assigneeInput && document.activeElement !== assigneeInput && parsed.assignee) {
        assigneeInput.value = parsed.assignee;
      }
      if (dueDateInput && document.activeElement !== dueDateInput && parsed.dueDateIso) {
        dueDateInput.value = parsed.dueDateIso;
      }
    }

    window.openQuickTaskModal = function() {
      modal.style.display = 'flex';
      modal.classList.add('active');
      input.value = '';
      if (clearBtn) clearBtn.style.display = 'none';
      updatePreview();
      setTimeout(() => input.focus(), 60);
    };

    window.closeQuickTaskModal = function() {
      modal.classList.remove('active');
      modal.style.display = 'none';
      input.value = '';
      if (clearBtn) clearBtn.style.display = 'none';
    };

    if (triggerBtn) {
      triggerBtn.addEventListener('click', (e) => {
        e.preventDefault();
        openQuickTaskModal();
      });
    }

    if (closeBtn) closeBtn.addEventListener('click', closeQuickTaskModal);
    if (cancelBtn) cancelBtn.addEventListener('click', closeQuickTaskModal);

    modal.addEventListener('click', (e) => {
      if (e.target === modal) closeQuickTaskModal();
    });

    if (clearBtn) {
      clearBtn.addEventListener('click', () => {
        input.value = '';
        input.focus();
        updatePreview();
      });
    }

    // Live NLP input listener
    input.addEventListener('input', updatePreview);

    // Adjusters direct change listener
    if (projectSelect) {
      projectSelect.addEventListener('change', () => {
        if (chipProjectVal) chipProjectVal.textContent = projectSelect.value;
      });
    }
    if (prioritySelect) {
      prioritySelect.addEventListener('change', () => {
        const p = prioritySelect.value;
        if (chipPriorityVal && chipPriority) {
          chipPriorityVal.textContent = p;
          chipPriority.className = 'omni-chip chip-priority priority-' + p.toLowerCase();
        }
      });
    }
    if (assigneeInput) {
      assigneeInput.addEventListener('input', () => {
        if (chipAssigneeVal) chipAssigneeVal.textContent = assigneeInput.value || 'Unassigned';
      });
    }
    if (dueDateInput) {
      dueDateInput.addEventListener('change', () => {
        if (dueDateInput.value && chipDueDateVal) {
          const parts = dueDateInput.value.split('-');
          if (parts.length === 3) {
            chipDueDateVal.textContent = `${parts[2]}-${parts[1]}-${parts[0]}`;
          }
        }
      });
    }

    // Syntax quick tags click helper
    document.querySelectorAll('.syntax-tag').forEach(tag => {
      tag.addEventListener('click', () => {
        const val = tag.getAttribute('data-tag');
        if (!val) return;
        if (input.value && !input.value.endsWith(' ')) {
          input.value += ' ' + val;
        } else {
          input.value += val;
        }
        input.focus();
        updatePreview();
      });
    });

    // Global keyboard shortcut
    document.addEventListener('keydown', (e) => {
      const activeEl = document.activeElement;
      const isInput = activeEl && (
        activeEl.tagName === 'INPUT' ||
        activeEl.tagName === 'TEXTAREA' ||
        activeEl.tagName === 'SELECT' ||
        activeEl.isContentEditable
      );

      // 'N' shortcut opens modal if not actively typing in an input
      if ((e.key === 'n' || e.key === 'N') && !e.ctrlKey && !e.metaKey && !e.altKey && !isInput) {
        e.preventDefault();
        if (modal.classList.contains('active')) {
          closeQuickTaskModal();
        } else {
          openQuickTaskModal();
        }
      } else if (e.key === 'Escape' && modal.classList.contains('active')) {
        closeQuickTaskModal();
      }
    });

    // Enter to submit
    input.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        submitQuickTask();
      }
    });

    if (submitBtn) {
      submitBtn.addEventListener('click', (e) => {
        e.preventDefault();
        submitQuickTask();
      });
    }

    async function submitQuickTask() {
      const raw = input.value.trim();
      const parsed = parseNLP(raw);

      if (!parsed.title || parsed.title === 'Type a task above...') {
        showToast('Please enter a task name');
        input.focus();
        return;
      }

      const originalBtnHtml = submitBtn ? submitBtn.innerHTML : 'Create Task';
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span>Creating...</span>';
      }

      const dueFinal = dueDateInput && dueDateInput.value
        ? (() => {
            const p = dueDateInput.value.split('-');
            return `${p[2]}-${p[1]}-${p[0]}`;
          })()
        : parsed.dueDateFormatted;

      const payload = {
        action: 'create',
        task_name: parsed.title,
        project_name: (projectSelect ? projectSelect.value : parsed.project) || '2dapp',
        start_date: formatDdMmYyyy(new Date()),
        due_date: dueFinal,
        priority: prioritySelect ? prioritySelect.value : parsed.priority,
        assigned_to: (assigneeInput ? assigneeInput.value : parsed.assignee) || 'Alex',
        status: 'Open'
      };

      try {
        const res = await secureFetch('api/tasks.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        const data = await res.json();

        if (data.success) {
          playSuccessChime();
          showToast(`✨ Task "${parsed.title}" created successfully!`);
          closeQuickTaskModal();

          // If currently on tasks, kanban, calendar, or gantt, smoothly reload after a short delay
          const currentPath = window.location.pathname.toLowerCase();
          if (
            currentPath.includes('assignments') ||
            currentPath.includes('kanban') ||
            currentPath.includes('calendar') ||
            currentPath.includes('gantt')
          ) {
            setTimeout(() => location.reload(), 450);
          }
        } else {
          alert(data.message || 'Error creating task');
        }
      } catch (err) {
        console.error('Quick task error:', err);
        showToast('Error connecting to task service');
      } finally {
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.innerHTML = originalBtnHtml;
        }
      }
    }
  }

  // Execute on DOM Ready
  document.addEventListener('DOMContentLoaded', () => {
    initTheme();
    initCopilot();
    initCommandPalette();
    initNotifications();
    initOmniQuickTask();
    initEventListeners();
    loadDashboardData();
  });

})();

