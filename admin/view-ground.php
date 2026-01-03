<?php
require_once __DIR__ . '/_bootstrap.php';

if (!isset($_GET['id'])) {
    die("Ground ID missing.");
}
$ground_id = intval($_GET['id']);

// Fetch ground
$stmt = $conn->prepare("SELECT * FROM grounds WHERE id = ?");
$stmt->bind_param("i", $ground_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
    die("Ground not found.");
}
$ground = $res->fetch_assoc();

// Fetch batches linked to this ground
$batches = $conn->prepare("SELECT id, name, code FROM batches WHERE ground_id = ?");
$batches->bind_param("i", $ground_id);
$batches->execute();
$batches_res = $batches->get_result();
?>
<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>View Ground</h1>

<div class="table-card">
    <div class="table-header">
        <h2><?php echo htmlspecialchars($ground['name']); ?></h2>
        <a href="edit-ground.php?id=<?php echo $ground['id']; ?>" class="button">✏️ Edit</a>
    </div>

    <table class="acatable">
        <tr>
            <th>ID</th>
            <td><?php echo $ground['id']; ?></td>
        </tr>
        <tr>
            <th>Code</th>
            <td><?php echo htmlspecialchars($ground['code']); ?></td>
        </tr>
        <tr>
            <th>Address</th>
            <td><?php echo htmlspecialchars($ground['address']); ?></td>
        </tr>
        <tr>
            <th>City</th>
            <td><?php echo htmlspecialchars($ground['city']); ?></td>
        </tr>
        <tr>
            <th>Province</th>
            <td><?php echo htmlspecialchars($ground['province']); ?></td>
        </tr>
        <tr>
            <th>Country</th>
            <td><?php echo htmlspecialchars($ground['country']); ?></td>
        </tr>
        <tr>
            <th>Indoor</th>
            <td><?php echo ((int)$ground['indoor'] === 1) ? 'Yes (Indoor)' : 'No (Outdoor)'; ?></td>
        </tr>
        <tr>
            <th>Status</th>
            <td><?php echo htmlspecialchars($ground['status']); ?></td>
        </tr>
        <tr>
            <th>Attendance Login Password</th>
            <td><?php echo htmlspecialchars($ground['password']); ?></td>
        </tr>
        <tr>
            <th>Created At</th>
            <td><?php echo htmlspecialchars($ground['created_at']); ?></td>
        </tr>
    </table>
</div>

<div class="table-card">
    <div class="table-header">
        <h2>Batches using this Ground</h2>
    </div>

    <table class="acatable">
        <thead>
            <tr>
                <th>ID</th>
                <th>Batch Name</th>
                <th>Code</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($batches_res && $batches_res->num_rows > 0): ?>
            <?php while ($b = $batches_res->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $b['id']; ?></td>
                    <td><?php echo htmlspecialchars($b['name']); ?></td>
                    <td><?php echo htmlspecialchars($b['code']); ?></td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="3">No batches linked to this ground yet.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<p style="margin-top:10px;">
    <a class="text-link" href="grounds.php">⬅ Back to Grounds</a>
</p>

<?php include "includes/footer.php"; ?>
