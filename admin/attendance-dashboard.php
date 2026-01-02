<?php
require_once __DIR__ . '/_bootstrap.php';

AdminGuard::requirePermission(Permissions::ATTENDANCE_VIEW);

date_default_timezone_set('America/Toronto');

$today = date('Y-m-d');
$selectedDate = ($_GET['date'] ?? '') !== '' ? $_GET['date'] : $today;
$isPrint = (($_GET['print'] ?? '') === '1');

// Total active students
$totalStudents = 0;
$r = $conn->query("SELECT COUNT(*) AS cnt FROM students WHERE status='active'");
if ($r) {
    $row = $r->fetch_assoc();
    $totalStudents = (int)$row['cnt'];
}

// Present today
$presentToday = 0;
$stmt = $conn->prepare("SELECT COUNT(DISTINCT student_id) AS cnt FROM attendance_logs WHERE log_date=?");
$stmt->bind_param("s", $selectedDate);
$stmt->execute();
$stmt->bind_result($presentToday);
$stmt->fetch();
$stmt->close();

$attendancePercent = $totalStudents > 0 ? round(($presentToday / $totalStudents) * 100) : 0;

// Batch-wise
$batchRows = [];
$stmtB = $conn->prepare(
    "SELECT b.id, b.name, b.age_group,
            COUNT(DISTINCT s.id) AS total_students,
            COUNT(DISTINCT CASE WHEN al.log_date=? THEN s.id END) AS present_students
     FROM batches b
     LEFT JOIN students s ON s.batch_id=b.id AND s.status='active'
     LEFT JOIN attendance_logs al ON al.student_id=s.id
     GROUP BY b.id, b.name, b.age_group
     ORDER BY b.name ASC"
);
$stmtB->bind_param("s", $selectedDate);
$stmtB->execute();
$resB = $stmtB->get_result();
while ($row = $resB->fetch_assoc()) { $batchRows[] = $row; }
$stmtB->close();

// Ground-wise
$groundRows = [];
$stmtG = $conn->prepare(
    "SELECT g.id, g.name,
            COUNT(DISTINCT al.student_id) AS present_students
     FROM grounds g
     LEFT JOIN attendance_logs al
       ON al.ground_id=g.id AND al.log_date=?
     WHERE g.status='active'
     GROUP BY g.id, g.name
     ORDER BY g.name ASC"
);
$stmtG->bind_param("s", $selectedDate);
$stmtG->execute();
$resG = $stmtG->get_result();
while ($row = $resG->fetch_assoc()) { $groundRows[] = $row; }
$stmtG->close();

// Trend (last 7 days)
$trend = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime($selectedDate . " -$i day"));
    $cnt = 0;
    $st = $conn->prepare("SELECT COUNT(DISTINCT student_id) FROM attendance_logs WHERE log_date=?");
    $st->bind_param("s", $d);
    $st->execute();
    $st->bind_result($cnt);
    $st->fetch();
    $st->close();
    $trend[] = ['date'=>$d, 'count'=>$cnt];
}

