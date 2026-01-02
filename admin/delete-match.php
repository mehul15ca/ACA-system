<?php
require_once __DIR__ . '/_bootstrap.php';
AdminGuard::requirePermission(Permissions::MATCHES_MANAGE);
Csrf::validateRequest();

$id = (int)($_POST['id'] ?? 0);
$conn->query("DELETE FROM match_players WHERE match_id=$id");
$conn->prepare("DELETE FROM matches WHERE id=?")->bind_param("i",$id)->execute();

header("Location: matches.php");
exit;
