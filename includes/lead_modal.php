<?php
/**
 * Interactive Lead Details & Calling Modal
 * Included in Workspace and Admin Lead views
 */
?>
<!-- Lead Details & Call Logging Modal -->
<div class="modal fade" id="leadDetailsModal" tabindex="-1" role="dialog" aria-labelledby="leadModalTitle" aria-hidden="true" data-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content shadow border-0">
            <!-- Modal Header -->
            <div class="modal-header bg-dark text-white py-3">
                <div class="d-flex align-items-center">
                    <span class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center mr-3" style="width: 44px; height: 44px; font-size: 1.2rem;">
                        <i class="fas fa-user"></i>
                    </span>
                    <div>
                        <h5 class="modal-title font-weight-bold mb-0 text-white" id="leadModalName">Loading Lead...</h5>
                        <small class="text-light opacity-75" id="leadModalCompany">Company</small>
                    </div>
                </div>
                <div class="ml-auto mr-3 d-none d-sm-block text-right">
                    <span id="leadModalStatusBadge"></span>
                    <span class="badge badge-light border ml-1" id="leadModalAttemptsBadge">0 Attempts</span>
                </div>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <!-- Modal Body -->
            <div class="modal-body p-0">
                <!-- Clickable Call Action Banner -->
                <div class="bg-light p-3 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div class="d-flex align-items-center">
                        <a id="leadModalCallBtn" href="tel:" class="btn btn-success btn-lg shadow-sm font-weight-bold px-4 mr-3">
                            <i class="fas fa-phone-alt mr-2"></i> <span id="leadModalCallText">Call Now</span>
                        </a>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnCopyPhone" title="Copy Phone Number">
                            <i class="far fa-copy mr-1"></i> Copy
                        </button>
                    </div>
                    <div class="text-muted small mt-2 mt-sm-0">
                        Assigned To: <strong class="text-dark" id="leadModalAssignedTo">-</strong> &bull;
                        Last Called: <span id="leadModalLastCalled">Never</span>
                    </div>
                </div>

                <!-- Tabs Navigation -->
                <ul class="nav nav-tabs px-3 pt-2 bg-white" id="leadModalTabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active font-weight-bold" id="tab-log-link" data-toggle="tab" href="#tab-log" role="tab">
                            <i class="fas fa-phone-volume text-primary mr-1"></i> Log Call Outcome
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link font-weight-bold" id="tab-history-link" data-toggle="tab" href="#tab-history" role="tab">
                            <i class="fas fa-history text-secondary mr-1"></i> Call History (<span id="leadModalLogCount">0</span>)
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link font-weight-bold" id="tab-info-link" data-toggle="tab" href="#tab-info" role="tab">
                            <i class="fas fa-info-circle text-info mr-1"></i> Lead Profile
                        </a>
                    </li>
                </ul>

                <div class="tab-content p-4" id="leadModalTabContent">
                    <!-- TAB 1: LOG CALL FORM -->
                    <div class="tab-pane fade show active" id="tab-log" role="tabpanel">
                        <div id="logCallAlert" class="alert d-none"></div>

                        <form id="formLogCall" action="<?= get_base_url() ?>/lead_api.php" method="POST" enctype="multipart/form-data" onsubmit="return submitCallLogForm(event);">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="log_call">
                            <input type="hidden" name="lead_id" id="logLeadId" value="">

                            <div class="row">
                                <!-- Call Outcome Selection -->
                                <div class="col-md-7 mb-3">
                                    <label class="font-weight-bold text-secondary small">
                                        Call Outcome / Status <span class="text-danger">*</span>
                                    </label>
                                    <select name="call_outcome" id="selectCallOutcome" class="form-control form-control-lg font-weight-bold" required>
                                        <option value="">-- Select Call Outcome --</option>
                                        <optgroup label="Positive Outcomes">
                                            <option value="Interested">Interested</option>
                                            <option value="Follow-up Required">Follow-up Required</option>
                                            <option value="Meeting Scheduled">Meeting Scheduled</option>
                                            <option value="Sales Closed">Sales Closed</option>
                                        </optgroup>
                                        <optgroup label="Unanswered / Busy">
                                            <option value="Ringing">Ringing / No Pick</option>
                                            <option value="Not Answered">Not Answered</option>
                                            <option value="Busy">Busy</option>
                                            <option value="Switched Off">Switched Off</option>
                                        </optgroup>
                                        <optgroup label="Negative / Other">
                                            <option value="Not Interested">Not Interested</option>
                                            <option value="Invalid Number">Invalid Number</option>
                                        </optgroup>
                                    </select>
                                </div>

                                <!-- Call Duration -->
                                <div class="col-md-5 mb-3">
                                    <label class="font-weight-bold text-secondary small">Call Duration (mm:ss)</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend"><span class="input-group-text"><i class="far fa-clock"></i></span></div>
                                        <input type="text" name="call_duration" id="inputCallDuration" class="form-control" placeholder="02:30">
                                    </div>
                                    <small class="text-muted">Auto-detected upon recording upload</small>
                                </div>
                            </div>

                            <!-- Follow-up Date & Time (Conditional) -->
                            <div class="form-group mb-3 p-3 bg-light rounded border border-primary d-none" id="groupFollowUpDate">
                                <label class="font-weight-bold text-primary small d-flex align-items-center mb-1">
                                    <i class="fas fa-calendar-alt mr-1"></i> Follow-up Date & Time <span class="text-danger ml-1">*</span>
                                </label>
                                <input type="datetime-local" name="follow_up_date" id="inputFollowUpDate" class="form-control font-weight-bold">
                                <small class="text-muted">Set when to call back this lead. Will show in today's due follow-ups.</small>
                            </div>

                            <!-- Remarks / Call Notes -->
                            <div class="form-group mb-3">
                                <label class="font-weight-bold text-secondary small">Call Notes / Discussion Summary</label>
                                <textarea name="remarks" id="inputRemarks" class="form-control" rows="3" placeholder="Enter key remarks, client requirements, budget discussion, or next steps..."></textarea>
                            </div>

                            <!-- Audio Recording Upload -->
                            <div class="form-group mb-3">
                                <label class="font-weight-bold text-secondary small">
                                    <i class="fas fa-microphone-alt text-danger mr-1"></i> Attach Call Recording (Optional)
                                </label>
                                <div class="custom-file">
                                    <input type="file" name="audio_file" class="custom-file-input" id="modalAudioFile" accept=".mp3,.wav,.m4a,.ogg,.aac,.amr,.webm,audio/*">
                                    <label class="custom-file-label" for="modalAudioFile">Choose audio file (mp3, wav, m4a, ogg)...</label>
                                </div>
                                <small class="text-muted">Max file size: <?= MAX_UPLOAD_SIZE_MB ?> MB</small>

                                <!-- Audio Selection Preview -->
                                <div id="modalAudioPreviewBox" class="mt-2 p-2 border rounded bg-light d-none">
                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                        <div class="small font-weight-bold text-dark text-truncate" style="max-width: 75%;">
                                            <i class="fas fa-file-audio text-danger mr-1"></i> <span id="modalAudioFileName">Selected audio</span>
                                        </div>
                                        <span id="modalAudioFileSize" class="badge badge-info px-2 py-1">0 KB</span>
                                    </div>
                                    <audio id="modalAudioPreviewPlayer" controls class="w-100 mt-1" style="height: 32px;" preload="none"></audio>
                                </div>
                            </div>

                            <!-- Real-time Upload Progress Bar -->
                            <div id="uploadProgressContainer" class="mb-3 p-3 bg-light border rounded d-none">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span id="uploadStatusText" class="small font-weight-bold text-primary">
                                        <i class="fas fa-cloud-upload-alt mr-1"></i> Uploading recording...
                                    </span>
                                    <span id="uploadPercentText" class="badge badge-primary px-2 py-1 font-weight-bold">0%</span>
                                </div>
                                <div class="progress" style="height: 12px; border-radius: 6px; background-color: #dee2e6;">
                                    <div id="uploadProgressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-primary" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mt-2">
                                    <small id="uploadBytesText" class="text-muted" style="font-size: 0.75rem;">Uploading...</small>
                                    <span id="uploadDoneBadge" class="badge badge-success px-2 py-1 font-weight-bold d-none shadow-sm">
                                        <i class="fas fa-check-double mr-1"></i> Uploaded
                                    </span>
                                </div>
                            </div>

                            <button type="submit" id="btnSubmitLog" class="btn btn-primary btn-block btn-lg shadow-sm font-weight-bold">
                                <i class="fas fa-save mr-1"></i> Save Call Log & Update Lead
                            </button>
                        </form>
                    </div>

                    <!-- TAB 2: CALL HISTORY TIMELINE -->
                    <div class="tab-pane fade" id="tab-history" role="tabpanel">
                        <div id="leadHistoryList">
                            <p class="text-muted text-center py-4">No call attempts recorded for this lead yet.</p>
                        </div>
                    </div>

                    <!-- TAB 3: LEAD INFORMATION -->
                    <div class="tab-pane fade" id="tab-info" role="tabpanel">
                        <div class="row">
                            <div class="col-sm-6 mb-3">
                                <label class="text-muted small font-weight-bold mb-0">Phone Number</label>
                                <div class="font-weight-bold text-dark" id="leadInfoPhone">-</div>
                            </div>
                            <div class="col-sm-6 mb-3">
                                <label class="text-muted small font-weight-bold mb-0">Email Address</label>
                                <div class="font-weight-bold text-dark" id="leadInfoEmail">-</div>
                            </div>
                            <div class="col-sm-6 mb-3">
                                <label class="text-muted small font-weight-bold mb-0">Company / Organization</label>
                                <div class="font-weight-bold text-dark" id="leadInfoCompany">-</div>
                            </div>
                            <div class="col-sm-6 mb-3">
                                <label class="text-muted small font-weight-bold mb-0">City / Location</label>
                                <div class="font-weight-bold text-dark" id="leadInfoCity">-</div>
                            </div>
                            <div class="col-12 mb-3">
                                <label class="text-muted small font-weight-bold mb-0">Initial Notes / Source Details</label>
                                <div class="p-2 bg-light rounded text-dark small" id="leadInfoNotes">-</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
