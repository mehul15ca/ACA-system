<?php
include "../config.php";
checkLogin();

$role = currentUserRole();
if (!in_array($role, ['admin','superadmin'])) {
    http_response_code(403);
    echo "Access denied.";
    exit;
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) die("Invalid announcement ID.");

$sql = "
    SELECT a.*,
           b.name AS batch_name,
           g.name AS ground_name
    FROM announcements a
    LEFT JOIN batches b ON a.batch_id = b.id
    LEFT JOIN grounds g ON a.ground_id = g.id
    WHERE a.id = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$a = $stmt->get_result()->fetch_assoc();
if (!$a) die("Announcement not found.");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    $newStatus = ($a['status'] === 'active') ? 'archived' : 'active';
    $stmtU = $conn->prepare("UPDATE announcements SET status = ? WHERE id = ?");
    $stmtU->bind_param("si", $newStatus, $id);
    $stmtU->execute();
    $a['status'] = $newStatus;
}
?>
<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Announcement</h1>

<div class="form-card">
    <p style="font-size:13px;color:#6b7280;line-height:1.6;">
        Title: <strong><?php echo htmlspecialchars($a['title']); ?></strong><br>
        Audience: <?php echo htmlspecialchars($a['audience']); ?><br>
        Scope:
        <?php
        if (!empty($a['batch_id']) && !empty($a['batch_name'])) {
            echo "Batch: " . htmlspecialchars($a['batch_name']);
        } elseif (!empty($a['ground_id']) && !empty($a['ground_name'])) {
            echo "Ground: " . htmlspecialchars($a['ground_name']);
        } else {
            echo "All";
        }
        ?><br>
        Status: <strong><?php echo htmlspecialchars($a['status']); ?></strong><br>
        Created at: <?php echo htmlspecialchars($a['created_at']); ?>
    </p>

    <div style="margin-top:8px;">
        <form method="POST" style="display:inline;">
            <button type="submit" name="toggle_status" class="button-primary">
                <?php echo $a['status'] === 'active' ? 'Archive' : 'Mark Active'; ?>
            </button>
        </form>
        <a href="announcement-edit.php?id=<?php echo $a['id']; ?>" class="button">Edit</a>
        <a href="announcements.php" class="button">Back to list</a>
    </div>
</div>

<div class="form-card">
    <h2 style="font-size:15px;margin-bottom:6px;">Message</h2>
    <p style="white-space:pre-wrap;font-size:13px;"><?php echo htmlspecialchars($a['body']); ?></p>
</div>

<?php include "includes/footer.php"; ?>
