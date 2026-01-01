<?php
include "../config.php";
checkLogin();
$role = currentUserRole();

include "includes/header.php";
include "includes/sidebar.php";

function getVal($conn, $sql){
  $r=$conn->query($sql);
  if($r&&$r->num_rows){ $a=$r->fetch_assoc(); return array_values($a)[0]??0;}
  return 0;
}

$totalStudents = getVal($conn,"SELECT COUNT(*) FROM students");
$totalCoaches = getVal($conn,"SELECT COUNT(*) FROM coaches");
$totalBatches = getVal($conn,"SELECT COUNT(*) FROM batches");
$totalGrounds = getVal($conn,"SELECT COUNT(*) FROM grounds");

$today=date('Y-m-d');
$todayAttendance=getVal($conn,"SELECT COUNT(DISTINCT student_id) FROM attendance_logs WHERE log_date='$today'");

$pendingFees=getVal($conn,"SELECT SUM(amount) FROM fees_invoices WHERE status IN ('unpaid','partial')");

$ym=date('Y-m');
$incomeMonth=getVal($conn,"SELECT SUM(amount) FROM fees_payments WHERE DATE_FORMAT(paid_on,'%Y-%m')='$ym'");
$expensesMonth=getVal($conn,"SELECT SUM(total_amount) FROM expenses WHERE DATE_FORMAT(expense_date,'%Y-%m')='$ym'");

$pendingSalary=getVal($conn,"SELECT SUM(amount) FROM coach_salary_sessions WHERE status!='paid'");

$att7 = getVal($conn,"
  SELECT COUNT(*) FROM attendance_logs 
  WHERE log_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND CURDATE()
");
$totalPossible = $totalStudents*7;
$attPct = $totalPossible>0 ? round(($att7/$totalPossible)*100,1) : 0;

?>
<div class='page-header'>
  <h1>ACA Admin Dashboard</h1>
</div>

<div class='card-grid'>
  <div class='card'><h2>Students</h2><p><?= $totalStudents ?></p></div>
  <div class='card'><h2>Coaches</h2><p><?= $totalCoaches ?></p></div>
  <div class='card'><h2>Batches</h2><p><?= $totalBatches ?></p></div>
  <div class='card'><h2>Grounds</h2><p><?= $totalGrounds ?></p></div>
</div>

<div class='card-grid'>
  <div class='card'><h2>Today Attendance</h2><p><?= $todayAttendance ?></p></div>
  <div class='card'><h2>Pending Fees</h2><p>CAD <?= number_format($pendingFees,2) ?></p></div>
  <div class='card'><h2>Income (<?= date('M') ?>)</h2><p>CAD <?= number_format($incomeMonth,2) ?></p></div>
  <div class='card'><h2>Expenses (<?= date('M') ?>)</h2><p>CAD <?= number_format($expensesMonth,2) ?></p></div>
</div>

<div class='card-grid'>
  <div class='card'><h2>Pending Salary</h2><p>CAD <?= number_format($pendingSalary,2) ?></p></div>
  <div class='card'><h2>Attendance Compliance</h2><p><?= $attPct ?>%</p></div>
</div>

<div class='card'>
  <h2>Quick Links</h2>
  <ul>
    <li><a href='students.php'>Manage Students</a></li>
    <li><a href='coaches.php'>Manage Coaches</a></li>
    <li><a href='batch-schedule.php'>Batch Schedule</a></li>
    <li><a href='fees-invoices.php'>Fees & Invoices</a></li>
    <li><a href='expenses-dashboard.php'>Expenses Dashboard</a></li>
  </ul>
</div>

<?php include "includes/footer.php"; ?>