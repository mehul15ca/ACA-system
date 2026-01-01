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
if ($id <= 0) die("Invalid invoice ID.");

$message = "";
$success = "";

$stmt = $conn->prepare("SELECT * FROM fees_invoices WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$inv = $stmt->get_result()->fetch_assoc();
if (!$inv) die("Invoice not found.");

if ($inv['status'] === 'paid') {
    die("Paid invoices cannot be edited. You may only view them.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount      = floatval($_POST['amount']);
    $currency    = $_POST['currency'] !== "" ? $_POST['currency'] : "CAD";
    $due_date    = $_POST['due_date'];
    $period_from = $_POST['period_from'];
    $period_to   = $_POST['period_to'];
    $status      = $_POST['status'];

    if ($amount <= 0 || $due_date === "" || $period_from === "" || $period_to === "") {
        $message = "Amount, due date and period are required.";
    } elseif (!in_array($status, ['unpaid','cancelled'])) {
        $message = "Status must be unpaid or cancelled here.";
    } else {
        $sql = "UPDATE fees_invoices
                SET amount = ?, currency = ?, due_date = ?, period_from = ?, period_to = ?, status = ?
                WHERE id = ?";
        $stmtU = $conn->prepare($sql);
        $stmtU->bind_param("dsssssi", $amount, $currency, $due_date, $period_from, $period_to, $status, $id);
        if ($stmtU->execute()) {
            $success = "Invoice updated (including any manual late fee adjustments).";
            $inv['amount'] = $amount;
            $inv['currency'] = $currency;
            $inv['due_date'] = $due_date;
            $inv['period_from'] = $period_from;
            $inv['period_to'] = $period_to;
            $inv['status'] = $status;
        } else {
            $message = "Database error: " . $conn->error;
        }
    }
}
?>
<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Edit Invoice</h1>

<div class="form-card">
    <?php if ($message): ?><div class="alert-error"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label>Invoice No</label>
            <input type="text" value="<?php echo htmlspecialchars($inv['invoice_no']); ?>" disabled>
        </div>

        <div class="form-grid-2">
            <div class="form-group">
                <label>Amount (update here to include any late fee)</label>
                <input type="number" step="0.01" name="amount" value="<?php echo htmlspecialchars($inv['amount']); ?>" required>
            </div>
            <div class="form-group">
                <label>Currency</label>
                <input type="text" name="currency" value="<?php echo htmlspecialchars($inv['currency']); ?>">
            </div>
        </div>

        <div class="form-grid-2">
            <div class="form-group">
                <label>Period From</label>
                <input type="date" name="period_from" value="<?php echo htmlspecialchars($inv['period_from']); ?>" required>
            </div>
            <div class="form-group">
                <label>Period To</label>
                <input type="date" name="period_to" value="<?php echo htmlspecialchars($inv['period_to']); ?>" required>
            </div>
        </div>

        <div class="form-group">
            <label>Due Date</label>
            <input type="date" name="due_date" value="<?php echo htmlspecialchars($inv['due_date']); ?>" required>
        </div>

        <div class="form-group">
            <label>Status</label>
            <select name="status">
                <option value="unpaid"   <?php if ($inv['status']==='unpaid')   echo 'selected'; ?>>unpaid</option>
                <option value="cancelled"<?php if ($inv['status']==='cancelled')echo 'selected'; ?>>cancelled</option>
            </select>
        </div>

        <button type="submit" class="button-primary">Save Changes</button>
        <a href="invoice-view.php?id=<?php echo $inv['id']; ?>" class="button">Back to invoice</a>
    </form>
</div>

<?php include "includes/footer.php"; ?>