window.currentOpenLeadPhone = '';

window.safeShowModal = function(modalId) {
    const $m = $('#' + modalId);
    if ($m.length && typeof $m.modal === 'function') {
        $m.modal('show');
    } else {
        const el = document.getElementById(modalId);
        if (el) {
            el.classList.add('show');
            el.style.display = 'block';
            el.removeAttribute('aria-hidden');
            el.setAttribute('aria-modal', 'true');
            document.body.classList.add('modal-open');
            let backdrop = document.getElementById(modalId + '-backdrop');
            if (!backdrop) {
                backdrop = document.createElement('div');
                backdrop.id = modalId + '-backdrop';
                backdrop.className = 'modal-backdrop fade show';
                document.body.appendChild(backdrop);
            }
        }
    }
};

window.safeHideModal = function(modalId) {
    const $m = $('#' + modalId);
    if ($m.length && typeof $m.modal === 'function') {
        $m.modal('hide');
    } else {
        const el = document.getElementById(modalId);
        if (el) {
            el.classList.remove('show');
            el.style.display = 'none';
            el.setAttribute('aria-hidden', 'true');
            el.removeAttribute('aria-modal');
            document.body.classList.remove('modal-open');
            const backdrop = document.getElementById(modalId + '-backdrop');
            if (backdrop) backdrop.remove();
        }
    }
};

