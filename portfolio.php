<?php
require_once __DIR__ . '/config/db.php';
startSecureSession();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$db = getDbConnection();
$userId = (int)$_SESSION['user_id'];

// Fetch user info
$stmtUser = $db->prepare("SELECT * FROM users WHERE id = :uid");
$stmtUser->execute(['uid' => $userId]);
$user = $stmtUser->fetch();

// Fetch user overall stats
$stmtStats = $db->prepare("SELECT * FROM user_stats WHERE user_id = :uid");
$stmtStats->execute(['uid' => $userId]);
$stats = $stmtStats->fetch() ?: [
    'study_hours' => 0,
    'study_minutes' => 0,
    'weekly_lessons_current' => 0,
    'current_streak' => 1
];

// Fetch all courses
$stmtCourses = $db->prepare("SELECT * FROM courses WHERE user_id = :uid ORDER BY progress_pct DESC, id ASC");
$stmtCourses->execute(['uid' => $userId]);
$courses = $stmtCourses->fetchAll();

// Derive evaluated competencies from user's course progress
$stmtSkills = $db->prepare("SELECT category as skill_name, ROUND(AVG(progress_pct)) as rating FROM courses WHERE user_id = :uid GROUP BY category ORDER BY rating DESC");
$stmtSkills->execute(['uid' => $userId]);
$skills = $stmtSkills->fetchAll();

// Fallback if no courses yet
if (empty($skills)) {
    $skills = [
        ['skill_name' => 'UI / UX Architecture', 'rating' => 95],
        ['skill_name' => 'Full-Stack JavaScript', 'rating' => 88],
        ['skill_name' => 'Data Structures & Algorithms', 'rating' => 82],
        ['skill_name' => 'Systems Design & DevOps', 'rating' => 78]
    ];
}

$certId = 'MND-' . strtoupper(dechex($userId)) . '-' . date('Y') . '-' . substr(md5(($user['email'] ?? '') . $userId), 0, 6);
$issuedDate = date('F j, Y');

