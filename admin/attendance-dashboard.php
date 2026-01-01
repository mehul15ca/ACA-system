<?php
include "../config.php";
checkLogin();

$role = currentUserRole();
if (!in_array($role, ['admin','superadmin'])) {
    http_response_code(403);
    echo "Access denied.";
    exit;
}

date_default_timezone_set('America/Toronto');

$today = date('Y-m-d');
$selectedDate = isset($_GET['date']) && $_GET['date'] !== '' ? $_GET['date'] : $today;
$isPrint = isset($_GET['print']) && $_GET['print'] == '1';

// Total active students
$resTotal = $conn->query("SELECT COUNT(*) AS cnt FROM students WHERE status='active'");
$rowTotal = $resTotal ? $resTotal->fetch_assoc() : ['cnt' => 0];
$totalStudents = (int)$rowTotal['cnt'];

// Present today (distinct students)
$stmtPresent = $conn->prepare("SELECT COUNT(DISTINCT student_id) AS cnt FROM attendance_logs WHERE log_date = ?");
$stmtPresent->bind_param("s", $selectedDate);
$stmtPresent->execute();
$resPresent = $stmtPresent->get_result()->fetch_assoc();
$presentToday = (int)$resPresent['cnt'];

$attendancePercent = $totalStudents > 0 ? round(($presentToday / $totalStudents) * 100) : 0;

// Batch-wise summary
$sqlBatch = "
    SELECT b.id, b.name, b.age_group,
           COUNT(DISTINCT s.id) AS total_students,
           COUNT(DISTINCT CASE WHEN al.log_date = ? THEN s.id END) AS present_students
    FROM batches b
    LEFT JOIN students s ON s.batch_id = b.id AND s.status = 'active'
    LEFT JOIN attendance_logs al ON al.student_id = s.id
    GROUP BY b.id, b.name, b.age_group
    ORDER BY b.name ASC
";
$stmtB = $conn->prepare($sqlBatch);
$stmtB->bind_param("s", $selectedDate);
$stmtB->execute();
$resBatch = $stmtB->get_result();

// Ground-wise summary
$sqlGround = "
    SELECT g.id, g.name,
           COUNT(DISTINCT al.student_id) AS present_students
    FROM grounds g
    LEFT JOIN attendance_logs al
        ON al.ground_id = g.id
       AND al.log_date = ?
    WHERE g.status = 'active'
    GROUP BY g.id, g.name
    ORDER BY g.name ASC
";
$stmtG = $conn->prepare($sqlGround);
$stmtG->bind_param("s", $selectedDate);
$stmtG->execute();
$resGround = $stmtG->get_result();

// Last 7 days trend
$trend = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime($selectedDate . " -$i day"));
    $st = $conn->prepare("SELECT COUNT(DISTINCT student_id) AS cnt FROM attendance_logs WHERE log_date = ?");
    $st->bind_param("s", $d);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $trend[] = [
        'date' => $d,
        'count' => (int)$row['cnt']
    ];
}

// Simple HTML for print or normal
if ($isPrint):
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Attendance Dashboard Report - <?php echo htmlspecialchars($selectedDate); ?></title>
    <style>
        body {
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            padding:20px;
            color:#111827;
        }
        h1 {
            margin-bottom:4px;
        }
        h2 {
            margin-top:20px;
            margin-bottom:8px;
        }
        table {
            border-collapse:collapse;
            width:100%;
            margin-bottom:12px;
        }
        th, td {
            border:1px solid #d1d5db;
            padding:6px 8px;
            font-size:13px;
        }
        th {
            background:#f3f4f6;
        }
        .summary-grid {
            display:flex;
            gap:16px;
            margin-top:12px;
        }
        .card {
            border:1px solid #d1d5db;
            border-radius:8px;
            padding:10px 12px;
            flex:1;
            font-size:13px;
        }
        .card-title {
            font-size:12px;
            color:#6b7280;
            text-transform:uppercase;
            letter-spacing:0.04em;
        }
        .card-value {
            font-size:20px;
            font-weight:600;
            margin-top:4px;
        }
        .card-note {
            font-size:11px;
            color:#6b7280;
            margin-top:2px;
        }
        .trend-row td {
            font-size:11px;
        }
    </style>
