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
$message = "";

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

// Batch filter
$selected_batch_id = isset($_GET['batch_id']) ? intval($_GET['batch_id']) : 0;

// Fetch batches for filter
$batches_res = $conn->query("SELECT id, name, age_group FROM batches WHERE status='active' ORDER BY name ASC");

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_player'])) {
        $student_id = intval($_POST['student_id']);
        $role_text  = trim($_POST['role']);
        $is_xi      = isset($_POST['is_playing_xi']) ? 1 : 0;

        if ($student_id <= 0) {
            $message = "Please select a student.";
        } else {
            // Prevent duplicate entry
            $check = $conn->prepare("SELECT id FROM match_players WHERE match_id = ? AND student_id = ?");
            $check->bind_param("ii", $match_id, $student_id);
            $check->execute();
            $dupRes = $check->get_result();
            if ($dupRes->num_rows > 0) {
                $message = "This student is already added to the match.";
            } else {
                $ins = $conn->prepare("
                    INSERT INTO match_players (match_id, student_id, role, is_playing_xi)
                    VALUES (?, ?, ?, ?)
                ");
                $ins->bind_param("iisi", $match_id, $student_id, $role_text, $is_xi);
                if ($ins->execute()) {
                    $message = "Player added to match.";
                } else {
                    $message = "Error adding player: " . $conn->error;
                }
            }
        }
    }

    if (isset($_POST['update_player'])) {
        $mp_id    = intval($_POST['mp_id']);
        $role_txt = trim($_POST['role']);
        $is_xi    = isset($_POST['is_playing_xi']) ? 1 : 0;

        $up = $conn->prepare("
            UPDATE match_players
            SET role = ?, is_playing_xi = ?
            WHERE id = ? AND match_id = ?
        ");
        $up->bind_param("siii", $role_txt, $is_xi, $mp_id, $match_id);
        if ($up->execute()) {
            $message = "Player updated.";
        } else {
            $message = "Error updating player: " . $conn->error;
        }
    }
}

// Fetch students for dropdown (active only, optional batch filter)
$students_sql = "
    SELECT s.id, s.admission_no, s.first_name, s.last_name, b.name AS batch_name
    FROM students s
    LEFT JOIN batches b ON s.batch_id = b.id
    WHERE s.status = 'active'
";
if ($selected_batch_id > 0) {
    $students_sql .= " AND s.batch_id = " . intval($selected_batch_id);
}
$students_sql .= " ORDER BY b.name ASC, s.first_name ASC";
$students_res = $conn->query($students_sql);

// Fetch current match players
$playersSql = "
    SELECT mp.*, s.admission_no, s.first_name, s.last_name, b.name AS batch_name
    FROM match_players mp
    JOIN students s ON mp.student_id = s.id
    LEFT JOIN batches b ON s.batch_id = b.id
    WHERE mp.match_id = ?
    ORDER BY mp.is_playing_xi DESC, s.first_name ASC
";
$ps = $conn->prepare($playersSql);
$ps->bind_param("i", $match_id);
$ps->execute();
$playersRes = $ps->get_result();

?>
<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Manage Players – Match #<?php echo $match['id']; ?></h1>

<div class="table-card">
    <div class="table-header">
        <h2>Match Summary</h2>
        <a href="view-match.php?id=<?php echo $match['id']; ?>" class="button">ℹ View Match</a>
    </div>

    <table class="acatable">
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
            <th>Venue</th>
            <td><?php echo htmlspecialchars($match['venue_text']); ?></td>
        </tr>
        <tr>
            <th>Status</th>
            <td><?php echo htmlspecialchars($match['status']); ?></td>
        </tr>
    </table>
</div>

<div class="form-card">
    <?php if ($message): ?>
        <p style="color:red; margin-bottom:10px;"><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>

    <h2 style="margin-top:0;">Add Player to Match</h2>

    <form method="GET" style="margin-bottom:10px;">
        <input type="hidden" name="id" value="<?php echo $match_id; ?>">
        <div class="form-row">
            <label>Filter by Batch</label>
            <select name="batch_id" onchange="this.form.submit()">
                <option value="0">-- All Batches --</option>
                <?php if ($batches_res): ?>
                    <?php while ($b = $batches_res->fetch_assoc()): ?>
                        <option value="<?php echo $b['id']; ?>" <?php if ($selected_batch_id == $b['id']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($b['name'] . " (" . $b['age_group'] . ")"); ?>
                        </option>
                    <?php endwhile; ?>
                <?php endif; ?>
            </select>
        </div>
    </form>

    <form method="POST">
        <input type="hidden" name="add_player" value="1">

        <div class="form-row">
            <label>Student</label>
            <select name="student_id" required>
                <option value="">-- Select Student --</option>
                <?php if ($students_res): ?>
                    <?php while ($s = $students_res->fetch_assoc()): ?>
                        <option value="<?php echo $s['id']; ?>">
                            <?php echo htmlspecialchars($s['admission_no'] . " - " . $s['first_name'] . " " . $s['last_name'] . " (" . $s['batch_name'] . ")"); ?>
                        </option>
                    <?php endwhile; ?>
                <?php endif; ?>
            </select>
        </div>

        <div class="form-row">
            <label>Role (free text)</label>
            <input type="text" name="role" placeholder="e.g. Opening Batsman, Fast Bowler, WK" required>
        </div>

        <div class="form-row">
            <label>
                <input type="checkbox" name="is_playing_xi" checked>
                Part of Playing XI
            </label>
        </div>

        <button type="submit" class="button-primary">Add Player</button>
    </form>
</div>

<div class="table-card">
    <div class="table-header">
        <h2>Current Players</h2>
    </div>

    <table class="acatable">
        <thead>
            <tr>
                <th>#</th>
                <th>Admission No</th>
                <th>Name</th>
                <th>Batch</th>
                <th>Role</th>
                <th>Playing XI</th>
                <th>Actions</th>
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
                    <td>
                        <form method="POST" style="display:inline-block;">
                            <input type="hidden" name="update_player" value="1">
                            <input type="hidden" name="mp_id" value="<?php echo $p['id']; ?>">
                            <input type="text" name="role" value="<?php echo htmlspecialchars($p['role']); ?>" style="width:140px;">
                    </td>
                    <td>
                            <input type="checkbox" name="is_playing_xi" <?php if ($p['is_playing_xi']) echo 'checked'; ?>>
                    </td>
                    <td>
                            <button type="submit" class="button-small">Save</button>
                            <a class="text-link" href="delete-match-player.php?id=<?php echo $p['id']; ?>&match_id=<?php echo $match_id; ?>">Remove</a>
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="7">No players added yet.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<p style="margin-top:12px;">
    <a href="matches.php" class="text-link">⬅ Back to Matches</a>
</p>

<?php include "includes/footer.php"; ?>
