<?php
include "../config.php";
checkLogin();

$role = currentUserRole();
if (!in_array($role, ['admin','superadmin','coach'])) {
    http_response_code(403);
    echo "Access denied.";
    exit;
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) die("Invalid injury ID.");

$message = "";
$success = "";

$sql = "
    SELECT * FROM injury_reports
    WHERE id = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$inj = $stmt->get_result()->fetch_assoc();
if (!$inj) die("Injury report not found.");

$students_res = $conn->query("SELECT id, admission_no, first_name, last_name FROM students WHERE status='active' ORDER BY first_name ASC");
$coaches_res  = $conn->query("SELECT id, name FROM coaches WHERE status='active' ORDER BY name ASC");

// If coach, ensure this is their report (if coach_id set)
if ($role === 'coach' && $inj['coach_id']) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $userId = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
    $stmtU = $conn->prepare("SELECT coach_id FROM users WHERE id = ?");
    $stmtU->bind_param("i", $userId);
    $stmtU->execute();
    $u = $stmtU->get_result()->fetch_assoc();
    $coachId = $u && $u['coach_id'] ? intval($u['coach_id']) : 0;

    if ($coachId <= 0 || $coachId !== intval($inj['coach_id'])) {
        http_response_code(403);
        echo "Access denied.";
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        $sqlU = "
            UPDATE injury_reports
            SET student_id = ?, coach_id = ?, incident_date = ?, reported_at = ?,
                severity = ?, injury_area = ?, notes = ?, action_taken = ?, status = ?
            WHERE id = ?
        ";
        $stmtU = $conn->prepare($sqlU);
        $stmtU->bind_param(
            "iisssssssi",
            $student_id,
            $coach_id,
            $incident_date,
            $reported_at,
            $severity,
            $injury_area,
            $notes,
            $action_taken,
            $status,
            $id
        );
        if ($stmtU->execute()) {
            $success = "Injury report updated.";
            $inj['student_id'] = $student_id;
            $inj['coach_id'] = $coach_id;
            $inj['incident_date'] = $incident_date;
            $inj['reported_at'] = $reported_at;
            $inj['severity'] = $severity;
            $inj['injury_area'] = $injury_area;
            $inj['notes'] = $notes;
            $inj['action_taken'] = $action_taken;
            $inj['status'] = $status;
        } else {
            $message = "Database error: " . $conn->error;
        }
    }
}
?>
<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Edit Injury Report</h1>

<div class="form-card">
    <?php if ($message): ?><div class="alert-error"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

    <form method="POST">
        <div class="form-grid-2">
            <div class="form-group">
                <label>Student</label>
                <select name="student_id" required>
                    <option value="">-- Select Student --</option>
                    <?php if ($students_res): ?>
                        <?php while ($s = $students_res->fetch_assoc()): ?>
                            <option value="<?php echo $s['id']; ?>"
                                <?php if ($s['id'] == $inj['student_id']) echo 'selected'; ?>>
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
                                <?php if ($c['id'] == $inj['coach_id']) echo 'selected'; ?>>
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
                       value="<?php echo htmlspecialchars($inj['incident_date']); ?>" required>
            </div>
            <div class="form-group">
                <label>Reported At</label>
                <input type="datetime-local" name="reported_at"
                       value="<?php echo htmlspecialchars(date('Y-m-d\TH:i', strtotime($inj['reported_at']))); ?>">
            </div>
        </div>

        <div class="form-grid-2">
            <div class="form-group">
                <label>Severity</label>
                <select name="severity" required>
                    <option value="minor" <?php if ($inj['severity']==='minor') echo 'selected'; ?>>minor</option>
                    <option value="moderate" <?php if ($inj['severity']==='moderate') echo 'selected'; ?>>moderate</option>
                    <option value="serious" <?php if ($inj['severity']==='serious') echo 'selected'; ?>>serious</option>
                    <option value="critical" <?php if ($inj['severity']==='critical') echo 'selected'; ?>>critical</option>
                </select>
            </div>
            <div class="form-group">
                <label>Injury Area</label>
                <input type="text" name="injury_area"
                       value="<?php echo htmlspecialchars($inj['injury_area']); ?>">
            </div>
        </div>

        <div class="form-group">
            <label>Notes (what happened)</label>
            <textarea name="notes" rows="4"><?php echo htmlspecialchars($inj['notes']); ?></textarea>
        </div>

        <div class="form-group">
            <label>Action Taken (treatment, rest plan, etc.)</label>
            <textarea name="action_taken" rows="3"><?php echo htmlspecialchars($inj['action_taken']); ?></textarea>
        </div>

        <div class="form-group">
            <label>Status</label>
            <select name="status">
                <option value="open" <?php if ($inj['status']==='open') echo 'selected'; ?>>open</option>
                <option value="pending" <?php if ($inj['status']==='pending') echo 'selected'; ?>>pending</option>
                <option value="closed" <?php if ($inj['status']==='closed') echo 'selected'; ?>>closed</option>
            </select>
        </div>

        <button type="submit" class="button-primary">Update Injury Report</button>
        <a href="injury-view.php?id=<?php echo $inj['id']; ?>" class="button">Back</a>
    </form>
</div>

<?php include "includes/footer.php"; ?>