window.safeShowTab = function(tabLinkId) {
    const $t = $('#' + tabLinkId);
    if ($t.length && typeof $t.tab === 'function') {
        $t.tab('show');
    } else {
        const link = document.getElementById(tabLinkId);
        if (link) {
            const targetId = link.getAttribute('href');
            document.querySelectorAll('#leadModalTabs .nav-link').forEach(function(l) {
                l.classList.remove('active');
            });
            document.querySelectorAll('#leadModalTabContent .tab-pane').forEach(function(p) {
                p.classList.remove('show', 'active');
            });
            link.classList.add('active');
            if (targetId) {
                const pane = document.querySelector(targetId);
                if (pane) pane.classList.add('show', 'active');
            }
        }
    }
};

// Global fallback click listener for modal dismissal and tabs
document.addEventListener('click', function(e) {
    if (e.target.closest('#leadDetailsModal [data-dismiss="modal"]') || (e.target.id && e.target.id === 'leadDetailsModal-backdrop')) {
        window.safeHideModal('leadDetailsModal');
    }
    const tabLink = e.target.closest('#leadModalTabs .nav-link');
    if (tabLink && (!window.jQuery || typeof $.fn.tab !== 'function')) {
        e.preventDefault();
        window.safeShowTab(tabLink.id);
    }
});

