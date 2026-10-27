<?php
/**
 * Admin Panel - All Employees Daily Reports & Performance Statistics
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$db = get_db();
$currentUser = current_user();

// Fetch all active/inactive employees for filter dropdown
$allEmployees = $db->query("SELECT id, employee_id, name FROM users WHERE role = 'employee' ORDER BY name ASC")->fetchAll();

// Handle Filters
$selectedEmpId = (int)($_GET['employee_id'] ?? 0);
$startDate     = clean_string($_GET['start_date'] ?? date('Y-m-01'));
$endDate       = clean_string($_GET['end_date'] ?? date('Y-m-d'));
$export        = clean_string($_GET['export'] ?? '');

// Base Query
$whereClause = "WHERE 1=1";
$params = [];

if ($selectedEmpId > 0) {
    $whereClause .= " AND r.user_id = ?";
    $params[] = $selectedEmpId;
}
if (!empty($startDate)) {
    $whereClause .= " AND r.report_date >= ?";
    $params[] = $startDate;
}
if (!empty($endDate)) {
    $whereClause .= " AND r.report_date <= ?";
    $params[] = $endDate;
}

// -------------------------------------------------------------
// CSV Export Handler
// -------------------------------------------------------------
if ($export === 'csv') {
    $exportSql = "
        SELECT r.report_date, u.employee_id, u.name as employee_name,
               r.total_calls, r.call_attempts, r.interested_leads,
               r.meetings_booked, r.sales_closed, r.notes,
               COUNT(c.id) as recordings_count
        FROM daily_reports r
        JOIN users u ON u.id = r.user_id
        LEFT JOIN call_recordings c ON c.report_id = r.id
        $whereClause
        GROUP BY r.id
        ORDER BY r.report_date DESC, u.name ASC
    ";
    $stmtExp = $db->prepare($exportSql);
    $stmtExp->execute($params);
    $rows = $stmtExp->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=telecaller_daily_reports_' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Report Date', 'Employee ID', 'Telecaller Name', 'Total Calls', 'Call Attempts', 'Interested Leads', 'Meetings Booked', 'Sales Closed', 'Recordings Count', 'Notes']);

    foreach ($rows as $row) {
        fputcsv($output, [
            $row['report_date'],
            $row['employee_id'],
            $row['employee_name'],
            $row['total_calls'],
            $row['call_attempts'],
            $row['interested_leads'],
            $row['meetings_booked'],
            $row['sales_closed'],
            $row['recordings_count'],
            str_replace(["\r", "\n"], ' ', $row['notes'] ?? '')
        ]);
    }
    fclose($output);
    exit;
}

// -------------------------------------------------------------
// Query Daily Reports
// -------------------------------------------------------------
$reportsSql = "
    SELECT r.*, u.employee_id, u.name as employee_name, u.phone as employee_phone,
           COUNT(c.id) as recordings_count
    FROM daily_reports r
    JOIN users u ON u.id = r.user_id
    LEFT JOIN call_recordings c ON c.report_id = r.id
    $whereClause
    GROUP BY r.id
    ORDER BY r.report_date DESC, u.name ASC
";
$stmtReports = $db->prepare($reportsSql);
$stmtReports->execute($params);
$reports = $stmtReports->fetchAll();

// Calculate Summary Statistics for Filtered Scope
$sumCalls = 0;
$sumAttempts = 0;
$sumInterested = 0;
$sumMeetings = 0;
$sumSales = 0;
$sumRecordings = 0;

foreach ($reports as $r) {
    $sumCalls += (int)$r['total_calls'];
    $sumAttempts += (int)$r['call_attempts'];
    $sumInterested += (int)$r['interested_leads'];
    $sumMeetings += (int)$r['meetings_booked'];
    $sumSales += (int)$r['sales_closed'];
    $sumRecordings += (int)$r['recordings_count'];
}

$pageTitle = "Daily Reports";
$activeNav = 'admin_reports';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
$flash = get_flash();
?>

<div class="content-wrapper">
    <!-- Content Header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2 align-items-center">
                <div class="col-sm-6">
                    <h1 class="m-0 font-weight-bold text-dark">
                        <i class="fas fa-chart-line text-primary mr-2"></i> Telecaller Daily Reports
                    </h1>
                    <span class="text-muted small">Comprehensive daily performance records across all team members</span>
                </div>
                <div class="col-sm-6 text-sm-right mt-2 mt-sm-0">
                    <a href="reports.php?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" class="btn btn-outline-success btn-sm shadow-sm">
                        <i class="fas fa-file-excel mr-1"></i> Export to CSV
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <section class="content">
        <div class="container-fluid">

            <?php if ($flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show" role="alert">
                    <?= e($flash['message']) ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>

            <!-- Filter Controls Bar -->
            <div class="card card-outline card-secondary shadow-sm mb-4">
                <div class="card-body py-3">
                    <form action="reports.php" method="GET" class="row align-items-end">
                        <div class="col-md-3 col-sm-6 mb-2 mb-md-0">
                            <label class="font-weight-bold text-secondary small mb-1">Telecaller Employee</label>
                            <select name="employee_id" class="form-control form-control-sm">
                                <option value="0">-- All Telecallers --</option>
                                <?php foreach ($allEmployees as $emp): ?>
                                    <option value="<?= $emp['id'] ?>" <?= $selectedEmpId === (int)$emp['id'] ? 'selected' : '' ?>>
                                        <?= e($emp['name']) ?> (<?= e($emp['employee_id']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-2 mb-md-0">
                            <label class="font-weight-bold text-secondary small mb-1">From Date</label>
                            <input type="date" name="start_date" class="form-control form-control-sm" value="<?= e($startDate) ?>">
                        </div>
                        <div class="col-md-3 col-sm-6 mb-2 mb-md-0">
                            <label class="font-weight-bold text-secondary small mb-1">To Date</label>
                            <input type="date" name="end_date" class="form-control form-control-sm" value="<?= e($endDate) ?>">
                        </div>
                        <div class="col-md-3 col-sm-6 d-flex gap-2">
                            <button type="submit" class="btn btn-sm btn-primary flex-fill mr-1">
                                <i class="fas fa-filter mr-1"></i> Filter
                            </button>
                            <a href="reports.php" class="btn btn-sm btn-outline-secondary">
                                Reset
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Performance Summary Cards for Filtered Range -->
            <div class="row">
                <div class="col-lg-2 col-6">
                    <div class="small-box bg-info">
                        <div class="inner">
                            <h3><?= number_format($sumCalls) ?></h3>
                            <p>Total Calls</p>
                        </div>
                        <div class="icon"><i class="fas fa-phone-alt"></i></div>
                    </div>
                </div>
                <div class="col-lg-2 col-6">
                    <div class="small-box bg-secondary">
                        <div class="inner">
                            <h3><?= number_format($sumAttempts) ?></h3>
                            <p>Call Attempts</p>
                        </div>
                        <div class="icon"><i class="fas fa-redo-alt"></i></div>
                    </div>
                </div>
                <div class="col-lg-2 col-6">
                    <div class="small-box bg-primary">
                        <div class="inner">
                            <h3><?= number_format($sumInterested) ?></h3>
                            <p>Interested Leads</p>
                        </div>
                        <div class="icon"><i class="fas fa-thumbs-up"></i></div>
                    </div>
                </div>
                <div class="col-lg-2 col-6">
                    <div class="small-box bg-warning">
                        <div class="inner">
                            <h3><?= number_format($sumMeetings) ?></h3>
                            <p>Meetings Booked</p>
                        </div>
                        <div class="icon"><i class="fas fa-calendar-check"></i></div>
                    </div>
                </div>
                <div class="col-lg-2 col-6">
                    <div class="small-box bg-success">
                        <div class="inner">
                            <h3><?= number_format($sumSales) ?></h3>
                            <p>Sales Closed</p>
                        </div>
                        <div class="icon"><i class="fas fa-trophy"></i></div>
                    </div>
                </div>
                <div class="col-lg-2 col-6">
                    <div class="small-box bg-danger">
                        <div class="inner">
                            <h3><?= number_format($sumRecordings) ?></h3>
                            <p>Recordings</p>
                        </div>
                        <div class="icon"><i class="fas fa-microphone"></i></div>
                    </div>
                </div>
            </div>

            <!-- Reports Table -->
            <div class="card card-outline card-primary shadow">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h3 class="card-title font-weight-bold text-dark">
                        <i class="fas fa-list-alt text-primary mr-1"></i> Daily Performance Reports
                    </h3>
                    <span class="badge badge-light border"><?= count($reports) ?> Reports Found</span>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($reports)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-search fa-3x text-muted mb-3"></i>
                            <h5 class="text-secondary font-weight-bold">No daily reports matching criteria</h5>
                            <p class="text-muted small">Try adjusting your filters or date range.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-striped align-middle mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Telecaller</th>
                                        <th class="text-center">Total Calls</th>
                                        <th class="text-center">Attempts</th>
                                        <th class="text-center">Interested</th>
                                        <th class="text-center">Meetings</th>
                                        <th class="text-center">Sales Closed</th>
                                        <th class="text-center">Recordings</th>
                                        <th>Shift Notes</th>
                                        <th class="text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reports as $r): ?>
                                        <tr>
                                            <td class="font-weight-bold text-dark">
                                                <i class="far fa-calendar-alt text-primary mr-1"></i>
                                                <?= format_date($r['report_date']) ?>
                                            </td>
                                            <td>
                                                <div class="font-weight-bold text-dark"><?= e($r['employee_name']) ?></div>
                                                <span class="badge badge-secondary"><?= e($r['employee_id']) ?></span>
                                            </td>
                                            <td class="text-center font-weight-bold text-info">
                                                <?= number_format((int)$r['total_calls']) ?>
                                            </td>
                                            <td class="text-center text-secondary">
                                                <?= number_format((int)$r['call_attempts']) ?>
                                            </td>
                                            <td class="text-center font-weight-bold text-primary">
                                                <?= number_format((int)$r['interested_leads']) ?>
                                            </td>
                                            <td class="text-center font-weight-bold text-warning">
                                                <?= number_format((int)$r['meetings_booked']) ?>
                                            </td>
                                            <td class="text-center font-weight-bold text-success">
                                                <span class="badge badge-success px-2 py-1">
                                                    <?= number_format((int)$r['sales_closed']) ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge <?= $r['recordings_count'] > 0 ? 'badge-primary' : 'badge-light border' ?>">
                                                    <i class="fas fa-microphone mr-1"></i> <?= $r['recordings_count'] ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small class="text-muted text-truncate d-inline-block" style="max-width: 160px;">
                                                    <?= !empty($r['notes']) ? e($r['notes']) : '-' ?>
                                                </small>
                                            </td>
                                            <td class="text-right">
                                                <a href="report_view.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-primary shadow-sm" title="View Full Report & Recordings">
                                                    <i class="fas fa-folder-open mr-1"></i> Details
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </section>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
