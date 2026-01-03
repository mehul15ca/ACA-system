<?php
require_once __DIR__ . '/_bootstrap.php';

if (!hasPermission('manage_store')) {
    http_response_code(403);
    echo "Access denied.";
    exit;
}



// List orders
$sql = "
    SELECT o.*, u.username
    FROM store_orders o
    JOIN users u ON o.user_id = u.id
    ORDER BY o.created_at DESC
";
$res = $conn->query($sql);
?>
<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Store Orders</h1>

<div class="table-card">
    <div class="table-header">
        <h2>All Orders</h2>
    </div>
    <table class="acatable">
        <thead>
            <tr>
                <th>Order No</th>
                <th>Date</th>
                <th>User</th>
                <th>Role</th>
                <th>Total</th>
                <th>Status</th>
                <th>Payment</th>
                <th>View</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($res && $res->num_rows > 0): ?>
            <?php while ($o = $res->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($o['order_no']); ?></td>
                    <td><?php echo htmlspecialchars($o['created_at']); ?></td>
                    <td><?php echo htmlspecialchars($o['username']); ?></td>
                    <td><?php echo htmlspecialchars($o['role']); ?></td>
                    <td>$<?php echo number_format($o['total_amount'], 2); ?> CAD</td>
                    <td><?php echo htmlspecialchars($o['status']); ?></td>
                    <td><?php echo htmlspecialchars($o['payment_status']); ?></td>
                    <td>
                        <a href="store-order-view.php?id=<?php echo $o['id']; ?>" class="text-link">View</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="8">No orders yet.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include "includes/footer.php"; ?>
