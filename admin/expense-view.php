<?php
require_once __DIR__ . '/_bootstrap.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) die("Invalid expense ID.");

$stmt = $conn->prepare("SELECT * FROM expenses WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$e = $stmt->get_result()->fetch_assoc();
if (!$e) die("Expense not found.");
?>
<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Expense Details</h1>

<div class="form-card">
    <p style="font-size:13px;color:#6b7280;line-height:1.6;">
        <strong>Date:</strong> <?php echo htmlspecialchars($e['expense_date']); ?><br>
        <strong>Category:</strong>
        <?php
        $cat = $e['category'];
        if (!empty($e['subcategory'])) {
            $cat .= " / " . $e['subcategory'];
        }
        echo htmlspecialchars($cat);
        ?><br>
        <strong>Amount:</strong> $<?php echo number_format($e['amount'], 2); ?> CAD<br>
        <strong>Payment Method:</strong> <?php echo htmlspecialchars($e['payment_method']); ?><br>
        <strong>Vendor:</strong> <?php echo htmlspecialchars($e['vendor']); ?><br>
        <strong>Created At:</strong> <?php echo htmlspecialchars($e['created_at']); ?><br>
        <?php if (!empty($e['receipt_drive_id'])): ?>
            <strong>Receipt:</strong>
            <a href="https://drive.google.com/file/d/<?php echo urlencode($e['receipt_drive_id']); ?>/view"
               target="_blank" class="text-link">View on Google Drive</a><br>
        <?php endif; ?>
    </p>
    <div style="margin-top:8px;">
        <a href="expense-edit.php?id=<?php echo $e['id']; ?>" class="button">Edit</a>
        <a href="expenses.php" class="button">Back to list</a>
    </div>
</div>

<div class="form-card">
    <h2 style="font-size:15px;margin-bottom:6px;">Notes</h2>
    <p style="white-space:pre-wrap;font-size:13px;"><?php echo htmlspecialchars($e['notes']); ?></p>
</div>

<?php include "includes/footer.php"; ?>
