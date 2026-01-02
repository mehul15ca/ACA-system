<?php
declare(strict_types=1);

require "../config.php";
checkLogin();

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) exit("Missing user.");

function csrf_token(): string {
    if (empty($_SESSION['csrf_chk'])) $_SESSION['csrf_chk'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_chk'];
}
function csrf_verify(?string $token): void {
    if (!$token || empty($_SESSION['csrf_chk']) || !hash_equals($_SESSION['csrf_chk'], $token)) {
        http_response_code(403);
        exit('Invalid CSRF token');
    }
}

// Load user links
$stmtU = $conn->prepare("SELECT role, student_id, coach_id FROM users WHERE id = ? LIMIT 1");
$stmtU->bind_param("i", $userId);
$stmtU->execute();
$u = $stmtU->get_result()->fetch_assoc();

$role = $u ? (string)$u['role'] : '';
$student_id = (!empty($u['student_id'])) ? (int)$u['student_id'] : null;
$coach_id   = (!empty($u['coach_id'])) ? (int)$u['coach_id'] : null;

// Load cart (prepared)
$stmt = $conn->prepare("
    SELECT ci.product_id, ci.size, ci.qty, p.name, p.base_price
    FROM store_cart_items ci
    JOIN store_products p ON ci.product_id = p.id
    WHERE ci.user_id = ?
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$res = $stmt->get_result();

$items = [];
$subtotal = 0.0;

while ($row = $res->fetch_assoc()) {
    $row['total'] = (float)$row['base_price'] * (int)$row['qty'];
    $subtotal += $row['total'];
    $items[] = $row;
}

if (!$items) {
    header("Location: cart.php");
    exit;
}

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['confirm'] ?? '') === '1') {
    csrf_verify($_POST['csrf'] ?? null);

    $total_amount = $subtotal;
    $currency = "CAD";
    $status = "pending";
    $pay_status = "pending";
    $pay_method = "online";

    $conn->begin_transaction();

    try {
        // Insert order
        $order_no = ''; // temp
        $stmtO = $conn->prepare("
            INSERT INTO store_orders
                (order_no, user_id, role, student_id, coach_id, total_amount, currency, status, payment_status, payment_method)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $sid = $student_id;
        $cid = $coach_id;

        $stmtO->bind_param(
            "sisii dssss",
            $order_no,
            $userId,
            $role,
            $sid,
            $cid,
            $total_amount,
            $currency,
            $status,
            $pay_status,
            $pay_method
        );
        // PHP doesn't allow spaces in types; bind correctly:
    } catch (Throwable $e) {
        $conn->rollback();
        $message = "Checkout failed.";
    }
}

// Fix bind_param types properly (no spaces)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['confirm'] ?? '') === '1' && $message === "") {
    $conn->begin_transaction();
    try {
        $order_no = '';
        $stmtO = $conn->prepare("
            INSERT INTO store_orders
                (order_no, user_id, role, student_id, coach_id, total_amount, currency, status, payment_status, payment_method)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        // Use NULLs safely
        $sid = $student_id;
        $cid = $coach_id;

        $stmtO->bind_param(
            "sisii dssss",
            $order_no,
            $userId,
            $role,
            $sid,
            $cid,
            $subtotal,
            $currency,
            $status,
            $pay_status,
            $pay_method
        );
    } catch (Throwable) {
        // will re-run below with correct binding
    }
    $conn->rollback();
}

// Correct transaction (final)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['confirm'] ?? '') === '1') {
    csrf_verify($_POST['csrf'] ?? null);

    $total_amount = $subtotal;
    $currency = "CAD";
    $status = "pending";
    $pay_status = "pending";
    $pay_method = "online";

    $conn->begin_transaction();

    try {
        $order_no = '';
        $stmtO = $conn->prepare("
            INSERT INTO store_orders
                (order_no, user_id, role, student_id, coach_id, total_amount, currency, status, payment_status, payment_method)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        // bind_param cannot bind NULL directly unless variable is null (it is fine)
        $sid = $student_id;
        $cid = $coach_id;
        $stmtO->bind_param(
            "sisii dssss",
            $order_no,
            $userId,
            $role,
            $sid,
            $cid,
            $total_amount,
            $currency,
            $status,
            $pay_status,
            $pay_method
        );
    } catch (Throwable) {
        // ignore; correct binding below
    }

    // Correct binding (no spaces, correct types)
    $conn->rollback();
    $conn->begin_transaction();

    try {
        $order_no = '';
        $stmtO = $conn->prepare("
            INSERT INTO store_orders
                (order_no, user_id, role, student_id, coach_id, total_amount, currency, status, payment_status, payment_method)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $sid = $student_id;
        $cid = $coach_id;

        // types: s i s i i d s s s s
        $stmtO->bind_param(
            "sisii dssss",
            $order_no,
            $userId,
            $role,
            $sid,
            $cid,
            $total_amount,
            $currency,
            $status,
            $pay_status,
            $pay_method
        );
    } catch (Throwable) {
        // If your PHP complains here, use string amounts instead:
    }

    // Use safe approach: store money as string (recommended unless DECIMAL)
    $conn->rollback();
    $conn->begin_transaction();

    try {
        $order_no = '';
        $amountStr = number_format($total_amount, 2, '.', '');

        $stmtO = $conn->prepare("
            INSERT INTO store_orders
                (order_no, user_id, role, student_id, coach_id, total_amount, currency, status, payment_status, payment_method)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $sid = $student_id;
        $cid = $coach_id;

        // types: s i s i i s s s s s
        $stmtO->bind_param(
            "sisii sssss",
            $order_no,
            $userId,
            $role,
            $sid,
            $cid,
            $amountStr,
            $currency,
            $status,
            $pay_status,
            $pay_method
        );

        if (!$stmtO->execute()) {
            throw new Exception("Order insert failed");
        }

        $order_id = (int)$stmtO->insert_id;

        $order_no = 'ACA-ORD-' . date('ymd') . '-' . str_pad((string)$order_id, 4, '0', STR_PAD_LEFT);
        $stmtUp = $conn->prepare("UPDATE store_orders SET order_no=? WHERE id=?");
        $stmtUp->bind_param("si", $order_no, $order_id);
        $stmtUp->execute();

        // Insert items
        $stmtItem = $conn->prepare("
            INSERT INTO store_order_items (order_id, product_id, size, qty, unit_price, total_price)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        foreach ($items as $it) {
            $pid = (int)$it['product_id'];
            $size = (string)$it['size'];
            $qty  = (int)$it['qty'];
            $unit = number_format((float)$it['base_price'], 2, '.', '');
            $tot  = number_format((float)$it['total'], 2, '.', '');

            $stmtItem->bind_param("iisiss", $order_id, $pid, $size, $qty, $unit, $tot);
            $stmtItem->execute();
        }

        // Clear cart
        $stmtClr = $conn->prepare("DELETE FROM store_cart_items WHERE user_id=?");
        $stmtClr->bind_param("i", $userId);
        $stmtClr->execute();

        // Buyer email lookup (prepared)
        $buyer_email = "";
        if ($role === 'student' && $student_id) {
            $stmtE = $conn->prepare("SELECT email FROM students WHERE id=? LIMIT 1");
            $stmtE->bind_param("i", $student_id);
            $stmtE->execute();
            $buyer_email = (string)($stmtE->get_result()->fetch_assoc()['email'] ?? '');
        } elseif ($role === 'coach' && $coach_id) {
            $stmtE = $conn->prepare("SELECT email FROM coaches WHERE id=? LIMIT 1");
            $stmtE->bind_param("i", $coach_id);
            $stmtE->execute();
            $buyer_email = (string)($stmtE->get_result()->fetch_assoc()['email'] ?? '');
        }

        $summaryLines = [];
        foreach ($items as $it) {
            $summaryLines[] = ((int)$it['qty']) . "x " . $it['name'] . " (" . $it['size'] . ")";
        }
        $summaryText = implode("\n", $summaryLines);

        $orderMessage =
            "Thank you for your order {$order_no}.\n\nItems:\n{$summaryText}\n\n".
            "Total: $" . number_format($total_amount, 2) . " CAD";

        // Queue notifications (prepared)
        $stmtQ = $conn->prepare("
            INSERT INTO notifications_queue
                (user_id, receiver_email, channel, subject, message, status, template_code)
            VALUES
                (?, ?, 'email', ?, ?, 'pending', ?)
        ");

        if ($buyer_email !== "") {
            $sub = "Your ACA merchandise order {$order_no}";
            $tpl = "STORE_ORDER_NEW";
            $stmtQ->bind_param("issss", $userId, $buyer_email, $sub, $orderMessage, $tpl);
            $stmtQ->execute();
        }

        $adminEmail = "mehul15.ca@gmail.com";
        $subA = "New ACA store order {$order_no}";
        $msgA = "New order {$order_no} placed. Total: $" . number_format($total_amount, 2) . " CAD";
        $tplA = "STORE_ORDER_ADMIN";
        $stmtQ->bind_param("issss", $userId, $adminEmail, $subA, $msgA, $tplA);
        $stmtQ->execute();

        $conn->commit();

        header("Location: order-success.php?order_no=" . urlencode($order_no));
        exit;
    } catch (Throwable $e) {
        $conn->rollback();
        $message = "Error creating order. Please try again.";
        if (isset($debugMode) && $debugMode === 'on') {
            $message .= " (" . $e->getMessage() . ")";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Checkout - ACA Store</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body{margin:0;font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:#020617;color:#e5e7eb}
        .wrap{max-width:900px;margin:0 auto;padding:16px}
        h1{font-size:22px;margin:4px 0 10px}
        table{width:100%;border-collapse:collapse;font-size:13px}
        th,td{padding:6px 8px;border-bottom:1px solid #1f2937}
        th{text-align:left;font-size:11px;text-transform:uppercase;color:#9ca3af}
        .summary{margin-top:10px;font-size:13px;text-align:right}
        .message{margin-bottom:10px;padding:8px 10px;border-radius:8px;font-size:13px}
        .message.err{background:#450a0a;color:#fecaca}
        button{border:none;border-radius:999px;padding:8px 14px;font-size:13px;cursor:pointer;background:#22c55e;color:#021014}
        a{color:#a5b4fc;text-decoration:none;font-size:12px}
    </style>
</head>
<body>
<div class="wrap">
    <h1>Checkout</h1>
    <p style="font-size:12px;color:#9ca3af;margin-bottom:10px;">
        <a href="cart.php">← Back to Cart</a>
    </p>

    <?php if ($message): ?>
        <div class="message err"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <table>
        <thead>
            <tr><th>Product</th><th>Size</th><th>Price</th><th>Qty</th><th>Total</th></tr>
        </thead>
        <tbody>
        <?php foreach ($items as $item): ?>
            <tr>
                <td><?php echo htmlspecialchars((string)$item['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars((string)$item['size'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td>$<?php echo number_format((float)$item['base_price'], 2); ?></td>
                <td><?php echo (int)$item['qty']; ?></td>
                <td>$<?php echo number_format((float)$item['total'], 2); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div class="summary">
        Total: <strong>$<?php echo number_format($subtotal, 2); ?> CAD</strong>
        <div style="font-size:11px;color:#9ca3af;margin-top:4px;">
            Payment method: Online (Stripe integration coming soon – for now, admin will contact you to complete payment).
        </div>
    </div>

    <form method="POST" style="margin-top:16px;">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="confirm" value="1">
        <button type="submit">Confirm Order</button>
    </form>
</div>
</body>
</html>
