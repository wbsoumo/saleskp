<?php
/**
 * Database Connection & Initialization Helper
 * Telecaller Reporting Web App & Lead CRM
 */

require_once __DIR__ . '/config.php';

class Database {
    private static ?PDO $instance = null;
    private static string $activeDriver = 'mysql';
    private static bool $isFallback = false;

    public static function getConnection(): PDO {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $preferredDriver = strtolower(DB_DRIVER);

        if ($preferredDriver === 'mysql') {
            try {
                $dsn = sprintf(
                    'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                    DB_HOST,
                    DB_PORT,
                    DB_NAME,
                    DB_CHARSET
                );
                $options = [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ];

                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
                self::$instance->exec("SET NAMES utf8mb4");
                self::$activeDriver = 'mysql';
                self::$isFallback = false;
                self::ensureTablesExist(self::$instance, 'mysql');
                return self::$instance;
            } catch (PDOException $e) {
                // If MySQL connection fails and SQLite PDO is available, use SQLite fallback for local development
                if (in_array('sqlite', PDO::getAvailableDrivers())) {
                    self::$isFallback = true;
                    self::$activeDriver = 'sqlite';
                    return self::initSqliteFallback();
                }
                // If SQLite is not available, stop with clear guidance
                die("<h3>Database Connection Error:</h3><p>" . htmlspecialchars($e->getMessage()) . "</p><p>Please check your MySQL configuration in <code>config/config.php</code> or import <code>schema.sql</code>.</p>");
            }
        } elseif ($preferredDriver === 'sqlite') {
            self::$activeDriver = 'sqlite';
            self::$isFallback = false;
            return self::initSqliteFallback();
        } else {
            die("Unsupported DB_DRIVER: " . htmlspecialchars(DB_DRIVER));
        }
    }

    private static function initSqliteFallback(): PDO {
        $sqliteDir = dirname(SQLITE_FILE);
        if (!is_dir($sqliteDir)) {
            mkdir($sqliteDir, 0755, true);
        }

        $dsn = 'sqlite:' . SQLITE_FILE;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        self::$instance = new PDO($dsn, null, null, $options);
        self::$instance->exec("PRAGMA foreign_keys = ON;");
        self::$activeDriver = 'sqlite';
        self::ensureTablesExist(self::$instance, 'sqlite');
        return self::$instance;
    }

    public static function getActiveDriver(): string {
        return self::$activeDriver;
    }

    public static function isFallback(): bool {
        return self::$isFallback;
    }

    /**
     * Automatically ensure required tables and default seeds exist
     */
    private static function ensureTablesExist(PDO $pdo, string $driver): void {
        try {
            if ($driver === 'sqlite') {
                $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'");
                $hasUsers = (bool) $stmt->fetch();

                $stmtLeads = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='leads'");
                $hasLeads = (bool) $stmtLeads->fetch();

                $stmtAtt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='attendance'");
                $hasAtt = (bool) $stmtAtt->fetch();

                if (!$hasUsers || !$hasLeads || !$hasAtt) {
                    self::createSchema($pdo, $driver);
                }
            } else {
                $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
                $hasUsers = (bool) $stmt->fetch();

                $stmtLeads = $pdo->query("SHOW TABLES LIKE 'leads'");
                $hasLeads = (bool) $stmtLeads->fetch();

                $stmtAtt = $pdo->query("SHOW TABLES LIKE 'attendance'");
                $hasAtt = (bool) $stmtAtt->fetch();

                if (!$hasUsers || !$hasLeads || !$hasAtt) {
                    self::createSchema($pdo, $driver);
                }
            }
        } catch (Exception $e) {
            error_log("Table check error: " . $e->getMessage());
        }
    }

