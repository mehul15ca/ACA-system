<?php
include "../config.php";
checkLogin();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$userId = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
if ($userId <= 0) die("Missing user.");

$message = "";

// Handle updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update']) && isset($_POST['qty']) && is_array($_POST['qty'])) {
        foreach ($_POST['qty'] as $itemId => $q) {
            $id = intval($itemId);
            $qty = intval($q);
            if ($qty <= 0) {
                $conn->query("DELETE FROM store_cart_items WHERE id={$id} AND user_id={$userId}");
            } else {
                $conn->query("UPDATE store_cart_items SET qty={$qty} WHERE id={$id} AND user_id={$userId}");
            }
        }
        $message = "Cart updated.";
    } elseif (isset($_POST['clear'])) {
        $conn->query("DELETE FROM store_cart_items WHERE user_id={$userId}");
        $message = "Cart cleared.";
    }
}

// Load cart
$sql = "
    SELECT ci.*, p.name, p.base_price, p.main_image_drive_id
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
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>My Cart - ACA Store</title>
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
        a { color:#a5b4fc; text-decoration:none; }
        .message {
            margin-bottom:10px;
            padding:8px 10px;
            border-radius:8px;
            font-size:13px;
        }
        .message.ok { background:#022c22; color:#bbf7d0; }
        table { width:100%; border-collapse:collapse; font-size:13px; }
        th, td { padding:6px 8px; border-bottom:1px solid #1f2937; }
        th { text-align:left; font-size:11px; text-transform:uppercase; color:#9ca3af; }
        input[type=number] {
            background:#020617;
            border-radius:999px;
            border:1px solid #1f2937;
            color:#e5e7eb;
            padding:2px 6px;
            width:60px;
        }
        .actions {
            margin-top:10px;
            display:flex;
            justify-content:space-between;
            flex-wrap:wrap;
            gap:8px;
        }
        button {
            border:none;
            border-radius:999px;
            padding:6px 10px;
            font-size:12px;
            cursor:pointer;
        }
        .btn-primary { background:#22c55e; color:#021014; }
        .btn-secondary { background:#0f172a; color:#e5e7eb; border:1px solid #1f2937; }
        .summary {
            margin-top:10px;
            font-size:13px;
            text-align:right;
        }
    </style>
</head>
<body>
<div class="wrap">
    <h1>My Cart</h1>
    <p style="font-size:12px;color:#9ca3af;margin-bottom:10px;">
        <a href="index.php">← Back to Store</a>
    </p>

    <?php if ($message): ?>
        <div class="message ok"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <?php if (count($items) === 0): ?>
        <p style="font-size:13px;color:#9ca3af;">Your cart is empty.</p>
    <?php else: ?>
        <form method="POST">
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
                        <td>
                            <input type="number" name="qty[<?php echo $item['id']; ?>]" value="<?php echo (int)$item['qty']; ?>" min="0">
                        </td>
                        <td>$<?php echo number_format($item['total'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <div class="summary">
                Subtotal: <strong>$<?php echo number_format($subtotal, 2); ?> CAD</strong>
            </div>

            <div class="actions">
                <div>
                    <button type="submit" name="update" value="1" class="btn-secondary">Update Cart</button>
                    <button type="submit" name="clear" value="1" class="btn-secondary" onclick="return confirm('Clear cart?');">Clear Cart</button>
                </div>
                <div>
                    <a href="checkout.php" class="btn-primary" style="display:inline-block;padding:6px 12px;">Proceed to Checkout</a>
                </div>
            </div>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
