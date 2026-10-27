<?php
/**
 * Demo Data Seeder for Telecaller Reporting App & Lead CRM
 * Generates sample past reports, valid audio recordings, and realistic assigned leads
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';

function generate_demo_wav(string $filepath, int $durationSec = 3, int $freq = 440): void {
    $dir = dirname($filepath);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $sampleRate = 8000;
    $numSamples = $sampleRate * $durationSec;
    $byteRate = $sampleRate * 2;
    $dataChunkSize = $numSamples * 2;
    $fileSize = 36 + $dataChunkSize;

    $header = "RIFF" . pack("V", $fileSize) . "WAVEfmt " . pack("V", 16) . pack("v", 1) . pack("v", 1) . pack("V", $sampleRate) . pack("V", $byteRate) . pack("v", 2) . pack("v", 16) . "data" . pack("V", $dataChunkSize);

    $samples = "";
    for ($i = 0; $i < $numSamples; $i++) {
        $t = $i / $sampleRate;
        $val = (int)((sin(2 * M_PI * $freq * $t) * 0.7 + sin(2 * M_PI * ($freq * 1.25) * $t) * 0.3) * 10000);
        $samples .= pack("v", ($val < 0 ? $val + 65536 : $val));
    }

    file_put_contents($filepath, $header . $samples);
}

$db = get_db();

// 1. Fetch Users
$stmt = $db->query("SELECT id, employee_id, name FROM users WHERE role = 'employee'");
$employees = $stmt->fetchAll();

if (empty($employees)) {
    echo "No employees found to seed.\n";
    exit;
}

$emp1 = null; // Rahul EMP001
$emp2 = null; // Priya EMP002
foreach ($employees as $e) {
    if ($e['employee_id'] === 'EMP001') $emp1 = $e;
    if ($e['employee_id'] === 'EMP002') $emp2 = $e;
}

$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));
$day2 = date('Y-m-d', strtotime('-2 days'));
$day3 = date('Y-m-d', strtotime('-3 days'));

// 2. Clear old demo data
$db->exec("DELETE FROM daily_reports");
$db->exec("DELETE FROM call_recordings");
$db->exec("DELETE FROM lead_call_logs");
$db->exec("DELETE FROM attendance");
$db->exec("DELETE FROM leads");

// 3. Seed Past Reports for EMP001 (Rahul)
$rahulReports = [
    [
        'date' => $today,
        'calls' => 54, 'attempts' => 38, 'interested' => 9, 'meetings' => 4, 'sales' => 2,
        'notes' => 'Strong response from healthcare sector campaign. Two annual CRM subscriptions closed.'
    ],
    [
        'date' => $yesterday,
        'calls' => 62, 'attempts' => 44, 'interested' => 12, 'meetings' => 5, 'sales' => 1,
        'notes' => 'Followed up on pipeline leads. Demo booked for tomorrow 3 PM.'
    ],
    [
        'date' => $day2,
        'calls' => 48, 'attempts' => 35, 'interested' => 7, 'meetings' => 3, 'sales' => 1,
        'notes' => 'Cold calling round on newly imported trade database.'
    ],
    [
        'date' => $day3,
        'calls' => 50, 'attempts' => 32, 'interested' => 8, 'meetings' => 2, 'sales' => 0,
        'notes' => 'Multiple requests for follow-up callbacks over the weekend.'
    ]
];

$insertRep = $db->prepare("
    INSERT INTO daily_reports (user_id, report_date, total_calls, call_attempts, interested_leads, meetings_booked, sales_closed, notes)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
");

$insertRec = $db->prepare("
    INSERT INTO call_recordings (
        report_id, user_id, phone_number, customer_name, call_status, call_duration, call_notes,
        file_path, file_name, original_name, file_size, file_mime
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

if ($emp1) {
    foreach ($rahulReports as $rData) {
        $insertRep->execute([
            $emp1['id'], $rData['date'], $rData['calls'], $rData['attempts'],
            $rData['interested'], $rData['meetings'], $rData['sales'], $rData['notes']
        ]);
        $reportId = (int)$db->lastInsertId();

        $rec1Name = "rec_EMP001_" . strtotime($rData['date']) . "_call1.wav";
        $rec1RelPath = "uploads/recordings/EMP001/{$rData['date']}/" . $rec1Name;
        $rec1FullPath = ROOT_PATH . '/' . $rec1RelPath;
        generate_demo_wav($rec1FullPath, 4, 440);

        $insertRec->execute([
            $reportId, $emp1['id'], '+91 98201 12345', 'Anand Mahindra Enterprises',
            'Sales Closed', '04:12', 'Discussed premium annual enterprise tier. Client confirmed purchase.',
            $rec1RelPath, $rec1Name, 'anand_call_recording.wav', filesize($rec1FullPath), 'audio/wav'
        ]);

        $rec2Name = "rec_EMP001_" . strtotime($rData['date']) . "_call2.wav";
        $rec2RelPath = "uploads/recordings/EMP001/{$rData['date']}/" . $rec2Name;
        $rec2FullPath = ROOT_PATH . '/' . $rec2RelPath;
        generate_demo_wav($rec2FullPath, 3, 520);

        $insertRec->execute([
            $reportId, $emp1['id'], '+91 98110 54321', 'Kavita Verma',
            'Meeting Scheduled', '02:45', 'Product presentation scheduled for Friday 11:00 AM.',
            $rec2RelPath, $rec2Name, 'kavita_demo_booking.wav', filesize($rec2FullPath), 'audio/wav'
        ]);
    }
}

// 4. Seed Past Reports for EMP002 (Priya)
$priyaReports = [
    [
        'date' => $yesterday,
        'calls' => 58, 'attempts' => 40, 'interested' => 11, 'meetings' => 4, 'sales' => 2,
        'notes' => 'Great conversation with retail chain owners in Mumbai.'
    ],
    [
        'date' => $day2,
        'calls' => 51, 'attempts' => 36, 'interested' => 9, 'meetings' => 3, 'sales' => 1,
        'notes' => 'Pitched marketing automation add-on package.'
    ],
    [
        'date' => $day3,
        'calls' => 45, 'attempts' => 30, 'interested' => 6, 'meetings' => 2, 'sales' => 1,
        'notes' => 'Good progress on warm outbound callbacks.'
    ]
];

if ($emp2) {
    foreach ($priyaReports as $rData) {
        $insertRep->execute([
            $emp2['id'], $rData['date'], $rData['calls'], $rData['attempts'],
            $rData['interested'], $rData['meetings'], $rData['sales'], $rData['notes']
        ]);
        $reportId = (int)$db->lastInsertId();

        $recName = "rec_EMP002_" . strtotime($rData['date']) . "_call.wav";
        $recRelPath = "uploads/recordings/EMP002/{$rData['date']}/" . $recName;
        $recFullPath = ROOT_PATH . '/' . $recRelPath;
        generate_demo_wav($recFullPath, 3, 580);

        $insertRec->execute([
            $reportId, $emp2['id'], '+91 98765 88990', 'Suresh Menon & Sons',
            'Interested', '03:15', 'Client requested commercial quotation and SLA document on email.',
            $recRelPath, $recName, 'suresh_menon_pitch.wav', filesize($recFullPath), 'audio/wav'
        ]);
    }
}

// 5. Seed Realistic CRM Leads & Assignments
$leadsData = [
    // Assigned to Rahul (EMP001)
    [
        'name' => 'Rajesh Malhotra', 'phone' => '+919811223344', 'company' => 'Apex Industrial Corp',
        'email' => 'rajesh@apexcorp.com', 'city' => 'Mumbai', 'notes' => 'Interested in enterprise cloud ERP solutions',
        'assigned_to' => $emp1['id'], 'status' => 'Follow-up Required', 'attempts' => 2,
        'last_called' => date('Y-m-d 10:15:00'), 'follow_up' => date('Y-m-d 16:30:00') // Due Today!
    ],
    [
        'name' => 'Sunita Rao', 'phone' => '+919822334455', 'company' => 'Zenith Healthcare Ltd',
        'email' => 'sunita@zenithhealth.org', 'city' => 'Bangalore', 'notes' => 'Hospital management software procurement',
        'assigned_to' => $emp1['id'], 'status' => 'Interested', 'attempts' => 1,
        'last_called' => date('Y-m-d 11:30:00'), 'follow_up' => date('Y-m-d 14:00:00', strtotime('+1 day'))
    ],
    [
        'name' => 'Vikram Singhania', 'phone' => '+919833445566', 'company' => 'Singhania Logistics Pvt',
        'email' => 'vikram@singhanialogistics.in', 'city' => 'Delhi', 'notes' => 'Fleet tracking and CRM integration',
        'assigned_to' => $emp1['id'], 'status' => 'Sales Closed', 'attempts' => 3,
        'last_called' => date('Y-m-d 12:45:00'), 'follow_up' => null
    ],
    [
        'name' => 'Neha Kapoor', 'phone' => '+919844556677', 'company' => 'Kapoor Retail Chains',
        'email' => 'neha@kapoorretail.com', 'city' => 'Pune', 'notes' => 'Retail POS integration lead',
        'assigned_to' => $emp1['id'], 'status' => 'New', 'attempts' => 0,
        'last_called' => null, 'follow_up' => null
    ],

    // Assigned to Priya (EMP002)
    [
        'name' => 'Amitabh Joshi', 'phone' => '+919855667788', 'company' => 'SmartTech Innovations',
        'email' => 'amitabh@smarttech.io', 'city' => 'Hyderabad', 'notes' => 'Looking for outbound calling automation',
        'assigned_to' => $emp2['id'], 'status' => 'Follow-up Required', 'attempts' => 1,
        'last_called' => date('Y-m-d 09:30:00'), 'follow_up' => date('Y-m-d 15:00:00') // Due Today!
    ],
    [
        'name' => 'Pooja Deshmukh', 'phone' => '+919866778899', 'company' => 'Deshmukh Financial Advisors',
        'email' => 'pooja@deshmukhfin.com', 'city' => 'Ahmedabad', 'notes' => 'Lead qualification for financial CRM',
        'assigned_to' => $emp2['id'], 'status' => 'Meeting Scheduled', 'attempts' => 2,
        'last_called' => date('Y-m-d 11:00:00'), 'follow_up' => date('Y-m-d 11:00:00', strtotime('+2 days'))
    ],
    [
        'name' => 'Karan Mehra', 'phone' => '+919877889900', 'company' => 'Mehra Export House',
        'email' => 'karan@mehraexports.com', 'city' => 'Surat', 'notes' => 'Textile export manufacturer lead',
        'assigned_to' => $emp2['id'], 'status' => 'Ringing', 'attempts' => 1,
        'last_called' => date('Y-m-d 10:00:00'), 'follow_up' => null
    ],
    [
        'name' => 'Ritu Verma', 'phone' => '+919888990011', 'company' => 'Verma Consulting',
        'email' => 'ritu@vermaconsult.com', 'city' => 'Chennai', 'notes' => 'Fresh website inquiry lead',
        'assigned_to' => $emp2['id'], 'status' => 'New', 'attempts' => 0,
        'last_called' => null, 'follow_up' => null
    ],

    // Unassigned Leads
    [
        'name' => 'Deepak Chawla', 'phone' => '+919899001122', 'company' => 'Chawla Electronics',
        'email' => 'deepak@chawlaelec.com', 'city' => 'Kolkata', 'notes' => 'Imported trade fair lead',
        'assigned_to' => null, 'status' => 'New', 'attempts' => 0,
        'last_called' => null, 'follow_up' => null
    ],
    [
        'name' => 'Ananya Sen', 'phone' => '+919800112233', 'company' => 'Horizon Media Pvt Ltd',
        'email' => 'ananya@horizonmedia.in', 'city' => 'Jaipur', 'notes' => 'Digital ad agency prospect',
        'assigned_to' => null, 'status' => 'New', 'attempts' => 0,
        'last_called' => null, 'follow_up' => null
    ]
];

$insLead = $db->prepare("
    INSERT INTO leads (name, phone, company, email, city, notes, assigned_to, status, call_attempts, last_called_at, follow_up_date, created_by)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
");

$insLeadLog = $db->prepare("
    INSERT INTO lead_call_logs (lead_id, user_id, call_outcome, call_duration, follow_up_date, remarks, recording_path, recording_name, recording_size, recording_mime, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

foreach ($leadsData as $ld) {
    $insLead->execute([
        $ld['name'], $ld['phone'], $ld['company'], $ld['email'], $ld['city'], $ld['notes'],
        $ld['assigned_to'], $ld['status'], $ld['attempts'], $ld['last_called'], $ld['follow_up']
    ]);
    $leadId = (int)$db->lastInsertId();

    // If attempts > 0, generate sample call logs
    if ($ld['attempts'] > 0 && $ld['assigned_to']) {
        $caller = ($ld['assigned_to'] === $emp1['id']) ? $emp1 : $emp2;
        $callWav = "rec_lead_{$leadId}_call.wav";
        $relPath = "uploads/recordings/{$caller['employee_id']}/{$today}/{$callWav}";
        $fullPath = ROOT_PATH . '/' . $relPath;
        generate_demo_wav($fullPath, 3, 480);

        $insLeadLog->execute([
            $leadId,
            $caller['id'],
            $ld['status'],
            '03:10',
            $ld['follow_up'],
            'Discussed lead requirements. Outcome marked as ' . $ld['status'] . '.',
            $relPath,
            $callWav,
            filesize($fullPath),
            'audio/wav',
            $ld['last_called'] ?: date('Y-m-d H:i:s')
        ]);
    }
}

// -------------------------------------------------------------
// 6. Seed Realistic Monthly Attendance Records for Telecallers
// -------------------------------------------------------------
$adminUser = $db->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1")->fetch();
$adminId = $adminUser ? (int)$adminUser['id'] : 1;

$currentYear = (int)date('Y');
$currentMonth = (int)date('m');
$currentDay = (int)date('d');

$insAtt = $db->prepare("
    INSERT INTO attendance (user_id, attendance_date, status, punch_time, punch_ip, employee_remarks, is_modified, modified_by, modified_at, admin_remarks, created_at, updated_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$seededAttCount = 0;

for ($d = 1; $d < $currentDay; $d++) {
    $dateStr = sprintf('%04d-%02d-%02d', $currentYear, $currentMonth, $d);
    $dayOfWeek = date('N', strtotime($dateStr)); // 1 (Mon) to 7 (Sun)
    if ($dayOfWeek == 7) continue; // Skip Sundays

    // --- Rahul (EMP001) ---
    $rStatus = 'Present';
    $rRemarks = 'In-office calling shift';
    $rIsMod = 0;
    $rModBy = null;
    $rModAt = null;
    $rAdminRemarks = null;

    if ($d === 10) {
        $rStatus = 'Half Day';
        $rRemarks = 'Doctor appointment in afternoon';
    } elseif ($d === 18) {
        $rStatus = 'Absent';
        $rRemarks = 'Personal leave';
    } elseif ($d === 14) {
        $rStatus = 'Present';
        $rRemarks = 'Missed morning punch on portal';
        $rIsMod = 1;
        $rModBy = $adminId;
        $rModAt = $dateStr . ' 17:30:00';
        $rAdminRemarks = 'Verified call logs; attendance confirmed by Admin';
    }

    $rTime = sprintf('%s %02d:%02d:00', $dateStr, rand(8, 9), rand(10, 50));
    $insAtt->execute([
        $emp1['id'], $dateStr, $rStatus, $rTime, '192.168.1.101', $rRemarks,
        $rIsMod, $rModBy, $rModAt, $rAdminRemarks, $rTime, $rTime
    ]);
    $seededAttCount++;

    // --- Priya (EMP002) ---
    $pStatus = 'Present';
    $pRemarks = 'Standard shift';
    $pIsMod = 0;
    $pModBy = null;
    $pModAt = null;
    $pAdminRemarks = null;

    if ($d === 6) {
        $pStatus = 'Half Day';
        $pRemarks = 'Family commitment';
    } elseif ($d === 12) {
        $pStatus = 'Absent';
        $pRemarks = 'Medical sick leave';
    } elseif ($d === 22) {
        $pStatus = 'Half Day';
        $pRemarks = 'Half day client visit';
    }

    $pTime = sprintf('%s %02d:%02d:00', $dateStr, rand(8, 9), rand(15, 55));
    $insAtt->execute([
        $emp2['id'], $dateStr, $pStatus, $pTime, '192.168.1.102', $pRemarks,
        $pIsMod, $pModBy, $pModAt, $pAdminRemarks, $pTime, $pTime
    ]);
    $seededAttCount++;
}

echo "Successfully seeded demo reports, CRM leads, and attendance data!\n";
echo "- Total Attendance Records Seeded: {$seededAttCount}\n";
echo "- Rahul (EMP001): Monthly attendance active (Today left unmarked for popup check-in)\n";
echo "- Priya (EMP002): Monthly attendance active (Today left unmarked for popup check-in)\n";
echo "- Total Leads Seeded: " . count($leadsData) . "\n";

