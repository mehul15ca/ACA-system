<?php
require_once __DIR__ . '/_bootstrap.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) die("Invalid suggestion ID.");

$message = "";
$success = "";

$sql = "
    SELECT * FROM suggestions
    WHERE id = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$sg = $stmt->get_result()->fetch_assoc();
if (!$sg) die("Suggestion not found.");

$students_res = $conn->query("SELECT id, admission_no, first_name, last_name FROM students WHERE status='active' ORDER BY first_name ASC");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = intval($_POST['student_id']);
    $date       = $_POST['date'];
    $text       = trim($_POST['suggestion']);
    $status     = $_POST['status'];
    $drive_id   = trim($_POST['drive_file_id']);

    if ($student_id <= 0) {
        $message = "Student is required.";
    } elseif ($date === "") {
        $message = "Date is required.";
    } elseif (!in_array($status, ['open','closed'])) {
        $message = "Invalid status.";
    } elseif ($text === "") {
        $message = "Suggestion message cannot be empty.";
    } else {
        $sqlU = "
            UPDATE suggestions
            SET student_id = ?, `date` = ?, suggestion = ?, status = ?, drive_file_id = ?
            WHERE id = ?
        ";
        $stmtU = $conn->prepare($sqlU);
        $stmtU->bind_param(
            "issssi",
            $student_id,
            $date,
            $text,
            $status,
            $drive_id,
            $id
        );
        if ($stmtU->execute()) {
            $success = "Suggestion updated.";
            $sg['student_id']    = $student_id;
            $sg['date']          = $date;
            $sg['suggestion']    = $text;
            $sg['status']        = $status;
            $sg['drive_file_id'] = $drive_id;
        } else {
            $message = "Database error: " . $conn->error;
        }
    }
}
?>
<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Edit Suggestion</h1>

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
                            <option value="<?php echo $s['id']; ?>"
                                <?php if ($s['id'] == $sg['student_id']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($s['admission_no'] . " - " . $s['first_name'] . " " . $s['last_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Date</label>
                <input type="date" name="date"
                       value="<?php echo htmlspecialchars($sg['date']); ?>" required>
            </div>
        </div>

        <div class="form-group">
            <label>Suggestion / Feedback</label>
            <textarea name="suggestion" rows="5" required><?php echo htmlspecialchars($sg['suggestion']); ?></textarea>
        </div>

        <div class="form-group">
            <label>Status</label>
            <select name="status">
                <option value="open" <?php if ($sg['status']==='open') echo 'selected'; ?>>open</option>
                <option value="closed" <?php if ($sg['status']==='closed') echo 'selected'; ?>>closed</option>
            </select>
        </div>

        <div class="form-group">
            <label>Google Drive File ID (optional)</label>
            <input type="text" name="drive_file_id"
                   value="<?php echo htmlspecialchars($sg['drive_file_id']); ?>"
                   placeholder="Paste file ID or keep empty">
            <p style="font-size:11px;color:#9ca3af;margin-top:4px;">
                Example link:
                https://drive.google.com/file/d/<strong>FILE_ID_HERE</strong>/view
            </p>
        </div>

        <button type="submit" class="button-primary">Update Suggestion</button>
        <a href="suggestion-view.php?id=<?php echo $sg['id']; ?>" class="button">Back</a>
    </form>
</div>

<?php include "includes/footer.php"; ?>
