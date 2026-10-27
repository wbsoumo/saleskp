<?php
/**
 * Employee Attendance Management & History View
 * Telecaller Reporting Web App
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_employee();

$pageTitle = "My Attendance";
$activeNav = 'emp_attendance';
$userId = current_user_id();
$db = get_db();
$flash = get_flash();

// Selected month and year filter
$selectedMonth = isset($_GET['month']) ? str_pad((int)$_GET['month'], 2, '0', STR_PAD_LEFT) : date('m');
$selectedYear  = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// Working attendance date & today's status
$workingDate = get_current_attendance_date();
$todayRecord = get_employee_attendance_record($userId, $workingDate);
$summary = get_employee_attendance_summary($userId, $selectedMonth, (string)$selectedYear);

// Fetch records for the selected month and year
$isSqlite = Database::getActiveDriver() === 'sqlite';
if ($isSqlite) {
    $stmt = $db->prepare("
        SELECT a.*, m.name as modified_by_name
        FROM attendance a
        LEFT JOIN users m ON a.modified_by = m.id
        WHERE a.user_id = ?
          AND strftime('%m', a.attendance_date) = ?
          AND strftime('%Y', a.attendance_date) = ?
        ORDER BY a.attendance_date DESC
    ");
} else {
    $stmt = $db->prepare("
        SELECT a.*, m.name as modified_by_name
        FROM attendance a
        LEFT JOIN users m ON a.modified_by = m.id
        WHERE a.user_id = ?
          AND MONTH(a.attendance_date) = ?
          AND YEAR(a.attendance_date) = ?
        ORDER BY a.attendance_date DESC
    ");
}

$stmt->execute([$userId, (int)$selectedMonth, $selectedYear]);
$attendanceRecords = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<!-- Content Wrapper -->
<div class="content-wrapper">
    <!-- Content Header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2 align-items-center">
                <div class="col-sm-6">
                    <h1 class="m-0 font-weight-bold text-dark">
                        <i class="fas fa-user-clock text-primary mr-2"></i>My Attendance
                    </h1>
                </div>
                <div class="col-sm-6 text-sm-right mt-2 mt-sm-0">
                    <ol class="breadcrumb float-sm-right mb-0">
                        <li class="breadcrumb-item"><a href="<?= get_base_url() ?>/employee/workspace.php">Workspace</a></li>
                        <li class="breadcrumb-item active">Attendance</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">

            <?php if ($flash): ?>
                <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : ($flash['type'] === 'warning' ? 'warning' : 'success') ?> alert-dismissible fade show shadow-sm" role="alert">
                    <i class="fas <?= $flash['type'] === 'error' ? 'fa-exclamation-circle' : ($flash['type'] === 'warning' ? 'fa-exclamation-triangle' : 'fa-check-circle') ?> mr-2"></i>
                    <?= e($flash['message']) ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>

            <!-- Today's Status Banner -->
            <div class="card card-outline <?= $todayRecord ? 'card-success' : 'card-warning' ?> shadow-sm mb-4">
                <div class="card-body py-3">
                    <div class="row align-items-center">
                        <div class="col-md-8 d-flex align-items-center">
                            <div class="mr-3 p-3 rounded-circle <?= $todayRecord ? 'bg-success text-white' : 'bg-warning text-dark' ?>">
                                <i class="fas <?= $todayRecord ? 'fa-calendar-check' : 'fa-clock' ?> fa-2x"></i>
                            </div>
                            <div>
                                <h5 class="font-weight-bold mb-1">
                                    Today's Attendance (<?= date('l, d M Y', strtotime($workingDate)) ?>)
                                </h5>
                                <div class="text-muted small">
                                    <?php if ($todayRecord): ?>
                                        <span>Status: <?= get_attendance_badge($todayRecord['status']) ?></span>
                                        <span class="mx-2">•</span>
                                        <span><i class="far fa-clock mr-1"></i>Punched at <?= date('h:i A', strtotime($todayRecord['punch_time'])) ?></span>
                                        <?php if (!empty($todayRecord['employee_remarks'])): ?>
                                            <span class="mx-2">•</span>
                                            <span><i class="fas fa-comment-dots mr-1"></i><?= e($todayRecord['employee_remarks']) ?></span>
                                        <?php endif; ?>
                                        <?php if ($todayRecord['is_modified']): ?>
                                            <span class="badge badge-warning text-dark ml-2">
                                                <i class="fas fa-edit mr-1"></i>Adjusted by Admin
                                            </span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <?php if ((int)date('H') >= 7): ?>
                                            <span class="text-danger font-weight-bold">
                                                <i class="fas fa-exclamation-triangle mr-1"></i>You have not marked your attendance for today yet.
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">
                                                <i class="fas fa-info-circle mr-1"></i>Daily attendance opens at 7:00 AM.
                                            </span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 text-md-right mt-3 mt-md-0">
                            <?php if (!$todayRecord && (int)date('H') >= 7): ?>
                                <button type="button" class="btn btn-primary btn-sm shadow-sm" data-toggle="modal" data-target="#mandatoryAttendanceModal">
                                    <i class="fas fa-hand-paper mr-1"></i>Mark Attendance Now
                                </button>
                            <?php else: ?>
                                <span class="badge badge-light border p-2 text-muted">
                                    <i class="fas fa-lock mr-1"></i>One Entry Per Day (Cutoff: 7:00 AM)
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Metric KPI Summary Cards -->
            <div class="row">
                <!-- Total Present Days -->
                <div class="col-12 col-sm-6 col-md-3">
                    <div class="info-box shadow-sm mb-3">
                        <span class="info-box-icon bg-success elevation-1"><i class="fas fa-user-check"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text text-muted">Full Days Present</span>
                            <span class="info-box-number font-weight-bold text-success" style="font-size: 1.5rem;">
                                <?= $summary['present_count'] ?> <small class="text-muted font-weight-normal">days</small>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Total Half Days -->
                <div class="col-12 col-sm-6 col-md-3">
                    <div class="info-box shadow-sm mb-3">
                        <span class="info-box-icon bg-warning text-dark elevation-1"><i class="fas fa-adjust"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text text-muted">Half Days (0.5)</span>
                            <span class="info-box-number font-weight-bold text-warning" style="font-size: 1.5rem;">
                                <?= $summary['half_day_count'] ?> <small class="text-muted font-weight-normal">days</small>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Total Absences -->
                <div class="col-12 col-sm-6 col-md-3">
                    <div class="info-box shadow-sm mb-3">
                        <span class="info-box-icon bg-danger elevation-1"><i class="fas fa-times-circle"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text text-muted">Absences / Leaves</span>
                            <span class="info-box-number font-weight-bold text-danger" style="font-size: 1.5rem;">
                                <?= $summary['absent_count'] ?> <small class="text-muted font-weight-normal">days</small>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Effective Days / Attendance Rate -->
                <div class="col-12 col-sm-6 col-md-3">
                    <div class="info-box shadow-sm mb-3">
                        <span class="info-box-icon bg-info elevation-1"><i class="fas fa-percentage"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text text-muted">Attendance Rate</span>
                            <span class="info-box-number font-weight-bold text-info" style="font-size: 1.5rem;">
                                <?= $summary['attendance_rate'] ?>%
                            </span>
                            <small class="text-muted"><?= $summary['effective_present'] ?> effective / <?= $summary['total_records'] ?> records</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Attendance History Card -->
            <div class="card card-outline card-primary shadow-sm">
                <div class="card-header bg-white py-3">
                    <div class="row align-items-center">
                        <div class="col-md-6 mb-2 mb-md-0">
                            <h3 class="card-title font-weight-bold text-dark mb-0">
                                <i class="fas fa-calendar-alt text-primary mr-2"></i>Attendance History
                            </h3>
                            <span class="badge badge-light border ml-2">
                                <?= date('F Y', strtotime("$selectedYear-$selectedMonth-01")) ?>
                            </span>
                        </div>
                        <div class="col-md-6">
                            <!-- Filter Form -->
                            <form method="GET" class="form-inline justify-content-md-end">
                                <div class="form-group mr-2 mb-0">
                                    <label class="mr-2 small font-weight-bold text-muted">Month:</label>
                                    <select name="month" class="form-control form-control-sm" onchange="this.form.submit()">
                                        <?php for ($m = 1; $m <= 12; $m++): ?>
                                            <?php $mPad = str_pad($m, 2, '0', STR_PAD_LEFT); ?>
                                            <option value="<?= $mPad ?>" <?= $selectedMonth === $mPad ? 'selected' : '' ?>>
                                                <?= date('F', mktime(0, 0, 0, $m, 10)) ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="form-group mb-0">
                                    <label class="mr-2 small font-weight-bold text-muted">Year:</label>
                                    <select name="year" class="form-control form-control-sm" onchange="this.form.submit()">
                                        <?php for ($y = (int)date('Y'); $y >= 2024; $y--): ?>
                                            <option value="<?= $y ?>" <?= $selectedYear === $y ? 'selected' : '' ?>><?= $y ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table id="empAttendanceTable" class="table table-hover table-striped align-middle mb-0" style="width: 100%;">
                            <thead class="thead-light">
                                <tr>
                                    <th style="width: 50px;">#</th>
                                    <th>Working Date</th>
                                    <th>Day</th>
                                    <th>Attendance Status</th>
                                    <th>Punch Time</th>
                                    <th>Employee Remarks</th>
                                    <th>Admin Review / Adjustments</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($attendanceRecords)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-5 text-muted">
                                            <i class="fas fa-clipboard-list fa-3x mb-3 text-secondary opacity-50"></i>
                                            <p class="mb-0">No attendance entries recorded for <?= date('F Y', strtotime("$selectedYear-$selectedMonth-01")) ?>.</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($attendanceRecords as $index => $row): ?>
                                        <tr>
                                            <td><?= $index + 1 ?></td>
                                            <td class="font-weight-bold text-dark">
                                                <?= date('d M Y', strtotime($row['attendance_date'])) ?>
                                            </td>
                                            <td class="text-muted">
                                                <?= date('l', strtotime($row['attendance_date'])) ?>
                                            </td>
                                            <td>
                                                <?= get_attendance_badge($row['status']) ?>
                                            </td>
                                            <td class="small">
                                                <div><i class="far fa-clock text-info mr-1"></i><?= date('h:i A', strtotime($row['punch_time'])) ?></div>
                                                <?php if (!empty($row['punch_ip'])): ?>
                                                    <div class="text-muted"><i class="fas fa-network-wired mr-1"></i><?= e($row['punch_ip']) ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="small">
                                                <?= !empty($row['employee_remarks']) ? e($row['employee_remarks']) : '<span class="text-muted">-</span>' ?>
                                            </td>
                                            <td class="small">
                                                <?php if ($row['is_modified']): ?>
                                                    <div class="badge badge-warning text-dark mb-1">
                                                        <i class="fas fa-user-shield mr-1"></i>Corrected by Admin
                                                    </div>
                                                    <div class="text-muted">
                                                        <strong><?= e($row['modified_by_name'] ?? 'Admin') ?>:</strong>
                                                        <?= e($row['admin_remarks'] ?? 'No remark provided') ?>
                                                    </div>
                                                    <div class="text-muted font-italic" style="font-size: 0.75rem;">
                                                        <?= date('d M Y, h:i A', strtotime($row['modified_at'])) ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-success"><i class="fas fa-check mr-1"></i>Original punch</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card-footer bg-light py-2">
                    <div class="d-flex justify-content-between align-items-center small text-muted">
                        <span><i class="fas fa-info-circle mr-1"></i>Daily attendance is counted towards your monthly performance and payroll.</span>
                        <span>Shift starts: 7:00 AM daily cutoff</span>
                    </div>
                </div>
            </div>

        </div>
    </section>
</div>

<?php
$extraScripts = '
<script>
$(document).ready(function() {
    if ($("#empAttendanceTable tbody tr td").length > 1) {
        $("#empAttendanceTable").DataTable({
            responsive: true,
            autoWidth: false,
            pageLength: 25,
            order: [[1, "desc"]],
            language: {
                search: "_INPUT_",
                searchPlaceholder: "Search attendance records..."
            }
        });
    }
});
</script>
';
require_once __DIR__ . '/../includes/footer.php';
?>
