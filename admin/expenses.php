<?php
require_once __DIR__ . '/_bootstrap.php';

// Default date range: current month
$today = date('Y-m-d');
$firstOfMonth = date('Y-m-01');

$from = isset($_GET['from']) && $_GET['from'] !== '' ? $_GET['from'] : $firstOfMonth;
$to   = isset($_GET['to']) && $_GET['to'] !== '' ? $_GET['to']   : $today;
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$method   = isset($_GET['method']) ? trim($_GET['method']) : '';

// 1) Summary – Expenses
$sqlExp = "SELECT IFNULL(SUM(amount),0) AS total_expense
           FROM expenses
           WHERE expense_date BETWEEN ? AND ?";
$paramsExp = [$from, $to];
$typesExp  = "ss";

if ($category !== '') {
    $sqlExp .= " AND category = ?";
    $paramsExp[] = $category;
    $typesExp   .= "s";
}
if ($method !== '') {
    $sqlExp .= " AND payment_method = ?";
    $paramsExp[] = $method;
    $typesExp   .= "s";
}

$stmtExp = $conn->prepare($sqlExp);
$stmtExp->bind_param($typesExp, ...$paramsExp);
$stmtExp->execute();
$resExp = $stmtExp->get_result()->fetch_assoc();
$totalExpense = $resExp ? floatval($resExp['total_expense']) : 0.0;

// 2) Summary – Income (fees_payments)
$sqlInc = "SELECT IFNULL(SUM(amount),0) AS total_income
           FROM fees_payments
           WHERE DATE(paid_on) BETWEEN ? AND ?
             AND currency = 'CAD'";
$stmtInc = $conn->prepare($sqlInc);
$stmtInc->bind_param("ss", $from, $to);
$stmtInc->execute();
$resInc = $stmtInc->get_result()->fetch_assoc();
$totalIncome = $resInc ? floatval($resInc['total_income']) : 0.0;

$net = $totalIncome - $totalExpense;

// 3) Expense list
$sqlList = "SELECT * FROM expenses WHERE expense_date BETWEEN ? AND ?";
$paramsList = [$from, $to];
$typesList  = "ss";

if ($category !== '') {
    $sqlList .= " AND category = ?";
    $paramsList[] = $category;
    $typesList   .= "s";
}
if ($method !== '') {
    $sqlList .= " AND payment_method = ?";
    $paramsList[] = $method;
    $typesList   .= "s";
}

$sqlList .= " ORDER BY expense_date DESC, created_at DESC";

$stmtList = $conn->prepare($sqlList);
$stmtList->bind_param($typesList, ...$paramsList);
$stmtList->execute();
$resList = $stmtList->get_result();

// Distinct categories & methods for filters
$catsRes = $conn->query("SELECT DISTINCT category FROM expenses ORDER BY category ASC");
$methodsRes = $conn->query("SELECT DISTINCT payment_method FROM expenses WHERE payment_method IS NOT NULL AND payment_method <> '' ORDER BY payment_method ASC");
?>
<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Expenses</h1>

<div class="form-card" style="margin-bottom:16px;">
    <form method="GET" style="display:flex; flex-wrap:wrap; gap:10px; align-items:flex-end;">
        <div>
            <label style="font-size:12px;">From</label>
            <input type="date" name="from" value="<?php echo htmlspecialchars($from); ?>">
        </div>
        <div>
            <label style="font-size:12px;">To</label>
            <input type="date" name="to" value="<?php echo htmlspecialchars($to); ?>">
        </div>
        <div>
            <label style="font-size:12px;">Category</label>
            <select name="category">
                <option value="">All</option>
                <?php if ($catsRes): ?>
                    <?php while ($c = $catsRes->fetch_assoc()): ?>
                        <option value="<?php echo htmlspecialchars($c['category']); ?>"
                            <?php if ($category === $c['category']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($c['category']); ?>
                        </option>
                    <?php endwhile; ?>
                <?php endif; ?>
            </select>
        </div>
        <div>
            <label style="font-size:12px;">Payment Method</label>
            <select name="method">
                <option value="">All</option>
                <?php if ($methodsRes): ?>
                    <?php while ($m = $methodsRes->fetch_assoc()): ?>
                        <option value="<?php echo htmlspecialchars($m['payment_method']); ?>"
                            <?php if ($method === $m['payment_method']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($m['payment_method']); ?>
                        </option>
                    <?php endwhile; ?>
                <?php endif; ?>
            </select>
        </div>
        <div>
            <button type="submit" class="button-primary">Filter</button>
        </div>
        <div style="margin-left:auto;">
            <a href="expense-add.php" class="button">➕ Add Expense</a>
        </div>
    </form>
</div>

<div class="form-card" style="display:flex; flex-wrap:wrap; gap:16px; margin-bottom:16px;">
    <div style="flex:1; min-width:180px;">
        <div style="font-size:11px; color:#6b7280;">Total Expenses</div>
        <div style="font-size:18px; font-weight:600; color:#ef4444;">
            $<?php echo number_format($totalExpense, 2); ?> CAD
        </div>
    </div>
    <div style="flex:1; min-width:180px;">
        <div style="font-size:11px; color:#6b7280;">Total Fee Income</div>
        <div style="font-size:18px; font-weight:600; color:#22c55e;">
            $<?php echo number_format($totalIncome, 2); ?> CAD
        </div>
    </div>
    <div style="flex:1; min-width:180px;">
        <div style="font-size:11px; color:#6b7280;">Net (Income - Expense)</div>
        <div style="font-size:18px; font-weight:600; color:<?php echo $net >= 0 ? '#22c55e' : '#ef4444'; ?>;">
            $<?php echo number_format($net, 2); ?> CAD
        </div>
    </div>
</div>

<div class="table-card">
    <div class="table-header">
        <h2>Expenses List</h2>
    </div>
    <table class="acatable">
        <thead>
            <tr>
                <th>Date</th>
                <th>Category</th>
                <th>Amount</th>
                <th>Method</th>
                <th>Vendor</th>
                <th>Notes</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($resList && $resList->num_rows > 0): ?>
            <?php while ($e = $resList->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($e['expense_date']); ?></td>
                    <td>
                        <?php
                        $cat = $e['category'];
                        if (!empty($e['subcategory'])) {
                            $cat .= " / " . $e['subcategory'];
                        }
                        echo htmlspecialchars($cat);
                        ?>
                    </td>
                    <td>$<?php echo number_format($e['amount'], 2); ?> CAD</td>
                    <td><?php echo htmlspecialchars($e['payment_method']); ?></td>
                    <td><?php echo htmlspecialchars($e['vendor']); ?></td>
                    <td>
                        <?php
                        $short = mb_substr($e['notes'], 0, 40);
                        if (mb_strlen($e['notes']) > 40) $short .= "...";
                        echo htmlspecialchars($short);
                        ?>
                    </td>
                    <td>
                        <a href="expense-view.php?id=<?php echo $e['id']; ?>" class="text-link">View</a> |
                        <a href="expense-edit.php?id=<?php echo $e['id']; ?>" class="text-link">Edit</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="7">No expenses found for this period.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include "includes/footer.php"; ?>
