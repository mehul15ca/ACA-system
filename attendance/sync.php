<?php
include "../config.php";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

if (!isset($_SESSION['attendance_ground_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Ground not logged in.']);
    exit;
}
$ground_id = intval($_SESSION['attendance_ground_id']);

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!$data || !isset($data['records']) || !is_array($data['records'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid payload.']);
    exit;
}

date_default_timezone_set('America/Toronto');

$inserted = 0;
foreach ($data['records'] as $rec) {
    if (!isset($rec['uid'])) continue;
    $uid = trim($rec['uid']);
    if ($uid === '') continue;

    $tapTime = isset($rec['ts']) ? strtotime($rec['ts']) : time();
    $log_date = date('Y-m-d', $tapTime);
    $log_time = date('H:i:s', $tapTime);

    // Find card
    $stmt = $conn->prepare("
        SELECT c.*, s.id AS student_id
        FROM cards c
        LEFT JOIN students s
            ON c.assigned_to_type = 'student' AND c.assigned_to_id = s.id
        WHERE c.uid = ? AND c.status = 'active'
    ");
    $stmt->bind_param("s", $uid);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) continue;
    $row = $res->fetch_assoc();
    if (!$row['student_id']) continue;

    $student_id = intval($row['student_id']);
    $card_id    = intval($row['id']);

    // Check duplicate
    $check = $conn->prepare("SELECT id FROM attendance_logs WHERE student_id = ? AND log_date = ?");
    $check->bind_param("is", $student_id, $log_date);
    $check->execute();
    $chkRes = $check->get_result();
    if ($chkRes->num_rows > 0) continue;

    $ins = $conn->prepare("
        INSERT INTO attendance_logs (student_id, card_id, ground_id, log_date, log_time, type)
        VALUES (?, ?, ?, ?, ?, 'IN')
    ");
    $ins->bind_param("iiiss", $student_id, $card_id, $ground_id, $log_date, $log_time);
    if ($ins->execute()) {
        $inserted++;
    }
}

echo json_encode(['status' => 'ok', 'inserted' => $inserted]);
