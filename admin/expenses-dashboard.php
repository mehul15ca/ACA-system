<?php
require_once __DIR__ . '/_bootstrap.php';

if (!hasPermission('manage_expenses')) {
    http_response_code(403);
    echo "Access denied.";
    exit;
}



// Date filters
$year = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));
$month = isset($_GET['month']) ? intval($_GET['month']) : intval(date('n'));

$monthStart = sprintf('%04d-%02d-01', $year, $month);
$monthEnd = date('Y-m-d', strtotime($monthStart . ' +1 month -1 day'));
$yearStart = sprintf('%04d-01-01', $year);
$yearEnd = sprintf('%04d-12-31', $year);

// Helper: get single value
function aca_sum($conn, $sql, $types = "", $params = []) {
    $stmt = $conn->prepare($sql);
    if ($types && $params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result()->fetch_row();
    return $res && $res[0] !== null ? (float)$res[0] : 0.0;
}

// 1) Expenses - this month / year
$monthExpenses = aca_sum(
    $conn,
    "SELECT SUM(total_amount) FROM expenses WHERE expense_date BETWEEN ? AND ? AND status IN ('paid','pending')",
    "ss",
    [$monthStart, $monthEnd]
);
$yearExpenses = aca_sum(
    $conn,
    "SELECT SUM(total_amount) FROM expenses WHERE expense_date BETWEEN ? AND ? AND status IN ('paid','pending')",
    "ss",
    [$yearStart, $yearEnd]
);

// 2) Income (fees) - this month/year
$monthFeesIncome = aca_sum(
    $conn,
    "SELECT SUM(amount) FROM fees_payments WHERE DATE(paid_on) BETWEEN ? AND ?",
    "ss",
    [$monthStart, $monthEnd]
);
$yearFeesIncome = aca_sum(
    $conn,
    "SELECT SUM(amount) FROM fees_payments WHERE DATE(paid_on) BETWEEN ? AND ?",
    "ss",
    [$yearStart, $yearEnd]
);

// 3) Income (store) - this month/year
$monthStoreIncome = aca_sum(
    $conn,
    "SELECT SUM(total_amount) FROM store_orders WHERE DATE(created_at) BETWEEN ? AND ? AND status IN ('paid','delivered','completed')",
    "ss",
    [$monthStart, $monthEnd]
);
$yearStoreIncome = aca_sum(
    $conn,
    "SELECT SUM(total_amount) FROM store_orders WHERE DATE(created_at) BETWEEN ? AND ? AND status IN ('paid','delivered','completed')",
    "ss",
    [$yearStart, $yearEnd]
);

$monthIncome = $monthFeesIncome + $monthStoreIncome;
$yearIncome = $yearFeesIncome + $yearStoreIncome;

$monthNet = $monthIncome - $monthExpenses;
$yearNet = $yearIncome - $yearExpenses;

// Category-wise breakdown (month)
$cats = ['Salary','Rent','Equipment','Merchandise','Marketing','Misc'];
$catData = [];
$stmtCat = $conn->prepare("
    SELECT category, SUM(total_amount) AS total_cat
    FROM expenses
    WHERE expense_date BETWEEN ? AND ?
      AND status IN ('paid','pending')
    GROUP BY category
");
$stmtCat->bind_param("ss", $monthStart, $monthEnd);
$stmtCat->execute();
$resCat = $stmtCat->get_result();
while ($row = $resCat->fetch_assoc()) {
    $catData[$row['category']] = (float)$row['total_cat'];
}
$catTotals = [];
foreach ($cats as $c) {
    $catTotals[] = isset($catData[$c]) ? $catData[$c] : 0.0;
}

// Monthly expenses for year
$monthlyExp = [];
for ($m = 1; $m <= 12; $m++) {
    $ms = sprintf('%04d-%02d-01', $year, $m);
    $me = date('Y-m-d', strtotime($ms . ' +1 month -1 day'));
    $monthlyExp[$m] = aca_sum(
        $conn,
        "SELECT SUM(total_amount) FROM expenses WHERE expense_date BETWEEN ? AND ? AND status IN ('paid','pending')",
        "ss",
        [$ms, $me]
    );
}

// Monthly income (fees + store) for year
$monthlyInc = [];
for ($m = 1; $m <= 12; $m++) {
    $ms = sprintf('%04d-%02d-01', $year, $m);
    $me = date('Y-m-d', strtotime($ms . ' +1 month -1 day'));

    $mf = aca_sum(
        $conn,
        "SELECT SUM(amount) FROM fees_payments WHERE DATE(paid_on) BETWEEN ? AND ?",
        "ss",
        [$ms, $me]
    );
    $msi = aca_sum(
        $conn,
        "SELECT SUM(total_amount) FROM store_orders WHERE DATE(created_at) BETWEEN ? AND ? AND status IN ('paid','delivered','completed')",
        "ss",
        [$ms, $me]
    );
    $monthlyInc[$m] = $mf + $msi;
}

$yearOptions = [];
$currentYear = intval(date('Y'));
for ($y = $currentYear - 3; $y <= $currentYear + 1; $y++) {
    $yearOptions[] = $y;
}

$monthNames = [1=>'Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
?>
<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Expense & Income Dashboard</h1>
<p style="font-size:13px;color:#9ca3af;margin-bottom:12px;">
    Finance overview for <?php echo htmlspecialchars(date('F Y', strtotime($monthStart))); ?> (and full year <?php echo $year; ?>).
</p>

<form method="GET" style="margin-bottom:12px;display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
    <label style="font-size:13px;">
        Month:
        <select name="month">
            <?php foreach ($monthNames as $num=>$name): ?>
                <option value="<?php echo $num; ?>" <?php echo $num === $month ? 'selected' : ''; ?>>
                    <?php echo $name; ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>
    <label style="font-size:13px;">
        Year:
        <select name="year">
            <?php foreach ($yearOptions as $y): ?>
                <option value="<?php echo $y; ?>" <?php echo $y === $year ? 'selected' : ''; ?>>
                    <?php echo $y; ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>
    <button type="submit" class="button-primary">Update</button>
    <a href="expenses-export.php" class="button-secondary" style="margin-left:8px;font-size:13px;">Exports</a>
</form>

<div class="cards-grid-4">
    <div class="card">
        <div class="card-label">Month Income</div>
        <div class="card-value">$<?php echo number_format($monthIncome,2); ?></div>
        <div class="card-sub">Fees + Store (<?php echo htmlspecialchars(date('M Y', strtotime($monthStart))); ?>)</div>
    </div>
    <div class="card">
        <div class="card-label">Month Expenses</div>
        <div class="card-value">$<?php echo number_format($monthExpenses,2); ?></div>
        <div class="card-sub">All categories</div>
    </div>
    <div class="card">
        <div class="card-label">Month Net</div>
        <div class="card-value"
             style="color:<?php echo $monthNet >= 0 ? '#4ade80' : '#f97373'; ?>;">
            $<?php echo number_format($monthNet,2); ?>
        </div>
        <div class="card-sub">Income - Expenses</div>
    </div>
    <div class="card">
        <div class="card-label">Year Net (<?php echo $year; ?>)</div>
        <div class="card-value"
             style="color:<?php echo $yearNet >= 0 ? '#4ade80' : '#f97373'; ?>;">
            $<?php echo number_format($yearNet,2); ?>
        </div>
        <div class="card-sub">All months</div>
    </div>
</div>

<div class="cards-grid-2" style="margin-top:16px;">
    <div class="card">
        <h2 class="card-title">Monthly Expenses (<?php echo $year; ?>)</h2>
        <canvas id="chartMonthlyExpenses" height="140"></canvas>
    </div>
    <div class="card">
        <h2 class="card-title">Income vs Expenses (<?php echo $year; ?>)</h2>
        <canvas id="chartIncomeVsExpenses" height="140"></canvas>
    </div>
</div>

<div class="cards-grid-2" style="margin-top:16px;">
    <div class="card">
        <h2 class="card-title">Category Split (<?php echo htmlspecialchars(date('M Y', strtotime($monthStart))); ?>)</h2>
        <canvas id="chartCategoryPie" height="160"></canvas>
    </div>
    <div class="card">
        <h2 class="card-title">Category Table</h2>
        <table class="table-basic">
            <thead>
                <tr>
                    <th>Category</th>
                    <th style="text-align:right;">Amount</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($cats as $idx=>$cat): ?>
                <tr>
                    <td><?php echo htmlspecialchars($cat); ?></td>
                    <td style="text-align:right;">$<?php echo number_format($catTotals[$idx],2); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const monthLabels = <?php echo json_encode(array_values($monthNames)); ?>;
const monthlyExpenses = <?php echo json_encode(array_values($monthlyExp)); ?>;
const monthlyIncome = <?php echo json_encode(array_values($monthlyInc)); ?>;
const catLabels = <?php echo json_encode($cats); ?>;
const catValues = <?php echo json_encode($catTotals); ?>;

const ctxExp = document.getElementById('chartMonthlyExpenses').getContext('2d');
new Chart(ctxExp, {
    type: 'bar',
    data: {
        labels: monthLabels,
        datasets: [{
            label: 'Expenses',
            data: monthlyExpenses
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true }
        }
    }
});

const ctxIncExp = document.getElementById('chartIncomeVsExpenses').getContext('2d');
new Chart(ctxIncExp, {
    type: 'line',
    data: {
        labels: monthLabels,
        datasets: [
            {
                label: 'Income',
                data: monthlyIncome
            },
            {
                label: 'Expenses',
                data: monthlyExpenses
            }
        ]
    },
    options: {
        responsive: true,
        scales: {
            y: { beginAtZero: true }
        }
    }
});

const ctxPie = document.getElementById('chartCategoryPie').getContext('2d');
new Chart(ctxPie, {
    type: 'pie',
    data: {
        labels: catLabels,
        datasets: [{
            data: catValues
        }]
    },
    options: {
        responsive: true
    }
});
</script>

<?php include "includes/footer.php"; ?>
