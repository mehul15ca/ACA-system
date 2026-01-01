<?php
include "../config.php";
checkLogin();

$role = currentUserRole();
if (!in_array($role, ['admin','superadmin'])) {
    http_response_code(403);
    echo "Access denied.";
    exit;
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    header("Location: cards.php");
    exit;
}

$stmt = $conn->prepare("SELECT status FROM cards WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
$card = $res->fetch_assoc();
if (!$card) {
    header("Location: cards.php");
    exit;
}

$newStatus = $card['status'] === 'active' ? 'inactive' : 'active';
$stmtU = $conn->prepare("UPDATE cards SET status = ? WHERE id = ?");
$stmtU->bind_param("si", $newStatus, $id);
$stmtU->execute();

header("Location: cards.php");
exit;
