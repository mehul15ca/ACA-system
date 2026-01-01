<?php
include "../config.php";
checkLogin();

$role = currentUserRole();
if ($role !== 'student') {
    http_response_code(403);
    echo "Access denied.";
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$userId = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
if ($userId <= 0) {
    die("Missing user session.");
}

// Get linked student
$stmt = $conn->prepare("SELECT student_id FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$u = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$u || !$u['student_id']) {
    die("No student profile linked to this user.");
}
$student_id = (int)$u['student_id'];

// Load invoices
$sql = "
    SELECT fi.*, fp.name AS plan_name
    FROM fees_invoices fi
    LEFT JOIN fees_plans fp ON fi.plan_id = fp.id
    WHERE fi.student_id = {$student_id}
    ORDER BY fi.due_date DESC, fi.created_at DESC
";
$res = $conn->query($sql);

// Helper: outstanding
function aca_outstanding_for_invoice($conn, $invoice_id) {
    $invoice_id = (int)$invoice_id;
    $resInv = $conn->query("SELECT amount FROM fees_invoices WHERE id={$invoice_id}");
    if (!$resInv || !$inv = $resInv->fetch_assoc()) {
        return 0;
    }
    $amount = (float)$inv['amount'];
    $paid = 0.0;
    $resPay = $conn->query("SELECT SUM(amount) AS total_paid FROM fees_payments WHERE invoice_id={$invoice_id}");
    if ($resPay && ($rowP = $resPay->fetch_assoc()) && $rowP['total_paid'] !== null) {
        $paid = (float)$rowP['total_paid'];
    }
    return $amount - $paid;
}

$invoices = [];
$totalOutstanding = 0.0;
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $outstanding = aca_outstanding_for_invoice($conn, $row['id']);
        $row['outstanding'] = $outstanding;
        if (in_array($row['status'], ['unpaid','partial'])) {
            $totalOutstanding += $outstanding;
        }
        $invoices[] = $row;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>My Fees - Australasia Cricket Academy</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            margin:0;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background:#020617;
            color:#e5e7eb;
        }
        .wrap {
            max-width:900px;
            margin:0 auto;
            padding:16px;
        }
        h1 {
            font-size:22px;
            margin:4px 0 4px;
        }
        .sub {
            font-size:13px;
            color:#9ca3af;
            margin-bottom:12px;
        }
        .summary-box {
            background:#020617;
            border-radius:16px;
            border:1px solid #1f2937;
            padding:12px;
            margin-bottom:14px;
            font-size:13px;
        }
        .summary-title {
            font-size:12px;
            text-transform:uppercase;
            color:#9ca3af;
            margin-bottom:4px;
        }
        .summary-value {
            font-size:18px;
            font-weight:600;
            color:#facc15;
        }
        table {
            width:100%;
            border-collapse:collapse;
            font-size:13px;
        }
        th, td {
            padding:6px 8px;
            border-bottom:1px solid #1f2937;
        }
        th {
            text-align:left;
            font-size:11px;
            text-transform:uppercase;
            color:#9ca3af;
        }
        .status-pill {
            display:inline-block;
            padding:2px 8px;
            border-radius:999px;
            font-size:11px;
        }
        .status-unpaid { background:#450a0a; color:#fecaca; }
        .status-partial { background:#451a03; color:#fed7aa; }
        .status-paid { background:#022c22; color:#bbf7d0; }
        .status-cancelled { background:#1f2937; color:#9ca3af; }
        .pay-btn {
            border-radius:999px;
            border:1px solid #4b5563;
            background:#0f172a;
            color:#9ca3af;
            padding:4px 10px;
            font-size:11px;
            cursor:not-allowed;
            opacity:0.7;
        }
        .note {
            font-size:11px;
            color:#9ca3af;
            margin-top:6px;
        }
    </style>
</head>
<body>
<div class="wrap">
    <h1>My Fees</h1>
    <div class="sub">View your invoices, status, and outstanding balance.</div>

    <div class="summary-box">
        <div class="summary-title">Total Outstanding</div>
        <div class="summary-value">$<?php echo number_format($totalOutstanding, 2); ?> CAD</div>
        <div class="note">
            Online payment is coming soon. For now, please follow the academy's instructions for fee payment.
        </div>
    </div>

    <?php if (empty($invoices)): ?>
        <p style="font-size:13px;color:#9ca3af;">No invoices found yet.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Invoice</th>
                    <th>Plan</th>
                    <th>Period</th>
                    <th>Amount</th>
                    <th>Outstanding</th>
                    <th>Due Date</th>
                    <th>Status</th>
                    <th>Pay</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($invoices as $inv): ?>
                <?php
                $statusClass = 'status-unpaid';
                if ($inv['status'] === 'paid') $statusClass = 'status-paid';
                elseif ($inv['status'] === 'partial') $statusClass = 'status-partial';
                elseif ($inv['status'] === 'cancelled') $statusClass = 'status-cancelled';

                $period = '';
                if (!empty($inv['period_from']) && !empty($inv['period_to'])) {
                    $period = $inv['period_from']." to ".$inv['period_to'];
                }
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($inv['invoice_no']); ?></td>
                    <td><?php echo htmlspecialchars($inv['plan_name'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($period); ?></td>
                    <td>$<?php echo number_format($inv['amount'], 2); ?> <?php echo htmlspecialchars($inv['currency']); ?></td>
                    <td>$<?php echo number_format($inv['outstanding'], 2); ?> <?php echo htmlspecialchars($inv['currency']); ?></td>
                    <td><?php echo htmlspecialchars($inv['due_date']); ?></td>
                    <td>
                        <span class="status-pill <?php echo $statusClass; ?>">
                            <?php echo ucfirst($inv['status']); ?>
                        </span>
                    </td>
                    <td>
                        <?php if (in_array($inv['status'], ['unpaid','partial'])): ?>
                            <button class="pay-btn" disabled>Pay Now (coming soon)</button>
                        <?php else: ?>
                            <span style="font-size:11px;color:#9ca3af;">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
</body>
</html>
