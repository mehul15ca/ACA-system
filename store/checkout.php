<?php
include "../config.php";
checkLogin();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$userId = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
if ($userId <= 0) die("Missing user.");

$role = currentUserRole();

// Load user link to student/coach
$student_id = null;
$coach_id = null;
$stmtU = $conn->prepare("SELECT role, student_id, coach_id FROM users WHERE id = ?");
$stmtU->bind_param("i", $userId);
$stmtU->execute();
$u = $stmtU->get_result()->fetch_assoc();
if ($u) {
    $student_id = $u['student_id'] ? intval($u['student_id']) : null;
    $coach_id   = $u['coach_id'] ? intval($u['coach_id']) : null;
}

// Load cart
$sql = "
    SELECT ci.*, p.name, p.base_price
    FROM store_cart_items ci
    JOIN store_products p ON ci.product_id = p.id
    WHERE ci.user_id = {$userId}
";
$res = $conn->query($sql);

$items = [];
$subtotal = 0.0;
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $row['total'] = $row['base_price'] * $row['qty'];
        $subtotal += $row['total'];
        $items[] = $row;
    }
}

if (count($items) === 0) {
    header("Location: cart.php");
    exit;
}

$message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm']) && $_POST['confirm']=='1') {
    // Create order
    $total_amount = $subtotal;
    $currency = "CAD";

    // Insert order
    $role_db = $conn->real_escape_string($role);
    $student_sql = $student_id !== null ? intval($student_id) : "NULL";
    $coach_sql   = $coach_id !== null ? intval($coach_id)   : "NULL";

    $sqlOrder = "
        INSERT INTO store_orders
            (order_no, user_id, role, student_id, coach_id, total_amount, currency, status, payment_status, payment_method)
        VALUES
            ('', {$userId}, '{$role_db}', {$student_sql}, {$coach_sql}, {$total_amount}, '{$currency}', 'pending', 'pending', 'online')
    ";
    if ($conn->query($sqlOrder)) {
        $order_id = $conn->insert_id;
        // Generate order_no using ID
        $order_no = 'ACA-ORD-' . date('ymd') . '-' . str_pad($order_id, 4, '0', STR_PAD_LEFT);
        $conn->query("
            UPDATE store_orders
            SET order_no = '".$conn->real_escape_string($order_no)."'
            WHERE id = {$order_id}
        ");

        // Insert order items
        foreach ($items as $item) {
            $pid = intval($item['product_id']);
            $size = $conn->real_escape_string($item['size']);
            $qty  = intval($item['qty']);
            $unit = floatval($item['base_price']);
            $total = floatval($item['total']);
            $conn->query("
                INSERT INTO store_order_items (order_id, product_id, size, qty, unit_price, total_price)
                VALUES ({$order_id}, {$pid}, '{$size}', {$qty}, {$unit}, {$total})
            ");
        }

        // Clear cart
        $conn->query("DELETE FROM store_cart_items WHERE user_id={$userId}");

        // Queue emails
        // 1) Get buyer email
        $buyer_email = "";
        if ($role === 'student' && $student_id) {
            $resE = $conn->query("SELECT email FROM students WHERE id=".(int)$student_id);
            if ($resE && ($rowE = $resE->fetch_assoc())) {
                $buyer_email = $rowE['email'];
            }
        } elseif ($role === 'coach' && $coach_id) {
            $resE = $conn->query("SELECT email FROM coaches WHERE id=".(int)$coach_id);
            if ($resE && ($rowE = $resE->fetch_assoc())) {
                $buyer_email = $rowE['email'];
            }
        }

        $summaryLines = [];
        foreach ($items as $item) {
            $summaryLines[] = $item['qty'] . "x " . $item['name'] . " (" . $item['size'] . ")";
        }
        $summaryText = implode("\n", $summaryLines);
        $orderMessage = "Thank you for your order " . $order_no . ".\n\nItems:\n" .
            $summaryText . "\n\nTotal: $" . number_format($total_amount,2) . " CAD";

        // Insert email for buyer
        if ($buyer_email !== "") {
            $emailEsc = $conn->real_escape_string($buyer_email);
            $subjectEsc = $conn->real_escape_string("Your ACA merchandise order ".$order_no);
            $msgEsc = $conn->real_escape_string($orderMessage);

            $conn->query("
                INSERT INTO notifications_queue
                    (user_id, receiver_email, channel, subject, message, status, template_code)
                VALUES
                    ({$userId}, '{$emailEsc}', 'email', '{$subjectEsc}', '{$msgEsc}', 'pending', 'STORE_ORDER_NEW')
            ");
        }

        // Insert email for superadmin
        $adminEmail = "mehul15.ca@gmail.com";
        $adminEsc = $conn->real_escape_string($adminEmail);
        $subjectAdmin = $conn->real_escape_string("New ACA store order ".$order_no);
        $msgAdmin = $conn->real_escape_string("New order ".$order_no." placed. Total: $".number_format($total_amount,2)." CAD");

        $conn->query("
            INSERT INTO notifications_queue
                (user_id, receiver_email, channel, subject, message, status, template_code)
            VALUES
                ({$userId}, '{$adminEsc}', 'email', '{$subjectAdmin}', '{$msgAdmin}', 'pending', 'STORE_ORDER_ADMIN')
        ");

        header("Location: order-success.php?order_no=".urlencode($order_no));
        exit;
    } else {
        $message = "Error creating order: ".$conn->error;
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
        body {
            margin:0;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background:#020617;
            color:#e5e7eb;
        }
        .wrap { max-width:900px; margin:0 auto; padding:16px; }
        h1 { font-size:22px; margin:4px 0 10px; }
        table { width:100%; border-collapse:collapse; font-size:13px; }
        th, td { padding:6px 8px; border-bottom:1px solid #1f2937; }
        th { text-align:left; font-size:11px; text-transform:uppercase; color:#9ca3af; }
        .summary { margin-top:10px; font-size:13px; text-align:right; }
        .message { margin-bottom:10px; padding:8px 10px; border-radius:8px; font-size:13px; }
        .message.err { background:#450a0a; color:#fecaca; }
        button {
            border:none;
            border-radius:999px;
            padding:8px 14px;
            font-size:13px;
            cursor:pointer;
            background:#22c55e;
            color:#021014;
        }
        a { color:#a5b4fc; text-decoration:none; font-size:12px; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Checkout</h1>
    <p style="font-size:12px;color:#9ca3af;margin-bottom:10px;">
        <a href="cart.php">← Back to Cart</a>
    </p>

    <?php if ($message): ?>
        <div class="message err"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <table>
        <thead>
            <tr>
                <th>Product</th>
                <th>Size</th>
                <th>Price</th>
                <th>Qty</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($items as $item): ?>
            <tr>
                <td><?php echo htmlspecialchars($item['name']); ?></td>
                <td><?php echo htmlspecialchars($item['size']); ?></td>
                <td>$<?php echo number_format($item['base_price'], 2); ?></td>
                <td><?php echo (int)$item['qty']; ?></td>
                <td>$<?php echo number_format($item['total'], 2); ?></td>
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
        <input type="hidden" name="confirm" value="1">
        <button type="submit">Confirm Order</button>
    </form>
</div>
</body>
</html>
