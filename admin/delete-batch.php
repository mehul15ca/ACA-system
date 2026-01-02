<?php
require_once __DIR__ . '/_bootstrap.php';
AdminGuard::requirePermission(Permissions::BATCHES_MANAGE);

$id = (int)($_POST['id'] ?? 0);
Csrf::validateRequest();

$stmt = $conn->prepare("UPDATE batches SET status='disabled' WHERE id=?");
$stmt->bind_param("i",$id);
$stmt->execute();

header("Location: batches.php");
exit;
