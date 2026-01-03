<?php
require_once __DIR__ . '/_bootstrap.php';
AdminGuard::requirePermission(Permissions::BATCHES_MANAGE);

$res = $conn->query("SELECT * FROM batches ORDER BY created_at DESC");
?>

<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Batches / Programs</h1>

<div class="table-card">
  <div class="table-header">
    <h2>All Batches</h2>
    <a href="add-batch.php" class="button">➕ Add Batch</a>
  </div>

  <table class="acatable">
    <thead>
      <tr><th>ID</th><th>Name</th><th>Code</th><th>Age</th><th>Status</th><th>Actions</th></tr>
    </thead>
    <tbody>
    <?php if ($res && $res->num_rows): ?>
      <?php while ($b = $res->fetch_assoc()): ?>
      <tr>
        <td><?php echo (int)$b['id']; ?></td>
        <td><?php echo htmlspecialchars($b['name']); ?></td>
        <td><?php echo htmlspecialchars($b['code']); ?></td>
        <td><?php echo htmlspecialchars($b['age_group']); ?></td>
        <td>
          <span class="badge <?php echo $b['status']==='active'?'green':''; ?>">
            <?php echo htmlspecialchars($b['status']); ?>
          </span>
        </td>
        <td>
          <a href="view-batch.php?id=<?php echo (int)$b['id']; ?>" class="text-link">View</a> |
          <a href="edit-batch.php?id=<?php echo (int)$b['id']; ?>" class="text-link">Edit</a>

          <?php if ($b['status'] === 'active'): ?>
            | <form method="POST" action="delete-batch.php" style="display:inline">
                <?php echo Csrf::field(); ?>
                <input type="hidden" name="id" value="<?php echo (int)$b['id']; ?>">
                <button class="text-link" onclick="return confirm('Disable this batch?')">Disable</button>
              </form>
          <?php endif; ?>
        </td>
      </tr>
      <?php endwhile; ?>
    <?php else: ?>
      <tr><td colspan="6">No batches found.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<?php include "includes/footer.php"; ?>
