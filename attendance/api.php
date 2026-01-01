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
if (!$data || !isset($data['uid'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
    exit;
}
$uid = trim($data['uid']);
if ($uid === '') {
    echo json_encode(['status' => 'error', 'message' => 'Empty UID.']);
    exit;
}

date_default_timezone_set('America/Toronto');
$log_date = date('Y-m-d');
$log_time = date('H:i:s');

// Find card by UID
$stmt = $conn->prepare("
    SELECT c.*, s.id AS student_id, s.first_name, s.last_name,
           s.admission_no, b.name AS batch_name
    FROM cards c
    LEFT JOIN students s
        ON c.assigned_to_type = 'student' AND c.assigned_to_id = s.id
    LEFT JOIN batches b ON s.batch_id = b.id
    WHERE c.uid = ? AND c.status = 'active'
");
$stmt->bind_param("s", $uid);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Card not recognized.']);
    exit;
}
$row = $res->fetch_assoc();
if (!$row['student_id']) {
    echo json_encode(['status' => 'error', 'message' => 'Card not linked to a student.']);
    exit;
}
$student_id = intval($row['student_id']);
$card_id    = intval($row['id']);

// Prevent multiple INs in same day
$check = $conn->prepare("SELECT id FROM attendance_logs WHERE student_id = ? AND log_date = ?");
$check->bind_param("is", $student_id, $log_date);
$check->execute();
$chkRes = $check->get_result();
if ($chkRes->num_rows > 0) {
    echo json_encode([
        'status'        => 'duplicate',
        'message'       => 'Already marked today.',
        'first_name'    => $row['first_name'],
        'last_name'     => $row['last_name'],
        'student_name'  => $row['first_name'] . ' ' . $row['last_name'],
        'batch_name'    => $row['batch_name'],
        'admission_no'  => $row['admission_no'],
    ]);
    exit;
}

// Insert attendance
$ins = $conn->prepare("
    INSERT INTO attendance_logs (student_id, card_id, ground_id, log_date, log_time, type)
    VALUES (?, ?, ?, ?, ?, 'IN')
");
$ins->bind_param("iiiss", $student_id, $card_id, $ground_id, $log_date, $log_time);
if ($ins->execute()) {
    echo json_encode([
        'status'        => 'ok',
        'message'       => 'Attendance marked.',
        'first_name'    => $row['first_name'],
        'last_name'     => $row['last_name'],
        'student_name'  => $row['first_name'] . ' ' . $row['last_name'],
        'batch_name'    => $row['batch_name'],
        'admission_no'  => $row['admission_no'],
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'DB error: ' . $conn->error]);
}
