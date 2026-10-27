<?php
/**
 * Global Header Component (AdminLTE 3)
 * Telecaller Reporting Web App
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

$currentUser = current_user();
$companyName = get_setting('company_name', DEFAULT_COMPANY_NAME);
$pageTitle = $pageTitle ?? APP_NAME;
$baseUrl = get_base_url();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> | <?= e($companyName) ?></title>

    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,600,700&display=fallback">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- AdminLTE 3 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap4.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/custom.css">
    <!-- jQuery (loaded in head so all inline scripts and modals have immediate access to $) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed text-sm">
<div class="wrapper">

    <!-- Navbar -->
    <nav class="main-header navbar navbar-expand navbar-white navbar-light border-bottom-0 shadow-sm">
        <!-- Left navbar links -->
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
            </li>
            <li class="nav-item d-none d-sm-inline-block">
                <span class="nav-link font-weight-bold text-dark">
                    <?= e($companyName) ?>
                </span>
            </li>
        </ul>

        <!-- Right navbar links -->
        <ul class="navbar-nav ml-auto align-items-center">
            <?php if (Database::isFallback()): ?>
                <li class="nav-item mr-2">
                    <span class="badge badge-warning p-1" title="SQLite demo fallback is active because local MySQL is not started. Production-ready for MySQL!">
                        <i class="fas fa-info-circle mr-1"></i> SQLite Mode
                    </span>
                </li>
            <?php endif; ?>

            <?php if ($currentUser): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link d-flex align-items-center" data-toggle="dropdown" href="#">
                        <div class="rounded-circle bg-primary text-white d-inline-flex justify-content-center align-items-center mr-2" style="width: 32px; height: 32px; font-size: 14px;">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="d-none d-md-block text-left">
                            <span class="font-weight-bold d-block text-dark leading-tight" style="line-height: 1.1; font-size: 0.9rem;">
                                <?= e($currentUser['name']) ?>
                            </span>
                            <small class="text-muted text-uppercase" style="font-size: 0.72rem;">
                                <?= e($currentUser['role']) ?> (<?= e($currentUser['employee_id']) ?>)
                            </small>
                        </div>
                        <i class="fas fa-chevron-down ml-2 text-muted" style="font-size: 0.75rem;"></i>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right shadow border-0">
                        <div class="dropdown-header text-center py-2 bg-light">
                            <strong><?= e($currentUser['name']) ?></strong>
                            <div class="small text-muted"><?= e($currentUser['employee_id']) ?></div>
                        </div>
                        <div class="dropdown-divider"></div>
                        <?php if ($currentUser['role'] === 'admin'): ?>
                            <a href="<?= $baseUrl ?>/admin/settings.php" class="dropdown-item">
                                <i class="fas fa-sliders-h mr-2 text-primary"></i> System Settings
                            </a>
                        <?php endif; ?>
                        <a href="<?= $baseUrl ?>/logout.php" class="dropdown-item text-danger">
                            <i class="fas fa-sign-out-alt mr-2"></i> Sign Out
                        </a>
                    </div>
                </li>
            <?php endif; ?>
        </ul>
    </nav>
    <!-- /.navbar -->
