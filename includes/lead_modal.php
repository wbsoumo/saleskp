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

                        <form id="formLogCall" enctype="multipart/form-data">
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
                            <div class="form-group mb-4">
                                <label class="font-weight-bold text-secondary small">
                                    <i class="fas fa-microphone-alt text-danger mr-1"></i> Attach Call Recording (Optional)
                                </label>
                                <div class="custom-file">
                                    <input type="file" name="audio_file" class="custom-file-input" id="modalAudioFile" accept=".mp3,.wav,.m4a,.ogg,.aac,.amr,.webm,audio/*">
                                    <label class="custom-file-label" for="modalAudioFile">Choose audio file (mp3, wav, m4a, ogg)...</label>
                                </div>
                                <small class="text-muted">Max file size: <?= MAX_UPLOAD_SIZE_MB ?> MB</small>
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
let currentOpenLeadPhone = '';

function openLeadModal(leadId) {
    $('#logCallAlert').addClass('d-none').text('');
    $('#formLogCall')[0].reset();
    $('#modalAudioFile').next('.custom-file-label').html('Choose audio file (mp3, wav, m4a, ogg)...');
    $('#groupFollowUpDate').addClass('d-none');
    $('#inputFollowUpDate').prop('required', false);

    // Set lead ID
    $('#logLeadId').val(leadId);
    $('#leadModalName').text('Loading Lead #' + leadId + '...');
    $('#leadModalCompany').text('Please wait...');
    $('#leadModalStatusBadge').html('');
    $('#leadModalAttemptsBadge').text('...');
    $('#leadModalCallBtn').attr('href', 'tel:');
    $('#leadModalCallText').text('Loading...');

    $('#leadDetailsModal').modal('show');
    $('#tab-log-link').tab('show');

    // Fetch lead details via API
    $.ajax({
        url: '<?= get_base_url() ?>/lead_api.php?action=get_lead&id=' + leadId,
        type: 'GET',
        dataType: 'json',
        success: function(resp) {
            if (!resp.success) {
                alert(resp.error || 'Failed to load lead details.');
                $('#leadDetailsModal').modal('hide');
                return;
            }

            const l = resp.lead;
            currentOpenLeadPhone = l.phone;
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
            renderCallLogs(resp.call_logs);
        },
        error: function() {
            alert('Error communicating with server.');
            $('#leadDetailsModal').modal('hide');
        }
    });
}

function renderCallLogs(logs) {
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
}

// Dynamic toggle for Follow-up Date requirement
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
    if (currentOpenLeadPhone) {
        navigator.clipboard.writeText(currentOpenLeadPhone).then(function() {
            alert('Phone number copied to clipboard: ' + currentOpenLeadPhone);
        });
    }
});

// Auto-detect audio duration on file select
$('#modalAudioFile').on('change', function() {
    const file = this.files[0];
    if (!file) return;

    let fileName = $(this).val().split('\\').pop();
    $(this).next('.custom-file-label').addClass('selected').html(fileName);

    const tempAudio = new Audio();
    const objectUrl = URL.createObjectURL(file);
    tempAudio.src = objectUrl;
    tempAudio.addEventListener('loadedmetadata', function() {
        const sec = Math.round(tempAudio.duration);
        const m = Math.floor(sec / 60);
        const s = sec % 60;
        $('#inputCallDuration').val(String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0'));
        URL.revokeObjectURL(objectUrl);
    });
});

// AJAX Form Submit for Call Logging
$('#formLogCall').on('submit', function(e) {
    e.preventDefault();
    const btn = $('#btnSubmitLog');
    const alertBox = $('#logCallAlert');

    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Saving Call Log...');
    alertBox.addClass('d-none').removeClass('alert-success alert-danger');

    const formData = new FormData(this);

    $.ajax({
        url: '<?= get_base_url() ?>/lead_api.php',
        type: 'POST',
        data: formData,
        contentType: false,
        processData: false,
        dataType: 'json',
        success: function(resp) {
            btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Save Call Log & Update Lead');
            if (resp.success) {
                alertBox.removeClass('d-none').addClass('alert-success').html('<i class="fas fa-check-circle mr-1"></i> ' + resp.message);
                // Reload page after 1.2 seconds to reflect updated summary cards and table
                setTimeout(function() {
                    window.location.reload();
                }, 1200);
            } else {
                alertBox.removeClass('d-none').addClass('alert-danger').html('<i class="fas fa-exclamation-triangle mr-1"></i> ' + resp.error);
            }
        },
        error: function() {
            btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Save Call Log & Update Lead');
            alertBox.removeClass('d-none').addClass('alert-danger').html('<i class="fas fa-exclamation-triangle mr-1"></i> Network or server error.');
        }
    });
});
</script>
