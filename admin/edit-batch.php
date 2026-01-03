<?php
require_once __DIR__ . '/_bootstrap.php';

AdminGuard::requirePermission(Permissions::BATCHES_MANAGE);

$batch_id = (int)($_GET['id'] ?? 0);
if ($batch_id <= 0) {
    http_response_code(400);
    echo "Invalid batch id.";
    exit;
}

$message = "";

// Load batch
$stmt = $conn->prepare("SELECT * FROM batches WHERE id = ?");
$stmt->bind_param("i", $batch_id);
$stmt->execute();
$batch = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$batch) {
    http_response_code(404);
    echo "Batch not found.";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::validateRequest();

    $name      = trim($_POST['name'] ?? '');
    $code      = trim($_POST['code'] ?? '');
    $age_group = trim($_POST['age_group'] ?? '');
    $status    = ($_POST['status'] ?? 'active') === 'disabled' ? 'disabled' : 'active';

    if ($name === '') {
        $message = "Batch name is required.";
    } else {
        $up = $conn->prepare("
            UPDATE batches
            SET name = ?, code = ?, age_group = ?, status = ?
            WHERE id = ?
        ");
        $up->bind_param("ssssi", $name, $code, $age_group, $status, $batch_id);

        if ($up->execute()) {
            $message = "Batch updated successfully.";
            $batch['name'] = $name;
            $batch['code'] = $code;
            $batch['age_group'] = $age_group;
            $batch['status'] = $status;
        } else {
            $message = "Database error: " . htmlspecialchars($conn->error);
        }
        $up->close();
    }
}
?>

<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Edit Batch / Program</h1>

<div class="form-card">
    <?php if ($message): ?>
        <div class="<?php echo str_contains($message, 'successfully') ? 'alert-success' : 'alert-error'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <?php echo Csrf::field(); ?>

        <div class="form-row">
            <label>Batch Name</label>
            <input type="text" name="name" value="<?php echo htmlspecialchars($batch['name'] ?? ''); ?>" required>
        </div>

        <div class="form-row">
            <label>Code</label>
            <input type="text" name="code" value="<?php echo htmlspecialchars($batch['code'] ?? ''); ?>">
        </div>

        <div class="form-row">
            <label>Age Group</label>
            <input type="text" name="age_group" value="<?php echo htmlspecialchars($batch['age_group'] ?? ''); ?>">
        </div>

        <div class="form-row">
            <label>Status</label>
            <select name="status">
                <option value="active"   <?php if (($batch['status'] ?? '') === 'active') echo 'selected'; ?>>active</option>
                <option value="disabled" <?php if (($batch['status'] ?? '') === 'disabled') echo 'selected'; ?>>disabled</option>
            </select>
        </div>

        <button type="submit" class="button-primary">Save Changes</button>
        <a href="batches.php" class="button">Back</a>
    </form>
</div>

<?php include "includes/footer.php"; ?>
