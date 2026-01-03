<?php
require_once __DIR__ . '/_bootstrap.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) die("Invalid plan ID.");

$message = "";
$success = "";

$stmt = $conn->prepare("SELECT * FROM fees_plans WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$plan = $stmt->get_result()->fetch_assoc();
if (!$plan) die("Plan not found.");

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
        $sql = "UPDATE fees_plans
                SET name = ?, description = ?, amount = ?, currency = ?, frequency = ?
                WHERE id = ?";
        $stmtU = $conn->prepare($sql);
        $stmtU->bind_param("ssdssi", $name, $description, $amount, $currency, $frequency, $id);
        if ($stmtU->execute()) {
            $success = "Plan updated.";
            $plan['name'] = $name;
            $plan['description'] = $description;
            $plan['amount'] = $amount;
            $plan['currency'] = $currency;
            $plan['frequency'] = $frequency;
        } else {
            $message = "Database error: " . $conn->error;
        }
    }
}
?>
<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Edit Fee Plan</h1>

<div class="form-card">
    <?php if ($message): ?><div class="alert-error"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

    <form method="POST">
        <?php echo Csrf::field(); ?>

        <div class="form-group">
            <label>Plan Name</label>
            <input type="text" name="name" value="<?php echo htmlspecialchars($plan['name']); ?>" required>
        </div>
        <div class="form-group">
            <label>Amount (base)</label>
            <input type="number" step="0.01" name="amount" value="<?php echo htmlspecialchars($plan['amount']); ?>" required>
        </div>
        <div class="form-group">
            <label>Currency</label>
            <input type="text" name="currency" value="<?php echo htmlspecialchars($plan['currency']); ?>">
        </div>
        <div class="form-group">
            <label>Frequency</label>
            <select name="frequency">
                <option value="monthly" <?php if ($plan['frequency']==='monthly') echo 'selected'; ?>>monthly</option>
                <option value="term"    <?php if ($plan['frequency']==='term')    echo 'selected'; ?>>term</option>
                <option value="yearly"  <?php if ($plan['frequency']==='yearly')  echo 'selected'; ?>>yearly</option>
                <option value="one_time"<?php if ($plan['frequency']==='one_time')echo 'selected'; ?>>one_time</option>
            </select>
        </div>
        <div class="form-group">
            <label>Description</label>
            <textarea name="description" rows="3"><?php echo htmlspecialchars($plan['description']); ?></textarea>
        </div>
        <button type="submit" class="button-primary">Update Plan</button>
        <a href="fees-plans.php" class="button">Back to list</a>
    </form>
</div>

<?php include "includes/footer.php"; ?>
