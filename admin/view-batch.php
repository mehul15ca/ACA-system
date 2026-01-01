<?php
include "../config.php";
checkLogin();

$role = currentUserRole();
if (!in_array($role, ['admin', 'superadmin'])) {
    http_response_code(403);
    echo "Access denied. Admin/Superadmin only.";
    exit;
}

if (!isset($_GET['id'])) {
    die("Batch ID missing.");
}
$batch_id = intval($_GET['id']);
$message = "";

// Handle adding student to this batch
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_student_id'])) {
    $sid = intval($_POST['add_student_id']);
    if ($sid > 0) {
        $up = $conn->prepare("UPDATE students SET batch_id = ? WHERE id = ?");
        $up->bind_param("ii", $batch_id, $sid);
        if ($up->execute()) {
            $message = "Student added to batch.";
        } else {
            $message = "Error adding student: " . $conn->error;
        }
    }
}

// Fetch batch
$stmt = $conn->prepare("SELECT * FROM batches WHERE id = ?");
$stmt->bind_param("i", $batch_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
    die("Batch not found.");
}
$batch = $res->fetch_assoc();

// Fetch students in this batch
$students_stmt = $conn->prepare("
    SELECT id, admission_no, first_name, last_name, status
    FROM students
    WHERE batch_id = ?
    ORDER BY first_name ASC
");
$students_stmt->bind_param("i", $batch_id);
$students_stmt->execute();
$students_res = $students_stmt->get_result();

// Fetch students without batch for "Add to this batch"
$free_students = $conn->query("
    SELECT id, admission_no, first_name, last_name
    FROM students
    WHERE batch_id IS NULL
    ORDER BY first_name ASC
");
?>
<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>View Batch</h1>

<div class="table-card">
    <div class="table-header">
        <h2><?php echo htmlspecialchars($batch['name']); ?></h2>
        <a href="edit-batch.php?id=<?php echo $batch['id']; ?>" class="button">✏️ Edit</a>
    </div>

    <?php if ($message): ?>
        <p style="margin-bottom:10px;"><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>

    <table class="acatable">
        <tr>
            <th>ID</th>
            <td><?php echo $batch['id']; ?></td>
        </tr>
        <tr>
            <th>Code</th>
            <td><?php echo htmlspecialchars($batch['code']); ?></td>
        </tr>
        <tr>
            <th>Age Group</th>
            <td><?php echo htmlspecialchars($batch['age_group']); ?></td>
        </tr>
        <tr>
            <th>Status</th>
            <td><?php echo htmlspecialchars($batch['status']); ?></td>
        </tr>
        <tr>
            <th>Created At</th>
            <td><?php echo htmlspecialchars($batch['created_at']); ?></td>
        </tr>
    </table>
</div>

<div class="table-card">
    <div class="table-header">
        <h2>Students in this Batch</h2>
    </div>

    <table class="acatable">
        <thead>
            <tr>
                <th>ID</th>
                <th>Admission No</th>
                <th>Name</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($students_res && $students_res->num_rows > 0): ?>
            <?php while ($s = $students_res->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $s['id']; ?></td>
                    <td><?php echo htmlspecialchars($s['admission_no']); ?></td>
                    <td><?php echo htmlspecialchars(trim($s['first_name'] . " " . $s['last_name'])); ?></td>
                    <td><?php echo htmlspecialchars($s['status']); ?></td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="4">No students in this batch yet.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>

    <div style="margin-top:12px;">
        <form method="POST">
            <label style="font-size:13px; display:block; margin-bottom:4px;">
                Add existing student to this batch:
            </label>
            <select name="add_student_id" style="max-width:300px; margin-right:8px;">
                <option value="">-- Select student (no batch yet) --</option>
                <?php if ($free_students && $free_students->num_rows > 0): ?>
                    <?php while ($fs = $free_students->fetch_assoc()): ?>
                        <option value="<?php echo $fs['id']; ?>">
                            <?php
                            $full = trim($fs['first_name'] . " " . $fs['last_name']);
                            echo htmlspecialchars($full . " (" . $fs['admission_no'] . ")");
                            ?>
                        </option>
                    <?php endwhile; ?>
                <?php endif; ?>
            </select>
            <button type="submit" class="button-primary">Add to Batch</button>
        </form>
    </div>
</div>

<p style="margin-top:10px;">
    <a class="text-link" href="batches.php">⬅ Back to Batches</a>
</p>

<?php include "includes/footer.php"; ?>
