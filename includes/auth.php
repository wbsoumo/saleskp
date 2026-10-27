<?php
/**
 * Authentication and Authorization System
 * Telecaller Reporting Web App
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';

/**
 * Check if a user is currently logged in
 */
function is_logged_in(): bool {
    return !empty($_SESSION['user_id']) && !empty($_SESSION['role']);
}

/**
 * Get the current logged-in user array
 */
function current_user(): ?array {
    if (!is_logged_in()) {
        return null;
    }

    return [
        'id'          => $_SESSION['user_id'],
        'employee_id' => $_SESSION['employee_id'] ?? '',
        'name'        => $_SESSION['user_name'] ?? 'User',
        'email'       => $_SESSION['user_email'] ?? '',
        'role'        => $_SESSION['role'] ?? 'employee',
        'phone'       => $_SESSION['user_phone'] ?? ''
    ];
}

/**
 * Get current user ID
 */
function current_user_id(): ?int {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Check if user is an Admin
 */
function is_admin(): bool {
    return is_logged_in() && ($_SESSION['role'] ?? '') === 'admin';
}

/**
 * Check if user is an Employee
 */
function is_employee(): bool {
    return is_logged_in() && ($_SESSION['role'] ?? '') === 'employee';
}

/**
 * Enforce login requirement
 */
function require_login(): void {
    if (!is_logged_in()) {
        set_flash('danger', 'You must log in to access this page.');
        header('Location: ' . get_base_url() . '/login.php');
        exit;
    }
}

/**
 * Enforce admin-only access
 */
function require_admin(): void {
    require_login();
    if (!is_admin()) {
        set_flash('danger', 'Access denied. Administrator privileges required.');
        header('Location: ' . get_base_url() . '/employee/today_report.php');
        exit;
    }
}

/**
 * Enforce employee access
 */
function require_employee(): void {
    require_login();
    // Admin can also view or switch, but employees cannot view admin
    if (!is_employee() && !is_admin()) {
        set_flash('danger', 'Access denied. Employee privileges required.');
        header('Location: ' . get_base_url() . '/login.php');
        exit;
    }
}

/**
 * Authenticate credentials
 * @param string $loginId Can be Employee ID (e.g. EMP001, admin) or Email address
 * @param string $password
 * @return array ['success' => bool, 'user' => ?array, 'error' => ?string]
 */
function authenticate_user(string $loginId, string $password): array {
    $cleanLoginId = trim($loginId);
    if (empty($cleanLoginId) || empty($password)) {
        return ['success' => false, 'error' => 'Please provide both your Employee ID / Username and Password.'];
    }

    $db = get_db();
    $stmt = $db->prepare("SELECT * FROM users WHERE (LOWER(employee_id) = LOWER(?) OR LOWER(email) = LOWER(?)) LIMIT 1");
    $stmt->execute([$cleanLoginId, $cleanLoginId]);
    $user = $stmt->fetch();

    if (!$user) {
        return ['success' => false, 'error' => 'Invalid Employee ID or Password.'];
    }

    if ($user['status'] !== 'active') {
        return ['success' => false, 'error' => 'Your account is currently inactive. Please contact the administrator.'];
    }

    if (!password_verify($password, $user['password'])) {
        return ['success' => false, 'error' => 'Invalid Employee ID or Password.'];
    }

    // Update last login timestamp
    try {
        $updateStmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $updateStmt->execute([$user['id']]);
    } catch (Exception $e) {
        // Fallback for SQLite datetime
        $updateStmt = $db->prepare("UPDATE users SET last_login = datetime('now') WHERE id = ?");
        $updateStmt->execute([$user['id']]);
    }

    return ['success' => true, 'user' => $user];
}

/**
 * Establish authenticated session
 */
function login_user(array $user): void {
    // Regenerate session ID to prevent session fixation attacks
    session_regenerate_id(true);

    $_SESSION['user_id']     = (int)$user['id'];
    $_SESSION['employee_id'] = $user['employee_id'];
    $_SESSION['user_name']   = $user['name'];
    $_SESSION['user_email']  = $user['email'];
    $_SESSION['user_phone']  = $user['phone'] ?? '';
    $_SESSION['role']        = $user['role'];
    $_SESSION['logged_in_at']= time();
}

/**
 * Terminate user session
 */
function logout_user(): void {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}
