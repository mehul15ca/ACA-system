<?php
// admin/delete-match-player.php
require_once __DIR__ . '/_bootstrap.php';

AdminGuard::requireAnyRole(['admin', 'superadmin', 'coach']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

Csrf::validateRequest();

$id    = (int)($_POST['id'] ?? 0);
$match = (int)($_POST['match_id'] ?? 0);

if ($id <= 0 || $match <= 0) {
    http_response_code(400);
    exit('Invalid parameters');
}

$stmt = $conn->prepare("DELETE FROM match_players WHERE id=? AND match_id=?");
$stmt->bind_param("ii", $id, $match);
$stmt->execute();

header("Location: manage-players.php?id=" . $match);
exit;
