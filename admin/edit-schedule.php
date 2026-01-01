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
$message = "";

// Fetch schedule
$stmt = $conn->prepare("SELECT * FROM batch_schedule WHERE id = ?");
$stmt->bind_param("i", $schedule_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
    die("Schedule not found.");
}
$schedule = $res->fetch_assoc();

// Fetch dropdown data
$batches_res = $conn->query("SELECT id, name, age_group FROM batches WHERE status='active' ORDER BY name ASC");
$grounds_res = $conn->query("SELECT id, name FROM grounds WHERE status='active' ORDER BY name ASC");
$coaches_res = $conn->query("SELECT id, name FROM coaches WHERE status='active' ORDER BY name ASC");

// Day options
$days_map = [
    1 => "Monday",
    2 => "Tuesday",
    3 => "Wednesday",
    4 => "Thursday",
    5 => "Friday",
    6 => "Saturday",
    7 => "Sunday"
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $batch_id    = intval($_POST['batch_id']);
    $day_of_week = intval($_POST['day_of_week']);
    $start_time  = $_POST['start_time'];
    $end_time    = $_POST['end_time'];
    $ground_id   = intval($_POST['ground_id']);
    $coach_id    = intval($_POST['coach_id']);
    $status      = $_POST['status'];

    if ($batch_id == 0 || $day_of_week == 0 || $ground_id == 0 || $coach_id == 0) {
        $message = "All fields are required.";
    } elseif ($start_time >= $end_time) {
        $message = "Start time must be earlier than end time.";
    } else {
        // Overlap check (exclude this schedule)
        $stmt2 = $conn->prepare("
            SELECT COUNT(*) AS cnt
            FROM batch_schedule
            WHERE day_of_week = ?
              AND (
                    coach_id = ?
                    OR ground_id = ?
                  )
              AND NOT (
                    ? >= end_time
                    OR ? <= start_time
                  )
              AND id <> ?
        ");
        $stmt2->bind_param(
            "iiissi",
            $day_of_week,
            $coach_id,
            $ground_id,
            $start_time,
            $end_time,
            $schedule_id
        );
        $stmt2->execute();
        $overlap = $stmt2->get_result()->fetch_assoc()['cnt'];

        if ($overlap > 0) {
            $message = "Overlap detected. Coach or ground is already booked for that time.";
        } else {
            $up = $conn->prepare("
                UPDATE batch_schedule
                SET batch_id = ?, day_of_week = ?, start_time = ?, end_time = ?, ground_id = ?, coach_id = ?, status = ?
                WHERE id = ?
            ");
            $up->bind_param(
                "iississi",
                $batch_id,
                $day_of_week,
                $start_time,
                $end_time,
                $ground_id,
                $coach_id,
                $status,
                $schedule_id
            );

            if ($up->execute()) {
                $message = "Schedule updated successfully.";
                $schedule['batch_id']    = $batch_id;
                $schedule['day_of_week'] = $day_of_week;
                $schedule['start_time']  = $start_time;
                $schedule['end_time']    = $end_time;
                $schedule['ground_id']   = $ground_id;
                $schedule['coach_id']    = $coach_id;
                $schedule['status']      = $status;
            } else {
                $message = "Error: " . $conn->error;
            }
        }
    }
}
?>
<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Edit Batch Session</h1>

<div class="form-card">
    <?php if ($message): ?>
        <p style="color:red; margin-bottom:10px;"><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>

    <form method="POST">

        <div class="form-row">
            <label>Batch</label>
            <select name="batch_id" required>
                <option value="">-- Select Batch --</option>
                <?php if ($batches_res): ?>
                    <?php while($b = $batches_res->fetch_assoc()): ?>
                        <option value="<?php echo $b['id']; ?>" <?php if ($schedule['batch_id'] == $b['id']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($b['name'] . " (" . $b['age_group'] . ")"); ?>
                        </option>
                    <?php endwhile; ?>
                <?php endif; ?>
            </select>
        </div>

        <div class="form-row">
            <label>Day of Week</label>
            <select name="day_of_week" required>
                <option value="">-- Select Day --</option>
                <?php foreach ($days_map as $num => $label): ?>
                    <option value="<?php echo $num; ?>" <?php if ((int)$schedule['day_of_week'] === $num) echo 'selected'; ?>>
                        <?php echo $label; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-row">
            <label>Start Time</label>
            <input type="time" name="start_time" value="<?php echo htmlspecialchars(substr($schedule['start_time'],0,5)); ?>" required>
        </div>

        <div class="form-row">
            <label>End Time</label>
            <input type="time" name="end_time" value="<?php echo htmlspecialchars(substr($schedule['end_time'],0,5)); ?>" required>
        </div>

        <div class="form-row">
            <label>Ground</label>
            <select name="ground_id" required>
                <option value="">-- Select Ground --</option>
                <?php if ($grounds_res): ?>
                    <?php while($g = $grounds_res->fetch_assoc()): ?>
                        <option value="<?php echo $g['id']; ?>" <?php if ($schedule['ground_id'] == $g['id']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($g['name']); ?>
                        </option>
                    <?php endwhile; ?>
                <?php endif; ?>
            </select>
        </div>

        <div class="form-row">
            <label>Coach</label>
            <select name="coach_id" required>
                <option value="">-- Select Coach --</option>
                <?php if ($coaches_res): ?>
                    <?php while($c = $coaches_res->fetch_assoc()): ?>
                        <option value="<?php echo $c['id']; ?>" <?php if ($schedule['coach_id'] == $c['id']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($c['name']); ?>
                        </option>
                    <?php endwhile; ?>
                <?php endif; ?>
            </select>
        </div>

        <div class="form-row">
            <label>Status</label>
            <select name="status">
                <option value="upcoming"  <?php if ($schedule['status'] === 'upcoming')  echo 'selected'; ?>>upcoming</option>
                <option value="ongoing"   <?php if ($schedule['status'] === 'ongoing')   echo 'selected'; ?>>ongoing</option>
                <option value="completed" <?php if ($schedule['status'] === 'completed') echo 'selected'; ?>>completed</option>
                <option value="cancelled" <?php if ($schedule['status'] === 'cancelled') echo 'selected'; ?>>cancelled</option>
            </select>
        </div>

        <button type="submit" class="button-primary">Save Changes</button>
    </form>

</div>

<p style="margin-top:12px;">
    <a href="batch-schedule.php" class="text-link">⬅ Back to Schedule</a>
</p>

<?php include "includes/footer.php"; ?>
