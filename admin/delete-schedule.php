<?php
include "../config.php";
checkLogin();

$role = currentUserRole();
if (!in_array($role, ['admin','superadmin'])) {
    http_response_code(403);
    echo "Access denied. Admin/Superadmin only.";
    exit;
}

if (!isset($_GET['id'])) {
    die("Schedule ID missing.");
}
$schedule_id = intval($_GET['id']);

// Fetch schedule for confirmation
$stmt = $conn->prepare("
    SELECT bs.*, b.name AS batch_name
    FROM batch_schedule bs
    LEFT JOIN batches b ON bs.batch_id = b.id
    WHERE bs.id = ?
");
$stmt->bind_param("i", $schedule_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
    die("Schedule not found.");
}
$schedule = $res->fetch_assoc();

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $del = $conn->prepare("DELETE FROM batch_schedule WHERE id = ?");
    $del->bind_param("i", $schedule_id);
    if ($del->execute()) {
        header("Location: batch-schedule.php");
        exit;
    } else {
        $message = "Error deleting schedule: " . $conn->error;
    }
}
?>
<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Delete Session</h1>

<div class="form-card">
    <p>You are about to <strong>delete</strong> this session:</p>
    <p style="margin:8px 0;">
        ID: <strong><?php echo $schedule['id']; ?></strong><br>
        Batch: <strong><?php echo htmlspecialchars($schedule['batch_name']); ?></strong><br>
        Day: <strong><?php echo htmlspecialchars($schedule['day_of_week']); ?></strong><br>
        Time: <strong><?php echo htmlspecialchars(substr($schedule['start_time'],0,5) . " - " . substr($schedule['end_time'],0,5)); ?></strong>
    </p>

    <?php if ($message): ?>
        <p style="margin:8px 0; color:red;"><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>

    <p style="margin:8px 0; font-size:13px;">
        This will permanently remove this scheduled session. Attendance and other records are not affected.
    </p>

    <form method="POST">
        <button type="submit" class="button-primary">Confirm Delete</button>
    </form>
</div>

<p style="margin-top:12px;">
    <a href="batch-schedule.php" class="text-link">⬅ Back to Schedule</a>
</p>

<?php include "includes/footer.php"; ?>
