<?php
include "../config.php";
checkLogin();
if (!hasPermission('manage_batches')) {
    http_response_code(403);
    echo "Access denied.";
    exit;
}


$role = currentUserRole();
if (!in_array($role, ['admin', 'superadmin'])) {
    http_response_code(403);
    echo "Access denied. Admin/Superadmin only.";
    exit;
}

$sql = "SELECT * FROM batches ORDER BY created_at DESC";
$result = $conn->query($sql);
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
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Code</th>
                <th>Age Group</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($result && $result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $row['id']; ?></td>
                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                    <td><?php echo htmlspecialchars($row['code']); ?></td>
                    <td><?php echo htmlspecialchars($row['age_group']); ?></td>
                    <td>
                        <span class="badge <?php echo $row['status'] === 'active' ? 'green' : ''; ?>">
                            <?php echo htmlspecialchars($row['status']); ?>
                        </span>
                    </td>
                    <td>
                        <a class="text-link" href="view-batch.php?id=<?php echo $row['id']; ?>">View</a>
                        |
                        <a class="text-link" href="edit-batch.php?id=<?php echo $row['id']; ?>">Edit</a>
                        <?php if ($row['status'] !== 'disabled'): ?>
                            | <a class="text-link" href="delete-batch.php?id=<?php echo $row['id']; ?>">Disable</a>
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
