<?php
/**
 * Admin Panel - Global Call Recordings Search, Filter & Quality Audit Player
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$db = get_db();
$currentUser = current_user();

// Fetch employees for filter dropdown
$employees = $db->query("SELECT id, employee_id, name FROM users WHERE role = 'employee' ORDER BY name ASC")->fetchAll();

// Filter parameters
$filterEmpId   = (int)($_GET['employee_id'] ?? 0);
$filterPhone   = clean_string($_GET['phone'] ?? '');
$filterStatus  = clean_string($_GET['status'] ?? '');
$filterKeyword = clean_string($_GET['keyword'] ?? '');
$startDate     = clean_string($_GET['start_date'] ?? '');
$endDate       = clean_string($_GET['end_date'] ?? '');

$where = ["1=1"];
$params = [];

if ($filterEmpId > 0) {
    $where[] = "c.user_id = ?";
    $params[] = $filterEmpId;
}
if (!empty($filterPhone)) {
    $where[] = "c.phone_number LIKE ?";
    $params[] = '%' . $filterPhone . '%';
}
if (!empty($filterStatus)) {
    $where[] = "c.call_status = ?";
    $params[] = $filterStatus;
}
if (!empty($filterKeyword)) {
    $where[] = "(c.customer_name LIKE ? OR c.call_notes LIKE ?)";
    $params[] = '%' . $filterKeyword . '%';
    $params[] = '%' . $filterKeyword . '%';
}
if (!empty($startDate)) {
    $where[] = "r.report_date >= ?";
    $params[] = $startDate;
}
if (!empty($endDate)) {
    $where[] = "r.report_date <= ?";
    $params[] = $endDate;
}

$whereSql = implode(" AND ", $where);

// Query filtered recordings
$recSql = "
    SELECT c.*, 
           u.employee_id AS emp_code, u.name AS employee_name,
           r.report_date
    FROM call_recordings c
    JOIN users u ON u.id = c.user_id
    JOIN daily_reports r ON r.id = c.report_id
    WHERE $whereSql
    ORDER BY c.id DESC
";
$stmt = $db->prepare($recSql);
$stmt->execute($params);
$recordings = $stmt->fetchAll();

$pageTitle = "Call Recordings Search";
$activeNav = 'admin_recordings';
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
                        <i class="fas fa-microphone-alt text-danger mr-2"></i> Call Recordings Search & Player
                    </h1>
                    <span class="text-muted small">Search and listen to individual customer conversations across all telecallers</span>
                </div>
                <div class="col-sm-6 text-sm-right mt-2 mt-sm-0">
                    <span class="badge badge-light border px-3 py-2 text-secondary shadow-sm">
                        <i class="fas fa-database text-danger mr-1"></i> <?= count($recordings) ?> Audio Files Matching
                    </span>
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

            <!-- Advanced Filter Card -->
            <div class="card card-outline card-danger shadow-sm mb-4">
                <div class="card-header bg-white py-2">
                    <h5 class="card-title font-weight-bold text-dark m-0">
                        <i class="fas fa-filter text-danger mr-1"></i> Search & Filter Recordings
                    </h5>
                </div>
                <div class="card-body">
                    <form action="recordings.php" method="GET">
                        <div class="row">
                            <!-- Telecaller filter -->
                            <div class="col-md-3 col-sm-6 mb-3">
                                <label class="font-weight-bold text-secondary small mb-1">Telecaller Employee</label>
                                <select name="employee_id" class="form-control form-control-sm">
                                    <option value="0">-- All Telecallers --</option>
                                    <?php foreach ($employees as $emp): ?>
                                        <option value="<?= $emp['id'] ?>" <?= $filterEmpId === (int)$emp['id'] ? 'selected' : '' ?>>
                                            <?= e($emp['name']) ?> (<?= e($emp['employee_id']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Phone Number filter -->
                            <div class="col-md-3 col-sm-6 mb-3">
                                <label class="font-weight-bold text-secondary small mb-1">Customer Phone Number</label>
                                <div class="input-group input-group-sm">
                                    <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-phone"></i></span></div>
                                    <input type="text" name="phone" class="form-control" placeholder="Search phone..." value="<?= e($filterPhone) ?>">
                                </div>
                            </div>

                            <!-- Call Status filter -->
                            <div class="col-md-3 col-sm-6 mb-3">
                                <label class="font-weight-bold text-secondary small mb-1">Call Status</label>
                                <select name="status" class="form-control form-control-sm">
                                    <option value="">-- All Statuses --</option>
                                    <?php foreach ($CALL_STATUS_OPTIONS as $k => $opt): ?>
                                        <option value="<?= e($k) ?>" <?= $filterStatus === $k ? 'selected' : '' ?>><?= e($opt['label']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Keyword filter -->
                            <div class="col-md-3 col-sm-6 mb-3">
                                <label class="font-weight-bold text-secondary small mb-1">Name / Notes Keyword</label>
                                <input type="text" name="keyword" class="form-control form-control-sm" placeholder="Search keywords..." value="<?= e($filterKeyword) ?>">
                            </div>

                            <!-- Date Range filters -->
                            <div class="col-md-3 col-sm-6 mb-3 mb-md-0">
                                <label class="font-weight-bold text-secondary small mb-1">From Date</label>
                                <input type="date" name="start_date" class="form-control form-control-sm" value="<?= e($startDate) ?>">
                            </div>
                            <div class="col-md-3 col-sm-6 mb-3 mb-md-0">
                                <label class="font-weight-bold text-secondary small mb-1">To Date</label>
                                <input type="date" name="end_date" class="form-control form-control-sm" value="<?= e($endDate) ?>">
                            </div>

                            <!-- Action Buttons -->
                            <div class="col-md-6 col-sm-12 d-flex align-items-end">
                                <button type="submit" class="btn btn-sm btn-danger px-4 mr-2 shadow-sm">
                                    <i class="fas fa-search mr-1"></i> Apply Filter
                                </button>
                                <a href="recordings.php" class="btn btn-sm btn-outline-secondary">
                                    <i class="fas fa-undo mr-1"></i> Reset Filters
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Recordings List Card -->
            <div class="card card-outline card-primary shadow">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h3 class="card-title font-weight-bold text-dark">
                        <i class="fas fa-headphones text-primary mr-1"></i> Call Audio Records
                    </h3>
                    <span class="badge badge-primary"><?= count($recordings) ?> Recordings Found</span>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($recordings)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-microphone-slash fa-3x text-muted mb-3"></i>
                            <h5 class="text-secondary font-weight-bold">No call recordings found</h5>
                            <p class="text-muted small">No audio files match your search criteria. Try modifying your filters.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-striped align-middle mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Telecaller</th>
                                        <th>Phone Number</th>
                                        <th>Customer / Lead</th>
                                        <th>Status</th>
                                        <th>Duration</th>
                                        <th style="min-width: 320px;">Play Recording</th>
                                        <th>Remarks / Notes</th>
                                        <th class="text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recordings as $rec): ?>
                                        <tr>
                                            <td class="font-weight-bold text-dark">
                                                <i class="far fa-calendar text-primary mr-1"></i> <?= format_date($rec['report_date']) ?>
                                            </td>
                                            <td>
                                                <div class="font-weight-bold text-dark"><?= e($rec['employee_name']) ?></div>
                                                <small class="badge badge-secondary"><?= e($rec['emp_code']) ?></small>
                                            </td>
                                            <td class="font-weight-bold text-dark">
                                                <i class="fas fa-phone-alt text-muted mr-1 small"></i> <?= format_phone($rec['phone_number']) ?>
                                            </td>
                                            <td>
                                                <?= !empty($rec['customer_name']) ? e($rec['customer_name']) : '<span class="text-muted small">N/A</span>' ?>
                                            </td>
                                            <td>
                                                <?= get_call_status_badge($rec['call_status']) ?>
                                            </td>
                                            <td>
                                                <span class="badge badge-light border">
                                                    <i class="far fa-clock mr-1 text-muted"></i> <?= !empty($rec['call_duration']) ? e($rec['call_duration']) : '--:--' ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="audio-player-container">
                                                    <audio id="audio_all_<?= $rec['id'] ?>" controls preload="none">
                                                        <source src="<?= $baseUrl . '/' . e($rec['file_path']) ?>" type="<?= e($rec['file_mime']) ?>">
                                                        Audio not supported.
                                                    </audio>
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <button type="button" class="btn btn-outline-secondary btn-xs speed-btn" data-target="audio_all_<?= $rec['id'] ?>" data-speed="1.0">1x</button>
                                                        <button type="button" class="btn btn-outline-secondary btn-xs speed-btn" data-target="audio_all_<?= $rec['id'] ?>" data-speed="1.5">1.5x</button>
                                                        <button type="button" class="btn btn-outline-secondary btn-xs speed-btn" data-target="audio_all_<?= $rec['id'] ?>" data-speed="2.0">2x</button>
                                                    </div>
                                                </div>
                                                <small class="text-muted d-block mt-1">
                                                    <?= e($rec['original_name']) ?> &bull; <?= format_file_size((int)$rec['file_size']) ?>
                                                </small>
                                            </td>
                                            <td>
                                                <small class="text-secondary d-inline-block" style="max-width: 220px;">
                                                    <?= !empty($rec['call_notes']) ? e($rec['call_notes']) : '-' ?>
                                                </small>
                                            </td>
                                            <td class="text-right">
                                                <div class="btn-group">
                                                    <a href="<?= $baseUrl . '/' . e($rec['file_path']) ?>" download class="btn btn-sm btn-outline-secondary" title="Download Audio File">
                                                        <i class="fas fa-download"></i>
                                                    </a>
                                                    <a href="report_view.php?id=<?= $rec['report_id'] ?>" class="btn btn-sm btn-outline-primary" title="View Full Daily Report">
                                                        <i class="fas fa-file-invoice"></i>
                                                    </a>
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
