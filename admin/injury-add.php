<?php
require_once __DIR__ . '/../includes/security/csrf.php';
include "../config.php";
checkLogin();

$role = currentUserRole();
if (!in_array($role, ['admin','superadmin','coach'])) {
    http_response_code(403);
    echo "Access denied.";
    exit;
}

$message = "";
$success = "";

$students_res = $conn->query("SELECT id, admission_no, first_name, last_name FROM students WHERE status='active' ORDER BY first_name ASC");
$coaches_res  = $conn->query("SELECT id, name FROM coaches WHERE status='active' ORDER BY name ASC");

$defaultCoachId = "";
if ($role === 'coach') {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $userId = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
    if ($userId > 0) {
        $stmt = $conn->prepare("SELECT coach_id FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if ($row && $row['coach_id']) {
            $defaultCoachId = intval($row['coach_id']);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::validateRequest();
    $student_id    = intval($_POST['student_id']);
    $coach_id      = intval($_POST['coach_id']);
    $incident_date = $_POST['incident_date'];
    $reported_at   = $_POST['reported_at'] !== "" ? $_POST['reported_at'] : date('Y-m-d H:i:s');
    $severity      = $_POST['severity'];
    $injury_area   = trim($_POST['injury_area']);
    $notes         = trim($_POST['notes']);
    $action_taken  = trim($_POST['action_taken']);
    $status        = $_POST['status'];

    if ($student_id <= 0) {
        $message = "Student is required.";
    } elseif (!in_array($severity, ['minor','moderate','serious','critical'])) {
        $message = "Invalid severity.";
    } elseif (!in_array($status, ['open','pending','closed'])) {
        $message = "Invalid status.";
    } elseif ($incident_date === "") {
        $message = "Incident date is required.";
    } else {
        $sql = "
            INSERT INTO injury_reports
                (student_id, coach_id, incident_date, reported_at,
                 severity, injury_area, notes, action_taken, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "iisssssss",
            $student_id,
            $coach_id,
            $incident_date,
            $reported_at,
            $severity,
            $injury_area,
            $notes,
            $action_taken,
            $status
        );
        if ($stmt->execute()) {
            $success = "Injury report saved.";
        } else {
            $message = "Database error: " . $conn->error;
        }
    }
}
?>
<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>New Injury Report</h1>

<div class="form-card">
    <?php if ($message): ?><div class="alert-error"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

    <form method="POST">
        <?php echo Csrf::field(); ?>

        <div class="form-grid-2">
            <div class="form-group">
                <label>Student</label>
                <select name="student_id" required>
                    <option value="">-- Select Student --</option>
                    <?php if ($students_res): ?>
                        <?php while ($s = $students_res->fetch_assoc()): ?>
                            <option value="<?php echo $s['id']; ?>">
                                <?php echo htmlspecialchars($s['admission_no'] . " - " . $s['first_name'] . " " . $s['last_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Coach (reporting)</label>
                <select name="coach_id">
                    <option value="0">-- Select Coach --</option>
                    <?php if ($coaches_res): ?>
                        <?php while ($c = $coaches_res->fetch_assoc()): ?>
                            <option value="<?php echo $c['id']; ?>"
                                <?php if ($defaultCoachId && $defaultCoachId == $c['id']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($c['name']); ?>
                            </option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>
            </div>
        </div>

        <div class="form-grid-2">
            <div class="form-group">
                <label>Incident Date</label>
                <input type="date" name="incident_date"
                       value="<?php echo htmlspecialchars(date('Y-m-d')); ?>" required>
            </div>
            <div class="form-group">
                <label>Reported At</label>
                <input type="datetime-local" name="reported_at"
                       value="<?php echo htmlspecialchars(date('Y-m-d\TH:i')); ?>">
            </div>
        </div>

        <div class="form-grid-2">
            <div class="form-group">
                <label>Severity</label>
                <select name="severity" required>
                    <option value="minor">minor</option>
                    <option value="moderate">moderate</option>
                    <option value="serious">serious</option>
                    <option value="critical">critical</option>
                </select>
            </div>
            <div class="form-group">
                <label>Injury Area (e.g., right ankle, left shoulder)</label>
                <input type="text" name="injury_area" placeholder="e.g., right ankle, left knee">
            </div>
        </div>

        <div class="form-group">
            <label>Notes (what happened)</label>
            <textarea name="notes" rows="4" placeholder="Describe how the injury occurred, context, drills, etc."></textarea>
        </div>

        <div class="form-group">
            <label>Action Taken (treatment, rest plan, etc.)</label>
            <textarea name="action_taken" rows="3" placeholder="e.g., ice + rest, referred to physio, 1 week no bowling"></textarea>
        </div>

        <div class="form-group">
            <label>Status</label>
            <select name="status">
                <option value="open">open</option>
                <option value="pending">pending</option>
                <option value="closed">closed</option>
            </select>
        </div>

        <button type="submit" class="button-primary">Save Injury Report</button>
        <a href="injuries.php" class="button">Back to list</a>
    </form>
</div>

<?php include "includes/footer.php"; ?>
