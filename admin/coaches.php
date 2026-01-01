<?php
include "../config.php";
checkLogin();
if (!hasPermission('manage_coaches')) {
    http_response_code(403);
    echo "Access denied.";
    exit;
}


$role = currentUserRole();
if (!in_array($role, ['admin','superadmin'])) {
    http_response_code(403);
    echo "Access denied. Admin/Superadmin only.";
    exit;
}

$sql = "SELECT * FROM coaches ORDER BY created_at DESC";
$result = $conn->query($sql);
?>
<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Coaches</h1>

<div class="table-card">
    <div class="table-header">
        <h2>All Coaches</h2>
        <a href="add-coach.php" class="button">➕ Add Coach</a>
    </div>

    <table class="acatable">
        <thead>
            <tr>
                <th>ID</th>
                <th>Code</th>
                <th>Name</th>
                <th>Phone</th>
                <th>Email</th>
                <th>Specialization</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($result && $result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $row['id']; ?></td>
                    <td><?php echo htmlspecialchars($row['coach_code']); ?></td>
                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                    <td><?php echo htmlspecialchars($row['phone']); ?></td>
                    <td><?php echo htmlspecialchars($row['email']); ?></td>
                    <td><?php echo htmlspecialchars($row['specialization']); ?></td>
                    <td>
                        <span class="badge <?php echo $row['status'] === 'active' ? 'green' : ''; ?>">
                            <?php echo htmlspecialchars($row['status']); ?>
                        </span>
                    </td>
                    <td>
                        <a class="text-link" href="view-coach.php?id=<?php echo $row['id']; ?>">View</a>
                        |
                        <a class="text-link" href="edit-coach.php?id=<?php echo $row['id']; ?>">Edit</a>
                        <?php if ($row['status'] !== 'disabled'): ?>
                            | <a class="text-link" href="delete-coach.php?id=<?php echo $row['id']; ?>">Disable</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="8">No coaches found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include "includes/footer.php"; ?>
