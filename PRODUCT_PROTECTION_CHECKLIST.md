# Product Protection Checklist & Architecture Guardrails

> **Core Mandate**: *"Remove AI slop without breaking the product."*  
> Every visual refinement, simplification, and code cleanup must preserve all underlying functionality, business logic, API contracts, session security, and database integrity. Never modify backend systems, security routines, or data models for cosmetic reasons.

---

## 1. Authentication System
- **Password Security**: Bcrypt hashing using `password_hash($password, PASSWORD_BCRYPT, ['cost' => 12])` (or `PASSWORD_DEFAULT`).
- **Timing Attack Mitigation**: Execution of dummy password verification (`password_verify`) when an account is not found, mitigating user enumeration.
- **Session Hardening**: Immediate invocation of `session_regenerate_id(true)` upon successful credential authentication to prevent session fixation.
- **CSRF Token Generation**: Per-session cryptographic token creation via `bin2hex(random_bytes(32))` stored in `$_SESSION['csrf_token']` and transmitted via `X-CSRF-Token` headers and meta tags.

---

## 2. Registration System
- **File Endpoints**: `register.php`, `api/register.php`
- **Input Validation**:
  - Full name trimming and validation (minimum 2 characters).
  - Case-insensitive RFC-compliant email address validation (`filter_var($email, FILTER_VALIDATE_EMAIL)`).
  - Minimum password length constraint (>= 6 characters).
- **Collision Prevention**: Unique database query checking existing email addresses before insertion.
- **Initial Workspace Provisioning**:
  - Auto-creation of the default user account row in `users`.
  - Default initialization of `user_stats` (streaks, study minutes, lesson counters).
  - Automatic seeding of initial starter courses (`UI Design Mastery`, `Learn JavaScript`, etc.).
  - Automatic setup of starter streak tracking (`weekly_streaks`).

---

## 3. Login System
- **File Endpoints**: `login.php`, `api/login.php`
- **Brute-Force & Rate Limiting**:
  - IP-based rate limiting via `checkLoginRateLimit($db, $ip, 5, 900)` restricting failed attempts to 5 per 15-minute window.
  - Recording failed attempts via `recordLoginAttempt($db, $ip)`.
  - Immediate clearing of failed attempts upon successful authentication via `clearLoginAttempts($db, $ip)`.
- **Client Submission Flow**:
  - JSON payload submission via `fetch('api/login.php')`.
  - UI state lock: Submit button disables and indicates `"Signing in..."` to prevent duplicate submissions.
  - Redirect handling: Graceful redirection to `index.php` (or target URL from `data.redirect`).

---

## 4. Logout System
- **File Endpoints**: `api/logout.php`, header sign-out triggers
- **Session Invalidation**: Complete unsetting and destruction of `$_SESSION` via `$_SESSION = []` and `session_destroy()`.
- **Cookie Clearance**: Deletion of the `PHPSESSID` cookie across matching domain, path, and security flags (`setcookie(session_name(), '', time() - 3600, ...)`).
- **Navigation Response**: Clean JSON response `{ "success": true, "redirect": "login.php" }` or immediate HTTP 302 redirect to `login.php`.

---

## 5. API Requests & Contracts
All 25 API endpoints must strictly maintain their existing request parameters, response JSON schemas (`{ success: bool, message?: string, data?: any }`), and HTTP status codes (`200`, `400`, `401`, `404`, `405`, `500`):

