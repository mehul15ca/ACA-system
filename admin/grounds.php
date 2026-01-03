<?php
require_once __DIR__ . '/_bootstrap.php';
AdminGuard::requirePermission(Permissions::GROUNDS_MANAGE);

$result = $conn->query("SELECT * FROM grounds ORDER BY created_at DESC");
?>

<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Grounds / Locations</h1>

<div class="table-card">
<div class="table-header">
<h2>All Grounds</h2>
<a href="add-ground.php" class="button">➕ Add Ground</a>
</div>

<table class="acatable">
<thead>
<tr>
<th>ID</th><th>Name</th><th>Code</th><th>City</th>
<th>Indoor</th><th>Status</th><th>Actions</th>
</tr>
</thead>
<tbody>
<?php while ($g = $result->fetch_assoc()): ?>
<tr>
<td><?php echo (int)$g['id']; ?></td>
<td><?php echo htmlspecialchars($g['name']); ?></td>
<td><?php echo htmlspecialchars($g['code']); ?></td>
<td><?php echo htmlspecialchars($g['city']); ?></td>
<td><?php echo $g['indoor'] ? '<span class="badge green">Indoor</span>' : 'Outdoor'; ?></td>
<td><?php echo htmlspecialchars($g['status']); ?></td>
<td>
<a href="edit-ground.php?id=<?php echo $g['id']; ?>" class="text-link">Edit</a>

<?php if ($g['status']==='active'): ?>
 | <form method="POST" action="delete-ground.php" style="display:inline">
    <?php echo Csrf::field(); ?>
    <input type="hidden" name="id" value="<?php echo (int)$g['id']; ?>">
    <button class="text-link" onclick="return confirm('Disable ground?')">Disable</button>
   </form>
<?php endif; ?>
</td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
</div>

<?php include "includes/footer.php"; ?>
