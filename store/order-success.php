<?php
include "../config.php";
checkLogin();
$order_no = isset($_GET['order_no']) ? $_GET['order_no'] : '';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Order Placed - ACA Store</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            margin:0;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background:#020617;
            color:#e5e7eb;
        }
        .wrap { max-width:600px; margin:0 auto; padding:24px 16px; text-align:center; }
        h1 { font-size:24px; margin-bottom:8px; }
        p { font-size:13px; color:#9ca3af; margin-bottom:6px; }
        a { color:#22c55e; text-decoration:none; font-size:13px; }
        .order-no { font-size:14px; font-weight:600; color:#a5b4fc; margin:8px 0; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Thank You!</h1>
    <?php if ($order_no): ?>
        <p>Your order has been placed.</p>
        <div class="order-no">Order No: <?php echo htmlspecialchars($order_no); ?></div>
    <?php else: ?>
        <p>Your order has been placed successfully.</p>
    <?php endif; ?>
    <p>You will receive an email confirmation once the order is processed.</p>
    <p style="margin-top:16px;">
        <a href="index.php">← Back to Store</a>
    </p>
</div>
</body>
</html>
