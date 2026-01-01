<?php
include "../config.php";
checkLogin();

$role = currentUserRole();
if (!in_array($role, ['admin','superadmin'])) {
    http_response_code(403);
    echo "Access denied. Admin/Superadmin only.";
    exit;
}

$message = "";

// Fetch academy grounds for dropdown
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
            INSERT INTO matches (opponent, match_date, match_time, ground_id, venue_text, status, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "sssisss",
            $opponent,
            $match_date,
            $match_time,
            $ground_id,
            $venue_text,
            $status,
            $notes
        );

        if ($stmt->execute()) {
            header("Location: matches.php");
            exit;
        } else {
            $message = "Error inserting match: " . $conn->error;
        }
    }
}
?>
<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Add Match</h1>

<div class="form-card">
    <?php if ($message): ?>
        <p style="color:red; margin-bottom:10px;"><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>

    <form method="POST">
        <div class="form-row">
            <label>Opponent</label>
            <input type="text" name="opponent" required>
        </div>

        <div class="form-row">
            <label>Match Date</label>
            <input type="date" name="match_date" required>
        </div>

        <div class="form-row">
            <label>Match Time</label>
            <input type="time" name="match_time">
        </div>

        <div class="form-row">
            <label>Academy Ground (optional)</label>
            <select name="ground_id">
                <option value="">-- Select Ground --</option>
                <?php if ($grounds_res): ?>
                    <?php while ($g = $grounds_res->fetch_assoc()): ?>
                        <option value="<?php echo $g['id']; ?>">
                            <?php echo htmlspecialchars($g['name']); ?>
                        </option>
                    <?php endwhile; ?>
                <?php endif; ?>
            </select>
            <p style="font-size:11px; margin-top:4px;">
                If match is at an academy ground, select it here. You can still type a custom venue below.
            </p>
        </div>

        <div class="form-row">
            <label>Venue Text (custom)</label>
            <input type="text" name="venue_text" placeholder="e.g. Maple Leaf Cricket Club, Brampton">
        </div>

        <div class="form-row">
            <label>Status</label>
            <select name="status">
                <option value="upcoming">upcoming</option>
                <option value="ongoing">ongoing</option>
                <option value="completed">completed</option>
                <option value="cancelled">cancelled</option>
            </select>
            <p style="font-size:11px; margin-top:4px;">
                Status will automatically move between upcoming / ongoing / completed based on date. Cancelled stays as is.
            </p>
        </div>

        <div class="form-row">
            <label>Notes</label>
            <textarea name="notes" rows="4" placeholder="Optional notes (format, overs, special rules, etc.)"></textarea>
        </div>

        <button type="submit" class="button-primary">Save Match</button>
    </form>
</div>

<p style="margin-top:12px;">
    <a href="matches.php" class="text-link">⬅ Back to Matches</a>
</p>

<?php include "includes/footer.php"; ?>
