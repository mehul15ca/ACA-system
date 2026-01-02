<?php
require_once __DIR__ . '/_bootstrap.php';
AdminGuard::requirePermission(Permissions::BATCH_SCHEDULE_MANAGE);
Csrf::validateRequest();

$id = (int)($_POST['id'] ?? 0);
$conn->prepare("DELETE FROM batch_schedule WHERE id=?")->bind_param("i",$id)->execute();

header("Location: batch-schedule.php");
exit;
