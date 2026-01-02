<?php
require_once __DIR__ . '/_bootstrap.php';
AdminGuard::requireAnyRole(['admin','superadmin']);

function val(mysqli $c, string $q): float {
    $r = $c->query($q);
    return $r && $r->num_rows ? (float)array_values($r->fetch_assoc())[0] : 0;
}

$today = date('Y-m-d');
$ym = date('Y-m');

$data = [
    'students' => val($conn,"SELECT COUNT(*) FROM students"),
    'coaches'  => val($conn,"SELECT COUNT(*) FROM coaches"),
    'batches'  => val($conn,"SELECT COUNT(*) FROM batches"),
    'grounds'  => val($conn,"SELECT COUNT(*) FROM grounds"),
    'attendance_today' => val($conn,"SELECT COUNT(DISTINCT student_id) FROM attendance_logs WHERE log_date='$today'"),
    'pending_fees' => val($conn,"SELECT SUM(amount) FROM fees_invoices WHERE status IN ('unpaid','partial')"),
    'income' => val($conn,"SELECT SUM(amount) FROM fees_payments WHERE DATE_FORMAT(paid_on,'%Y-%m')='$ym'"),
    'expenses' => val($conn,"SELECT SUM(total_amount) FROM expenses WHERE DATE_FORMAT(expense_date,'%Y-%m')='$ym'"),
    'pending_salary' => val($conn,"SELECT SUM(amount) FROM coach_salary_sessions WHERE status!='paid'")
];
?>

<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Admin Dashboard</h1>

<div class="card-grid">
<?php foreach ($data as $k=>$v): ?>
  <div class="card">
    <h2><?php echo ucwords(str_replace('_',' ',$k)); ?></h2>
    <p><?php echo number_format($v,2); ?></p>
  </div>
<?php endforeach; ?>
</div>

<?php include "includes/footer.php"; ?>
