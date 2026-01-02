<?php
require_once __DIR__ . '/_bootstrap.php';
AdminGuard::requirePermission(Permissions::GROUNDS_MANAGE);
Csrf::validateRequest();

$id = (int)($_POST['id'] ?? 0);
$conn->prepare("UPDATE grounds SET status='disabled' WHERE id=?")->bind_param("i",$id)->execute();

header("Location: grounds.php");
exit;
