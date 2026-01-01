<?php
include "../config.php";
checkLogin();
$role = currentUserRole();
if (!in_array($role, ['admin','superadmin'])) {
    http_response_code(403);
    echo "Access denied.";
    exit;
}

$categories = ['Salary','Rent','Equipment','Merchandise','Marketing','Misc'];
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$editing = $id > 0;

$title = "";
$category = "Rent";
$base_amount = "";
$tax_amount = "";
$total_amount = "";
$frequency = "monthly";
$day_of_month = 1;
$next_run_date = date('Y-m-d');
$statusVal = "active";
$notes = "";

if ($editing) {
    $stmt = $conn->prepare("SELECT * FROM recurring_expenses WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if ($row) {
        $title = $row['title'];
        $category = $row['category'];
        $base_amount = $row['base_amount'];
        $tax_amount = $row['tax_amount'];
        $total_amount = $row['total_amount'];
        $frequency = $row['frequency'];
        $day_of_month = $row['day_of_month'];
        $next_run_date = $row['next_run_date'];
        $statusVal = $row['status'];
        $notes = $row['notes'];
    }
}

$message = "";
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? "");
    $category = $_POST['category'] ?? "Rent";
    $base_amount = floatval($_POST['base_amount'] ?? 0);
    $tax_amount = floatval($_POST['tax_amount'] ?? 0);
    $frequency = "monthly";
    $day_of_month = intval($_POST['day_of_month'] ?? 1);
    $next_run_date = $_POST['next_run_date'] ?? date('Y-m-d');
    $statusVal = $_POST['status'] ?? "active";
    $notes = trim($_POST['notes'] ?? "");

    $total_amount = $base_amount + $tax_amount;

    if ($title === "") {
        $message = "Title is required.";
    } elseif ($base_amount <= 0) {
        $message = "Base amount must be greater than zero.";
    } elseif ($day_of_month < 1 || $day_of_month > 28) {
        $message = "Day of month should be between 1 and 28 (for safety).";
    } else {
        if ($editing) {
            $stmt = $conn->prepare("
                UPDATE recurring_expenses
                SET title=?, category=?, base_amount=?, tax_amount=?, total_amount=?,
                    frequency=?, day_of_month=?, next_run_date=?, status=?, notes=?
                WHERE id=?
            ");
            $stmt->bind_param(
                "ssdddsisssi",
                $title, $category, $base_amount, $tax_amount, $total_amount,
                $frequency, $day_of_month, $next_run_date, $statusVal, $notes, $id
            );
            $stmt->execute();
            $success = "Recurring expense updated.";
        } else {
            $stmt = $conn->prepare("
                INSERT INTO recurring_expenses
                    (title, category, base_amount, tax_amount, total_amount,
                     frequency, day_of_month, next_run_date, status, notes)
                VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param(
                "ssdddsisss",
                $title, $category, $base_amount, $tax_amount, $total_amount,
                $frequency, $day_of_month, $next_run_date, $statusVal, $notes
            );
            $stmt->execute();
            $id = $conn->insert_id;
            $editing = true;
            $success = "Recurring expense created.";
        }
    }
}
?>
<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1><?php echo $editing ? "Edit Recurring Expense" : "Add Recurring Expense"; ?></h1>

<div class="card">
    <?php if ($message): ?><div class="alert-error"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

    <form method="POST">
        <div class="form-grid-2">
            <div class="form-group">
                <label>Title *</label>
                <input type="text" name="title" value="<?php echo htmlspecialchars($title); ?>" required>
            </div>
            <div class="form-group">
                <label>Category *</label>
                <select name="category">
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat); ?>"
                            <?php echo $category === $cat ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Base Amount (pre-tax) *</label>
                <input type="number" step="0.01" name="base_amount"
                       value="<?php echo htmlspecialchars($base_amount); ?>" required>
            </div>
            <div class="form-group">
                <label>Tax Amount</label>
                <input type="number" step="0.01" name="tax_amount"
                       value="<?php echo htmlspecialchars($tax_amount); ?>">
            </div>
            <div class="form-group">
                <label>Day of Month *</label>
                <input type="number" name="day_of_month"
                       value="<?php echo (int)$day_of_month; ?>" min="1" max="28" required>
                <small>e.g. 1 = 1st of each month</small>
            </div>
            <div class="form-group">
                <label>Next Run Date *</label>
                <input type="date" name="next_run_date"
                       value="<?php echo htmlspecialchars($next_run_date); ?>" required>
            </div>
            <div class="form-group">
                <label>Status *</label>
                <select name="status">
                    <option value="active" <?php echo $statusVal === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="paused" <?php echo $statusVal === 'paused' ? 'selected' : ''; ?>>Paused</option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label>Notes</label>
            <textarea name="notes" rows="3"><?php echo htmlspecialchars($notes); ?></textarea>
        </div>

        <div style="margin-top:12px;">
            <button type="submit" class="button-primary">Save</button>
            <a href="recurring-expenses.php" class="button-secondary" style="margin-left:8px;">Back</a>
        </div>
    </form>
</div>

<?php include "includes/footer.php"; ?>
