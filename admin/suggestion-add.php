<?php
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = intval($_POST['student_id']);
    $date       = $_POST['date'] !== "" ? $_POST['date'] : date('Y-m-d');
    $text       = trim($_POST['suggestion']);
    $status     = $_POST['status'];
    $drive_id   = trim($_POST['drive_file_id']);

    if ($student_id <= 0) {
        $message = "Student is required.";
    } elseif ($text === "") {
        $message = "Suggestion message cannot be empty.";
    } elseif (!in_array($status, ['open','closed'])) {
        $message = "Invalid status.";
    } else {
        $sql = "
            INSERT INTO suggestions (student_id, `date`, suggestion, status, drive_file_id)
            VALUES (?, ?, ?, ?, ?)
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "issss",
            $student_id,
            $date,
            $text,
            $status,
            $drive_id
        );
        if ($stmt->execute()) {
            $success = "Suggestion saved.";
        } else {
            $message = "Database error: " . $conn->error;
        }
    }
}
?>
<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>New Suggestion / Feedback (Admin / Coach)</h1>

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
                            <option value="<?php echo $s['id']; ?>">
                                <?php echo htmlspecialchars($s['admission_no'] . " - " . $s['first_name'] . " " . $s['last_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Date</label>
                <input type="date" name="date"
                       value="<?php echo htmlspecialchars(date('Y-m-d')); ?>">
            </div>
        </div>

        <div class="form-group">
            <label>Suggestion / Feedback</label>
            <textarea name="suggestion" rows="5" required></textarea>
        </div>

        <div class="form-group">
            <label>Status</label>
            <select name="status">
                <option value="open">open</option>
                <option value="closed">closed</option>
            </select>
        </div>

        <div class="form-group">
            <label>Google Drive File ID (optional)</label>
            <input type="text" name="drive_file_id" placeholder="Paste Drive file ID if there is an attachment">
            <p style="font-size:11px;color:#9ca3af;margin-top:4px;">
                Example link:
                https://drive.google.com/file/d/<strong>FILE_ID_HERE</strong>/view
            </p>
        </div>

        <button type="submit" class="button-primary">Save Suggestion</button>
        <a href="suggestions.php" class="button">Back to list</a>
    </form>
</div>

<?php include "includes/footer.php"; ?>
