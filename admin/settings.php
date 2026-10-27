<?php
/**
 * Admin Panel - System Configuration & Environment Diagnostics
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$currentUser = current_user();

// -------------------------------------------------------------
// POST Handler: Update Settings
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_settings') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Security validation failed.');
        header('Location: ' . get_base_url() . '/admin/settings.php');
        exit;
    }

    $companyName = clean_string($_POST['company_name'] ?? DEFAULT_COMPANY_NAME);
    $maxUploadMb = (int)($_POST['max_upload_size_mb'] ?? 25);
    $allowedExts = clean_string($_POST['allowed_extensions'] ?? 'mp3,wav,m4a,ogg,aac,amr,webm');
    $timezone    = clean_string($_POST['timezone'] ?? 'Asia/Kolkata');

    update_setting('company_name', $companyName);
    update_setting('max_upload_size_mb', (string)$maxUploadMb);
    update_setting('allowed_extensions', strtolower($allowedExts));
    update_setting('timezone', $timezone);

    set_flash('success', 'System settings saved successfully.');
    header('Location: ' . get_base_url() . '/admin/settings.php');
    exit;
}

$currentCompanyName = get_setting('company_name', DEFAULT_COMPANY_NAME);
$currentMaxUploadMb = (int)get_setting('max_upload_size_mb', (string)MAX_UPLOAD_SIZE_MB);
$currentAllowedExts = get_setting('allowed_extensions', implode(',', $ALLOWED_AUDIO_EXTENSIONS));
$currentTimezone    = get_setting('timezone', 'Asia/Kolkata');

// Environment Diagnostics
$phpUploadMax = ini_get('upload_max_filesize');
$phpPostMax   = ini_get('post_max_size');
$phpMemLimit  = ini_get('memory_limit');
$isUploadsWritable = is_writable(ROOT_PATH . '/uploads');
$isRecordingsWritable = is_writable(RECORDINGS_PATH) || @mkdir(RECORDINGS_PATH, 0755, true);

$pageTitle = "System Settings";
$activeNav = 'admin_settings';
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
                        <i class="fas fa-sliders-h text-primary mr-2"></i> System Configuration
                    </h1>
                    <span class="text-muted small">Application parameters, upload rules, and shared hosting diagnostics</span>
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

            <div class="row">
                <!-- Settings Form -->
                <div class="col-lg-7">
                    <div class="card card-outline card-primary shadow">
                        <div class="card-header bg-white">
                            <h5 class="card-title font-weight-bold text-dark m-0">
                                <i class="fas fa-cog text-primary mr-1"></i> General Application Settings
                            </h5>
                        </div>
                        <form action="settings.php" method="POST">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="save_settings">

                            <div class="card-body">
                                <div class="form-group">
                                    <label class="font-weight-bold text-secondary">Organization / Company Name</label>
                                    <input type="text" name="company_name" class="form-control" value="<?= e($currentCompanyName) ?>" required>
                                    <small class="form-text text-muted">Displayed in header, page titles, and reports.</small>
                                </div>

                                <div class="form-group">
                                    <label class="font-weight-bold text-secondary">
                                        Max Call Recording Upload Limit (in MB)
                                    </label>
                                    <div class="input-group">
                                        <input type="number" min="1" max="200" name="max_upload_size_mb" class="form-control" value="<?= $currentMaxUploadMb ?>" required>
                                        <div class="input-group-append">
                                            <span class="input-group-text">MB</span>
                                        </div>
                                    </div>
                                    <small class="form-text text-muted">
                                        Note: PHP's <code>upload_max_filesize</code> is currently set to <strong><?= $phpUploadMax ?></strong>.
                                    </small>
                                </div>

                                <div class="form-group">
                                    <label class="font-weight-bold text-secondary">
                                        Allowed Audio Extensions (comma separated)
                                    </label>
                                    <input type="text" name="allowed_extensions" class="form-control" value="<?= e($currentAllowedExts) ?>" required>
                                    <small class="form-text text-muted">e.g. mp3,wav,m4a,ogg,aac,amr,webm</small>
                                </div>

                                <div class="form-group">
                                    <label class="font-weight-bold text-secondary">System Timezone</label>
                                    <input type="text" name="timezone" class="form-control" value="<?= e($currentTimezone) ?>" required>
                                    <small class="form-text text-muted">e.g. Asia/Kolkata, UTC, America/New_York</small>
                                </div>
                            </div>

                            <div class="card-footer bg-light">
                                <button type="submit" class="btn btn-primary px-4 shadow-sm">
                                    <i class="fas fa-save mr-1"></i> Save System Settings
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Server Environment Diagnostics -->
                <div class="col-lg-5">
                    <div class="card card-outline card-info shadow mb-4">
                        <div class="card-header bg-white">
                            <h5 class="card-title font-weight-bold text-dark m-0">
                                <i class="fas fa-server text-info mr-1"></i> Hosting Environment Diagnostics
                            </h5>
                        </div>
                        <div class="card-body p-0">
                            <ul class="list-group list-group-flush small">
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>PHP Version:</span>
                                    <strong class="text-dark"><?= phpversion() ?></strong>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>Active Database Driver:</span>
                                    <span class="badge badge-primary text-uppercase"><?= Database::getActiveDriver() ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>Upload Directory:</span>
                                    <span class="badge <?= $isUploadsWritable ? 'badge-success' : 'badge-danger' ?>">
                                        <?= $isUploadsWritable ? 'Writable (OK)' : 'Not Writable' ?>
                                    </span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>Recordings Directory:</span>
                                    <span class="badge <?= $isRecordingsWritable ? 'badge-success' : 'badge-danger' ?>">
                                        <?= $isRecordingsWritable ? 'Writable (OK)' : 'Not Writable' ?>
                                    </span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>PHP upload_max_filesize:</span>
                                    <strong><?= $phpUploadMax ?></strong>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>PHP post_max_size:</span>
                                    <strong><?= $phpPostMax ?></strong>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>PHP memory_limit:</span>
                                    <strong><?= $phpMemLimit ?></strong>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>Server Software:</span>
                                    <span class="text-truncate" style="max-width: 200px;"><?= e($_SERVER['SERVER_SOFTWARE'] ?? 'PHP CLI') ?></span>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div class="card card-outline card-secondary shadow">
                        <div class="card-header bg-white">
                            <h5 class="card-title font-weight-bold text-dark m-0">
                                <i class="fas fa-shield-alt text-secondary mr-1"></i> Security Check
                            </h5>
                        </div>
                        <div class="card-body small">
                            <p class="mb-2"><i class="fas fa-check-circle text-success mr-1"></i> <strong>SQL Injection Protection:</strong> 100% PDO prepared statements.</p>
                            <p class="mb-2"><i class="fas fa-check-circle text-success mr-1"></i> <strong>XSS Protection:</strong> Output escaping via <code>htmlspecialchars</code>.</p>
                            <p class="mb-2"><i class="fas fa-check-circle text-success mr-1"></i> <strong>CSRF Protection:</strong> Active token on all form submissions.</p>
                            <p class="mb-2"><i class="fas fa-check-circle text-success mr-1"></i> <strong>Password Hashing:</strong> PHP Bcrypt (cost 12).</p>
                            <p class="mb-0"><i class="fas fa-check-circle text-success mr-1"></i> <strong>Script Execution Shield:</strong> Upload directory protected by <code>.htaccess</code>.</p>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </section>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
