<?php
// admin/delete-ground.php
require_once __DIR__ . '/_bootstrap.php';

AdminGuard::requirePermission(Permissions::GROUNDS_MANAGE);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

Csrf::validateRequest();

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit('Invalid ground id');
}

$stmt = $conn->prepare("UPDATE grounds SET status='disabled' WHERE id=?");
$stmt->bind_param("i", $id);
$stmt->execute();

header("Location: grounds.php");
exit;
