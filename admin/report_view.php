<?php
/**
 * Admin Panel - Single Daily Report Drill-Down & Recordings
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$db = get_db();
$reportId = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("
    SELECT r.*, u.employee_id, u.name as employee_name, u.email as employee_email, u.phone as employee_phone
    FROM daily_reports r
    JOIN users u ON u.id = r.user_id
    WHERE r.id = ?
    LIMIT 1
");
$stmt->execute([$reportId]);
$report = $stmt->fetch();

if (!$report) {
    set_flash('danger', 'Report not found.');
    header('Location: ' . get_base_url() . '/admin/reports.php');
    exit;
}

// Fetch all call recordings for this report
$recStmt = $db->prepare("SELECT * FROM call_recordings WHERE report_id = ? ORDER BY id DESC");
$recStmt->execute([$reportId]);
$recordings = $recStmt->fetchAll();

$pageTitle = "Report Details - " . format_date($report['report_date']);
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
                        <i class="fas fa-file-invoice text-primary mr-2"></i> Report: <?= format_date($report['report_date']) ?>
                    </h1>
                    <span class="text-muted small">Submitted by <strong><?= e($report['employee_name']) ?></strong> (<?= e($report['employee_id']) ?>)</span>
                </div>
                <div class="col-sm-6 text-sm-right mt-2 mt-sm-0">
                    <a href="reports.php" class="btn btn-outline-secondary btn-sm shadow-sm">
                        <i class="fas fa-arrow-left mr-1"></i> Back to All Reports
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

            <!-- Performance Numbers Overview Card -->
            <div class="card card-outline card-primary shadow mb-4">
                <div class="card-header bg-white">
                    <h5 class="card-title font-weight-bold text-dark m-0">
                        <i class="fas fa-chart-pie text-primary mr-2"></i> Daily Performance Breakdown
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-lg-2 col-6">
                            <div class="small-box bg-info">
                                <div class="inner">
                                    <h3><?= number_format((int)$report['total_calls']) ?></h3>
                                    <p>Total Calls</p>
                                </div>
                                <div class="icon"><i class="fas fa-phone-alt"></i></div>
                            </div>
                        </div>
                        <div class="col-lg-2 col-6">
                            <div class="small-box bg-secondary">
                                <div class="inner">
                                    <h3><?= number_format((int)$report['call_attempts']) ?></h3>
                                    <p>Call Attempts</p>
                                </div>
                                <div class="icon"><i class="fas fa-redo-alt"></i></div>
                            </div>
                        </div>
                        <div class="col-lg-2 col-6">
                            <div class="small-box bg-primary">
                                <div class="inner">
                                    <h3><?= number_format((int)$report['interested_leads']) ?></h3>
                                    <p>Interested Leads</p>
                                </div>
                                <div class="icon"><i class="fas fa-thumbs-up"></i></div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-6">
                            <div class="small-box bg-warning">
                                <div class="inner">
                                    <h3><?= number_format((int)$report['meetings_booked']) ?></h3>
                                    <p>Meetings Booked</p>
                                </div>
                                <div class="icon"><i class="fas fa-calendar-check"></i></div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-12">
                            <div class="small-box bg-success">
                                <div class="inner">
                                    <h3><?= number_format((int)$report['sales_closed']) ?></h3>
                                    <p>Sales Closed</p>
                                </div>
                                <div class="icon"><i class="fas fa-trophy"></i></div>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($report['notes'])): ?>
                        <div class="bg-light p-3 rounded border text-muted small mt-2">
                            <strong class="text-dark d-block mb-1"><i class="fas fa-comment-dots text-primary mr-1"></i> Shift Observations:</strong>
                            <?= nl2br(e($report['notes'])) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Call Recordings & Audio Player List -->
            <div class="card card-outline card-danger shadow">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title font-weight-bold text-dark m-0">
                        <i class="fas fa-headphones text-danger mr-2"></i> Uploaded Call Recordings (<?= count($recordings) ?>)
                    </h5>
                    <span class="text-muted small">Listen and evaluate call quality</span>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($recordings)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-file-audio fa-3x text-muted mb-3"></i>
                            <h6 class="text-muted font-weight-bold">No call audio recordings attached to this report.</h6>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-striped align-middle mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Customer Phone</th>
                                        <th>Lead Name</th>
                                        <th>Call Status</th>
                                        <th>Duration</th>
                                        <th style="min-width: 320px;">Play Recording</th>
                                        <th>Notes</th>
                                        <th>File Info</th>
                                        <th class="text-right">Download</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recordings as $idx => $rec): ?>
                                        <tr>
                                            <td class="font-weight-bold text-muted"><?= $idx + 1 ?></td>
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
                                                    <audio id="audio_view_<?= $rec['id'] ?>" controls preload="none">
                                                        <source src="<?= $baseUrl . '/' . e($rec['file_path']) ?>" type="<?= e($rec['file_mime']) ?>">
                                                        Audio not supported.
                                                    </audio>
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <button type="button" class="btn btn-outline-secondary btn-xs speed-btn" data-target="audio_view_<?= $rec['id'] ?>" data-speed="1.0">1x</button>
                                                        <button type="button" class="btn btn-outline-secondary btn-xs speed-btn" data-target="audio_view_<?= $rec['id'] ?>" data-speed="1.5">1.5x</button>
                                                        <button type="button" class="btn btn-outline-secondary btn-xs speed-btn" data-target="audio_view_<?= $rec['id'] ?>" data-speed="2.0">2x</button>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <small class="text-secondary d-inline-block" style="max-width: 220px;">
                                                    <?= !empty($rec['call_notes']) ? e($rec['call_notes']) : '-' ?>
                                                </small>
                                            </td>
                                            <td>
                                                <small class="text-muted d-block"><?= format_file_size((int)$rec['file_size']) ?></small>
                                                <small class="text-muted"><?= format_datetime($rec['uploaded_at'], 'h:i A') ?></small>
                                            </td>
                                            <td class="text-right">
                                                <a href="<?= $baseUrl . '/' . e($rec['file_path']) ?>" download class="btn btn-sm btn-outline-secondary" title="Download Audio">
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