window.openLeadModal = function(leadId) {
    isCallLogSubmitting = false;
    if (typeof $ !== 'undefined') {
        $('#btnSubmitLog').prop('disabled', false).removeClass('btn-success').addClass('btn-primary').html('<i class="fas fa-save mr-1"></i> Save Call Log & Update Lead');
        $('#logCallAlert').addClass('d-none').text('');
        $('#modalAudioFile').next('.custom-file-label').html('Choose audio file (mp3, wav, m4a, ogg)...');
        $('#groupFollowUpDate').addClass('d-none');
        $('#inputFollowUpDate').prop('required', false);
        $('#modalAudioPreviewBox').addClass('d-none');
        $('#uploadProgressContainer').addClass('d-none');
        $('#uploadProgressBar').css('width', '0%');
        $('#uploadDoneBadge').addClass('d-none');
        $('#logLeadId').val(leadId);
        $('#leadModalName').text('Loading Lead #' + leadId + '...');
        $('#leadModalCompany').text('Please wait...');
        $('#leadModalStatusBadge').html('');
        $('#leadModalAttemptsBadge').text('...');
        $('#leadModalCallBtn').attr('href', 'tel:');
        $('#leadModalCallText').text('Loading...');
    }

    const form = document.getElementById('formLogCall');
    if (form) form.reset();

    var prevPlayer = document.getElementById('modalAudioPreviewPlayer');
    if (prevPlayer) {
        prevPlayer.pause();
        prevPlayer.src = '';
    }

    window.safeShowModal('leadDetailsModal');
    window.safeShowTab('tab-log-link');

    // Fetch lead details via API
    $.ajax({
        url: '<?= get_base_url() ?>/lead_api.php?action=get_lead&id=' + leadId,
        type: 'GET',
        dataType: 'json',
        success: function(resp) {
            if (!resp.success) {
                alert(resp.error || 'Failed to load lead details.');
                window.safeHideModal('leadDetailsModal');
                return;
            }

            const l = resp.lead;
            window.currentOpenLeadPhone = l.phone;
            $('#leadModalName').text(l.name);
            $('#leadModalCompany').text(l.company ? l.company : (l.city ? l.city : 'Direct Prospect'));
            $('#leadModalStatusBadge').html(l.status_badge);
            $('#leadModalAttemptsBadge').text(l.call_attempts + ' Attempts');

            // Set Call Now Tel link
            $('#leadModalCallBtn').attr('href', 'tel:' + l.phone);
            $('#leadModalCallText').text('Call ' + l.phone);
            $('#leadModalAssignedTo').text(l.assigned_to_name);
            $('#leadModalLastCalled').text(l.last_called_at);

            // Populate Info tab
            $('#leadInfoPhone').text(l.phone);
            $('#leadInfoEmail').text(l.email || 'N/A');
            $('#leadInfoCompany').text(l.company || 'N/A');
            $('#leadInfoCity').text(l.city || 'N/A');
            $('#leadInfoNotes').text(l.notes || 'No initial notes available.');

            // Populate Call History
            $('#leadModalLogCount').text(resp.call_logs.length);
            window.renderCallLogs(resp.call_logs);
        },
        error: function() {
            alert('Error communicating with server.');
            window.safeHideModal('leadDetailsModal');
        }
    });
};

