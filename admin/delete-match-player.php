<?php
include "../config.php";
checkLogin();

$role = currentUserRole();
if (!in_array($role, ['admin','superadmin','coach'])) {
    http_response_code(403);
    echo "Access denied.";
    exit;
}

if (!isset($_GET['id']) || !isset($_GET['match_id'])) {
    die("Missing parameters.");
}
$mp_id    = intval($_GET['id']);
$match_id = intval($_GET['match_id']);

$del = $conn->prepare("DELETE FROM match_players WHERE id = ? AND match_id = ?");
$del->bind_param("ii", $mp_id, $match_id);
$del->execute();

header("Location: manage-players.php?id=" . $match_id);
exit;
