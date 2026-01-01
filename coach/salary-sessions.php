<?php
include "../config.php";
checkLogin();
$role = currentUserRole();
if ($role !== 'coach') {
    http_response_code(403);
    echo "Access denied.";
    exit;
}
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$userId = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
if ($userId <= 0) die("Missing user.");

$stmtU = $conn->prepare("SELECT coach_id FROM users WHERE id = ?");
$stmtU->bind_param("i", $userId);
$stmtU->execute();
$u = $stmtU->get_result()->fetch_assoc();
if (!$u || !$u['coach_id']) die("No coach linked.");
$coach_id = intval($u['coach_id']);

$stmtC = $conn->prepare("SELECT name FROM coaches WHERE id = ?");
$stmtC->bind_param("i", $coach_id);
$stmtC->execute();
$coach = $stmtC->get_result()->fetch_assoc();
if (!$coach) die("Coach not found.");

$year  = isset($_GET['year'])  && $_GET['year']  !== '' ? intval($_GET['year'])  : intval(date('Y'));
$month = isset($_GET['month']) && $_GET['month'] !== '' ? intval($_GET['month']) : intval(date('n'));
if ($month < 1 || $month > 12) $month = intval(date('n'));
if ($year < 2000 || $year > 2100) $year = intval(date('Y'));

$sql = "
    SELECT s.*, b.name AS batch_name
    FROM coach_salary_sessions s
    LEFT JOIN batches b ON s.batch_id = b.id
    WHERE s.coach_id = {$coach_id}
      AND s.month = {$month}
      AND s.year = {$year}
    ORDER BY s.session_date ASC, s.id ASC
";
$res = $conn->query($sql);

$total_amount = 0;
$total_unpaid = 0;
$total_hours  = 0;
if ($res) {
    foreach ($res as $row) {
        $total_amount += floatval($row['amount']);
        if ($row['status'] === 'unpaid') {
            $total_unpaid += floatval($row['amount']);
        }
        $total_hours += floatval($row['hours']);
    }
    $res->data_seek(0);
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>My Salary - Australasia Cricket Academy</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            margin:0;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background:#050b12;
            color:#f9fafb;
        }
        .wrap {
            max-width:960px;
            margin:0 auto;
            padding:16px;
        }
        h1 { font-size:22px; margin:4px 0 2px; }
        .sub { font-size:12px; color:#9ca3af; margin-bottom:12px; }
        .filters {
            display:flex;
            flex-wrap:wrap;
            gap:8px;
            align-items:center;
            margin-bottom:12px;
            font-size:12px;
        }
        .filters select, .filters input {
            padding:4px 6px;
            border-radius:6px;
            border:1px solid #1f2937;
            background:#020617;
            color:#f9fafb;
        }
        .filters button {
            padding:6px 10px;
            border-radius:999px;
            border:none;
            background:#22c55e;
            color:#020617;
            font-size:12px;
            cursor:pointer;
        }
        table {
            width:100%;
            border-collapse:collapse;
            font-size:12px;
            margin-top:8px;
        }
        th, td {
            padding:6px 8px;
            border-bottom:1px solid #1f2937;
        }
        th {
            text-align:left;
            font-size:11px;
            text-transform:uppercase;
            letter-spacing:0.03em;
            color:#9ca3af;
        }
        .paid {
            color:#22c55e;
            font-weight:600;
        }
        .unpaid {
            color:#ef4444;
            font-weight:600;
        }
        .summary {
            font-size:12px;
            color:#9ca3af;
            margin-top:8px;
        }
    </style>
</head>
<body>
<div class="wrap">
    <h1>My Salary</h1>
    <div class="sub">
        Coach: <?php echo htmlspecialchars($coach['name']); ?>
    </div>

    <form method="GET" class="filters">
        <div>
            <label>Month</label>
            <select name="month">
                <?php for ($m=1;$m<=12;$m++): ?>
                    <option value="<?php echo $m; ?>" <?php if ($m==$month) echo 'selected'; ?>>
                        <?php echo date('F', mktime(0,0,0,$m,1)); ?>
                    </option>
                <?php endfor; ?>
            </select>
        </div>
        <div>
            <label>Year</label>
            <input type="number" name="year" value="<?php echo $year; ?>" style="width:80px;">
        </div>
        <div>
            <button type="submit">Apply</button>
        </div>
    </form>

    <div class="summary">
        Total: $<?php echo number_format($total_amount, 2); ?> CAD ·
        Unpaid: <span class="unpaid">$<?php echo number_format($total_unpaid, 2); ?> CAD</span> ·
        Hours (hour-based only): <?php echo number_format($total_hours, 2); ?>
    </div>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Batch</th>
                <th>Type</th>
                <th>Hours</th>
                <th>Rate</th>
                <th>Amount</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($res && $res->num_rows > 0): ?>
            <?php while ($s = $res->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($s['session_date']); ?></td>
                    <td><?php echo htmlspecialchars($s['batch_name']); ?></td>
                    <td><?php echo htmlspecialchars($s['rate_type']); ?></td>
                    <td><?php echo number_format($s['hours'], 2); ?></td>
                    <td>$<?php echo number_format($s['rate_amount'], 2); ?></td>
                    <td>$<?php echo number_format($s['amount'], 2); ?></td>
                    <td>
                        <?php if ($s['status'] === 'paid'): ?>
                            <span class="paid">PAID</span>
                        <?php else: ?>
                            <span class="unpaid">UNPAID</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="7">No salary lines for this month yet.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>
