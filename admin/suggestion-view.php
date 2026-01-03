<?php
require_once __DIR__ . '/_bootstrap.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) die("Invalid suggestion ID.");

$sql = "
    SELECT sg.*,
           st.admission_no,
           st.first_name AS s_first,
           st.last_name  AS s_last
    FROM suggestions sg
    JOIN students st ON sg.student_id = st.id
    WHERE sg.id = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$sg = $stmt->get_result()->fetch_assoc();
if (!$sg) die("Suggestion not found.");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    $newStatus = ($sg['status'] === 'open') ? 'closed' : 'open';
    $stmtU = $conn->prepare("UPDATE suggestions SET status = ? WHERE id = ?");
    $stmtU->bind_param("si", $newStatus, $id);
    $stmtU->execute();
    $sg['status'] = $newStatus;
}
?>
<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Suggestion / Feedback</h1>

<div class="form-card">
    <p style="font-size:13px;color:#6b7280;line-height:1.6;">
        Date: <?php echo htmlspecialchars($sg['date']); ?><br>
        Student: <strong><?php echo htmlspecialchars($sg['s_first'] . " " . $sg['s_last']); ?></strong>
        (<?php echo htmlspecialchars($sg['admission_no']); ?>)<br>
        Status: <strong><?php echo htmlspecialchars($sg['status']); ?></strong><br>
        Created At: <?php echo htmlspecialchars($sg['created_at']); ?>
    </p>

    <div style="margin-top:8px;">
        <form method="POST" style="display:inline;">
        <?php echo Csrf::field(); ?>

            <button type="submit" name="toggle_status" class="button-primary">
                <?php echo $sg['status'] === 'open' ? 'Mark as closed' : 'Reopen'; ?>
            </button>
        </form>
        <a href="suggestion-edit.php?id=<?php echo $sg['id']; ?>" class="button">Edit</a>
        <a href="suggestions.php" class="button">Back to list</a>
    </div>
</div>

<div class="form-card">
    <h2 style="font-size:15px;margin-bottom:6px;">Message</h2>
    <p style="white-space:pre-wrap;font-size:13px;"><?php echo htmlspecialchars($sg['suggestion']); ?></p>
</div>

<div class="form-card">
    <h2 style="font-size:15px;margin-bottom:6px;">Attachment</h2>
    <?php if (!empty($sg['drive_file_id'])): ?>
        <p style="font-size:13px;">
            <a href="https://drive.google.com/file/d/<?php echo urlencode($sg['drive_file_id']); ?>/view"
               target="_blank" class="text-link">Open in Google Drive</a>
        </p>
    <?php else: ?>
        <p style="font-size:13px;color:#9ca3af;">No attachment provided.</p>
    <?php endif; ?>
</div>

<?php include "includes/footer.php"; ?>
