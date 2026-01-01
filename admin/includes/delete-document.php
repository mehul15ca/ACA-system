<?php
include "../config.php";
checkLogin();
$role = currentUserRole();

if (!in_array($role, ['admin','superadmin'])) {
    http_response_code(403);
    echo "Access denied.";
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$owner_type = $_GET['owner_type'] ?? '';
$owner_id   = isset($_GET['owner_id']) ? (int)$_GET['owner_id'] : 0;

if ($id <= 0) {
    die("Invalid document id.");
}

$stmt = $conn->prepare("DELETE FROM documents WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();

if ($owner_type === 'student' && $owner_id > 0) {
    header("Location: view-student.php?id=" . $owner_id);
} elseif ($owner_type === 'coach' && $owner_id > 0) {
    header("Location: view-coach.php?id=" . $owner_id);
} else {
    header("Location: dashboard.php");
}
exit;
