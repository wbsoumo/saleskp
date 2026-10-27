<?php
/**
 * Mandatory Daily Attendance Modal Component
 * Triggers every day for employees logging in after 7:00 AM if attendance has not yet been recorded.
 */

if (!is_logged_in() || !is_employee()) {
    return;
}

$currentUserId = current_user_id();
if (!should_show_attendance_popup($currentUserId)) {
    return;
}

$workingDate = get_current_attendance_date();
$formattedDate = date('l, d F Y', strtotime($workingDate));
$currentTime = date('h:i A');
$csrfToken = get_csrf_token();
?>

<!-- Mandatory Attendance Modal -->
<div class="modal fade" id="mandatoryAttendanceModal" data-backdrop="static" data-keyboard="false" tabindex="-1" role="dialog" aria-labelledby="attendanceModalLabel" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered modal-md" role="document">
        <div class="modal-content shadow-lg border-0 rounded-lg">
            <!-- Modal Header -->
            <div class="modal-header bg-gradient-navy text-white py-3">
                <div class="d-flex align-items-center">
                    <div class="mr-3 bg-white text-navy rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 44px; height: 44px;">
                        <i class="fas fa-calendar-check fa-lg text-primary"></i>
                    </div>
                    <div>
                        <h5 class="modal-title font-weight-bold mb-0" id="attendanceModalLabel">
                            Daily Attendance Check-In
                        </h5>
                        <small class="text-white-50">
                            <i class="fas fa-business-time mr-1"></i>Working Date: <strong><?= $formattedDate ?></strong>
                        </small>
                    </div>
                </div>
            </div>

            <!-- Modal Form -->
            <form id="mandatoryAttendanceForm" action="<?= get_base_url() ?>/employee/attendance_action.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="redirect_url" value="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? '') ?>">

                <div class="modal-body p-4">
                    <!-- Info Alert -->
                    <div class="alert alert-light border border-info mb-3 py-2 px-3">
                        <div class="d-flex align-items-center justify-content-between">
                            <span class="text-muted small">
                                <i class="fas fa-user-circle text-primary mr-1"></i>Telecaller: <strong><?= e(current_user_name()) ?></strong>
                            </span>
                            <span class="badge badge-info px-2 py-1">
                                <i class="far fa-clock mr-1"></i><?= $currentTime ?>
                            </span>
                        </div>
                    </div>

                    <p class="text-secondary small mb-3">
                        Please record your daily attendance before accessing your workspace. Submissions are limited to <strong>once per day</strong> and reset after <strong>7:00 AM</strong> the following morning.
                    </p>

                    <!-- Status Selection Options -->
                    <label class="font-weight-bold text-dark mb-2">
                        Select Today's Attendance Status <span class="text-danger">*</span>
                    </label>

                    <div class="attendance-options-group mb-3">
                        <!-- Option: Present -->
                        <label class="attendance-option-card d-flex align-items-center p-3 mb-2 border rounded cursor-pointer selected" for="att_status_present">
                            <div class="custom-control custom-radio mr-3">
                                <input type="radio" id="att_status_present" name="status" value="Present" class="custom-control-input" checked required>
                                <label class="custom-control-label" for="att_status_present"></label>
                            </div>
                            <div class="icon-box mr-3 text-success">
                                <i class="fas fa-check-circle fa-2x"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="font-weight-bold text-dark mb-0">Present</div>
                                <small class="text-muted">Full day available for telecalling & client leads</small>
                            </div>
                            <span class="badge badge-success px-2 py-1 font-weight-normal">1.0 Day</span>
                        </label>

                        <!-- Option: Half Day -->
                        <label class="attendance-option-card d-flex align-items-center p-3 mb-2 border rounded cursor-pointer" for="att_status_halfday">
                            <div class="custom-control custom-radio mr-3">
                                <input type="radio" id="att_status_halfday" name="status" value="Half Day" class="custom-control-input" required>
                                <label class="custom-control-label" for="att_status_halfday"></label>
                            </div>
                            <div class="icon-box mr-3 text-warning">
                                <i class="fas fa-adjust fa-2x"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="font-weight-bold text-dark mb-0">Half Day</div>
                                <small class="text-muted">Partial working shift (4 hours)</small>
                            </div>
                            <span class="badge badge-warning px-2 py-1 font-weight-normal text-dark">0.5 Day</span>
                        </label>

                        <!-- Option: Absent -->
                        <label class="attendance-option-card d-flex align-items-center p-3 mb-2 border rounded cursor-pointer" for="att_status_absent">
                            <div class="custom-control custom-radio mr-3">
                                <input type="radio" id="att_status_absent" name="status" value="Absent" class="custom-control-input" required>
                                <label class="custom-control-label" for="att_status_absent"></label>
                            </div>
                            <div class="icon-box mr-3 text-danger">
                                <i class="fas fa-times-circle fa-2x"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="font-weight-bold text-dark mb-0">Absent / Leave</div>
                                <small class="text-muted">Not available for calling duties today</small>
                            </div>
                            <span class="badge badge-danger px-2 py-1 font-weight-normal">0.0 Day</span>
                        </label>
                    </div>

                    <!-- Optional Remarks -->
                    <div class="form-group mb-0">
                        <label for="att_remarks" class="small font-weight-bold text-secondary">
                            Remarks / Reason <span class="text-muted font-weight-normal">(Optional)</span>
                        </label>
                        <input type="text" id="att_remarks" name="remarks" class="form-control form-control-sm" placeholder="e.g. In-office, Working from home, Doctor appointment, Sick leave...">
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="modal-footer bg-light p-3">
                    <button type="submit" id="btnSubmitAttendance" class="btn btn-primary btn-block font-weight-bold py-2 shadow-sm">
                        <i class="fas fa-check-double mr-2"></i>Confirm & Submit Attendance
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.attendance-option-card {
    transition: all 0.2s ease;
    background: #ffffff;
    cursor: pointer;
    border: 2px solid #e9ecef !important;
}
.attendance-option-card:hover {
    background: #f8f9fa;
    border-color: #ced4da !important;
}
.attendance-option-card.selected {
    background: #f0f7ff;
    border-color: #007bff !important;
    box-shadow: 0 2px 8px rgba(0, 123, 255, 0.15);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof jQuery !== 'undefined') {
        var $modal = $('#mandatoryAttendanceModal');
        if ($modal.length) {
            if (typeof $modal.modal === 'function') {
                $modal.modal({
                    backdrop: 'static',
                    keyboard: false,
                    show: true
                });
            } else {
                var el = document.getElementById('mandatoryAttendanceModal');
                if (el) {
                    el.classList.add('show');
                    el.style.display = 'block';
                    document.body.classList.add('modal-open');
                }
            }

            // Card highlight on radio change
            $('input[name="status"]').on('change', function() {
                $('.attendance-option-card').removeClass('selected');
                $(this).closest('.attendance-option-card').addClass('selected');
            });

            // Prevent double submission
            $('#mandatoryAttendanceForm').on('submit', function() {
                var btn = $('#btnSubmitAttendance');
                btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i>Recording Attendance...');
            });
        }
    }
});
</script>
