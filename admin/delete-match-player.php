<?php
require_once __DIR__ . '/_bootstrap.php';
AdminGuard::requireAnyRole(['admin','superadmin','coach']);
Csrf::validateRequest();

$id = (int)($_POST['id'] ?? 0);
$match = (int)($_POST['match_id'] ?? 0);

$conn->prepare("DELETE FROM match_players WHERE id=? AND match_id=?")
      ->bind_param("ii",$id,$match)->execute();

header("Location: manage-players.php?id=".$match);
exit;
