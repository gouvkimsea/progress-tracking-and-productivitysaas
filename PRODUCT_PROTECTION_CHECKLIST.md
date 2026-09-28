# Product Protection Checklist

> **Core Principle**: "Remove AI slop without breaking the product."  
> Every visual refinement, simplification, and cleanup must preserve all underlying functionality, API contracts, session states, and database integrity. Never modify business logic or backend files for purely cosmetic reasons.

---

## 1. Authentication & Session Management
- [x] **Registration Flow** (`register.php`, `api/register.php`)
  - Password hashing with `password_hash($password, PASSWORD_BCRYPT, ['cost' => 12])`
  - Input validation: Email format, name sanitization, password complexity (> 6 chars)
  - Duplicate email check (case-insensitive)
  - Auto-initialization of user preferences and default workspaces upon registration
- [x] **Login Flow** (`login.php`, `api/login.php`)
  - Brute-force protection via `checkLoginRateLimit($db, $ip, 5, 900)`
  - Dummy password verification mitigation against user enumeration attacks
  - Session regeneration on login (`session_regenerate_id(true)`) to mitigate session fixation
  - Failed attempt recording (`recordLoginAttempt`) and success clearing (`clearLoginAttempts`)
- [x] **Logout Flow** (`api/logout.php`)
  - Unsetting and destruction of `$_SESSION`
  - Session cookie clearance with matching domain/path/httponly parameters
- [x] **Session Security** (`config/db.php`)
  - `startSecureSession()`: Secure cookies (`httponly`, `samesite=Lax`, `secure` when HTTPS)
  - Session variables: `$_SESSION['user_id']`, `$_SESSION['user_name']`, `$_SESSION['user_email']`
  - Session expiration and idle timeout handling

---

## 2. API Endpoints & Request Contracts
All API endpoints must retain their existing request parameters, response JSON schemas (`{ success: bool, data?: any, message?: string }`), and HTTP status codes:

| Endpoint | Responsibility | Critical Protections |
| :--- | :--- | :--- |
| `api/login.php` | Authentication | JSON / POST input parsing, rate limiting, credential check |
| `api/register.php` | Account creation | Password hashing, email verification, default seeding |
| `api/logout.php` | Session termination | Session destruction, cookie invalidation |
| `api/dashboard.php` | Dashboard metrics | Task aggregations, streak stats, heatmap day-level data |
| `api/tasks.php` | Task CRUD & toggles | Task status toggle, priority updates, due dates, project linkage |
| `api/kanban.php` | Kanban board updates | Column movement, reordering, status updates |
| `api/gantt.php` | Gantt chart timeline | Dependencies, progress tracking, timeline start/end dates |
| `api/goals.php` | Goals & Key Results | Goal creation, progress computation, target milestones |
| `api/flashcards.php` | Spaced repetition study | Leitner box intervals, study ratings, repetition scheduling |
| `api/courses.php` | Course curriculum | Module management, syllabus tracking, course completion status |
| `api/assignments.php` | Assignment workflows | Submissions, grade calculation, deadline trackers |
| `api/files.php` | File asset management | File uploads, mime validation, secure name hashing, file deletion |
| `api/timer.php` | Pomodoro / Time logs | Active timer status, log activity entry creation, duration tracking |
| `api/log_activity.php` | Activity logging | Timestamp recording, module/project linking, duration recording |
| `api/toggle_streak.php` | Habit streak tracker | Consecutive day streak calculations, freeze recovery logic |
| `api/toggle_favorite.php` | Favorites manager | Task and project bookmarking |
| `api/search.php` | Global search engine | Multi-entity lookup (tasks, projects, courses, goals) |
| `api/export.php` | Data export | CSV / JSON data generation and stream download |
| `api/calendar_export.php` | Calendar sync | RFC 5545 iCalendar (`.ics`) format generation |
| `api/notifications.php` | Notification alerts | Unread alerts fetching, mark-as-read, system notices |
| `api/copilot.php` | Assistant integration | Task query handling, context prompt processing, suggestions |
| `api/update_profile.php` | Account settings | Profile name, email change, avatar update |

