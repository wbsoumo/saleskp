<?php
/**
 * Employee Portal Index
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

require_employee();
header('Location: ' . get_base_url() . '/employee/workspace.php');
exit;
