<?php
/**
 * Admin Panel - Lead Management, Excel/CSV Import & Telecaller Assignment
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$db = get_db();
$currentUser = current_user();
$adminId = (int)$currentUser['id'];

// Fetch all active telecallers for assignment dropdowns
$activeEmployees = $db->query("
    SELECT id, employee_id, name 
    FROM users 
    WHERE role = 'employee' AND status = 'active' 
    ORDER BY name ASC
")->fetchAll();

// -------------------------------------------------------------
// POST Handler: Excel / CSV Leads Import
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'import_leads') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Security validation failed.');
        header('Location: ' . get_base_url() . '/admin/leads.php');
        exit;
    }

    if (!isset($_FILES['leads_file']) || $_FILES['leads_file']['error'] !== UPLOAD_ERR_OK) {
        set_flash('danger', 'Please select a valid CSV file to upload.');
        header('Location: ' . get_base_url() . '/admin/leads.php');
        exit;
    }

    $file = $_FILES['leads_file'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['csv', 'txt'])) {
        set_flash('danger', 'Unsupported file format (.' . htmlspecialchars($ext) . '). Please upload a CSV file.');
        header('Location: ' . get_base_url() . '/admin/leads.php');
        exit;
    }

    $assignMode = clean_string($_POST['assign_mode'] ?? '0'); // '0' = Unassigned, 'round_robin' = Distribute, or employee ID
    $dupMode    = clean_string($_POST['dup_mode'] ?? 'skip'); // 'skip' or 'update'

    $parseResult = parse_leads_file($file['tmp_name']);
    if (!$parseResult['success']) {
        set_flash('danger', 'Import Error: ' . $parseResult['error']);
        header('Location: ' . get_base_url() . '/admin/leads.php');
        exit;
    }

    $leads = $parseResult['leads'];
    if (empty($leads)) {
        set_flash('warning', 'No valid lead records found in the uploaded file.');
        header('Location: ' . get_base_url() . '/admin/leads.php');
        exit;
    }

    // Determine round-robin telecaller IDs if requested
    $employeeIds = array_column($activeEmployees, 'id');
    $numEmps = count($employeeIds);
    $rrIndex = 0;

    $importedCount = 0;
    $skippedCount = 0;
    $updatedCount = 0;

    try {
        $db->beginTransaction();

        $checkPhone = $db->prepare("SELECT id, assigned_to FROM leads WHERE phone = ? LIMIT 1");
        $insertLead = $db->prepare("
            INSERT INTO leads (name, phone, company, email, city, notes, assigned_to, status, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'New', ?)
        ");
        $now = date('Y-m-d H:i:s');
        $updateLead = $db->prepare("
            UPDATE leads 
            SET name = ?, company = ?, email = ?, city = ?, notes = ?, updated_at = ?
            WHERE id = ?
        ");

        foreach ($leads as $l) {
            $phone = $l['phone'];

            // Check duplicate phone
            $checkPhone->execute([$phone]);
            $existing = $checkPhone->fetch();

            if ($existing) {
                if ($dupMode === 'update') {
                    $updateLead->execute([$l['name'], $l['company'], $l['email'], $l['city'], $l['notes'], $now, $existing['id']]);
                    $updatedCount++;
                } else {
                    $skippedCount++;
                }
                continue;
            }

            // Determine assignment
            $assignedTo = null;
            if ($assignMode === 'round_robin' && $numEmps > 0) {
                $assignedTo = $employeeIds[$rrIndex % $numEmps];
                $rrIndex++;
            } elseif ((int)$assignMode > 0) {
                $assignedTo = (int)$assignMode;
            }

            $insertLead->execute([
                $l['name'],
                $phone,
                $l['company'],
                $l['email'],
                $l['city'],
                $l['notes'],
                $assignedTo,
                $adminId
            ]);
            $importedCount++;
        }

        $db->commit();

        $msg = "Successfully processed file! <strong>{$importedCount} new leads imported</strong>";
        if ($updatedCount > 0) $msg .= ", {$updatedCount} updated";
        if ($skippedCount > 0) $msg .= ", {$skippedCount} duplicate phone numbers skipped";
        set_flash('success', $msg);
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        set_flash('danger', 'Import failed due to database error: ' . $e->getMessage());
    }

    header('Location: ' . get_base_url() . '/admin/leads.php');
    exit;
}

// -------------------------------------------------------------
// POST Handler: Add Single Lead
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_lead') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Security validation failed.');
        header('Location: ' . get_base_url() . '/admin/leads.php');
        exit;
    }

    $name       = clean_string($_POST['name'] ?? '');
    $phone      = sanitize_phone($_POST['phone'] ?? '');
    $company    = clean_string($_POST['company'] ?? '');
    $email      = clean_string($_POST['email'] ?? '');
    $city       = clean_string($_POST['city'] ?? '');
    $notes      = clean_string($_POST['notes'] ?? '');
    $assignedTo = (int)($_POST['assigned_to'] ?? 0) ?: null;

    if (empty($name) || empty($phone)) {
        set_flash('danger', 'Lead Name and Phone Number are required.');
        header('Location: ' . get_base_url() . '/admin/leads.php');
        exit;
    }

    // Duplicate check
    $check = $db->prepare("SELECT id FROM leads WHERE phone = ? LIMIT 1");
    $check->execute([$phone]);
    if ($check->fetch()) {
        set_flash('warning', "A lead with phone number '{$phone}' already exists.");
        header('Location: ' . get_base_url() . '/admin/leads.php');
        exit;
    }

    $ins = $db->prepare("
        INSERT INTO leads (name, phone, company, email, city, notes, assigned_to, status, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'New', ?)
    ");
    $ins->execute([$name, $phone, $company, $email, $city, $notes, $assignedTo, $adminId]);
    set_flash('success', "Lead '{$name}' created successfully!");
    header('Location: ' . get_base_url() . '/admin/leads.php');
    exit;
}

// -------------------------------------------------------------
// POST Handler: Bulk Reassign Leads
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'bulk_reassign') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Security validation failed.');
        header('Location: ' . get_base_url() . '/admin/leads.php');
        exit;
    }

    $leadIds = $_POST['selected_leads'] ?? [];
    $targetEmp = (int)($_POST['target_employee'] ?? 0);

    if (empty($leadIds) || !is_array($leadIds)) {
        set_flash('warning', 'Please select at least one lead from the table to reassign.');
        header('Location: ' . get_base_url() . '/admin/leads.php');
        exit;
    }

    $targetVal = ($targetEmp > 0) ? $targetEmp : null;
    $now = date('Y-m-d H:i:s');
    $placeholders = implode(',', array_fill(0, count($leadIds), '?'));
    $params = array_merge([$targetVal, $now], $leadIds);

    $upd = $db->prepare("UPDATE leads SET assigned_to = ?, updated_at = ? WHERE id IN ($placeholders)");
    $upd->execute($params);

    set_flash('success', count($leadIds) . ' leads updated successfully!');
    header('Location: ' . get_base_url() . '/admin/leads.php');
    exit;
}

// -------------------------------------------------------------
// POST Handler: Delete Lead
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_lead') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Security validation failed.');
        header('Location: ' . get_base_url() . '/admin/leads.php');
        exit;
    }

    $leadId = (int)($_POST['lead_id'] ?? 0);
    $del = $db->prepare("DELETE FROM leads WHERE id = ?");
    $del->execute([$leadId]);

    set_flash('success', 'Lead and associated call logs deleted.');
    header('Location: ' . get_base_url() . '/admin/leads.php');
    exit;
}

// -------------------------------------------------------------
// 2. Query Leads with Search, Filters & Pagination
// -------------------------------------------------------------
$search       = clean_string($_GET['search'] ?? '');
$statusFilter = clean_string($_GET['status'] ?? '');
$empFilter    = clean_string($_GET['employee_id'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 15;
$offset       = ($page - 1) * $perPage;

$where = ["1=1"];
$params = [];

if (!empty($search)) {
    $where[] = "(l.name LIKE ? OR l.phone LIKE ? OR l.company LIKE ? OR l.city LIKE ?)";
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if (!empty($statusFilter)) {
    $where[] = "l.status = ?";
    $params[] = $statusFilter;
}

if ($empFilter === 'unassigned') {
    $where[] = "l.assigned_to IS NULL";
} elseif ((int)$empFilter > 0) {
    $where[] = "l.assigned_to = ?";
    $params[] = (int)$empFilter;
}

$whereSql = implode(" AND ", $where);

// Summary Stats
$stats = [
    'total'       => (int)$db->query("SELECT COUNT(*) FROM leads")->fetchColumn(),
    'assigned'    => (int)$db->query("SELECT COUNT(*) FROM leads WHERE assigned_to IS NOT NULL")->fetchColumn(),
    'unassigned'  => (int)$db->query("SELECT COUNT(*) FROM leads WHERE assigned_to IS NULL")->fetchColumn(),
    'contacted'   => (int)$db->query("SELECT COUNT(*) FROM leads WHERE call_attempts > 0")->fetchColumn(),
    'follow_ups'  => (int)$db->query("SELECT COUNT(*) FROM leads WHERE status = 'Follow-up Required'")->fetchColumn(),
    'sales'       => (int)$db->query("SELECT COUNT(*) FROM leads WHERE status = 'Sales Closed'")->fetchColumn(),
];

// Total Count for filtered set
$countStmt = $db->prepare("SELECT COUNT(*) FROM leads l WHERE $whereSql");
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = ceil($totalRows / $perPage);

// Paginated Leads Query
$query = "
    SELECT l.*, u.name AS assigned_to_name, u.employee_id AS assigned_to_code
    FROM leads l
    LEFT JOIN users u ON u.id = l.assigned_to
    WHERE $whereSql
    ORDER BY l.id DESC
    LIMIT $perPage OFFSET $offset
";
$stmt = $db->prepare($query);
$stmt->execute($params);
$leads = $stmt->fetchAll();

$pageTitle = "Leads Management & Import";
$activeNav = 'admin_leads';
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
                        <i class="fas fa-user-friends text-primary mr-2"></i> Lead Management & Import
                    </h1>
                    <span class="text-muted small">Import Excel/CSV leads, assign telecallers, and track calls</span>
                </div>
                <div class="col-sm-6 text-sm-right mt-2 mt-sm-0">
                    <div class="btn-group shadow-sm">
                        <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#modalImportLeads">
                            <i class="fas fa-file-excel mr-1"></i> Import Leads (CSV/Excel)
                        </button>
                        <button type="button" class="btn btn-outline-primary" data-toggle="modal" data-target="#modalAddLead">
                            <i class="fas fa-plus mr-1"></i> Add Single Lead
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <section class="content">
        <div class="container-fluid">

            <?php if ($flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show shadow-sm" role="alert">
                    <?= $flash['message'] ?>
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                </div>
            <?php endif; ?>

            <!-- Metric Summary Cards -->
            <div class="row">
                <div class="col-xl-2 col-lg-4 col-sm-6 col-6">
                    <div class="small-box bg-info shadow-sm">
                        <div class="inner">
                            <h3><?= number_format($stats['total']) ?></h3>
                            <p>Total Leads</p>
                        </div>
                        <div class="icon"><i class="fas fa-database"></i></div>
                    </div>
                </div>
                <div class="col-xl-2 col-lg-4 col-sm-6 col-6">
                    <div class="small-box bg-teal text-white shadow-sm">
                        <div class="inner">
                            <h3><?= number_format($stats['assigned']) ?></h3>
                            <p>Assigned Leads</p>
                        </div>
                        <div class="icon"><i class="fas fa-user-check"></i></div>
                    </div>
                </div>
                <div class="col-xl-2 col-lg-4 col-sm-6 col-6">
                    <div class="small-box bg-secondary shadow-sm">
                        <div class="inner">
                            <h3><?= number_format($stats['unassigned']) ?></h3>
                            <p>Unassigned</p>
                        </div>
                        <div class="icon"><i class="fas fa-user-clock"></i></div>
                    </div>
                </div>
                <div class="col-xl-2 col-lg-4 col-sm-6 col-6">
                    <div class="small-box bg-primary shadow-sm">
                        <div class="inner">
                            <h3><?= number_format($stats['contacted']) ?></h3>
                            <p>Contacted</p>
                        </div>
                        <div class="icon"><i class="fas fa-phone-alt"></i></div>
                    </div>
                </div>
                <div class="col-xl-2 col-lg-4 col-sm-6 col-6">
                    <div class="small-box bg-warning shadow-sm">
                        <div class="inner">
                            <h3><?= number_format($stats['follow_ups']) ?></h3>
                            <p>Follow-ups</p>
                        </div>
                        <div class="icon"><i class="fas fa-clock"></i></div>
                    </div>
                </div>
                <div class="col-xl-2 col-lg-4 col-sm-6 col-6">
                    <div class="small-box bg-success shadow-sm">
                        <div class="inner">
                            <h3><?= number_format($stats['sales']) ?></h3>
                            <p>Sales Closed</p>
                        </div>
                        <div class="icon"><i class="fas fa-trophy"></i></div>
                    </div>
                </div>
            </div>

            <!-- Leads Management Table & Filter Card -->
            <div class="card card-outline card-primary shadow">
                <!-- Filter Controls -->
                <div class="card-header bg-white py-3">
                    <form action="leads.php" method="GET" class="row align-items-center">
                        <div class="col-lg-4 col-md-5 mb-2 mb-md-0">
                            <div class="input-group">
                                <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-search"></i></span></div>
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
                            <select name="employee_id" class="form-control">
                                <option value="">-- All Telecallers --</option>
                                <option value="unassigned" <?= $empFilter === 'unassigned' ? 'selected' : '' ?>>Unassigned Only</option>
                                <?php foreach ($activeEmployees as $emp): ?>
                                    <option value="<?= $emp['id'] ?>" <?= $empFilter == $emp['id'] ? 'selected' : '' ?>>
                                        <?= e($emp['name']) ?> (<?= e($emp['employee_id']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-lg-2 col-md-12 text-md-right mt-2 mt-lg-0 d-flex gap-2">
                            <button type="submit" class="btn btn-primary flex-fill mr-1 shadow-sm">
                                <i class="fas fa-filter mr-1"></i> Filter
                            </button>
                            <a href="leads.php" class="btn btn-outline-secondary">Reset</a>
                        </div>
                    </form>
                </div>

                <!-- Table Form (supports bulk reassignment) -->
                <form id="bulkLeadsForm" action="leads.php" method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="bulk_reassign">

                    <div class="card-body p-0">
                        <!-- Bulk Actions Toolbar -->
                        <div class="p-2 bg-light border-bottom d-flex align-items-center justify-content-between flex-wrap">
                            <div class="d-flex align-items-center mb-1 mb-sm-0">
                                <div class="custom-control custom-checkbox mr-3 ml-2">
                                    <input type="checkbox" class="custom-control-input" id="checkAllLeads">
                                    <label class="custom-control-label font-weight-normal small" for="checkAllLeads">Select All</label>
                                </div>
                                <span class="text-muted small" id="selectedCountText">0 selected</span>
                            </div>

                            <div class="d-flex align-items-center">
                                <select name="target_employee" class="form-control form-control-sm mr-2" style="max-width: 220px;">
                                    <option value="0">-- Unassign Selected --</option>
                                    <?php foreach ($activeEmployees as $emp): ?>
                                        <option value="<?= $emp['id'] ?>">Assign to <?= e($emp['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn btn-sm btn-primary shadow-sm" onclick="return confirm('Reassign all selected leads?');">
                                    <i class="fas fa-user-check mr-1"></i> Apply Reassign
                                </button>
                            </div>
                        </div>

                        <?php if (empty($leads)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-users-slash fa-3x text-muted mb-3"></i>
                                <h5 class="text-secondary font-weight-bold">No leads found</h5>
                                <p class="text-muted small">Import leads from a CSV file or add single leads to get started.</p>
                                <button type="button" class="btn btn-primary shadow-sm" data-toggle="modal" data-target="#modalImportLeads">
                                    <i class="fas fa-file-excel mr-1"></i> Import Leads Now
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover table-striped align-middle mb-0">
                                    <thead class="thead-light">
                                        <tr>
                                            <th width="40"></th>
                                            <th>Lead Name</th>
                                            <th>Phone Number</th>
                                            <th>Company</th>
                                            <th>Assigned Telecaller</th>
                                            <th>Call Status</th>
                                            <th class="text-center">Attempts</th>
                                            <th>Next Follow-up</th>
                                            <th>Last Called</th>
                                            <th class="text-right">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($leads as $l): ?>
                                            <tr>
                                                <td class="text-center">
                                                    <div class="custom-control custom-checkbox">
                                                        <input type="checkbox" name="selected_leads[]" value="<?= $l['id'] ?>" class="custom-control-input lead-chk" id="chk_<?= $l['id'] ?>">
                                                        <label class="custom-control-label" for="chk_<?= $l['id'] ?>"></label>
                                                    </div>
                                                </td>
                                                <td class="font-weight-bold text-dark">
                                                    <i class="fas fa-user text-muted mr-1 small"></i> <?= e($l['name']) ?>
                                                    <?php if (!empty($l['email'])): ?>
                                                        <small class="text-muted d-block"><?= e($l['email']) ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="tel:<?= e($l['phone']) ?>" class="btn btn-xs btn-outline-success font-weight-bold" title="Click to call">
                                                        <i class="fas fa-phone-alt mr-1"></i> <?= format_phone($l['phone']) ?>
                                                    </a>
                                                </td>
                                                <td>
                                                    <?= !empty($l['company']) ? e($l['company']) : '<span class="text-muted small">N/A</span>' ?>
                                                    <?php if (!empty($l['city'])): ?>
                                                        <small class="text-muted d-block"><?= e($l['city']) ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($l['assigned_to_name'])): ?>
                                                        <span class="badge badge-light border text-dark font-weight-bold">
                                                            <i class="fas fa-user-circle text-primary mr-1"></i> <?= e($l['assigned_to_name']) ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge badge-secondary">Unassigned</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?= get_call_status_badge($l['status']) ?>
                                                </td>
                                                <td class="text-center font-weight-bold">
                                                    <span class="badge <?= $l['call_attempts'] > 0 ? 'badge-primary' : 'badge-light border' ?>">
                                                        <?= $l['call_attempts'] ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?= format_follow_up_badge($l['follow_up_date']) ?>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?= $l['last_called_at'] ? format_datetime($l['last_called_at'], 'd M, h:i A') : '<span class="font-italic">Never</span>' ?>
                                                    </small>
                                                </td>
                                                <td class="text-right">
                                                    <button type="button" class="btn btn-sm btn-primary shadow-sm" onclick="openLeadModal(<?= $l['id'] ?>)">
                                                        <i class="fas fa-eye mr-1"></i> Details
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </form>

                <!-- Pagination Footer -->
                <?php if ($totalPages > 1): ?>
                    <div class="card-footer bg-light d-flex justify-content-between align-items-center flex-wrap">
                        <span class="text-muted small">
                            Showing page <?= $page ?> of <?= $totalPages ?> (Total: <?= $totalRows ?> leads)
                        </span>
                        <ul class="pagination pagination-sm m-0">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="leads.php?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">&laquo; Prev</a>
                                </li>
                            <?php endif; ?>
                            <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
                                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="leads.php?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>"><?= $p ?></a>
                                </li>
                            <?php endfor; ?>
                            <?php if ($page < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="leads.php?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">Next &raquo;</a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </section>
</div>

<!-- ========================================================
     MODAL: IMPORT LEADS (EXCEL / CSV)
     ======================================================== -->
<div class="modal fade" id="modalImportLeads" tabindex="-1" role="dialog" aria-labelledby="modalImportLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content shadow border-0">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title font-weight-bold" id="modalImportLabel">
                    <i class="fas fa-file-excel mr-2"></i> Import Leads (Excel / CSV)
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form action="leads.php" method="POST" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="import_leads">

                <div class="modal-body">
                    <div class="alert alert-info small border-0 shadow-sm mb-3">
                        <i class="fas fa-info-circle mr-1"></i> Upload a CSV file with columns: <strong>Name, Phone, Company, Email, City, Notes</strong>.
                        <div class="mt-2">
                            <a href="<?= get_base_url() ?>/assets/template_leads.csv" download class="btn btn-xs btn-outline-primary bg-white">
                                <i class="fas fa-download mr-1"></i> Download Sample CSV Template
                            </a>
                        </div>
                    </div>

                    <div class="form-group mb-3">
                        <label class="font-weight-bold text-secondary small">
                            Choose CSV File <span class="text-danger">*</span>
                        </label>
                        <div class="custom-file">
                            <input type="file" name="leads_file" class="custom-file-input" id="importFileInput" accept=".csv,.txt" required>
                            <label class="custom-file-label" for="importFileInput">Select .csv file...</label>
                        </div>
                    </div>

                    <div class="form-group mb-3">
                        <label class="font-weight-bold text-secondary small">Assign Leads To</label>
                        <select name="assign_mode" class="form-control">
                            <option value="0">-- Leave Unassigned (Assign Later) --</option>
                            <option value="round_robin">⚡ Distribute Evenly (Round-Robin to Active Telecallers)</option>
                            <optgroup label="Assign to Specific Employee">
                                <?php foreach ($activeEmployees as $emp): ?>
                                    <option value="<?= $emp['id'] ?>"><?= e($emp['name']) ?> (<?= e($emp['employee_id']) ?>)</option>
                                <?php endforeach; ?>
                            </optgroup>
                        </select>
                    </div>

                    <div class="form-group mb-0">
                        <label class="font-weight-bold text-secondary small">Duplicate Phone Number Handling</label>
                        <select name="dup_mode" class="form-control">
                            <option value="skip" selected>Skip Duplicate Phone Numbers (Recommended)</option>
                            <option value="update">Update Existing Lead Information</option>
                        </select>
                    </div>
                </div>

                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4 shadow-sm font-weight-bold">
                        <i class="fas fa-cloud-upload-alt mr-1"></i> Validate & Import Leads
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================================
     MODAL: ADD SINGLE LEAD
     ======================================================== -->
<div class="modal fade" id="modalAddLead" tabindex="-1" role="dialog" aria-labelledby="modalAddLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content shadow border-0">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title font-weight-bold" id="modalAddLabel">
                    <i class="fas fa-user-plus mr-2 text-primary"></i> Add Single Lead
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form action="leads.php" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_lead">

                <div class="modal-body">
                    <div class="form-group mb-2">
                        <label class="font-weight-bold text-secondary small">Lead Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Ramesh Chandra" required>
                    </div>

                    <div class="form-group mb-2">
                        <label class="font-weight-bold text-secondary small">Phone Number <span class="text-danger">*</span></label>
                        <input type="text" name="phone" class="form-control" placeholder="e.g. +91 9876543210" required>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-2">
                            <label class="font-weight-bold text-secondary small">Company Name</label>
                            <input type="text" name="company" class="form-control" placeholder="Acme Ltd">
                        </div>
                        <div class="col-md-6 mb-2">
                            <label class="font-weight-bold text-secondary small">City / Location</label>
                            <input type="text" name="city" class="form-control" placeholder="Mumbai">
                        </div>
                    </div>

                    <div class="form-group mb-2">
                        <label class="font-weight-bold text-secondary small">Email Address</label>
                        <input type="email" name="email" class="form-control" placeholder="ramesh@example.com">
                    </div>

                    <div class="form-group mb-2">
                        <label class="font-weight-bold text-secondary small">Assign to Telecaller</label>
                        <select name="assigned_to" class="form-control">
                            <option value="">-- Unassigned --</option>
                            <?php foreach ($activeEmployees as $emp): ?>
                                <option value="<?= $emp['id'] ?>"><?= e($emp['name']) ?> (<?= e($emp['employee_id']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group mb-0">
                        <label class="font-weight-bold text-secondary small">Initial Notes</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Lead background or requirements..."></textarea>
                    </div>
                </div>

                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4 shadow-sm font-weight-bold">
                        <i class="fas fa-check mr-1"></i> Save Lead
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Include Lead Details & Call Logging Modal -->
<?php require_once __DIR__ . '/../includes/lead_modal.php'; ?>

<?php
$extraScripts = "
<script>
    // File input label update
    $('#importFileInput').on('change', function() {
        let fileName = $(this).val().split('\\\\').pop();
        $(this).next('.custom-file-label').addClass('selected').html(fileName || 'Select .csv file...');
    });

    // Check all checkbox
    $('#checkAllLeads').on('change', function() {
        const isChecked = $(this).is(':checked');
        $('.lead-chk').prop('checked', isChecked);
        updateSelectedCount();
    });

    $('.lead-chk').on('change', function() {
        updateSelectedCount();
    });

    function updateSelectedCount() {
        const count = $('.lead-chk:checked').length;
        $('#selectedCountText').text(count + ' selected');
    }
" . (isset($_GET['open_modal']) && $_GET['open_modal'] === 'import' ? "$('#modalImportLeads').modal('show');" : "") . "
</script>
";
require_once __DIR__ . '/../includes/footer.php';
?>
