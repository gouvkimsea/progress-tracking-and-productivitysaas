<?php
require_once __DIR__ . '/config/db.php';
startSecureSession();

// If user already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
$initialTheme = $_COOKIE['mindrift_theme'] ?? '';
$isDark = ($initialTheme === 'dark');
?>
<!DOCTYPE html>
<html lang="en" <?= $isDark ? 'data-theme="dark"' : ''; ?>>
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Mindrift — Create Account</title>
<!-- Instant Early Theme Bootstrap Script -->
<script>
  (function() {
    try {
      var saved = localStorage.getItem('mindrift_theme');
      var cookieMatch = document.cookie.match(/(?:^|;\s*)mindrift_theme=([^;]+)/);
      var cookieTheme = cookieMatch ? cookieMatch[1] : null;
      var theme = saved || cookieTheme;
      if (!theme && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
        theme = 'dark';
      }
      if (theme === 'dark') {
        document.documentElement.setAttribute('data-theme', 'dark');
      } else if (theme === 'light') {
        document.documentElement.removeAttribute('data-theme');
      }
    } catch(e) {}
  })();
</script>
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="assets/css/style.css?v=<?= assetVersion(); ?>" />
<style>
  body {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
  }
  .auth-card {
    width: 100%;
    max-width: 460px;
    background: var(--white);
    border-radius: 24px;
    box-shadow: 0 30px 80px -20px rgba(60,40,110,0.35), 0 10px 30px -10px rgba(60,40,110,0.15);
    padding: 40px;
    border: 1px solid var(--border);
  }
  .auth-brand {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 28px;
  }
  .auth-logo {
    width: 42px; height: 42px; border-radius: 12px;
    background: linear-gradient(145deg, #8B7CF0, #5A46E0);
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 6px 16px -2px rgba(90,70,224,0.5);
  }
  .auth-title {
    font-size: 24px; font-weight: 800; letter-spacing: -0.02em; margin: 0 0 6px;
  }
  .auth-subtitle {
    font-size: 14px; color: var(--muted); margin: 0 0 28px; font-weight: 500;
  }
  .form-group {
    margin-bottom: 18px;
  }
  .form-label {
    display: block; font-size: 13px; font-weight: 700; color: var(--ink); margin-bottom: 8px;
  }
  .form-input {
    width: 100%;
    padding: 13px 16px;
    font-size: 14px;
    font-family: inherit;
    border: 1.5px solid var(--border);
    border-radius: 12px;
    background: #FDFDFE;
    color: var(--ink);
    outline: none;
    transition: all 0.2s ease;
  }
  .form-input:focus {
    border-color: var(--purple);
    background: var(--white);
    box-shadow: 0 0 0 4px rgba(108,92,231,0.12);
  }
  .btn-submit {
    width: 100%;
    padding: 14px;
    font-size: 15px;
    font-weight: 700;
    font-family: inherit;
    color: #fff;
    background: linear-gradient(145deg, #7C6CF0, #5A46E0);
    border: none;
    border-radius: 12px;
    cursor: pointer;
    box-shadow: 0 8px 20px -4px rgba(90,70,224,0.4);
    transition: all 0.2s ease;
    margin-top: 8px;
  }
  .btn-submit:hover {
    transform: translateY(-1px);
    box-shadow: 0 12px 24px -4px rgba(90,70,224,0.5);
  }
  .btn-submit:active { transform: translateY(0); }
  .auth-footer {
    text-align: center; margin-top: 24px; font-size: 13.5px; color: var(--ink-soft); font-weight: 500;
  }
  .auth-footer a {
    color: var(--purple-deep); font-weight: 700; text-decoration: none;
  }
  .auth-footer a:hover { text-decoration: underline; }
  .alert-box {
    padding: 12px 16px; border-radius: 10px; font-size: 13.5px; font-weight: 600;
    margin-bottom: 20px; display: none;
  }
  .alert-error { background: #FDE8E8; color: var(--red); border: 1px solid #F8B4B4; }
  .alert-success { background: #EAF8F1; color: var(--green); border: 1px solid #9AE6C4; }
</style>
</head>
<body>

<div class="auth-card">
  <div class="auth-brand">
    <div class="auth-logo">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
        <path d="M12 3 L13.6 9.2 L20 12 L13.6 14.8 L12 21 L10.4 14.8 L4 12 L10.4 9.2 Z" fill="#fff"/>
      </svg>
    </div>
    <span class="brand-name">Mindrift<span class="tm">™</span></span>
  </div>

  <h1 class="auth-title">Create an account</h1>
  <p class="auth-subtitle">Start tracking your personal learning journey</p>

  <div class="alert-box alert-error" id="alertBox"></div>

  <form id="registerForm">
    <div class="form-group">
      <label class="form-label" for="name">Full name</label>
      <input type="text" id="name" class="form-input" placeholder="Alex Morgan" required autofocus />
    </div>

    <div class="form-group">
      <label class="form-label" for="email">Email address</label>
      <input type="email" id="email" class="form-input" placeholder="alex@mindrift.io" required />
    </div>

    <div class="form-group">
      <label class="form-label" for="password">Password</label>
      <input type="password" id="password" class="form-input" placeholder="Minimum 6 characters" required minlength="6" />
    </div>

    <div class="form-group">
      <label class="form-label" for="confirmPassword">Confirm password</label>
      <input type="password" id="confirmPassword" class="form-input" placeholder="Re-enter password" required minlength="6" />
    </div>

    <button type="submit" class="btn-submit" id="btnSubmit">Create Account</button>
  </form>

  <div class="auth-footer">
    Already have an account? <a href="login.php">Sign In</a>
  </div>
</div>

<script>
  const registerForm = document.getElementById('registerForm');
  const alertBox = document.getElementById('alertBox');

  registerForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    alertBox.style.display = 'none';

    const name = document.getElementById('name').value.trim();
    const email = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirmPassword').value;

    if (password !== confirmPassword) {
      alertBox.className = 'alert-box alert-error';
      alertBox.textContent = 'Passwords do not match. Please verify.';
      alertBox.style.display = 'block';
      return;
    }

    const btnSubmit = document.getElementById('btnSubmit');
    btnSubmit.disabled = true;
    btnSubmit.textContent = 'Creating account...';

    try {
      const res = await fetch('api/register.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name, email, password })
      });
      const data = await res.json();

      if (data.success) {
        alertBox.className = 'alert-box alert-success';
        alertBox.textContent = data.message;
        alertBox.style.display = 'block';
        setTimeout(() => {
          window.location.href = data.redirect || 'index.php';
        }, 800);
      } else {
        alertBox.className = 'alert-box alert-error';
        alertBox.textContent = data.message || 'Registration failed.';
        alertBox.style.display = 'block';
        btnSubmit.disabled = false;
        btnSubmit.textContent = 'Create Account';
      }
    } catch (err) {
      alertBox.className = 'alert-box alert-error';
      alertBox.textContent = 'Connection error. Please try again.';
      alertBox.style.display = 'block';
      btnSubmit.disabled = false;
      btnSubmit.textContent = 'Create Account';
    }
  });
</script>

</body>
</html>
