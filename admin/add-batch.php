<?php
include "../config.php";
checkLogin();

$role = currentUserRole();
if (!in_array($role, ['admin', 'superadmin'])) {
    http_response_code(403);
    echo "Access denied. Admin/Superadmin only.";
    exit;
}

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name      = trim($_POST['name']);
    $code      = trim($_POST['code']);
    $age_group = trim($_POST['age_group']);
    $status    = $_POST['status'];

    if ($name === "") {
        $message = "Batch name is required.";
    } else {
        $stmt = $conn->prepare("
            INSERT INTO batches (name, code, age_group, status)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param(
            "ssss",
            $name,
            $code,
            $age_group,
            $status
        );

        if ($stmt->execute()) {
            $message = "Batch added successfully.";
        } else {
            $message = "Error: " . $conn->error;
        }
    }
}
?>
<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Add Batch / Program</h1>

<div class="form-card">
    <?php if ($message): ?>
        <p style="margin-bottom:10px;"><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>

    <form method="POST">
        <div class="form-row">
            <label>Batch Name</label>
            <input type="text" name="name" required>
        </div>

        <div class="form-row">
            <label>Code (optional, unique if used)</label>
            <input type="text" name="code">
        </div>

        <div class="form-row">
            <label>Age Group (free text, e.g. U10, U12, Beginners)</label>
            <input type="text" name="age_group">
        </div>

        <div class="form-row">
            <label>Status</label>
            <select name="status">
                <option value="active">active</option>
                <option value="disabled">disabled</option>
            </select>
        </div>

        <button type="submit" class="button-primary">Save Batch</button>
    </form>
</div>

<p style="margin-top:10px;">
    <a class="text-link" href="batches.php">⬅ Back to Batches</a>
</p>

<?php include "includes/footer.php"; ?>