---

## 3. Database Layer & Data Integrity
- [x] **Dual Connection Support** (`config/db.php`)
  - Primary: MySQL PDO connection with utf8mb4 encoding
  - Fallback: SQLite (`database.sqlite`) auto-provisioning for zero-friction local development
- [x] **Auto Schema Migration**
  - Table auto-creation for `users`, `tasks`, `projects`, `goals`, `flashcards`, `courses`, `assignments`, `files`, `time_logs`, `login_attempts`, `notifications`
- [x] **SQL Security**
  - 100% prepared statements (`$stmt->prepare()` with parameterized `:named` or positional `?` tokens)
  - Zero raw SQL string interpolation with user input
  - Explicit transaction blocks (`beginTransaction`, `commit`, `rollBack`) on multi-table operations

---

## 4. Security & Form Handling
- [x] **CSRF Mitigation** (`getCsrfToken()`, `verifyCsrfToken()`, `checkCsrfToken()`)
  - Every mutating POST/PUT/DELETE request must validate `X-CSRF-Token` header or `csrf_token` POST field
- [x] **XSS Sanitization**
  - All user-supplied output escaped with `htmlspecialchars($value, ENT_QUOTES, 'UTF-8')`
- [x] **File Upload Defenses** (`api/files.php`, `uploads/.htaccess`)
  - Server-side MIME validation (not just file extension)
  - Random hash file renaming (`bin2hex(random_bytes(16))`)
  - Execution prevention in `uploads/` directory via `.htaccess` (`php_flag engine off`, `SetHandler none`)
  - File size limits enforced on server and client sides

---

## 5. Routing, Modals & Navigation
- [x] **Protected Page Routing**
  - Session verification guard on all authenticated pages (`index.php`, `kanban.php`, `calendar.php`, `gantt.php`, `courses.php`, `assignments.php`, `flashcards.php`, `workload.php`, `goals.php`, `files.php`, `analytics.php`, `reports.php`, `community.php`, `portfolio.php`, `settings.php`, `help.php`)
  - Unauthenticated requests safely redirect to `login.php` (HTTP 302)
  - `404.php` preserved for unmatched routes
- [x] **Global Modals & Interactive Overlays**
  - Quick Task Modal (`includes/quick_task_modal.php`): Shortcuts, priority, project picker
  - Create Project Modal (`includes/create_project_modal.php`): Title, description, deadline, color tag
  - Log Activity Modal (`includes/log_activity_modal.php`): Category, minutes spent, notes
  - Command Palette (`includes/command_palette.php`): `Ctrl+K` / `Cmd+K` global keyboard navigation

---

## 6. Functional Business Logic & Integrations
- [x] **Productivity & Streak Calculations**: Streak counts, activity streak freezes, and heatmap levels (0 to 4)
- [x] **Spaced Repetition Algorithm**: Leitner intervals and review schedules in flashcards study mode
- [x] **Workload Matrix Computation**: Member/course capacity balancing and hour aggregations
- [x] **Gantt Chart Scheduling**: Milestone bars, progress percentages, and timeline dependencies
- [x] **Portfolio & Certification Generation**: Metrics computation, dynamic radar graphs, and clean print stylesheet (`@media print`)

---

## 7. Change Protocol: "Stop and Explain"
Before modifying any file, verify against this protocol:
1. **Is the file backend or business logic?**
   - If yes: Do **NOT** modify unless fixing an explicit, verified functional bug.
2. **Does a visual change alter form field names, IDs, or endpoints?**
   - If yes: Stop immediately. Maintain identical `id`, `name`, `data-*` attributes, and event handlers so existing JavaScript and backend handlers continue functioning seamlessly.
3. **Does a style change remove an interactive control?**
   - Verify that all primary user actions (submit, cancel, filter, navigate, pagination) remain directly accessible.
