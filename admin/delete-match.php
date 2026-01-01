<?php
include "../config.php";
checkLogin();

$role = currentUserRole();
if (!in_array($role, ['admin','superadmin'])) {
    http_response_code(403);
    echo "Access denied.";
    exit;
}

if (!isset($_GET['id'])) {
    die("Match ID missing.");
}
$match_id = intval($_GET['id']);

// Fetch match info
$stmt = $conn->prepare("SELECT * FROM matches WHERE id = ?");
$stmt->bind_param("i", $match_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
    die("Match not found.");
}
$match = $res->fetch_assoc();
$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // First delete players for this match
    $conn->query("DELETE FROM match_players WHERE match_id = " . intval($match_id));
    // Then delete match
    $del = $conn->prepare("DELETE FROM matches WHERE id = ?");
    $del->bind_param("i", $match_id);
    if ($del->execute()) {
        header("Location: matches.php");
        exit;
    } else {
        $message = "Error deleting match: " . $conn->error;
    }
}
?>
<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Delete Match</h1>

<div class="form-card">
    <p>You are about to <strong>delete</strong> this match:</p>
    <p style="margin:8px 0;">
        ID: <strong><?php echo $match['id']; ?></strong><br>
        Opponent: <strong><?php echo htmlspecialchars($match['opponent']); ?></strong><br>
        Date: <strong><?php echo htmlspecialchars($match['match_date']); ?></strong><br>
        Time: <strong><?php echo $match['match_time'] ? date('g:i A', strtotime($match['match_time'])) : '-'; ?></strong>
    </p>

    <?php if ($message): ?>
        <p style="color:red; margin:8px 0;"><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>

    <p style="font-size:13px; margin:8px 0;">
        This will also remove all players linked to this match. This action cannot be undone.
    </p>

    <form method="POST">
        <button type="submit" class="button-primary">Confirm Delete</button>
    </form>
</div>

<p style="margin-top:12px;">
    <a href="matches.php" class="text-link">⬅ Back to Matches</a>
</p>

<?php include "includes/footer.php"; ?>
