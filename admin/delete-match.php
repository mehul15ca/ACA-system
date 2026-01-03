<?php
// admin/delete-match.php
require_once __DIR__ . '/_bootstrap.php';

AdminGuard::requirePermission(Permissions::MATCHES_MANAGE);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

Csrf::validateRequest();

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit('Invalid match id');
}

$conn->begin_transaction();
try {
    $stmt1 = $conn->prepare("DELETE FROM match_players WHERE match_id=?");
    $stmt1->bind_param("i", $id);
    $stmt1->execute();

    $stmt2 = $conn->prepare("DELETE FROM matches WHERE id=?");
    $stmt2->bind_param("i", $id);
    $stmt2->execute();

    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    http_response_code(500);
    exit('Failed to delete match');
}

header("Location: matches.php");
exit;
