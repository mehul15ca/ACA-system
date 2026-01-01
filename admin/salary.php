<?php
include "../config.php";
checkLogin();
$role = currentUserRole();
if (!in_array($role, ['admin','superadmin'])) {
    http_response_code(403);
    echo "Access denied.";
    exit;
}

// Month/year filter
$year  = isset($_GET['year'])  && $_GET['year']  !== '' ? intval($_GET['year'])  : intval(date('Y'));
$month = isset($_GET['month']) && $_GET['month'] !== '' ? intval($_GET['month']) : intval(date('n'));

if ($month < 1 || $month > 12) $month = intval(date('n'));
if ($year < 2000 || $year > 2100) $year = intval(date('Y'));

$fromDate = sprintf('%04d-%02d-01', $year, $month);
$lastDay  = date('t', strtotime($fromDate));
$toDate   = sprintf('%04d-%02d-%02d', $year, $month, $lastDay);

$message = "";
$success = "";

// Handle actions: generate, mark_paid
if (isset($_POST['action']) && $_POST['action'] === 'generate') {
    // Call salary-generate logic via include
    include "salary-generate.php";
} elseif (isset($_POST['action']) && $_POST['action'] === 'mark_paid') {
    $coach_id = intval($_POST['coach_id']);
    if ($coach_id > 0) {
        $sql = "
            UPDATE coach_salary_sessions
            SET status='paid'
            WHERE coach_id = {$coach_id}
              AND month = {$month}
              AND year = {$year}
              AND status='unpaid'
        ";
        if ($conn->query($sql)) {
            $success = "Marked all unpaid sessions as PAID for this coach and month.";
        } else {
            $message = "Error updating salary: " . $conn->error;
        }
    }
}

// Load summary per coach
$sqlSummary = "
    SELECT
        c.id AS coach_id,
        c.name AS coach_name,
        COUNT(s.id) AS total_lines,
        IFNULL(SUM(s.hours),0) AS total_hours,
        IFNULL(SUM(s.amount),0) AS total_amount,
        IFNULL(SUM(CASE WHEN s.status='unpaid' THEN s.amount ELSE 0 END),0) AS unpaid_amount
    FROM coaches c
    LEFT JOIN coach_salary_sessions s
        ON s.coach_id = c.id
       AND s.month = {$month}
       AND s.year = {$year}
    WHERE c.status='active'
    GROUP BY c.id, c.name
    ORDER BY c.name ASC
";
$summaryRes = $conn->query($sqlSummary);
?>
<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Coach Salaries</h1>

<div class="form-card" style="margin-bottom:16px;">
    <form method="GET" style="display:flex; flex-wrap:wrap; gap:10px; align-items:flex-end;">
        <div>
            <label style="font-size:12px;">Month</label>
            <select name="month">
                <?php for ($m=1; $m<=12; $m++): ?>
                    <option value="<?php echo $m; ?>" <?php if ($m==$month) echo 'selected'; ?>>
                        <?php echo date('F', mktime(0,0,0,$m,1)); ?>
                    </option>
                <?php endfor; ?>
            </select>
        </div>
        <div>
            <label style="font-size:12px;">Year</label>
            <input type="number" name="year" value="<?php echo $year; ?>" style="width:90px;">
        </div>
        <div>
            <button type="submit" class="button-primary">Apply</button>
        </div>
        <div style="margin-left:auto;">
            <form method="POST" style="display:inline;">
                <input type="hidden" name="action" value="generate">
                <input type="hidden" name="month" value="<?php echo $month; ?>">
                <input type="hidden" name="year" value="<?php echo $year; ?>">
                <button type="submit" class="button">🔄 Generate / Refresh Salary Lines</button>
            </form>
        </div>
    </form>
</div>

<div class="form-card">
    <?php if ($message): ?><div class="alert-error"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

    <p style="font-size:12px;color:#9ca3af;">
        Period: <?php echo htmlspecialchars($fromDate); ?> to <?php echo htmlspecialchars($toDate); ?>
    </p>
</div>

<div class="table-card">
    <div class="table-header">
        <h2>Salary Summary – <?php echo date('F Y', strtotime($fromDate)); ?></h2>
    </div>
    <table class="acatable">
        <thead>
            <tr>
                <th>Coach</th>
                <th>Total Lines</th>
                <th>Total Hours</th>
                <th>Total Amount</th>
                <th>Unpaid Amount</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($summaryRes && $summaryRes->num_rows > 0): ?>
            <?php while ($row = $summaryRes->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['coach_name']); ?></td>
                    <td><?php echo (int)$row['total_lines']; ?></td>
                    <td><?php echo number_format($row['total_hours'], 2); ?></td>
                    <td>$<?php echo number_format($row['total_amount'], 2); ?> CAD</td>
                    <td>$<?php echo number_format($row['unpaid_amount'], 2); ?> CAD</td>
                    <td>
                        <a href="salary-details.php?coach_id=<?php echo $row['coach_id']; ?>&month=<?php echo $month; ?>&year=<?php echo $year; ?>" class="text-link">View details</a>
                        <?php if ($row['unpaid_amount'] > 0): ?>
                            |
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Mark all unpaid as PAID for this coach and month?');">
                                <input type="hidden" name="action" value="mark_paid">
                                <input type="hidden" name="coach_id" value="<?php echo $row['coach_id']; ?>">
                                <input type="hidden" name="month" value="<?php echo $month; ?>">
                                <input type="hidden" name="year" value="<?php echo $year; ?>">
                                <button type="submit" class="text-link" style="border:none;background:none;padding:0;cursor:pointer;color:#22c55e;">
                                    Mark all unpaid as PAID
                                </button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="6">No coaches or salary lines for this month yet.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include "includes/footer.php"; ?>
