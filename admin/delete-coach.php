<?php
require_once __DIR__ . '/_bootstrap.php';
AdminGuard::requirePermission(Permissions::COACHES_MANAGE);
Csrf::validateRequest();

$id = (int)($_POST['id'] ?? 0);

$conn->prepare("UPDATE coaches SET status='disabled' WHERE id=?")->bind_param("i",$id)->execute();
$conn->prepare("UPDATE users SET status='disabled' WHERE coach_id=?")->bind_param("i",$id)->execute();

header("Location: coaches.php");
exit;