window.renderCallLogs = function(logs) {
    const container = $('#leadHistoryList');
    if (!logs || logs.length === 0) {
        container.html('<div class="text-center py-4 text-muted"><i class="fas fa-phone-slash fa-2x mb-2 text-secondary"></i><p>No call history recorded for this lead yet.</p></div>');
        return;
    }

    let html = '<div class="timeline">';
    logs.forEach(function(item) {
        html += `
        <div class="time-label mb-2">
            <span class="bg-primary text-white text-xs px-2 py-1 rounded"><i class="fas fa-calendar-day mr-1"></i> ${item.created_at}</span>
        </div>
        <div>
            <i class="fas fa-phone bg-info text-white"></i>
            <div class="timeline-item shadow-sm mb-3">
                <span class="time"><i class="far fa-clock mr-1"></i> Duration: ${item.call_duration}</span>
                <h3 class="timeline-header font-weight-bold" style="font-size: 0.95rem;">
                    Caller: <span class="text-dark">${item.caller_name} (${item.caller_code})</span> &bull; Outcome: ${item.badge_html}
                </h3>
                <div class="timeline-body text-secondary small">
                    ${item.remarks ? `<div><i class="fas fa-quote-left mr-1 text-muted"></i> ${item.remarks}</div>` : '<span class="text-muted font-italic">No remarks entered.</span>'}
                    ${item.follow_up_date ? `<div class="mt-2 text-primary font-weight-bold"><i class="fas fa-bell mr-1"></i> Follow-up Scheduled: ${item.follow_up_date}</div>` : ''}
                    ${item.recording_url ? `
                        <div class="mt-3 pt-2 border-top">
                            <strong class="text-dark d-block mb-1"><i class="fas fa-headphones mr-1 text-danger"></i> Call Audio Recording:</strong>
                            <div class="audio-player-container">
                                <audio controls preload="none">
                                    <source src="${item.recording_url}">
                                    Audio not supported.
                                </audio>
                            </div>
                        </div>
                    ` : ''}
                </div>
            </div>
        </div>
        `;
    });
    html += '<div><i class="fas fa-clock bg-gray"></i></div></div>';
    container.html(html);
};

let isCallLogSubmitting = false;

// Form submit handler with progress bar
window.submitCallLogForm = function(e) {
    if (e) {
        if (e.preventDefault) e.preventDefault();
        if (e.stopPropagation) e.stopPropagation();
        if (e.stopImmediatePropagation) e.stopImmediatePropagation();
    }

    if (isCallLogSubmitting) {
        return false;
    }

    const form = document.getElementById('formLogCall');
    if (!form) return false;

    if (!form.checkValidity()) {
        form.reportValidity();
        return false;
    }

    isCallLogSubmitting = true;

    const btn = $('#btnSubmitLog');
    const alertBox = $('#logCallAlert');
    const audioInput = document.getElementById('modalAudioFile');
    const hasAudio = audioInput && audioInput.files && audioInput.files.length > 0;

    btn.prop('disabled', true);
    alertBox.addClass('d-none').removeClass('alert-success alert-danger');

    if (hasAudio) {
        $('#uploadProgressContainer').removeClass('d-none');
        $('#uploadProgressBar').removeClass('bg-success').addClass('bg-primary progress-bar-animated progress-bar-striped').css('width', '0%');
        $('#uploadPercentText').removeClass('badge-success').addClass('badge-primary').text('0%');
        $('#uploadStatusText').html('<i class="fas fa-cloud-upload-alt mr-1"></i> Uploading call recording...');
        $('#uploadDoneBadge').addClass('d-none');
        btn.html('<i class="fas fa-spinner fa-spin mr-1"></i> Uploading Recording...');
    } else {
        $('#uploadProgressContainer').addClass('d-none');
        btn.html('<i class="fas fa-spinner fa-spin mr-1"></i> Saving Call Log...');
    }

    const formData = new FormData(form);

    $.ajax({
        url: '<?= get_base_url() ?>/lead_api.php',
        type: 'POST',
        data: formData,
        contentType: false,
        processData: false,
        dataType: 'json',
        xhr: function() {
            const xhr = new window.XMLHttpRequest();
            if (hasAudio && xhr.upload) {
                xhr.upload.addEventListener('progress', function(ev) {
                    if (ev.lengthComputable) {
                        const percent = Math.min(100, Math.round((ev.loaded / ev.total) * 100));
                        $('#uploadProgressBar').css('width', percent + '%').attr('aria-valuenow', percent);
                        $('#uploadPercentText').text(percent + '%');

                        const loadedMb = (ev.loaded / (1024 * 1024)).toFixed(2);
                        const totalMb = (ev.total / (1024 * 1024)).toFixed(2);
                        $('#uploadBytesText').text(loadedMb + ' MB of ' + totalMb + ' MB uploaded');

                        if (percent >= 100) {
                            $('#uploadProgressBar').removeClass('bg-primary progress-bar-animated').addClass('bg-success');
                            $('#uploadPercentText').removeClass('badge-primary').addClass('badge-success').text('100%');
                            $('#uploadStatusText').html('<i class="fas fa-check-circle text-success mr-1"></i> <strong>Uploaded!</strong> Finalizing call data...');
                            $('#uploadDoneBadge').removeClass('d-none');
                        }
                    }
                }, false);
            }
            return xhr;
        },
        success: function(resp) {
            if (resp.success) {
                if (hasAudio) {
                    $('#uploadProgressBar').removeClass('bg-primary progress-bar-animated').addClass('bg-success').css('width', '100%');
                    $('#uploadPercentText').removeClass('badge-primary').addClass('badge-success').text('100%');
                    $('#uploadStatusText').html('<i class="fas fa-check-double text-success mr-1"></i> <strong class="text-success">Recording Uploaded & Call Saved!</strong>');
                    $('#uploadDoneBadge').removeClass('d-none');
                }
                alertBox.removeClass('d-none').addClass('alert-success').html('<i class="fas fa-check-circle mr-1"></i> ' + resp.message);
                btn.removeClass('btn-primary').addClass('btn-success').html('<i class="fas fa-check mr-1"></i> Saved! Updating Lead...');
                setTimeout(function() {
                    window.location.reload();
                }, 1500);
            } else {
                isCallLogSubmitting = false;
                btn.prop('disabled', false).removeClass('btn-success').addClass('btn-primary').html('<i class="fas fa-save mr-1"></i> Save Call Log & Update Lead');
                $('#uploadProgressContainer').addClass('d-none');
                alertBox.removeClass('d-none').addClass('alert-danger').html('<i class="fas fa-exclamation-triangle mr-1"></i> ' + resp.error);
            }
        },
        error: function(xhr) {
            isCallLogSubmitting = false;
            btn.prop('disabled', false).removeClass('btn-success').addClass('btn-primary').html('<i class="fas fa-save mr-1"></i> Save Call Log & Update Lead');
            $('#uploadProgressContainer').addClass('d-none');
            let errMsg = 'Network or server error while saving call data.';
            if (xhr.responseJSON && xhr.responseJSON.error) {
                errMsg = xhr.responseJSON.error;
            }
            alertBox.removeClass('d-none').addClass('alert-danger').html('<i class="fas fa-exclamation-triangle mr-1"></i> ' + errMsg);
        }
    });

    return false;
};

