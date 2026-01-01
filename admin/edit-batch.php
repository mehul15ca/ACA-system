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

// Fetch batch
$stmt = $conn->prepare("SELECT * FROM batches WHERE id = ?");
$stmt->bind_param("i", $batch_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
    die("Batch not found.");
}
$batch = $res->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name      = trim($_POST['name']);
    $code      = trim($_POST['code']);
    $age_group = trim($_POST['age_group']);
    $status    = $_POST['status'];

    if ($name === "") {
        $message = "Batch name is required.";
    } else {
        $up = $conn->prepare("
            UPDATE batches
            SET name = ?, code = ?, age_group = ?, status = ?
            WHERE id = ?
        ");
        $up->bind_param(
            "ssssi",
            $name,
            $code,
            $age_group,
            $status,
            $batch_id
        );

        if ($up->execute()) {
            $message = "Batch updated successfully.";
            $batch['name']      = $name;
            $batch['code']      = $code;
            $batch['age_group'] = $age_group;
            $batch['status']    = $status;
        } else {
            $message = "Error: " . $conn->error;
        }
    }
}
?>
<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Edit Batch / Program</h1>

<div class="form-card">
    <?php if ($message): ?>
        <p style="margin-bottom:10px;"><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>

    <form method="POST">
        <div class="form-row">
            <label>Batch Name</label>
            <input type="text" name="name" value="<?php echo htmlspecialchars($batch['name']); ?>" required>
        </div>

        <div class="form-row">
            <label>Code</label>
            <input type="text" name="code" value="<?php echo htmlspecialchars($batch['code']); ?>">
        </div>

        <div class="form-row">
            <label>Age Group</label>
            <input type="text" name="age_group" value="<?php echo htmlspecialchars($batch['age_group']); ?>">
        </div>

        <div class="form-row">
            <label>Status</label>
            <select name="status">
                <option value="active" <?php if ($batch['status'] === 'active') echo 'selected'; ?>>active</option>
                <option value="disabled" <?php if ($batch['status'] === 'disabled') echo 'selected'; ?>>disabled</option>
            </select>
        </div>

        <button type="submit" class="button-primary">Save Changes</button>
    </form>
</div>

<p style="margin-top:10px;">
    <a class="text-link" href="batches.php">⬅ Back to Batches</a>
</p>

<?php include "includes/footer.php"; ?>
