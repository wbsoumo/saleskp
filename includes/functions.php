<?php
/**
 * Helper Functions
 * Telecaller Reporting Web App & Lead CRM
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';

/**
 * Escape HTML output for XSS prevention
 */
function e(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Clean & trim input string
 */
function clean_string(?string $val): string {
    return trim($val ?? '');
}

/**
 * Format Date (e.g., "27 Oct, 2026")
 */
function format_date(?string $date, string $format = 'd M, Y'): string {
    if (!$date) return '-';
    $timestamp = strtotime($date);
    return $timestamp ? date($format, $timestamp) : '-';
}

/**
 * Format Date & Time
 */
function format_datetime(?string $datetime, string $format = 'd M, Y h:i A'): string {
    if (!$datetime) return '-';
    $timestamp = strtotime($datetime);
    return $timestamp ? date($format, $timestamp) : '-';
}

/**
 * Format Follow-up Date with urgency indicator
 */
function format_follow_up_badge(?string $datetime): string {
    if (!$datetime) return '<span class="text-muted small">None</span>';
    $ts = strtotime($datetime);
    if (!$ts) return '-';

    $todayStart = strtotime('today midnight');
    $todayEnd   = strtotime('tomorrow midnight') - 1;
    $formatted  = date('d M, h:i A', $ts);

    if ($ts < $todayStart) {
        return '<span class="badge badge-danger text-uppercase font-weight-bold mr-1"><i class="fas fa-exclamation-triangle mr-1"></i>Overdue</span> <small class="text-danger font-weight-bold">' . e($formatted) . '</small>';
    } elseif ($ts >= $todayStart && $ts <= $todayEnd) {
        return '<span class="badge badge-warning text-uppercase font-weight-bold mr-1"><i class="fas fa-bell mr-1"></i>Today</span> <strong class="text-dark">' . e(date('h:i A', $ts)) . '</strong>';
    } else {
        return '<span class="badge badge-light border text-muted mr-1"><i class="far fa-clock mr-1"></i>Upcoming</span> <small class="text-muted">' . e($formatted) . '</small>';
    }
}

/**
 * Format file size in KB or MB
 */
function format_file_size(int $bytes): string {
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 1) . ' KB';
    }
    return $bytes . ' B';
}

/**
 * Format phone number display
 */
function format_phone(?string $phone): string {
    if (!$phone) return '-';
    return e(trim($phone));
}

/**
 * Clean phone number for dialing/storing (removes spaces, dashes, brackets)
 */
function sanitize_phone(?string $phone): string {
    if (!$phone) return '';
    return preg_replace('/[^\+0-9]/', '', trim($phone));
}

/**
 * Generate CSRF Token
 */
function generate_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF Token
 */
