<?php
// admin/delete-schedule.php
require_once __DIR__ . '/_bootstrap.php';

AdminGuard::requirePermission(Permissions::BATCH_SCHEDULE_MANAGE);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

Csrf::validateRequest();

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit('Invalid schedule id');
}

$stmt = $conn->prepare("DELETE FROM batch_schedule WHERE id=?");
$stmt->bind_param("i", $id);
$stmt->execute();

header("Location: batch-schedule.php");
exit;
