<?php
include "../config.php";
checkLogin();
$role = currentUserRole();
if (!in_array($role, ['admin','superadmin'])) {
    http_response_code(403);
    echo "Access denied.";
    exit;
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) die("Invalid order id.");

$message = "";
$success = "";

// Superadmin can update status/payment
if ($role === 'superadmin' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $status = $_POST['status'] ?? '';
    $pay_status = $_POST['payment_status'] ?? '';

    $allowed_status = ['pending','processing','in_transit','delivered','cancelled'];
    $allowed_pay = ['pending','paid','failed'];

    if (in_array($status, $allowed_status) && in_array($pay_status, $allowed_pay)) {
        $statusEsc = $conn->real_escape_string($status);
        $payEsc    = $conn->real_escape_string($pay_status);
        $sql = "
            UPDATE store_orders
            SET status='{$statusEsc}',
                payment_status='{$payEsc}'
            WHERE id={$id}
        ";
        if ($conn->query($sql)) {
            $success = "Order updated.";
        } else {
            $message = "Error updating order: ".$conn->error;
        }
    } else {
        $message = "Invalid status selection.";
    }
}

// Load order
$sqlO = "
    SELECT o.*, u.username
    FROM store_orders o
    JOIN users u ON o.user_id = u.id
    WHERE o.id = {$id}
";
$resO = $conn->query($sqlO);
$order = $resO ? $resO->fetch_assoc() : null;
if (!$order) die("Order not found.");

$sqlItems = "
    SELECT oi.*, p.name
    FROM store_order_items oi
    JOIN store_products p ON oi.product_id = p.id
    WHERE oi.order_id = {$id}
";
$resItems = $conn->query($sqlItems);

?>
<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Order Details</h1>

<div class="form-card">
    <?php if ($message): ?><div class="alert-error"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

    <p style="font-size:13px;color:#6b7280;line-height:1.6;">
        <strong>Order No:</strong> <?php echo htmlspecialchars($order['order_no']); ?><br>
        <strong>Date:</strong> <?php echo htmlspecialchars($order['created_at']); ?><br>
        <strong>User:</strong> <?php echo htmlspecialchars($order['username']); ?> (<?php echo htmlspecialchars($order['role']); ?>)<br>
        <strong>Total:</strong> $<?php echo number_format($order['total_amount'], 2); ?> CAD<br>
        <strong>Status:</strong> <?php echo htmlspecialchars($order['status']); ?><br>
        <strong>Payment Status:</strong> <?php echo htmlspecialchars($order['payment_status']); ?><br>
    </p>

    <?php if ($role === 'superadmin'): ?>
        <form method="POST" style="margin-top:8px;">
            <div class="form-grid-2">
                <div class="form-group">
                    <label>Order Status</label>
                    <select name="status">
                        <?php
                        $opts = ['pending','processing','in_transit','delivered','cancelled'];
                        foreach ($opts as $opt):
                        ?>
                            <option value="<?php echo $opt; ?>" <?php if ($order['status']===$opt) echo 'selected'; ?>>
                                <?php echo ucfirst(str_replace('_',' ',$opt)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Payment Status</label>
                    <select name="payment_status">
                        <?php
                        $pOpts = ['pending','paid','failed'];
                        foreach ($pOpts as $opt):
                        ?>
                            <option value="<?php echo $opt; ?>" <?php if ($order['payment_status']===$opt) echo 'selected'; ?>>
                                <?php echo ucfirst($opt); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <button type="submit" class="button-primary">Update Order</button>
        </form>
    <?php else: ?>
        <p style="font-size:12px;color:#9ca3af;margin-top:4px;">
            You have read-only access. Only superadmin can update order status.
        </p>
    <?php endif; ?>

    <p style="margin-top:10px;">
        <a href="store-orders.php" class="button">Back to orders</a>
    </p>
</div>

<div class="table-card">
    <div class="table-header">
        <h2>Items</h2>
    </div>
    <table class="acatable">
        <thead>
            <tr>
                <th>Product</th>
                <th>Size</th>
                <th>Qty</th>
                <th>Unit Price</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($resItems && $resItems->num_rows > 0): ?>
            <?php while ($i = $resItems->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($i['name']); ?></td>
                    <td><?php echo htmlspecialchars($i['size']); ?></td>
                    <td><?php echo (int)$i['qty']; ?></td>
                    <td>$<?php echo number_format($i['unit_price'], 2); ?></td>
                    <td>$<?php echo number_format($i['total_price'], 2); ?></td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="5">No items.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include "includes/footer.php"; ?>
