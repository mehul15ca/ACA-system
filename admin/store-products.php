<?php
require_once __DIR__ . '/_bootstrap.php';

$role = function_exists('currentUserRole') ? currentUserRole() : ($_SESSION['role'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_active'])) {
    // CSRF validated by _bootstrap.php (Csrf::validateRequest on POST)
    if (!in_array($role, ['superadmin'], true)) {
        http_response_code(403);
        echo "Access denied.";
        exit;
    }

    $id = (int)($_POST['id'] ?? 0);
    $active = (int)($_POST['active'] ?? 0);
    $active = $active === 1 ? 1 : 0;

    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE store_products SET active=? WHERE id=?");
        $stmt->bind_param("ii", $active, $id);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: store-products.php");
    exit;
}

$res = $conn->query("SELECT * FROM store_products ORDER BY created_at DESC");
?>

<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Store Products</h1>

<div class="table-card">
  <div class="table-header">
    <h2>All Products</h2>
    <a href="store-product-add.php" class="button">➕ Add Product</a>
  </div>

  <table class="acatable">
    <thead>
      <tr>
        <th>ID</th>
        <th>Name</th>
        <th>Price</th>
        <th>Active</th>
        <th>Created</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
    <?php if ($res && $res->num_rows): ?>
      <?php while ($p = $res->fetch_assoc()): ?>
        <tr>
          <td><?php echo (int)$p['id']; ?></td>
          <td><?php echo htmlspecialchars($p['name'] ?? ''); ?></td>
          <td><?php echo htmlspecialchars($p['price'] ?? ''); ?></td>
          <td>
            <span class="badge <?php echo ((int)($p['active'] ?? 0) === 1) ? 'green' : ''; ?>">
              <?php echo ((int)($p['active'] ?? 0) === 1) ? 'active' : 'inactive'; ?>
            </span>
          </td>
          <td><?php echo htmlspecialchars($p['created_at'] ?? '-'); ?></td>
          <td>
            <a class="text-link" href="store-product-edit.php?id=<?php echo (int)$p['id']; ?>">Edit</a>
            <?php if (in_array($role, ['superadmin'], true)): ?>
              |
              <form method="POST" style="display:inline-block">
                <?php echo Csrf::field(); ?>
                <input type="hidden" name="toggle_active" value="1">
                <input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>">
                <input type="hidden" name="active" value="<?php echo ((int)($p['active'] ?? 0) === 1) ? 0 : 1; ?>">
                <button type="submit" class="button-small">
                  <?php echo ((int)($p['active'] ?? 0) === 1) ? 'Disable' : 'Enable'; ?>
                </button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endwhile; ?>
    <?php else: ?>
      <tr><td colspan="6">No products found.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<?php include "includes/footer.php"; ?>
