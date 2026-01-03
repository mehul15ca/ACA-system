<?php
require_once __DIR__ . '/_bootstrap.php';

// date filters
$from = isset($_GET['from']) ? trim($_GET['from']) : '';
$to   = isset($_GET['to']) ? trim($_GET['to']) : '';

$where = "1=1";
if ($from !== '') {
    $fromEsc = $conn->real_escape_string($from);
    $where .= " AND fr.sent_at >= '{$fromEsc} 00:00:00'";
}
if ($to !== '') {
    $toEsc = $conn->real_escape_string($to);
    $where .= " AND fr.sent_at <= '{$toEsc} 23:59:59'";
}

$sql = "
    SELECT fr.*, fi.invoice_no, s.first_name, s.last_name, s.admission_no
    FROM fees_reminders_log fr
    JOIN fees_invoices fi ON fr.invoice_id = fi.id
    JOIN students s ON fr.student_id = s.id
    WHERE {$where}
    ORDER BY fr.sent_at DESC
    LIMIT 200
";
$res = $conn->query($sql);
?>
<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Fees Reminders Log</h1>

<div class="form-card">
    <form method="GET" class="filter-form">
        <div class="form-grid-2">
            <div class="form-group">
                <label>From Date</label>
                <input type="date" name="from" value="<?php echo htmlspecialchars($from); ?>">
            </div>
            <div class="form-group">
                <label>To Date</label>
                <input type="date" name="to" value="<?php echo htmlspecialchars($to); ?>">
            </div>
        </div>
        <button type="submit" class="button">Filter</button>
    </form>
</div>

<div class="table-card">
    <div class="table-header">
        <h2>Recent Reminders (max 200)</h2>
    </div>
    <table class="acatable">
        <thead>
            <tr>
                <th>Sent At</th>
                <th>Invoice</th>
                <th>Student</th>
                <th>Type</th>
                <th>Status</th>
                <th>Error</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($res && $res->num_rows > 0): ?>
            <?php while ($row = $res->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['sent_at']); ?></td>
                    <td><?php echo htmlspecialchars($row['invoice_no']); ?></td>
                    <td><?php echo htmlspecialchars($row['admission_no']." - ".$row['first_name']." ".$row['last_name']); ?></td>
                    <td><?php echo htmlspecialchars($row['reminder_type']); ?></td>
                    <td><?php echo htmlspecialchars($row['status']); ?></td>
                    <td><?php echo htmlspecialchars($row['error_message']); ?></td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="6">No reminders logged for this period.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include "includes/footer.php"; ?>
