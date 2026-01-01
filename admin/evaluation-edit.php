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
if ($id <= 0) die("Invalid evaluation ID.");

$message = "";
$success = "";

$sql = "
    SELECT * FROM player_evaluation
    WHERE id = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$ev = $stmt->get_result()->fetch_assoc();
if (!$ev) die("Evaluation not found.");

$students_res = $conn->query("SELECT id, admission_no, first_name, last_name FROM students WHERE status='active' ORDER BY first_name ASC");
$coaches_res  = $conn->query("SELECT id, name FROM coaches WHERE status='active' ORDER BY name ASC");

// If coach, ensure this evaluation belongs to them
if ($role === 'coach') {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $userId = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
    $stmtU = $conn->prepare("SELECT coach_id FROM users WHERE id = ?");
    $stmtU->bind_param("i", $userId);
    $stmtU->execute();
    $u = $stmtU->get_result()->fetch_assoc();
    $coachId = $u && $u['coach_id'] ? intval($u['coach_id']) : 0;

    if ($coachId <= 0 || $coachId !== intval($ev['coach_id'])) {
        http_response_code(403);
        echo "Access denied.";
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = intval($_POST['student_id']);
    $coach_id   = intval($_POST['coach_id']);
    $eval_time  = $_POST['eval_time'] !== "" ? $_POST['eval_time'] : date('Y-m-d H:i:s');
    $status     = $_POST['status'];

    $batting    = $_POST['batting_rating']   !== "" ? intval($_POST['batting_rating'])   : null;
    $bowling    = $_POST['bowling_rating']   !== "" ? intval($_POST['bowling_rating'])   : null;
    $fielding   = $_POST['fielding_rating']  !== "" ? intval($_POST['fielding_rating'])  : null;
    $fitness    = $_POST['fitness_rating']   !== "" ? intval($_POST['fitness_rating'])   : null;
    $discipline = $_POST['discipline_rating']!== "" ? intval($_POST['discipline_rating']): null;
    $attitude   = $_POST['attitude_rating']  !== "" ? intval($_POST['attitude_rating'])  : null;
    $technique  = $_POST['technique_rating'] !== "" ? intval($_POST['technique_rating']) : null;

    $notes      = trim($_POST['notes']);

    if ($student_id <= 0 || $coach_id <= 0) {
        $message = "Student and coach are required.";
    } elseif (!in_array($status, ['draft','final'])) {
        $message = "Invalid status.";
    } else {
        $vals = [];
        foreach ([$batting, $bowling, $fielding, $fitness, $discipline, $attitude, $technique] as $v) {
            if ($v !== null) $vals[] = $v;
        }
        $overall = null;
        if (count($vals) > 0) {
            $overall = array_sum($vals) / count($vals);
        }

        $sqlU = "
            UPDATE player_evaluation
            SET eval_time = ?, student_id = ?, coach_id = ?,
                batting_rating = ?, bowling_rating = ?, fielding_rating = ?,
                fitness_rating = ?, discipline_rating = ?, attitude_rating = ?, technique_rating = ?,
                overall_score = ?, notes = ?, status = ?
            WHERE id = ?
        ";
        $stmtU = $conn->prepare($sqlU);
        $stmtU->bind_param(
            "siiiiiiiii dssi".replace(" ", ""),
            $eval_time,
            $student_id,
            $coach_id,
            $batting,
            $bowling,
            $fielding,
            $fitness,
            $discipline,
            $attitude,
            $technique,
            $overall,
            $notes,
            $status,
            $id
        );
        if ($stmtU->execute()) {
            $success = "Evaluation updated.";
            // refresh $ev
            $ev['eval_time'] = $eval_time;
            $ev['student_id'] = $student_id;
            $ev['coach_id'] = $coach_id;
            $ev['batting_rating'] = $batting;
            $ev['bowling_rating'] = $bowling;
            $ev['fielding_rating'] = $fielding;
            $ev['fitness_rating'] = $fitness;
            $ev['discipline_rating'] = $discipline;
            $ev['attitude_rating'] = $attitude;
            $ev['technique_rating'] = $technique;
            $ev['overall_score'] = $overall;
            $ev['notes'] = $notes;
            $ev['status'] = $status;
        } else {
            $message = "Database error: " . $conn->error;
        }
    }
}
?>
<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Edit Player Evaluation</h1>

<div class="form-card">
    <?php if ($message): ?><div class="alert-error"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

    <form method="POST">
        <div class="form-grid-2">
            <div class="form-group">
                <label>Student</label>
                <select name="student_id" required>
                    <option value="">-- Select Student --</option>
                    <?php if ($students_res): ?>
                        <?php while ($s = $students_res->fetch_assoc()): ?>
                            <option value="<?php echo $s['id']; ?>"
                                <?php if ($s['id'] == $ev['student_id']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($s['admission_no'] . " - " . $s['first_name'] . " " . $s['last_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Coach</label>
                <select name="coach_id" required>
                    <option value="">-- Select Coach --</option>
                    <?php if ($coaches_res): ?>
                        <?php while ($c = $coaches_res->fetch_assoc()): ?>
                            <option value="<?php echo $c['id']; ?>"
                                <?php if ($c['id'] == $ev['coach_id']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($c['name']); ?>
                            </option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label>Evaluation Time</label>
            <input type="datetime-local" name="eval_time"
                   value="<?php echo htmlspecialchars(date('Y-m-d\TH:i', strtotime($ev['eval_time']))); ?>">
        </div>

        <div class="form-grid-2">
            <div class="form-group">
                <label>Batting (1–10)</label>
                <input type="number" name="batting_rating" min="1" max="10"
                       value="<?php echo htmlspecialchars($ev['batting_rating']); ?>">
            </div>
            <div class="form-group">
                <label>Bowling (1–10)</label>
                <input type="number" name="bowling_rating" min="1" max="10"
                       value="<?php echo htmlspecialchars($ev['bowling_rating']); ?>">
            </div>
        </div>

        <div class="form-grid-2">
            <div class="form-group">
                <label>Fielding (1–10)</label>
                <input type="number" name="fielding_rating" min="1" max="10"
                       value="<?php echo htmlspecialchars($ev['fielding_rating']); ?>">
            </div>
            <div class="form-group">
                <label>Fitness (1–10)</label>
                <input type="number" name="fitness_rating" min="1" max="10"
                       value="<?php echo htmlspecialchars($ev['fitness_rating']); ?>">
            </div>
        </div>

        <div class="form-grid-2">
            <div class="form-group">
                <label>Discipline (1–10)</label>
                <input type="number" name="discipline_rating" min="1" max="10"
                       value="<?php echo htmlspecialchars($ev['discipline_rating']); ?>">
            </div>
            <div class="form-group">
                <label>Attitude (1–10)</label>
                <input type="number" name="attitude_rating" min="1" max="10"
                       value="<?php echo htmlspecialchars($ev['attitude_rating']); ?>">
            </div>
        </div>

        <div class="form-group">
            <label>Technique (1–10)</label>
            <input type="number" name="technique_rating" min="1" max="10"
                   value="<?php echo htmlspecialchars($ev['technique_rating']); ?>">
        </div>

        <div class="form-group">
            <label>Notes (overall feedback)</label>
            <textarea name="notes" rows="4"><?php echo htmlspecialchars($ev['notes']); ?></textarea>
        </div>

        <div class="form-group">
            <label>Status</label>
            <select name="status">
                <option value="draft" <?php if ($ev['status']==='draft') echo 'selected'; ?>>draft</option>
                <option value="final" <?php if ($ev['status']==='final') echo 'selected'; ?>>final</option>
            </select>
        </div>

        <button type="submit" class="button-primary">Update Evaluation</button>
        <a href="evaluation-view.php?id=<?php echo $ev['id']; ?>" class="button">Back</a>
    </form>
</div>

<?php include "includes/footer.php"; ?>