function verify_csrf_token(?string $token): bool {
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Output hidden CSRF input field
 */
function csrf_field(): string {
    $token = generate_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . e($token) . '">';
}

/**
 * Return badge HTML for call status
 */
function get_call_status_badge(string $status): string {
    global $CALL_STATUS_OPTIONS;
    $info = $CALL_STATUS_OPTIONS[$status] ?? ['label' => $status, 'badge' => 'badge-secondary', 'icon' => 'fa-tag'];
    $icon = $info['icon'] ?? 'fa-tag';
    return sprintf('<span class="badge %s px-2 py-1"><i class="fas %s mr-1"></i>%s</span>', e($info['badge']), e($icon), e($info['label']));
}

/**
 * Validate audio upload file
 */
function validate_audio_upload(array $file): array {
    global $ALLOWED_AUDIO_EXTENSIONS, $ALLOWED_AUDIO_MIMES;

    if (!isset($file['error']) || is_array($file['error'])) {
        return ['valid' => false, 'error' => 'Invalid file upload parameters.'];
    }

    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            return ['valid' => false, 'error' => 'No file was uploaded. Please choose an audio recording.'];
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return ['valid' => false, 'error' => 'Uploaded file exceeds server upload size limit.'];
        default:
            return ['valid' => false, 'error' => 'Unknown file upload error (code: ' . $file['error'] . ').'];
    }

    $maxBytes = MAX_UPLOAD_SIZE_BYTES;
    if ($file['size'] > $maxBytes) {
        return ['valid' => false, 'error' => 'File size exceeds maximum allowed limit of ' . MAX_UPLOAD_SIZE_MB . ' MB.'];
    }

    if ($file['size'] === 0) {
        return ['valid' => false, 'error' => 'Uploaded file is empty.'];
    }

    $originalName = $file['name'];
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($ext, $ALLOWED_AUDIO_EXTENSIONS, true)) {
        return [
            'valid' => false,
            'error' => 'Invalid file extension (.' . e($ext) . '). Allowed audio formats: ' . implode(', ', $ALLOWED_AUDIO_EXTENSIONS)
        ];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $detectedMime = $finfo->file($file['tmp_name']);

    $isValidMime = in_array($detectedMime, $ALLOWED_AUDIO_MIMES, true) ||
                   str_starts_with($detectedMime, 'audio/') ||
                   str_starts_with($detectedMime, 'video/mp4') ||
                   str_starts_with($detectedMime, 'video/webm') ||
                   ($detectedMime === 'application/octet-stream' && in_array($ext, $ALLOWED_AUDIO_EXTENSIONS, true));

    if (!$isValidMime) {
        return [
            'valid' => false,
            'error' => 'Invalid file MIME type (' . e($detectedMime) . '). Please upload a valid audio recording.'
        ];
    }

    if ($detectedMime === 'application/octet-stream') {
        $mimeMap = [
            'wav'  => 'audio/wav',
            'mp3'  => 'audio/mpeg',
            'm4a'  => 'audio/mp4',
            'ogg'  => 'audio/ogg',
            'aac'  => 'audio/aac',
            'amr'  => 'audio/amr',
            'webm' => 'audio/webm'
        ];
        $detectedMime = $mimeMap[$ext] ?? 'audio/mpeg';
    }

    return [
        'valid' => true,
        'error' => null,
        'ext'   => $ext,
        'mime'  => $detectedMime
    ];
}

/**
 * Save recording file into organized directory structure:
 * uploads/recordings/{employee_code}/{YYYY-MM-DD}/{unique_name}.ext
 */
function save_call_recording(array $file, string $employeeCode, string $reportDate): array {
    $validation = validate_audio_upload($file);
    if (!$validation['valid']) {
        return ['success' => false, 'error' => $validation['error']];
    }

    $safeEmployeeCode = preg_replace('/[^a-zA-Z0-9_-]/', '', $employeeCode);
    if (empty($safeEmployeeCode)) {
        $safeEmployeeCode = 'emp_unknown';
    }

    $safeDate = preg_replace('/[^0-9-]/', '', $reportDate);
    if (empty($safeDate)) {
        $safeDate = date('Y-m-d');
    }

    $relativeDir = 'uploads/recordings/' . $safeEmployeeCode . '/' . $safeDate;
    $targetDir = ROOT_PATH . '/' . $relativeDir;

    if (!is_dir($targetDir)) {
        if (!mkdir($targetDir, 0755, true)) {
            return ['success' => false, 'error' => 'Failed to create recording directory on server. Please check folder permissions.'];
        }
    }

    $ext = $validation['ext'];
    $uniqueToken = bin2hex(random_bytes(6));
    $timestamp = time();
    $generatedFilename = sprintf('rec_%s_%s_%s.%s', $safeEmployeeCode, $timestamp, $uniqueToken, $ext);
    $targetFilePath = $targetDir . '/' . $generatedFilename;
    $relativeFilePath = $relativeDir . '/' . $generatedFilename;

    $moved = is_uploaded_file($file['tmp_name'])
        ? move_uploaded_file($file['tmp_name'], $targetFilePath)
        : (copy($file['tmp_name'], $targetFilePath) && (@unlink($file['tmp_name']) || true));

    if (!$moved) {
        return ['success' => false, 'error' => 'Failed to save uploaded file to destination directory.'];
    }

    chmod($targetFilePath, 0644);

    return [
        'success'        => true,
        'file_name'      => $generatedFilename,
        'original_name'  => basename($file['name']),
        'file_path'      => $relativeFilePath,
        'file_size'      => $file['size'],
        'file_mime'      => $validation['mime'],
        'ext'            => $ext
    ];
}

