<?php
include "../config.php";
checkLogin();
if (!hasPermission('manage_batch_schedule')) {
    http_response_code(403);
    echo "Access denied.";
    exit;
}


$role = currentUserRole();
if (!in_array($role, ['admin','superadmin'])) {
    http_response_code(403);
    echo "Access denied. Admin/Superadmin only.";
    exit;
}

// Fetch all schedules with joins
$sql = "
    SELECT bs.*,
           b.name AS batch_name,
           b.age_group,
           g.name AS ground_name,
           c.name AS coach_name
    FROM batch_schedule bs
    LEFT JOIN batches b ON bs.batch_id = b.id
    LEFT JOIN grounds g ON bs.ground_id = g.id
    LEFT JOIN coaches c ON bs.coach_id = c.id
    ORDER BY bs.day_of_week, bs.start_time
";
$result = $conn->query($sql);

// Helper for day names
$days_map = [
    1 => 'Mon',
    2 => 'Tue',
    3 => 'Wed',
    4 => 'Thu',
    5 => 'Fri',
    6 => 'Sat',
    7 => 'Sun'
];

// Toronto time (adjust if needed)
date_default_timezone_set('America/Toronto');
$nowDay  = (int)date('N');  // 1-7 (Mon–Sun)
$nowTime = date('H:i:s');
?>
<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Batch Schedule</h1>

<div class="table-card">
    <div class="table-header">
        <h2>All Scheduled Sessions</h2>
        <a href="add-schedule.php" class="button">➕ Add Session</a>
    </div>

    <table class="acatable">
        <thead>
            <tr>
                <th>ID</th>
                <th>Day</th>
                <th>Time</th>
                <th>Batch</th>
                <th>Age Group</th>
                <th>Ground</th>
                <th>Coach</th>
                <th>Status</th>
                <th>Now</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($result && $result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <?php
                    $dayNum = (int)$row['day_of_week'];
                    $dayLabel = isset($days_map[$dayNum]) ? $days_map[$dayNum] : $dayNum;

                    // 12-hour time format
                    $start12 = date('g:i A', strtotime($row['start_time']));
                    $end12   = date('g:i A', strtotime($row['end_time']));

                    // Live indicator
                    $isLive = false;
                    if ($dayNum === $nowDay && $row['start_time'] <= $nowTime && $row['end_time'] >= $nowTime) {
                        $isLive = true;
                    }
                ?>
                <tr>
                    <td><?php echo $row['id']; ?></td>
                    <td><?php echo htmlspecialchars($dayLabel); ?></td>
                    <td><?php echo htmlspecialchars($start12 . " - " . $end12); ?></td>
                    <td><?php echo htmlspecialchars($row['batch_name']); ?></td>
                    <td><?php echo htmlspecialchars($row['age_group']); ?></td>
                    <td><?php echo htmlspecialchars($row['ground_name']); ?></td>
                    <td><?php echo htmlspecialchars($row['coach_name']); ?></td>
                    <td><?php echo htmlspecialchars($row['status']); ?></td>
                    <td>
                        <?php if ($isLive): ?>
                            <span class="badge green">LIVE</span>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td>
                        <a class="text-link" href="edit-schedule.php?id=<?php echo $row['id']; ?>">Edit</a>
                        |
                        <a class="text-link" href="copy-schedule.php?id=<?php echo $row['id']; ?>">Copy</a>
                        |
                        <a class="text-link" href="delete-schedule.php?id=<?php echo $row['id']; ?>">Delete</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="10">No scheduled sessions yet.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include "includes/footer.php"; ?>
