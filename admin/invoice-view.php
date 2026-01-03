<?php
require_once __DIR__ . '/_bootstrap.php';
include "fees-helpers.php";

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$print = isset($_GET['print']) && $_GET['print'] == '1';

if ($id <= 0) die("Invalid invoice ID.");

$sql = "
    SELECT fi.*, s.admission_no, s.first_name, s.last_name, s.email,
           fp.name AS plan_name
    FROM fees_invoices fi
    JOIN students s ON fi.student_id = s.id
    LEFT JOIN fees_plans fp ON fi.plan_id = fp.id
    WHERE fi.id = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$inv = $stmt->get_result()->fetch_assoc();
if (!$inv) die("Invoice not found.");

// get payments
$stmtP = $conn->prepare("SELECT * FROM fees_payments WHERE invoice_id = ? ORDER BY paid_on ASC");
$stmtP->bind_param("i", $id);
$stmtP->execute();
$payments = $stmtP->get_result();

if ($print):
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Invoice <?php echo htmlspecialchars($inv['invoice_no']); ?></title>
    <style>
        body { font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; padding:20px; color:#111827; }
        h1 { margin-bottom:4px; }
        h2 { margin-top:20px; margin-bottom:8px; }
        table { border-collapse:collapse; width:100%; margin-bottom:12px; }
        th, td { border:1px solid #d1d5db; padding:6px 8px; font-size:13px; }
        th { background:#f3f4f6; }
        .meta { font-size:13px; color:#4b5563; margin-bottom:16px; }
        .amount { font-size:18px; font-weight:600; }
    </style>
</head>
<body onload="window.print()">
    <h1>Invoice <?php echo htmlspecialchars($inv['invoice_no']); ?></h1>
    <div class="meta">
        Australasia Cricket Academy<br>
        Invoice Date: <?php echo htmlspecialchars($inv['created_at']); ?><br>
        Due Date: <?php echo htmlspecialchars($inv['due_date']); ?><br>
        Period: <?php echo htmlspecialchars($inv['period_from']); ?> to <?php echo htmlspecialchars($inv['period_to']); ?><br>
        Status: <?php echo htmlspecialchars($inv['status']); ?>
    </div>

    <h2>Bill To</h2>
    <p class="meta">
        <?php echo htmlspecialchars($inv['first_name'] . " " . $inv['last_name']); ?><br>
        Admission: <?php echo htmlspecialchars($inv['admission_no']); ?><br>
        Email: <?php echo htmlspecialchars($inv['email']); ?>
    </p>

    <h2>Details</h2>
    <table>
        <thead>
            <tr>
                <th>Description</th>
                <th>Period</th>
                <th>Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <?php
                        if ($inv['plan_name']) {
                            echo htmlspecialchars($inv['plan_name']);
                        } else {
                            echo "Monthly Training Fees";
                        }
                    ?>
                </td>
                <td><?php echo htmlspecialchars($inv['period_from']); ?> to <?php echo htmlspecialchars($inv['period_to']); ?></td>
                <td><?php echo number_format($inv['amount'], 2) . " " . htmlspecialchars($inv['currency']); ?></td>
            </tr>
        </tbody>
    </table>

    <p class="amount">
        Total: <?php echo number_format($inv['amount'], 2) . " " . htmlspecialchars($inv['currency']); ?>
    </p>

    <?php if ($payments && $payments->num_rows > 0): ?>
        <h2>Payments</h2>
        <table>
            <thead>
                <tr>
                    <th>Paid On</th>
                    <th>Method</th>
                    <th>Reference</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($p = $payments->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($p['paid_on']); ?></td>
                    <td><?php echo htmlspecialchars($p['method']); ?></td>
                    <td><?php echo htmlspecialchars($p['reference']); ?></td>
                    <td><?php echo number_format($p['amount'], 2) . " " . htmlspecialchars($p['currency']); ?></td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    <?php endif; ?>
</body>
</html>
<?php
exit;
endif;
?>

<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Invoice <?php echo htmlspecialchars($inv['invoice_no']); ?></h1>

<div class="form-card">
    <p style="font-size:13px;color:#6b7280;">
        Status: <strong><?php echo htmlspecialchars($inv['status']); ?></strong><br>
        Student: <?php echo htmlspecialchars($inv['admission_no'] . " - " . $inv['first_name'] . " " . $inv['last_name']); ?><br>
        Period: <?php echo htmlspecialchars($inv['period_from']); ?> → <?php echo htmlspecialchars($inv['period_to']); ?><br>
        Due Date: <?php echo htmlspecialchars($inv['due_date']); ?><br>
    </p>
    <p style="font-size:18px;font-weight:600;">
        Amount: <?php echo number_format($inv['amount'], 2) . " " . htmlspecialchars($inv['currency']); ?>
    </p>

    <div style="margin-top:8px;">
        <a href="invoice-view.php?id=<?php echo $inv['id']; ?>&print=1" class="button" target="_blank">🧾 Print / PDF</a>
        <?php if ($inv['status'] === 'unpaid'): ?>
            <a href="invoice-mark-paid.php?id=<?php echo $inv['id']; ?>" class="button-primary">Mark as Paid (Online)</a>
            <a href="invoice-edit.php?id=<?php echo $inv['id']; ?>" class="button">Edit Invoice</a>
        <?php endif; ?>
        <a href="invoices.php" class="button">Back to list</a>
    </div>
</div>

<div class="table-card">
    <div class="table-header">
        <h2>Payment History</h2>
    </div>
    <table class="acatable">
        <thead>
            <tr>
                <th>Paid On</th>
                <th>Method</th>
                <th>Reference</th>
                <th>Amount</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($payments && $payments->num_rows > 0): ?>
            <?php mysqli_data_seek($payments, 0); while ($p = $payments->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($p['paid_on']); ?></td>
                    <td><?php echo htmlspecialchars($p['method']); ?></td>
                    <td><?php echo htmlspecialchars($p['reference']); ?></td>
                    <td><?php echo number_format($p['amount'], 2) . " " . htmlspecialchars($p['currency']); ?></td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="4">No payments recorded.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include "includes/footer.php"; ?>
