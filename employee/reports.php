<?php
/**
 * Employee Portal - Historical Daily Reports & Call Audio Playback
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_employee();

$db = get_db();
$currentUser = current_user();
$userId = $currentUser['id'];

// Date range filters
$startDate = clean_string($_GET['start_date'] ?? date('Y-m-01')); // Default to 1st of current month
$endDate   = clean_string($_GET['end_date'] ?? date('Y-m-d'));

// Query daily reports for current employee within filter range
$query = "
    SELECT r.*, 
           COUNT(c.id) AS recordings_count
    FROM daily_reports r
    LEFT JOIN call_recordings c ON c.report_id = r.id
    WHERE r.user_id = ?
";
$params = [$userId];

if (!empty($startDate)) {
    $query .= " AND r.report_date >= ?";
    $params[] = $startDate;
}
if (!empty($endDate)) {
    $query .= " AND r.report_date <= ?";
    $params[] = $endDate;
}

$query .= " GROUP BY r.id ORDER BY r.report_date DESC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$reports = $stmt->fetchAll();

// Aggregated Summary Stats for the filtered period
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

$pageTitle = "My Performance Reports";
$activeNav = 'emp_reports';
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
                        <i class="fas fa-history text-primary mr-2"></i> My Performance Reports
                    </h1>
                    <span class="text-muted small">View your date-wise daily submissions and listen to uploaded recordings</span>
                </div>
                <div class="col-sm-6 text-sm-right mt-2 mt-sm-0">
                    <a href="today_report.php" class="btn btn-outline-primary btn-sm shadow-sm">
                        <i class="fas fa-calendar-day mr-1"></i> Today's Report
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

            <!-- Filter Card -->
            <div class="card card-outline card-secondary shadow-sm mb-4">
                <div class="card-body py-3">
                    <form action="reports.php" method="GET" class="row align-items-end">
                        <div class="col-md-4 col-sm-6 mb-2 mb-md-0">
                            <label class="font-weight-bold text-secondary small mb-1">From Date</label>
                            <div class="input-group input-group-sm">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="far fa-calendar-alt"></i></span>
                                </div>
                                <input type="date" name="start_date" class="form-control" value="<?= e($startDate) ?>">
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-6 mb-2 mb-md-0">
                            <label class="font-weight-bold text-secondary small mb-1">To Date</label>
                            <div class="input-group input-group-sm">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="far fa-calendar-alt"></i></span>
                                </div>
                                <input type="date" name="end_date" class="form-control" value="<?= e($endDate) ?>">
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-12 d-flex gap-2">
                            <button type="submit" class="btn btn-sm btn-primary flex-fill mr-1">
                                <i class="fas fa-filter mr-1"></i> Filter Reports
                            </button>
                            <a href="reports.php" class="btn btn-sm btn-outline-secondary">
                                Reset
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Aggregated KPI Summary for Filtered Period -->
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
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-warning">
                        <div class="inner">
                            <h3><?= number_format($sumMeetings) ?></h3>
                            <p>Meetings Booked</p>
                        </div>
                        <div class="icon"><i class="fas fa-calendar-check"></i></div>
                    </div>
                </div>
                <div class="col-lg-3 col-12">
                    <div class="small-box bg-success">
                        <div class="inner">
                            <h3><?= number_format($sumSales) ?></h3>
                            <p>Sales Closed</p>
                        </div>
                        <div class="icon"><i class="fas fa-trophy"></i></div>
                    </div>
                </div>
            </div>

            <!-- Date-wise Reports List -->
            <div class="card card-outline card-primary shadow">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h3 class="card-title font-weight-bold text-dark">
                        <i class="fas fa-table mr-1 text-primary"></i> Date-wise Submissions History
                    </h3>
                    <span class="badge badge-light border"><?= count($reports) ?> Days Found</span>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($reports)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-folder-open fa-3x text-muted mb-3"></i>
                            <h5 class="text-secondary font-weight-bold">No reports found for this period</h5>
                            <p class="text-muted small">Try adjusting your date filters or submit today's report.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-striped align-middle mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Date</th>
                                        <th class="text-center">Total Calls</th>
                                        <th class="text-center">Attempts</th>
                                        <th class="text-center">Interested</th>
                                        <th class="text-center">Meetings</th>
                                        <th class="text-center">Sales Closed</th>
                                        <th class="text-center">Recordings</th>
                                        <th>Notes</th>
                                        <th class="text-right">Recordings & Details</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reports as $r): ?>
                                        <tr>
                                            <td class="font-weight-bold text-dark">
                                                <i class="far fa-calendar-alt text-primary mr-1"></i>
                                                <?= format_date($r['report_date']) ?>
                                            </td>
                                            <td class="text-center font-weight-bold text-info">
                                                <?= number_format($r['total_calls']) ?>
                                            </td>
                                            <td class="text-center text-secondary">
                                                <?= number_format($r['call_attempts']) ?>
                                            </td>
                                            <td class="text-center font-weight-bold text-primary">
                                                <?= number_format($r['interested_leads']) ?>
                                            </td>
                                            <td class="text-center font-weight-bold text-warning">
                                                <?= number_format($r['meetings_booked']) ?>
                                            </td>
                                            <td class="text-center font-weight-bold text-success">
                                                <span class="badge badge-success px-2 py-1">
                                                    <?= number_format($r['sales_closed']) ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge <?= $r['recordings_count'] > 0 ? 'badge-primary' : 'badge-light border' ?>">
                                                    <i class="fas fa-microphone mr-1"></i> <?= $r['recordings_count'] ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small class="text-muted text-truncate d-inline-block" style="max-width: 180px;">
                                                    <?= !empty($r['notes']) ? e($r['notes']) : '-' ?>
                                                </small>
                                            </td>
                                            <td class="text-right">
                                                <button class="btn btn-sm btn-outline-primary" type="button" data-toggle="collapse" data-target="#recordings_<?= $r['id'] ?>" aria-expanded="false">
                                                    <i class="fas fa-play-circle mr-1"></i> View & Play (<?= $r['recordings_count'] ?>)
                                                </button>
                                            </td>
                                        </tr>

                                        <!-- Collapsible Recordings View for each date -->
                                        <tr class="collapse bg-light" id="recordings_<?= $r['id'] ?>">
                                            <td colspan="9" class="p-3">
                                                <?php
                                                $recStmt = $db->prepare("SELECT * FROM call_recordings WHERE report_id = ? ORDER BY id DESC");
                                                $recStmt->execute([$r['id']]);
                                                $recordingsList = $recStmt->fetchAll();
                                                ?>
                                                <div class="card card-body bg-white border shadow-sm mb-0">
                                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                                        <h6 class="font-weight-bold text-dark m-0">
                                                            <i class="fas fa-headphones text-primary mr-1"></i> Call Recordings for <?= format_date($r['report_date']) ?>
                                                        </h6>
                                                        <span class="text-muted small"><?= count($recordingsList) ?> call files recorded</span>
                                                    </div>

                                                    <?php if (empty($recordingsList)): ?>
                                                        <p class="text-muted small mb-0 font-italic">No call recordings were uploaded for this date.</p>
                                                    <?php else: ?>
                                                        <div class="table-responsive">
                                                            <table class="table table-sm table-bordered align-middle mb-0">
                                                                <thead class="thead-light">
                                                                    <tr>
                                                                        <th>Phone Number</th>
                                                                        <th>Lead Name</th>
                                                                        <th>Call Status</th>
                                                                        <th>Duration</th>
                                                                        <th style="min-width: 320px;">Play Audio Recording</th>
                                                                        <th>Notes</th>
                                                                        <th class="text-right">Download</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    <?php foreach ($recordingsList as $rec): ?>
                                                                        <tr>
                                                                            <td class="font-weight-bold text-dark">
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
                                                                                    <audio id="audio_rep_<?= $rec['id'] ?>" controls preload="none">
                                                                                        <source src="<?= $baseUrl . '/' . e($rec['file_path']) ?>" type="<?= e($rec['file_mime']) ?>">
                                                                                        Audio not supported.
                                                                                    </audio>
                                                                                    <div class="btn-group btn-group-sm" role="group">
                                                                                        <button type="button" class="btn btn-outline-secondary btn-xs speed-btn" data-target="audio_rep_<?= $rec['id'] ?>" data-speed="1.0">1x</button>
                                                                                        <button type="button" class="btn btn-outline-secondary btn-xs speed-btn" data-target="audio_rep_<?= $rec['id'] ?>" data-speed="1.5">1.5x</button>
                                                                                        <button type="button" class="btn btn-outline-secondary btn-xs speed-btn" data-target="audio_rep_<?= $rec['id'] ?>" data-speed="2.0">2x</button>
                                                                                    </div>
                                                                                </div>
                                                                                <small class="text-muted d-block mt-1">
                                                                                    <?= e($rec['original_name']) ?> (<?= format_file_size((int)$rec['file_size']) ?>)
                                                                                </small>
                                                                            </td>
                                                                            <td><small class="text-secondary"><?= !empty($rec['call_notes']) ? e($rec['call_notes']) : '-' ?></small></td>
                                                                            <td class="text-right">
                                                                                <a href="<?= $baseUrl . '/' . e($rec['file_path']) ?>" download class="btn btn-sm btn-outline-secondary" title="Download Audio File">
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
