<?php
include "../config.php";
checkLogin();

$role = currentUserRole();
if (!in_array($role, ['admin','superadmin','coach'])) {
    http_response_code(403);
    echo "Access denied.";
    exit;
}

csrfInit();

$message = "";
$success = "";

// Dropdown data
$students_res = $conn->query("
    SELECT id, admission_no, first_name, last_name
    FROM students
    WHERE status='active'
    ORDER BY first_name ASC
");
$coaches_res  = $conn->query("
    SELECT id, name
    FROM coaches
    WHERE status='active'
    ORDER BY name ASC
");

// Default coach if logged in as coach
$defaultCoachId = "";
if ($role === 'coach') {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $userId = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
    if ($userId > 0) {
        $stmt = $conn->prepare("SELECT coach_id FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if ($row && !empty($row['coach_id'])) {
            $defaultCoachId = intval($row['coach_id']);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::validateRequest();
    verifyCsrf();

    $student_id = intval($_POST['student_id'] ?? 0);
    $coach_id   = intval($_POST['coach_id'] ?? 0);

    // datetime-local comes as YYYY-MM-DDTHH:MM -> store as YYYY-MM-DD HH:MM:SS
    $eval_time_in = trim($_POST['eval_time'] ?? '');
    if ($eval_time_in !== '') {
        $eval_time = str_replace('T', ' ', $eval_time_in) . ":00";
    } else {
        $eval_time = date('Y-m-d H:i:s');
    }

    $status     = $_POST['status'] ?? 'draft';

    $batting    = ($_POST['batting_rating']   ?? '') !== "" ? intval($_POST['batting_rating'])   : null;
    $bowling    = ($_POST['bowling_rating']   ?? '') !== "" ? intval($_POST['bowling_rating'])   : null;
    $fielding   = ($_POST['fielding_rating']  ?? '') !== "" ? intval($_POST['fielding_rating'])  : null;
    $fitness    = ($_POST['fitness_rating']   ?? '') !== "" ? intval($_POST['fitness_rating'])   : null;
    $discipline = ($_POST['discipline_rating']?? '') !== "" ? intval($_POST['discipline_rating']): null;
    $attitude   = ($_POST['attitude_rating']  ?? '') !== "" ? intval($_POST['attitude_rating'])  : null;
    $technique  = ($_POST['technique_rating'] ?? '') !== "" ? intval($_POST['technique_rating']) : null;

    $notes      = trim($_POST['notes'] ?? '');

    if ($student_id <= 0 || $coach_id <= 0) {
        $message = "Student and coach are required.";
    } elseif (!in_array($status, ['draft','final'], true)) {
        $message = "Invalid status.";
    } else {
        // overall average of provided ratings
        $vals = [];
        foreach ([$batting, $bowling, $fielding, $fitness, $discipline, $attitude, $technique] as $v) {
            if ($v !== null) $vals[] = $v;
        }
        $overall = null;
        if (count($vals) > 0) $overall = array_sum($vals) / count($vals);

        $sql = "
            INSERT INTO player_evaluation
                (eval_time, student_id, coach_id,
                 batting_rating, bowling_rating, fielding_rating,
                 fitness_rating, discipline_rating, attitude_rating, technique_rating,
                 overall_score, notes, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            $message = "Prepare failed: " . $conn->error;
        } else {
            // s i i i i i i i i i d s s
            $stmt->bind_param(
                "siiiiiiiii dss",
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
                $status
            );

            if ($stmt->execute()) {
                $success = "Evaluation saved.";
            } else {
                $message = "Database error: " . $stmt->error;
            }
        }
    }
}
?>
<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>New Player Evaluation</h1>

<div class="form-card">
    <?php if ($message): ?><div class="alert-error"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">

        <div class="form-grid-2">
            <div class="form-group">
                <label>Student</label>
                <select name="student_id" required>
                    <option value="">-- Select Student --</option>
                    <?php if ($students_res): ?>
                        <?php while ($s = $students_res->fetch_assoc()): ?>
                            <option value="<?php echo (int)$s['id']; ?>">
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
                            <option value="<?php echo (int)$c['id']; ?>"
                                <?php if ($defaultCoachId && $defaultCoachId == $c['id']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($c['name']); ?>
                            </option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label>Evaluation Time</label>
            <input type="datetime-local" name="eval_time" value="<?php echo htmlspecialchars(date('Y-m-d\TH:i')); ?>">
        </div>

        <div class="form-grid-2">
            <div class="form-group">
                <label>Batting (1–10)</label>
                <input type="number" name="batting_rating" min="1" max="10">
            </div>
            <div class="form-group">
                <label>Bowling (1–10)</label>
                <input type="number" name="bowling_rating" min="1" max="10">
            </div>
        </div>

        <div class="form-grid-2">
            <div class="form-group">
                <label>Fielding (1–10)</label>
                <input type="number" name="fielding_rating" min="1" max="10">
            </div>
            <div class="form-group">
                <label>Fitness (1–10)</label>
                <input type="number" name="fitness_rating" min="1" max="10">
            </div>
        </div>

        <div class="form-grid-2">
            <div class="form-group">
                <label>Discipline (1–10)</label>
                <input type="number" name="discipline_rating" min="1" max="10">
            </div>
            <div class="form-group">
                <label>Attitude (1–10)</label>
                <input type="number" name="attitude_rating" min="1" max="10">
            </div>
        </div>

        <div class="form-group">
            <label>Technique (1–10)</label>
            <input type="number" name="technique_rating" min="1" max="10">
        </div>

        <div class="form-group">
            <label>Notes (overall feedback)</label>
            <textarea name="notes" rows="4" placeholder="Write overall feedback, strengths, areas to improve..."></textarea>
        </div>

        <div class="form-group">
            <label>Status</label>
            <select name="status">
                <option value="draft">draft</option>
                <option value="final">final</option>
            </select>
        </div>

        <button type="submit" class="button-primary">Save Evaluation</button>
        <a href="evaluations.php" class="button">Back to list</a>
    </form>
</div>

<?php include "includes/footer.php"; ?>
