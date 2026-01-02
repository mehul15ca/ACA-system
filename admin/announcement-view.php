<?php
require_once __DIR__ . '/_bootstrap.php';

AdminGuard::requirePermission(Permissions::ANNOUNCEMENTS_MANAGE);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(400); exit('Invalid announcement ID.'); }

$stmt = $conn->prepare(
    "SELECT a.*,
            b.name AS batch_name,
            g.name AS ground_name
     FROM announcements a
     LEFT JOIN batches b ON a.batch_id=b.id
     LEFT JOIN grounds g ON a.ground_id=g.id
     WHERE a.id=?"
);
$stmt->bind_param("i", $id);
$stmt->execute();
$a = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$a) { http_response_code(404); exit('Announcement not found.'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    $newStatus = ($a['status'] === 'active') ? 'archived' : 'active';
    $u = $conn->prepare("UPDATE announcements SET status=? WHERE id=?");
    $u->bind_param("si", $newStatus, $id);
    $u->execute();
    $u->close();
    $a['status'] = $newStatus;
}
?>

<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Announcement</h1>

<div class="form-card">
    <p style="font-size:13px;line-height:1.6;">
        Title: <strong><?php echo htmlspecialchars($a['title']); ?></strong><br>
        Audience: <?php echo htmlspecialchars($a['audience']); ?><br>
        Scope:
        <?php
        if (!empty($a['batch_id']) && !empty($a['batch_name'])) {
            echo 'Batch: ' . htmlspecialchars($a['batch_name']);
        } elseif (!empty($a['ground_id']) && !empty($a['ground_name'])) {
            echo 'Ground: ' . htmlspecialchars($a['ground_name']);
        } else {
            echo 'All';
        }
        ?>
        <br>
        Status: <strong><?php echo htmlspecialchars($a['status']); ?></strong><br>
        Created at: <?php echo htmlspecialchars($a['created_at']); ?>
    </p>

    <div style="margin-top:8px;">
        <form method="POST" style="display:inline;">
            <?php echo Csrf::field(); ?>
            <button type="submit" name="toggle_status" class="button-primary">
                <?php echo ($a['status'] === 'active') ? 'Archive' : 'Mark Active'; ?>
            </button>
        </form>
        <a href="announcement-edit.php?id=<?php echo (int)$a['id']; ?>" class="button">Edit</a>
        <a href="announcements.php" class="button">Back to list</a>
    </div>
</div>

<div class="form-card">
    <h2 style="font-size:15px;margin-bottom:6px;">Message</h2>
    <p style="white-space:pre-wrap;font-size:13px;"><?php echo htmlspecialchars($a['body']); ?></p>
</div>

<?php include "includes/footer.php"; ?>
