<?php
/**
 * Employee Portal - Calling Workspace Dashboard
 * Telecaller lead management, calling dialer, and real-time performance cards
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_employee();

$db = get_db();
$currentUser = current_user();
$userId = (int)$currentUser['id'];
$todayDate = date('Y-m-d');

// -------------------------------------------------------------
// 1. Compute Today's Real-Time Workspace Summary Cards
// -------------------------------------------------------------
// Total Call Attempts Today
$stmtAtt = $db->prepare("
    SELECT COUNT(*) FROM lead_call_logs 
    WHERE user_id = ? AND " . (Database::getActiveDriver() === 'sqlite' ? "date(created_at) = ?" : "DATE(created_at) = ?")
);
$stmtAtt->execute([$userId, $todayDate]);
$todayAttemptsCount = (int)$stmtAtt->fetchColumn();

// Connected Calls Today (Interested, Follow-up Required, Meeting Scheduled, Sales Closed, Not Interested)
$connectedOutcomes = ['Interested', 'Follow-up Required', 'Meeting Scheduled', 'Sales Closed', 'Not Interested'];
$inClause = "'" . implode("','", $connectedOutcomes) . "'";
$stmtConn = $db->prepare("
    SELECT COUNT(*) FROM lead_call_logs 
    WHERE user_id = ? AND call_outcome IN ($inClause) AND " . (Database::getActiveDriver() === 'sqlite' ? "date(created_at) = ?" : "DATE(created_at) = ?")
);
$stmtConn->execute([$userId, $todayDate]);
$todayConnectedCount = (int)$stmtConn->fetchColumn();

// Follow-ups Scheduled Today
$stmtFu = $db->prepare("
    SELECT COUNT(*) FROM lead_call_logs 
    WHERE user_id = ? AND call_outcome = 'Follow-up Required' AND " . (Database::getActiveDriver() === 'sqlite' ? "date(created_at) = ?" : "DATE(created_at) = ?")
);
$stmtFu->execute([$userId, $todayDate]);
$todayFollowupsCount = (int)$stmtFu->fetchColumn();

// Meetings Scheduled Today
$stmtMeet = $db->prepare("
    SELECT COUNT(*) FROM lead_call_logs 
    WHERE user_id = ? AND call_outcome = 'Meeting Scheduled' AND " . (Database::getActiveDriver() === 'sqlite' ? "date(created_at) = ?" : "DATE(created_at) = ?")
);
$stmtMeet->execute([$userId, $todayDate]);
$todayMeetingsCount = (int)$stmtMeet->fetchColumn();

// Sales Closed Today
$stmtSales = $db->prepare("
    SELECT COUNT(*) FROM lead_call_logs 
    WHERE user_id = ? AND call_outcome = 'Sales Closed' AND " . (Database::getActiveDriver() === 'sqlite' ? "date(created_at) = ?" : "DATE(created_at) = ?")
);
$stmtSales->execute([$userId, $todayDate]);
$todaySalesCount = (int)$stmtSales->fetchColumn();

// -------------------------------------------------------------
// 2. Query Assigned Leads with Search, Filters & Pagination
// -------------------------------------------------------------
$search       = clean_string($_GET['search'] ?? '');
$statusFilter = clean_string($_GET['status'] ?? '');
$dueFilter    = clean_string($_GET['due'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 15;
$offset       = ($page - 1) * $perPage;

$where = ["assigned_to = ?"];
$params = [$userId];

if (!empty($search)) {
    $where[] = "(name LIKE ? OR phone LIKE ? OR company LIKE ? OR city LIKE ?)";
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if (!empty($statusFilter)) {
    $where[] = "status = ?";
    $params[] = $statusFilter;
}

if ($dueFilter === 'today') {
    if (Database::getActiveDriver() === 'sqlite') {
        $where[] = "date(follow_up_date) = date('now')";
    } else {
        $where[] = "DATE(follow_up_date) = CURDATE()";
    }
} elseif ($dueFilter === 'overdue') {
    if (Database::getActiveDriver() === 'sqlite') {
        $where[] = "follow_up_date < datetime('now')";
    } else {
        $where[] = "follow_up_date < NOW()";
    }
} elseif ($dueFilter === 'new') {
    $where[] = "status = 'New'";
}

$whereSql = implode(" AND ", $where);

// Count Total Leads
$countStmt = $db->prepare("SELECT COUNT(*) FROM leads WHERE $whereSql");
$countStmt->execute($params);
$totalLeads = (int)$countStmt->fetchColumn();
$totalPages = ceil($totalLeads / $perPage);

// Fetch Paginated Leads
$leadsQuery = "
    SELECT * FROM leads 
    WHERE $whereSql 
    ORDER BY 
        CASE 
            WHEN status = 'Follow-up Required' AND follow_up_date IS NOT NULL THEN 0
            WHEN status = 'New' THEN 1
            ELSE 2
        END ASC,
        follow_up_date ASC,
        id DESC
    LIMIT $perPage OFFSET $offset
";
$leadsStmt = $db->prepare($leadsQuery);
$leadsStmt->execute($params);
$leads = $leadsStmt->fetchAll();

$pageTitle = "Calling Workspace";
$activeNav = 'emp_workspace';
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
                        <i class="fas fa-laptop-house text-primary mr-2"></i> Calling Workspace
                    </h1>
                    <span class="text-muted small">Real-time calling queue, lead dialer, and outcome tracking</span>
                </div>
                <div class="col-sm-6 text-sm-right mt-2 mt-sm-0">
                    <span class="badge badge-light border px-3 py-2 text-secondary shadow-sm">
                        <i class="fas fa-calendar-day text-primary mr-1"></i> Today: <strong><?= date('d M, Y') ?></strong>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">

            <?php if ($flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show" role="alert">
                    <?= e($flash['message']) ?>
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                </div>
            <?php endif; ?>

            <!-- ========================================================
                 TODAY'S 5 WORKSPACE SUMMARY CARDS
                 1. Today's Call Attempts
                 2. Today's Connected Calls
                 3. Today's Follow-ups Scheduled
                 4. Today's Meetings Scheduled
                 5. Today's Sales Closed
                 ======================================================== -->
            <div class="row">
                <!-- Today's Call Attempts -->
                <div class="col-xl col-lg-4 col-sm-6 col-12">
                    <div class="small-box bg-secondary shadow-sm">
                        <div class="inner">
                            <h3><?= number_format($todayAttemptsCount) ?></h3>
                            <p class="font-weight-bold">Today's Call Attempts</p>
                        </div>
                        <div class="icon"><i class="fas fa-redo-alt"></i></div>
                    </div>
                </div>

                <!-- Today's Connected Calls -->
                <div class="col-xl col-lg-4 col-sm-6 col-12">
                    <div class="small-box bg-info shadow-sm">
                        <div class="inner">
                            <h3><?= number_format($todayConnectedCount) ?></h3>
                            <p class="font-weight-bold">Connected Calls</p>
                        </div>
                        <div class="icon"><i class="fas fa-phone-volume"></i></div>
                    </div>
                </div>

                <!-- Today's Follow-ups Scheduled -->
                <div class="col-xl col-lg-4 col-sm-6 col-12">
                    <div class="small-box bg-primary shadow-sm">
                        <div class="inner">
                            <h3><?= number_format($todayFollowupsCount) ?></h3>
                            <p class="font-weight-bold">Follow-ups Scheduled</p>
                        </div>
                        <div class="icon"><i class="fas fa-clock"></i></div>
                    </div>
                </div>

                <!-- Today's Meetings Scheduled -->
                <div class="col-xl col-lg-6 col-sm-6 col-12">
                    <div class="small-box bg-warning shadow-sm">
                        <div class="inner">
                            <h3><?= number_format($todayMeetingsCount) ?></h3>
                            <p class="font-weight-bold">Meetings Scheduled</p>
                        </div>
                        <div class="icon"><i class="fas fa-calendar-check"></i></div>
                    </div>
                </div>

                <!-- Today's Sales Closed -->
                <div class="col-xl col-lg-6 col-sm-12 col-12">
                    <div class="small-box bg-success shadow-sm">
                        <div class="inner">
                            <h3><?= number_format($todaySalesCount) ?></h3>
                            <p class="font-weight-bold">Sales Closed</p>
                        </div>
                        <div class="icon"><i class="fas fa-trophy"></i></div>
                    </div>
                </div>
            </div>

            <!-- ========================================================
                 SEARCH, FILTER & LEADS TABLE
                 ======================================================== -->
            <div class="card card-outline card-primary shadow">
                <!-- Filter Bar Header -->
                <div class="card-header bg-white py-3">
                    <form action="workspace.php" method="GET" class="row align-items-center">
                        <div class="col-lg-4 col-md-5 mb-2 mb-md-0">
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text bg-light"><i class="fas fa-search"></i></span>
                                </div>
                                <input type="text" name="search" class="form-control" placeholder="Search by name, phone, company..." value="<?= e($search) ?>">
                            </div>
                        </div>

                        <div class="col-lg-3 col-md-4 col-6 mb-2 mb-md-0">
                            <select name="status" class="form-control">
                                <option value="">-- All Statuses --</option>
                                <?php foreach ($CALL_STATUS_OPTIONS as $k => $opt): ?>
                                    <option value="<?= e($k) ?>" <?= $statusFilter === $k ? 'selected' : '' ?>><?= e($opt['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-lg-3 col-md-3 col-6 mb-2 mb-md-0">
                            <select name="due" class="form-control">
                                <option value="">-- All Follow-ups --</option>
                                <option value="today" <?= $dueFilter === 'today' ? 'selected' : '' ?>>Due Today</option>
                                <option value="overdue" <?= $dueFilter === 'overdue' ? 'selected' : '' ?>>Overdue Follow-ups</option>
                                <option value="new" <?= $dueFilter === 'new' ? 'selected' : '' ?>>New / Untouched</option>
                            </select>
                        </div>

                        <div class="col-lg-2 col-md-12 text-md-right mt-2 mt-lg-0 d-flex gap-2">
                            <button type="submit" class="btn btn-primary flex-fill mr-1 shadow-sm">
                                <i class="fas fa-filter mr-1"></i> Filter
                            </button>
                            <a href="workspace.php" class="btn btn-outline-secondary">Reset</a>
                        </div>
                    </form>
                </div>

                <!-- Table Body -->
                <div class="card-body p-0">
                    <?php if (empty($leads)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-folder-open fa-3x text-muted mb-3"></i>
                            <h5 class="text-secondary font-weight-bold">No assigned leads matching criteria</h5>
                            <p class="text-muted small">Ask your administrator to assign fresh leads or adjust your filters.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-striped align-middle mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Lead Name</th>
                                        <th>Company</th>
                                        <th>Phone Number</th>
                                        <th>Call Status</th>
                                        <th class="text-center">Attempts</th>
                                        <th>Follow-up Due</th>
                                        <th>Last Called</th>
                                        <th class="text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($leads as $l): ?>
                                        <tr>
                                            <td class="font-weight-bold text-dark">
                                                <div class="d-flex align-items-center">
                                                    <span class="rounded-circle bg-light border text-primary d-inline-flex justify-content-center align-items-center mr-2" style="width: 32px; height: 32px; font-size: 13px;">
                                                        <i class="fas fa-user"></i>
                                                    </span>
                                                    <span><?= e($l['name']) ?></span>
                                                </div>
                                            </td>
                                            <td>
                                                <?= !empty($l['company']) ? e($l['company']) : '<span class="text-muted small">N/A</span>' ?>
                                                <?php if (!empty($l['city'])): ?>
                                                    <small class="text-muted d-block"><i class="fas fa-map-marker-alt mr-1"></i><?= e($l['city']) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="tel:<?= e($l['phone']) ?>" class="btn btn-sm btn-outline-success font-weight-bold" title="Click to Call">
                                                    <i class="fas fa-phone-alt mr-1"></i> <?= format_phone($l['phone']) ?>
                                                </a>
                                            </td>
                                            <td>
                                                <?= get_call_status_badge($l['status']) ?>
                                            </td>
                                            <td class="text-center font-weight-bold">
                                                <span class="badge <?= $l['call_attempts'] > 0 ? 'badge-secondary' : 'badge-light border' ?> px-2 py-1">
                                                    <?= $l['call_attempts'] ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?= format_follow_up_badge($l['follow_up_date']) ?>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?= $l['last_called_at'] ? format_datetime($l['last_called_at'], 'd M, h:i A') : '<span class="text-muted font-italic">Never</span>' ?>
                                                </small>
                                            </td>
                                            <td class="text-right">
                                                <button type="button" class="btn btn-sm btn-primary shadow-sm" onclick="openLeadModal(<?= $l['id'] ?>)">
                                                    <i class="fas fa-phone-volume mr-1"></i> Details / Call
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Pagination Footer -->
                <?php if ($totalPages > 1): ?>
                    <div class="card-footer bg-light d-flex justify-content-between align-items-center flex-wrap">
                        <span class="text-muted small">
                            Showing page <?= $page ?> of <?= $totalPages ?> (Total: <?= $totalLeads ?> leads)
                        </span>
                        <ul class="pagination pagination-sm m-0">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="workspace.php?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">&laquo; Prev</a>
                                </li>
                            <?php endif; ?>
                            <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
                                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="workspace.php?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>"><?= $p ?></a>
                                </li>
                            <?php endfor; ?>
                            <?php if ($page < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="workspace.php?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">Next &raquo;</a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </section>
</div>

<!-- Include Lead Details & Call Logging Modal -->
<?php require_once __DIR__ . '/../includes/lead_modal.php'; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
