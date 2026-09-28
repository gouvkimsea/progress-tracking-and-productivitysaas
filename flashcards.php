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

// Fetch courses for deck filter
$stmtCourses = $db->prepare("SELECT DISTINCT name FROM courses WHERE user_id = :uid ORDER BY name ASC");
$stmtCourses->execute(['uid' => $userId]);
$userCourses = $stmtCourses->fetchAll();

// Fetch initial flashcards
$stmtCards = $db->prepare("SELECT * FROM flashcards WHERE user_id = :uid ORDER BY due_date ASC, id ASC");
$stmtCards->execute(['uid' => $userId]);
$initialCards = $stmtCards->fetchAll();

$today = date('Y-m-d');
$totalCount = count($initialCards);
$dueCount = 0;
$masteredCount = 0;

foreach ($initialCards as $c) {
    if (($c['due_date'] ?? '') <= $today) $dueCount++;
    if ((int)($c['interval_days'] ?? 1) >= 7) $masteredCount++;
}

$pageTitle = 'Mindrift — Flashcards';
include __DIR__ . '/includes/head.php';
?>
<style>
  .flashcards-page-wrap {
    max-width: 960px;
    margin: 0 auto;
    padding: 0 0 40px;
  }
  .fc-header-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 12px;
  }
  .fc-title-wrap {
    display: flex;
    align-items: center;
    gap: 10px;
  }
  .fc-title {
    margin: 0;
    font-size: 22px;
    font-weight: 800;
    color: var(--ink);
    letter-spacing: -0.02em;
  }

  /* Filter Pills */
  .fc-filter-strip {
    display: flex;
    align-items: center;
    gap: 8px;
    overflow-x: auto;
    padding-bottom: 8px;
    margin-bottom: 24px;
  }
  .fc-pill {
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 12.5px;
    font-weight: 600;
    background: var(--panel-bg);
    border: 1px solid var(--border);
    color: var(--muted);
    cursor: pointer;
    white-space: nowrap;
    transition: all 0.15s ease;
  }
  .fc-pill.active {
    background: var(--purple);
    border-color: var(--purple);
    color: #FFFFFF;
    font-weight: 700;
  }

  /* Metrics Bento */
  .fc-bento {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 28px;
  }
  .fc-metric-card {
    background: var(--panel-bg);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 18px 16px;
    text-align: center;
    box-shadow: var(--shadow-card);
  }
  .fc-metric-val {
    font-size: 26px;
    font-weight: 800;
    color: var(--ink);
    line-height: 1.1;
  }
  .fc-metric-lbl {
    font-size: 11.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--muted);
    margin-top: 6px;
  }

  /* 3D Flashcard Stage */
  .fc-stage {
    perspective: 1200px;
    max-width: 680px;
    margin: 0 auto 24px;
    min-height: 340px;
  }
  .fc-card {
    width: 100%;
    min-height: 340px;
    position: relative;
    transform-style: preserve-3d;
    transition: transform 0.25s ease;
    cursor: pointer;
    border-radius: var(--radius-xl);
  }
  .fc-card.flipped {
    transform: rotateY(180deg);
  }
  .fc-face {
    position: absolute;
    width: 100%;
    min-height: 340px;
    backface-visibility: hidden;
    border-radius: var(--radius-xl);
    padding: 36px 32px;
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    background: var(--panel-bg);
    border: 2px solid var(--border);
    box-shadow: var(--shadow-shell);
  }
  .fc-front {
    transform: rotateY(0deg);
  }
  .fc-back {
    transform: rotateY(180deg);
    background: var(--panel-bg);
    border-color: rgba(108, 92, 231, 0.35);
  }
  .fc-badge {
    align-self: flex-start;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 11.5px;
    font-weight: 700;
    color: var(--purple);
    background: rgba(108, 92, 231, 0.08);
  }
  .fc-content-text {
    font-size: 20px;
    font-weight: 700;
    color: var(--ink);
    line-height: 1.45;
    text-align: center;
    margin: auto 0;
  }
  .fc-hint {
    font-size: 11.5px;
    color: var(--muted);
    text-align: center;
    font-weight: 600;
  }

  /* Spaced Repetition Buttons */
  .fc-rating-strip {
    display: flex;
    gap: 10px;
    justify-content: center;
    margin-bottom: 24px;
    flex-wrap: wrap;
  }
  .fc-btn-rate {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 10px 18px;
    border-radius: 10px;
    border: 1px solid var(--border);
    background: var(--panel-bg);
    cursor: pointer;
    font-size: 13px;
    font-weight: 700;
    transition: background 0.15s ease, border-color 0.15s ease;
    min-width: 90px;
  }
  .fc-btn-rate:hover {
    border-color: currentColor;
  }
  .fc-btn-again { color: #EF4444; border-color: rgba(239, 68, 68, 0.3); }
  .fc-btn-again:hover { background: rgba(239, 68, 68, 0.08); }
  .fc-btn-hard { color: #F59E0B; border-color: rgba(245, 158, 11, 0.3); }
  .fc-btn-hard:hover { background: rgba(245, 158, 11, 0.08); }
  .fc-btn-good { color: #10B981; border-color: rgba(16, 185, 129, 0.3); }
  .fc-btn-good:hover { background: rgba(16, 185, 129, 0.08); }
  .fc-btn-easy { color: #3B82F6; border-color: rgba(59, 130, 246, 0.3); }
  .fc-btn-easy:hover { background: rgba(59, 130, 246, 0.08); }
  .fc-rate-sub { font-size: 10.5px; font-weight: 500; opacity: 0.8; margin-top: 2px; }

  /* Deck Nav Controls */
  .fc-deck-controls {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 16px;
  }
  .btn-nav-arrow {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    border: 1px solid var(--border);
    background: var(--panel-bg);
    color: var(--ink);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-weight: 700;
    transition: all 0.15s ease;
  }
  .btn-nav-arrow:hover {
    background: rgba(108, 92, 231, 0.08);
    border-color: var(--purple);
  }
  .fc-counter-text {
    font-size: 13.5px;
    font-weight: 700;
    color: var(--muted);
  }
</style>
</head>
<body>

<div class="app" id="app">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>

  <main class="main">
    <?php include __DIR__ . '/includes/header.php'; ?>

    <div class="flashcards-page-wrap">
      
      <!-- Top Action Bar -->
      <div class="fc-header-row">
        <div class="fc-title-wrap">
          <div>
            <h2 class="fc-title">Flashcards</h2>
            <div style="font-size: 12.5px; color: var(--muted); margin-top: 2px;">Review study cards with spaced repetition</div>
          </div>
        </div>
        <button type="button" class="btn-save" onclick="document.getElementById('createCardModal').classList.add('active')" style="display:inline-flex; align-items:center; gap:8px; font-size:13px; padding:8px 16px;">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Add Flashcard
        </button>
      </div>

      <!-- Course Deck Filter -->
      <div class="fc-filter-strip">
        <button type="button" class="fc-pill active" data-course="all" onclick="filterDeck('all', this)">All Decks</button>
        <?php foreach ($userCourses as $uc): ?>
          <button type="button" class="fc-pill" data-course="<?= htmlspecialchars($uc['name']); ?>" onclick="filterDeck('<?= htmlspecialchars($uc['name']); ?>', this)">
            <?= htmlspecialchars($uc['name']); ?>
          </button>
        <?php endforeach; ?>
      </div>

      <!-- Metrics Bento -->
      <div class="fc-bento">
        <div class="fc-metric-card">
          <div class="fc-metric-val" id="metricTotal"><?= $totalCount; ?></div>
          <div class="fc-metric-lbl">Total Cards</div>
        </div>
        <div class="fc-metric-card">
          <div class="fc-metric-val" id="metricDue" style="color: #F59E0B;"><?= $dueCount; ?></div>
          <div class="fc-metric-lbl">Due for Review</div>
        </div>
        <div class="fc-metric-card">
          <div class="fc-metric-val" id="metricMastered" style="color: #10B981;"><?= $masteredCount; ?></div>
          <div class="fc-metric-lbl">Mastered (7d+)</div>
        </div>
        <div class="fc-metric-card">
          <div class="fc-metric-val" id="metricReviewedToday" style="color: var(--purple);">0</div>
          <div class="fc-metric-lbl">Reviewed Today</div>
        </div>
      </div>

      <!-- 3D Flashcard Container -->
      <div class="fc-stage">
        <div class="fc-card" id="activeFlashcard" onclick="flipCard()">
          
          <!-- Front Face -->
          <div class="fc-face fc-front">
            <div class="fc-badge" id="cardCourse">Course Name</div>
            <div class="fc-content-text" id="cardQuestion">Loading question...</div>
            <div class="fc-hint">Click card or press Space to flip</div>
          </div>

          <!-- Back Face -->
          <div class="fc-face fc-back">
            <div class="fc-badge" style="color:#10B981; background:rgba(16,185,129,0.08);">Answer</div>
            <div class="fc-content-text" id="cardAnswer" style="font-size:17.5px; font-weight:600;">Loading answer...</div>
            <div class="fc-hint">Rate your recall to schedule the next review</div>
          </div>

        </div>
      </div>

      <!-- Spaced Repetition Rating Buttons -->
      <div class="fc-rating-strip" id="ratingStrip" style="display: none;">
        <button type="button" class="fc-btn-rate fc-btn-again" onclick="rateCard('again')">
          <span>Again</span>
          <span class="fc-rate-sub">1d (Reset)</span>
        </button>
        <button type="button" class="fc-btn-rate fc-btn-hard" onclick="rateCard('hard')">
          <span>Hard</span>
          <span class="fc-rate-sub">+1d</span>
        </button>
        <button type="button" class="fc-btn-rate fc-btn-good" onclick="rateCard('good')">
          <span>Good</span>
          <span class="fc-rate-sub">2x Interval</span>
        </button>
        <button type="button" class="fc-btn-rate fc-btn-easy" onclick="rateCard('easy')">
          <span>Easy</span>
          <span class="fc-rate-sub">3x Interval</span>
        </button>
      </div>

      <!-- Bottom Deck Nav Controls -->
      <div class="fc-deck-controls">
        <button type="button" class="btn-nav-arrow" onclick="prevCard()" title="Previous Card (←)">‹</button>
        <span class="fc-counter-text" id="cardCounter">Card 1 of 4</span>
        <button type="button" class="btn-nav-arrow" onclick="nextCard()" title="Next Card (→)">›</button>
      </div>

    </div>

  </main>
</div>

<!-- Modal: Add New Flashcard -->
<div class="modal-overlay" id="createCardModal">
  <div class="modal-card">
    <div class="modal-header">
      <h3 class="modal-title">Create Flashcard</h3>
      <button class="modal-close-btn" onclick="document.getElementById('createCardModal').classList.remove('active')">&times;</button>
    </div>
    <form id="createCardForm" onsubmit="submitFlashcard(event)">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label" for="fcCourseSelect">Course / Topic</label>
          <select id="fcCourseSelect" class="form-input">
            <?php foreach ($userCourses as $uc): ?>
              <option value="<?= htmlspecialchars($uc['name']); ?>"><?= htmlspecialchars($uc['name']); ?></option>
            <?php endforeach; ?>
            <option value="General Knowledge">General Knowledge</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label" for="fcQuestion">Question</label>
          <textarea id="fcQuestion" class="form-input" rows="3" placeholder="Enter question..." required></textarea>
        </div>
        <div class="form-group">
          <label class="form-label" for="fcAnswer">Answer</label>
          <textarea id="fcAnswer" class="form-input" rows="3" placeholder="Enter answer..." required></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-cancel" onclick="document.getElementById('createCardModal').classList.remove('active')">Cancel</button>
        <button type="submit" class="btn-save" id="btnSubmitCard">Save Flashcard</button>
      </div>
    </form>
  </div>
</div>

<script src="assets/js/app.js"></script>
<script>
let deckCards = <?= json_encode($initialCards); ?>;
let activeIndex = 0;
let reviewedTodayCount = 0;

function renderCard() {
  const cardElem = document.getElementById('activeFlashcard');
  const ratingStrip = document.getElementById('ratingStrip');
  const counterText = document.getElementById('cardCounter');

  if (cardElem) cardElem.classList.remove('flipped');
  if (ratingStrip) ratingStrip.style.display = 'none';

  if (!deckCards || deckCards.length === 0) {
    document.getElementById('cardCourse').textContent = 'Deck Completed';
    document.getElementById('cardQuestion').textContent = 'All cards in this deck have been reviewed. Add new cards or check back later.';
    document.getElementById('cardAnswer').textContent = 'No cards currently due for review.';
    counterText.textContent = '0 of 0';
    return;
  }

  if (activeIndex >= deckCards.length) activeIndex = 0;
  if (activeIndex < 0) activeIndex = deckCards.length - 1;

  const current = deckCards[activeIndex];
  document.getElementById('cardCourse').textContent = current.course_name;
  document.getElementById('cardQuestion').textContent = current.question;
  document.getElementById('cardAnswer').textContent = current.answer;
  counterText.textContent = `Card ${activeIndex + 1} of ${deckCards.length}`;
}

function flipCard() {
  const card = document.getElementById('activeFlashcard');
  const ratingStrip = document.getElementById('ratingStrip');
  if (!deckCards || deckCards.length === 0) return;

  card.classList.toggle('flipped');
  const isFlipped = card.classList.contains('flipped');
  if (ratingStrip) {
    ratingStrip.style.display = isFlipped ? 'flex' : 'none';
  }
}

function nextCard() {
  if (!deckCards || deckCards.length === 0) return;
  activeIndex = (activeIndex + 1) % deckCards.length;
  renderCard();
}

function prevCard() {
  if (!deckCards || deckCards.length === 0) return;
  activeIndex = (activeIndex - 1 + deckCards.length) % deckCards.length;
  renderCard();
}

async function rateCard(rating) {
  if (!deckCards || deckCards.length === 0) return;
  const current = deckCards[activeIndex];

  try {
    const res = await secureFetch('api/flashcards.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'review', card_id: current.id, rating })
    });
    const data = await res.json();
    if (data.success) {
      reviewedTodayCount++;
      document.getElementById('metricReviewedToday').textContent = reviewedTodayCount;
      if (typeof showToast === 'function') showToast(data.message);

      // Advance to next card
      setTimeout(() => {
        nextCard();
      }, 200);
    }
  } catch (err) {
    console.error(err);
  }
}

async function filterDeck(course, btn) {
  document.querySelectorAll('.fc-pill').forEach(p => p.classList.remove('active'));
  btn.classList.add('active');

  try {
    const res = await fetch(`api/flashcards.php?course=${encodeURIComponent(course)}`);
    const data = await res.json();
    if (data.success) {
      deckCards = data.cards;
      activeIndex = 0;
      document.getElementById('metricTotal').textContent = data.total_count;
      document.getElementById('metricDue').textContent = data.due_count;
      document.getElementById('metricMastered').textContent = data.mastered_count;
      renderCard();
    }
  } catch (err) {
    console.error(err);
  }
}

async function submitFlashcard(e) {
  e.preventDefault();
  const course_name = document.getElementById('fcCourseSelect').value;
  const question = document.getElementById('fcQuestion').value.trim();
  const answer = document.getElementById('fcAnswer').value.trim();
  const btn = document.getElementById('btnSubmitCard');

  btn.disabled = true;
  btn.textContent = 'Saving...';

  try {
    const res = await secureFetch('api/flashcards.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'create', course_name, question, answer })
    });
    const data = await res.json();
    if (data.success) {
      if (typeof showToast === 'function') showToast('Flashcard created.');
      document.getElementById('createCardModal').classList.remove('active');
      setTimeout(() => location.reload(), 400);
    } else {
      alert(data.message || 'Failed to create card');
    }
  } catch (err) {
    console.error(err);
  } finally {
    btn.disabled = false;
    btn.textContent = 'Save Flashcard';
  }
}

// Keyboard navigation
document.addEventListener('keydown', (e) => {
  if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
  if (e.code === 'Space') {
    e.preventDefault();
    flipCard();
  } else if (e.code === 'ArrowRight') {
    nextCard();
  } else if (e.code === 'ArrowLeft') {
    prevCard();
  } else if (e.key === '1') {
    rateCard('again');
  } else if (e.key === '2') {
    rateCard('hard');
  } else if (e.key === '3') {
    rateCard('good');
  } else if (e.key === '4') {
    rateCard('easy');
  }
});

document.addEventListener('DOMContentLoaded', () => {
  renderCard();
});
</script>
</body>
</html>
