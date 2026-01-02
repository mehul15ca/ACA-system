<?php
require_once __DIR__ . '/_bootstrap.php';

AdminGuard::requirePermission(Permissions::SCHEDULES_MANAGE);

$message = '';

// Fetch dropdown data
$batches = [];
$grounds = [];
$coaches = [];

$r = $conn->query("SELECT id, name, age_group FROM batches WHERE status='active' ORDER BY name");
while ($row = $r->fetch_assoc()) { $batches[] = $row; }

$r = $conn->query("SELECT id, name FROM grounds WHERE status='active' ORDER BY name");
while ($row = $r->fetch_assoc()) { $grounds[] = $row; }

$r = $conn->query("SELECT id, name FROM coaches WHERE status='active' ORDER BY name");
while ($row = $r->fetch_assoc()) { $coaches[] = $row; }

$days = [
    1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday',
    4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $batch_id    = (int)($_POST['batch_id'] ?? 0);
    $day_of_week = (int)($_POST['day_of_week'] ?? 0);
    $start_time  = $_POST['start_time'] ?? '';
    $end_time    = $_POST['end_time'] ?? '';
    $ground_id   = (int)($_POST['ground_id'] ?? 0);
    $coach_id    = (int)($_POST['coach_id'] ?? 0);
    $status      = $_POST['status'] ?? 'upcoming';

    if ($batch_id <= 0 || $day_of_week <= 0 || $ground_id <= 0 || $coach_id <= 0 || $start_time === '' || $end_time === '') {
        $message = 'All fields are required.';
    } elseif ($start_time >= $end_time) {
        $message = 'Start time must be earlier than end time.';
    } else {
        $chk = $conn->prepare(
            "SELECT COUNT(*) FROM batch_schedule
             WHERE day_of_week=?
               AND (coach_id=? OR ground_id=?)
               AND NOT (? >= end_time OR ? <= start_time)"
        );
        $chk->bind_param("iiiss", $day_of_week, $coach_id, $ground_id, $start_time, $end_time);
        $chk->execute();
        $chk->bind_result($cnt);
        $chk->fetch();
        $chk->close();

        if ($cnt > 0) {
            $message = 'Overlap detected for coach or ground.';
        } else {
            $ins = $conn->prepare(
                "INSERT INTO batch_schedule
                 (batch_id, day_of_week, start_time, end_time, ground_id, coach_id, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $ins->bind_param("iississ", $batch_id, $day_of_week, $start_time, $end_time, $ground_id, $coach_id, $status);

            if ($ins->execute()) {
                header("Location: batch-schedule.php");
                exit;
            }
            $message = 'Database error.';
            $ins->close();
        }
    }
}
?>

<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Add Batch Session</h1>

<div class="form-card">
    <?php if ($message !== ''): ?>
        <div class="alert-error"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <form method="POST">
        <?php echo Csrf::field(); ?>

        <div class="form-row">
            <label>Batch</label>
            <select name="batch_id" required>
                <option value="">-- Select Batch --</option>
                <?php foreach ($batches as $b): ?>
                    <option value="<?php echo (int)$b['id']; ?>">
                        <?php echo htmlspecialchars($b['name'] . ' (' . $b['age_group'] . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-row">
            <label>Day of Week</label>
            <select name="day_of_week" required>
                <option value="">-- Select Day --</option>
                <?php foreach ($days as $k => $v): ?>
                    <option value="<?php echo $k; ?>"><?php echo $v; ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-row">
            <label>Start Time</label>
            <input type="time" name="start_time" required>
        </div>

        <div class="form-row">
            <label>End Time</label>
            <input type="time" name="end_time" required>
        </div>

        <div class="form-row">
            <label>Ground</label>
            <select name="ground_id" required>
                <option value="">-- Select Ground --</option>
                <?php foreach ($grounds as $g): ?>
                    <option value="<?php echo (int)$g['id']; ?>">
                        <?php echo htmlspecialchars($g['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-row">
            <label>Coach</label>
            <select name="coach_id" required>
                <option value="">-- Select Coach --</option>
                <?php foreach ($coaches as $c): ?>
                    <option value="<?php echo (int)$c['id']; ?>">
                        <?php echo htmlspecialchars($c['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-row">
            <label>Status</label>
            <select name="status">
                <option value="upcoming">upcoming</option>
                <option value="ongoing">ongoing</option>
                <option value="completed">completed</option>
                <option value="cancelled">cancelled</option>
            </select>
        </div>

        <button class="button-primary">Save Session</button>
    </form>
</div>

<p><a href="batch-schedule.php" class="text-link">⬅ Back</a></p>

<?php include "includes/footer.php"; ?>
