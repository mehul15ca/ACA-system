<?php
include "../config.php";
checkLogin();
$role = currentUserRole();
if (!in_array($role, ['admin','superadmin'])) {
    http_response_code(403);
    echo "Access denied.";
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$message = "";
$success = "";

// Load all active students with their batch
$sqlStu = "
    SELECT s.id, s.first_name, s.last_name, s.admission_no,
           b.name AS batch_name
    FROM students s
    LEFT JOIN batches b ON b.id = s.batch_id
    WHERE s.status = 'active'
    ORDER BY s.first_name, s.last_name
";
$resStu = $conn->query($sqlStu);
$students = [];
if ($resStu) {
    while ($r = $resStu->fetch_assoc()) {
        $students[] = $r;
    }
}

// Load current top players
$current = [];
$resTP = $conn->query("SELECT rank_position, student_id, highlight_text FROM top_players WHERE active=1");
if ($resTP) {
    while ($r = $resTP->fetch_assoc()) {
        $rank = (int)$r['rank_position'];
        $current[$rank] = $r;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // We will wipe existing and then insert new ones
    $conn->query("DELETE FROM top_players");

    // For ranks 1 to 5, read POST
    for ($rank = 1; $rank <= 5; $rank++) {
        $sidField = "student_id_" . $rank;
        $txtField = "highlight_" . $rank;

        $studentId = isset($_POST[$sidField]) ? intval($_POST[$sidField]) : 0;
        $highlight = trim($_POST[$txtField] ?? '');

        if ($studentId > 0) {
            $stmt = $conn->prepare("
                INSERT INTO top_players (rank_position, student_id, highlight_text, active)
                VALUES (?, ?, ?, 1)
            ");
            $stmt->bind_param("iis", $rank, $studentId, $highlight);
            $stmt->execute();
        }
    }
    $success = "Top players updated successfully.";
    // Reload current for display
    $current = [];
    $resTP = $conn->query("SELECT rank_position, student_id, highlight_text FROM top_players WHERE active=1");
    if ($resTP) {
        while ($r = $resTP->fetch_assoc()) {
            $rank = (int)$r['rank_position'];
            $current[$rank] = $r;
        }
    }
}
?>
<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Edit Top Players</h1>
<p style="font-size:13px;color:#9ca3af;margin-bottom:12px;">
    Choose up to 5 players to highlight on the academy homepage.
</p>

<div class="card">
    <?php if ($message): ?><div class="alert-error"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

    <form method="POST">
        <table class="table-basic">
            <thead>
            <tr>
                <th style="width:60px;">Rank</th>
                <th style="width:260px;">Player</th>
                <th>Highlight / Description</th>
            </tr>
            </thead>
            <tbody>
            <?php for ($rank=1; $rank<=5; $rank++): ?>
                <?php
                $currStudentId = isset($current[$rank]) ? (int)$current[$rank]['student_id'] : 0;
                $currHighlight = isset($current[$rank]) ? $current[$rank]['highlight_text'] : "";
                ?>
                <tr>
                    <td>#<?php echo $rank; ?></td>
                    <td>
                        <select name="student_id_<?php echo $rank; ?>">
                            <option value="0">-- None --</option>
                            <?php foreach ($students as $s): ?>
                                <?php
                                $label = $s['first_name']." ".$s['last_name'];
                                if (!empty($s['batch_name'])) {
                                    $label .= " (".$s['batch_name'].")";
                                }
                                ?>
                                <option value="<?php echo (int)$s['id']; ?>"
                                    <?php echo $currStudentId === (int)$s['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td>
                        <input type="text"
                               name="highlight_<?php echo $rank; ?>"
                               value="<?php echo htmlspecialchars($currHighlight); ?>"
                               placeholder="e.g. Player of the Month – December">
                    </td>
                </tr>
            <?php endfor; ?>
            </tbody>
        </table>

        <div style="margin-top:12px;">
            <button type="submit" class="button-primary">Save Top Players</button>
            <a href="top-players.php" class="button-secondary" style="margin-left:8px;">Back</a>
        </div>
    </form>
</div>

<?php include "includes/footer.php"; ?>