// Print view
if ($isPrint):
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Attendance Dashboard – <?php echo htmlspecialchars($selectedDate); ?></title>
<style>
body{font-family:system-ui,sans-serif;color:#111827;padding:20px}
h1{margin:0}
table{border-collapse:collapse;width:100%;margin:12px 0}
th,td{border:1px solid #d1d5db;padding:6px 8px;font-size:13px}
th{background:#f3f4f6}
.cards{display:flex;gap:16px;margin:12px 0}
.card{border:1px solid #d1d5db;border-radius:8px;padding:10px;flex:1}
.small{font-size:11px;color:#6b7280}
</style>
</head>
<body onload="window.print()">
<h1>Attendance Dashboard</h1>
<div class="small">Date: <strong><?php echo htmlspecialchars($selectedDate); ?></strong></div>

<div class="cards">
  <div class="card"><div class="small">Total Active Students</div><div style="font-size:20px"><?php echo $totalStudents; ?></div></div>
  <div class="card"><div class="small">Present</div><div style="font-size:20px"><?php echo $presentToday; ?></div></div>
  <div class="card"><div class="small">Attendance %</div><div style="font-size:20px"><?php echo $attendancePercent; ?>%</div></div>
</div>

<h2>Batch-wise</h2>
<table>
<thead><tr><th>Batch</th><th>Age</th><th>Total</th><th>Present</th><th>%</th></tr></thead>
<tbody>
<?php foreach ($batchRows as $b): $ts=(int)$b['total_students']; $ps=(int)$b['present_students']; $pct=$ts?round(($ps/$ts)*100):0; ?>
<tr>
<td><?php echo htmlspecialchars($b['name']); ?></td>
<td><?php echo htmlspecialchars($b['age_group']); ?></td>
<td><?php echo $ts; ?></td>
<td><?php echo $ps; ?></td>
<td><?php echo $pct; ?>%</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

<h2>Ground-wise</h2>
<table>
<thead><tr><th>Ground</th><th>Present</th></tr></thead>
<tbody>
<?php foreach ($groundRows as $g): ?>
<tr><td><?php echo htmlspecialchars($g['name']); ?></td><td><?php echo (int)$g['present_students']; ?></td></tr>
<?php endforeach; ?>
</tbody>
</table>

<h2>Last 7 Days</h2>
<table>
<thead><tr><th>Date</th><th>Present</th></tr></thead>
<tbody>
<?php foreach ($trend as $t): ?>
<tr><td><?php echo htmlspecialchars($t['date']); ?></td><td><?php echo (int)$t['count']; ?></td></tr>
<?php endforeach; ?>
</tbody>
</table>
</body>
</html>
<?php
exit;
endif;
?>

<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Attendance Dashboard</h1>

<div class="form-card">
  <form method="GET" style="display:flex;gap:10px;align-items:center">
    <div>
      <label style="font-size:12px">Select Date</label>
      <input type="date" name="date" value="<?php echo htmlspecialchars($selectedDate); ?>">
    </div>
    <button class="button-primary">Load</button>
    <div style="margin-left:auto">
      <a class="button" target="_blank"
         href="attendance-dashboard.php?date=<?php echo urlencode($selectedDate); ?>&print=1">🧾 Export</a>
    </div>
  </form>
</div>

<div class="summary-grid">
  <div class="card"><div class="card-title">Total Active</div><div class="card-value"><?php echo $totalStudents; ?></div></div>
  <div class="card"><div class="card-title">Present</div><div class="card-value"><?php echo $presentToday; ?></div></div>
  <div class="card"><div class="card-title">Attendance %</div><div class="card-value"><?php echo $attendancePercent; ?>%</div></div>
</div>

<div class="table-card">
  <div class="table-header"><h2>Batch-wise Summary</h2></div>
  <table class="acatable">
    <thead><tr><th>Batch</th><th>Age</th><th>Total</th><th>Present</th><th>%</th></tr></thead>
    <tbody>
    <?php foreach ($batchRows as $b): $ts=(int)$b['total_students']; $ps=(int)$b['present_students']; $pct=$ts?round(($ps/$ts)*100):0; ?>
      <tr>
        <td><?php echo htmlspecialchars($b['name']); ?></td>
        <td><?php echo htmlspecialchars($b['age_group']); ?></td>
        <td><?php echo $ts; ?></td>
        <td><?php echo $ps; ?></td>
        <td><?php echo $pct; ?>%</td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="table-card">
  <div class="table-header"><h2>Ground-wise Summary</h2></div>
  <table class="acatable">
    <thead><tr><th>Ground</th><th>Present</th></tr></thead>
    <tbody>
    <?php foreach ($groundRows as $g): ?>
      <tr><td><?php echo htmlspecialchars($g['name']); ?></td><td><?php echo (int)$g['present_students']; ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="table-card">
  <div class="table-header"><h2>Last 7 Days Trend</h2></div>
  <table class="acatable">
    <thead><tr><th>Date</th><th>Present</th><th>Intensity</th></tr></thead>
    <tbody>
    <?php
      $max = 0; foreach ($trend as $t) if ($t['count'] > $max) $max = $t['count'];
      foreach ($trend as $t):
        $ratio = $max ? $t['count']/$max : 0;
        $green = (int)(180 + 60*$ratio);
        $red   = (int)(255 - 120*$ratio);
        $bg = "rgb($red,$green,170)";
    ?>
      <tr>
        <td><?php echo htmlspecialchars($t['date']); ?></td>
        <td><?php echo (int)$t['count']; ?></td>
        <td><div style="height:10px;border-radius:999px;background:<?php echo $bg; ?>"></div></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php include "includes/footer.php"; ?>
