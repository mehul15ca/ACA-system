<?php
require_once __DIR__ . '/_bootstrap.php';

AdminGuard::requirePermission(Permissions::BATCH_SCHEDULE_MANAGE);

// Fetch schedules
$stmt = $conn->prepare("
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
");
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

$days_map = [1=>'Mon',2=>'Tue',3=>'Wed',4=>'Thu',5=>'Fri',6=>'Sat',7=>'Sun'];

date_default_timezone_set('America/Toronto');
$nowDay  = (int)date('N');
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
        <th>ID</th><th>Day</th><th>Time</th><th>Batch</th>
        <th>Age</th><th>Ground</th><th>Coach</th>
        <th>Status</th><th>Now</th><th>Actions</th>
      </tr>
    </thead>
    <tbody>
    <?php if ($result && $result->num_rows): ?>
      <?php while ($r = $result->fetch_assoc()):
        $live = ($r['day_of_week']===$nowDay &&
                 $r['start_time'] <= $nowTime &&
                 $r['end_time'] >= $nowTime);
      ?>
      <tr>
        <td><?php echo (int)$r['id']; ?></td>
        <td><?php echo htmlspecialchars($days_map[$r['day_of_week']] ?? $r['day_of_week']); ?></td>
        <td><?php echo htmlspecialchars(date('g:i A',strtotime($r['start_time'])) . ' - ' . date('g:i A',strtotime($r['end_time']))); ?></td>
        <td><?php echo htmlspecialchars($r['batch_name']); ?></td>
        <td><?php echo htmlspecialchars($r['age_group']); ?></td>
        <td><?php echo htmlspecialchars($r['ground_name']); ?></td>
        <td><?php echo htmlspecialchars($r['coach_name']); ?></td>
        <td><?php echo htmlspecialchars($r['status']); ?></td>
        <td><?php echo $live ? '<span class="badge green">LIVE</span>' : '-'; ?></td>
        <td>
          <a class="text-link" href="edit-schedule.php?id=<?php echo (int)$r['id']; ?>">Edit</a> |
          <a class="text-link" href="copy-schedule.php?id=<?php echo (int)$r['id']; ?>">Copy</a> |
          <form method="POST" action="delete-schedule.php" style="display:inline">
            <?php echo Csrf::field(); ?>
            <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
            <button class="link-button" onclick="return confirm('Delete session?')">Delete</button>
          </form>
        </td>
      </tr>
      <?php endwhile; ?>
    <?php else: ?>
      <tr><td colspan="10">No scheduled sessions.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<?php include "includes/footer.php"; ?>
