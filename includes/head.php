<?php
// Read saved theme from cookie for zero-flash server-side rendering
$initialTheme = $_COOKIE['mindrift_theme'] ?? '';
$isDark = ($initialTheme === 'dark');
?>
<!DOCTYPE html>
<html lang="en" <?= $isDark ? 'data-theme="dark"' : ''; ?>>
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="csrf-token" content="<?= htmlspecialchars(getCsrfToken()); ?>">
<title><?= htmlspecialchars($pageTitle ?? 'Mindrift — Project & Progress Tracking'); ?></title>
<!-- Instant Early Theme Bootstrap Script to eliminate Flash of Unstyled Theme (FOUC) -->
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
<link rel="manifest" href="manifest.json" />
<link rel="icon" type="image/svg+xml" href="assets/img/icon.svg" />
<link rel="apple-touch-icon" href="assets/img/icon.svg" />
<meta name="theme-color" content="#6C5CE7" />
<script>
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
      navigator.serviceWorker.register('sw.js').catch(function() {});
    });
  }
</script>
