# Mindrift — Modern Project & Progress Tracking Platform

Mindrift is an interactive, full-stack project tracking and productivity suite engineered with PHP, Vanilla CSS, and modern asynchronous JavaScript.

---

## 🚀 Deployment Readiness Checklist

Mindrift is **100% production deployable** across all environments:
- ✅ **Docker & Container Ready**: Includes a production-tuned `Dockerfile` and `docker-compose.yml`.
- ✅ **Apache / cPanel Ready**: `.htaccess` pre-configured with sensitive file protection (`.env`, `*.sqlite`, `*.sql`), Gzip compression, and browser caching.
- ✅ **Nginx / VPS Ready**: `nginx.conf.example` included with PHP-FPM FastCGI and directory execution blocks.
- ✅ **Zero-Config Database Fallback**: Automatically uses MySQL, or falls back to local SQLite (`mindrift.sqlite`) if no database server is configured.
- ✅ **Cloud / PaaS Ready**: Deployable on Render, Railway, Fly.io, or Heroku.

---

## 🛠️ Deployment Methods

### Option 1: 1-Click Docker Deployment (Recommended)
Clone the repository and run:
```bash
docker compose up -d --build
```
Your application will be live at `http://localhost:8080`.

---

### Option 2: Traditional Apache / cPanel / Shared Hosting
1. Upload all files to your `public_html` or web root directory.
2. Ensure PHP 8.0+ is enabled with `pdo_mysql` and/or `pdo_sqlite`.
3. Copy `.env.example` to `.env` and configure your database credentials:
   ```env
   DB_HOST=127.0.0.1
   DB_NAME=mindrift
   DB_USER=your_db_user
   DB_PASS=your_db_password
   ```
4. Set write permissions on `uploads/` and `mindrift.sqlite` (if using SQLite):
   ```bash
   chmod -R 775 uploads
   chmod 664 mindrift.sqlite
   ```
5. Apache will automatically apply [.htaccess](.htaccess) rules protecting `.env` and database files from direct access.

---

### Option 3: Nginx + PHP-FPM (Ubuntu/Debian VPS)
1. Copy `nginx.conf.example` to `/etc/nginx/sites-available/mindrift`.
2. Update the `server_name` and `root` path.
3. Enable the site and reload Nginx:
   ```bash
   sudo ln -s /etc/nginx/sites-available/mindrift /etc/nginx/sites-enabled/
   sudo systemctl reload nginx
   ```

---

### Option 4: Local Development
To run locally without Docker:
- **Windows (1-Click)**: Double-click `serve.bat`
- **Manual Command Line**:
  ```bash
  php -S 127.0.0.1:8000
  ```
Then navigate to `http://127.0.0.1:8000` in your browser.

---


## 🔑 Default Credentials
- **Email:** `alex@mindrift.io`
- **Password:** `password123`

---

## 🌟 Key Application Features
- 📊 **Interactive Dashboard**: Real-time study streak tracking, course module progress, and weekly learning contribution heatmap.
- 🎯 **Objectives & Key Results (Goals)**: Track quantified targets, deliverables, and progress percentages with status indicators (`goals.php`).
- 📓 **Daily Reflection Journal**: Mood tracking, daily reflection quiz, and daily to-do checklists (`journal.php`).
- 📋 **Kanban Workflow**: Visual task pipeline with drag-and-drop state transitions (`kanban.php`).
- 📅 **Calendar & Schedule**: Integrated monthly view with public read-only schedule sharing (`schedule.php`) and live iCalendar subscription feeds (`api/calendar_export.php`).
- 📈 **Gantt Chart & Dependencies**: Timeline visualization with task dependency mapping (`gantt.php`).
- 👥 **Team Workload**: Optimized resource allocation and task distribution view (`workload.php`).
- 🃏 **Spaced Repetition Flashcards**: Leitner-style active recall study deck (`flashcards.php`).
- 📁 **Secure Document Management**: Attachment uploads with streaming download delivery (`files.php`).
- ⚡ **High-Performance Architecture**: Zero runtime DDL, multi-tenant composite B-tree indexing, and non-blocking session release.

---

## 🛡️ Built-in Security Features
- **OWASP ASVS Compliance**:
  - Universal CSRF token validation on all mutating API calls (`secureFetch`).
  - Strict parameter-binding prepared statements (SQL Injection immune).
  - Rate limiting on login attempts (Max 5 failed attempts per 15 minutes per IP).
  - Obfuscated file upload names with `.htaccess` execution blocking.
  - Safe CSV formula injection escaping on export.
  - Content Security Policy (CSP), `X-Frame-Options: SAMEORIGIN`, and `nosniff` headers.