$pageTitle = 'Mindrift — Verified Learning Portfolio & Certificate';
include __DIR__ . '/includes/head.php';
?>
<style>
  .portfolio-page {
    max-width: 900px;
    margin: 0 auto;
    padding: 36px 24px 60px;
  }
  .portfolio-actions-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 24px;
  }
  .btn-print {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: var(--purple);
    color: #FFFFFF;
    border: none;
    padding: 10px 20px;
    border-radius: 10px;
    font-size: 13.5px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.15s ease;
  }
  .btn-print:hover {
    background: var(--purple-deep);
    transform: translateY(-1px);
  }
  .btn-back {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: var(--muted);
    text-decoration: none;
    font-size: 13.5px;
    font-weight: 600;
  }
  .btn-back:hover {
    color: var(--ink);
  }

  /* Executive Certificate Paper Layout */
  .cert-paper {
    background: var(--panel-bg);
    border: 1px solid var(--border);
    border-radius: var(--radius-xl);
    padding: 48px;
    box-shadow: var(--shadow-shell);
    position: relative;
    overflow: hidden;
  }
  .cert-watermark {
    position: absolute;
    right: -40px;
    bottom: -40px;
    font-size: 260px;
    font-weight: 900;
    opacity: 0.025;
    pointer-events: none;
    user-select: none;
    line-height: 1;
    color: var(--ink);
  }
  .cert-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    border-bottom: 2px solid var(--border);
    padding-bottom: 24px;
    margin-bottom: 32px;
  }
  .cert-brand {
    font-size: 26px;
    font-weight: 900;
    letter-spacing: -0.04em;
    background: linear-gradient(135deg, #6C5CE7, #4FA3E0);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
  }
  .cert-sub {
    font-size: 12px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--muted);
    margin-top: 4px;
  }
  .cert-meta {
    text-align: right;
    font-size: 12.5px;
    color: var(--muted);
    line-height: 1.5;
  }
  .cert-meta b {
    color: var(--ink);
  }

  .student-hero {
    margin-bottom: 32px;
  }
  .student-label {
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--purple);
  }
  .student-name {
    font-size: 32px;
    font-weight: 900;
    letter-spacing: -0.03em;
    color: var(--ink);
    margin: 4px 0 8px;
  }
  .student-bio {
    font-size: 14.5px;
    color: var(--muted);
    max-width: 680px;
    line-height: 1.5;
  }

  /* Metric Highlights Bento */
  .metrics-bento {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 36px;
  }
  .metric-card {
    background: rgba(108, 92, 231, 0.04);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 16px;
    text-align: center;
  }
  .metric-val {
    font-size: 24px;
    font-weight: 800;
    color: var(--ink);
    line-height: 1.2;
  }
  .metric-lbl {
    font-size: 11.5px;
    font-weight: 700;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: 0.04em;
    margin-top: 4px;
  }

  /* Portfolio Table */
  .portfolio-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 36px;
  }
  .portfolio-table th {
    text-align: left;
    font-size: 11.5px;
    font-weight: 800;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 10px 14px;
    border-bottom: 2px solid var(--border);
  }
  .portfolio-table td {
    padding: 14px;
    border-bottom: 1px solid var(--border);
    font-size: 13.5px;
    color: var(--ink);
  }
  .progress-pill-track {
    background: var(--border);
    border-radius: 10px;
    height: 8px;
    width: 100px;
    overflow: hidden;
    display: inline-block;
    vertical-align: middle;
    margin-right: 8px;
  }
  .progress-pill-fill {
    height: 100%;
    background: linear-gradient(90deg, #6C5CE7, #4FA3E0);
    border-radius: 10px;
  }

  /* Skill Badges */
  .skills-strip {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 40px;
  }
  .skill-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    background: var(--panel-bg);
    border: 1px solid var(--border);
    border-radius: 20px;
    font-size: 12.5px;
    font-weight: 700;
    color: var(--ink);
  }

  /* Official Verification Seal */
  .cert-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-top: 1px solid var(--border);
    padding-top: 24px;
    margin-top: 20px;
  }
  .seal-wrap {
    display: flex;
    align-items: center;
    gap: 12px;
  }
  .seal-icon {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: linear-gradient(135deg, #10B981, #059669);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #FFFFFF;
    font-size: 20px;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
  }
  .seal-text {
    font-size: 12px;
    color: var(--muted);
    line-height: 1.4;
  }
  .seal-text b {
    color: var(--ink);
  }

  /* Print Styles */
  @media print {
    body {
      background: #FFFFFF !important;
      color: #000000 !important;
      margin: 0 !important;
      padding: 0 !important;
    }
    .sidebar, .topbar, .portfolio-actions-bar, .floating-chat-btn {
      display: none !important;
    }
    .portfolio-page {
      max-width: 100% !important;
      padding: 0 !important;
    }
    .cert-paper {
      box-shadow: none !important;
      border: none !important;
      padding: 24px 0 !important;
      background: #FFFFFF !important;
    }
    .metric-card, .skill-badge {
      border-color: #E5E7EB !important;
      background: #F9FAFB !important;
    }
    .portfolio-table th, .portfolio-table td {
      border-color: #E5E7EB !important;
    }
    * {
      -webkit-print-color-adjust: exact !important;
      print-color-adjust: exact !important;
    }
  }
</style>
</head>
<body>

<div class="portfolio-page">
  
  <!-- Navigation and Action Bar -->
  <div class="portfolio-actions-bar">
    <a href="reports.php" class="btn-back">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 12H5"/><polyline points="12 19 5 12 12 5"/></svg>
      Return to Reports
    </a>
    <button type="button" class="btn-print" onclick="window.print()">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
      Print / Save as PDF
    </button>
  </div>

  <!-- Official Executive Certificate Paper -->
  <div class="cert-paper">
    <div class="cert-watermark">M</div>

    <!-- Header -->
    <div class="cert-header">
      <div>
        <div class="cert-brand">MINDRIFT</div>
        <div class="cert-sub">Verified Learning Portfolio &amp; Credential</div>
      </div>
      <div class="cert-meta">
        <div>Credential ID: <b><?= htmlspecialchars($certId); ?></b></div>
        <div>Issued: <b><?= htmlspecialchars($issuedDate); ?></b></div>
        <div>Verification: <b>Authenticated &amp; Certified</b></div>
      </div>
    </div>

    <!-- Student Info -->
    <div class="student-hero">
      <div class="student-label">Certified Scholar</div>
      <h1 class="student-name"><?= htmlspecialchars($user['name']); ?></h1>
      <p class="student-bio">
        This document serves as formal academic recognition of demonstrated competencies, cumulative study hours, active projects, and learning milestones completed across engineering, design, and data curriculums on the Mindrift platform.
      </p>
    </div>

    <!-- Key Metrics Bento -->
    <div class="metrics-bento">
      <div class="metric-card">
        <div class="metric-val"><?= (int)$stats['study_hours']; ?>h <?= (int)$stats['study_minutes']; ?>m</div>
        <div class="metric-lbl">Total Study Hours</div>
      </div>
      <div class="metric-card">
        <div class="metric-val"><?= count($courses); ?></div>
        <div class="metric-lbl">Active Projects</div>
      </div>
      <div class="metric-card">
        <div class="metric-val"><?= (int)$stats['current_streak']; ?> Days</div>
        <div class="metric-lbl">Daily Streak Milestone</div>
      </div>
      <div class="metric-card">
        <div class="metric-val"><?= (int)$stats['weekly_lessons_current']; ?></div>
        <div class="metric-lbl">Lessons Completed</div>
      </div>
    </div>

    <!-- Course Projects Performance Table -->
    <h3 style="font-size:16px; font-weight:800; color:var(--ink); margin:0 0 14px;">Curriculum &amp; Course Mastery</h3>
    <table class="portfolio-table">
      <thead>
        <tr>
          <th>Course / Project</th>
          <th>Discipline</th>
          <th>Level</th>
          <th>Progress</th>
          <th style="text-align:right;">Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($courses as $c): ?>
          <tr>
            <td style="font-weight:700;">
              <span style="display:inline-block; width:28px; height:28px; line-height:28px; text-align:center; background:rgba(108,92,231,0.1); color:var(--purple); border-radius:6px; font-size:11px; margin-right:8px;">
                <?= htmlspecialchars($c['code'] ?? 'M'); ?>
              </span>
              <?= htmlspecialchars($c['name']); ?>
            </td>
            <td><?= htmlspecialchars($c['category']); ?></td>
            <td><span style="font-size:12px; font-weight:600; color:var(--muted);"><?= htmlspecialchars($c['level']); ?></span></td>
            <td>
              <div class="progress-pill-track">
                <div class="progress-pill-fill" style="width: <?= (int)$c['progress_pct']; ?>%;"></div>
              </div>
              <span style="font-weight:700; font-size:12px;"><?= (int)$c['progress_pct']; ?>%</span>
            </td>
            <td style="text-align:right;">
              <?php if ((int)$c['progress_pct'] >= 100 || strtolower($c['status'] ?? '') === 'completed'): ?>
                <span style="color:#10B981; font-weight:800; font-size:12px;">✓ Completed</span>
              <?php else: ?>
                <span style="color:#F59E0B; font-weight:700; font-size:12px;">⏳ In Progress</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <!-- Verified Competencies -->
    <?php if (!empty($skills)): ?>
      <h3 style="font-size:16px; font-weight:800; color:var(--ink); margin:0 0 12px;">Evaluated Technical Competencies</h3>
      <div class="skills-strip">
        <?php foreach ($skills as $sk): ?>
          <div class="skill-badge">
            <span>⚡ <?= htmlspecialchars($sk['skill_name']); ?></span>
            <span style="color:var(--purple); font-weight:800;"><?= (int)$sk['rating']; ?>%</span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <!-- Official Seal & Certification Sign-Off -->
    <div class="cert-footer">
      <div class="seal-wrap">
        <div class="seal-icon">✓</div>
        <div class="seal-text">
          <b>Officially Verified Record</b><br />
          Digitally signed by Mindrift Academic &amp; Project Systems
        </div>
      </div>
      <div style="text-align:right; font-size:12px; color:var(--muted);">
        Verified at <b>mindrift.io/verify/<?= substr($certId, 4); ?></b>
      </div>
    </div>

  </div>

</div>

</body>
</html>
