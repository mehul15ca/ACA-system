<?php
// admin/upload-document.php

include "../config.php";
checkLogin();
$role = currentUserRole();

// Only admin/superadmin for now
if (!in_array($role, ['admin','superadmin'])) {
    http_response_code(403);
    echo "Access denied.";
    exit;
}

require_once "includes/documents-helper.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit;
}

$owner_type = $_POST['owner_type'] ?? '';
$owner_id   = isset($_POST['owner_id']) ? (int)$_POST['owner_id'] : 0;
$title      = trim($_POST['title'] ?? '');
$drive_input = trim($_POST['drive_input'] ?? '');

if ($owner_type === '' || $owner_id <= 0 || $title === '' || $drive_input === '') {
    die("Missing required data.");
}

$drive_id = extractDriveFileId($drive_input);
if ($drive_id === '') {
    die("Could not extract Google Drive File ID. Please check the link/ID.");
}

// Optional: guess type by simple heuristic
$file_type = null;

$stmt = $conn->prepare("
    INSERT INTO documents (owner_type, owner_id, title, file_type, drive_file_id, created_at)
    VALUES (?, ?, ?, ?, ?, NOW())
");
$stmt->bind_param("sisss", $owner_type, $owner_id, $title, $file_type, $drive_id);
$stmt->execute();

// Redirect back to profile
if ($owner_type === 'student') {
    header("Location: view-student.php?id=" . $owner_id);
} elseif ($owner_type === 'coach') {
    header("Location: view-coach.php?id=" . $owner_id);
} else {
    header("Location: dashboard.php");
}
exit;
