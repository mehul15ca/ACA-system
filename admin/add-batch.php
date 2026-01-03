<?php
require_once __DIR__ . '/_bootstrap.php';

AdminGuard::requirePermission(Permissions::BATCHES_MANAGE);

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::validateRequest();

    $name      = trim($_POST['name'] ?? '');
    $code      = trim($_POST['code'] ?? '');
    $age_group = trim($_POST['age_group'] ?? '');
    $status    = ($_POST['status'] ?? 'active') === 'disabled' ? 'disabled' : 'active';

    if ($name === '') {
        $message = 'Batch name is required.';
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO batches (name, code, age_group, status) VALUES (?,?,?,?)"
        );
        $stmt->bind_param("ssss", $name, $code, $age_group, $status);

        if ($stmt->execute()) {
            header("Location: batches.php?created=1");
            exit;
        }
        $message = 'Database error: ' . htmlspecialchars($conn->error);
        $stmt->close();
    }
}
?>

<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Add Batch / Program</h1>

<div class="form-card">
    <?php if ($message): ?>
        <div class="alert-error"><?php echo $message; ?></div>
    <?php endif; ?>

    <form method="POST">
        <?php echo Csrf::field(); ?>

        <div class="form-row">
            <label>Batch Name</label>
            <input type="text" name="name" required value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
        </div>

        <div class="form-row">
            <label>Code</label>
            <input type="text" name="code" value="<?php echo htmlspecialchars($_POST['code'] ?? ''); ?>">
        </div>

        <div class="form-row">
            <label>Age Group</label>
            <input type="text" name="age_group" value="<?php echo htmlspecialchars($_POST['age_group'] ?? ''); ?>">
        </div>

        <div class="form-row">
            <label>Status</label>
            <select name="status">
                <?php $st = $_POST['status'] ?? 'active'; ?>
                <option value="active"   <?php echo $st === 'active' ? 'selected' : ''; ?>>active</option>
                <option value="disabled" <?php echo $st === 'disabled' ? 'selected' : ''; ?>>disabled</option>
            </select>
        </div>

        <button type="submit" class="button-primary">Save</button>
        <a href="batches.php" class="button">Back</a>
    </form>
</div>

<?php include "includes/footer.php"; ?>
