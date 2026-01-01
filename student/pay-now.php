<?php
include "../config.php";
checkLogin();

// Only students should access
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
    die("Missing user.");
}

// Get linked student
$stmtU = $conn->prepare("SELECT student_id FROM users WHERE id = ?");
$stmtU->bind_param("i", $userId);
$stmtU->execute();
$rowU = $stmtU->get_result()->fetch_assoc();
if (!$rowU || !$rowU['student_id']) {
    die("No student linked.");
}
$studentId = intval($rowU['student_id']);

// Invoice ID
$invId = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($invId <= 0) {
    die("Invalid invoice.");
}

// Load invoice and ensure it belongs to this student
$stmtI = $conn->prepare("
    SELECT fi.*, s.admission_no, s.first_name, s.last_name
    FROM fees_invoices fi
    JOIN students s ON fi.student_id = s.id
    WHERE fi.id = ? AND fi.student_id = ?
");
$stmtI->bind_param("ii", $invId, $studentId);
$stmtI->execute();
$inv = $stmtI->get_result()->fetch_assoc();
if (!$inv) {
    die("Invoice not found.");
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Pay Invoice - Australasia Cricket Academy</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            margin:0;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background:#050b12;
            color:#f9fafb;
        }
        .wrap {
            max-width:480px;
            margin:0 auto;
            padding:20px 16px 40px;
        }
        .card {
            background:#0b1724;
            border-radius:18px;
            padding:18px 16px 16px;
            box-shadow:0 18px 34px rgba(0,0,0,0.65);
        }
        h1 {
            font-size:22px;
            margin:0 0 4px;
        }
        .sub {
            font-size:12px;
            color:#9ca3af;
            margin-bottom:16px;
        }
        .amount {
            font-size:26px;
            font-weight:650;
            margin:8px 0 4px;
        }
        .meta {
            font-size:12px;
            color:#9ca3af;
            line-height:1.5;
        }
        .notice {
            margin-top:16px;
            background:#020617;
            border-radius:12px;
            padding:10px 12px;
            font-size:12px;
            color:#e5e7eb;
        }
        .pill {
            display:inline-block;
            padding:2px 10px;
            border-radius:999px;
            font-size:11px;
            text-transform:uppercase;
            letter-spacing:0.08em;
        }
        .pill-unpaid { background:#451a03; color:#fed7aa; }
        .pill-paid { background:#022c22; color:#6ee7b7; }
        .btn-back {
            margin-top:18px;
            display:inline-block;
            padding:8px 16px;
            border-radius:999px;
            background:#020617;
            color:#e5e7eb;
            font-size:13px;
            text-decoration:none;
        }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h1>Pay Invoice</h1>
        <div class="sub">
            Invoice: <?php echo htmlspecialchars($inv['invoice_no']); ?><br>
            Student: <?php echo htmlspecialchars($inv['first_name'] . " " . $inv['last_name']); ?> (<?php echo htmlspecialchars($inv['admission_no']); ?>)
        </div>

        <div class="amount">
            <?php echo number_format($inv['amount'], 2) . " " . htmlspecialchars($inv['currency']); ?>
        </div>
        <div class="meta">
            Status:
            <?php
                $cls = 'pill-unpaid';
                $label = $inv['status'];
                if ($inv['status'] === 'paid') { $cls = 'pill-paid'; }
            ?>
            <span class="pill <?php echo $cls; ?>"><?php echo htmlspecialchars(strtoupper($label)); ?></span><br>
            Due Date: <?php echo htmlspecialchars($inv['due_date']); ?><br>
            Period: <?php echo htmlspecialchars($inv['period_from']); ?> → <?php echo htmlspecialchars($inv['period_to']); ?>
        </div>

        <div class="notice">
            Online payment is not enabled yet for this academy account.<br><br>
            You will receive a secure payment link once the online gateway is activated.<br><br>
            For now, please follow the instructions shared by the academy for fee payment
            (e‑transfer, manual link, or in-person).
        </div>

        <a href="my-fees.php" class="btn-back">← Back to My Fees</a>
    </div>
</div>
</body>
</html>
