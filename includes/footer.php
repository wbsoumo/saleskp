<?php
/**
 * Global Footer Component (AdminLTE 3)
 * Telecaller Reporting Web App
 */

$companyName = get_setting('company_name', DEFAULT_COMPANY_NAME);
$baseUrl = get_base_url();
?>
    <!-- Main Footer -->
    <footer class="main-footer text-muted" style="font-size: 0.85rem;">
        <div class="float-right d-none d-sm-inline">
            <b>Version</b> <?= APP_VERSION ?>
        </div>
        <strong>Copyright &copy; <?= date('Y') ?> <a href="<?= $baseUrl ?>"><?= e($companyName) ?></a>.</strong> All rights reserved.
    </footer>
</div>
<!-- ./wrapper -->

<?php require_once __DIR__ . '/attendance_modal.php'; ?>

<!-- REQUIRED SCRIPTS -->
<!-- AdminLTE App -->
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<!-- DataTables -->
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap4.min.js"></script>
<!-- ChartJS -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<!-- Custom App Script -->
<script src="<?= $baseUrl ?>/assets/js/app.js"></script>

<?php if (isset($extraScripts)): ?>
    <?= $extraScripts ?>
<?php endif; ?>
</body>
</html>
