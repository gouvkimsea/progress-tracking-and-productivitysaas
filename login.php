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
<title>Mindrift — Sign In</title>
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
    background: var(--bg);
    color: var(--ink);
  }
  .auth-card {
    width: 100%;
    max-width: 400px;
    background: var(--panel-bg);
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-shell);
    padding: 36px 32px;
    border: 1px solid var(--border);
  }
  .auth-brand {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 24px;
  }
  .auth-logo {
    width: 32px; height: 32px; border-radius: var(--radius-sm);
    background: var(--blue);
    display: flex; align-items: center; justify-content: center;
    color: #fff;
  }
  .brand-name {
    font-size: 16px;
    font-weight: 800;
    letter-spacing: -0.02em;
    color: var(--ink);
  }
  .auth-title {
    font-size: 20px; font-weight: 700; letter-spacing: -0.02em; margin: 0 0 6px;
    color: var(--ink);
  }
  .auth-subtitle {
    font-size: 13.5px; color: var(--muted); margin: 0 0 24px; font-weight: 400;
  }
  .form-group {
    margin-bottom: 18px;
  }
  .form-label {
    display: block; font-size: 13px; font-weight: 600; color: var(--ink); margin-bottom: 6px;
  }
  .input-wrapper {
    position: relative;
  }
  .form-input {
    width: 100%;
    padding: 10px 14px;
    font-size: 13.5px;
    font-family: inherit;
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    background: var(--panel-bg);
    color: var(--ink);
    outline: none;
    box-sizing: border-box;
    transition: border-color 0.15s ease;
  }
  .form-input:focus {
    border-color: var(--blue);
    box-shadow: 0 0 0 3px rgba(37,99,235,0.12);
  }
  .pwd-toggle {
    position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
    background: none; border: none; color: var(--muted); cursor: pointer; padding: 2px;
    display: flex; align-items: center;
  }
  .pwd-toggle:hover { color: var(--ink); }
  .btn-submit {
    width: 100%;
    padding: 11px 16px;
    font-size: 14px;
    font-weight: 600;
    font-family: inherit;
    color: #fff;
    background: var(--blue);
    border: none;
    border-radius: var(--radius-sm);
    cursor: pointer;
    transition: background 0.15s ease;
    margin-top: 6px;
  }
  .btn-submit:hover {
    background: var(--blue-hover);
  }
  .btn-submit:disabled {
    opacity: 0.6;
    cursor: not-allowed;
  }
  .auth-footer {
    text-align: center; margin-top: 20px; font-size: 13px; color: var(--muted);
  }
  .auth-footer a {
    color: var(--blue); font-weight: 600; text-decoration: none;
  }
  .auth-footer a:hover { text-decoration: underline; }
  .alert-box {
    padding: 10px 14px; border-radius: var(--radius-sm); font-size: 13px; font-weight: 500;
    margin-bottom: 18px; display: none;
  }
  .alert-error { background: #FEF2F2; color: var(--red); border: 1px solid #FCA5A5; }
  .alert-success { background: #F0FDF4; color: var(--green); border: 1px solid #86EFAC; }
</style>
</head>
<body>

<div class="auth-card">
  <div class="auth-brand">
    <div class="auth-logo">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
        <path d="M12 3 L13.6 9.2 L20 12 L13.6 14.8 L12 21 L10.4 14.8 L4 12 L10.4 9.2 Z" fill="#fff"/>
      </svg>
    </div>
    <span class="brand-name">Mindrift</span>
  </div>

  <h1 class="auth-title">Sign In</h1>
  <p class="auth-subtitle">Enter your email and password to access your workspace.</p>

  <div class="alert-box alert-error" id="alertBox"></div>

  <form id="loginForm">
    <div class="form-group">
      <label class="form-label" for="email">Email address</label>
      <input type="email" id="email" class="form-input" placeholder="alex@mindrift.io" required autofocus />
    </div>

    <div class="form-group">
      <label class="form-label" for="password">Password</label>
      <div class="input-wrapper">
        <input type="password" id="password" class="form-input" placeholder="••••••••" required />
        <button type="button" class="pwd-toggle" id="pwdToggle" title="Toggle password visibility">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
        </button>
      </div>
    </div>

    <button type="submit" class="btn-submit" id="btnSubmit">Sign In</button>
  </form>

  <div class="auth-footer">
    Don't have an account? <a href="register.php">Sign Up</a>
  </div>
</div>

<script>
  const loginForm = document.getElementById('loginForm');
  const alertBox = document.getElementById('alertBox');
  const pwdToggle = document.getElementById('pwdToggle');
  const passwordInput = document.getElementById('password');

  pwdToggle.addEventListener('click', () => {
    const isPassword = passwordInput.type === 'password';
    passwordInput.type = isPassword ? 'text' : 'password';
  });

  loginForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    alertBox.style.display = 'none';
    const email = document.getElementById('email').value.trim();
    const password = passwordInput.value;

    const btnSubmit = document.getElementById('btnSubmit');
    btnSubmit.disabled = true;
    btnSubmit.textContent = 'Signing in...';

    try {
      const res = await fetch('api/login.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email, password })
      });
      const data = await res.json();

      if (data.success) {
        alertBox.className = 'alert-box alert-success';
        alertBox.textContent = data.message;
        alertBox.style.display = 'block';
        setTimeout(() => {
          window.location.href = data.redirect || 'index.php';
        }, 600);
      } else {
        alertBox.className = 'alert-box alert-error';
        alertBox.textContent = data.message || 'Invalid email or password. Please try again.';
        alertBox.style.display = 'block';
        btnSubmit.disabled = false;
        btnSubmit.textContent = 'Sign In';
      }
    } catch (err) {
      alertBox.className = 'alert-box alert-error';
      alertBox.textContent = 'Could not connect to the server. Check your connection and try again.';
      alertBox.style.display = 'block';
      btnSubmit.disabled = false;
      btnSubmit.textContent = 'Sign In';
    }
  });
</script>

</body>
</html>
