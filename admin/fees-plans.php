<?php
require_once __DIR__ . '/_bootstrap.php';

$res = $conn->query("SELECT * FROM fees_plans ORDER BY name ASC");
?>
<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Fee Plans</h1>

<div class="form-card" style="margin-bottom:16px;">
    <p style="font-size:13px;color:#9ca3af;">
        Define monthly base plans and optional add-ons here. You can override the amount per invoice if needed.
    </p>
    <a href="fees-plan-add.php" class="button">➕ Add Fee Plan</a>
</div>

<div class="table-card">
    <div class="table-header">
        <h2>All Fee Plans</h2>
    </div>
    <table class="acatable">
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Frequency</th>
                <th>Amount</th>
                <th>Currency</th>
                <th>Description</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($res && $res->num_rows > 0): ?>
            <?php while ($p = $res->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $p['id']; ?></td>
                    <td><?php echo htmlspecialchars($p['name']); ?></td>
                    <td><?php echo htmlspecialchars($p['frequency']); ?></td>
                    <td><?php echo number_format($p['amount'], 2); ?></td>
                    <td><?php echo htmlspecialchars($p['currency']); ?></td>
                    <td><?php echo nl2br(htmlspecialchars($p['description'])); ?></td>
                    <td>
                        <a href="fees-plan-edit.php?id=<?php echo $p['id']; ?>" class="text-link">Edit</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="7">No fee plans defined yet.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include "includes/footer.php"; ?>
