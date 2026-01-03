<?php
require_once __DIR__ . '/_bootstrap.php';
AdminGuard::requirePermission(Permissions::COACHES_MANAGE);

$res = $conn->query("SELECT * FROM coaches ORDER BY created_at DESC");
?>

<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Coaches</h1>

<div class="table-card">
<table class="acatable">
<thead>
<tr><th>ID</th><th>Code</th><th>Name</th><th>Email</th><th>Status</th><th>Actions</th></tr>
</thead>
<tbody>
<?php while ($c=$res->fetch_assoc()): ?>
<tr>
<td><?php echo (int)$c['id']; ?></td>
<td><?php echo htmlspecialchars($c['coach_code']); ?></td>
<td><?php echo htmlspecialchars($c['name']); ?></td>
<td><?php echo htmlspecialchars($c['email']); ?></td>
<td><?php echo htmlspecialchars($c['status']); ?></td>
<td>
<a href="edit-coach.php?id=<?php echo (int)$c['id']; ?>" class="text-link">Edit</a>

<?php if ($c['status']==='active'): ?>
 | <form method="POST" action="delete-coach.php" style="display:inline">
    <?php echo Csrf::field(); ?>
    <input type="hidden" name="id" value="<?php echo (int)$c['id']; ?>">
    <button class="text-link" onclick="return confirm('Disable coach?')">Disable</button>
   </form>
<?php endif; ?>
</td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
</div>

<?php include "includes/footer.php"; ?>
