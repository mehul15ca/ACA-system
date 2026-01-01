<?php
include "../config.php";
checkLogin();

$role = currentUserRole();
if (!in_array($role, ['admin','superadmin'])) {
    http_response_code(403);
    echo "Access denied. Admin/Superadmin only.";
    exit;
}

// Auto-update match statuses (upcoming / ongoing / completed) except cancelled
date_default_timezone_set('America/Toronto'); // adjust if needed
$today = date('Y-m-d');

$updateSql = "
    UPDATE matches
    SET status = CASE
        WHEN match_date = ? THEN 'ongoing'
        WHEN match_date < ? THEN 'completed'
        ELSE 'upcoming'
    END
    WHERE status <> 'cancelled'
";
if ($stmtUp = $conn->prepare($updateSql)) {
    $stmtUp->bind_param("ss", $today, $today);
    $stmtUp->execute();
    $stmtUp->close();
}

// Fetch matches
$sql = "
    SELECT m.*,
           g.name AS ground_name
    FROM matches m
    LEFT JOIN grounds g ON m.ground_id = g.id
    ORDER BY m.match_date DESC, m.match_time DESC
";
$result = $conn->query($sql);

?>
<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Matches</h1>

<div class="table-card">
    <div class="table-header">
        <h2>All Matches</h2>
        <a href="add-match.php" class="button">➕ Add Match</a>
    </div>

    <table class="acatable">
        <thead>
            <tr>
                <th>ID</th>
                <th>Date</th>
                <th>Time</th>
                <th>Opponent</th>
                <th>Ground</th>
                <th>Venue</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($result && $result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <?php
                    $dateText = $row['match_date'] ? htmlspecialchars($row['match_date']) : '';
                    $timeText = $row['match_time'] ? date('g:i A', strtotime($row['match_time'])) : '-';
                ?>
                <tr>
                    <td><?php echo $row['id']; ?></td>
                    <td><?php echo $dateText; ?></td>
                    <td><?php echo $timeText; ?></td>
                    <td><?php echo htmlspecialchars($row['opponent']); ?></td>
                    <td><?php echo htmlspecialchars($row['ground_name']); ?></td>
                    <td><?php echo htmlspecialchars($row['venue_text']); ?></td>
                    <td><?php echo htmlspecialchars($row['status']); ?></td>
                    <td>
                        <a class="text-link" href="view-match.php?id=<?php echo $row['id']; ?>">View</a>
                        |
                        <a class="text-link" href="edit-match.php?id=<?php echo $row['id']; ?>">Edit</a>
                        |
                        <a class="text-link" href="manage-players.php?id=<?php echo $row['id']; ?>">Players</a>
                        |
                        <a class="text-link" href="delete-match.php?id=<?php echo $row['id']; ?>">Delete</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="8">No matches yet.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include "includes/footer.php"; ?>
