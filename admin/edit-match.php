<?php
include "../config.php";
checkLogin();

$role = currentUserRole();
if (!in_array($role, ['admin','superadmin'])) {
    http_response_code(403);
    echo "Access denied. Admin/Superadmin only.";
    exit;
}

if (!isset($_GET['id'])) {
    die("Match ID missing.");
}
$match_id = intval($_GET['id']);
$message = "";

// Fetch match
$stmt = $conn->prepare("SELECT * FROM matches WHERE id = ?");
$stmt->bind_param("i", $match_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
    die("Match not found.");
}
$match = $res->fetch_assoc();

// Fetch grounds
$grounds_res = $conn->query("SELECT id, name FROM grounds WHERE status = 'active' ORDER BY name ASC");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $opponent   = trim($_POST['opponent']);
    $match_date = $_POST['match_date'];
    $match_time = $_POST['match_time'] !== '' ? $_POST['match_time'] : null;
    $ground_id  = isset($_POST['ground_id']) && $_POST['ground_id'] !== '' ? intval($_POST['ground_id']) : null;
    $venue_text = trim($_POST['venue_text']);
    $status     = $_POST['status'];
    $notes      = trim($_POST['notes']);

    if ($opponent === '' || $match_date === '') {
        $message = "Opponent and date are required.";
    } else {
        $sql = "
            UPDATE matches
            SET opponent = ?, match_date = ?, match_time = ?, ground_id = ?, venue_text = ?, status = ?, notes = ?
            WHERE id = ?
        ";
        $stmtUp = $conn->prepare($sql);
        $stmtUp->bind_param(
            "sssisssi",
            $opponent,
            $match_date,
            $match_time,
            $ground_id,
            $venue_text,
            $status,
            $notes,
            $match_id
        );

        if ($stmtUp->execute()) {
            $message = "Match updated successfully.";
            $match['opponent']   = $opponent;
            $match['match_date'] = $match_date;
            $match['match_time'] = $match_time;
            $match['ground_id']  = $ground_id;
            $match['venue_text'] = $venue_text;
            $match['status']     = $status;
            $match['notes']      = $notes;
        } else {
            $message = "Error updating match: " . $conn->error;
        }
    }
}
?>
<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Edit Match</h1>

<div class="form-card">
    <?php if ($message): ?>
        <p style="color:red; margin-bottom:10px;"><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>

    <form method="POST">
        <div class="form-row">
            <label>Opponent</label>
            <input type="text" name="opponent" value="<?php echo htmlspecialchars($match['opponent']); ?>" required>
        </div>

        <div class="form-row">
            <label>Match Date</label>
            <input type="date" name="match_date" value="<?php echo htmlspecialchars($match['match_date']); ?>" required>
        </div>

        <div class="form-row">
            <label>Match Time</label>
            <input type="time" name="match_time" value="<?php echo $match['match_time'] ? htmlspecialchars(substr($match['match_time'],0,5)) : ''; ?>">
        </div>

        <div class="form-row">
            <label>Academy Ground (optional)</label>
            <select name="ground_id">
                <option value="">-- Select Ground --</option>
                <?php if ($grounds_res): ?>
                    <?php while ($g = $grounds_res->fetch_assoc()): ?>
                        <option value="<?php echo $g['id']; ?>" <?php if ($match['ground_id'] == $g['id']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($g['name']); ?>
                        </option>
                    <?php endwhile; ?>
                <?php endif; ?>
            </select>
        </div>

        <div class="form-row">
            <label>Venue Text (custom)</label>
            <input type="text" name="venue_text" value="<?php echo htmlspecialchars($match['venue_text']); ?>">
        </div>

        <div class="form-row">
            <label>Status</label>
            <select name="status">
                <option value="upcoming"  <?php if ($match['status'] === 'upcoming')  echo 'selected'; ?>>upcoming</option>
                <option value="ongoing"   <?php if ($match['status'] === 'ongoing')   echo 'selected'; ?>>ongoing</option>
                <option value="completed" <?php if ($match['status'] === 'completed') echo 'selected'; ?>>completed</option>
                <option value="cancelled" <?php if ($match['status'] === 'cancelled') echo 'selected'; ?>>cancelled</option>
            </select>
        </div>

        <div class="form-row">
            <label>Notes</label>
            <textarea name="notes" rows="4"><?php echo htmlspecialchars($match['notes']); ?></textarea>
        </div>

        <button type="submit" class="button-primary">Save Changes</button>
    </form>
</div>

<p style="margin-top:12px;">
    <a href="matches.php" class="text-link">⬅ Back to Matches</a>
</p>

<?php include "includes/footer.php"; ?>
