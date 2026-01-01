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
    die("Invalid card ID.");
}

$sql = "
    SELECT c.*,
           CASE
               WHEN c.assigned_to_type = 'student' THEN s.first_name
               WHEN c.assigned_to_type = 'coach'   THEN ch.name
               ELSE NULL
           END AS owner_first_name,
           CASE
               WHEN c.assigned_to_type = 'student' THEN s.last_name
               ELSE ''
           END AS owner_last_name,
           CASE
               WHEN c.assigned_to_type = 'student' THEN s.admission_no
               ELSE ch.coach_code
           END AS owner_code
    FROM cards c
    LEFT JOIN students s
        ON c.assigned_to_type = 'student' AND c.assigned_to_id = s.id
    LEFT JOIN coaches ch
        ON c.assigned_to_type = 'coach' AND c.assigned_to_id = ch.id
    WHERE c.id = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
$card = $res->fetch_assoc();
if (!$card) {
    die("Card not found.");
}

$ownerName = "";
if ($card['assigned_to_type'] === 'student') {
    $ownerName = trim($card['owner_first_name'] . " " . $card['owner_last_name']);
} elseif ($card['assigned_to_type'] === 'coach') {
    $ownerName = $card['owner_first_name'];
}
?>
<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>View Card</h1>

<div class="form-card">
    <table class="detail-table">
        <tr>
            <th>ID</th>
            <td><?php echo $card['id']; ?></td>
        </tr>
        <tr>
            <th>Card Number</th>
            <td><?php echo htmlspecialchars($card['card_number']); ?></td>
        </tr>
        <tr>
            <th>UID</th>
            <td><?php echo htmlspecialchars($card['uid']); ?></td>
        </tr>
        <tr>
            <th>Card Type</th>
            <td><?php echo htmlspecialchars($card['card_type']); ?></td>
        </tr>
        <tr>
            <th>Owner Type</th>
            <td><?php echo htmlspecialchars($card['assigned_to_type']); ?></td>
        </tr>
        <tr>
            <th>Owner Name</th>
            <td><?php echo htmlspecialchars($ownerName ?: '-'); ?></td>
        </tr>
        <tr>
            <th>Owner Code</th>
            <td><?php echo htmlspecialchars($card['owner_code'] ?: '-'); ?></td>
        </tr>
        <tr>
            <th>Status</th>
            <td><?php echo htmlspecialchars($card['status']); ?></td>
        </tr>
        <tr>
            <th>Issued On</th>
            <td><?php echo htmlspecialchars($card['issued_on']); ?></td>
        </tr>
        <tr>
            <th>Created At</th>
            <td><?php echo htmlspecialchars($card['created_at']); ?></td>
        </tr>
    </table>

    <div style="margin-top:12px;">
        <a href="edit-card.php?id=<?php echo $card['id']; ?>" class="button">Edit</a>
        <a href="replace-card.php?id=<?php echo $card['id']; ?>" class="button">Replace</a>
        <a href="toggle-card-status.php?id=<?php echo $card['id']; ?>" class="button">
            <?php echo $card['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>
        </a>
        <a href="cards.php" class="button">Back to list</a>
    </div>
</div>

<?php include "includes/footer.php"; ?>
