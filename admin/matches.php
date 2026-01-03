<?php
require_once __DIR__ . '/_bootstrap.php';
AdminGuard::requirePermission(Permissions::MATCHES_MANAGE);

$sql = "
SELECT m.*, g.name AS ground_name
FROM matches m
LEFT JOIN grounds g ON m.ground_id = g.id
ORDER BY m.match_date DESC, m.match_time DESC
";
$result = $conn->query($sql);
?>

<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Matches</h1>

<div class="table-card">
<div class="table-header">
<h2>All Matches</h2>
<a href="add-match.php" class="button">➕ Add Match</a>
</div>

<table class="acatable">
<thead>
<tr>
<th>ID</th><th>Date</th><th>Time</th><th>Opponent</th>
<th>Ground</th><th>Status</th><th>Actions</th>
</tr>
</thead>
<tbody>
<?php while ($m=$result->fetch_assoc()): ?>
<tr>
<td><?php echo (int)$m['id']; ?></td>
<td><?php echo htmlspecialchars($m['match_date']); ?></td>
<td><?php echo $m['match_time'] ? date('g:i A', strtotime($m['match_time'])) : '-'; ?></td>
<td><?php echo htmlspecialchars($m['opponent']); ?></td>
<td><?php echo htmlspecialchars($m['ground_name'] ?? '-'); ?></td>
<td><?php echo htmlspecialchars($m['status']); ?></td>
<td>
<a href="edit-match.php?id=<?php echo $m['id']; ?>" class="text-link">Edit</a> |
<a href="manage-players.php?id=<?php echo $m['id']; ?>" class="text-link">Players</a>

<form method="POST" action="delete-match.php" style="display:inline">
<?php echo Csrf::field(); ?>
<input type="hidden" name="id" value="<?php echo (int)$m['id']; ?>">
<button class="text-link" onclick="return confirm('Delete match?')">Delete</button>
</form>
</td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
</div>

<?php include "includes/footer.php"; ?>
