<?php
require_once __DIR__ . '/_bootstrap.php';

AdminGuard::requirePermission(Permissions::ANNOUNCEMENTS_MANAGE);

$filter_audience = trim($_GET['audience'] ?? '');
$filter_status   = trim($_GET['status'] ?? '');

$allowedAudience = ['all','students','coaches'];
$allowedStatus   = ['active','archived'];

$sql = "
    SELECT a.*,
           b.name AS batch_name,
           g.name AS ground_name
    FROM announcements a
    LEFT JOIN batches b ON a.batch_id = b.id
    LEFT JOIN grounds g ON a.ground_id = g.id
    WHERE 1=1
";

$params = [];
$types  = '';

if ($filter_audience !== '' && in_array($filter_audience, $allowedAudience, true)) {
    $sql .= " AND a.audience = ? ";
    $params[] = $filter_audience;
    $types .= 's';
}

if ($filter_status !== '' && in_array($filter_status, $allowedStatus, true)) {
    $sql .= " AND a.status = ? ";
    $params[] = $filter_status;
    $types .= 's';
}

$sql .= " ORDER BY a.created_at DESC, a.id DESC";

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$res = $stmt->get_result();
?>

<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Announcements</h1>

<div class="form-card" style="margin-bottom:16px;">
    <form method="GET" style="display:flex; flex-wrap:wrap; gap:10px; align-items:flex-end;">
        <div>
            <label style="font-size:12px;">Audience</label>
            <select name="audience">
                <option value="">All</option>
                <option value="all"<?php if ($filter_audience==='all') echo ' selected'; ?>>All (students + coaches)</option>
                <option value="students"<?php if ($filter_audience==='students') echo ' selected'; ?>>Students</option>
                <option value="coaches"<?php if ($filter_audience==='coaches') echo ' selected'; ?>>Coaches</option>
            </select>
        </div>

        <div>
            <label style="font-size:12px;">Status</label>
            <select name="status">
                <option value="">All</option>
                <option value="active"<?php if ($filter_status==='active') echo ' selected'; ?>>Active</option>
                <option value="archived"<?php if ($filter_status==='archived') echo ' selected'; ?>>Archived</option>
            </select>
        </div>

        <div>
            <button type="submit" class="button-primary">Filter</button>
        </div>

        <div style="margin-left:auto;">
            <a href="announcement-add.php" class="button">➕ New Announcement</a>
        </div>
    </form>
</div>

<div class="table-card">
    <div class="table-header"><h2>All Announcements</h2></div>

    <table class="acatable">
        <thead>
            <tr>
                <th>Title</th>
                <th>Audience</th>
                <th>Scope</th>
                <th>Status</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($res && $res->num_rows > 0): ?>
            <?php while ($a = $res->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($a['title']); ?></td>
                    <td><?php echo htmlspecialchars($a['audience']); ?></td>
                    <td>
                        <?php
                        if (!empty($a['batch_id']) && !empty($a['batch_name'])) {
                            echo 'Batch: ' . htmlspecialchars($a['batch_name']);
                        } elseif (!empty($a['ground_id']) && !empty($a['ground_name'])) {
                            echo 'Ground: ' . htmlspecialchars($a['ground_name']);
                        } else {
                            echo 'All';
                        }
                        ?>
                    </td>
                    <td><?php echo htmlspecialchars($a['status']); ?></td>
                    <td><?php echo htmlspecialchars($a['created_at']); ?></td>
                    <td>
                        <a class="text-link" href="announcement-view.php?id=<?php echo (int)$a['id']; ?>">View</a> |
                        <a class="text-link" href="announcement-edit.php?id=<?php echo (int)$a['id']; ?>">Edit</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="6">No announcements found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php
$stmt->close();
include "includes/footer.php";
?>
