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
if ($id <= 0) die("Invalid expense ID.");

$message = "";
$success = "";

$stmt = $conn->prepare("SELECT * FROM expenses WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$e = $stmt->get_result()->fetch_assoc();
if (!$e) die("Expense not found.");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $expense_date   = $_POST['expense_date'] !== "" ? $_POST['expense_date'] : date('Y-m-d');
    $category       = trim($_POST['category']);
    $subcategory    = trim($_POST['subcategory']);
    $amount         = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
    $currency       = "CAD";
    $payment_method = trim($_POST['payment_method']);
    $vendor         = trim($_POST['vendor']);
    $notes          = trim($_POST['notes']);
    $receipt_id     = trim($_POST['receipt_drive_id']);

    if ($category === '') {
        $message = "Category is required.";
    } elseif ($amount <= 0) {
        $message = "Amount must be greater than zero.";
    } else {
        $expense_date   = $conn->real_escape_string($expense_date);
        $category       = $conn->real_escape_string($category);
        $subcategory    = $conn->real_escape_string($subcategory);
        $amount         = floatval($amount);
        $payment_method = $conn->real_escape_string($payment_method);
        $vendor         = $conn->real_escape_string($vendor);
        $notes          = $conn->real_escape_string($notes);
        $receipt_id     = $conn->real_escape_string($receipt_id);

        $sql = "
            UPDATE expenses
            SET expense_date='{$expense_date}',
                category='{$category}',
                subcategory='{$subcategory}',
                amount={$amount},
                currency='CAD',
                payment_method='{$payment_method}',
                vendor='{$vendor}',
                notes='{$notes}',
                receipt_drive_id='{$receipt_id}'
            WHERE id={$id}
        ";
        if ($conn->query($sql)) {
            $success = "Expense updated successfully.";
            $stmt2 = $conn->prepare("SELECT * FROM expenses WHERE id = ?");
            $stmt2->bind_param("i", $id);
            $stmt2->execute();
            $e = $stmt2->get_result()->fetch_assoc();
        } else {
            $message = "Database error: " . $conn->error;
        }
    }
}
?>
<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Edit Expense</h1>

<div class="form-card">
    <?php if ($message): ?><div class="alert-error"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if (!empty($success)): ?><div class="alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

    <form method="POST">
        <div class="form-grid-2">
            <div class="form-group">
                <label>Date</label>
                <input type="date" name="expense_date"
                       value="<?php echo htmlspecialchars($e['expense_date']); ?>">
            </div>
            <div class="form-group">
                <label>Category</label>
                <select name="category" required>
                    <?php
                    $cats = ["Rent","Utilities","Salaries","Equipment","Events","Travel","Marketing","Other"];
                    ?>
                    <option value="">-- Select Category --</option>
                    <?php foreach ($cats as $cat): ?>
                        <option value="<?php echo $cat; ?>"
                            <?php if ($e['category'] === $cat) echo 'selected'; ?>>
                            <?php echo $cat; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-grid-2">
            <div class="form-group">
                <label>Subcategory (optional)</label>
                <input type="text" name="subcategory"
                       value="<?php echo htmlspecialchars($e['subcategory']); ?>">
            </div>
            <div class="form-group">
                <label>Amount (CAD)</label>
                <input type="number" step="0.01" min="0" name="amount"
                       value="<?php echo htmlspecialchars($e['amount']); ?>" required>
            </div>
        </div>

        <div class="form-grid-2">
            <div class="form-group">
                <label>Payment Method</label>
                <select name="payment_method">
                    <?php
                    $methods = ["","Cash","Bank Transfer","Card","e-Transfer","Cheque","Other"];
                    ?>
                    <?php foreach ($methods as $m): ?>
                        <option value="<?php echo $m; ?>"
                            <?php if ($e['payment_method'] === $m) echo 'selected'; ?>>
                            <?php echo $m === "" ? "-- Select Method --" : $m; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Vendor (optional)</label>
                <input type="text" name="vendor"
                       value="<?php echo htmlspecialchars($e['vendor']); ?>">
            </div>
        </div>

        <div class="form-group">
            <label>Notes (optional)</label>
            <textarea name="notes" rows="4"><?php echo htmlspecialchars($e['notes']); ?></textarea>
        </div>

        <div class="form-group">
            <label>Receipt Google Drive File ID (optional)</label>
            <input type="text" name="receipt_drive_id"
                   value="<?php echo htmlspecialchars($e['receipt_drive_id']); ?>">
        </div>

        <button type="submit" class="button-primary">Update Expense</button>
        <a href="expense-view.php?id=<?php echo $e['id']; ?>" class="button">Back</a>
    </form>
</div>

<?php include "includes/footer.php"; ?>
