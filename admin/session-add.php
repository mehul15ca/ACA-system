<?php
require_once __DIR__ . '/../includes/security/csrf.php';
include "../config.php";
checkLogin();

$role = currentUserRole();
if (!in_array($role, ['admin','superadmin','coach'])) {
    http_response_code(403);
    echo "Access denied.";
    exit;
}

$message = "";
$success = "";

$batches_res = $conn->query("SELECT id, name FROM batches WHERE status='active' ORDER BY name ASC");
$coaches_res = $conn->query("SELECT id, name FROM coaches WHERE status='active' ORDER BY name ASC");
$grounds_res = $conn->query("SELECT id, name FROM grounds WHERE status='active' ORDER BY name ASC");

$defaultCoachId = "";
if ($role === 'coach') {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $userId = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
    if ($userId > 0) {
        $stmt = $conn->prepare("SELECT coach_id FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if ($row && $row['coach_id']) {
            $defaultCoachId = intval($row['coach_id']);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::validateRequest();
    $session_date = $_POST['session_date'] !== "" ? $_POST['session_date'] : date('Y-m-d');
    $batch_id     = intval($_POST['batch_id']);
    $coach_id     = intval($_POST['coach_id']);
    $ground_id    = intval($_POST['ground_id']);
    $notes        = trim($_POST['notes']);

    if ($batch_id <= 0) {
        $message = "Batch is required.";
    } elseif ($coach_id <= 0) {
        $message = "Coach is required.";
    } else {
        $sql = "
            INSERT INTO training_sessions (session_date, batch_id, coach_id, ground_id, notes)
            VALUES (?, ?, ?, ?, ?)
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "siiis",
            $session_date,
            $batch_id,
            $coach_id,
            $ground_id,
            $notes
        );
        if ($stmt->execute()) {
            $success = "Training session saved.";
        } else {
            $message = "Database error: " . $conn->error;
        }
    }
}
?>
<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Add Training Session</h1>

<div class="form-card">
    <?php if ($message): ?><div class="alert-error"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

    <form method="POST">
        <?php echo Csrf::field(); ?>

        <div class="form-grid-2">
            <div class="form-group">
                <label>Date</label>
                <input type="date" name="session_date"
                       value="<?php echo htmlspecialchars(date('Y-m-d')); ?>">
            </div>
            <div class="form-group">
                <label>Batch</label>
                <select name="batch_id" required>
                    <option value="">-- Select Batch --</option>
                    <?php if ($batches_res): ?>
                        <?php while ($b = $batches_res->fetch_assoc()): ?>
                            <option value="<?php echo $b['id']; ?>">
                                <?php echo htmlspecialchars($b['name']); ?>
                            </option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>
            </div>
        </div>

        <div class="form-grid-2">
            <div class="form-group">
                <label>Coach</label>
                <select name="coach_id" required <?php if ($role === 'coach') echo 'readonly'; ?>>
                    <option value="">-- Select Coach --</option>
                    <?php if ($coaches_res): ?>
                        <?php while ($c = $coaches_res->fetch_assoc()): ?>
                            <option value="<?php echo $c['id']; ?>"
                                <?php if ($defaultCoachId && $defaultCoachId == $c['id']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($c['name']); ?>
                            </option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Ground (optional)</label>
                <select name="ground_id">
                    <option value="0">-- (not set) --</option>
                    <?php if ($grounds_res): ?>
                        <?php while ($g = $grounds_res->fetch_assoc()): ?>
                            <option value="<?php echo $g['id']; ?>">
                                <?php echo htmlspecialchars($g['name']); ?>
                            </option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label>Session Notes (what was done today)</label>
            <textarea name="notes" rows="5" placeholder="Example: Warm-up, fielding drills, batting nets vs spin, fitness sprints, match scenario simulation, etc."></textarea>
        </div>

        <button type="submit" class="button-primary">Save Session</button>
        <a href="sessions.php" class="button">Back to list</a>
    </form>
</div>

<?php include "includes/footer.php"; ?>