// Initialize listeners when jQuery and DOM are ready
document.addEventListener('DOMContentLoaded', function() {
    if (typeof $ === 'undefined') return;

    // Toggle Follow-up Date requirement
    $('#selectCallOutcome').on('change', function() {
        const outcome = $(this).val();
        if (outcome === 'Follow-up Required' || outcome === 'Meeting Scheduled') {
            $('#groupFollowUpDate').removeClass('d-none');
            $('#inputFollowUpDate').prop('required', true);
        } else {
            $('#groupFollowUpDate').addClass('d-none');
            $('#inputFollowUpDate').prop('required', false);
        }
    });

    // Copy Phone button
    $('#btnCopyPhone').on('click', function() {
        if (window.currentOpenLeadPhone) {
            navigator.clipboard.writeText(window.currentOpenLeadPhone).then(function() {
                alert('Phone number copied to clipboard: ' + window.currentOpenLeadPhone);
            });
        }
    });

    // Audio duration auto-detect and preview player
    $('#modalAudioFile').on('change', function() {
        const file = this.files[0];
        if (!file) {
            $('#modalAudioPreviewBox').addClass('d-none');
            return;
        }

        let fileName = $(this).val().split('\\').pop();
        $(this).next('.custom-file-label').addClass('selected').html(fileName);

        // Update preview details
        $('#modalAudioFileName').text(fileName);
        const sizeStr = file.size > 1048576 
            ? (file.size / 1048576).toFixed(2) + ' MB'
            : Math.round(file.size / 1024) + ' KB';
        $('#modalAudioFileSize').text(sizeStr);
        $('#modalAudioPreviewBox').removeClass('d-none');

        const previewPlayer = document.getElementById('modalAudioPreviewPlayer');
        const objectUrl = URL.createObjectURL(file);
        if (previewPlayer) {
            previewPlayer.src = objectUrl;
        }

        const tempAudio = new Audio();
        tempAudio.src = objectUrl;
        tempAudio.addEventListener('loadedmetadata', function() {
            if (!isNaN(tempAudio.duration) && isFinite(tempAudio.duration)) {
                const sec = Math.round(tempAudio.duration);
                const m = Math.floor(sec / 60);
                const s = sec % 60;
                $('#inputCallDuration').val(String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0'));
            }
        });
    });
});
</script>