/**
 * Robust CSV/TSV Parser with Auto-Delimiter and Header Matching
 */
function parse_leads_file(string $filepath): array {
    if (!file_exists($filepath) || !is_readable($filepath)) {
        return ['success' => false, 'error' => 'Unable to read uploaded file.'];
    }

    $content = file_get_contents($filepath);
    // Remove UTF-8 BOM if present
    if (str_starts_with($content, "\xEF\xBB\xBF")) {
        $content = substr($content, 3);
    }

    // Auto-detect delimiter from first 3 lines
    $firstLine = strtok($content, "\r\n");
    $delimiters = [',', ';', "\t", '|'];
    $bestDelimiter = ',';
    $maxCount = 0;
    foreach ($delimiters as $delim) {
        $count = substr_count($firstLine, $delim);
        if ($count > $maxCount) {
            $maxCount = $count;
            $bestDelimiter = $delim;
        }
    }

    $handle = fopen('php://memory', 'r+');
    fwrite($handle, $content);
    rewind($handle);

    $rawHeader = fgetcsv($handle, 0, $bestDelimiter);
    if (!$rawHeader || count($rawHeader) === 0) {
        fclose($handle);
        return ['success' => false, 'error' => 'Uploaded file is empty or does not contain header columns.'];
    }

    // Normalize header map
    $headerMap = [];
    foreach ($rawHeader as $index => $col) {
        $cleanCol = strtolower(trim(preg_replace('/[^a-zA-Z0-9_]/', '', $col)));
        if (in_array($cleanCol, ['name', 'fullname', 'clientname', 'leadname', 'contactperson', 'customer'])) {
            $headerMap['name'] = $index;
        } elseif (in_array($cleanCol, ['phone', 'phonenumber', 'mobile', 'mobilenumber', 'contact', 'cell', 'tel'])) {
            $headerMap['phone'] = $index;
        } elseif (in_array($cleanCol, ['company', 'companyname', 'organization', 'org', 'firm', 'business'])) {
            $headerMap['company'] = $index;
        } elseif (in_array($cleanCol, ['email', 'emailaddress', 'mail'])) {
            $headerMap['email'] = $index;
        } elseif (in_array($cleanCol, ['city', 'location', 'state', 'address'])) {
            $headerMap['city'] = $index;
        } elseif (in_array($cleanCol, ['notes', 'remarks', 'description', 'comments'])) {
            $headerMap['notes'] = $index;
        }
    }

    if (!isset($headerMap['phone'])) {
        fclose($handle);
        return [
            'success' => false,
            'error'   => 'Could not detect a "Phone" or "Mobile" column in the header. Please ensure your file has a Phone/Mobile column.'
        ];
    }

    // If 'name' is not mapped, default to column 0 or empty
    if (!isset($headerMap['name'])) {
        $headerMap['name'] = 0;
    }

    $leads = [];
    $lineNum = 1;
    while (($row = fgetcsv($handle, 0, $bestDelimiter)) !== false) {
        $lineNum++;
        if (count(array_filter($row)) === 0) continue; // Skip completely empty rows

        $phone = sanitize_phone($row[$headerMap['phone']] ?? '');
        if (empty($phone)) {
            continue; // Skip rows without phone numbers
        }

        $name = trim($row[$headerMap['name']] ?? 'Prospect');
        if (empty($name)) $name = 'Lead (' . $phone . ')';

        $company = isset($headerMap['company']) ? trim($row[$headerMap['company']] ?? '') : '';
        $email   = isset($headerMap['email']) ? trim($row[$headerMap['email']] ?? '') : '';
        $city    = isset($headerMap['city']) ? trim($row[$headerMap['city']] ?? '') : '';
        $notes   = isset($headerMap['notes']) ? trim($row[$headerMap['notes']] ?? '') : '';

        $leads[] = [
            'name'    => $name,
            'phone'   => $phone,
            'company' => $company,
            'email'   => $email,
            'city'    => $city,
            'notes'   => $notes
        ];
    }

    fclose($handle);
    return ['success' => true, 'leads' => $leads, 'total' => count($leads)];
}

