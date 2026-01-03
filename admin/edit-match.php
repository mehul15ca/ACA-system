<?php
require_once __DIR__ . '/_bootstrap.php';

AdminGuard::requirePermission(Permissions::MATCHES_MANAGE);

$match_id = (int)($_GET['id'] ?? 0);
if ($match_id <= 0) {
    http_response_code(400);
    echo "Invalid match id.";
    exit;
}

$message = "";
$errors = [];

// Load match
$stmt = $conn->prepare("SELECT * FROM matches WHERE id = ?");
$stmt->bind_param("i", $match_id);
$stmt->execute();
$match = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$match) {
    http_response_code(404);
    echo "Match not found.";
    exit;
}

// Grounds dropdown
$grounds_res = $conn->query("SELECT id, name FROM grounds WHERE status='active' ORDER BY name ASC");

$allowed_status = ['upcoming','ongoing','completed','cancelled'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::validateRequest();

    $opponent   = trim($_POST['opponent'] ?? '');
    $match_date = $_POST['match_date'] ?? '';
    $match_time = ($_POST['match_time'] ?? '') !== '' ? $_POST['match_time'] : null;
    $ground_id  = ($_POST['ground_id'] ?? '') !== '' ? (int)$_POST['ground_id'] : null;
    $venue_text = trim($_POST['venue_text'] ?? '');
    $status     = $_POST['status'] ?? 'upcoming';
    $notes      = trim($_POST['notes'] ?? '');

    if ($opponent === '' || $match_date === '') $errors[] = "Opponent and date are required.";
    if (!in_array($status, $allowed_status, true)) $errors[] = "Invalid status.";

    if (!$errors) {
        $up = $conn->prepare("
            UPDATE matches
            SET opponent = ?, match_date = ?, match_time = ?, ground_id = ?, venue_text = ?, status = ?, notes = ?
            WHERE id = ?
        ");
        $up->bind_param("sssisssi", $opponent, $match_date, $match_time, $ground_id, $venue_text, $status, $notes, $match_id);

        if ($up->execute()) {
            $message = "Match updated successfully.";
            $match['opponent']=$opponent; $match['match_date']=$match_date; $match['match_time']=$match_time;
            $match['ground_id']=$ground_id; $match['venue_text']=$venue_text; $match['status']=$status; $match['notes']=$notes;
        } else {
            $errors[] = "Database error: " . htmlspecialchars($conn->error);
        }
        $up->close();
    }
}
?>

<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Edit Match</h1>

<div class="form-card">
    <?php if ($message): ?><div class="alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php foreach ($errors as $e): ?><div class="alert-error"><?php echo htmlspecialchars($e); ?></div><?php endforeach; ?>

    <form method="POST">
        <?php echo Csrf::field(); ?>

        <div class="form-row">
            <label>Opponent</label>
            <input type="text" name="opponent" value="<?php echo htmlspecialchars($match['opponent'] ?? ''); ?>" required>
        </div>

        <div class="form-row">
            <label>Match Date</label>
            <input type="date" name="match_date" value="<?php echo htmlspecialchars($match['match_date'] ?? ''); ?>" required>
        </div>

        <div class="form-row">
            <label>Match Time</label>
            <input type="time" name="match_time" value="<?php echo !empty($match['match_time']) ? htmlspecialchars(substr($match['match_time'],0,5)) : ''; ?>">
        </div>

        <div class="form-row">
            <label>Academy Ground (optional)</label>
            <select name="ground_id">
                <option value="">-- Select Ground --</option>
                <?php if ($grounds_res): while($g = $grounds_res->fetch_assoc()): ?>
                    <option value="<?php echo (int)$g['id']; ?>" <?php echo ((int)($match['ground_id'] ?? 0) === (int)$g['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($g['name']); ?>
                    </option>
                <?php endwhile; endif; ?>
            </select>
        </div>

        <div class="form-row">
            <label>Venue Text (custom)</label>
            <input type="text" name="venue_text" value="<?php echo htmlspecialchars($match['venue_text'] ?? ''); ?>">
        </div>

        <div class="form-row">
            <label>Status</label>
            <select name="status">
                <option value="upcoming"  <?php if (($match['status'] ?? '') === 'upcoming') echo 'selected'; ?>>upcoming</option>
                <option value="ongoing"   <?php if (($match['status'] ?? '') === 'ongoing') echo 'selected'; ?>>ongoing</option>
                <option value="completed" <?php if (($match['status'] ?? '') === 'completed') echo 'selected'; ?>>completed</option>
                <option value="cancelled" <?php if (($match['status'] ?? '') === 'cancelled') echo 'selected'; ?>>cancelled</option>
            </select>
        </div>

        <div class="form-row">
            <label>Notes</label>
            <textarea name="notes" rows="4"><?php echo htmlspecialchars($match['notes'] ?? ''); ?></textarea>
        </div>

        <button type="submit" class="button-primary">Save Changes</button>
        <a href="matches.php" class="button">Back</a>
    </form>
</div>

<?php include "includes/footer.php"; ?>
