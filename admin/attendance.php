<?php
/**
 * Admin Attendance Management & Correction View
 * Allows viewing, filtering, editing, and correcting employee attendance with audit trails
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$pageTitle = "Attendance Logs & Management";
$activeNav = 'admin_attendance';
$db = get_db();
$flash = get_flash();

// Handle Admin Actions (Correct Status or Manual Mark)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        set_flash('error', 'Security session expired. Please try again.');
        header('Location: ' . get_base_url() . '/admin/attendance.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'correct_status') {
        $attendanceId = (int)($_POST['attendance_id'] ?? 0);
        $newStatus    = clean_string($_POST['new_status'] ?? '');
        $remarks      = clean_string($_POST['admin_remarks'] ?? '');

        if ($attendanceId <= 0) {
            set_flash('error', 'Invalid attendance record selected.');
        } elseif (empty($remarks)) {
            set_flash('error', 'Admin remarks are mandatory when altering attendance records.');
        } else {
            $updateRes = admin_update_attendance($attendanceId, $newStatus, $remarks, current_user_id());
            if ($updateRes['success']) {
                set_flash('success', $updateRes['message']);
            } else {
                set_flash('error', $updateRes['error']);
            }
        }
        header('Location: ' . get_base_url() . '/admin/attendance.php?' . http_build_query($_GET));
        exit;
    }

    if ($action === 'manual_mark') {
        $targetUserId = (int)($_POST['user_id'] ?? 0);
        $targetDate   = clean_string($_POST['attendance_date'] ?? date('Y-m-d'));
        $status       = clean_string($_POST['status'] ?? 'Present');
        $adminRemarks = clean_string($_POST['admin_remarks'] ?? 'Manually recorded by administrator');

        if ($targetUserId <= 0) {
            set_flash('error', 'Please select a valid employee.');
        } elseif (!in_array($status, ['Present', 'Half Day', 'Absent'], true)) {
            set_flash('error', 'Invalid status selected.');
        } else {
            // Check if record exists
            $stmtCheck = $db->prepare("SELECT id FROM attendance WHERE user_id = ? AND attendance_date = ? LIMIT 1");
            $stmtCheck->execute([$targetUserId, $targetDate]);
            if ($stmtCheck->fetch()) {
                set_flash('error', 'Attendance for this employee and date already exists. Please use the Edit button to correct it.');
            } else {
                $now = date('Y-m-d H:i:s');
                $stmtInsert = $db->prepare("
                    INSERT INTO attendance (user_id, attendance_date, status, punch_time, punch_ip, employee_remarks, is_modified, modified_by, modified_at, admin_remarks, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?)
                ");
                $stmtInsert->execute([
                    $targetUserId,
                    $targetDate,
                    $status,
                    $now,
                    $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                    'Manual Entry',
                    current_user_id(),
                    $now,
                    $adminRemarks,
                    $now,
                    $now
                ]);
                set_flash('success', "Attendance marked successfully for the employee on {$targetDate}.");
            }
        }
        header('Location: ' . get_base_url() . '/admin/attendance.php?' . http_build_query($_GET));
        exit;
    }
}

// Fetch active employees for filters and manual entry dropdown
$employees = $db->query("SELECT id, employee_id, name, email FROM users WHERE role = 'employee' AND status = 'active' ORDER BY name ASC")->fetchAll();

// Filter parameters
$filterDateFrom = clean_string($_GET['date_from'] ?? date('Y-m-01'));
$filterDateTo   = clean_string($_GET['date_to'] ?? date('Y-m-d'));
$filterUser     = isset($_GET['user_id']) && $_GET['user_id'] !== '' ? (int)$_GET['user_id'] : '';
$filterStatus   = clean_string($_GET['status'] ?? '');

// Build query
$whereClauses = ["1=1"];
$params = [];

if (!empty($filterDateFrom)) {
    $whereClauses[] = "a.attendance_date >= ?";
    $params[] = $filterDateFrom;
}
if (!empty($filterDateTo)) {
    $whereClauses[] = "a.attendance_date <= ?";
    $params[] = $filterDateTo;
}
if ($filterUser !== '') {
    $whereClauses[] = "a.user_id = ?";
    $params[] = $filterUser;
}
if (!empty($filterStatus)) {
    $whereClauses[] = "a.status = ?";
    $params[] = $filterStatus;
}

$whereSql = implode(' AND ', $whereClauses);

$sql = "
    SELECT a.*, u.name as employee_name, u.employee_id as emp_code,
           m.name as modified_by_name
    FROM attendance a
    JOIN users u ON a.user_id = u.id
    LEFT JOIN users m ON a.modified_by = m.id
    WHERE {$whereSql}
    ORDER BY a.attendance_date DESC, a.punch_time DESC
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$attendanceLogs = $stmt->fetchAll();

// KPI Counters
$totalLogs = count($attendanceLogs);
$presentCount = 0;
$halfDayCount = 0;
$absentCount = 0;

foreach ($attendanceLogs as $log) {
    if ($log['status'] === 'Present') $presentCount++;
    elseif ($log['status'] === 'Half Day') $halfDayCount++;
    elseif ($log['status'] === 'Absent') $absentCount++;
}

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
                        <i class="fas fa-clipboard-check text-primary mr-2"></i>Attendance Logs & Corrections
                    </h1>
                </div>
                <div class="col-sm-6 text-sm-right mt-2 mt-sm-0">
                    <button type="button" class="btn btn-primary btn-sm shadow-sm mr-2" data-toggle="modal" data-target="#manualAttendanceModal">
                        <i class="fas fa-plus-circle mr-1"></i>Manual Attendance Punch
                    </button>
                    <a href="<?= get_base_url() ?>/admin/attendance_sheet.php" class="btn btn-outline-info btn-sm shadow-sm">
                        <i class="fas fa-table mr-1"></i>View Attendance Sheet
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <section class="content">
        <div class="container-fluid">

            <?php if ($flash): ?>
                <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : ($flash['type'] === 'warning' ? 'warning' : 'success') ?> alert-dismissible fade show shadow-sm" role="alert">
                    <i class="fas <?= $flash['type'] === 'error' ? 'fa-exclamation-circle' : 'fa-check-circle' ?> mr-2"></i>
                    <?= e($flash['message']) ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>

            <!-- KPI Row -->
            <div class="row">
                <div class="col-6 col-md-3">
                    <div class="info-box shadow-sm mb-3">
                        <span class="info-box-icon bg-primary elevation-1"><i class="fas fa-list-ol"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text text-muted">Total Entries</span>
                            <span class="info-box-number font-weight-bold text-dark" style="font-size: 1.4rem;"><?= number_format($totalLogs) ?></span>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="info-box shadow-sm mb-3">
                        <span class="info-box-icon bg-success elevation-1"><i class="fas fa-check-circle"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text text-muted">Present (Full Day)</span>
                            <span class="info-box-number font-weight-bold text-success" style="font-size: 1.4rem;"><?= number_format($presentCount) ?></span>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="info-box shadow-sm mb-3">
                        <span class="info-box-icon bg-warning text-dark elevation-1"><i class="fas fa-adjust"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text text-muted">Half Days</span>
                            <span class="info-box-number font-weight-bold text-warning" style="font-size: 1.4rem;"><?= number_format($halfDayCount) ?></span>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="info-box shadow-sm mb-3">
                        <span class="info-box-icon bg-danger elevation-1"><i class="fas fa-times-circle"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text text-muted">Absences</span>
                            <span class="info-box-number font-weight-bold text-danger" style="font-size: 1.4rem;"><?= number_format($absentCount) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter Card -->
            <div class="card card-outline card-secondary shadow-sm mb-4">
                <div class="card-header bg-light py-2">
                    <h3 class="card-title font-weight-bold text-secondary mb-0" style="font-size: 0.95rem;">
                        <i class="fas fa-filter mr-1"></i>Filter Attendance Records
                    </h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-tool" data-card-widget="collapse">
                            <i class="fas fa-minus"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body py-3">
                    <form method="GET" action="<?= get_base_url() ?>/admin/attendance.php">
                        <div class="form-row align-items-end">
                            <div class="form-group col-md-3 col-sm-6 mb-2">
                                <label class="small font-weight-bold text-muted">Date From:</label>
                                <input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($filterDateFrom) ?>">
                            </div>
                            <div class="form-group col-md-3 col-sm-6 mb-2">
                                <label class="small font-weight-bold text-muted">Date To:</label>
                                <input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($filterDateTo) ?>">
                            </div>
                            <div class="form-group col-md-3 col-sm-6 mb-2">
                                <label class="small font-weight-bold text-muted">Employee:</label>
                                <select name="user_id" class="form-control form-control-sm">
                                    <option value="">All Telecallers</option>
                                    <?php foreach ($employees as $emp): ?>
                                        <option value="<?= $emp['id'] ?>" <?= $filterUser === (int)$emp['id'] ? 'selected' : '' ?>>
                                            <?= e($emp['name']) ?> (<?= e($emp['employee_id']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group col-md-2 col-sm-6 mb-2">
                                <label class="small font-weight-bold text-muted">Status:</label>
                                <select name="status" class="form-control form-control-sm">
                                    <option value="">All Statuses</option>
                                    <option value="Present" <?= $filterStatus === 'Present' ? 'selected' : '' ?>>Present</option>
                                    <option value="Half Day" <?= $filterStatus === 'Half Day' ? 'selected' : '' ?>>Half Day</option>
                                    <option value="Absent" <?= $filterStatus === 'Absent' ? 'selected' : '' ?>>Absent</option>
                                </select>
                            </div>
                            <div class="form-group col-md-1 col-sm-12 mb-2 text-right">
                                <button type="submit" class="btn btn-primary btn-sm btn-block">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Attendance Records Table Card -->
            <div class="card card-outline card-primary shadow-sm">
                <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
                    <h3 class="card-title font-weight-bold text-dark mb-0">
                        <i class="fas fa-history text-primary mr-2"></i>Attendance Log Entries
                    </h3>
                    <span class="badge badge-light border text-muted">7:00 AM Daily Cutoff Enforced</span>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table id="adminAttendanceTable" class="table table-hover table-striped align-middle mb-0" style="width: 100%;">
                            <thead class="thead-light">
                                <tr>
                                    <th style="width: 40px;">#</th>
                                    <th>Employee</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Punch Time & IP</th>
                                    <th>Employee Remarks</th>
                                    <th>Modification Audit</th>
                                    <th style="width: 100px;" class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($attendanceLogs)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-5 text-muted">
                                            <i class="fas fa-calendar-times fa-3x mb-3 text-secondary opacity-50"></i>
                                            <p class="mb-0">No attendance records found matching your filter criteria.</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($attendanceLogs as $idx => $row): ?>
                                        <tr>
                                            <td><?= $idx + 1 ?></td>
                                            <td>
                                                <div class="font-weight-bold text-dark"><?= e($row['employee_name']) ?></div>
                                                <small class="badge badge-light border text-muted"><?= e($row['emp_code']) ?></small>
                                            </td>
                                            <td>
                                                <div class="font-weight-bold text-dark"><?= date('d M Y', strtotime($row['attendance_date'])) ?></div>
                                                <small class="text-muted"><?= date('l', strtotime($row['attendance_date'])) ?></small>
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
                                                    <span class="badge badge-warning text-dark mb-1">
                                                        <i class="fas fa-user-shield mr-1"></i>Adjusted by <?= e($row['modified_by_name'] ?? 'Admin') ?>
                                                    </span>
                                                    <div class="text-muted font-italic">
                                                        "<?= e($row['admin_remarks'] ?? '') ?>"
                                                    </div>
                                                    <div class="text-muted" style="font-size: 0.72rem;">
                                                        <?= date('d M Y, h:i A', strtotime($row['modified_at'])) ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted"><i class="fas fa-check mr-1 text-success"></i>Original submission</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-outline-primary btn-xs btn-edit-attendance"
                                                    data-id="<?= $row['id'] ?>"
                                                    data-name="<?= e($row['employee_name']) ?> (<?= e($row['emp_code']) ?>)"
                                                    data-date="<?= date('d M Y', strtotime($row['attendance_date'])) ?>"
                                                    data-status="<?= e($row['status']) ?>"
                                                    data-remarks="<?= e($row['admin_remarks'] ?? '') ?>"
                                                    title="Correct Attendance Status">
                                                    <i class="fas fa-edit mr-1"></i>Edit
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </section>
</div>

<!-- Modal: Edit / Correct Attendance Status -->
<div class="modal fade" id="editAttendanceModal" tabindex="-1" role="dialog" aria-labelledby="editAttendanceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-gradient-primary text-white py-3">
                <h5 class="modal-title font-weight-bold" id="editAttendanceModalLabel">
                    <i class="fas fa-user-edit mr-2"></i>Correct Attendance Record
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="<?= get_base_url() ?>/admin/attendance.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                <input type="hidden" name="action" value="correct_status">
                <input type="hidden" name="attendance_id" id="edit_attendance_id" value="">

                <div class="modal-body p-4">
                    <div class="alert alert-light border mb-3 py-2 px-3">
                        <div class="small text-muted mb-1">Employee: <strong id="edit_employee_name" class="text-dark"></strong></div>
                        <div class="small text-muted">Attendance Date: <strong id="edit_attendance_date" class="text-dark"></strong></div>
                    </div>

                    <div class="form-group">
                        <label for="edit_status" class="font-weight-bold text-dark">
                            Corrected Status <span class="text-danger">*</span>
                        </label>
                        <select name="new_status" id="edit_status" class="form-control" required>
                            <option value="Present">Present (Full Day)</option>
                            <option value="Half Day">Half Day (0.5 Day)</option>
                            <option value="Absent">Absent (0 Day)</option>
                        </select>
                    </div>

                    <div class="form-group mb-0">
                        <label for="edit_admin_remarks" class="font-weight-bold text-dark">
                            Admin Remarks / Reason for Correction <span class="text-danger">*</span>
                        </label>
                        <textarea name="admin_remarks" id="edit_admin_remarks" class="form-control" rows="3" required placeholder="Mandatory: explain why this record is being corrected (e.g., punch machine issue, approved emergency leave, typo)..."></textarea>
                        <small class="text-muted"><i class="fas fa-shield-alt mr-1"></i>All corrections are permanently stamped with your admin account and timestamp.</small>
                    </div>
                </div>

                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm font-weight-bold shadow-sm">
                        <i class="fas fa-save mr-1"></i>Save Correction
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Manual Attendance Punch -->
<div class="modal fade" id="manualAttendanceModal" tabindex="-1" role="dialog" aria-labelledby="manualAttendanceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-gradient-navy text-white py-3">
                <h5 class="modal-title font-weight-bold" id="manualAttendanceModalLabel">
                    <i class="fas fa-plus-circle mr-2"></i>Manual Attendance Entry
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="<?= get_base_url() ?>/admin/attendance.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                <input type="hidden" name="action" value="manual_mark">

                <div class="modal-body p-4">
                    <p class="text-muted small mb-3">
                        Use this form to mark attendance for an employee who was unable to check in themselves.
                    </p>

                    <div class="form-group">
                        <label for="manual_user_id" class="font-weight-bold text-dark">Select Employee <span class="text-danger">*</span></label>
                        <select name="user_id" id="manual_user_id" class="form-control" required>
                            <option value="">-- Choose Employee --</option>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?= $emp['id'] ?>"><?= e($emp['name']) ?> (<?= e($emp['employee_id']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="manual_date" class="font-weight-bold text-dark">Working Date <span class="text-danger">*</span></label>
                        <input type="date" name="attendance_date" id="manual_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="manual_status" class="font-weight-bold text-dark">Attendance Status <span class="text-danger">*</span></label>
                        <select name="status" id="manual_status" class="form-control" required>
                            <option value="Present">Present (Full Day)</option>
                            <option value="Half Day">Half Day</option>
                            <option value="Absent">Absent</option>
                        </select>
                    </div>

                    <div class="form-group mb-0">
                        <label for="manual_admin_remarks" class="font-weight-bold text-dark">Remarks <span class="text-danger">*</span></label>
                        <input type="text" name="admin_remarks" id="manual_admin_remarks" class="form-control" value="Manual attendance entry by admin" required>
                    </div>
                </div>

                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success btn-sm font-weight-bold shadow-sm">
                        <i class="fas fa-check mr-1"></i>Record Attendance
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$extraScripts = '
<script>
$(document).ready(function() {
    if ($("#adminAttendanceTable tbody tr td").length > 1) {
        $("#adminAttendanceTable").DataTable({
            responsive: true,
            autoWidth: false,
            pageLength: 25,
            order: [[2, "desc"]],
            language: {
                search: "_INPUT_",
                searchPlaceholder: "Search employee, date, or status..."
            }
        });
    }

    $(document).on("click", ".btn-edit-attendance", function() {
        var id = $(this).data("id");
        var name = $(this).data("name");
        var date = $(this).data("date");
        var status = $(this).data("status");
        var remarks = $(this).data("remarks");

        $("#edit_attendance_id").val(id);
        $("#edit_employee_name").text(name);
        $("#edit_attendance_date").text(date);
        $("#edit_status").val(status);
        $("#edit_admin_remarks").val(remarks);

        $("#editAttendanceModal").modal("show");
    });
});
</script>
';
require_once __DIR__ . '/../includes/footer.php';
?>
