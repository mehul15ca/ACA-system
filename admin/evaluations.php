<?php
include "../config.php";
checkLogin();

$role = currentUserRole();
if (!in_array($role, ['admin','superadmin','coach'])) {
    http_response_code(403);
    echo "Access denied.";
    exit;
}

$filter_student = isset($_GET['student']) ? trim($_GET['student']) : '';
$filter_coach   = isset($_GET['coach']) ? trim($_GET['coach']) : '';
$filter_status  = isset($_GET['status']) ? trim($_GET['status']) : '';
$filter_from    = isset($_GET['from']) ? $_GET['from'] : '';
$filter_to      = isset($_GET['to']) ? $_GET['to'] : '';

$sql = "
    SELECT pe.*,
           s.admission_no,
           s.first_name AS s_first,
           s.last_name  AS s_last,
           c.name       AS coach_name
    FROM player_evaluation pe
    JOIN students s ON pe.student_id = s.id
    LEFT JOIN coaches c ON pe.coach_id = c.id
    WHERE 1=1
";

$params = [];
$types  = "";

// Coach restriction: coach sees only their own evaluations
if ($role === 'coach') {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $userId = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;

    $stmt = $conn->prepare("SELECT coach_id FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $coachId = $r && $r['coach_id'] ? intval($r['coach_id']) : 0;

    if ($coachId > 0) {
        $sql .= " AND pe.coach_id = ? ";
        $params[] = $coachId;
        $types   .= "i";
    }
}

if ($filter_student !== '') {
    $sql .= " AND (
        s.admission_no LIKE ?
        OR CONCAT(s.first_name, ' ', s.last_name) LIKE ?
    )";
    $like = "%" . $filter_student . "%";
    $params[] = $like;
    $params[] = $like;
    $types   .= "ss";
}

if ($filter_coach !== '') {
    $sql .= " AND c.name LIKE ? ";
    $params[] = "%" . $filter_coach . "%";
    $types   .= "s";
}

if ($filter_status !== '') {
    $sql .= " AND pe.status = ? ";
    $params[] = $filter_status;
    $types   .= "s";
}

if ($filter_from !== '') {
    $sql .= " AND pe.eval_time >= ? ";
    $params[] = $filter_from . " 00:00:00";
    $types   .= "s";
}
if ($filter_to !== '') {
    $sql .= " AND pe.eval_time <= ? ";
    $params[] = $filter_to . " 23:59:59";
    $types   .= "s";
}

$sql .= " ORDER BY pe.eval_time DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$res = $stmt->get_result();
?>
<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Player Evaluations</h1>

<div class="form-card" style="margin-bottom:16px;">
    <form method="GET" style="display:flex; flex-wrap:wrap; gap:10px; align-items:flex-end;">
        <div>
            <label style="font-size:12px;">Student (name or admission)</label>
            <input type="text" name="student" value="<?php echo htmlspecialchars($filter_student); ?>">
        </div>
        <div>
            <label style="font-size:12px;">Coach</label>
            <input type="text" name="coach" value="<?php echo htmlspecialchars($filter_coach); ?>">
        </div>
        <div>
            <label style="font-size:12px;">Status</label>
            <select name="status">
                <option value="">All</option>
                <option value="draft" <?php if ($filter_status==='draft') echo 'selected'; ?>>draft</option>
                <option value="final" <?php if ($filter_status==='final') echo 'selected'; ?>>final</option>
            </select>
        </div>
        <div>
            <label style="font-size:12px;">From</label>
            <input type="date" name="from" value="<?php echo htmlspecialchars($filter_from); ?>">
        </div>
        <div>
            <label style="font-size:12px;">To</label>
            <input type="date" name="to" value="<?php echo htmlspecialchars($filter_to); ?>">
        </div>
        <div>
            <button type="submit" class="button-primary">Filter</button>
        </div>
        <?php if (in_array($role, ['admin','superadmin','coach'])): ?>
            <div style="margin-left:auto;">
                <a href="evaluation-add.php" class="button">➕ New Evaluation</a>
            </div>
        <?php endif; ?>
    </form>
</div>

<div class="table-card">
    <div class="table-header">
        <h2>All Evaluations</h2>
    </div>
    <table class="acatable">
        <thead>
            <tr>
                <th>Date/Time</th>
                <th>Student</th>
                <th>Coach</th>
                <th>Overall</th>
                <th>Status</th>
                <th>Notes (short)</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($res && $res->num_rows > 0): ?>
            <?php while ($e = $res->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($e['eval_time']); ?></td>
                    <td><?php echo htmlspecialchars($e['admission_no'] . " - " . $e['s_first'] . " " . $e['s_last']); ?></td>
                    <td><?php echo htmlspecialchars($e['coach_name']); ?></td>
                    <td>
                        <?php
                        if (isset($e['overall_score']) && $e['overall_score'] !== null) {
                            echo number_format($e['overall_score'], 2);
                        } else {
                            echo "-";
                        }
                        ?>
                    </td>
                    <td><?php echo htmlspecialchars($e['status']); ?></td>
                    <td>
                        <?php
                        $short = mb_substr($e['notes'], 0, 40);
                        if (mb_strlen($e['notes']) > 40) $short .= "...";
                        echo htmlspecialchars($short);
                        ?>
                    </td>
                    <td>
                        <a href="evaluation-view.php?id=<?php echo $e['id']; ?>" class="text-link">View</a>
                        <?php if (in_array($role, ['admin','superadmin','coach'])): ?>
                            | <a href="evaluation-edit.php?id=<?php echo $e['id']; ?>" class="text-link">Edit</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="7">No evaluations found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include "includes/footer.php"; ?>
