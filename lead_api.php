<?php
/**
 * Lead API Endpoint
 * Handles fetching lead details and logging call attempts with audio recordings
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

require_login();

$db = get_db();
$currentUser = current_user();
$userId = $currentUser['id'];
$userRole = $currentUser['role'];
$employeeCode = $currentUser['employee_id'];

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ==========================================================
// 1. GET LEAD DETAILS & CALL HISTORY
// ==========================================================
if ($action === 'get_lead') {
    $leadId = (int)($_GET['id'] ?? 0);
    if ($leadId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid lead ID.']);
        exit;
    }

    $leadStmt = $db->prepare("
        SELECT l.*, u.name AS assigned_to_name, u.employee_id AS assigned_to_code
        FROM leads l
        LEFT JOIN users u ON u.id = l.assigned_to
        WHERE l.id = ?
        LIMIT 1
    ");
    $leadStmt->execute([$leadId]);
    $lead = $leadStmt->fetch();

    if (!$lead) {
        echo json_encode(['success' => false, 'error' => 'Lead not found.']);
        exit;
    }

    // Role check: If employee, verify lead is assigned to them (or allow viewing if unassigned/shared)
    if ($userRole === 'employee' && (int)$lead['assigned_to'] !== $userId) {
        // Telecallers only work on their assigned leads
        echo json_encode(['success' => false, 'error' => 'You do not have permission to access this lead.']);
        exit;
    }

    // Fetch complete call logs for this lead
    $logsStmt = $db->prepare("
        SELECT cl.*, u.name AS caller_name, u.employee_id AS caller_code
        FROM lead_call_logs cl
        JOIN users u ON u.id = cl.user_id
        WHERE cl.lead_id = ?
        ORDER BY cl.id DESC
    ");
    $logsStmt->execute([$leadId]);
    $callLogs = $logsStmt->fetchAll();

    // Format logs for frontend
    $formattedLogs = [];
    $baseUrl = get_base_url();
    foreach ($callLogs as $log) {
        $formattedLogs[] = [
            'id'             => $log['id'],
            'caller_name'    => $log['caller_name'],
            'caller_code'    => $log['caller_code'],
            'call_outcome'   => $log['call_outcome'],
            'badge_html'     => get_call_status_badge($log['call_outcome']),
            'call_duration'  => $log['call_duration'] ?: '--:--',
            'follow_up_date' => $log['follow_up_date'] ? date('d M, Y h:i A', strtotime($log['follow_up_date'])) : null,
            'remarks'        => $log['remarks'],
            'recording_url'  => $log['recording_path'] ? $baseUrl . '/' . $log['recording_path'] : null,
            'recording_name' => $log['recording_name'],
            'recording_size' => $log['recording_size'] ? format_file_size((int)$log['recording_size']) : null,
            'created_at'     => date('d M, Y h:i A', strtotime($log['created_at']))
        ];
    }

    echo json_encode([
        'success'   => true,
        'lead'      => [
            'id'               => $lead['id'],
            'name'             => $lead['name'],
            'phone'            => $lead['phone'],
            'company'          => $lead['company'] ?: '',
            'email'            => $lead['email'] ?: '',
            'city'             => $lead['city'] ?: '',
            'notes'            => $lead['notes'] ?: '',
            'status'           => $lead['status'],
            'status_badge'     => get_call_status_badge($lead['status']),
            'call_attempts'    => (int)$lead['call_attempts'],
            'last_called_at'   => $lead['last_called_at'] ? date('d M, Y h:i A', strtotime($lead['last_called_at'])) : 'Never',
            'follow_up_date'   => $lead['follow_up_date'] ? date('Y-m-d\TH:i', strtotime($lead['follow_up_date'])) : '',
            'follow_up_badge'  => format_follow_up_badge($lead['follow_up_date']),
            'assigned_to_name' => $lead['assigned_to_name'] ?: 'Unassigned'
        ],
        'call_logs' => $formattedLogs
    ]);
    exit;
}

// ==========================================================
// 2. LOG CALL ATTEMPT & OUTCOME
// ==========================================================
if ($action === 'log_call') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'error' => 'Security token expired. Please refresh the page.']);
        exit;
    }

    $leadId       = (int)($_POST['lead_id'] ?? 0);
    $callOutcome  = clean_string($_POST['call_outcome'] ?? '');
    $callDuration = clean_string($_POST['call_duration'] ?? '');
    $followUpDate = clean_string($_POST['follow_up_date'] ?? '');
    $remarks      = clean_string($_POST['remarks'] ?? '');

    if ($leadId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid lead specified.']);
        exit;
    }

    global $CALL_STATUS_OPTIONS;
    if (!isset($CALL_STATUS_OPTIONS[$callOutcome]) || $callOutcome === 'New') {
        echo json_encode(['success' => false, 'error' => 'Please select a valid call outcome.']);
        exit;
    }

    // If Follow-up Required is selected, Follow-up date is MANDATORY
    if ($callOutcome === 'Follow-up Required' && empty($followUpDate)) {
        echo json_encode(['success' => false, 'error' => 'Follow-up Date & Time is mandatory when "Follow-up Required" is selected.']);
        exit;
    }

    // Format follow-up date for SQL (YYYY-MM-DD HH:MM:SS)
    $sqlFollowUp = null;
    if (!empty($followUpDate)) {
        $ts = strtotime($followUpDate);
        if ($ts) {
            $sqlFollowUp = date('Y-m-d H:i:s', $ts);
        }
    }

    // Verify lead existence and permission
    $checkStmt = $db->prepare("SELECT * FROM leads WHERE id = ? LIMIT 1");
    $checkStmt->execute([$leadId]);
    $lead = $checkStmt->fetch();

    if (!$lead) {
        echo json_encode(['success' => false, 'error' => 'Lead does not exist.']);
        exit;
    }

    if ($userRole === 'employee' && (int)$lead['assigned_to'] !== $userId) {
        echo json_encode(['success' => false, 'error' => 'You are not assigned to this lead.']);
        exit;
    }

    // Handle Call Recording Audio Upload if provided
    $recordingPath = null;
    $recordingName = null;
    $recordingSize = 0;
    $recordingMime = null;

    if (isset($_FILES['audio_file']) && $_FILES['audio_file']['error'] === UPLOAD_ERR_OK) {
        $uploadResult = save_call_recording($_FILES['audio_file'], $employeeCode, date('Y-m-d'));
        if (!$uploadResult['success']) {
            echo json_encode(['success' => false, 'error' => 'Recording upload failed: ' . $uploadResult['error']]);
            exit;
        }
        $recordingPath = $uploadResult['file_path'];
        $recordingName = $uploadResult['original_name'];
        $recordingSize = $uploadResult['file_size'];
        $recordingMime = $uploadResult['file_mime'];
    }

    try {
        // Begin transaction
        $db->beginTransaction();

        // 1. Insert into lead_call_logs
        $logStmt = $db->prepare("
            INSERT INTO lead_call_logs (
                lead_id, user_id, call_outcome, call_duration, follow_up_date, remarks,
                recording_path, recording_name, recording_size, recording_mime
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $logStmt->execute([
            $leadId,
            $userId,
            $callOutcome,
            $callDuration,
            $sqlFollowUp,
            $remarks,
            $recordingPath,
            $recordingName,
            $recordingSize,
            $recordingMime
        ]);

        // 2. Update leads table
        $updateLead = $db->prepare("
            UPDATE leads
            SET call_attempts = call_attempts + 1,
                status = ?,
                last_called_at = NOW(),
                last_call_status = ?,
                follow_up_date = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $updateLead->execute([$callOutcome, $callOutcome, $sqlFollowUp, $leadId]);

        // 3. Sync with today's daily_report if telecaller has a report or create/update
        $todayDate = date('Y-m-d');
        $reportCheck = $db->prepare("SELECT id FROM daily_reports WHERE user_id = ? AND report_date = ? LIMIT 1");
        $reportCheck->execute([$userId, $todayDate]);
        $existingReportId = $reportCheck->fetchColumn();

        $isInterested = ($callOutcome === 'Interested') ? 1 : 0;
        $isMeeting    = ($callOutcome === 'Meeting Scheduled') ? 1 : 0;
        $isSale       = ($callOutcome === 'Sales Closed') ? 1 : 0;
        $isConnected  = is_call_connected($callOutcome);
        $attemptCount = $isConnected ? 0 : 1; // if unanswered/busy, counts towards attempt

        if ($existingReportId) {
            $repUpdate = $db->prepare("
                UPDATE daily_reports
                SET total_calls = total_calls + 1,
                    call_attempts = call_attempts + ?,
                    interested_leads = interested_leads + ?,
                    meetings_booked = meetings_booked + ?,
                    sales_closed = sales_closed + ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $repUpdate->execute([$attemptCount, $isInterested, $isMeeting, $isSale, $existingReportId]);
        } else {
            $repInsert = $db->prepare("
                INSERT INTO daily_reports (user_id, report_date, total_calls, call_attempts, interested_leads, meetings_booked, sales_closed)
                VALUES (?, ?, 1, ?, ?, ?, ?)
            ");
            $repInsert->execute([$userId, $todayDate, $attemptCount, $isInterested, $isMeeting, $isSale]);
            $existingReportId = $db->lastInsertId();
        }

        // If recording was uploaded, also register in call_recordings for date-wise reporting
        if ($recordingPath && $existingReportId) {
            $recStmt = $db->prepare("
                INSERT INTO call_recordings (
                    report_id, user_id, phone_number, customer_name, call_status, call_duration, call_notes,
                    file_path, file_name, original_name, file_size, file_mime
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $recStmt->execute([
                $existingReportId,
                $userId,
                $lead['phone'],
                $lead['name'] . ($lead['company'] ? ' (' . $lead['company'] . ')' : ''),
                $callOutcome,
                $callDuration,
                $remarks,
                $recordingPath,
                basename($recordingPath),
                $recordingName ?: 'recording.wav',
                $recordingSize,
                $recordingMime ?: 'audio/wav'
            ]);
        }

        $db->commit();

        echo json_encode([
            'success'   => true,
            'message'   => "Call attempt logged successfully! Lead status updated to '{$callOutcome}'.",
            'lead_id'   => $leadId,
            'new_status'=> $callOutcome,
            'badge_html'=> get_call_status_badge($callOutcome)
        ]);
        exit;
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
}

echo json_encode(['success' => false, 'error' => 'Invalid action.']);