</head>
<body onload="window.print()">
    <h1>Attendance Dashboard</h1>
    <div style="font-size:13px; color:#4b5563;">
        Date: <strong><?php echo htmlspecialchars($selectedDate); ?></strong><br>
        Generated at: <?php echo date('Y-m-d H:i'); ?>
    </div>

    <div class="summary-grid">
        <div class="card">
            <div class="card-title">Total Active Students</div>
            <div class="card-value"><?php echo $totalStudents; ?></div>
        </div>
        <div class="card">
            <div class="card-title">Present on <?php echo htmlspecialchars($selectedDate); ?></div>
            <div class="card-value"><?php echo $presentToday; ?></div>
        </div>
        <div class="card">
            <div class="card-title">Attendance %</div>
            <div class="card-value"><?php echo $attendancePercent; ?>%</div>
        </div>
    </div>

    <h2>Batch-wise Summary</h2>
    <table>
        <thead>
            <tr>
                <th>Batch</th>
                <th>Age Group</th>
                <th>Total Students</th>
                <th>Present</th>
                <th>Attendance %</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($resBatch && $resBatch->num_rows > 0): ?>
            <?php while ($b = $resBatch->fetch_assoc()): ?>
                <?php
                    $ts = (int)$b['total_students'];
                    $ps = (int)$b['present_students'];
                    $pct = $ts > 0 ? round(($ps / $ts) * 100) : 0;
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($b['name']); ?></td>
                    <td><?php echo htmlspecialchars($b['age_group']); ?></td>
                    <td><?php echo $ts; ?></td>
                    <td><?php echo $ps; ?></td>
                    <td><?php echo $pct; ?>%</td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="5">No batches found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>

    <h2>Ground-wise Summary</h2>
    <table>
        <thead>
            <tr>
                <th>Ground</th>
                <th>Present Students</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($resGround && $resGround->num_rows > 0): ?>
            <?php while ($g = $resGround->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($g['name']); ?></td>
                    <td><?php echo (int)$g['present_students']; ?></td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="2">No grounds found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>

    <h2>Last 7 Days Trend</h2>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Present Students</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($trend as $t): ?>
            <tr class="trend-row">
                <td><?php echo htmlspecialchars($t['date']); ?></td>
                <td><?php echo $t['count']; ?></td>
            </tr>
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

<div class="form-card" style="margin-bottom:16px;">
    <form method="GET" style="display:flex; flex-wrap:wrap; gap:10px; align-items:center;">
        <div>
            <label style="font-size:12px; display:block; margin-bottom:2px;">Select Date</label>
            <input type="date" name="date" value="<?php echo htmlspecialchars($selectedDate); ?>">
        </div>
        <div>
            <button type="submit" class="button-primary">Load</button>
        </div>
        <div style="margin-left:auto;">
            <a href="attendance-dashboard.php?date=<?php echo urlencode($selectedDate); ?>&print=1"
               class="button" target="_blank">🧾 Export PDF (Print View)</a>
        </div>
    </form>
</div>

<div class="summary-grid">
    <div class="card">
        <div class="card-title">Total Active Students</div>
        <div class="card-value"><?php echo $totalStudents; ?></div>
        <div class="card-note">Students with status = active.</div>
    </div>
    <div class="card">
        <div class="card-title">Present on <?php echo htmlspecialchars($selectedDate); ?></div>
        <div class="card-value"><?php echo $presentToday; ?></div>
        <div class="card-note">Distinct students who tapped IN.</div>
    </div>
    <div class="card">
        <div class="card-title">Attendance %</div>
        <div class="card-value"><?php echo $attendancePercent; ?>%</div>
        <div class="card-note">Present vs total active.</div>
    </div>
</div>

<div class="table-card">
    <div class="table-header">
        <h2>Batch-wise Summary</h2>
    </div>
    <table class="acatable">
        <thead>
        <tr>
            <th>Batch</th>
            <th>Age Group</th>
            <th>Total Students</th>
            <th>Present</th>
            <th>Attendance %</th>
        </tr>
        </thead>
        <tbody>
        <?php if ($resBatch && $resBatch->num_rows > 0): ?>
            <?php while ($b = $resBatch->fetch_assoc()): ?>
                <?php
                    $ts = (int)$b['total_students'];
                    $ps = (int)$b['present_students'];
                    $pct = $ts > 0 ? round(($ps / $ts) * 100) : 0;
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($b['name']); ?></td>
                    <td><?php echo htmlspecialchars($b['age_group']); ?></td>
                    <td><?php echo $ts; ?></td>
                    <td><?php echo $ps; ?></td>
                    <td><?php echo $pct; ?>%</td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="5">No batches found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="table-card">
    <div class="table-header">
        <h2>Ground-wise Summary</h2>
    </div>
    <table class="acatable">
        <thead>
        <tr>
            <th>Ground</th>
            <th>Present Students</th>
        </tr>
        </thead>
        <tbody>
        <?php if ($resGround && $resGround->num_rows > 0): ?>
            <?php while ($g = $resGround->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($g['name']); ?></td>
                    <td><?php echo (int)$g['present_students']; ?></td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="2">No grounds found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="table-card">
    <div class="table-header">
        <h2>Last 7 Days Trend</h2>
    </div>
    <table class="acatable">
        <thead>
            <tr>
                <th>Date</th>
                <th>Present Students</th>
                <th>Intensity</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $maxCount = 0;
        foreach ($trend as $t) {
            if ($t['count'] > $maxCount) $maxCount = $t['count'];
        }
        foreach ($trend as $t):
            $ratio = $maxCount > 0 ? $t['count'] / $maxCount : 0;
            $green = (int)(180 + 60 * $ratio);
            $red   = (int)(255 - 120 * $ratio);
            $bg = "rgb($red,$green,170)";
        ?>
            <tr>
                <td><?php echo htmlspecialchars($t['date']); ?></td>
                <td><?php echo $t['count']; ?></td>
                <td>
                    <div style="height:10px; border-radius:999px; background:<?php echo $bg; ?>;"></div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include "includes/footer.php"; ?>
