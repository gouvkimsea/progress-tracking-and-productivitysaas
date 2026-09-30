<?php
/**
 * Mindrift Database Connection Manager
 * Supports MySQL with automatic SQLite fallback for smooth development.
 */

// Load .env file if present
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, " \t\n\r\0\x0B\"'");
            if (!array_key_exists($key, $_ENV)) {
                $_ENV[$key] = $value;
                putenv("{$key}={$value}");
            }
        }
    }
}

if (!function_exists('envValue')) {
    function envValue(string $key, ?string $default = null): ?string {
        $val = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        return ($val !== false && $val !== null && $val !== '') ? (string)$val : $default;
    }
}

if (!defined('DB_DRIVER')) define('DB_DRIVER', envValue('DB_DRIVER', 'mysql'));
if (!defined('DB_HOST')) define('DB_HOST', envValue('DB_HOST', '127.0.0.1'));
if (!defined('DB_PORT')) define('DB_PORT', envValue('DB_PORT', '3306'));
if (!defined('DB_NAME')) define('DB_NAME', envValue('DB_NAME', 'mindrift_db'));
if (!defined('DB_USER')) define('DB_USER', envValue('DB_USER', 'root'));
if (!defined('DB_PASS')) define('DB_PASS', envValue('DB_PASS', ''));
if (!defined('DB_CHARSET')) define('DB_CHARSET', envValue('DB_CHARSET', 'utf8mb4'));
if (!defined('DB_TIMEOUT')) define('DB_TIMEOUT', (int)envValue('DB_TIMEOUT', '5'));
if (!defined('DB_SOCKET')) define('DB_SOCKET', envValue('DB_SOCKET', ''));

// Safe session starter with security headers
function startSecureSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        if (!headers_sent()) {
            $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
                || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
                || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'domain' => '',
                'secure' => $isSecure,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        }
        @session_start();
    }
}

/**
 * Generate or get CSRF token
 */
