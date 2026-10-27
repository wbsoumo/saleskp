<?php
/**
 * Employee Portal - Today's Daily Report & Call Recordings
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_employee();

$db = get_db();
$currentUser = current_user();
$userId = $currentUser['id'];
$employeeCode = $currentUser['employee_id'];
$todayDate = date('Y-m-d');
$todayFormatted = date('l, d F Y');

// Fetch today's report if it exists
$stmt = $db->prepare("SELECT * FROM daily_reports WHERE user_id = ? AND report_date = ? LIMIT 1");
$stmt->execute([$userId, $todayDate]);
$todayReport = $stmt->fetch();

$activeStep = $_GET['step'] ?? ($todayReport ? 'recordings' : 'form');

// -------------------------------------------------------------
// POST Handler: Submit or Update Today's Daily Report
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_daily_report') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Invalid security token. Please try again.');
        header('Location: ' . get_base_url() . '/employee/today_report.php');
        exit;
    }

    $totalCalls     = max(0, (int)($_POST['total_calls'] ?? 0));
    $callAttempts   = max(0, (int)($_POST['call_attempts'] ?? 0));
    $interested     = max(0, (int)($_POST['interested_leads'] ?? 0));
    $meetings       = max(0, (int)($_POST['meetings_booked'] ?? 0));
    $salesClosed    = max(0, (int)($_POST['sales_closed'] ?? 0));
    $notes          = clean_string($_POST['notes'] ?? '');

    try {
        if ($todayReport) {
            // Update existing report
            $updateStmt = $db->prepare("
                UPDATE daily_reports 
                SET total_calls = ?, call_attempts = ?, interested_leads = ?, meetings_booked = ?, sales_closed = ?, notes = ?, updated_at = NOW()
                WHERE id = ? AND user_id = ?
            ");
            $updateStmt->execute([$totalCalls, $callAttempts, $interested, $meetings, $salesClosed, $notes, $todayReport['id'], $userId]);
            set_flash('success', "Today's report statistics updated successfully!");
        } else {
            // Insert new report
            $insertStmt = $db->prepare("
                INSERT INTO daily_reports (user_id, report_date, total_calls, call_attempts, interested_leads, meetings_booked, sales_closed, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $insertStmt->execute([$userId, $todayDate, $totalCalls, $callAttempts, $interested, $meetings, $salesClosed, $notes]);
            set_flash('success', "Daily report submitted successfully! Proceed to upload call recordings below.");
        }
        header('Location: ' . get_base_url() . '/employee/today_report.php?step=recordings#recordingsSection');
        exit;
    } catch (Exception $e) {
        set_flash('danger', 'Database error: ' . $e->getMessage());
        header('Location: ' . get_base_url() . '/employee/today_report.php');
        exit;
    }
}

// -------------------------------------------------------------
// POST Handler: Upload Call Recording
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_recording') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Invalid security token. Please try again.');
        header('Location: ' . get_base_url() . '/employee/today_report.php?step=recordings');
        exit;
    }

    if (!$todayReport) {
        set_flash('danger', 'Please submit your daily report first before uploading call recordings.');
        header('Location: ' . get_base_url() . '/employee/today_report.php');
        exit;
    }

    $phoneNumber   = clean_string($_POST['phone_number'] ?? '');
    $customerName  = clean_string($_POST['customer_name'] ?? '');
    $callStatus    = clean_string($_POST['call_status'] ?? 'Interested');
    $callDuration  = clean_string($_POST['call_duration'] ?? '');
    $callNotes     = clean_string($_POST['call_notes'] ?? '');

    if (empty($phoneNumber)) {
        set_flash('danger', 'Customer Phone Number is required.');
        header('Location: ' . get_base_url() . '/employee/today_report.php?step=recordings');
        exit;
    }

    if (!isset($_FILES['audio_file']) || $_FILES['audio_file']['error'] !== UPLOAD_ERR_OK) {
        set_flash('danger', 'Please select a valid audio file to upload.');
        header('Location: ' . get_base_url() . '/employee/today_report.php?step=recordings');
        exit;
    }

    $uploadResult = save_call_recording($_FILES['audio_file'], $employeeCode, $todayDate);
    if (!$uploadResult['success']) {
        set_flash('danger', 'Upload failed: ' . $uploadResult['error']);
        header('Location: ' . get_base_url() . '/employee/today_report.php?step=recordings');
        exit;
    }

    try {
        $recStmt = $db->prepare("
            INSERT INTO call_recordings (
                report_id, user_id, phone_number, customer_name, call_status, call_duration, call_notes,
                file_path, file_name, original_name, file_size, file_mime
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $recStmt->execute([
            $todayReport['id'],
            $userId,
            $phoneNumber,
            $customerName,
            $callStatus,
            $callDuration,
            $callNotes,
            $uploadResult['file_path'],
            $uploadResult['file_name'],
            $uploadResult['original_name'],
            $uploadResult['file_size'],
            $uploadResult['file_mime']
        ]);

        set_flash('success', 'Call recording for ' . htmlspecialchars($phoneNumber) . ' uploaded successfully!');
        header('Location: ' . get_base_url() . '/employee/today_report.php?step=recordings#recordingsSection');
        exit;
    } catch (Exception $e) {
        set_flash('danger', 'Failed to save recording record: ' . $e->getMessage());
        header('Location: ' . get_base_url() . '/employee/today_report.php?step=recordings');
        exit;
    }
}

// -------------------------------------------------------------
// POST Handler: Delete Call Recording (Same-day deletion)
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_recording') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Invalid security token.');
        header('Location: ' . get_base_url() . '/employee/today_report.php?step=recordings');
        exit;
    }

    $recId = (int)($_POST['recording_id'] ?? 0);
    $fetchRec = $db->prepare("SELECT * FROM call_recordings WHERE id = ? AND user_id = ?");
    $fetchRec->execute([$recId, $userId]);
    $rec = $fetchRec->fetch();

    if ($rec) {
        // Remove physical file
        $diskPath = ROOT_PATH . '/' . $rec['file_path'];
        if (file_exists($diskPath)) {
            @unlink($diskPath);
        }
        $delStmt = $db->prepare("DELETE FROM call_recordings WHERE id = ?");
        $delStmt->execute([$recId]);
        set_flash('success', 'Recording deleted successfully.');
    }
    header('Location: ' . get_base_url() . '/employee/today_report.php?step=recordings#recordingsSection');
    exit;
}

// Fetch today's recordings if report exists
$todayRecordings = [];
if ($todayReport) {
    $recStmt = $db->prepare("SELECT * FROM call_recordings WHERE report_id = ? ORDER BY id DESC");
    $recStmt->execute([$todayReport['id']]);
    $todayRecordings = $recStmt->fetchAll();
}

$pageTitle = "Today's Report";
$activeNav = 'emp_today';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

$flash = get_flash();
?>

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2 align-items-center">
                <div class="col-sm-6">
                    <h1 class="m-0 font-weight-bold text-dark">
                        <i class="fas fa-calendar-day text-primary mr-2"></i> Today's Report
                    </h1>
                    <span class="text-muted small">Reporting Date: <strong><?= e($todayFormatted) ?></strong></span>
                </div>
                <div class="col-sm-6 text-sm-right mt-2 mt-sm-0">
                    <span class="badge badge-light border px-3 py-2 text-secondary shadow-sm">
                        <i class="fas fa-user-circle mr-1 text-primary"></i> <?= e($currentUser['name']) ?> (<?= e($currentUser['employee_id']) ?>)
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">

            <?php if ($flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show shadow-sm" role="alert">
                    <i class="fas <?= $flash['type'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle' ?> mr-2"></i>
                    <?= e($flash['message']) ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>

            <!-- Workflow Steps Indicator -->
            <div class="flow-wizard mb-4">
                <div class="flow-step <?= $todayReport ? 'completed' : 'active' ?>">
                    <i class="fas <?= $todayReport ? 'fa-check-circle text-success' : 'fa-edit text-primary' ?>"></i>
                    <span>Step 1: Daily Call Statistics</span>
                </div>
                <div class="flow-step <?= $todayReport ? 'active' : '' ?>">
                    <i class="fas <?= $todayReport ? 'fa-microphone text-primary' : 'fa-lock text-muted' ?>"></i>
                    <span>Step 2: Upload Call Recordings</span>
                </div>
            </div>

            <?php if (!$todayReport): ?>
                <!-- ========================================================
                     EMPTY STATE & INITIAL SUBMISSION FORM
                     The prompt specifies: "Today's Report should remain empty 
                     until the employee submits their daily report."
                     ======================================================== -->
                <div class="card card-outline card-primary shadow">
                    <div class="card-header bg-white">
                        <h3 class="card-title font-weight-bold text-dark">
                            <i class="fas fa-clipboard-list text-primary mr-1"></i> Today's Daily Report Entry
                        </h3>
                    </div>
                    <form action="today_report.php" method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="save_daily_report">

                        <div class="card-body">
                            <div class="alert alert-info border-0 shadow-sm mb-4">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-info-circle fa-2x mr-3 text-info"></i>
                                    <div>
                                        <h6 class="font-weight-bold mb-1">Today's report has not been submitted yet</h6>
                                        <p class="mb-0 small">Please enter your call performance numbers below to submit today's report. Once submitted, you will be able to upload call recordings with customer details.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4 col-sm-6">
                                    <div class="form-group">
                                        <label class="font-weight-bold text-secondary">
                                            <i class="fas fa-phone-alt text-primary mr-1"></i> Total Calls <span class="text-danger">*</span>
                                        </label>
                                        <input type="number" min="0" name="total_calls" class="form-control form-control-lg font-weight-bold" placeholder="0" value="0" required>
                                        <small class="form-text text-muted">All incoming and outgoing calls handled today.</small>
                                    </div>
                                </div>
                                <div class="col-md-4 col-sm-6">
                                    <div class="form-group">
                                        <label class="font-weight-bold text-secondary">
                                            <i class="fas fa-redo-alt text-secondary mr-1"></i> Call Attempts <span class="text-danger">*</span>
                                        </label>
                                        <input type="number" min="0" name="call_attempts" class="form-control form-control-lg font-weight-bold" placeholder="0" value="0" required>
                                        <small class="form-text text-muted">Ringing, busy, or unanswered attempts.</small>
                                    </div>
                                </div>
                                <div class="col-md-4 col-sm-6">
                                    <div class="form-group">
                                        <label class="font-weight-bold text-secondary">
                                            <i class="fas fa-thumbs-up text-info mr-1"></i> Interested Leads <span class="text-danger">*</span>
                                        </label>
                                        <input type="number" min="0" name="interested_leads" class="form-control form-control-lg font-weight-bold" placeholder="0" value="0" required>
                                        <small class="form-text text-muted">Prospects who showed positive interest.</small>
                                    </div>
                                </div>
                                <div class="col-md-6 col-sm-6">
                                    <div class="form-group">
                                        <label class="font-weight-bold text-secondary">
                                            <i class="fas fa-calendar-check text-warning mr-1"></i> Meetings Booked <span class="text-danger">*</span>
                                        </label>
                                        <input type="number" min="0" name="meetings_booked" class="form-control form-control-lg font-weight-bold" placeholder="0" value="0" required>
                                        <small class="form-text text-muted">Scheduled demos, meetings, or consultations.</small>
                                    </div>
                                </div>
                                <div class="col-md-6 col-sm-12">
                                    <div class="form-group">
                                        <label class="font-weight-bold text-secondary">
                                            <i class="fas fa-trophy text-success mr-1"></i> Sales Closed <span class="text-danger">*</span>
                                        </label>
                                        <input type="number" min="0" name="sales_closed" class="form-control form-control-lg font-weight-bold" placeholder="0" value="0" required>
                                        <small class="form-text text-muted">Final deals closed / conversions achieved.</small>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="font-weight-bold text-secondary">
                                    <i class="fas fa-sticky-note mr-1 text-muted"></i> Daily Shift Notes / Observations (Optional)
                                </label>
                                <textarea name="notes" class="form-control" rows="3" placeholder="Enter key remarks, campaign notes, or feedback..."></textarea>
                            </div>
                        </div>

                        <div class="card-footer bg-light d-flex justify-content-between align-items-center">
                            <span class="text-muted small">Make sure all numbers are accurate before submitting.</span>
                            <button type="submit" class="btn btn-primary btn-lg px-4 shadow-sm">
                                Submit Daily Report & Proceed to Next Step <i class="fas fa-arrow-right ml-2"></i>
                            </button>
                        </div>
                    </form>
                </div>

            <?php else: ?>
                <!-- ========================================================
                     SUBMITTED STATE: STATS DISPLAY & RECORDING UPLOAD FLOW
                     ======================================================== -->
                
                <!-- Today's Submitted KPI Widgets -->
                <div class="card card-outline card-success shadow-sm mb-4">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center py-2">
                        <h5 class="card-title font-weight-bold text-dark m-0">
                            <i class="fas fa-check-circle text-success mr-1"></i> Today's Report Submitted
                        </h5>
                        <button class="btn btn-sm btn-outline-secondary" type="button" data-toggle="collapse" data-target="#editReportCollapse" aria-expanded="false">
                            <i class="fas fa-edit mr-1"></i> Edit Statistics
                        </button>
                    </div>

                    <!-- Collapsible Edit Form -->
                    <div class="collapse" id="editReportCollapse">
                        <div class="card-body bg-light border-bottom">
                            <h6 class="font-weight-bold text-secondary mb-3">Update Today's Statistics</h6>
                            <form action="today_report.php" method="POST">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="save_daily_report">
                                <div class="row">
                                    <div class="col-6 col-md">
                                        <div class="form-group">
                                            <label class="small font-weight-bold">Total Calls</label>
                                            <input type="number" min="0" name="total_calls" class="form-control" value="<?= (int)$todayReport['total_calls'] ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-6 col-md">
                                        <div class="form-group">
                                            <label class="small font-weight-bold">Call Attempts</label>
                                            <input type="number" min="0" name="call_attempts" class="form-control" value="<?= (int)$todayReport['call_attempts'] ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-6 col-md">
                                        <div class="form-group">
                                            <label class="small font-weight-bold">Interested Leads</label>
                                            <input type="number" min="0" name="interested_leads" class="form-control" value="<?= (int)$todayReport['interested_leads'] ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-6 col-md">
                                        <div class="form-group">
                                            <label class="small font-weight-bold">Meetings</label>
                                            <input type="number" min="0" name="meetings_booked" class="form-control" value="<?= (int)$todayReport['meetings_booked'] ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-6 col-md">
                                        <div class="form-group">
                                            <label class="small font-weight-bold">Sales Closed</label>
                                            <input type="number" min="0" name="sales_closed" class="form-control" value="<?= (int)$todayReport['sales_closed'] ?>" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="small font-weight-bold">Notes</label>
                                    <textarea name="notes" class="form-control" rows="2"><?= e($todayReport['notes']) ?></textarea>
                                </div>
                                <button type="submit" class="btn btn-sm btn-success">
                                    <i class="fas fa-save mr-1"></i> Save Changes
                                </button>
                            </form>
                        </div>
                    </div>

                    <div class="card-body">
                        <div class="row">
                            <div class="col-lg-2 col-6">
                                <div class="small-box bg-info">
                                    <div class="inner">
                                        <h3><?= number_format($todayReport['total_calls']) ?></h3>
                                        <p>Total Calls</p>
                                    </div>
                                    <div class="icon"><i class="fas fa-phone-alt"></i></div>
                                </div>
                            </div>
                            <div class="col-lg-2 col-6">
                                <div class="small-box bg-secondary">
                                    <div class="inner">
                                        <h3><?= number_format($todayReport['call_attempts']) ?></h3>
                                        <p>Call Attempts</p>
                                    </div>
                                    <div class="icon"><i class="fas fa-redo-alt"></i></div>
                                </div>
                            </div>
                            <div class="col-lg-3 col-6">
                                <div class="small-box bg-primary">
                                    <div class="inner">
                                        <h3><?= number_format($todayReport['interested_leads']) ?></h3>
                                        <p>Interested Leads</p>
                                    </div>
                                    <div class="icon"><i class="fas fa-thumbs-up"></i></div>
                                </div>
                            </div>
                            <div class="col-lg-2 col-6">
                                <div class="small-box bg-warning">
                                    <div class="inner">
                                        <h3><?= number_format($todayReport['meetings_booked']) ?></h3>
                                        <p>Meetings Booked</p>
                                    </div>
                                    <div class="icon"><i class="fas fa-calendar-check"></i></div>
                                </div>
                            </div>
                            <div class="col-lg-3 col-12">
                                <div class="small-box bg-success">
                                    <div class="inner">
                                        <h3><?= number_format($todayReport['sales_closed']) ?></h3>
                                        <p>Sales Closed</p>
                                    </div>
                                    <div class="icon"><i class="fas fa-trophy"></i></div>
                                </div>
                            </div>
                        </div>

                        <?php if (!empty($todayReport['notes'])): ?>
                            <div class="bg-light p-3 rounded border text-muted small">
                                <i class="fas fa-quote-left mr-2 text-primary"></i> <?= nl2br(e($todayReport['notes'])) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Call Recordings Section (The "Next" workflow requirement) -->
                <div class="card card-outline card-primary shadow" id="recordingsSection">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap">
                        <div>
                            <h4 class="card-title font-weight-bold text-dark m-0">
                                <i class="fas fa-microphone-alt text-primary mr-2"></i> Today's Call Recordings
                            </h4>
                            <span class="badge badge-primary ml-2"><?= count($todayRecordings) ?> Uploaded</span>
                        </div>
                        <button type="button" class="btn btn-primary btn-sm shadow-sm mt-2 mt-sm-0" data-toggle="modal" data-target="#uploadRecordingModal">
                            <i class="fas fa-cloud-upload-alt mr-1"></i> Upload Call Recording
                        </button>
                    </div>

                    <div class="card-body p-0">
                        <?php if (empty($todayRecordings)): ?>
                            <div class="text-center py-5 px-3">
                                <div class="text-muted mb-3">
                                    <i class="fas fa-file-audio fa-4x text-light" style="color: #cbd5e1 !important;"></i>
                                </div>
                                <h5 class="text-dark font-weight-bold">No call recordings uploaded yet for today</h5>
                                <p class="text-muted small mx-auto" style="max-width: 480px;">
                                    Upload audio recordings for individual calls along with the lead's phone number, status, and remarks.
                                </p>
                                <button type="button" class="btn btn-primary px-4 shadow-sm" data-toggle="modal" data-target="#uploadRecordingModal">
                                    <i class="fas fa-plus-circle mr-1"></i> Upload First Call Recording
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover table-striped align-middle mb-0">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Phone Number</th>
                                            <th>Lead / Customer</th>
                                            <th>Call Status</th>
                                            <th>Duration</th>
                                            <th style="min-width: 280px;">Audio Recording & Player</th>
                                            <th>Notes</th>
                                            <th class="text-right">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($todayRecordings as $idx => $rec): ?>
                                            <tr>
                                                <td class="font-weight-bold text-muted"><?= $idx + 1 ?></td>
                                                <td>
                                                    <span class="font-weight-bold text-dark">
                                                        <i class="fas fa-phone-alt mr-1 text-muted small"></i> <?= format_phone($rec['phone_number']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?= !empty($rec['customer_name']) ? e($rec['customer_name']) : '<span class="text-muted small">N/A</span>' ?>
                                                </td>
                                                <td>
                                                    <?= get_call_status_badge($rec['call_status']) ?>
                                                </td>
                                                <td>
                                                    <span class="badge badge-light border font-weight-normal">
                                                        <i class="far fa-clock mr-1 text-muted"></i> <?= !empty($rec['call_duration']) ? e($rec['call_duration']) : '--:--' ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="audio-player-container">
                                                        <audio id="audio_today_<?= $rec['id'] ?>" controls preload="none">
                                                            <source src="<?= $baseUrl . '/' . e($rec['file_path']) ?>" type="<?= e($rec['file_mime']) ?>">
                                                            Your browser does not support HTML5 audio.
                                                        </audio>
                                                        <div class="btn-group btn-group-sm" role="group">
                                                            <button type="button" class="btn btn-outline-secondary btn-xs speed-btn" data-target="audio_today_<?= $rec['id'] ?>" data-speed="1.0" title="1x Speed">1x</button>
                                                            <button type="button" class="btn btn-outline-secondary btn-xs speed-btn" data-target="audio_today_<?= $rec['id'] ?>" data-speed="1.5" title="1.5x Speed">1.5x</button>
                                                            <button type="button" class="btn btn-outline-secondary btn-xs speed-btn" data-target="audio_today_<?= $rec['id'] ?>" data-speed="2.0" title="2x Speed">2x</button>
                                                        </div>
                                                    </div>
                                                    <small class="text-muted d-block mt-1">
                                                        <i class="far fa-file-audio mr-1"></i> <?= e($rec['original_name']) ?> (<?= format_file_size((int)$rec['file_size']) ?>)
                                                    </small>
                                                </td>
                                                <td>
                                                    <span class="small text-secondary" style="max-width: 200px; display: inline-block;">
                                                        <?= !empty($rec['call_notes']) ? e($rec['call_notes']) : '<span class="text-muted">-</span>' ?>
                                                    </span>
                                                </td>
                                                <td class="text-right">
                                                    <div class="btn-group">
                                                        <a href="<?= $baseUrl . '/' . e($rec['file_path']) ?>" download class="btn btn-sm btn-outline-secondary" title="Download Audio File">
                                                            <i class="fas fa-download"></i>
                                                        </a>
                                                        <form action="today_report.php" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this recording?');">
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="action" value="delete_recording">
                                                            <input type="hidden" name="recording_id" value="<?= $rec['id'] ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Recording">
                                                                <i class="fas fa-trash-alt"></i>
                                                            </button>
                                                        </form>
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

            <?php endif; ?>

        </div>
    </section>
</div>

<!-- ========================================================
     MODAL: UPLOAD CALL RECORDING
     ======================================================== -->
<?php if ($todayReport): ?>
<div class="modal fade" id="uploadRecordingModal" tabindex="-1" role="dialog" aria-labelledby="uploadModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content shadow border-0">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title font-weight-bold" id="uploadModalLabel">
                    <i class="fas fa-file-upload mr-2"></i> Upload Call Recording
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="recordingUploadForm" action="today_report.php" method="POST" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="upload_recording">

                <div class="modal-body">
                    <div class="form-group">
                        <label class="font-weight-bold text-secondary">
                            Customer Phone Number <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-phone"></i></span>
                            </div>
                            <input type="text" name="phone_number" class="form-control" placeholder="e.g. +91 9876543210" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold text-secondary">Lead / Customer Name</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                            </div>
                            <input type="text" name="customer_name" class="form-control" placeholder="e.g. Amit Verma (Optional)">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-7">
                            <div class="form-group">
                                <label class="font-weight-bold text-secondary">
                                    Call Status / Outcome <span class="text-danger">*</span>
                                </label>
                                <select name="call_status" class="form-control" required>
                                    <?php foreach ($CALL_STATUS_OPTIONS as $statusKey => $st): ?>
                                        <option value="<?= e($statusKey) ?>"><?= e($st['label']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="form-group">
                                <label class="font-weight-bold text-secondary">Call Duration</label>
                                <input type="text" name="call_duration" class="form-control" placeholder="e.g. 03:45" title="Duration in mm:ss">
                                <small class="text-muted">Auto-detected upon file selection</small>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold text-secondary">
                            Audio Recording File <span class="text-danger">*</span>
                        </label>
                        <div class="custom-file">
                            <input type="file" name="audio_file" class="custom-file-input" id="audioFileInput" accept=".mp3,.wav,.m4a,.ogg,.aac,.amr,.webm,audio/*" data-max-size="<?= MAX_UPLOAD_SIZE_MB ?>" required>
                            <label class="custom-file-label" for="audioFileInput">Choose audio file...</label>
                        </div>
                        <small class="form-text text-muted mt-1">
                            Allowed formats: <code><?= implode(', ', $ALLOWED_AUDIO_EXTENSIONS) ?></code> (Max: <?= MAX_UPLOAD_SIZE_MB ?> MB)
                        </small>
                    </div>

                    <div class="form-group mb-0">
                        <label class="font-weight-bold text-secondary">Call Notes / Discussion Summary</label>
                        <textarea name="call_notes" class="form-control" rows="2" placeholder="Customer requirements, next action, follow-up date..."></textarea>
                    </div>
                </div>

                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4 shadow-sm">
                        <i class="fas fa-upload mr-1"></i> Upload Recording
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
$extraScripts = "
<script>
    // Custom file input label update
    $('.custom-file-input').on('change', function() {
        let fileName = $(this).val().split('\\\\').pop();
        $(this).next('.custom-file-label').addClass('selected').html(fileName || 'Choose audio file...');
    });
</script>
";
require_once __DIR__ . '/../includes/footer.php';
?>
