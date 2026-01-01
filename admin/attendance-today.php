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
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Fetch logs with joins
$sql = "
    SELECT al.*, s.admission_no, s.first_name, s.last_name,
           b.name AS batch_name, g.name AS ground_name
    FROM attendance_logs al
    JOIN students s ON al.student_id = s.id
    LEFT JOIN batches b ON s.batch_id = b.id
    LEFT JOIN grounds g ON al.ground_id = g.id
    WHERE al.log_date = ?
    ORDER BY g.name ASC, b.name ASC, s.first_name ASC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $date);
$stmt->execute();
$result = $stmt->get_result();
?>
<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Attendance – <?php echo htmlspecialchars($date); ?></h1>

<div class="form-card" style="margin-bottom:16px;">
    <form method="GET">
        <div class="form-row" style="display:flex;align-items:center;gap:8px;">
            <label>Select Date</label>
            <input type="date" name="date" value="<?php echo htmlspecialchars($date); ?>">
            <button type="submit" class="button-primary">Load</button>
        </div>
    </form>
</div>

<div class="table-card">
    <div class="table-header">
        <h2>Attendance Logs</h2>
    </div>

    <table class="acatable">
        <thead>
            <tr>
                <th>#</th>
                <th>Time</th>
                <th>Student</th>
                <th>Admission No</th>
                <th>Batch</th>
                <th>Ground</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($result && $result->num_rows > 0): ?>
            <?php $i=1; ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $i++; ?></td>
                    <td><?php echo htmlspecialchars(substr($row['log_time'],0,5)); ?></td>
                    <td><?php echo htmlspecialchars($row['first_name'] . " " . $row['last_name']); ?></td>
                    <td><?php echo htmlspecialchars($row['admission_no']); ?></td>
                    <td><?php echo htmlspecialchars($row['batch_name']); ?></td>
                    <td><?php echo htmlspecialchars($row['ground_name']); ?></td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="6">No attendance records for this date.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include "includes/footer.php"; ?>