| Endpoint | Method | Key Protections |
| :--- | :--- | :--- |
| `api/login.php` | POST | JSON body parsing, rate limiting, credential verification, session creation |
| `api/register.php` | POST | Password hashing, email deduplication, starter workspace provisioning |
| `api/logout.php` | POST | Session invalidation, session cookie deletion |
| `api/dashboard.php` | GET | Active task counts, course progress aggregations, streak stats, activity heatmap |
| `api/tasks.php` | GET, POST | Task CRUD, status cycling (`Open`, `In Progress`, `Done`), priority, due dates, project association |
| `api/kanban.php` | GET, POST | Task status column migration, board order, drag-and-drop persistence |
| `api/calendar_export.php` | GET | RFC 5545 iCalendar (`.ics`) generation with valid `UID`, `SUMMARY`, and `DTSTART` |
| `api/gantt.php` | GET, POST | Timeline start/end dates, milestone dependencies, completion percentages |
| `api/goals.php` | GET, POST | OKRs, current vs target values, unit measurement, deadline tracking |
| `api/flashcards.php` | GET, POST | Spaced repetition Leitner interval progression, ratings (`again`, `hard`, `good`, `easy`) |
| `api/courses.php` | GET, POST | Course curriculum, progress updates, module counts, category filters |
| `api/create_project.php` | POST | Project creation, unique code assignment, level and category metadata |
| `api/assignments.php` | GET, POST | Group and individual assignments, deadlines, completion statuses |
| `api/files.php` | GET, POST | File uploads, mime/extension threat detection, deletion, metadata retrieval |
| `api/timer.php` | GET, POST | Pomodoro session logging, elapsed minutes tracking, database synchronization |
| `api/log_activity.php` | POST | Manual time logging, category attribution, study minutes aggregation |
| `api/toggle_streak.php` | POST | Day streak toggling, consecutive day computation, habit status |
| `api/toggle_favorite.php`| POST | Project star bookmarking toggle (`is_starred`) |
| `api/update_profile.php` | POST | Profile name, email modification, password update |
| `api/update_project_status.php`| POST| Project workflow status updating |
| `api/search.php` | GET | Global multi-entity search across tasks, projects, courses, goals |
| `api/export.php` | GET | Comprehensive CSV / JSON data export stream generation |
| `api/notifications.php`| GET, POST | In-app notification polling, mark-as-read updates |
| `api/copilot.php` | POST | Assistant advice, study schedule recommendations, task breakdown |
| `api/schedule_share.php`| GET, POST | Schedule token retrieval, live feed URL generation, token revocation |

---

## 6. Database Interactions & Schema Integrity
- **Dual Engine Architecture** (`config/db.php`):
  - Primary: MySQL PDO connection with `charset=utf8mb4`.
  - Fallback: SQLite (`database.sqlite`) auto-provisioning for resilient local operation.
- **Zero-Injection Policy**:
  - 100% prepared statements (`$stmt->prepare()` with parameterized `:named` or `?` tokens).
  - Absolute prohibition of raw string interpolation (`"SELECT ... WHERE id = " . $_GET['id']`).
- **Auto-Migration & Schema Safety**:
  - Automated table provisioning for: `users`, `user_stats`, `courses`, `tasks`, `goals`, `flashcards`, `files`, `time_logs`, `weekly_streaks`, `login_attempts`, `notifications`, `assignments`.
  - Preservation of foreign keys and `user_id` scoping across all queries to ensure tenant isolation.

---

## 7. User Sessions & State
- **Session Configuration** (`startSecureSession()`):
  - Cookie flags: `httponly = true`, `samesite = Lax`, `secure = true` (when HTTPS active).
  - Lifetime & garbage collection managed securely.
- **Session Variables**:
  - Core identifiers: `$_SESSION['user_id']`, `$_SESSION['user_name']`, `$_SESSION['user_email']`.
  - Auth protection guards: Every authenticated page and API must check `isset($_SESSION['user_id'])` and redirect to `login.php` or return HTTP 401.

---

## 8. Routing & Access Controls
- **Authenticated Page Routes**:
  - `index.php`, `courses.php`, `assignments.php`, `kanban.php`, `calendar.php`, `gantt.php`, `workload.php`, `reports.php`, `analytics.php`, `goals.php`, `notes.php`, `flashcards.php`, `community.php`, `files.php`, `settings.php`, `help.php`.
  - Must consistently redirect unauthenticated requests to `login.php` (HTTP 302).
- **Public Auth Routes**:
  - `login.php`, `register.php`.
  - Authenticated visitors accessing login/register must automatically redirect to `index.php`.
- **Public Share Route**:
  - `schedule.php` (permits read-only tokenized viewing; rejects invalid or revoked tokens with 404).
- **Fallback Route**:
  - `404.php` must render cleanly for invalid URL requests without exposing server paths.

---

## 9. Forms & Mutating Actions
- **CSRF Token Validation**:
  - Validated on mutating API requests via `checkCsrfToken()`.
- **Double-Submission Protection**:
  - Submit buttons must disable (`btn.disabled = true`) upon dispatch and show a progress state.
  - Buttons must restore (`btn.disabled = false`) in `finally` blocks regardless of API success or error.
- **Input Sanitization**:
  - User-generated content rendered in HTML must pass through `htmlspecialchars($val, ENT_QUOTES, 'UTF-8')`.

---

## 10. File Uploads & Storage Protection
- **File Upload Handler**: `api/files.php`
- **Disk Isolation**:
  - Files stored in dedicated `uploads/` directory with random 32-character hexadecimal filenames (`bin2hex(random_bytes(16)) . '.' . $ext`).
  - Original file names preserved in the database for clean user-facing downloads.
