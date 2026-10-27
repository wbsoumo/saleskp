<?php
/**
 * Global Sidebar Navigation (AdminLTE 3)
 * Telecaller Reporting Web App & Lead CRM
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/auth.php';

$currentUser = current_user();
$activeNav = $activeNav ?? '';
$baseUrl = get_base_url();
$isAdmin = is_admin();
?>
<!-- Main Sidebar Container -->
<aside class="main-sidebar sidebar-dark-primary elevation-4">
    <!-- Brand Logo -->
    <a href="<?= $isAdmin ? $baseUrl . '/admin/index.php' : $baseUrl . '/employee/workspace.php' ?>" class="brand-link bg-dark text-center py-3">
        <i class="fas fa-headset mr-2 text-primary" style="font-size: 1.25rem;"></i>
        <span class="brand-text font-weight-bold">Telecaller Pro</span>
    </a>

    <!-- Sidebar -->
    <div class="sidebar">
        <!-- Sidebar user panel -->
        <div class="user-panel mt-3 pb-3 mb-3 d-flex align-items-center">
            <div class="image">
                <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                    <i class="fas <?= $isAdmin ? 'fa-user-shield' : 'fa-user' ?>"></i>
                </div>
            </div>
            <div class="info">
                <a href="#" class="d-block font-weight-bold text-white"><?= e($currentUser['name'] ?? 'User') ?></a>
                <span class="badge <?= $isAdmin ? 'badge-danger' : 'badge-info' ?> text-uppercase">
                    <?= e($currentUser['role'] ?? 'guest') ?>: <?= e($currentUser['employee_id'] ?? '') ?>
                </span>
            </div>
        </div>

        <!-- Sidebar Menu -->
        <nav class="mt-2">
            <ul class="nav nav-pills nav-sidebar flex-column nav-child-indent" data-widget="treeview" role="menu" data-accordion="false">
                
                <?php if ($isAdmin): ?>
                    <!-- ADMIN NAVIGATION -->
                    <li class="nav-header text-uppercase font-weight-bold text-muted">Admin Controls</li>

                    <li class="nav-item">
                        <a href="<?= $baseUrl ?>/admin/index.php" class="nav-link <?= $activeNav === 'admin_dashboard' ? 'active' : '' ?>">
                            <i class="nav-icon fas fa-tachometer-alt"></i>
                            <p>Dashboard</p>
                        </a>
                    </li>

                    <li class="nav-item">
                        <a href="<?= $baseUrl ?>/admin/leads.php" class="nav-link <?= $activeNav === 'admin_leads' ? 'active' : '' ?>">
                            <i class="nav-icon fas fa-user-friends"></i>
                            <p>Leads & Import</p>
                        </a>
                    </li>

                    <li class="nav-item">
                        <a href="<?= $baseUrl ?>/admin/employees.php" class="nav-link <?= $activeNav === 'admin_employees' ? 'active' : '' ?>">
                            <i class="nav-icon fas fa-users-cog"></i>
                            <p>Manage Employees</p>
                        </a>
                    </li>

                    <li class="nav-item">
                        <a href="<?= $baseUrl ?>/admin/reports.php" class="nav-link <?= $activeNav === 'admin_reports' ? 'active' : '' ?>">
                            <i class="nav-icon fas fa-chart-line"></i>
                            <p>Daily Reports</p>
                        </a>
                    </li>

                    <li class="nav-item">
                        <a href="<?= $baseUrl ?>/admin/recordings.php" class="nav-link <?= $activeNav === 'admin_recordings' ? 'active' : '' ?>">
                            <i class="nav-icon fas fa-microphone-alt"></i>
                            <p>Call Recordings</p>
                        </a>
                    </li>

                    <li class="nav-item">
                        <a href="<?= $baseUrl ?>/admin/settings.php" class="nav-link <?= $activeNav === 'admin_settings' ? 'active' : '' ?>">
                            <i class="nav-icon fas fa-sliders-h"></i>
                            <p>System Settings</p>
                        </a>
                    </li>

                    <li class="nav-header text-uppercase font-weight-bold text-muted">Preview Employee View</li>
                    <li class="nav-item">
                        <a href="<?= $baseUrl ?>/employee/workspace.php" class="nav-link <?= $activeNav === 'emp_workspace' ? 'active' : '' ?>">
                            <i class="nav-icon fas fa-laptop-house"></i>
                            <p>Calling Workspace</p>
                        </a>
                    </li>

                <?php else: ?>
                    <!-- EMPLOYEE NAVIGATION -->
                    <li class="nav-header text-uppercase font-weight-bold text-muted">Calling Workspace</li>

                    <li class="nav-item">
                        <a href="<?= $baseUrl ?>/employee/workspace.php" class="nav-link <?= $activeNav === 'emp_workspace' ? 'active' : '' ?>">
                            <i class="nav-icon fas fa-laptop-house"></i>
                            <p>Workspace</p>
                        </a>
                    </li>

                    <li class="nav-header text-uppercase font-weight-bold text-muted">Daily Reports</li>

                    <li class="nav-item">
                        <a href="<?= $baseUrl ?>/employee/today_report.php" class="nav-link <?= $activeNav === 'emp_today' ? 'active' : '' ?>">
                            <i class="nav-icon fas fa-calendar-day"></i>
                            <p>Today's Report</p>
                        </a>
                    </li>

                    <li class="nav-item">
                        <a href="<?= $baseUrl ?>/employee/reports.php" class="nav-link <?= $activeNav === 'emp_reports' ? 'active' : '' ?>">
                            <i class="nav-icon fas fa-history"></i>
                            <p>Reports</p>
                        </a>
                    </li>
                <?php endif; ?>

                <li class="nav-item mt-4 pt-3 border-top border-secondary">
                    <a href="<?= $baseUrl ?>/logout.php" class="nav-link text-danger">
                        <i class="nav-icon fas fa-sign-out-alt"></i>
                        <p>Sign Out</p>
                    </a>
                </li>
            </ul>
        </nav>
        <!-- /.sidebar-menu -->
    </div>
    <!-- /.sidebar -->
</aside>
