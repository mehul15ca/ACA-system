<?php
require_once "../config.php";

// 1) Generate PDF (your existing function)
$pdfPath = generateDailyAttendancePDF(); 

// 2) Insert row into notifications queue
$payload = [
    'report_date' => date('Y-m-d'),
    'admin_name' => 'Admin'
];

$payload_json = $conn->real_escape_string(json_encode($payload));

$conn->query("
INSERT INTO notifications_queue 
(receiver_email, cc_email, channel, subject, message, template_code, payload_json, status)
VALUES (
    'admin@aca.com',
    NULL,
    'email',
    'Daily Attendance Report',
    '',
    'DAILY_ATTENDANCE_REPORT',
    '$payload_json',
    'pending'
)");
