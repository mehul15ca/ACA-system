<?php
require_once __DIR__ . '/_bootstrap.php';

AdminGuard::requirePermission(Permissions::ATTENDANCE_VIEW);

date_default_timezone_set('America/Toronto');
$date = ($_GET['date'] ?? '') !== '' ? $_GET['date'] : date('Y-m-d');

$rows = [];
$stmt = $conn->prepare(
    "SELECT al.log_time,
            s.admission_no, s.first_name, s.last_name,
            b.name AS batch_name, g.name AS ground_name
     FROM attendance_logs al
     JOIN students s ON al.student_id=s.id
     LEFT JOIN batches b ON s.batch_id=b.id
     LEFT JOIN grounds g ON al.ground_id=g.id
     WHERE al.log_date=?
     ORDER BY g.name ASC, b.name ASC, s.first_name ASC"
);
$stmt->bind_param("s", $date);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) { $rows[] = $r; }
$stmt->close();
?>

<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Attendance – <?php echo htmlspecialchars($date); ?></h1>

<div class="form-card">
  <form method="GET" style="display:flex;gap:8px;align-items:center">
    <label>Select Date</label>
    <input type="date" name="date" value="<?php echo htmlspecialchars($date); ?>">
    <button class="button-primary">Load</button>
  </form>
</div>

<div class="table-card">
  <div class="table-header"><h2>Attendance Logs</h2></div>
  <table class="acatable">
    <thead>
      <tr><th>#</th><th>Time</th><th>Student</th><th>Admission No</th><th>Batch</th><th>Ground</th></tr>
    </thead>
    <tbody>
    <?php if ($rows): $i=1; foreach ($rows as $r): ?>
      <tr>
        <td><?php echo $i++; ?></td>
        <td><?php echo htmlspecialchars(substr($r['log_time'],0,5)); ?></td>
        <td><?php echo htmlspecialchars($r['first_name'].' '.$r['last_name']); ?></td>
        <td><?php echo htmlspecialchars($r['admission_no']); ?></td>
        <td><?php echo htmlspecialchars($r['batch_name']); ?></td>
        <td><?php echo htmlspecialchars($r['ground_name']); ?></td>
      </tr>
    <?php endforeach; else: ?>
      <tr><td colspan="6">No attendance records for this date.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<?php include "includes/footer.php"; ?>
