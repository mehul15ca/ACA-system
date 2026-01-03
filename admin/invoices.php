<?php
require_once __DIR__ . '/_bootstrap.php';
include "fees-helpers.php";

$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$filter_month  = isset($_GET['month']) && $_GET['month'] !== '' ? $_GET['month'] : date('Y-m');
$filter_student= isset($_GET['student']) ? trim($_GET['student']) : '';

list($y, $m) = explode('-', $filter_month);
$monthStart = sprintf('%04d-%02d-01', $y, $m);
$monthEnd   = date('Y-m-t', strtotime($monthStart));

$sql = "
    SELECT fi.*, s.admission_no, s.first_name, s.last_name
    FROM fees_invoices fi
    JOIN students s ON fi.student_id = s.id
    WHERE fi.period_from >= ? AND fi.period_from <= ?
";
$params = [$monthStart, $monthEnd];
$types  = "ss";

if ($filter_status !== '') {
    $sql .= " AND fi.status = ? ";
    $params[] = $filter_status;
    $types   .= "s";
}
if ($filter_student !== '') {
    $sql .= " AND (
        s.admission_no LIKE ?
        OR CONCAT(s.first_name, ' ', s.last_name) LIKE ?
    )";
    $like = "%" . $filter_student . "%";
    $params[] = $like;
    $params[] = $like;
    $types   .= "ss";
}

$sql .= " ORDER BY fi.due_date ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();

// For student filter dropdown, optional
$students_res = $conn->query("SELECT id, admission_no, first_name, last_name FROM students ORDER BY first_name ASC");
?>
<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Invoices</h1>

<div class="form-card" style="margin-bottom:16px;">
    <form method="GET" style="display:flex; flex-wrap:wrap; gap:10px; align-items:flex-end;">
        <div>
            <label style="font-size:12px;">Month</label>
            <input type="month" name="month" value="<?php echo htmlspecialchars($filter_month); ?>">
        </div>
        <div>
            <label style="font-size:12px;">Status</label>
            <select name="status">
                <option value="">All</option>
                <option value="unpaid"   <?php if ($filter_status==='unpaid')   echo 'selected'; ?>>unpaid</option>
                <option value="paid"     <?php if ($filter_status==='paid')     echo 'selected'; ?>>paid</option>
                <option value="cancelled"<?php if ($filter_status==='cancelled')echo 'selected'; ?>>cancelled</option>
            </select>
        </div>
        <div>
            <label style="font-size:12px;">Student (name or admission)</label>
            <input type="text" name="student" value="<?php echo htmlspecialchars($filter_student); ?>">
        </div>
        <div>
            <button type="submit" class="button-primary">Filter</button>
        </div>
        <div style="margin-left:auto;">
            <a href="invoice-add.php" class="button">➕ Create Manual Invoice</a>
        </div>
    </form>
</div>

<div class="table-card">
    <div class="table-header">
        <h2>Invoices for <?php echo htmlspecialchars(date('F Y', strtotime($monthStart))); ?></h2>
    </div>
    <table class="acatable">
        <thead>
            <tr>
                <th>Invoice No</th>
                <th>Student</th>
                <th>Period</th>
                <th>Amount</th>
                <th>Due Date</th>
                <th>Status</th>
                <th>Paid On</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($res && $res->num_rows > 0): ?>
            <?php while ($inv = $res->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($inv['invoice_no']); ?></td>
                    <td>
                        <?php echo htmlspecialchars($inv['admission_no'] . " - " . $inv['first_name'] . " " . $inv['last_name']); ?>
                    </td>
                    <td>
                        <?php echo htmlspecialchars($inv['period_from']); ?>
                        &rarr;
                        <?php echo htmlspecialchars($inv['period_to']); ?>
                    </td>
                    <td><?php echo number_format($inv['amount'], 2) . " " . htmlspecialchars($inv['currency']); ?></td>
                    <td><?php echo htmlspecialchars($inv['due_date']); ?></td>
                    <td><?php echo htmlspecialchars($inv['status']); ?></td>
                    <td>
                        <?php
                        if ($inv['status'] === 'paid') {
                            // find latest payment
                            $stmtP = $conn->prepare("SELECT MAX(paid_on) AS last_paid FROM fees_payments WHERE invoice_id = ?");
                            $stmtP->bind_param("i", $inv['id']);
                            $stmtP->execute();
                            $rp = $stmtP->get_result()->fetch_assoc();
                            echo htmlspecialchars($rp['last_paid']);
                        } else {
                            echo "-";
                        }
                        ?>
                    </td>
                    <td>
                        <a href="invoice-view.php?id=<?php echo $inv['id']; ?>" class="text-link">View</a>
                        <?php if ($inv['status'] === 'unpaid'): ?>
                            | <a href="invoice-mark-paid.php?id=<?php echo $inv['id']; ?>" class="text-link">Mark Paid</a>
                            | <a href="invoice-edit.php?id=<?php echo $inv['id']; ?>" class="text-link">Edit</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="8">No invoices found for this filter.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include "includes/footer.php"; ?>
