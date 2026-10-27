<?php
/**
 * Admin Attendance Sheet (Monthly Matrix View & CSV Export)
 * Displays employees as rows and calendar dates as columns
 * Telecaller Reporting Web App
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$db = get_db();
$companyName = get_setting('company_name', DEFAULT_COMPANY_NAME);

// Selected Month & Year (defaults to current)
$selectedMonth = isset($_GET['month']) ? str_pad((int)$_GET['month'], 2, '0', STR_PAD_LEFT) : date('m');
$selectedYear  = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$selectedEmpId = isset($_GET['employee_id']) && $_GET['employee_id'] !== '' ? (int)$_GET['employee_id'] : null;

// Determine days in month
$daysInMonth = (int)date('t', strtotime("{$selectedYear}-{$selectedMonth}-01"));
$monthLabel = date('F Y', strtotime("{$selectedYear}-{$selectedMonth}-01"));

// Fetch employees
$empSql = "SELECT id, employee_id, name, email, phone FROM users WHERE role = 'employee' AND status = 'active'";
if ($selectedEmpId) {
    $empSql .= " AND id = " . (int)$selectedEmpId;
}
$empSql .= " ORDER BY name ASC";
$employees = $db->query($empSql)->fetchAll();

// Fetch all attendance records for this month
$isSqlite = Database::getActiveDriver() === 'sqlite';
if ($isSqlite) {
    $attSql = "
        SELECT user_id, attendance_date, status, punch_time, employee_remarks, is_modified, admin_remarks
        FROM attendance
        WHERE strftime('%m', attendance_date) = ?
          AND strftime('%Y', attendance_date) = ?
    ";
} else {
    $attSql = "
        SELECT user_id, attendance_date, status, punch_time, employee_remarks, is_modified, admin_remarks
        FROM attendance
        WHERE MONTH(attendance_date) = ?
          AND YEAR(attendance_date) = ?
    ";
}
$stmtAtt = $db->prepare($attSql);
$stmtAtt->execute([(int)$selectedMonth, $selectedYear]);
$rawRecords = $stmtAtt->fetchAll();

// Map attendance into lookup: $matrix[user_id][day_number] = record
$matrix = [];
foreach ($rawRecords as $rec) {
    $dayNum = (int)date('j', strtotime($rec['attendance_date']));
    $matrix[$rec['user_id']][$dayNum] = $rec;
}

// ---------------------------------------------------------
// CSV / EXCEL EXPORT HANDLER
// ---------------------------------------------------------
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $filename = "attendance_sheet_{$selectedYear}_{$selectedMonth}.csv";
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    // Output UTF-8 BOM for Excel
    fputs($output, "\xEF\xBB\xBF");

    // Title rows
    fputcsv($output, [$companyName . ' - Monthly Attendance Matrix Report'], ',', '"', "\\");
    fputcsv($output, ['Month & Year:', $monthLabel], ',', '"', "\\");
    fputcsv($output, ['Generated On:', date('d M Y, h:i A')], ',', '"', "\\");
    fputcsv($output, [], ',', '"', "\\"); // Empty row

    // Table Header Row
    $headerRow = ['Employee Code', 'Employee Name', 'Email'];
    for ($d = 1; $d <= $daysInMonth; $d++) {
        $dayDate = sprintf('%04d-%02d-%02d', $selectedYear, (int)$selectedMonth, $d);
        $dayOfWeek = date('D', strtotime($dayDate));
        $headerRow[] = sprintf('%02d (%s)', $d, $dayOfWeek);
    }
    $headerRow[] = 'Present Days (P)';
    $headerRow[] = 'Half Days (HD)';
    $headerRow[] = 'Absent Days (A)';
    $headerRow[] = 'Effective Days';
    $headerRow[] = 'Attendance Rate %';
    fputcsv($output, $headerRow, ',', '"', "\\");

    // Employee Data Rows
    foreach ($employees as $emp) {
        $pCount = 0;
        $hdCount = 0;
        $aCount = 0;
        $empRow = [$emp['employee_id'], $emp['name'], $emp['email']];

        for ($d = 1; $d <= $daysInMonth; $d++) {
            if (isset($matrix[$emp['id']][$d])) {
                $status = $matrix[$emp['id']][$d]['status'];
                if ($status === 'Present') {
                    $empRow[] = 'P';
                    $pCount++;
                } elseif ($status === 'Half Day') {
                    $empRow[] = 'HD';
                    $hdCount++;
                } elseif ($status === 'Absent') {
                    $empRow[] = 'A';
                    $aCount++;
                } else {
                    $empRow[] = $status;
                }
            } else {
                $empRow[] = '-';
            }
        }

        $effective = $pCount + ($hdCount * 0.5);
        $totalRecorded = $pCount + $hdCount + $aCount;
        $rate = $totalRecorded > 0 ? round(($effective / $totalRecorded) * 100, 1) : 0;

        $empRow[] = $pCount;
        $empRow[] = $hdCount;
        $empRow[] = $aCount;
        $empRow[] = $effective;
        $empRow[] = $rate . '%';

        fputcsv($output, $empRow, ',', '"', "\\");
    }

    fclose($output);
    exit;
}

// ---------------------------------------------------------
// WEB VIEW SETUP
// ---------------------------------------------------------
$pageTitle = "Monthly Attendance Sheet";
$activeNav = 'admin_attendance_sheet';

// Calculate monthly team stats
$teamTotalP = 0;
$teamTotalHD = 0;
$teamTotalA = 0;
$totalCells = count($employees) * $daysInMonth;

foreach ($matrix as $userId => $days) {
    foreach ($days as $d => $rec) {
        if ($rec['status'] === 'Present') $teamTotalP++;
        elseif ($rec['status'] === 'Half Day') $teamTotalHD++;
        elseif ($rec['status'] === 'Absent') $teamTotalA++;
    }
}

$teamEffective = $teamTotalP + ($teamTotalHD * 0.5);
$teamRecordedTotal = $teamTotalP + $teamTotalHD + $teamTotalA;
$teamAvgRate = $teamRecordedTotal > 0 ? round(($teamEffective / $teamRecordedTotal) * 100, 1) : 0.0;

// Fetch all employees for filter dropdown
$allEmployeesDropdown = $db->query("SELECT id, employee_id, name FROM users WHERE role = 'employee' AND status = 'active' ORDER BY name ASC")->fetchAll();

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
                        <i class="fas fa-table text-primary mr-2"></i>Attendance Sheet Matrix
                    </h1>
                </div>
                <div class="col-sm-6 text-sm-right mt-2 mt-sm-0">
                    <!-- Export to CSV Button -->
                    <a href="<?= get_base_url() ?>/admin/attendance_sheet.php?month=<?= $selectedMonth ?>&year=<?= $selectedYear ?><?= $selectedEmpId ? '&employee_id=' . $selectedEmpId : '' ?>&export=csv" class="btn btn-success btn-sm shadow-sm mr-2">
                        <i class="fas fa-file-excel mr-1"></i>Export to Excel/CSV
                    </a>
                    <a href="<?= get_base_url() ?>/admin/attendance.php" class="btn btn-outline-primary btn-sm shadow-sm">
                        <i class="fas fa-list mr-1"></i>Attendance Logs
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <section class="content">
        <div class="container-fluid">

            <!-- Team Summary KPI Row -->
            <div class="row">
                <div class="col-6 col-md-3">
                    <div class="info-box shadow-sm mb-3">
                        <span class="info-box-icon bg-success elevation-1"><i class="fas fa-check-circle"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text text-muted">Total Present (P)</span>
                            <span class="info-box-number font-weight-bold text-success" style="font-size: 1.4rem;"><?= number_format($teamTotalP) ?></span>
                            <small class="text-muted">Full working days</small>
                        </div>
                    </div>
                </div>

                <div class="col-6 col-md-3">
                    <div class="info-box shadow-sm mb-3">
                        <span class="info-box-icon bg-warning text-dark elevation-1"><i class="fas fa-adjust"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text text-muted">Total Half Days (HD)</span>
                            <span class="info-box-number font-weight-bold text-warning" style="font-size: 1.4rem;"><?= number_format($teamTotalHD) ?></span>
                            <small class="text-muted">Half shifts (0.5 day)</small>
                        </div>
                    </div>
                </div>

                <div class="col-6 col-md-3">
                    <div class="info-box shadow-sm mb-3">
                        <span class="info-box-icon bg-danger elevation-1"><i class="fas fa-times-circle"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text text-muted">Total Absences (A)</span>
                            <span class="info-box-number font-weight-bold text-danger" style="font-size: 1.4rem;"><?= number_format($teamTotalA) ?></span>
                            <small class="text-muted">Leaves & absences</small>
                        </div>
                    </div>
                </div>

                <div class="col-6 col-md-3">
                    <div class="info-box shadow-sm mb-3">
                        <span class="info-box-icon bg-info elevation-1"><i class="fas fa-chart-pie"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text text-muted">Team Attendance Rate</span>
                            <span class="info-box-number font-weight-bold text-info" style="font-size: 1.4rem;"><?= $teamAvgRate ?>%</span>
                            <small class="text-muted"><?= $teamEffective ?> effective days</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter Controls Card -->
            <div class="card card-outline card-secondary shadow-sm mb-4">
                <div class="card-body py-3">
                    <form method="GET" action="<?= get_base_url() ?>/admin/attendance_sheet.php">
                        <div class="form-row align-items-end">
                            <div class="form-group col-md-3 col-sm-6 mb-2">
                                <label class="small font-weight-bold text-muted">Month:</label>
                                <select name="month" class="form-control form-control-sm" onchange="this.form.submit()">
                                    <?php for ($m = 1; $m <= 12; $m++): ?>
                                        <?php $mPad = str_pad($m, 2, '0', STR_PAD_LEFT); ?>
                                        <option value="<?= $mPad ?>" <?= $selectedMonth === $mPad ? 'selected' : '' ?>>
                                            <?= date('F', mktime(0, 0, 0, $m, 10)) ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>

                            <div class="form-group col-md-3 col-sm-6 mb-2">
                                <label class="small font-weight-bold text-muted">Year:</label>
                                <select name="year" class="form-control form-control-sm" onchange="this.form.submit()">
                                    <?php for ($y = (int)date('Y'); $y >= 2024; $y--): ?>
                                        <option value="<?= $y ?>" <?= $selectedYear === $y ? 'selected' : '' ?>><?= $y ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>

                            <div class="form-group col-md-4 col-sm-8 mb-2">
                                <label class="small font-weight-bold text-muted">Filter Employee:</label>
                                <select name="employee_id" class="form-control form-control-sm" onchange="this.form.submit()">
                                    <option value="">All Telecallers (<?= count($allEmployeesDropdown) ?> active)</option>
                                    <?php foreach ($allEmployeesDropdown as $dEmp): ?>
                                        <option value="<?= $dEmp['id'] ?>" <?= $selectedEmpId === (int)$dEmp['id'] ? 'selected' : '' ?>>
                                            <?= e($dEmp['name']) ?> (<?= e($dEmp['employee_id']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group col-md-2 col-sm-4 mb-2 text-right">
                                <button type="submit" class="btn btn-primary btn-sm btn-block">
                                    <i class="fas fa-filter mr-1"></i>Apply Filter
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Attendance Sheet Matrix Card -->
            <div class="card card-outline card-primary shadow-sm">
                <div class="card-header bg-white py-3 d-flex flex-wrap align-items-center justify-content-between">
                    <div>
                        <h3 class="card-title font-weight-bold text-dark mb-0">
                            <i class="fas fa-calendar-alt text-primary mr-2"></i>Monthly Sheet: <?= $monthLabel ?>
                        </h3>
                        <span class="badge badge-light border ml-2 text-muted"><?= $daysInMonth ?> Calendar Days</span>
                    </div>

                    <!-- Legend -->
                    <div class="d-flex align-items-center mt-2 mt-md-0 small">
                        <span class="mr-3"><span class="badge badge-success px-2 py-1 mr-1 font-weight-bold">P</span> Present</span>
                        <span class="mr-3"><span class="badge badge-warning text-dark px-2 py-1 mr-1 font-weight-bold">HD</span> Half Day</span>
                        <span class="mr-3"><span class="badge badge-danger px-2 py-1 mr-1 font-weight-bold">A</span> Absent</span>
                        <span class="text-muted"><span class="badge badge-light border px-2 py-1 mr-1">-</span> Not Marked</span>
                    </div>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 650px;">
                        <table class="table table-bordered table-sm text-center mb-0 attendance-matrix-table" style="font-size: 0.82rem;">
                            <thead class="thead-light">
                                <tr>
                                    <th class="sticky-col text-left py-2" style="min-width: 170px; background: #e9ecef;">
                                        Telecaller
                                    </th>
                                    <?php for ($d = 1; $d <= $daysInMonth; $d++): ?>
                                        <?php
                                            $dayDate = sprintf('%04d-%02d-%02d', $selectedYear, (int)$selectedMonth, $d);
                                            $dayOfWeek = date('D', strtotime($dayDate));
                                            $isWeekend = ($dayOfWeek === 'Sun' || $dayOfWeek === 'Sat');
                                            $isToday = ($dayDate === date('Y-m-d'));
                                        ?>
                                        <th class="py-2 <?= $isWeekend ? 'bg-light text-muted' : '' ?> <?= $isToday ? 'border-primary bg-primary text-white' : '' ?>" style="min-width: 38px;">
                                            <div class="font-weight-bold"><?= sprintf('%02d', $d) ?></div>
                                            <div style="font-size: 0.68rem;" class="<?= $isToday ? 'text-white' : 'text-muted' ?>"><?= substr($dayOfWeek, 0, 1) ?></div>
                                        </th>
                                    <?php endfor; ?>
                                    <th class="bg-light text-success font-weight-bold" style="min-width: 45px;" title="Full Days Present">P</th>
                                    <th class="bg-light text-warning font-weight-bold" style="min-width: 45px;" title="Half Days (0.5)">HD</th>
                                    <th class="bg-light text-danger font-weight-bold" style="min-width: 45px;" title="Absences">A</th>
                                    <th class="bg-light text-primary font-weight-bold" style="min-width: 55px;" title="Effective Working Days">Eff.</th>
                                    <th class="bg-light text-dark font-weight-bold" style="min-width: 60px;" title="Attendance % Rate">Rate%</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($employees)): ?>
                                    <tr>
                                        <td colspan="<?= $daysInMonth + 6 ?>" class="text-center py-5 text-muted">
                                            <i class="fas fa-users-slash fa-3x mb-2 text-secondary opacity-50"></i>
                                            <p class="mb-0">No active telecaller employees found.</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($employees as $emp): ?>
                                        <?php
                                            $pCount = 0;
                                            $hdCount = 0;
                                            $aCount = 0;
                                        ?>
                                        <tr>
                                            <td class="sticky-col text-left py-2 font-weight-bold text-dark" style="background: #ffffff;">
                                                <div class="text-truncate" style="max-width: 160px;" title="<?= e($emp['name']) ?>">
                                                    <?= e($emp['name']) ?>
                                                </div>
                                                <small class="badge badge-light border text-muted"><?= e($emp['employee_id']) ?></small>
                                            </td>

                                            <?php for ($d = 1; $d <= $daysInMonth; $d++): ?>
                                                <?php
                                                    $dayDate = sprintf('%04d-%02d-%02d', $selectedYear, (int)$selectedMonth, $d);
                                                    $dayOfWeek = date('D', strtotime($dayDate));
                                                    $isWeekend = ($dayOfWeek === 'Sun' || $dayOfWeek === 'Sat');
                                                    $rec = $matrix[$emp['id']][$d] ?? null;
                                                ?>
                                                <td class="p-1 align-middle <?= $isWeekend ? 'bg-light' : '' ?>">
                                                    <?php if ($rec): ?>
                                                        <?php
                                                            $st = $rec['status'];
                                                            $timeStr = date('h:i A', strtotime($rec['punch_time']));
                                                            $tooltipText = "{$st} on " . date('d M', strtotime($dayDate)) . " at {$timeStr}";
                                                            if (!empty($rec['employee_remarks'])) {
                                                                $tooltipText .= " (" . e($rec['employee_remarks']) . ")";
                                                            }
                                                            if ($rec['is_modified']) {
                                                                $tooltipText .= " [Admin adjusted: " . e($rec['admin_remarks']) . "]";
                                                            }
                                                        ?>
                                                        <?php if ($st === 'Present'): ?>
                                                            <?php $pCount++; ?>
                                                            <span class="matrix-pill badge-success" data-toggle="tooltip" title="<?= $tooltipText ?>">P</span>
                                                        <?php elseif ($st === 'Half Day'): ?>
                                                            <?php $hdCount++; ?>
                                                            <span class="matrix-pill badge-warning text-dark" data-toggle="tooltip" title="<?= $tooltipText ?>">HD</span>
                                                        <?php elseif ($st === 'Absent'): ?>
                                                            <?php $aCount++; ?>
                                                            <span class="matrix-pill badge-danger" data-toggle="tooltip" title="<?= $tooltipText ?>">A</span>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="text-muted" style="opacity: 0.35;">-</span>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endfor; ?>

                                            <?php
                                                $eff = $pCount + ($hdCount * 0.5);
                                                $recTotal = $pCount + $hdCount + $aCount;
                                                $rate = $recTotal > 0 ? round(($eff / $recTotal) * 100, 1) : 0;
                                            ?>
                                            <td class="font-weight-bold text-success align-middle"><?= $pCount ?></td>
                                            <td class="font-weight-bold text-warning align-middle"><?= $hdCount ?></td>
                                            <td class="font-weight-bold text-danger align-middle"><?= $aCount ?></td>
                                            <td class="font-weight-bold text-primary align-middle"><?= $eff ?></td>
                                            <td class="font-weight-bold text-dark align-middle">
                                                <span class="badge badge-<?= $rate >= 85 ? 'success' : ($rate >= 70 ? 'info' : 'warning') ?> px-2 py-1">
                                                    <?= $rate ?>%
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card-footer bg-light py-2">
                    <div class="d-flex flex-wrap justify-content-between align-items-center small text-muted">
                        <span><i class="fas fa-info-circle mr-1"></i>Hover over any cell to view punch time, IP, remarks, or admin adjustments.</span>
                        <span>Cutoff rule: 7:00 AM daily working cycle</span>
                    </div>
                </div>
            </div>

        </div>
    </section>
</div>

<style>
.sticky-col {
    position: sticky;
    left: 0;
    z-index: 2;
    box-shadow: 2px 0 5px rgba(0,0,0,0.05);
}
.attendance-matrix-table td, .attendance-matrix-table th {
    vertical-align: middle;
}
.matrix-pill {
    display: inline-block;
    width: 28px;
    height: 22px;
    line-height: 22px;
    border-radius: 4px;
    font-weight: 700;
    font-size: 0.72rem;
    cursor: default;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}
</style>

<?php
$extraScripts = '
<script>
$(document).ready(function() {
    $("[data-toggle=\"tooltip\"]").tooltip({
        placement: "top",
        boundary: "window"
    });
});
</script>
';
require_once __DIR__ . '/../includes/footer.php';
?>
