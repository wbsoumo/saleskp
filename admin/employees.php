<?php
/**
 * Admin Panel - Employee Accounts & Credentials Management
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$db = get_db();
$currentUser = current_user();

// -------------------------------------------------------------
// POST Handler: Create Employee
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_employee') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Security validation failed.');
        header('Location: ' . get_base_url() . '/admin/employees.php');
        exit;
    }

    $empId    = strtoupper(clean_string($_POST['employee_id'] ?? ''));
    $name     = clean_string($_POST['name'] ?? '');
    $email    = clean_string($_POST['email'] ?? '');
    $phone    = clean_string($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = in_array($_POST['role'] ?? '', ['admin', 'employee']) ? $_POST['role'] : 'employee';
    $status   = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';

    if (empty($empId) || empty($name) || empty($password)) {
        set_flash('danger', 'Employee ID, Full Name, and Password are required.');
        header('Location: ' . get_base_url() . '/admin/employees.php');
        exit;
    }

    // Check if employee_id already exists
    $checkStmt = $db->prepare("SELECT id FROM users WHERE UPPER(employee_id) = ? LIMIT 1");
    $checkStmt->execute([$empId]);
    if ($checkStmt->fetch()) {
        set_flash('danger', 'Employee ID "' . htmlspecialchars($empId) . '" is already registered.');
        header('Location: ' . get_base_url() . '/admin/employees.php');
        exit;
    }

    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

    try {
        $insertStmt = $db->prepare("
            INSERT INTO users (employee_id, name, email, phone, password, role, status)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $insertStmt->execute([$empId, $name, $email, $phone, $hashedPassword, $role, $status]);
        set_flash('success', "Employee account for {$name} ({$empId}) created successfully!");
    } catch (Exception $e) {
        set_flash('danger', 'Error creating employee: ' . $e->getMessage());
    }

    header('Location: ' . get_base_url() . '/admin/employees.php');
    exit;
}

// -------------------------------------------------------------
// POST Handler: Edit Employee Details
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_employee') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Security validation failed.');
        header('Location: ' . get_base_url() . '/admin/employees.php');
        exit;
    }

    $id     = (int)($_POST['id'] ?? 0);
    $name   = clean_string($_POST['name'] ?? '');
    $email  = clean_string($_POST['email'] ?? '');
    $phone  = clean_string($_POST['phone'] ?? '');
    $role   = in_array($_POST['role'] ?? '', ['admin', 'employee']) ? $_POST['role'] : 'employee';
    $status = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';

    if (empty($name)) {
        set_flash('danger', 'Full Name is required.');
        header('Location: ' . get_base_url() . '/admin/employees.php');
        exit;
    }

    try {
        $updateStmt = $db->prepare("
            UPDATE users
            SET name = ?, email = ?, phone = ?, role = ?, status = ?
            WHERE id = ?
        ");
        $updateStmt->execute([$name, $email, $phone, $role, $status, $id]);
        set_flash('success', 'Employee profile updated successfully.');
    } catch (Exception $e) {
        set_flash('danger', 'Error updating employee: ' . $e->getMessage());
    }

    header('Location: ' . get_base_url() . '/admin/employees.php');
    exit;
}

// -------------------------------------------------------------
// POST Handler: Reset Password
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_password') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Security validation failed.');
        header('Location: ' . get_base_url() . '/admin/employees.php');
        exit;
    }

    $id          = (int)($_POST['id'] ?? 0);
    $newPassword = $_POST['new_password'] ?? '';

    if (empty($newPassword) || strlen($newPassword) < 6) {
        set_flash('danger', 'Password must be at least 6 characters long.');
        header('Location: ' . get_base_url() . '/admin/employees.php');
        exit;
    }

    $hashed = password_hash($newPassword, PASSWORD_BCRYPT);
    try {
        $pwdStmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
        $pwdStmt->execute([$hashed, $id]);
        set_flash('success', 'Password reset successfully for employee.');
    } catch (Exception $e) {
        set_flash('danger', 'Error resetting password: ' . $e->getMessage());
    }

    header('Location: ' . get_base_url() . '/admin/employees.php');
    exit;
}

// -------------------------------------------------------------
// POST Handler: Toggle Status (Active / Inactive)
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_status') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Security validation failed.');
        header('Location: ' . get_base_url() . '/admin/employees.php');
        exit;
    }

    $id = (int)($_POST['id'] ?? 0);
    // Don't allow admin to deactivate themselves
    if ($id === (int)$currentUser['id']) {
        set_flash('danger', 'You cannot deactivate your own administrative account.');
        header('Location: ' . get_base_url() . '/admin/employees.php');
        exit;
    }

    $fetch = $db->prepare("SELECT status, name FROM users WHERE id = ?");
    $fetch->execute([$id]);
    $emp = $fetch->fetch();

    if ($emp) {
        $newStatus = ($emp['status'] === 'active') ? 'inactive' : 'active';
        $toggleStmt = $db->prepare("UPDATE users SET status = ? WHERE id = ?");
        $toggleStmt->execute([$newStatus, $id]);
        set_flash('info', "Account status for {$emp['name']} changed to " . strtoupper($newStatus) . ".");
    }

    header('Location: ' . get_base_url() . '/admin/employees.php');
    exit;
}

// -------------------------------------------------------------
// Query All Users with Stats
// -------------------------------------------------------------
$usersSql = "
    SELECT u.*,
           COUNT(DISTINCT r.id) AS total_reports,
           COALESCE(SUM(r.total_calls), 0) AS lifetime_calls,
           COALESCE(SUM(r.sales_closed), 0) AS lifetime_sales,
           COUNT(DISTINCT c.id) AS total_recordings
    FROM users u
    LEFT JOIN daily_reports r ON r.user_id = u.id
    LEFT JOIN call_recordings c ON c.user_id = u.id
    GROUP BY u.id
    ORDER BY u.role ASC, u.id ASC
";
$usersList = $db->query($usersSql)->fetchAll();

$pageTitle = "Manage Employees";
$activeNav = 'admin_employees';
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
                        <i class="fas fa-users-cog text-primary mr-2"></i> Employee Accounts
                    </h1>
                    <span class="text-muted small">Create, edit, reset passwords, and manage telecaller credentials</span>
                </div>
                <div class="col-sm-6 text-sm-right mt-2 mt-sm-0">
                    <button type="button" class="btn btn-primary shadow-sm" data-toggle="modal" data-target="#createEmployeeModal">
                        <i class="fas fa-user-plus mr-1"></i> Add New Employee
                    </button>
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

            <div class="card card-outline card-primary shadow">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h3 class="card-title font-weight-bold text-dark">
                        <i class="fas fa-id-card text-primary mr-1"></i> Registered Telecallers & Admins
                    </h3>
                    <span class="badge badge-primary"><?= count($usersList) ?> Accounts</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped align-middle mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>Employee ID</th>
                                    <th>Full Name</th>
                                    <th>Contact Info</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th class="text-center">Reports</th>
                                    <th class="text-center">Lifetime Calls</th>
                                    <th class="text-center">Sales Closed</th>
                                    <th>Last Login</th>
                                    <th class="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($usersList as $u): ?>
                                    <tr>
                                        <td class="font-weight-bold text-dark">
                                            <span class="badge badge-light border text-dark px-2 py-1" style="font-size: 0.85rem;">
                                                <i class="fas fa-id-badge text-primary mr-1"></i> <?= e($u['employee_id']) ?>
                                            </span>
                                        </td>
                                        <td class="font-weight-bold text-dark">
                                            <?= e($u['name']) ?>
                                        </td>
                                        <td>
                                            <div class="small">
                                                <?php if (!empty($u['email'])): ?>
                                                    <div><i class="far fa-envelope mr-1 text-muted"></i> <?= e($u['email']) ?></div>
                                                <?php endif; ?>
                                                <?php if (!empty($u['phone'])): ?>
                                                    <div><i class="fas fa-phone mr-1 text-muted"></i> <?= e($u['phone']) ?></div>
                                                <?php endif; ?>
                                                <?php if (empty($u['email']) && empty($u['phone'])): ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge <?= $u['role'] === 'admin' ? 'badge-danger' : 'badge-info' ?> text-uppercase">
                                                <?= e($u['role']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <form action="employees.php" method="POST" class="d-inline">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                                <button type="submit" class="btn btn-xs <?= $u['status'] === 'active' ? 'btn-success' : 'btn-secondary' ?>" title="Click to toggle status">
                                                    <i class="fas <?= $u['status'] === 'active' ? 'fa-check-circle' : 'fa-ban' ?> mr-1"></i>
                                                    <?= ucfirst(e($u['status'])) ?>
                                                </button>
                                            </form>
                                        </td>
                                        <td class="text-center font-weight-bold text-secondary">
                                            <?= number_format((int)$u['total_reports']) ?>
                                        </td>
                                        <td class="text-center font-weight-bold text-info">
                                            <?= number_format((int)$u['lifetime_calls']) ?>
                                        </td>
                                        <td class="text-center font-weight-bold text-success">
                                            <span class="badge badge-success px-2 py-1"><?= number_format((int)$u['lifetime_sales']) ?></span>
                                        </td>
                                        <td>
                                            <small class="text-muted"><?= format_datetime($u['last_login']) ?></small>
                                        </td>
                                        <td class="text-right">
                                            <div class="btn-group">
                                                <!-- Edit Details Button -->
                                                <button type="button" class="btn btn-sm btn-outline-primary" data-toggle="modal" data-target="#editModal_<?= $u['id'] ?>" title="Edit Details">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <!-- Reset Password Button -->
                                                <button type="button" class="btn btn-sm btn-outline-warning" data-toggle="modal" data-target="#resetModal_<?= $u['id'] ?>" title="Reset Password">
                                                    <i class="fas fa-key"></i>
                                                </button>
                                                <!-- Reports Link -->
                                                <a href="reports.php?employee_id=<?= $u['id'] ?>" class="btn btn-sm btn-outline-secondary" title="View Reports">
                                                    <i class="fas fa-chart-bar"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>

                                    <!-- EDIT MODAL FOR THIS USER -->
                                    <div class="modal fade" id="editModal_<?= $u['id'] ?>" tabindex="-1" role="dialog" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered" role="document">
                                            <div class="modal-content shadow border-0">
                                                <div class="modal-header bg-primary text-white">
                                                    <h5 class="modal-title font-weight-bold">
                                                        <i class="fas fa-user-edit mr-2"></i> Edit Employee: <?= e($u['employee_id']) ?>
                                                    </h5>
                                                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                                                </div>
                                                <form action="employees.php" method="POST">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="edit_employee">
                                                    <input type="hidden" name="id" value="<?= $u['id'] ?>">

                                                    <div class="modal-body">
                                                        <div class="form-group">
                                                            <label class="font-weight-bold small text-secondary">Employee ID (Read-only)</label>
                                                            <input type="text" class="form-control" value="<?= e($u['employee_id']) ?>" disabled>
                                                        </div>
                                                        <div class="form-group">
                                                            <label class="font-weight-bold small text-secondary">Full Name <span class="text-danger">*</span></label>
                                                            <input type="text" name="name" class="form-control" value="<?= e($u['name']) ?>" required>
                                                        </div>
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <div class="form-group">
                                                                    <label class="font-weight-bold small text-secondary">Email Address</label>
                                                                    <input type="email" name="email" class="form-control" value="<?= e($u['email']) ?>">
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <div class="form-group">
                                                                    <label class="font-weight-bold small text-secondary">Phone Number</label>
                                                                    <input type="text" name="phone" class="form-control" value="<?= e($u['phone']) ?>">
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <div class="form-group">
                                                                    <label class="font-weight-bold small text-secondary">Role</label>
                                                                    <select name="role" class="form-control">
                                                                        <option value="employee" <?= $u['role'] === 'employee' ? 'selected' : '' ?>>Employee (Telecaller)</option>
                                                                        <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Administrator</option>
                                                                    </select>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <div class="form-group">
                                                                    <label class="font-weight-bold small text-secondary">Account Status</label>
                                                                    <select name="status" class="form-control">
                                                                        <option value="active" <?= $u['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                                                        <option value="inactive" <?= $u['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                                                    </select>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer bg-light">
                                                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-primary px-4 shadow-sm">Save Changes</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- RESET PASSWORD MODAL -->
                                    <div class="modal fade" id="resetModal_<?= $u['id'] ?>" tabindex="-1" role="dialog" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered" role="document">
                                            <div class="modal-content shadow border-0">
                                                <div class="modal-header bg-warning text-dark">
                                                    <h5 class="modal-title font-weight-bold">
                                                        <i class="fas fa-key mr-2"></i> Reset Password: <?= e($u['name']) ?> (<?= e($u['employee_id']) ?>)
                                                    </h5>
                                                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                                                </div>
                                                <form action="employees.php" method="POST">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="reset_password">
                                                    <input type="hidden" name="id" value="<?= $u['id'] ?>">

                                                    <div class="modal-body">
                                                        <div class="form-group">
                                                            <label class="font-weight-bold small text-secondary">New Password <span class="text-danger">*</span></label>
                                                            <input type="password" name="new_password" class="form-control" placeholder="Min. 6 characters" minlength="6" required>
                                                            <small class="form-text text-muted">Provide this new password securely to the employee.</small>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer bg-light">
                                                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-warning px-4 font-weight-bold shadow-sm">Reset Password</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </section>
</div>

<!-- ========================================================
     MODAL: CREATE NEW EMPLOYEE
     ======================================================== -->
<div class="modal fade" id="createEmployeeModal" tabindex="-1" role="dialog" aria-labelledby="createEmployeeLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content shadow border-0">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title font-weight-bold" id="createEmployeeLabel">
                    <i class="fas fa-user-plus mr-2"></i> Register New Employee / Telecaller
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <form action="employees.php" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create_employee">

                <div class="modal-body">
                    <div class="form-group">
                        <label class="font-weight-bold small text-secondary">
                            Employee ID / Login Code <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-id-badge"></i></span></div>
                            <input type="text" name="employee_id" class="form-control" placeholder="e.g. EMP003" style="text-transform: uppercase;" required>
                        </div>
                        <small class="form-text text-muted">The employee will use this ID to sign in.</small>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold small text-secondary">Full Name <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-user"></i></span></div>
                            <input type="text" name="name" class="form-control" placeholder="e.g. Ankit Saxena" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold small text-secondary">Email Address</label>
                                <input type="email" name="email" class="form-control" placeholder="ankit@telecaller.com">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold small text-secondary">Phone Number</label>
                                <input type="text" name="phone" class="form-control" placeholder="+91 9876543210">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold small text-secondary">
                            Initial Login Password <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-lock"></i></span></div>
                            <input type="password" name="password" class="form-control" placeholder="Enter password (e.g. telecaller123)" minlength="6" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold small text-secondary">Role</label>
                                <select name="role" class="form-control">
                                    <option value="employee" selected>Employee (Telecaller)</option>
                                    <option value="admin">Administrator</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="font-weight-bold small text-secondary">Initial Status</label>
                                <select name="status" class="form-control">
                                    <option value="active" selected>Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4 shadow-sm">
                        <i class="fas fa-check-circle mr-1"></i> Create Account
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
