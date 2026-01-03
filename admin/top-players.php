<?php
require_once __DIR__ . '/_bootstrap.php';

// Load current top players with student + batch
$sql = "
    SELECT tp.rank_position, tp.highlight_text,
           s.id AS student_id, s.first_name, s.last_name, s.admission_no,
           b.name AS batch_name
    FROM top_players tp
    JOIN students s ON s.id = tp.student_id
    LEFT JOIN batches b ON b.id = s.batch_id
    WHERE tp.active = 1
    ORDER BY tp.rank_position ASC
";
$res = $conn->query($sql);
$topPlayers = [];
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $topPlayers[] = $row;
    }
}
?>
<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Top Players</h1>
<p style="font-size:13px;color:#9ca3af;margin-bottom:12px;">
    Highlight up to 5 players on your academy homepage.
</p>

<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
        <h2 style="font-size:16px;margin:0;">Current Top Players</h2>
        <a href="top-players-edit.php" class="button-primary">Edit Top Players</a>
    </div>

    <?php if (!$topPlayers): ?>
        <p style="font-size:13px;color:#9ca3af;">No top players selected yet.</p>
    <?php else: ?>
        <table class="table-basic">
            <thead>
                <tr>
                    <th style="width:60px;">Rank</th>
                    <th>Player</th>
                    <th>Batch</th>
                    <th>Highlight</th>
                    <th style="width:100px;">Profile</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($topPlayers as $tp): ?>
                <tr>
                    <td>#<?php echo (int)$tp['rank_position']; ?></td>
                    <td>
                        <?php echo htmlspecialchars($tp['first_name']." ".$tp['last_name']); ?>
                        <div style="font-size:11px;color:#9ca3af;">
                            ID: <?php echo htmlspecialchars($tp['admission_no']); ?>
                        </div>
                    </td>
                    <td><?php echo htmlspecialchars($tp['batch_name'] ?? ""); ?></td>
                    <td><?php echo htmlspecialchars($tp['highlight_text']); ?></td>
                    <td>
                        <a href="view-student.php?id=<?php echo (int)$tp['student_id']; ?>" class="link-small">View</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php include "includes/footer.php"; ?>
