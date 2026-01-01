<?php
include "../config.php";
include "fees-helpers.php";
checkLogin();
$role = currentUserRole();
if (!in_array($role, ['admin','superadmin'])) {
    http_response_code(403);
    echo "Access denied.";
    exit;
}

$message = "";
$success = "";

// load students & plans
$students_res = $conn->query("SELECT id, admission_no, first_name, last_name FROM students WHERE status='active' ORDER BY first_name ASC");
$plans_res = $conn->query("SELECT id, name, amount, currency FROM fees_plans ORDER BY name ASC");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = intval($_POST['student_id']);
    $plan_id    = intval($_POST['plan_id']);
    $amount     = floatval($_POST['amount']);
    $currency   = $_POST['currency'] !== "" ? $_POST['currency'] : "CAD";
    $due_date   = $_POST['due_date'];
    $period_from= $_POST['period_from'];
    $period_to  = $_POST['period_to'];
    $status     = $_POST['status'];

    if ($student_id <= 0 || $amount <= 0 || $due_date === "" || $period_from === "" || $period_to === "") {
        $message = "Student, amount, due date and period are required.";
    } elseif (!in_array($status, ['unpaid','paid','cancelled'])) {
        $message = "Invalid status.";
    } else {
        $invoice_no = aca_generate_invoice_no($conn, $due_date);

        $sql = "INSERT INTO fees_invoices
            (invoice_no, student_id, plan_id, amount, currency, due_date, status, period_from, period_to)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "siidsssss",
            $invoice_no, $student_id, $plan_id, $amount, $currency, $due_date, $status, $period_from, $period_to
        );
        if ($stmt->execute()) {
            $success = "Invoice created with number " . htmlspecialchars($invoice_no) . ".";
        } else {
            $message = "Database error: " . $conn->error;
        }
    }
}
?>
<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Create Manual Invoice</h1>

<div class="form-card">
    <?php if ($message): ?><div class="alert-error"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert-success"><?php echo $success; ?></div><?php endif; ?>

    <form method="POST">
        <div class="form-grid-2">
            <div class="form-group">
                <label>Student</label>
                <select name="student_id" required>
                    <option value="">-- Select Student --</option>
                    <?php if ($students_res): ?>
                        <?php while ($s = $students_res->fetch_assoc()): ?>
                            <option value="<?php echo $s['id']; ?>">
                                <?php echo htmlspecialchars($s['admission_no'] . " - " . $s['first_name'] . " " . $s['last_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Fee Plan (optional – will prefill amount)</label>
                <select name="plan_id" id="planSelect">
                    <option value="">-- None (custom) --</option>
                    <?php if ($plans_res): ?>
                        <?php while ($p = $plans_res->fetch_assoc()): ?>
                            <option value="<?php echo $p['id']; ?>"
                                data-amount="<?php echo htmlspecialchars($p['amount']); ?>"
                                data-currency="<?php echo htmlspecialchars($p['currency']); ?>">
                                <?php echo htmlspecialchars($p['name'] . " (" . $p['amount'] . " " . $p['currency'] . ")"); ?>
                            </option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>
            </div>
        </div>

        <div class="form-grid-2">
            <div class="form-group">
                <label>Amount (you can include add-ons here)</label>
                <input type="number" step="0.01" name="amount" id="amountField" required>
            </div>
            <div class="form-group">
                <label>Currency</label>
                <input type="text" name="currency" id="currencyField" value="CAD">
            </div>
        </div>

        <div class="form-grid-2">
            <div class="form-group">
                <label>Period From</label>
                <input type="date" name="period_from" id="periodFrom" required>
            </div>
            <div class="form-group">
                <label>Period To</label>
                <input type="date" name="period_to" id="periodTo" required>
            </div>
        </div>

        <div class="form-group">
            <label>Due Date</label>
            <input type="date" name="due_date" id="dueDate" required>
        </div>

        <div class="form-group">
            <label>Status</label>
            <select name="status">
                <option value="unpaid">unpaid</option>
                <option value="paid">paid</option>
                <option value="cancelled">cancelled</option>
            </select>
        </div>

        <button type="submit" class="button-primary">Create Invoice</button>
        <a href="invoices.php" class="button">Back to list</a>
    </form>
</div>

<script>
// Autofill amount & currency when a plan is selected
const planSelect = document.getElementById('planSelect');
const amountField = document.getElementById('amountField');
const currencyField = document.getElementById('currencyField');

planSelect.addEventListener('change', function() {
    const opt = planSelect.options[planSelect.selectedIndex];
    const amt = opt.getAttribute('data-amount');
    const cur = opt.getAttribute('data-currency');
    if (amt) amountField.value = amt;
    if (cur) currencyField.value = cur;
});

// Default period to current month
(function() {
    const today = new Date();
    const y = today.getFullYear();
    const m = today.getMonth(); // 0-based
    const first = new Date(y, m, 1);
    const last  = new Date(y, m + 1, 0);

    function fmt(d) {
        const mm = String(d.getMonth() + 1).padStart(2, '0');
        const dd = String(d.getDate()).padStart(2, '0');
        return d.getFullYear() + '-' + mm + '-' + dd;
    }
    document.getElementById('periodFrom').value = fmt(first);
    document.getElementById('periodTo').value   = fmt(last);
    document.getElementById('dueDate').value    = fmt(new Date());
})();
</script>

<?php include "includes/footer.php"; ?>
