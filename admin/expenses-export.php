<?php
include "../config.php";
checkLogin();
$role = currentUserRole();
if (!in_array($role, ['admin','superadmin'])) {
    http_response_code(403);
    echo "Access denied.";
    exit;
}

$mode = $_GET['mode'] ?? 'view'; // view, csv
$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-t');

if ($from > $to) {
    $tmp = $from;
    $from = $to;
    $to = $tmp;
}

$stmt = $conn->prepare("
    SELECT expense_date, category, amount, tax_amount, total_amount,
           description, status
    FROM expenses
    WHERE expense_date BETWEEN ? AND ?
    ORDER BY expense_date ASC, id ASC
");
$stmt->bind_param("ss", $from, $to);
$stmt->execute();
$res = $stmt->get_result();
$rows = [];
while ($r = $res->fetch_assoc()) {
    $rows[] = $r;
}

if ($mode === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="expenses-' . $from . '-to-' . $to . '.csv"');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['Date','Category','Amount','Tax','Total','Status','Description']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['expense_date'],
            $r['category'],
            $r['amount'],
            $r['tax_amount'],
            $r['total_amount'],
            $r['status'],
            $r['description'],
        ]);
    }
    fclose($out);
    exit;
}
?>
<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Expense Export</h1>
<p style="font-size:13px;color:#9ca3af;margin-bottom:12px;">
    Generate printable reports or download CSV for a date range.
</p>

<form method="GET" style="margin-bottom:12px;display:flex;flex-wrap:wrap;gap:8px;align-items:flex-end;">
    <input type="hidden" name="mode" value="view">
    <div>
        <label style="font-size:13px;">From</label><br>
        <input type="date" name="from" value="<?php echo htmlspecialchars($from); ?>">
    </div>
    <div>
        <label style="font-size:13px;">To</label><br>
        <input type="date" name="to" value="<?php echo htmlspecialchars($to); ?>">
    </div>
    <div>
        <button type="submit" class="button-primary">Update</button>
    </div>
    <div>
        <a href="?mode=csv&from=<?php echo urlencode($from); ?>&to=<?php echo urlencode($to); ?>"
           class="button-secondary">Download CSV</a>
    </div>
</form>

<div class="card">
    <h2 class="card-title">Printable Expense Report</h2>
    <p style="font-size:12px;color:#9ca3af;">
        Tip: Use your browser's <strong>Print → Save as PDF</strong> to export this report as a PDF.
    </p>
    <div style="background:#020617;padding:12px;border-radius:12px;border:1px solid #1f2937;">
        <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:8px;">
            <div>
                <strong>Australasia Cricket Academy</strong><br>
                Expense Report
            </div>
            <div>
                Period: <?php echo htmlspecialchars($from); ?> to <?php echo htmlspecialchars($to); ?><br>
                Generated: <?php echo date('Y-m-d H:i'); ?>
            </div>
        </div>
        <table class="table-basic" style="font-size:12px;">
            <thead>
                <tr>
                    <th style="width:90px;">Date</th>
                    <th style="width:110px;">Category</th>
                    <th style="text-align:right;">Amount</th>
                    <th style="text-align:right;">Tax</th>
                    <th style="text-align:right;">Total</th>
                    <th style="width:80px;">Status</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $sumAmount = 0; $sumTax = 0; $sumTotal = 0;
            foreach ($rows as $r):
                $sumAmount += (float)$r['amount'];
                $sumTax += (float)$r['tax_amount'];
                $sumTotal += (float)$r['total_amount'];
            ?>
                <tr>
                    <td><?php echo htmlspecialchars($r['expense_date']); ?></td>
                    <td><?php echo htmlspecialchars($r['category']); ?></td>
                    <td style="text-align:right;"><?php echo number_format((float)$r['amount'],2); ?></td>
                    <td style="text-align:right;"><?php echo number_format((float)$r['tax_amount'],2); ?></td>
                    <td style="text-align:right;"><?php echo number_format((float)$r['total_amount'],2); ?></td>
                    <td><?php echo htmlspecialchars(ucfirst($r['status'])); ?></td>
                    <td><?php echo htmlspecialchars($r['description']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="2" style="text-align:right;">Totals:</th>
                    <th style="text-align:right;"><?php echo number_format($sumAmount,2); ?></th>
                    <th style="text-align:right;"><?php echo number_format($sumTax,2); ?></th>
                    <th style="text-align:right;"><?php echo number_format($sumTotal,2); ?></th>
                    <th colspan="2"></th>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<?php include "includes/footer.php"; ?>
