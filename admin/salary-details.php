<?php
include "../config.php";
checkLogin();
$role = currentUserRole();
if (!in_array($role, ['admin','superadmin'])) {
    http_response_code(403);
    echo "Access denied.";
    exit;
}

$coach_id = isset($_GET['coach_id']) ? intval($_GET['coach_id']) : 0;
$month    = isset($_GET['month']) ? intval($_GET['month']) : intval(date('n'));
$year     = isset($_GET['year'])  ? intval($_GET['year'])  : intval(date('Y'));

if ($coach_id <= 0) {
    die("Missing coach ID.");
}

$stmtC = $conn->prepare("SELECT name FROM coaches WHERE id = ?");
$stmtC->bind_param("i", $coach_id);
$stmtC->execute();
$coach = $stmtC->get_result()->fetch_assoc();
if (!$coach) die("Coach not found.");

$sql = "
    SELECT s.*, b.name AS batch_name
    FROM coach_salary_sessions s
    LEFT JOIN batches b ON s.batch_id = b.id
    WHERE s.coach_id = {$coach_id}
      AND s.month = {$month}
      AND s.year = {$year}
    ORDER BY s.session_date ASC, s.id ASC
";
$res = $conn->query($sql);

$total_amount = 0;
$total_unpaid = 0;
$total_hours  = 0;
if ($res) {
    foreach ($res as $row) {
        $total_amount += floatval($row['amount']);
        if ($row['status'] === 'unpaid') {
            $total_unpaid += floatval($row['amount']);
        }
        $total_hours += floatval($row['hours']);
    }
    // reset pointer for actual loop
    $res->data_seek(0);
}
?>
<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Salary Details</h1>

<div class="form-card">
    <p style="font-size:13px;color:#6b7280;line-height:1.6;">
        <strong>Coach:</strong> <?php echo htmlspecialchars($coach['name']); ?><br>
        <strong>Period:</strong> <?php echo date('F Y', strtotime(sprintf('%04d-%02d-01', $year, $month))); ?><br>
        <strong>Total Amount:</strong> $<?php echo number_format($total_amount, 2); ?> CAD<br>
        <strong>Unpaid Amount:</strong> $<?php echo number_format($total_unpaid, 2); ?> CAD<br>
        <strong>Total Hours (per_hour lines):</strong> <?php echo number_format($total_hours, 2); ?>
    </p>
    <a href="salary.php?month=<?php echo $month; ?>&year=<?php echo $year; ?>" class="button">Back to summary</a>
</div>

<div class="table-card">
    <div class="table-header">
        <h2>Salary Lines</h2>
    </div>
    <table class="acatable">
        <thead>
            <tr>
                <th>Date</th>
                <th>Batch</th>
                <th>Rate Type</th>
                <th>Hours</th>
                <th>Rate</th>
                <th>Amount</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($res && $res->num_rows > 0): ?>
            <?php while ($s = $res->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($s['session_date']); ?></td>
                    <td><?php echo htmlspecialchars($s['batch_name']); ?></td>
                    <td><?php echo htmlspecialchars($s['rate_type']); ?></td>
                    <td><?php echo number_format($s['hours'], 2); ?></td>
                    <td>$<?php echo number_format($s['rate_amount'], 2); ?> CAD</td>
                    <td>$<?php echo number_format($s['amount'], 2); ?> CAD</td>
                    <td>
                        <?php if ($s['status'] === 'paid'): ?>
                            <span style="color:#22c55e;font-weight:600;">PAID</span>
                        <?php else: ?>
                            <span style="color:#ef4444;font-weight:600;">UNPAID</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="7">No salary lines for this coach and month.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include "includes/footer.php"; ?>
