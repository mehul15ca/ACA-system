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
    header("Location: invoices.php");
    exit;
}

// load invoice
$stmt = $conn->prepare("SELECT * FROM fees_invoices WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$inv = $stmt->get_result()->fetch_assoc();
if (!$inv) {
    header("Location: invoices.php");
    exit;
}
if ($inv['status'] === 'paid') {
    header("Location: invoice-view.php?id=" . $id);
    exit;
}

$message = "";
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reference = trim($_POST['reference']);

    // no partials: pay full invoice amount
    $amount   = floatval($inv['amount']);
    $currency = $inv['currency'];
    $method   = "Online Gateway";
    $now      = date('Y-m-d H:i:s');

    $conn->begin_transaction();
    try {
        // insert payment
        $sqlP = "INSERT INTO fees_payments (invoice_id, amount, currency, paid_on, method, reference)
                 VALUES (?, ?, ?, ?, ?, ?)";
        $stmtP = $conn->prepare($sqlP);
        $stmtP->bind_param("idssss", $id, $amount, $currency, $now, $method, $reference);
        $stmtP->execute();

        // update invoice status
        $stmtU = $conn->prepare("UPDATE fees_invoices SET status = 'paid' WHERE id = ?");
        $stmtU->bind_param("i", $id);
        $stmtU->execute();

        $conn->commit();
        $success = "Invoice marked as paid.";
    } catch (Exception $e) {
        $conn->rollback();
        $message = "Error recording payment: " . $e->getMessage();
    }
}
?>
<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Mark Invoice Paid</h1>

<div class="form-card">
    <p style="font-size:13px;color:#6b7280;">
        Invoice: <strong><?php echo htmlspecialchars($inv['invoice_no']); ?></strong><br>
        Student: <?php echo htmlspecialchars($inv['student_id']); ?><br>
        Amount: <?php echo number_format($inv['amount'], 2) . " " . htmlspecialchars($inv['currency']); ?><br>
        Note: Partial payments are <strong>not allowed</strong>. This will record full payment and set invoice to <strong>paid</strong>.
    </p>

    <?php if ($message): ?><div class="alert-error"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

    <?php if (!$success): ?>
        <form method="POST">
            <div class="form-group">
                <label>Gateway Reference / Transaction ID (optional)</label>
                <input type="text" name="reference" placeholder="e.g. STRIPE-CH_12345 or RAZORPAY-XYZ">
            </div>
            <button type="submit" class="button-primary">Confirm Full Payment</button>
            <a href="invoice-view.php?id=<?php echo $inv['id']; ?>" class="button">Cancel</a>
        </form>
    <?php else: ?>
        <a href="invoice-view.php?id=<?php echo $inv['id']; ?>" class="button">Back to invoice</a>
    <?php endif; ?>
</div>

<?php include "includes/footer.php"; ?>
