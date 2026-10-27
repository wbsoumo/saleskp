<?php
/**
 * Sign Out Script
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

logout_user();
set_flash('info', 'You have been successfully logged out.');
header('Location: ' . get_base_url() . '/login.php');
exit;
