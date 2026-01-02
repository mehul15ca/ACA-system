<?php
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit;
}

if (date('d') != "01") exit;

require_once "../config.php";

$payload = generateSystemLogsSummary();

$payload_json = $conn->real_escape_string(json_encode($payload));

$conn->query("
INSERT INTO notifications_queue
(receiver_email, template_code, payload_json, status)
VALUES (
    'superadmin@aca.com',
    'MONTHLY_SYSTEM_LOGS',
    '$payload_json',
    'pending'
)");
