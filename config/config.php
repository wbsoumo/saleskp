<?php
/**
 * Application Configuration
 * Telecaller Reporting Web App & Lead CRM
 */

// Start session securely if not already started
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    // If running over HTTPS, mark cookies as secure
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', '1');
    }
    session_start();
}

// Basic App Information
define('APP_NAME', 'Telecaller Pro');
define('APP_VERSION', '1.1.0');
define('DEFAULT_COMPANY_NAME', 'TeleConnect Sales CRM');

// Path Definitions
define('ROOT_PATH', dirname(__DIR__));
define('UPLOADS_PATH', ROOT_PATH . '/uploads');
define('RECORDINGS_PATH', ROOT_PATH . '/uploads/recordings');

// Timezone Setting
date_default_timezone_set('Asia/Kolkata');

// ==========================================================
// Database Configuration
// Configured with production credentials provided by user
// ==========================================================
define('DB_DRIVER', 'mysql');               // 'mysql' (standard production) or 'sqlite' (local test)
define('DB_HOST', 'localhost');             // 'localhost' or '127.0.0.1'
define('DB_PORT', '3306');
define('DB_NAME', 'helnovexaa_kpsales');    // Database name
define('DB_USER', 'helnovexaa_kpsales');    // Database username
define('DB_PASS', 'Soumojit1234@');         // Database password
define('DB_CHARSET', 'utf8mb4');

// Fallback SQLite file for zero-config local testing if MySQL service is not available
define('SQLITE_FILE', ROOT_PATH . '/data/telecaller.sqlite');

// ==========================================================
// Upload Configuration
// ==========================================================
define('MAX_UPLOAD_SIZE_MB', 25);
define('MAX_UPLOAD_SIZE_BYTES', MAX_UPLOAD_SIZE_MB * 1024 * 1024);

// Allowed audio extensions
$ALLOWED_AUDIO_EXTENSIONS = ['mp3', 'wav', 'm4a', 'ogg', 'aac', 'amr', 'webm'];

// Allowed MIME types
$ALLOWED_AUDIO_MIMES = [
    'audio/mpeg',
    'audio/mp3',
    'audio/wav',
    'audio/x-wav',
    'audio/x-m4a',
    'audio/m4a',
    'audio/mp4',
    'audio/x-mp4',
    'audio/ogg',
    'audio/x-ogg',
    'audio/aac',
    'audio/x-aac',
    'audio/amr',
    'audio/3gpp',
    'audio/webm',
    'application/octet-stream' // Mobile browser audio upload fallback
];

// Lead & Call Status Options
$CALL_STATUS_OPTIONS = [
    'New'                => ['label' => 'New / Untouched',     'badge' => 'badge-light border text-muted', 'icon' => 'fa-star',        'connected' => false],
    'Interested'         => ['label' => 'Interested',          'badge' => 'badge-info',                   'icon' => 'fa-thumbs-up',    'connected' => true],
    'Follow-up Required' => ['label' => 'Follow-up Required',  'badge' => 'badge-primary',                'icon' => 'fa-clock',        'connected' => true],
    'Meeting Scheduled'  => ['label' => 'Meeting Scheduled',   'badge' => 'badge-warning text-dark',      'icon' => 'fa-calendar-alt', 'connected' => true],
    'Sales Closed'       => ['label' => 'Sales Closed',        'badge' => 'badge-success',                'icon' => 'fa-trophy',       'connected' => true],
    'Not Interested'     => ['label' => 'Not Interested',      'badge' => 'badge-secondary',              'icon' => 'fa-thumbs-down',  'connected' => true],
    'Ringing'            => ['label' => 'Ringing / No Pick',   'badge' => 'badge-light border text-dark', 'icon' => 'fa-bell',         'connected' => false],
    'Not Answered'       => ['label' => 'Not Answered',        'badge' => 'badge-warning',                'icon' => 'fa-phone-slash',  'connected' => false],
    'Busy'               => ['label' => 'Busy',                'badge' => 'badge-dark',                   'icon' => 'fa-user-clock',   'connected' => false],
    'Switched Off'       => ['label' => 'Switched Off',        'badge' => 'badge-secondary',              'icon' => 'fa-power-off',    'connected' => false],
    'Invalid Number'     => ['label' => 'Invalid Number',      'badge' => 'badge-danger',                 'icon' => 'fa-times-circle', 'connected' => false],
];

// Helper to check if a call outcome counts as "Connected Call"
function is_call_connected(string $outcome): bool {
    global $CALL_STATUS_OPTIONS;
    return !empty($CALL_STATUS_OPTIONS[$outcome]['connected']);
}

// Auto-detect base URL helper
function get_base_url(): string {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8080';
    $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
    // Clean scriptDir to find root
    $cleanPath = rtrim(str_replace(['/admin', '/employee'], '', $scriptDir), '/\\');
    return $protocol . $host . ($cleanPath === '' ? '' : $cleanPath);
}

// Flash Message Helpers
function set_flash(string $type, string $message): void {
    $_SESSION['flash'] = [
        'type' => $type, // 'success', 'danger', 'warning', 'info'
        'message' => $message
    ];
}

function get_flash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}
