<?php
require_once __DIR__ . '/_bootstrap.php';

$message = "";
$success = "";

// Handle add rate
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action']==='add') {
    $coach_id  = intval($_POST['coach_id']);
    $batch_id  = isset($_POST['batch_id']) && $_POST['batch_id'] !== "" ? intval($_POST['batch_id']) : null;
    $rate_type = $_POST['rate_type'] ?? '';
    $rate_amount = isset($_POST['rate_amount']) ? floatval($_POST['rate_amount']) : 0;
    $effective_from = !empty($_POST['effective_from']) ? $_POST['effective_from'] : null;

    if ($coach_id <= 0) {
        $message = "Please select a coach.";
    } elseif (!in_array($rate_type, ['per_session','per_hour','per_month'])) {
        $message = "Invalid rate type.";
    } elseif ($rate_amount <= 0) {
        $message = "Rate amount must be greater than zero.";
    } else {
        $coach_id  = intval($coach_id);
        $batch_sql = $batch_id === null ? "NULL" : intval($batch_id);
        $rate_type_db = $conn->real_escape_string($rate_type);
        $rate_amount_db = $rate_amount;
        $effective_sql = $effective_from ? ("'".$conn->real_escape_string($effective_from)."'") : "NULL";

        $sql = "
            INSERT INTO coach_salary_rates
                (coach_id, batch_id, rate_type, rate_amount, currency, effective_from)
            VALUES
                ({$coach_id}, {$batch_sql}, '{$rate_type_db}', {$rate_amount_db}, 'CAD', {$effective_sql})
        ";
        if ($conn->query($sql)) {
            $success = "Salary rate added.";
        } else {
            $message = "Database error: " . $conn->error;
        }
    }
}

// Load data
$coaches_res = $conn->query("SELECT id, name FROM coaches WHERE status='active' ORDER BY name ASC");
$batches_res = $conn->query("SELECT id, name FROM batches WHERE status='active' ORDER BY name ASC");

$rates_res = $conn->query("
    SELECT r.*, c.name AS coach_name, b.name AS batch_name
    FROM coach_salary_rates r
    JOIN coaches c ON r.coach_id = c.id
    LEFT JOIN batches b ON r.batch_id = b.id
    ORDER BY c.name ASC, b.name ASC, r.created_at DESC
");
?>
<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Coach Salary Rates</h1>

<div class="form-card">
    <?php if ($message): ?><div class="alert-error"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

    <h2 style="font-size:15px;margin-bottom:8px;">Add New Rate</h2>
    <form method="POST">
        <?php echo Csrf::field(); ?>

        <input type="hidden" name="action" value="add">
        <div class="form-grid-3">
            <div class="form-group">
                <label>Coach</label>
                <select name="coach_id" required>
                    <option value="">-- Select Coach --</option>
                    <?php if ($coaches_res): ?>
                        <?php while ($c = $coaches_res->fetch_assoc()): ?>
                            <option value="<?php echo $c['id']; ?>">
                                <?php echo htmlspecialchars($c['name']); ?>
                            </option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Batch (optional)</label>
                <select name="batch_id">
                    <option value="">-- Any Batch --</option>
                    <?php if ($batches_res): ?>
                        <?php while ($b = $batches_res->fetch_assoc()): ?>
                            <option value="<?php echo $b['id']; ?>">
                                <?php echo htmlspecialchars($b['name']); ?>
                            </option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>
                <p style="font-size:11px;color:#9ca3af;margin-top:2px;">
                    If set, this rate overrides the coach's global rate for that batch.
                </p>
            </div>
            <div class="form-group">
                <label>Rate Type</label>
                <select name="rate_type" required>
                    <option value="per_session">Per Session</option>
                    <option value="per_hour">Per Hour</option>
                    <option value="per_month">Per Month</option>
                </select>
            </div>
        </div>

        <div class="form-grid-3">
            <div class="form-group">
                <label>Rate Amount (CAD)</label>
                <input type="number" step="0.01" min="0" name="rate_amount" required>
            </div>
            <div class="form-group">
                <label>Effective From (optional)</label>
                <input type="date" name="effective_from">
            </div>
        </div>

        <button type="submit" class="button-primary">Add Rate</button>
    </form>
</div>

<div class="table-card">
    <div class="table-header">
        <h2>Existing Rates</h2>
    </div>
    <table class="acatable">
        <thead>
            <tr>
                <th>Coach</th>
                <th>Batch</th>
                <th>Type</th>
                <th>Rate (CAD)</th>
                <th>Effective From</th>
                <th>Created</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($rates_res && $rates_res->num_rows > 0): ?>
            <?php while ($r = $rates_res->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($r['coach_name']); ?></td>
                    <td>
                        <?php
                        echo $r['batch_id'] ? htmlspecialchars($r['batch_name']) : 'Any Batch';
                        ?>
                    </td>
                    <td><?php echo htmlspecialchars($r['rate_type']); ?></td>
                    <td>$<?php echo number_format($r['rate_amount'], 2); ?> CAD</td>
                    <td><?php echo htmlspecialchars($r['effective_from']); ?></td>
                    <td><?php echo htmlspecialchars($r['created_at']); ?></td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="6">No rates defined yet.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include "includes/footer.php"; ?>