/**
 * =========================================================================
 * ATTENDANCE MANAGEMENT SYSTEM HELPERS
 * =========================================================================
 */

/**
 * Get current working attendance date based on 7:00 AM daily cutoff
 */
function get_current_attendance_date(): string {
    $hour = (int)date('H');
    if ($hour < 7) {
        return date('Y-m-d', strtotime('-1 day'));
    }
    return date('Y-m-d');
}

/**
 * Check if employee has marked attendance for a given date
 */
function has_employee_marked_attendance(int $userId, ?string $date = null): bool {
    $date = $date ?? get_current_attendance_date();
    try {
        $stmt = get_db()->prepare("SELECT id FROM attendance WHERE user_id = ? AND attendance_date = ? LIMIT 1");
        $stmt->execute([$userId, $date]);
        return (bool)$stmt->fetchColumn();
    } catch (Exception $e) {
        error_log("has_employee_marked_attendance error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get employee attendance record for a specific date
 */
function get_employee_attendance_record(int $userId, ?string $date = null): ?array {
    $date = $date ?? get_current_attendance_date();
    try {
        $stmt = get_db()->prepare("
            SELECT a.*, u.name as employee_name, u.employee_id as emp_code,
                   m.name as modified_by_name
            FROM attendance a
            JOIN users u ON a.user_id = u.id
            LEFT JOIN users m ON a.modified_by = m.id
            WHERE a.user_id = ? AND a.attendance_date = ?
            LIMIT 1
        ");
        $stmt->execute([$userId, $date]);
        $row = $stmt->fetch();
        return $row ?: null;
    } catch (Exception $e) {
        error_log("get_employee_attendance_record error: " . $e->getMessage());
        return null;
    }
}

/**
 * Determine whether the mandatory daily attendance popup should be shown
 * Triggered when:
 * 1. User is an employee
 * 2. Employee has not marked attendance for the current working attendance date (reset daily at 7:00 AM)
 */
function should_show_attendance_popup(int $userId): bool {
    if (!is_employee()) {
        return false;
    }

    $workingDate = get_current_attendance_date();
    return !has_employee_marked_attendance($userId, $workingDate);
}

/**
 * Record attendance for an employee
 */
function record_employee_attendance(int $userId, string $status, ?string $remarks = null, ?string $date = null): array {
    $validStatuses = ['Present', 'Half Day', 'Absent'];
    if (!in_array($status, $validStatuses, true)) {
        return ['success' => false, 'error' => 'Invalid attendance status selected. Must be Present, Half Day, or Absent.'];
    }

    $date = $date ?? get_current_attendance_date();
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $punchTime = date('Y-m-d H:i:s');
    $cleanRemarks = !empty($remarks) ? trim($remarks) : null;

    try {
        $db = get_db();
        // Check if already submitted
        $stmtCheck = $db->prepare("SELECT id, status FROM attendance WHERE user_id = ? AND attendance_date = ? LIMIT 1");
        $stmtCheck->execute([$userId, $date]);
        $existing = $stmtCheck->fetch();

        if ($existing) {
            return [
                'success' => false,
                'error'   => 'Attendance for date ' . $date . ' has already been recorded (' . $existing['status'] . '). Duplicate submissions are not allowed.'
            ];
        }

        $stmt = $db->prepare("
            INSERT INTO attendance (user_id, attendance_date, status, punch_time, punch_ip, employee_remarks, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId,
            $date,
            $status,
            $punchTime,
            $ip,
            $cleanRemarks,
            $punchTime,
            $punchTime
        ]);

        return [
            'success' => true,
            'message' => 'Attendance for ' . date('d M Y', strtotime($date)) . ' marked as ' . $status . ' successfully!',
            'status'  => $status,
            'date'    => $date
        ];
    } catch (PDOException $e) {
        error_log("record_employee_attendance error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Database error recording attendance: ' . $e->getMessage()];
    }
}

/**
 * Admin correction of attendance with mandatory remarks and audit trail
 */
function admin_update_attendance(int $attendanceId, string $newStatus, string $remarks, int $adminId): array {
    $validStatuses = ['Present', 'Half Day', 'Absent'];
    if (!in_array($newStatus, $validStatuses, true)) {
        return ['success' => false, 'error' => 'Invalid attendance status selected.'];
    }

    $cleanRemarks = trim($remarks);
    if (empty($cleanRemarks)) {
        return ['success' => false, 'error' => 'Remarks are mandatory when correcting attendance records.'];
    }

    try {
        $db = get_db();
        $stmt = $db->prepare("
            UPDATE attendance
            SET status = ?,
                is_modified = 1,
                modified_by = ?,
                modified_at = ?,
                admin_remarks = ?,
                updated_at = ?
            WHERE id = ?
        ");
        $now = date('Y-m-d H:i:s');
        $stmt->execute([
            $newStatus,
            $adminId,
            $now,
            $cleanRemarks,
            $now,
            $attendanceId
        ]);

        return [
            'success' => true,
            'message' => 'Attendance record updated successfully to ' . $newStatus . '.'
        ];
    } catch (PDOException $e) {
        error_log("admin_update_attendance error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Failed to update attendance record: ' . $e->getMessage()];
    }
}

/**
 * Attendance status badge HTML
 */
function get_attendance_badge(string $status): string {
    switch ($status) {
        case 'Present':
            return '<span class="badge badge-success px-2 py-1"><i class="fas fa-check-circle mr-1"></i>Present</span>';
        case 'Half Day':
            return '<span class="badge badge-warning px-2 py-1 text-dark"><i class="fas fa-adjust mr-1"></i>Half Day</span>';
        case 'Absent':
            return '<span class="badge badge-danger px-2 py-1"><i class="fas fa-times-circle mr-1"></i>Absent</span>';
        default:
            return '<span class="badge badge-secondary px-2 py-1"><i class="fas fa-minus-circle mr-1"></i>' . e($status) . '</span>';
    }
}

/**
 * Monthly attendance summary for an employee
 */
function get_employee_attendance_summary(int $userId, ?string $month = null, ?string $year = null): array {
    $month = $month ?? date('m');
    $year = $year ?? date('Y');

    try {
        $db = get_db();
        $stmt = $db->prepare("
            SELECT
                COUNT(*) as total_records,
                SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_count,
                SUM(CASE WHEN status = 'Half Day' THEN 1 ELSE 0 END) as half_day_count,
                SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent_count
            FROM attendance
            WHERE user_id = ?
              AND strftime('%m', attendance_date) = ?
              AND strftime('%Y', attendance_date) = ?
        ");

        if (Database::getActiveDriver() === 'mysql') {
            $stmt = $db->prepare("
                SELECT
                    COUNT(*) as total_records,
                    SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_count,
                    SUM(CASE WHEN status = 'Half Day' THEN 1 ELSE 0 END) as half_day_count,
                    SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent_count
                FROM attendance
                WHERE user_id = ?
                  AND MONTH(attendance_date) = ?
                  AND YEAR(attendance_date) = ?
            ");
        }

        $stmt->execute([$userId, (int)$month, (int)$year]);
        $row = $stmt->fetch();

        $present = (int)($row['present_count'] ?? 0);
        $halfDay = (int)($row['half_day_count'] ?? 0);
        $absent  = (int)($row['absent_count'] ?? 0);
        $total   = (int)($row['total_records'] ?? 0);

        $effective = $present + ($halfDay * 0.5);
        $rate = $total > 0 ? round(($effective / $total) * 100, 1) : 0.0;

        return [
            'total_records'     => $total,
            'present_count'     => $present,
            'half_day_count'    => $halfDay,
            'absent_count'      => $absent,
            'effective_present' => $effective,
            'attendance_rate'   => $rate,
            'month'             => $month,
            'year'              => $year
        ];
    } catch (Exception $e) {
        error_log("get_employee_attendance_summary error: " . $e->getMessage());
        return [
            'total_records'     => 0,
            'present_count'     => 0,
            'half_day_count'    => 0,
            'absent_count'      => 0,
            'effective_present' => 0,
            'attendance_rate'   => 0.0,
            'month'             => $month,
            'year'              => $year
        ];
    }
}