function getCsrfToken(): string {
    startSecureSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verifyCsrfToken(?string $token): bool {
    startSecureSession();
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Convenience helper to verify CSRF token from header or POST body.
 * Aborts with HTTP 403 Forbidden if the token is invalid or missing.
 */
function checkCsrfToken(bool $abortOnFail = true): bool {
    startSecureSession();
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? null;
    $valid = verifyCsrfToken($token);
    if (!$valid && $abortOnFail) {
        sendJsonResponse(['success' => false, 'message' => 'CSRF validation failed. Please refresh the page.'], 403);
    }
    return $valid;
}

/**
 * Check if client IP has exceeded maximum failed login attempts within decay window
 */
function checkLoginRateLimit(PDO $db, string $ip, int $maxAttempts = 5, int $decaySeconds = 900): bool {
    try {
        $cutoff = time() - $decaySeconds;
        $stmt = $db->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip_address = :ip AND attempted_at > :cutoff");
        $stmt->execute(['ip' => $ip, 'cutoff' => $cutoff]);
        $attempts = (int)$stmt->fetchColumn();
        return $attempts < $maxAttempts;
    } catch (Throwable $e) {
        // If table doesn't exist yet, create it on-the-fly
        try {
            $db->exec("CREATE TABLE IF NOT EXISTS login_attempts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                ip_address TEXT NOT NULL,
                email TEXT NOT NULL,
                attempted_at INTEGER NOT NULL
            )");
        } catch (Throwable $ex) {
            try {
                $db->exec("CREATE TABLE IF NOT EXISTS login_attempts (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    ip_address VARCHAR(45) NOT NULL,
                    email VARCHAR(190) NOT NULL,
                    attempted_at INT NOT NULL,
                    INDEX idx_login_ip_time (ip_address, attempted_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            } catch (Throwable $ex2) {}
        }
        return true;
    }
}

/**
 * Record a failed login attempt for the client IP
 */
function recordLoginAttempt(PDO $db, string $ip, string $email): void {
    try {
        $stmt = $db->prepare("INSERT INTO login_attempts (ip_address, email, attempted_at) VALUES (:ip, :email, :t)");
        $stmt->execute(['ip' => $ip, 'email' => substr($email, 0, 190), 't' => time()]);
    } catch (Throwable $e) {
        error_log("Failed to record login attempt: " . $e->getMessage());
    }
}

/**
 * Clear recorded login attempts for client IP upon successful login
 */
function clearLoginAttempts(PDO $db, string $ip): void {
    try {
        $stmt = $db->prepare("DELETE FROM login_attempts WHERE ip_address = :ip");
        $stmt->execute(['ip' => $ip]);
    } catch (Throwable $e) {
        error_log("Failed to clear login attempts: " . $e->getMessage());
    }
}

/**
 * Database Connection Manager
 * Manages request-scoped singleton PDO instances with high-performance native
 * prepared statements, timeout protections, standardized character set collation,
 * and seamless fallback between MySQL and SQLite.
 */
class DatabaseConnectionManager {
    private static ?PDO $instance = null;

    /**
     * Get or create the singleton PDO connection.
     *
     * @param bool $forceNew If true, drops any existing connection and establishes a fresh one.
     * @return PDO
     */
    public static function getConnection(bool $forceNew = false): PDO {
        if (self::$instance !== null && !$forceNew) {
            return self::$instance;
        }

        self::$instance = null;
        $driver = strtolower(trim(DB_DRIVER));

        // 1. Explicit SQLite Driver Requested
        if ($driver === 'sqlite') {
            self::$instance = self::createSqliteConnection();
            return self::$instance;
        }

        // 2. MySQL Connection with Graceful Fallback
        try {
            self::$instance = self::createMysqlConnection();
            return self::$instance;
        } catch (PDOException $e) {
            // Log connection failure without exposing credentials
            error_log("[Mindrift DB] MySQL connection failure: " . $e->getMessage() . " -> Falling back to SQLite.");

            // If app is configured strictly for mysql in production with no fallback allowed, throw sanitized exception
            if (envValue('APP_ENV') === 'production' && envValue('DB_ALLOW_SQLITE_FALLBACK') === 'false') {
                throw new RuntimeException("Database connection failure. Please verify MySQL service status.");
            }

            self::$instance = self::createSqliteConnection();
            return self::$instance;
        }
    }

    /**
     * Cleanly close the active database connection and reset the singleton instance.
     */
    public static function closeConnection(): void {
        self::$instance = null;
    }

    /**
     * Create optimized MySQL PDO instance with security options.
     */
    private static function createMysqlConnection(): PDO {
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => DB_TIMEOUT,
        ];

        // Found rows attribute compatibility (PHP 8.5+ vs older)
        if (defined('Pdo\Mysql::ATTR_FOUND_ROWS')) {
            $options[\Pdo\Mysql::ATTR_FOUND_ROWS] = true;
        } elseif (defined('PDO::MYSQL_ATTR_FOUND_ROWS')) {
            $options[@constant('PDO::MYSQL_ATTR_FOUND_ROWS')] = true;
        }

        if (envValue('DB_PERSISTENT') === 'true') {
            $options[PDO::ATTR_PERSISTENT] = true;
        }

        if (!empty(DB_SOCKET)) {
            $dsnWithDb = "mysql:unix_socket=" . DB_SOCKET . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        } else {
            $dsnWithDb = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        }

        try {
            $pdo = new PDO($dsnWithDb, DB_USER, DB_PASS, $options);
            $pdo->exec("SET NAMES " . DB_CHARSET . " COLLATE utf8mb4_unicode_ci");
            return $pdo;
        } catch (PDOException $ex) {
            // Database might not exist yet; connect without DB name, create it, and ensure schema
            if (!empty(DB_SOCKET)) {
                $dsnWithoutDb = "mysql:unix_socket=" . DB_SOCKET . ";charset=" . DB_CHARSET;
            } else {
                $dsnWithoutDb = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";charset=" . DB_CHARSET;
            }

            $pdo = new PDO($dsnWithoutDb, DB_USER, DB_PASS, $options);
            $pdo->exec("SET NAMES " . DB_CHARSET . " COLLATE utf8mb4_unicode_ci");
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` DEFAULT CHARACTER SET " . DB_CHARSET . " COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `" . DB_NAME . "`");
            ensureMysqlTables($pdo);
            return $pdo;
        }
    }

    /**
     * Create optimized SQLite fallback PDO instance with WAL mode.
     */
    private static function createSqliteConnection(): PDO {
        $sqliteFile = __DIR__ . '/../mindrift.sqlite';
        $dsn = "sqlite:" . $sqliteFile;
        $pdo = new PDO($dsn, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => DB_TIMEOUT,
        ]);
        $pdo->exec("PRAGMA foreign_keys = ON;");
        $pdo->exec("PRAGMA journal_mode = WAL;");

        // Ensure tables exist on SQLite
        $tableCheck = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'")->fetch();
        if (!$tableCheck) {
            ensureSqliteTables($pdo);
        }

        return $pdo;
    }
}

/**
 * Global database connection helper.
 * Preserves complete backwards compatibility with all existing application calls.
 *
 * @param bool $forceNew
 * @return PDO
 */
function getDbConnection(bool $forceNew = false): PDO {
    return DatabaseConnectionManager::getConnection($forceNew);
}

/**
 * Cleanly close the active database connection.
 */
function closeDbConnection(): void {
    DatabaseConnectionManager::closeConnection();
}

function ensureMysqlTables(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(150) NOT NULL UNIQUE,
        password VARCHAR(255) NULL,
        avatar_url VARCHAR(255) NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Auto-migration: ensure password column exists if table was created previously
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN password VARCHAR(255) NULL");
    } catch (PDOException $e) {
        // Column already exists
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS user_stats (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        learning_streak INT DEFAULT 0,
        streak_delta INT DEFAULT 0,
        longest_streak INT DEFAULT 0,
        missed_days INT DEFAULT 0,
        inactive_pct INT DEFAULT 0,
        course_progress_pct DECIMAL(5,2) DEFAULT 0.00,
        progress_delta_pct DECIMAL(4,2) DEFAULT 0.00,
        weekly_lessons_current INT DEFAULT 0,
        weekly_lessons_last INT DEFAULT 0,
        study_hours INT DEFAULT 0,
        study_minutes INT DEFAULT 0,
        study_delta_pct DECIMAL(4,2) DEFAULT 0.00,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS courses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        code VARCHAR(10) NOT NULL,
        name VARCHAR(150) NOT NULL,
        category VARCHAR(50) NOT NULL,
        level VARCHAR(50) DEFAULT 'Intermediate',
        total_modules INT DEFAULT 10,
        completed_modules INT DEFAULT 0,
        progress_pct INT DEFAULT 0,
        bg_gradient VARCHAR(100) DEFAULT 'linear-gradient(145deg,#8B7CF0,#5A46E0)',
        ring_color VARCHAR(30) DEFAULT '#6C5CE7',
        is_starred INT DEFAULT 0,
        status VARCHAR(30) DEFAULT 'No status',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    try { $pdo->exec("ALTER TABLE courses ADD COLUMN is_starred INT DEFAULT 0"); } catch (PDOException $e) {}
    try { $pdo->exec("ALTER TABLE courses ADD COLUMN status VARCHAR(30) DEFAULT 'No status'"); } catch (PDOException $e) {}
    try { $pdo->exec("ALTER TABLE courses ADD COLUMN start_date VARCHAR(50) NULL"); } catch (PDOException $e) {}
    try { $pdo->exec("ALTER TABLE courses ADD COLUMN due_date VARCHAR(50) NULL"); } catch (PDOException $e) {}

    $pdo->exec("CREATE TABLE IF NOT EXISTS user_holidays (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        holiday_name VARCHAR(150) NOT NULL,
        holiday_date VARCHAR(50) NOT NULL,
        holiday_type VARCHAR(50) DEFAULT 'custom',
        country_code VARCHAR(10) DEFAULT 'KH',
        is_day_off TINYINT(1) DEFAULT 1,
        notes TEXT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_holidays (user_id, holiday_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS daily_activities (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        activity_date DATE NOT NULL,
        lessons_completed INT DEFAULT 0,
        study_minutes INT DEFAULT 0,
        category VARCHAR(50) DEFAULT 'General',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY user_date (user_id, activity_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS weekly_streaks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        day_index INT NOT NULL,
        day_name VARCHAR(10) NOT NULL,
        is_completed TINYINT(1) DEFAULT 0,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY user_day (user_id, day_index)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS user_skills (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        label VARCHAR(50) NOT NULL,
        score_pct DECIMAL(3,2) NOT NULL DEFAULT 0.50,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS group_assignments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        created_by_user_id INT NOT NULL,
        title VARCHAR(200) NOT NULL,
        course_name VARCHAR(150) NOT NULL,
        due_date VARCHAR(50) NOT NULL,
        description TEXT NULL,
        task_planning TEXT NULL,
        status VARCHAR(30) DEFAULT 'In Progress',
        completed_by_user_id INT NULL,
        completed_by_user_name VARCHAR(100) NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS tasks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        task_name VARCHAR(200) NOT NULL,
        project_name VARCHAR(150) NOT NULL,
        start_date VARCHAR(50) NOT NULL,
        assigned_to VARCHAR(100) DEFAULT 'unassigned',
        status VARCHAR(30) DEFAULT 'Open',
        time_log INT DEFAULT 0,
        deleted_at DATETIME NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS milestones (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_id INT NOT NULL,
        name VARCHAR(200) NOT NULL,
        due_date VARCHAR(50) NOT NULL,
        status VARCHAR(30) DEFAULT 'Pending',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS task_dependencies (
        id INT AUTO_INCREMENT PRIMARY KEY,
        task_id INT NOT NULL,
        depends_on_task_id INT NOT NULL,
        dependency_type VARCHAR(50) DEFAULT 'finish_to_start'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS task_checklists (
        id INT AUTO_INCREMENT PRIMARY KEY,
        task_id INT NOT NULL,
        item_text VARCHAR(255) NOT NULL,
        is_completed TINYINT(1) DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS files (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_id INT NULL,
        task_id INT NULL,
        user_id INT NOT NULL,
        file_name VARCHAR(255) NOT NULL,
        file_path VARCHAR(255) NOT NULL,
        file_size INT DEFAULT 0,
        uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(200) NOT NULL,
        message TEXT NOT NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS activity_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        action_type VARCHAR(100) NOT NULL,
        description TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_activity_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS flashcards (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        course_name VARCHAR(100) NOT NULL,
        question TEXT NOT NULL,
        answer TEXT NOT NULL,
        interval_days INT DEFAULT 1,
        ease_factor DECIMAL(3,2) DEFAULT 2.50,
        due_date DATE NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_flashcards_user_due (user_id, due_date),
        INDEX idx_flashcards_user_course (user_id, course_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS project_goals (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(150) NOT NULL,
        category VARCHAR(50) DEFAULT 'Productivity',
        target_value INT DEFAULT 100,
        current_value INT DEFAULT 0,
        unit VARCHAR(20) DEFAULT '%',
        due_date VARCHAR(50) NULL,
        status VARCHAR(30) DEFAULT 'On Track',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_goals_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS daily_journal (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        entry_date DATE NOT NULL,
        rating INT NOT NULL DEFAULT 3,
        mood_label VARCHAR(50) DEFAULT 'Okay',
        accomplishments TEXT NULL,
        challenges TEXT NULL,
        learning_notes TEXT NULL,
        journal_text TEXT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY user_date_unique (user_id, entry_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS journal_todos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        task_text VARCHAR(255) NOT NULL,
        is_completed TINYINT(1) DEFAULT 0,
        todo_date DATE NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        completed_at DATETIME NULL,
        INDEX idx_todos_user_date (user_id, todo_date, is_completed)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip_address VARCHAR(45) NOT NULL,
        email VARCHAR(190) NOT NULL,
        attempted_at INT NOT NULL,
        INDEX idx_login_ip_time (ip_address, attempted_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    try {
        $stmtFC = $pdo->query("SELECT COUNT(*) as cnt FROM flashcards");
        if ($stmtFC && $stmtFC->fetch()['cnt'] == 0) {
            $pdo->exec("INSERT INTO flashcards (user_id, course_name, question, answer, interval_days, ease_factor, due_date) VALUES
                (1, 'UI Design Mastery', 'What is the 60-30-10 Rule in UI design?', 'A classic aesthetic rule: 60% dominant base color, 30% secondary structure color, and 10% accent color for key call-to-actions and focal points.', 1, 2.50, CURDATE()),
                (1, 'Learn JavaScript', 'What is the key difference between Promise.all and Promise.allSettled?', 'Promise.all rejects immediately when any promise fails, whereas Promise.allSettled waits for all promises to settle and returns an array with status and value/reason for each.', 1, 2.50, CURDATE()),
                (1, 'Python for Data', 'In Pandas, what is the difference between loc and iloc?', 'loc accesses rows and columns by label/index names, whereas iloc accesses strictly by integer positional index coordinates.', 1, 2.50, CURDATE()),
                (1, 'Learn JavaScript', 'What is a closure in JavaScript?', 'A closure is the combination of a function bundled together with references to its surrounding lexical state, allowing an inner function to access an outer function scope even after it has returned.', 1, 2.50, CURDATE())");
        }
    } catch (Throwable $e) {}

    // Seed default data if users table is empty
    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM users");
    if ($stmt->fetch()['cnt'] == 0) {
        $defaultPasswordHash = password_hash('password123', PASSWORD_DEFAULT);
        $stmtInsertUser = $pdo->prepare("INSERT INTO users (id, name, email, password) VALUES (1, 'Alex Morgan', 'alex@mindrift.io', :pass)");
        $stmtInsertUser->execute(['pass' => $defaultPasswordHash]);
        $pdo->exec("INSERT INTO user_stats (user_id, learning_streak, streak_delta, longest_streak, missed_days, inactive_pct, course_progress_pct, progress_delta_pct, weekly_lessons_current, weekly_lessons_last, study_hours, study_minutes, study_delta_pct)
                    VALUES (1, 0, 0, 0, 0, 0, 0.00, 0.00, 0, 0, 0, 0, 0.00)");
        
        $pdo->exec("INSERT INTO courses (user_id, code, name, category, level, total_modules, completed_modules, progress_pct, bg_gradient, ring_color) VALUES
            (1, 'UI', 'UI Design Mastery', 'Design', 'Intermediate', 12, 0, 0, 'linear-gradient(145deg,#8B7CF0,#5A46E0)', '#6C5CE7'),
            (1, 'JS', 'Learn JavaScript', 'Programming', 'Beginner', 8, 0, 0, 'linear-gradient(145deg,#FBAE68,#F2994A)', '#F2994A'),
            (1, 'PS', 'Learn Photoshop', 'Design', 'Advanced', 32, 0, 0, 'linear-gradient(145deg,#6FC1F0,#4FA3E0)', '#4FA3E0'),
            (1, 'PY', 'Python for Data', 'Data Science', 'Intermediate', 20, 0, 0, 'linear-gradient(145deg,#7FD9A5,#2FBE73)', '#2FBE73')");

        $pdo->exec("INSERT INTO weekly_streaks (user_id, day_index, day_name, is_completed) VALUES
            (1, 0, 'Mon', 0), (1, 1, 'Tue', 0), (1, 2, 'Wed', 0), (1, 3, 'Thu', 0),
            (1, 4, 'Fri', 0), (1, 5, 'Sat', 0), (1, 6, 'Sun', 0)");

        $pdo->exec("INSERT INTO user_skills (user_id, label, score_pct) VALUES
            (1, 'Productivity', 0.50), (1, 'Data & Analysis', 0.50), (1, 'Communication', 0.50),
            (1, 'Creativity', 0.50), (1, 'Technical', 0.50)");
    }
}

function ensureSqliteTables(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT UNIQUE NOT NULL,
        password TEXT,
        avatar_url TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );");

    // Auto-migration: ensure password column exists if table was created previously
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN password TEXT");
    } catch (PDOException $e) {
        // Column already exists
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS user_stats (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        learning_streak INTEGER DEFAULT 0,
        streak_delta INTEGER DEFAULT 0,
        longest_streak INTEGER DEFAULT 0,
        missed_days INTEGER DEFAULT 0,
        inactive_pct INTEGER DEFAULT 0,
        course_progress_pct REAL DEFAULT 0.00,
        progress_delta_pct REAL DEFAULT 0.00,
        weekly_lessons_current INTEGER DEFAULT 0,
        weekly_lessons_last INTEGER DEFAULT 0,
        study_hours INTEGER DEFAULT 0,
        study_minutes INTEGER DEFAULT 0,
        study_delta_pct REAL DEFAULT 0.00,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );");

    $pdo->exec("CREATE TABLE IF NOT EXISTS courses (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        code TEXT NOT NULL,
        name TEXT NOT NULL,
        category TEXT NOT NULL,
        level TEXT DEFAULT 'Intermediate',
        total_modules INTEGER DEFAULT 10,
        completed_modules INTEGER DEFAULT 0,
        progress_pct INTEGER DEFAULT 0,
        bg_gradient TEXT,
        ring_color TEXT,
        is_starred INTEGER DEFAULT 0,
        status TEXT DEFAULT 'No status',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );");

    try { $pdo->exec("ALTER TABLE courses ADD COLUMN is_starred INTEGER DEFAULT 0"); } catch (PDOException $e) {}
    try { $pdo->exec("ALTER TABLE courses ADD COLUMN status TEXT DEFAULT 'No status'"); } catch (PDOException $e) {}
    try { $pdo->exec("ALTER TABLE courses ADD COLUMN start_date TEXT NULL"); } catch (PDOException $e) {}
    try { $pdo->exec("ALTER TABLE courses ADD COLUMN due_date TEXT NULL"); } catch (PDOException $e) {}

    $pdo->exec("CREATE TABLE IF NOT EXISTS user_holidays (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        holiday_name TEXT NOT NULL,
        holiday_date TEXT NOT NULL,
        holiday_type TEXT DEFAULT 'custom',
        country_code TEXT DEFAULT 'KH',
        is_day_off INTEGER DEFAULT 1,
        notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );");

    $pdo->exec("CREATE TABLE IF NOT EXISTS daily_activities (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        activity_date DATE NOT NULL,
        lessons_completed INTEGER DEFAULT 0,
        study_minutes INTEGER DEFAULT 0,
        category TEXT DEFAULT 'General',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(user_id, activity_date)
    );");

    $pdo->exec("CREATE TABLE IF NOT EXISTS weekly_streaks (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        day_index INTEGER NOT NULL,
        day_name TEXT NOT NULL,
        is_completed INTEGER DEFAULT 0,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(user_id, day_index)
    );");

    $pdo->exec("CREATE TABLE IF NOT EXISTS user_skills (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        label TEXT NOT NULL,
        score_pct REAL DEFAULT 0.50,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );");

    $pdo->exec("CREATE TABLE IF NOT EXISTS group_assignments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        created_by_user_id INTEGER NOT NULL,
        title TEXT NOT NULL,
        course_name TEXT NOT NULL,
        due_date TEXT NOT NULL,
        description TEXT NULL,
        task_planning TEXT NULL,
        status TEXT DEFAULT 'In Progress',
        completed_by_user_id INTEGER NULL,
        completed_by_user_name TEXT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );");

    $pdo->exec("CREATE TABLE IF NOT EXISTS tasks (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        task_name TEXT NOT NULL,
        project_name TEXT NOT NULL,
        start_date TEXT NOT NULL,
        assigned_to TEXT DEFAULT 'unassigned',
        status TEXT DEFAULT 'Open',
        time_log INTEGER DEFAULT 0,
        deleted_at DATETIME NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );");

    $pdo->exec("CREATE TABLE IF NOT EXISTS milestones (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        project_id INTEGER NOT NULL,
        name TEXT NOT NULL,
        due_date TEXT NOT NULL,
        status TEXT DEFAULT 'Pending',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );");

    $pdo->exec("CREATE TABLE IF NOT EXISTS task_dependencies (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        task_id INTEGER NOT NULL,
        depends_on_task_id INTEGER NOT NULL,
        dependency_type TEXT DEFAULT 'finish_to_start'
    );");

    $pdo->exec("CREATE TABLE IF NOT EXISTS task_checklists (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        task_id INTEGER NOT NULL,
        item_text TEXT NOT NULL,
        is_completed INTEGER DEFAULT 0
    );");

    $pdo->exec("CREATE TABLE IF NOT EXISTS files (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        project_id INTEGER NULL,
        task_id INTEGER NULL,
        user_id INTEGER NOT NULL,
        file_name TEXT NOT NULL,
        file_path TEXT NOT NULL,
        file_size INTEGER DEFAULT 0,
        uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );");

    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        title TEXT NOT NULL,
        message TEXT NOT NULL,
        is_read INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );");

    $pdo->exec("CREATE TABLE IF NOT EXISTS activity_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        action_type TEXT NOT NULL,
        description TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );");

    $pdo->exec("CREATE TABLE IF NOT EXISTS flashcards (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        course_name TEXT NOT NULL,
        question TEXT NOT NULL,
        answer TEXT NOT NULL,
        interval_days INTEGER DEFAULT 1,
        ease_factor REAL DEFAULT 2.50,
        due_date TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );");

    $pdo->exec("CREATE TABLE IF NOT EXISTS project_goals (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        title TEXT NOT NULL,
        category TEXT DEFAULT 'Productivity',
        target_value INTEGER DEFAULT 100,
        current_value INTEGER DEFAULT 0,
        unit TEXT DEFAULT '%',
        due_date TEXT NULL,
        status TEXT DEFAULT 'On Track',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );");

    $pdo->exec("CREATE TABLE IF NOT EXISTS daily_journal (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        entry_date TEXT NOT NULL,
        rating INTEGER NOT NULL DEFAULT 3,
        mood_label TEXT DEFAULT 'Okay',
        accomplishments TEXT NULL,
        challenges TEXT NULL,
        learning_notes TEXT NULL,
        journal_text TEXT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(user_id, entry_date)
    );");

    $pdo->exec("CREATE TABLE IF NOT EXISTS journal_todos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        task_text TEXT NOT NULL,
        is_completed INTEGER DEFAULT 0,
        todo_date TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        completed_at DATETIME NULL
    );");

    $pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        ip_address TEXT NOT NULL,
        email TEXT NOT NULL,
        attempted_at INTEGER NOT NULL
    );");

    try {
        $stmtFC = $pdo->query("SELECT COUNT(*) as cnt FROM flashcards");
        if ($stmtFC && $stmtFC->fetch()['cnt'] == 0) {
            $pdo->exec("INSERT INTO flashcards (user_id, course_name, question, answer, interval_days, ease_factor, due_date) VALUES
                (1, 'UI Design Mastery', 'What is the 60-30-10 Rule in UI design?', 'A classic aesthetic rule: 60% dominant base color, 30% secondary structure color, and 10% accent color for key call-to-actions and focal points.', 1, 2.50, date('now')),
                (1, 'Learn JavaScript', 'What is the key difference between Promise.all and Promise.allSettled?', 'Promise.all rejects immediately when any promise fails, whereas Promise.allSettled waits for all promises to settle and returns an array with status and value/reason for each.', 1, 2.50, date('now')),
                (1, 'Python for Data', 'In Pandas, what is the difference between loc and iloc?', 'loc accesses rows and columns by label/index names, whereas iloc accesses strictly by integer positional index coordinates.', 1, 2.50, date('now')),
                (1, 'Learn JavaScript', 'What is a closure in JavaScript?', 'A closure is the combination of a function bundled together with references to its surrounding lexical state, allowing an inner function to access an outer function scope even after it has returned.', 1, 2.50, date('now'))");
        }
    } catch (Throwable $e) {}

    // Seed default user if empty
    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM users");
    if ($stmt->fetch()['cnt'] == 0) {
        $defaultPasswordHash = password_hash('password123', PASSWORD_DEFAULT);
        $stmtInsertUser = $pdo->prepare("INSERT INTO users (id, name, email, password) VALUES (1, 'Alex Morgan', 'alex@mindrift.io', :pass)");
        $stmtInsertUser->execute(['pass' => $defaultPasswordHash]);
        $pdo->exec("INSERT INTO user_stats (user_id, learning_streak, streak_delta, longest_streak, missed_days, inactive_pct, course_progress_pct, progress_delta_pct, weekly_lessons_current, weekly_lessons_last, study_hours, study_minutes, study_delta_pct)
                    VALUES (1, 0, 0, 0, 0, 0, 0.00, 0.00, 0, 0, 0, 0, 0.00)");
        
        $pdo->exec("INSERT INTO courses (user_id, code, name, category, level, total_modules, completed_modules, progress_pct, bg_gradient, ring_color) VALUES
            (1, 'UI', 'UI Design Mastery', 'Design', 'Intermediate', 12, 0, 0, 'linear-gradient(145deg,#8B7CF0,#5A46E0)', '#6C5CE7'),
            (1, 'JS', 'Learn JavaScript', 'Programming', 'Beginner', 8, 0, 0, 'linear-gradient(145deg,#FBAE68,#F2994A)', '#F2994A'),
            (1, 'PS', 'Learn Photoshop', 'Design', 'Advanced', 32, 0, 0, 'linear-gradient(145deg,#6FC1F0,#4FA3E0)', '#4FA3E0'),
            (1, 'PY', 'Python for Data', 'Data Science', 'Intermediate', 20, 0, 0, 'linear-gradient(145deg,#7FD9A5,#2FBE73)', '#2FBE73')");

        $pdo->exec("INSERT INTO weekly_streaks (user_id, day_index, day_name, is_completed) VALUES
            (1, 0, 'Mon', 0), (1, 1, 'Tue', 0), (1, 2, 'Wed', 0), (1, 3, 'Thu', 0),
            (1, 4, 'Fri', 0), (1, 5, 'Sat', 0), (1, 6, 'Sun', 0)");

        $pdo->exec("INSERT INTO user_skills (user_id, label, score_pct) VALUES
            (1, 'Productivity', 0.50), (1, 'Data & Analysis', 0.50), (1, 'Communication', 0.50),
            (1, 'Creativity', 0.50), (1, 'Technical', 0.50)");
    }
}

/**
 * Safely parse incoming JSON request body with POST fallback
 */
function getJsonRequestData(): array {
    $raw = file_get_contents('php://input');
    if (!empty($raw)) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }
    if (!empty($_POST)) {
        return $_POST;
    }
    // CLI fallback
    if (php_sapi_name() === 'cli') {
        $stdin = @file_get_contents('php://stdin');
        if (!empty($stdin)) {
            $decoded = json_decode($stdin, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
    }
    return [];
}

/**
 * Helper to emit JSON response safely with security headers.
 */
function sendJsonResponse(array $data, int $statusCode = 200): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        @session_write_close();
    }

    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');

    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        http_response_code(200);
        exit;
    }

    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Cache-busting version helper for static assets
 */
function assetVersion(string $relPath = '/assets/css/style.css'): string {
    $full = __DIR__ . '/..' . $relPath;
    return file_exists($full) ? (string)filemtime($full) : '1.0';
}

/**
 * Retrieve or generate a persistent share token for the user's schedule.
 */
function getUserScheduleToken(PDO $db, int $userId): string {
    try {
        $stmt = $db->prepare("SELECT schedule_token FROM users WHERE id = :uid");
        $stmt->execute(['uid' => $userId]);
        $row = $stmt->fetch();
        if (!empty($row['schedule_token'])) {
            return $row['schedule_token'];
        }
    } catch (Throwable $e) {
        try {
            $db->exec("ALTER TABLE users ADD COLUMN schedule_token TEXT NULL");
        } catch (Throwable $ex) {}
    }

    $newToken = bin2hex(random_bytes(24));
    try {
        $stmt = $db->prepare("UPDATE users SET schedule_token = :t WHERE id = :uid");
        $stmt->execute(['t' => $newToken, 'uid' => $userId]);
        return $newToken;
    } catch (Throwable $e) {
        return hash_hmac('sha256', 'user_' . $userId, 'mindrift_schedule_salt_' . $userId);
    }
}

/**
 * Regenerate a user's schedule share token, revoking all existing shared links.
 */
function regenerateUserScheduleToken(PDO $db, int $userId): string {
    $newToken = bin2hex(random_bytes(24));
    try {
        $stmt = $db->prepare("UPDATE users SET schedule_token = :t WHERE id = :uid");
        $stmt->execute(['t' => $newToken, 'uid' => $userId]);
        return $newToken;
    } catch (Throwable $e) {
        return $newToken;
    }
}

/**
 * Look up a user by their schedule share token.
 */
function getUserByScheduleToken(PDO $db, string $token): ?array {
    $token = trim($token);
    if (empty($token) || strlen($token) < 16) return null;
    try {
        $stmt = $db->prepare("SELECT id, name, email FROM users WHERE schedule_token = :t");
        $stmt->execute(['t' => $token]);
        $user = $stmt->fetch();
        if ($user) return $user;
    } catch (Throwable $e) {}

    // Check deterministic fallback
    try {
        $stmt = $db->query("SELECT id, name, email FROM users");
        while ($u = $stmt->fetch()) {
            if (hash_hmac('sha256', 'user_' . $u['id'], 'mindrift_schedule_salt_' . $u['id']) === $token) {
                return $u;
            }
        }
    } catch (Throwable $e) {}

    return null;
}

