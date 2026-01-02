<?php
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit;
}

if (date('w') != 0) exit; // only Sunday

require_once "../config.php";

$payload = generateWeeklyFeesPayload(); // create function in attendance module

$payload_json = $conn->real_escape_string(json_encode($payload));

$conn->query("
INSERT INTO notifications_queue 
(receiver_email, template_code, payload_json, status)
VALUES (
    'admin@aca.com',
    'DAILY_ATTENDANCE_REPORT',
    '$payload_json',
    'pending'
)");
