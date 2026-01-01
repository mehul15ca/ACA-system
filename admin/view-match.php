<?php
include "../config.php";
checkLogin();

$role = currentUserRole();
if (!in_array($role, ['admin','superadmin','coach'])) {
    http_response_code(403);
    echo "Access denied.";
    exit;
}

if (!isset($_GET['id'])) {
    die("Match ID missing.");
}
$match_id = intval($_GET['id']);

// Auto-update status for this match batch (same logic as list)
date_default_timezone_set('America/Toronto');
$today = date('Y-m-d');
$updateSql = "
    UPDATE matches
    SET status = CASE
        WHEN match_date = ? THEN 'ongoing'
        WHEN match_date < ? THEN 'completed'
        ELSE 'upcoming'
    END
    WHERE status <> 'cancelled'
      AND id = ?
";
if ($stmtUp = $conn->prepare($updateSql)) {
    $stmtUp->bind_param("ssi", $today, $today, $match_id);
    $stmtUp->execute();
    $stmtUp->close();
}

// Fetch match
$sql = "
    SELECT m.*, g.name AS ground_name
    FROM matches m
    LEFT JOIN grounds g ON m.ground_id = g.id
    WHERE m.id = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $match_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
    die("Match not found.");
}
$match = $res->fetch_assoc();

// Fetch selected players
$playersSql = "
    SELECT mp.*, s.admission_no, s.first_name, s.last_name, b.name AS batch_name
    FROM match_players mp
    JOIN students s ON mp.student_id = s.id
    LEFT JOIN batches b ON s.batch_id = b.id
    WHERE mp.match_id = ?
    ORDER BY mp.is_playing_xi DESC, s.first_name ASC
";
$st = $conn->prepare($playersSql);
$st->bind_param("i", $match_id);
$st->execute();
$playersRes = $st->get_result();

?>
<?php include "includes.header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>View Match</h1>

<div class="table-card">
    <div class="table-header">
        <h2>Match Details</h2>
        <a href="edit-match.php?id=<?php echo $match['id']; ?>" class="button">✏️ Edit</a>
    </div>

    <table class="acatable">
        <tr>
            <th>ID</th>
            <td><?php echo $match['id']; ?></td>
        </tr>
        <tr>
            <th>Opponent</th>
            <td><?php echo htmlspecialchars($match['opponent']); ?></td>
        </tr>
        <tr>
            <th>Date</th>
            <td><?php echo htmlspecialchars($match['match_date']); ?></td>
        </tr>
        <tr>
            <th>Time</th>
            <td><?php echo $match['match_time'] ? date('g:i A', strtotime($match['match_time'])) : '-'; ?></td>
        </tr>
        <tr>
            <th>Ground</th>
            <td><?php echo htmlspecialchars($match['ground_name']); ?></td>
        </tr>
        <tr>
            <th>Venue Text</th>
            <td><?php echo htmlspecialchars($match['venue_text']); ?></td>
        </tr>
        <tr>
            <th>Status</th>
            <td><?php echo htmlspecialchars($match['status']); ?></td>
        </tr>
        <tr>
            <th>Notes</th>
            <td><?php echo nl2br(htmlspecialchars($match['notes'])); ?></td>
        </tr>
    </table>
</div>

<div class="table-card">
    <div class="table-header">
        <h2>Players Selected</h2>
        <a href="manage-players.php?id=<?php echo $match['id']; ?>" class="button">👥 Manage Players</a>
    </div>

    <table class="acatable">
        <thead>
            <tr>
                <th>#</th>
                <th>Admission No</th>
                <th>Name</th>
                <th>Batch</th>
                <th>Role</th>
                <th>Playing XI?</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($playersRes && $playersRes->num_rows > 0): ?>
            <?php $i = 1; ?>
            <?php while ($p = $playersRes->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $i++; ?></td>
                    <td><?php echo htmlspecialchars($p['admission_no']); ?></td>
                    <td><?php echo htmlspecialchars($p['first_name'] . " " . $p['last_name']); ?></td>
                    <td><?php echo htmlspecialchars($p['batch_name']); ?></td>
                    <td><?php echo htmlspecialchars($p['role']); ?></td>
                    <td><?php echo $p['is_playing_xi'] ? 'Yes' : 'No'; ?></td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="6">No players added yet.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<p style="margin-top:12px;">
    <a href="matches.php" class="text-link">⬅ Back to Matches</a>
</p>

<?php include "includes/footer.php"; ?>
