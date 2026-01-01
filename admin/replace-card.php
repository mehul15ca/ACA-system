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
if ($id <= 0) die("Invalid card ID.");

// Load old card
$stmt = $conn->prepare("SELECT * FROM cards WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
$old = $res->fetch_assoc();
if (!$old) die("Card not found.");

$message = "";
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_card_number = trim($_POST['card_number']);
    $new_uid         = trim($_POST['uid']);
    $issued_on       = $_POST['issued_on'] !== "" ? $_POST['issued_on'] : null;

    if ($new_card_number === "" || $new_uid === "") {
        $message = "New card number and UID are required.";
    } else {
        // Begin replacement: mark old inactive, create new active
        $conn->begin_transaction();
        try {
            $stmt1 = $conn->prepare("UPDATE cards SET status = 'inactive' WHERE id = ?");
            $stmt1->bind_param("i", $id);
            $stmt1->execute();

            $sql2 = "
                INSERT INTO cards (card_number, uid, card_type, assigned_to_type, assigned_to_id, issued_on, status)
                VALUES (?, ?, ?, ?, ?, ?, 'active')
            ";
            $stmt2 = $conn->prepare($sql2);
            $stmt2->bind_param(
                "ssssiss",
                $new_card_number,
                $new_uid,
                $old['card_type'],
                $old['assigned_to_type'],
                $old['assigned_to_id'],
                $issued_on
            );
            $stmt2->execute();

            $conn->commit();
            $success = "Card replaced successfully. Old card set to inactive, new card created & active.";
        } catch (Exception $e) {
            $conn->rollback();
            $message = "Error during replacement: " . $e->getMessage();
        }
    }
}
?>
<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Replace Card</h1>

<div class="form-card">
    <h2>Current Card</h2>
    <table class="detail-table">
        <tr><th>Card Number</th><td><?php echo htmlspecialchars($old['card_number']); ?></td></tr>
        <tr><th>UID</th><td><?php echo htmlspecialchars($old['uid']); ?></td></tr>
        <tr><th>Status</th><td><?php echo htmlspecialchars($old['status']); ?></td></tr>
        <tr><th>Assigned To Type</th><td><?php echo htmlspecialchars($old['assigned_to_type']); ?></td></tr>
        <tr><th>Assigned To ID</th><td><?php echo htmlspecialchars($old['assigned_to_id']); ?></td></tr>
    </table>

    <hr style="margin:14px 0; border:none; border-top:1px solid #1f2933;">

    <?php if ($message): ?>
        <div class="alert-error"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <h2>New Card Details</h2>
    <form method="POST">
        <div class="form-grid-2">
            <div class="form-group">
                <label>New Card Number</label>
                <input type="text" name="card_number" required>
            </div>
            <div class="form-group">
                <label>New UID</label>
                <input type="text" name="uid" id="uidField" required>
            </div>
        </div>
        <div class="form-group">
            <label>Issued On</label>
            <input type="date" name="issued_on">
        </div>
        <button type="submit" class="button-primary">Replace Card</button>
        <a href="cards.php" class="button">Back to list</a>
    </form>
</div>

<script>
document.getElementById('uidField').focus();
</script>

<?php include "includes/footer.php"; ?>
