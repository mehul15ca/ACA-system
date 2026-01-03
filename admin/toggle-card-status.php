<?php
// admin/toggle-card-status.php  (FIXED: POST + CSRF + prepared statements)
require_once __DIR__ . '/_bootstrap.php';

AdminGuard::requirePermission(Permissions::CARDS_MANAGE);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

Csrf::validateRequest();

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit('Invalid card id');
}

$stmt = $conn->prepare("SELECT status FROM cards WHERE id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
$card = $res ? $res->fetch_assoc() : null;

if (!$card) {
    http_response_code(404);
    exit('Card not found');
}

$newStatus = ($card['status'] === 'active') ? 'inactive' : 'active';

$stmtU = $conn->prepare("UPDATE cards SET status=? WHERE id=?");
$stmtU->bind_param("si", $newStatus, $id);
$stmtU->execute();

header("Location: cards.php");
exit;
