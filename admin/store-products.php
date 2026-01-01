<?php
include "../config.php";
checkLogin();
$role = currentUserRole();
if (!in_array($role, ['admin','superadmin'])) {
    http_response_code(403);
    echo "Access denied.";
    exit;
}

$message = "";
$success = "";

// Only superadmin can toggle active
if ($role === 'superadmin' && isset($_GET['toggle']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $conn->query("UPDATE store_products SET active = 1 - active WHERE id = {$id}");
    $success = "Product status updated.";
}

// Load products
$res = $conn->query("SELECT * FROM store_products ORDER BY created_at DESC");
?>
<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Store Products</h1>

<div class="form-card">
    <?php if ($message): ?><div class="alert-error"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

    <?php if ($role === 'superadmin'): ?>
        <p><a href="store-product-edit.php" class="button">➕ Add Product</a></p>
    <?php else: ?>
        <p style="font-size:12px;color:#9ca3af;">Only superadmin can add or edit products.</p>
    <?php endif; ?>
</div>

<div class="table-card">
    <div class="table-header">
        <h2>Products</h2>
    </div>
    <table class="acatable">
        <thead>
            <tr>
                <th>Name</th>
                <th>Price (CAD)</th>
                <th>Active</th>
                <th>Created</th>
                <?php if ($role === 'superadmin'): ?>
                    <th>Actions</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
        <?php if ($res && $res->num_rows > 0): ?>
            <?php while ($p = $res->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($p['name']); ?></td>
                    <td>$<?php echo number_format($p['base_price'], 2); ?></td>
                    <td><?php echo $p['active'] ? 'Yes' : 'No'; ?></td>
                    <td><?php echo htmlspecialchars($p['created_at']); ?></td>
                    <?php if ($role === 'superadmin'): ?>
                        <td>
                            <a href="store-product-edit.php?id=<?php echo $p['id']; ?>" class="text-link">Edit</a> |
                            <a href="store-products.php?toggle=1&id=<?php echo $p['id']; ?>" class="text-link"
                               onclick="return confirm('Toggle active status for this product?');">
                                Toggle Active
                            </a>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="<?php echo $role==='superadmin'?5:4; ?>">No products found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include "includes/footer.php"; ?>
