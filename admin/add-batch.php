<?php
require_once __DIR__ . '/_bootstrap.php';

AdminGuard::requirePermission(Permissions::BATCHES_MANAGE);

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name      = trim($_POST['name'] ?? '');
    $code      = trim($_POST['code'] ?? '');
    $age_group = trim($_POST['age_group'] ?? '');
    $status    = $_POST['status'] ?? 'active';

    if ($name === '') {
        $message = 'Batch name is required.';
    } elseif (!in_array($status, ['active','disabled'], true)) {
        $message = 'Invalid status.';
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO batches (name, code, age_group, status)
             VALUES (?, ?, ?, ?)"
        );
        $stmt->bind_param('ssss', $name, $code, $age_group, $status);

        if ($stmt->execute()) {
            $message = 'Batch added successfully.';
        } else {
            $message = 'Database error.';
        }
        $stmt->close();
    }
}
?>

<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Add Batch / Program</h1>

<div class="form-card">
    <?php if ($message): ?>
        <p><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>

    <form method="POST">
        <?= Csrf::field(); ?>

        <div class="form-row">
            <label>Batch Name</label>
            <input type="text" name="name" required>
        </div>

        <div class="form-row">
            <label>Code (optional)</label>
            <input type="text" name="code">
        </div>

        <div class="form-row">
            <label>Age Group</label>
            <input type="text" name="age_group">
        </div>

        <div class="form-row">
            <label>Status</label>
            <select name="status">
                <option value="active">active</option>
                <option value="disabled">disabled</option>
            </select>
        </div>

        <button class="button-primary">Save Batch</button>
    </form>
</div>

<p><a href="batches.php" class="text-link">⬅ Back</a></p>

<?php include "includes/footer.php"; ?>