- **Storage Directory Security**:
  - `uploads/.htaccess` enforces script execution blocking (`php_flag engine off`, `SetHandler none`, `deny from all` for script extensions).
- **Deletion Safety**:
  - File deletion requests must verify ownership (`WHERE id = :id AND user_id = :uid`) before unlinking disk files.

---

## 11. Scanning & Threat Detection Functionality
- **File Extension & Executable Detection** (`api/files.php`):
  - Strict whitelist validation: `['pdf', 'png', 'jpg', 'jpeg', 'gif', 'zip', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'csv']`.
  - Executable/script regex detection: `preg_match('/(php|phtml|phar|exe|sh|pl|cgi|asp|jsp)/i', $originalName)`.
  - Maximum size detection: 10MB limit enforcement.
- **Brute-Force Attack Detection** (`config/db.php`):
  - Client IP address detection with proxy header awareness (`REMOTE_ADDR`, `HTTP_X_FORWARDED_FOR`).
  - Detection of attack thresholds (> 5 failed attempts within 15 minutes).
- **Natural Language Parsing & Token Detection** (`assets/js/app.js` `parseNLP`):
  - Date keyword detection (`today`, `tomorrow`, `monday`, `in X days`).
  - Priority flag detection (`!urgent`, `!high`, `!med`, `!low`).
  - Project tag detection (`#project`).
  - Assignee tag detection (`@name`).

---

## 12. Dashboard Functionality & Metrics
- **Core View**: `index.php`
- **Dynamic Data Aggregations**:
  - Current learning streak & longest streak counters.
  - Overall course progress percentage.
  - Active tasks count and urgent priority countdowns.
  - 14-day study activity heatmap visualization.
- **Productivity Engines**:
  - Interactive Pomodoro focus timer with audio cues and session counter.
  - Quick action bridges (Command Palette, Omni Quick Task modal).

---

## 13. Subscription Functionality & Entitlement Guardrails
- **Entitlement Isolation**:
  - Multi-tenant data segregation: all queries scoped strictly to `user_id`.
  - Storage threshold guardrails: File uploads and task volumes protected against unauthenticated quota bypass.
- **Feature Gating Preservation**:
  - UI cleanups must never delete or disable tier indicators, upgrade pathways, or feature gates.
  - If subscription tiers (e.g. Free, Pro, Team) are active or added, feature locks and tier indicators must remain functional.

---

## 14. Payment Functionality & Billing Integrity
- **Transactional Safety**:
  - Payment forms, checkout modals, and billing hooks must never have input names, form actions, or tokenizers modified during visual sweeps.
  - Billing status badges and invoice links must retain their target routes and access permissions.
- **Auditability**:
  - Mutating billing or plan changes must remain atomic, maintaining database transaction wrapping (`beginTransaction`, `commit`, `rollBack`).

---

## 15. Existing Integrations
- **iCalendar Calendar Sync**:
  - RFC 5545 export endpoint (`api/calendar_export.php`) generating valid `.ics` streams compatible with Google Calendar, Apple Calendar, and Microsoft Outlook.
- **Data Export System**:
  - Structured CSV / JSON export engine (`api/export.php`) allowing users to download their tasks, courses, and time logs.
- **Copilot Assistant Engine**:
  - `api/copilot.php` handling contextual task advice, automated subtask breakdowns, and study recommendations.
- **Progressive Web App (PWA)**:
  - Valid `manifest.json` with icons and standalone display mode.
  - Service Worker (`sw.js`) providing asset caching and offline resilience.

---

## 16. Change Protocol: "Stop and Explain"

Before making any modification to the application, check against this protocol:

1. **Does the change touch any file in `api/` or `config/`?**
   - **Rule**: STOP. Do not modify backend files unless fixing a verified, reproducible functional defect.
2. **Does a visual change alter form element IDs, names, or classes referenced in JavaScript?**
   - **Rule**: STOP. Ensure IDs (`taskNameInput`, `btnSubmitAddTask`, `fileUploadInput`), classes, and `data-*` attributes (`data-task-id`, `data-prio`, `data-status`) are strictly preserved.
3. **Does a visual change remove an interactive control or route?**
   - **Rule**: STOP. Primary actions (submit, cancel, filter, paginate, delete, edit, close) must remain fully accessible.
4. **Does an AI-slop cleanup intersect with legitimate business logic?**
   - **Rule**: Explain the exact dependency to the user and obtain explicit confirmation before proceeding.
