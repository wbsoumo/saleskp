<?php
/**
 * Admin Panel - Executive Dashboard & Analytics
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$db = get_db();
$currentUser = current_user();

// Date range filters for dashboard metrics
$preset = $_GET['preset'] ?? 'month';
$startDate = $_GET['start_date'] ?? '';
$endDate   = $_GET['end_date'] ?? '';

if ($preset === 'today') {
    $startDate = date('Y-m-d');
    $endDate   = date('Y-m-d');
} elseif ($preset === 'yesterday') {
    $startDate = date('Y-m-d', strtotime('-1 day'));
    $endDate   = date('Y-m-d', strtotime('-1 day'));
} elseif ($preset === '7days') {
    $startDate = date('Y-m-d', strtotime('-6 days'));
    $endDate   = date('Y-m-d');
} elseif ($preset === 'month') {
    $startDate = date('Y-m-01');
    $endDate   = date('Y-m-d');
} elseif ($preset === 'all') {
    $startDate = '';
    $endDate   = '';
} else {
    if (empty($startDate)) $startDate = date('Y-m-01');
    if (empty($endDate)) $endDate = date('Y-m-d');
}

// -------------------------------------------------------------
// Fetch Aggregated KPI Metrics
// -------------------------------------------------------------
$kpiSql = "
    SELECT 
        COALESCE(SUM(total_calls), 0) AS total_calls,
        COALESCE(SUM(call_attempts), 0) AS call_attempts,
        COALESCE(SUM(interested_leads), 0) AS interested_leads,
        COALESCE(SUM(meetings_booked), 0) AS meetings_booked,
        COALESCE(SUM(sales_closed), 0) AS sales_closed,
        COUNT(DISTINCT report_date) AS active_reporting_days,
        COUNT(DISTINCT user_id) AS active_telecallers
    FROM daily_reports
    WHERE 1=1
";
$kpiParams = [];

if (!empty($startDate)) {
    $kpiSql .= " AND report_date >= ?";
    $kpiParams[] = $startDate;
}
if (!empty($endDate)) {
    $kpiSql .= " AND report_date <= ?";
    $kpiParams[] = $endDate;
}

$stmtKpi = $db->prepare($kpiSql);
$stmtKpi->execute($kpiParams);
$kpi = $stmtKpi->fetch();

// Total Recordings Count
$recSql = "
    SELECT COUNT(*) AS total_recordings
    FROM call_recordings c
    JOIN daily_reports r ON r.id = c.report_id
    WHERE 1=1
";
$recParams = [];
if (!empty($startDate)) {
    $recSql .= " AND r.report_date >= ?";
    $recParams[] = $startDate;
}
if (!empty($endDate)) {
    $recSql .= " AND r.report_date <= ?";
    $recParams[] = $endDate;
}
$stmtRec = $db->prepare($recSql);
$stmtRec->execute($recParams);
$totalRecordingsCount = (int)$stmtRec->fetchColumn();

// Total active telecallers registered in system
$totalTelecallersCount = (int)$db->query("SELECT COUNT(*) FROM users WHERE role = 'employee' AND status = 'active'")->fetchColumn();

// -------------------------------------------------------------
// Telecaller Performance Leaderboard
// -------------------------------------------------------------
$leadSql = "
    SELECT 
        u.id, u.employee_id, u.name, u.email, u.phone,
        COALESCE(SUM(r.total_calls), 0) AS calls,
        COALESCE(SUM(r.call_attempts), 0) AS attempts,
        COALESCE(SUM(r.interested_leads), 0) AS interested,
        COALESCE(SUM(r.meetings_booked), 0) AS meetings,
        COALESCE(SUM(r.sales_closed), 0) AS sales,
        COUNT(DISTINCT c.id) AS recordings_uploaded
    FROM users u
    LEFT JOIN daily_reports r ON r.user_id = u.id " . (!empty($startDate) ? " AND r.report_date >= '$startDate'" : "") . (!empty($endDate) ? " AND r.report_date <= '$endDate'" : "") . "
    LEFT JOIN call_recordings c ON c.report_id = r.id
    GROUP BY u.id
    ORDER BY sales DESC, meetings DESC, interested DESC, calls DESC
";
$leaderboard = $db->query($leadSql)->fetchAll();

// Lead CRM overview stats
$totalLeadsCount = (int)$db->query("SELECT COUNT(*) FROM leads")->fetchColumn();
$unassignedLeadsCount = (int)$db->query("SELECT COUNT(*) FROM leads WHERE assigned_to IS NULL")->fetchColumn();
$activeFollowupsCount = (int)$db->query("SELECT COUNT(*) FROM leads WHERE status = 'Follow-up Required'")->fetchColumn();

// -------------------------------------------------------------
// Daily Trend Chart Data (Last 7 Days)
// -------------------------------------------------------------
$trendDays = [];
$chartLabels = [];
$chartCalls = [];
$chartInterested = [];
$chartMeetings = [];
$chartSales = [];

for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $trendDays[$d] = [
        'label' => date('D, d M', strtotime($d)),
        'calls' => 0,
        'interested' => 0,
        'meetings' => 0,
        'sales' => 0
    ];
}

$trendSql = "
    SELECT report_date,
           SUM(total_calls) as calls,
           SUM(interested_leads) as interested,
           SUM(meetings_booked) as meetings,
           SUM(sales_closed) as sales
    FROM daily_reports
    WHERE report_date >= ?
    GROUP BY report_date
    ORDER BY report_date ASC
";
$trendStmt = $db->prepare($trendSql);
$trendStmt->execute([date('Y-m-d', strtotime('-6 days'))]);
$trendResults = $trendStmt->fetchAll();

foreach ($trendResults as $tr) {
    if (isset($trendDays[$tr['report_date']])) {
        $trendDays[$tr['report_date']]['calls'] = (int)$tr['calls'];
        $trendDays[$tr['report_date']]['interested'] = (int)$tr['interested'];
        $trendDays[$tr['report_date']]['meetings'] = (int)$tr['meetings'];
        $trendDays[$tr['report_date']]['sales'] = (int)$tr['sales'];
    }
}

foreach ($trendDays as $td) {
    $chartLabels[] = $td['label'];
    $chartCalls[] = $td['calls'];
    $chartInterested[] = $td['interested'];
    $chartMeetings[] = $td['meetings'];
    $chartSales[] = $td['sales'];
}

// -------------------------------------------------------------
// Recent 5 Call Recordings with Audio Players
// -------------------------------------------------------------
$recentRecSql = "
    SELECT c.*, u.name AS employee_name, u.employee_id AS emp_code, r.report_date
    FROM call_recordings c
    JOIN users u ON u.id = c.user_id
    JOIN daily_reports r ON r.id = c.report_id
    ORDER BY c.id DESC
    LIMIT 5
";
$recentRecordings = $db->query($recentRecSql)->fetchAll();

// -------------------------------------------------------------
// Today's Attendance Overview
// -------------------------------------------------------------
$todayWorkingDate = get_current_attendance_date();
$stmtTodayAtt = $db->prepare("
    SELECT 
        COUNT(*) as total_punched,
        SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as today_present,
        SUM(CASE WHEN status = 'Half Day' THEN 1 ELSE 0 END) as today_half_day,
        SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as today_absent
    FROM attendance
    WHERE attendance_date = ?
");
$stmtTodayAtt->execute([$todayWorkingDate]);
$todayAtt = $stmtTodayAtt->fetch();
$todayAttPresent = (int)($todayAtt['today_present'] ?? 0);
$todayAttHalfDay = (int)($todayAtt['today_half_day'] ?? 0);
$todayAttAbsent  = (int)($todayAtt['today_absent'] ?? 0);
$todayAttPunched = (int)($todayAtt['total_punched'] ?? 0);
$todayAttPending = max(0, $totalTelecallersCount - $todayAttPunched);

$pageTitle = "Admin Dashboard";
$activeNav = 'admin_dashboard';
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
                        <i class="fas fa-chart-line text-primary mr-2"></i> Performance Dashboard
                    </h1>
                    <span class="text-muted small">Telecaller performance overview, call statistics, and activity</span>
                </div>
                <div class="col-sm-6 text-sm-right mt-2 mt-sm-0">
                    <div class="btn-group shadow-sm">
                        <a href="index.php?preset=today" class="btn btn-sm <?= $preset === 'today' ? 'btn-primary' : 'btn-outline-secondary' ?>">Today</a>
                        <a href="index.php?preset=yesterday" class="btn btn-sm <?= $preset === 'yesterday' ? 'btn-primary' : 'btn-outline-secondary' ?>">Yesterday</a>
                        <a href="index.php?preset=7days" class="btn btn-sm <?= $preset === '7days' ? 'btn-primary' : 'btn-outline-secondary' ?>">Last 7 Days</a>
                        <a href="index.php?preset=month" class="btn btn-sm <?= $preset === 'month' ? 'btn-primary' : 'btn-outline-secondary' ?>">This Month</a>
                    </div>
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

            <!-- Custom Date Filter Bar -->
            <div class="card card-outline card-secondary shadow-sm mb-4">
                <div class="card-body py-2">
                    <form action="index.php" method="GET" class="row align-items-center">
                        <input type="hidden" name="preset" value="custom">
                        <div class="col-md-3 col-sm-6 my-1">
                            <label class="small text-muted font-weight-bold mb-0">From Date</label>
                            <input type="date" name="start_date" class="form-control form-control-sm" value="<?= e($startDate) ?>">
                        </div>
                        <div class="col-md-3 col-sm-6 my-1">
                            <label class="small text-muted font-weight-bold mb-0">To Date</label>
                            <input type="date" name="end_date" class="form-control form-control-sm" value="<?= e($endDate) ?>">
                        </div>
                        <div class="col-md-3 col-sm-12 my-1 d-flex align-items-end pt-sm-3">
                            <button type="submit" class="btn btn-sm btn-primary mr-2 flex-fill">
                                <i class="fas fa-filter mr-1"></i> Apply Filter
                            </button>
                            <a href="index.php?preset=month" class="btn btn-sm btn-outline-secondary">Reset</a>
                        </div>
                        <div class="col-md-3 text-md-right text-muted small my-1">
                            Reporting Scope: <strong><?= !empty($startDate) ? format_date($startDate) : 'Beginning' ?></strong> to <strong><?= !empty($endDate) ? format_date($endDate) : 'Today' ?></strong>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Dashboard Metric KPI Cards -->
            <div class="row">
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-info">
                        <div class="inner">
                            <h3><?= number_format((int)$kpi['total_calls']) ?></h3>
                            <p>Total Calls Made</p>
                        </div>
                        <div class="icon"><i class="fas fa-phone-volume"></i></div>
                        <a href="reports.php" class="small-box-footer">View detailed logs <i class="fas fa-arrow-circle-right"></i></a>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-primary">
                        <div class="inner">
                            <h3><?= number_format((int)$kpi['interested_leads']) ?></h3>
                            <p>Interested Leads</p>
                        </div>
                        <div class="icon"><i class="fas fa-thumbs-up"></i></div>
                        <a href="reports.php" class="small-box-footer">View leads <i class="fas fa-arrow-circle-right"></i></a>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-warning">
                        <div class="inner">
                            <h3><?= number_format((int)$kpi['meetings_booked']) ?></h3>
                            <p>Meetings Booked</p>
                        </div>
                        <div class="icon"><i class="fas fa-calendar-check"></i></div>
                        <a href="reports.php" class="small-box-footer">View meetings <i class="fas fa-arrow-circle-right"></i></a>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-success">
                        <div class="inner">
                            <h3><?= number_format((int)$kpi['sales_closed']) ?></h3>
                            <p>Sales Closed</p>
                        </div>
                        <div class="icon"><i class="fas fa-trophy"></i></div>
                        <a href="reports.php" class="small-box-footer">View conversions <i class="fas fa-arrow-circle-right"></i></a>
                    </div>
                </div>
            </div>

            <!-- Lead Management & CRM Pipeline Banner -->
            <div class="card bg-white border shadow-sm mb-4">
                <div class="card-body p-3 d-flex align-items-center justify-content-between flex-wrap">
                    <div class="d-flex align-items-center mb-2 mb-md-0">
                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center mr-3" style="width: 48px; height: 48px;">
                            <i class="fas fa-user-friends fa-lg"></i>
                        </div>
                        <div>
                            <h5 class="font-weight-bold text-dark mb-0">Leads & Calling Pipeline</h5>
                            <span class="text-muted small">
                                <strong><?= $totalLeadsCount ?></strong> Total Leads &bull; 
                                <span class="text-primary font-weight-bold"><?= $totalLeadsCount - $unassignedLeadsCount ?></span> Assigned &bull; 
                                <span class="text-warning font-weight-bold"><?= $activeFollowupsCount ?></span> Follow-ups Scheduled &bull;
                                <span class="text-secondary"><?= $unassignedLeadsCount ?> Unassigned</span>
                            </span>
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="leads.php" class="btn btn-sm btn-primary shadow-sm mr-2 font-weight-bold">
                            <i class="fas fa-table mr-1"></i> Manage Leads
                        </a>
                        <a href="leads.php?open_modal=import" class="btn btn-sm btn-outline-success font-weight-bold">
                            <i class="fas fa-file-excel mr-1"></i> Import Leads (Excel/CSV)
                        </a>
                    </div>
                </div>
            </div>

            <!-- Attendance Overview Banner -->
            <div class="card bg-white border shadow-sm mb-4">
                <div class="card-body p-3 d-flex align-items-center justify-content-between flex-wrap">
                    <div class="d-flex align-items-center mb-2 mb-md-0">
                        <div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center mr-3" style="width: 48px; height: 48px;">
                            <i class="fas fa-user-clock fa-lg"></i>
                        </div>
                        <div>
                            <h5 class="font-weight-bold text-dark mb-0">Today's Attendance Status (<?= date('d M Y', strtotime($todayWorkingDate)) ?>)</h5>
                            <span class="text-muted small">
                                <span class="badge badge-success px-2 py-1 font-weight-bold mr-1"><?= $todayAttPresent ?> Present</span> &bull; 
                                <span class="badge badge-warning text-dark px-2 py-1 font-weight-bold mr-1"><?= $todayAttHalfDay ?> Half Day</span> &bull; 
                                <span class="badge badge-danger px-2 py-1 font-weight-bold mr-1"><?= $todayAttAbsent ?> Absent</span> &bull; 
                                <span class="text-secondary"><?= $todayAttPending ?> Pending / Unmarked (Cutoff: 7:00 AM)</span>
                            </span>
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="attendance.php" class="btn btn-sm btn-outline-primary shadow-sm mr-2 font-weight-bold">
                            <i class="fas fa-list-check mr-1"></i> Attendance Logs
                        </a>
                        <a href="attendance_sheet.php" class="btn btn-sm btn-outline-info font-weight-bold">
                            <i class="fas fa-table mr-1"></i> Monthly Sheet Matrix
                        </a>
                    </div>
                </div>
            </div>

            <!-- Secondary Metrics: Attempts, Recordings, Telecallers -->
            <div class="row">
                <div class="col-md-4 col-sm-6">
                    <div class="info-box shadow-sm">
                        <span class="info-box-icon bg-secondary elevation-1"><i class="fas fa-redo-alt"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Call Attempts</span>
                            <span class="info-box-number"><?= number_format((int)$kpi['call_attempts']) ?></span>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-sm-6">
                    <div class="info-box shadow-sm">
                        <span class="info-box-icon bg-danger elevation-1"><i class="fas fa-microphone-alt"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Recordings Uploaded</span>
                            <span class="info-box-number"><?= number_format($totalRecordingsCount) ?></span>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-sm-12">
                    <div class="info-box shadow-sm">
                        <span class="info-box-icon bg-teal text-white elevation-1"><i class="fas fa-users"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Active Telecallers</span>
                            <span class="info-box-number"><?= $totalTelecallersCount ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="row">
                <div class="col-lg-8">
                    <div class="card card-outline card-primary shadow">
                        <div class="card-header bg-white">
                            <h3 class="card-title font-weight-bold text-dark">
                                <i class="fas fa-chart-area mr-1 text-primary"></i> 7-Day Performance Trend
                            </h3>
                        </div>
                        <div class="card-body">
                            <canvas id="trendChart" style="min-height: 280px; height: 280px; max-height: 280px; max-width: 100%;"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card card-outline card-success shadow">
                        <div class="card-header bg-white">
                            <h3 class="card-title font-weight-bold text-dark">
                                <i class="fas fa-chart-pie mr-1 text-success"></i> Funnel Conversion Ratio
                            </h3>
                        </div>
                        <div class="card-body">
                            <canvas id="funnelChart" style="min-height: 280px; height: 280px; max-height: 280px; max-width: 100%;"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Telecaller Performance Leaderboard -->
            <div class="card card-outline card-info shadow mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h3 class="card-title font-weight-bold text-dark">
                        <i class="fas fa-trophy text-warning mr-1"></i> Telecaller Performance Leaderboard
                    </h3>
                    <a href="reports.php" class="btn btn-sm btn-outline-primary">View Full Logs</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped align-middle mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>Telecaller</th>
                                    <th>Employee ID</th>
                                    <th class="text-center">Total Calls</th>
                                    <th class="text-center">Attempts</th>
                                    <th class="text-center">Interested Leads</th>
                                    <th class="text-center">Meetings Booked</th>
                                    <th class="text-center">Sales Closed</th>
                                    <th class="text-center">Recordings</th>
                                    <th class="text-center">Conversion Rate</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($leaderboard)): ?>
                                    <tr>
                                        <td colspan="9" class="text-center py-4 text-muted">No active telecaller reports recorded yet for this period.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($leaderboard as $index => $emp): 
                                        $calls = (int)$emp['calls'];
                                        $sales = (int)$emp['sales'];
                                        $rate = $calls > 0 ? round(($sales / $calls) * 100, 1) : 0;
                                    ?>
                                        <tr>
                                            <td class="font-weight-bold text-dark">
                                                <div class="d-flex align-items-center">
                                                    <span class="badge badge-light border rounded-circle mr-2 d-inline-flex justify-content-center align-items-center" style="width: 24px; height: 24px;">
                                                        <?= $index + 1 ?>
                                                    </span>
                                                    <span><?= e($emp['name']) ?></span>
                                                </div>
                                            </td>
                                            <td><span class="badge badge-secondary"><?= e($emp['employee_id']) ?></span></td>
                                            <td class="text-center font-weight-bold text-info"><?= number_format($calls) ?></td>
                                            <td class="text-center text-secondary"><?= number_format((int)$emp['attempts']) ?></td>
                                            <td class="text-center font-weight-bold text-primary"><?= number_format((int)$emp['interested']) ?></td>
                                            <td class="text-center font-weight-bold text-warning"><?= number_format((int)$emp['meetings']) ?></td>
                                            <td class="text-center font-weight-bold text-success">
                                                <span class="badge badge-success px-2 py-1"><?= number_format($sales) ?></span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge badge-info"><i class="fas fa-microphone mr-1"></i><?= (int)$emp['recordings_uploaded'] ?></span>
                                            </td>
                                            <td class="text-center font-weight-bold">
                                                <?= $rate ?>%
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Recent Call Recordings with Inline Players -->
            <div class="card card-outline card-danger shadow mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h3 class="card-title font-weight-bold text-dark">
                        <i class="fas fa-microphone-alt text-danger mr-1"></i> Recent Call Recordings
                    </h3>
                    <a href="recordings.php" class="btn btn-sm btn-outline-danger">
                        <i class="fas fa-search mr-1"></i> Search All Recordings
                    </a>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($recentRecordings)): ?>
                        <div class="text-center py-4 text-muted">No call recordings uploaded recently.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-striped align-middle mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Telecaller</th>
                                        <th>Customer Phone</th>
                                        <th>Lead Name</th>
                                        <th>Call Status</th>
                                        <th>Duration</th>
                                        <th style="min-width: 320px;">Play Recording</th>
                                        <th>Date</th>
                                        <th class="text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentRecordings as $rec): ?>
                                        <tr>
                                            <td class="font-weight-bold text-dark">
                                                <?= e($rec['employee_name']) ?> <small class="text-muted">(<?= e($rec['emp_code']) ?>)</small>
                                            </td>
                                            <td class="font-weight-bold">
                                                <i class="fas fa-phone mr-1 text-muted small"></i> <?= format_phone($rec['phone_number']) ?>
                                            </td>
                                            <td><?= !empty($rec['customer_name']) ? e($rec['customer_name']) : '<span class="text-muted small">N/A</span>' ?></td>
                                            <td><?= get_call_status_badge($rec['call_status']) ?></td>
                                            <td>
                                                <span class="badge badge-light border">
                                                    <i class="far fa-clock mr-1"></i> <?= !empty($rec['call_duration']) ? e($rec['call_duration']) : '--:--' ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="audio-player-container">
                                                    <audio id="audio_dash_<?= $rec['id'] ?>" controls preload="none">
                                                        <source src="<?= $baseUrl . '/' . e($rec['file_path']) ?>" type="<?= e($rec['file_mime']) ?>">
                                                        Audio not supported.
                                                    </audio>
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <button type="button" class="btn btn-outline-secondary btn-xs speed-btn" data-target="audio_dash_<?= $rec['id'] ?>" data-speed="1.0">1x</button>
                                                        <button type="button" class="btn btn-outline-secondary btn-xs speed-btn" data-target="audio_dash_<?= $rec['id'] ?>" data-speed="1.5">1.5x</button>
                                                        <button type="button" class="btn btn-outline-secondary btn-xs speed-btn" data-target="audio_dash_<?= $rec['id'] ?>" data-speed="2.0">2x</button>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><small class="text-muted"><?= format_date($rec['report_date']) ?></small></td>
                                            <td class="text-right">
                                                <a href="<?= $baseUrl . '/' . e($rec['file_path']) ?>" download class="btn btn-sm btn-outline-secondary" title="Download">
                                                    <i class="fas fa-download"></i>
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

<?php
$jsonLabels = json_encode($chartLabels);
$jsonCalls = json_encode($chartCalls);
$jsonInterested = json_encode($chartInterested);
$jsonMeetings = json_encode($chartMeetings);
$jsonSales = json_encode($chartSales);

$kpiInterested = (int)$kpi['interested_leads'];
$kpiMeetings = (int)$kpi['meetings_booked'];
$kpiSales = (int)$kpi['sales_closed'];

$extraScripts = "
<script>
    // 1. Trend Chart
    const ctxTrend = document.getElementById('trendChart').getContext('2d');
    new Chart(ctxTrend, {
        type: 'line',
        data: {
            labels: $jsonLabels,
            datasets: [
                {
                    label: 'Total Calls',
                    data: $jsonCalls,
                    borderColor: '#17a2b8',
                    backgroundColor: 'rgba(23, 162, 184, 0.1)',
                    tension: 0.3,
                    fill: true
                },
                {
                    label: 'Interested Leads',
                    data: $jsonInterested,
                    borderColor: '#007bff',
                    backgroundColor: 'transparent',
                    tension: 0.3
                },
                {
                    label: 'Meetings Booked',
                    data: $jsonMeetings,
                    borderColor: '#ffc107',
                    backgroundColor: 'transparent',
                    tension: 0.3
                },
                {
                    label: 'Sales Closed',
                    data: $jsonSales,
                    borderColor: '#28a745',
                    backgroundColor: 'transparent',
                    borderWidth: 3,
                    tension: 0.3
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' }
            },
            scales: {
                y: { beginAtZero: true }
            }
        }
    });

    // 2. Funnel Donut Chart
    const ctxFunnel = document.getElementById('funnelChart').getContext('2d');
    new Chart(ctxFunnel, {
        type: 'doughnut',
        data: {
            labels: ['Interested Leads', 'Meetings Booked', 'Sales Closed'],
            datasets: [{
                data: [$kpiInterested, $kpiMeetings, $kpiSales],
                backgroundColor: ['#007bff', '#ffc107', '#28a745']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });
</script>
";
require_once __DIR__ . '/../includes/footer.php';
?>
