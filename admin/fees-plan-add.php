<?php
require_once __DIR__ . '/_bootstrap.php';

$message = "";
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = trim($_POST['name']);
    $description = trim($_POST['description']);
    $amount      = floatval($_POST['amount']);
    $currency    = $_POST['currency'] !== "" ? $_POST['currency'] : "CAD";
    $frequency   = $_POST['frequency'];

    if ($name === "" || $amount <= 0) {
        $message = "Name and positive amount are required.";
    } elseif (!in_array($frequency, ['monthly','term','yearly','one_time'])) {
        $message = "Invalid frequency.";
    } else {
        $sql = "INSERT INTO fees_plans (name, description, amount, currency, frequency)
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssdss", $name, $description, $amount, $currency, $frequency);
        if ($stmt->execute()) {
            $success = "Fee plan created.";
        } else {
            $message = "Database error: " . $conn->error;
        }
    }
}
?>
<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Add Fee Plan</h1>

<div class="form-card">
    <?php if ($message): ?><div class="alert-error"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

    <form method="POST">
        <?php echo Csrf::field(); ?>

        <div class="form-group">
            <label>Plan Name</label>
            <input type="text" name="name" required placeholder="e.g. Monthly Training – U12">
        </div>
        <div class="form-group">
            <label>Amount (base)</label>
            <input type="number" step="0.01" name="amount" required placeholder="e.g. 150.00">
        </div>
        <div class="form-group">
            <label>Currency</label>
            <input type="text" name="currency" value="CAD">
        </div>
        <div class="form-group">
            <label>Frequency</label>
            <select name="frequency">
                <option value="monthly" selected>monthly</option>
                <option value="term">term</option>
                <option value="yearly">yearly</option>
                <option value="one_time">one_time (add-ons)</option>
            </select>
        </div>
        <div class="form-group">
            <label>Description (for your internal notes)</label>
            <textarea name="description" rows="3"></textarea>
        </div>
        <button type="submit" class="button-primary">Save Plan</button>
        <a href="fees-plans.php" class="button">Back to list</a>
    </form>
</div>

<?php include "includes/footer.php"; ?>
