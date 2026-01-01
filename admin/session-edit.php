<?php
include "../config.php";
checkLogin();

$role = currentUserRole();
if (!in_array($role, ['admin','superadmin','coach'])) {
    http_response_code(403);
    echo "Access denied.";
    exit;
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) die("Invalid session ID.");

$message = "";
$success = "";

$sql = "
    SELECT * FROM training_sessions
    WHERE id = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$s = $stmt->get_result()->fetch_assoc();
if (!$s) die("Session not found.");

$batches_res = $conn->query("SELECT id, name FROM batches WHERE status='active' ORDER BY name ASC");
$coaches_res = $conn->query("SELECT id, name FROM coaches WHERE status='active' ORDER BY name ASC");
$grounds_res = $conn->query("SELECT id, name FROM grounds WHERE status='active' ORDER BY name ASC");

// If coach, restrict editing to own sessions
if ($role === 'coach' && $s['coach_id']) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $userId = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
    $stmtU = $conn->prepare("SELECT coach_id FROM users WHERE id = ?");
    $stmtU->bind_param("i", $userId);
    $stmtU->execute();
    $u = $stmtU->get_result()->fetch_assoc();
    $coachId = $u && $u['coach_id'] ? intval($u['coach_id']) : 0;

    if ($coachId <= 0 || $coachId !== intval($s['coach_id'])) {
        http_response_code(403);
        echo "Access denied.";
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        $sqlU = "
            UPDATE training_sessions
            SET session_date = ?, batch_id = ?, coach_id = ?, ground_id = ?, notes = ?
            WHERE id = ?
        ";
        $stmtU = $conn->prepare($sqlU);
        $stmtU->bind_param(
            "siiisi",
            $session_date,
            $batch_id,
            $coach_id,
            $ground_id,
            $notes,
            $id
        );
        if ($stmtU->execute()) {
            $success = "Training session updated.";
            $s['session_date'] = $session_date;
            $s['batch_id']     = $batch_id;
            $s['coach_id']     = $coach_id;
            $s['ground_id']    = $ground_id;
            $s['notes']        = $notes;
        } else {
            $message = "Database error: " . $conn->error;
        }
    }
}
?>
<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Edit Training Session</h1>

<div class="form-card">
    <?php if ($message): ?><div class="alert-error"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

    <form method="POST">
        <div class="form-grid-2">
            <div class="form-group">
                <label>Date</label>
                <input type="date" name="session_date"
                       value="<?php echo htmlspecialchars($s['session_date']); ?>">
            </div>
            <div class="form-group">
                <label>Batch</label>
                <select name="batch_id" required>
                    <option value="">-- Select Batch --</option>
                    <?php if ($batches_res): ?>
                        <?php while ($b = $batches_res->fetch_assoc()): ?>
                            <option value="<?php echo $b['id']; ?>"
                                <?php if ($b['id'] == $s['batch_id']) echo 'selected'; ?>>
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
                <select name="coach_id" required>
                    <option value="">-- Select Coach --</option>
                    <?php if ($coaches_res): ?>
                        <?php while ($c = $coaches_res->fetch_assoc()): ?>
                            <option value="<?php echo $c['id']; ?>"
                                <?php if ($c['id'] == $s['coach_id']) echo 'selected'; ?>>
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
                            <option value="<?php echo $g['id']; ?>"
                                <?php if ($g['id'] == $s['ground_id']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($g['name']); ?>
                            </option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label>Session Notes</label>
            <textarea name="notes" rows="5"><?php echo htmlspecialchars($s['notes']); ?></textarea>
        </div>

        <button type="submit" class="button-primary">Update Session</button>
        <a href="session-view.php?id=<?php echo $s['id']; ?>" class="button">Back</a>
    </form>
</div>

<?php include "includes/footer.php"; ?>
