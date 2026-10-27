<?php
/**
 * Daily Attendance Action Handler
 * Processes employee attendance submission (Present, Half Day, Absent)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_employee();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . get_base_url() . '/employee/workspace.php');
    exit;
}

// Verify CSRF
$token = $_POST['csrf_token'] ?? '';
if (!verify_csrf_token($token)) {
    set_flash('error', 'Security session expired. Please try again.');
    $fallbackUrl = get_base_url() . '/employee/workspace.php';
    header('Location: ' . $fallbackUrl);
    exit;
}

$userId = current_user_id();
$status = clean_string($_POST['status'] ?? '');
$remarks = clean_string($_POST['remarks'] ?? '');
$redirectUrl = clean_string($_POST['redirect_url'] ?? '');

if (empty($redirectUrl) || !str_starts_with($redirectUrl, '/')) {
    $redirectUrl = get_base_url() . '/employee/workspace.php';
}

$result = record_employee_attendance($userId, $status, $remarks);

if ($result['success']) {
    set_flash('success', $result['message']);
} else {
    set_flash('warning', $result['error']);
}

header('Location: ' . $redirectUrl);
exit;
