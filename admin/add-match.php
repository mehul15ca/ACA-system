<?php
require_once __DIR__ . '/_bootstrap.php';

AdminGuard::requirePermission(Permissions::MATCHES_MANAGE);

$message = '';
$errors = [];

// Grounds dropdown
$grounds_res = $conn->query("SELECT id, name FROM grounds WHERE status='active' ORDER BY name ASC");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::validateRequest();

    $opponent   = trim($_POST['opponent'] ?? '');
    $match_date = $_POST['match_date'] ?? '';
    $match_time = ($_POST['match_time'] ?? '') !== '' ? $_POST['match_time'] : null;
    $ground_id  = ($_POST['ground_id'] ?? '') !== '' ? (int)$_POST['ground_id'] : null;
    $venue_text = trim($_POST['venue_text'] ?? '');
    $status     = $_POST['status'] ?? 'upcoming';
    $notes      = trim($_POST['notes'] ?? '');

    $allowed_status = ['upcoming','ongoing','completed','cancelled'];

    if ($opponent === '' || $match_date === '') $errors[] = 'Opponent and date are required.';
    if (!in_array($status, $allowed_status, true)) $errors[] = 'Invalid status.';

    if (!$errors) {
        $stmt = $conn->prepare("
            INSERT INTO matches (opponent, match_date, match_time, ground_id, venue_text, status, notes)
            VALUES (?,?,?,?,?,?,?)
        ");
        $stmt->bind_param("sssisss", $opponent, $match_date, $match_time, $ground_id, $venue_text, $status, $notes);

        if ($stmt->execute()) {
            header("Location: matches.php?created=1");
            exit;
        }
        $errors[] = 'Database error: ' . htmlspecialchars($conn->error);
        $stmt->close();
    }
}
?>

<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Add Match</h1>

<div class="form-card">
    <?php foreach ($errors as $e): ?>
        <div class="alert-error"><?php echo htmlspecialchars($e); ?></div>
    <?php endforeach; ?>

    <form method="POST">
        <?php echo Csrf::field(); ?>

        <div class="form-row">
            <label>Opponent</label>
            <input type="text" name="opponent" required value="<?php echo htmlspecialchars($_POST['opponent'] ?? ''); ?>">
        </div>

        <div class="form-row">
            <label>Match Date</label>
            <input type="date" name="match_date" required value="<?php echo htmlspecialchars($_POST['match_date'] ?? ''); ?>">
        </div>

        <div class="form-row">
            <label>Match Time</label>
            <input type="time" name="match_time" value="<?php echo htmlspecialchars($_POST['match_time'] ?? ''); ?>">
        </div>

        <div class="form-row">
            <label>Academy Ground (optional)</label>
            <select name="ground_id">
                <option value="">-- Select Ground --</option>
                <?php if ($grounds_res): while($g = $grounds_res->fetch_assoc()): ?>
                    <option value="<?php echo (int)$g['id']; ?>" <?php echo (string)($g['id']) === (string)($_POST['ground_id'] ?? '') ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($g['name']); ?>
                    </option>
                <?php endwhile; endif; ?>
            </select>
        </div>

        <div class="form-row">
            <label>Venue Text (custom)</label>
            <input type="text" name="venue_text" value="<?php echo htmlspecialchars($_POST['venue_text'] ?? ''); ?>">
        </div>

        <div class="form-row">
            <label>Status</label>
            <?php $st = $_POST['status'] ?? 'upcoming'; ?>
            <select name="status">
                <option value="upcoming"  <?php echo $st==='upcoming'?'selected':''; ?>>upcoming</option>
                <option value="ongoing"   <?php echo $st==='ongoing'?'selected':''; ?>>ongoing</option>
                <option value="completed" <?php echo $st==='completed'?'selected':''; ?>>completed</option>
                <option value="cancelled" <?php echo $st==='cancelled'?'selected':''; ?>>cancelled</option>
            </select>
        </div>

        <div class="form-row">
            <label>Notes</label>
            <textarea name="notes" rows="4"><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
        </div>

        <button type="submit" class="button-primary">Save</button>
        <a href="matches.php" class="button">Back</a>
    </form>
</div>

<?php include "includes/footer.php"; ?>
