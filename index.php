<?php
/**
 * Root Router / Entry Point
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    if (is_admin()) {
        header('Location: ' . get_base_url() . '/admin/index.php');
    } else {
        header('Location: ' . get_base_url() . '/employee/workspace.php');
    }
} else {
    header('Location: ' . get_base_url() . '/login.php');
}
exit;
