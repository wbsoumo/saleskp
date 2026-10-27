<?php
/**
 * Unified Login Page
 * Supports both Admin and Employee authentication via Employee ID / Password
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// Redirect if already authenticated
if (is_logged_in()) {
    if (is_admin()) {
        header('Location: ' . get_base_url() . '/admin/index.php');
    } else {
        header('Location: ' . get_base_url() . '/employee/workspace.php');
    }
    exit;
}

$error = '';
$loginId = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $error = 'Invalid security token. Please refresh and try again.';
    } else {
        $loginId = clean_string($_POST['login_id'] ?? '');
        $password = $_POST['password'] ?? '';

        $authResult = authenticate_user($loginId, $password);
        if ($authResult['success']) {
            login_user($authResult['user']);

            set_flash('success', 'Welcome back, ' . htmlspecialchars($authResult['user']['name']) . '!');
            if ($authResult['user']['role'] === 'admin') {
                header('Location: ' . get_base_url() . '/admin/index.php');
            } else {
                header('Location: ' . get_base_url() . '/employee/workspace.php');
            }
            exit;
        } else {
            $error = $authResult['error'];
        }
    }
}

$companyName = get_setting('company_name', DEFAULT_COMPANY_NAME);
$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign In | <?= e($companyName) ?></title>

    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,600,700&display=fallback">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- AdminLTE 3 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-box {
            width: 420px;
            max-width: 92%;
        }
        .card {
            border: 0;
            border-radius: 12px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.25);
            overflow: hidden;
        }
        .card-header {
            background: #ffffff;
            border-bottom: 1px solid #f1f5f9;
            padding: 24px 20px 18px;
        }
        .btn-primary {
            background-color: #2563eb;
            border-color: #2563eb;
            font-weight: 600;
            border-radius: 6px;
            padding: 10px;
        }
        .btn-primary:hover {
            background-color: #1d4ed8;
            border-color: #1d4ed8;
        }
    </style>
</head>
<body class="hold-transition login-page">
<div class="login-box my-4">
    <div class="card shadow">
        <div class="card-header text-center">
            <div class="mb-2">
                <span class="d-inline-flex align-items-center justify-content-center bg-primary text-white rounded-circle shadow-sm" style="width: 52px; height: 52px;">
                    <i class="fas fa-headset fa-2x"></i>
                </span>
            </div>
            <h4 class="font-weight-bold text-dark mb-0"><?= e($companyName) ?></h4>
            <p class="text-muted small mb-0">Telecaller Reporting & Analytics Portal</p>
        </div>
        <div class="card-body p-4">
            <?php if ($flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show" role="alert">
                    <?= e($flash['message']) ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle mr-1"></i> <?= e($error) ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST" autocomplete="on">
                <?= csrf_field() ?>

                <div class="form-group mb-3">
                    <label class="text-secondary small font-weight-bold mb-1">Employee ID / Username</label>
                    <div class="input-group">
                        <input type="text" id="login_id" name="login_id" class="form-control" placeholder="Enter Employee ID or Username" value="<?= e($loginId) ?>" required autofocus>
                        <div class="input-group-append">
                            <div class="input-group-text bg-light text-muted">
                                <span class="fas fa-id-badge"></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-group mb-4">
                    <label class="text-secondary small font-weight-bold mb-1">Password</label>
                    <div class="input-group">
                        <input type="password" id="password" name="password" class="form-control" placeholder="Enter your password" required>
                        <div class="input-group-append">
                            <button type="button" class="btn btn-outline-secondary" id="togglePasswordBtn" title="Show/Hide Password">
                                <span class="fas fa-eye" id="togglePasswordIcon"></span>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary btn-block shadow-sm">
                            <i class="fas fa-sign-in-alt mr-1"></i> Sign In to Portal
                        </button>
                    </div>
                </div>
            </form>
        </div>
        <div class="card-footer bg-light text-center py-2 text-muted small">
            <i class="fas fa-lock mr-1"></i> Secured Telecaller & Lead CRM Portal
        </div>
    </div>
</div>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Bootstrap 4 Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.getElementById('togglePasswordBtn').addEventListener('click', function() {
        const passInput = document.getElementById('password');
        const icon = document.getElementById('togglePasswordIcon');
        if (passInput.type === 'password') {
            passInput.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            passInput.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    });
</script>
</body>
</html>
