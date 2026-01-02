<?php
require_once __DIR__ . '/_bootstrap.php';

AdminGuard::requirePermission(Permissions::MATCHES_MANAGE);

$message = '';

// Load active grounds once
$grounds = $conn->query(
    "SELECT id, name FROM grounds WHERE status='active' ORDER BY name ASC"
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $opponent   = trim($_POST['opponent'] ?? '');
    $match_date = $_POST['match_date'] ?? '';
    $match_time = $_POST['match_time'] ?: null;
    $ground_id  = ($_POST['ground_id'] ?? '') !== '' ? (int)$_POST['ground_id'] : null;
    $venue_text = trim($_POST['venue_text'] ?? '');
    $status     = $_POST['status'] ?? 'upcoming';
    $notes      = trim($_POST['notes'] ?? '');

    if ($opponent === '' || $match_date === '') {
        $message = 'Opponent and date are required.';
    } elseif (!in_array($status, ['upcoming','ongoing','completed','cancelled'], true)) {
        $message = 'Invalid status.';
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO matches
             (opponent, match_date, match_time, ground_id, venue_text, status, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param(
            'sssisss',
            $opponent,
            $match_date,
            $match_time,
            $ground_id,
            $venue_text,
            $status,
            $notes
        );

        if ($stmt->execute()) {
            header('Location: matches.php');
            exit;
        }
        $message = 'Database error.';
        $stmt->close();
    }
}
?>

<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Add Match</h1>

<div class="form-card">
    <?php if ($message): ?>
        <div class="alert-error"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <form method="POST">
        <?= Csrf::field(); ?>

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
                <?php if ($grounds): while ($g = $grounds->fetch_assoc()): ?>
                    <option value="<?php echo (int)$g['id']; ?>">
                        <?php echo htmlspecialchars($g['name']); ?>
                    </option>
                <?php endwhile; endif; ?>
            </select>
        </div>

        <div class="form-row">
            <label>Venue Text (custom)</label>
            <input type="text" name="venue_text"
                   placeholder="e.g. Maple Leaf Cricket Club, Brampton">
        </div>

        <div class="form-row">
            <label>Status</label>
            <select name="status">
                <option value="upcoming">upcoming</option>
                <option value="ongoing">ongoing</option>
                <option value="completed">completed</option>
                <option value="cancelled">cancelled</option>
            </select>
        </div>

        <div class="form-row">
            <label>Notes</label>
            <textarea name="notes" rows="4"></textarea>
        </div>

        <button class="button-primary">Save Match</button>
    </form>
</div>

<p><a href="matches.php" class="text-link">⬅ Back to Matches</a></p>

<?php include "includes/footer.php"; ?>
