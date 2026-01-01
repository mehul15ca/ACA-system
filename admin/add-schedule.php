<?php
include "../config.php";
checkLogin();

$role = currentUserRole();
if (!in_array($role, ['admin','superadmin'])) {
    http_response_code(403);
    echo "Access denied. Admin/Superadmin only.";
    exit;
}

$message = "";

// Fetch dropdown data
$batches_res = $conn->query("SELECT id, name, age_group FROM batches WHERE status='active' ORDER BY name ASC");
$grounds_res = $conn->query("SELECT id, name FROM grounds WHERE status='active' ORDER BY name ASC");
$coaches_res = $conn->query("SELECT id, name FROM coaches WHERE status='active' ORDER BY name ASC");

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
        // Overlap check
        $stmt = $conn->prepare("
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
        ");
        $stmt->bind_param(
            "iiiss",
            $day_of_week,
            $coach_id,
            $ground_id,
            $start_time,
            $end_time
        );
        $stmt->execute();
        $overlap = $stmt->get_result()->fetch_assoc()['cnt'];

        if ($overlap > 0) {
            $message = "Overlap detected. Coach or ground is already booked for that time.";
        } else {
            $insert = $conn->prepare("
                INSERT INTO batch_schedule (batch_id, day_of_week, start_time, end_time, ground_id, coach_id, status)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $insert->bind_param(
                "iississ",
                $batch_id,
                $day_of_week,
                $start_time,
                $end_time,
                $ground_id,
                $coach_id,
                $status
            );

            if ($insert->execute()) {
                header("Location: batch-schedule.php");
                exit;
            } else {
                $message = "Error: " . $conn->error;
            }
        }
    }
}

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
?>
<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Add Batch Session</h1>

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
                        <option value="<?php echo $b['id']; ?>">
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
                    <option value="<?php echo $num; ?>"><?php echo $label; ?></option>
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
                <?php if ($grounds_res): ?>
                    <?php while($g = $grounds_res->fetch_assoc()): ?>
                        <option value="<?php echo $g['id']; ?>">
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
                        <option value="<?php echo $c['id']; ?>">
                            <?php echo htmlspecialchars($c['name']); ?>
                        </option>
                    <?php endwhile; ?>
                <?php endif; ?>
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

        <button type="submit" class="button-primary">Save Session</button>
    </form>

    <p style="margin-top:10px; font-size:12px;">
        Overlap protection: the system will block sessions where the same coach or ground is already booked on that day and time.
    </p>
</div>

<p style="margin-top:12px;">
    <a href="batch-schedule.php" class="text-link">⬅ Back to Schedule</a>
</p>

<?php include "includes/footer.php"; ?>