    /**
     * Create database schema if not present
     */
    private static function createSchema(PDO $pdo, string $driver): void {
        if ($driver === 'sqlite') {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS users (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    employee_id TEXT UNIQUE NOT NULL,
                    name TEXT NOT NULL,
                    email TEXT,
                    phone TEXT,
                    password TEXT NOT NULL,
                    role TEXT NOT NULL DEFAULT 'employee',
                    status TEXT NOT NULL DEFAULT 'active',
                    last_login DATETIME,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                );

                CREATE TABLE IF NOT EXISTS leads (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT NOT NULL,
                    phone TEXT NOT NULL,
                    company TEXT,
                    email TEXT,
                    city TEXT,
                    notes TEXT,
                    assigned_to INTEGER,
                    status TEXT NOT NULL DEFAULT 'New',
                    call_attempts INTEGER NOT NULL DEFAULT 0,
                    last_called_at DATETIME,
                    last_call_status TEXT,
                    follow_up_date DATETIME,
                    created_by INTEGER,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
                    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
                );

                CREATE TABLE IF NOT EXISTS lead_call_logs (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    lead_id INTEGER NOT NULL,
                    user_id INTEGER NOT NULL,
                    call_outcome TEXT NOT NULL,
                    call_duration TEXT,
                    follow_up_date DATETIME,
                    remarks TEXT,
                    recording_path TEXT,
                    recording_name TEXT,
                    recording_size INTEGER DEFAULT 0,
                    recording_mime TEXT,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                );

                CREATE TABLE IF NOT EXISTS attendance (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    attendance_date DATE NOT NULL,
                    status TEXT NOT NULL DEFAULT 'Present',
                    punch_time DATETIME DEFAULT CURRENT_TIMESTAMP,
                    punch_ip TEXT,
                    employee_remarks TEXT,
                    is_modified INTEGER NOT NULL DEFAULT 0,
                    modified_by INTEGER,
                    modified_at DATETIME,
                    admin_remarks TEXT,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE(user_id, attendance_date),
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    FOREIGN KEY (modified_by) REFERENCES users(id) ON DELETE SET NULL
                );

                CREATE TABLE IF NOT EXISTS daily_reports (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    report_date DATE NOT NULL,
                    total_calls INTEGER NOT NULL DEFAULT 0,
                    call_attempts INTEGER NOT NULL DEFAULT 0,
                    interested_leads INTEGER NOT NULL DEFAULT 0,
                    meetings_booked INTEGER NOT NULL DEFAULT 0,
                    sales_closed INTEGER NOT NULL DEFAULT 0,
                    notes TEXT,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE(user_id, report_date),
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                );

                CREATE TABLE IF NOT EXISTS call_recordings (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    report_id INTEGER NOT NULL,
                    user_id INTEGER NOT NULL,
                    phone_number TEXT NOT NULL,
                    customer_name TEXT,
                    call_status TEXT NOT NULL DEFAULT 'Interested',
                    call_duration TEXT,
                    call_notes TEXT,
                    file_path TEXT NOT NULL,
                    file_name TEXT NOT NULL,
                    original_name TEXT NOT NULL,
                    file_size INTEGER NOT NULL DEFAULT 0,
                    file_mime TEXT NOT NULL DEFAULT 'audio/mpeg',
                    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (report_id) REFERENCES daily_reports(id) ON DELETE CASCADE,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                );

                CREATE TABLE IF NOT EXISTS system_settings (
                    setting_key TEXT PRIMARY KEY,
                    setting_value TEXT NOT NULL
                );
            ");
        } else {
            // For MySQL, execute schema.sql
            $schemaFile = ROOT_PATH . '/schema.sql';
            if (file_exists($schemaFile)) {
                $sql = file_get_contents($schemaFile);
                $pdo->exec($sql);
                return;
            }
        }

        // Seed initial admin and demo telecallers if not exist
        $adminPassword = password_hash('Admin@123', PASSWORD_BCRYPT);
        $empPassword = password_hash('telecaller123', PASSWORD_BCRYPT);

        $stmt = $pdo->prepare("INSERT OR IGNORE INTO users (employee_id, name, email, phone, password, role, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute(['admin', 'System Administrator', 'admin@telecaller.com', '+91 9876543210', $adminPassword, 'admin', 'active']);
        $stmt->execute(['EMP001', 'Rahul Sharma', 'rahul.s@telecaller.com', '+91 9811122233', $empPassword, 'employee', 'active']);
        $stmt->execute(['EMP002', 'Priya Patel', 'priya.p@telecaller.com', '+91 9822233344', $empPassword, 'employee', 'active']);

        // Seed initial settings
        $stmtSet = $pdo->prepare("INSERT OR IGNORE INTO system_settings (setting_key, setting_value) VALUES (?, ?)");
        $stmtSet->execute(['company_name', 'TeleConnect Sales CRM']);
        $stmtSet->execute(['max_upload_size_mb', '25']);
        $stmtSet->execute(['allowed_extensions', 'mp3,wav,m4a,ogg,aac,amr,webm']);
        $stmtSet->execute(['timezone', 'Asia/Kolkata']);
    }
}

// Global helper for fetching DB connection
function get_db(): PDO {
    return Database::getConnection();
}

// System setting helpers
function get_setting(string $key, string $default = ''): string {
    try {
        $stmt = get_db()->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1");
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        return $val !== false ? (string)$val : $default;
    } catch (Exception $e) {
        return $default;
    }
}

function update_setting(string $key, string $value): bool {
    try {
        $db = get_db();
        if (Database::getActiveDriver() === 'sqlite') {
            $stmt = $db->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON CONFLICT(setting_key) DO UPDATE SET setting_value = excluded.setting_value");
        } else {
            $stmt = $db->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        }
        return $stmt->execute([$key, $value]);
    } catch (Exception $e) {
        return false;
    }
}
