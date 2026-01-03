<?php
// admin/delete-coach.php
require_once __DIR__ . '/_bootstrap.php';

AdminGuard::requirePermission(Permissions::COACHES_MANAGE);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

Csrf::validateRequest();

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit('Invalid coach id');
}

$stmt1 = $conn->prepare("UPDATE coaches SET status='disabled' WHERE id=?");
$stmt1->bind_param("i", $id);
$stmt1->execute();

$stmt2 = $conn->prepare("UPDATE users SET status='disabled' WHERE coach_id=?");
$stmt2->bind_param("i", $id);
$stmt2->execute();

header("Location: coaches.php");
exit;
