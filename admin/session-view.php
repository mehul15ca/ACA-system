<?php
include "../config.php";
checkLogin();

$role = currentUserRole();
if (!in_array($role, ['admin','superadmin','coach'])) {
    http_response_code(403);
    echo "Access denied.";
    exit;
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) die("Invalid session ID.");

$sql = "
    SELECT ts.*,
           b.name AS batch_name,
           c.name AS coach_name,
           g.name AS ground_name
    FROM training_sessions ts
    JOIN batches b ON ts.batch_id = b.id
    LEFT JOIN coaches c ON ts.coach_id = c.id
    LEFT JOIN grounds g ON ts.ground_id = g.id
    WHERE ts.id = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$s = $stmt->get_result()->fetch_assoc();
if (!$s) die("Session not found.");

// If coach, restrict to own sessions
if ($role === 'coach') {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $userId = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
    $stmtU = $conn->prepare("SELECT coach_id FROM users WHERE id = ?");
    $stmtU->bind_param("i", $userId);
    $stmtU->execute();
    $u = $stmtU->get_result()->fetch_assoc();
    $coachId = $u && $u['coach_id'] ? intval($u['coach_id']) : 0;

    if ($coachId <= 0 || $coachId !== intval($s['coach_id'])) {
        http_response_code(403);
        echo "Access denied.";
        exit;
    }
}
?>
<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Training Session</h1>

<div class="form-card">
    <p style="font-size:13px;color:#6b7280;line-height:1.6;">
        Date: <strong><?php echo htmlspecialchars($s['session_date']); ?></strong><br>
        Batch: <?php echo htmlspecialchars($s['batch_name']); ?><br>
        Coach: <?php echo htmlspecialchars($s['coach_name']); ?><br>
        Ground: <?php echo htmlspecialchars($s['ground_name']); ?><br>
        Created at: <?php echo htmlspecialchars($s['created_at']); ?>
    </p>

    <div style="margin-top:8px;">
        <a href="session-edit.php?id=<?php echo $s['id']; ?>" class="button">Edit</a>
        <a href="sessions.php" class="button">Back to list</a>
    </div>
</div>

<div class="form-card">
    <h2 style="font-size:15px;margin-bottom:6px;">Session Notes</h2>
    <p style="white-space:pre-wrap;font-size:13px;"><?php echo htmlspecialchars($s['notes']); ?></p>
</div>

<?php include "includes/footer.php"; ?>
