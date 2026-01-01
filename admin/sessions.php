<?php
include "../config.php";
checkLogin();

$role = currentUserRole();
if (!in_array($role, ['admin','superadmin','coach'])) {
    http_response_code(403);
    echo "Access denied.";
    exit;
}

$filter_batch = isset($_GET['batch_id']) ? intval($_GET['batch_id']) : 0;
$filter_coach = isset($_GET['coach_id']) ? intval($_GET['coach_id']) : 0;
$filter_from  = isset($_GET['from']) ? $_GET['from'] : '';
$filter_to    = isset($_GET['to']) ? $_GET['to'] : '';

$sql = "
    SELECT ts.*,
           b.name AS batch_name,
           c.name AS coach_name,
           g.name AS ground_name
    FROM training_sessions ts
    JOIN batches b ON ts.batch_id = b.id
    LEFT JOIN coaches c ON ts.coach_id = c.id
    LEFT JOIN grounds g ON ts.ground_id = g.id
    WHERE 1=1
";

$params = [];
$types  = "";

// Coach restriction: only sessions of that coach
if ($role === 'coach') {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $userId = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;

    $stmt = $conn->prepare("SELECT coach_id FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $coachId = $r && $r['coach_id'] ? intval($r['coach_id']) : 0;

    if ($coachId > 0) {
        $sql .= " AND ts.coach_id = ? ";
        $params[] = $coachId;
        $types   .= "i";
        $filter_coach = $coachId; // prefill filter
    }
}

if ($filter_batch > 0) {
    $sql .= " AND ts.batch_id = ? ";
    $params[] = $filter_batch;
    $types   .= "i";
}

if ($filter_coach > 0 && $role !== 'coach') {
    $sql .= " AND ts.coach_id = ? ";
    $params[] = $filter_coach;
    $types   .= "i";
}

if ($filter_from !== '') {
    $sql .= " AND ts.session_date >= ? ";
    $params[] = $filter_from;
    $types   .= "s";
}
if ($filter_to !== '') {
    $sql .= " AND ts.session_date <= ? ";
    $params[] = $filter_to;
    $types   .= "s";
}

$sql .= " ORDER BY ts.session_date DESC, ts.created_at DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$res = $stmt->get_result();

// Fetch batches & coaches for filters
$batches_res = $conn->query("SELECT id, name FROM batches WHERE status='active' ORDER BY name ASC");
$coaches_res = $conn->query("SELECT id, name FROM coaches WHERE status='active' ORDER BY name ASC");
?>
<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Training Sessions</h1>

<div class="form-card" style="margin-bottom:16px;">
    <form method="GET" style="display:flex; flex-wrap:wrap; gap:10px; align-items:flex-end;">
        <div>
            <label style="font-size:12px;">Batch</label>
            <select name="batch_id">
                <option value="0">All</option>
                <?php if ($batches_res): ?>
                    <?php while ($b = $batches_res->fetch_assoc()): ?>
                        <option value="<?php echo $b['id']; ?>"
                            <?php if ($filter_batch == $b['id']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($b['name']); ?>
                        </option>
                    <?php endwhile; ?>
                <?php endif; ?>
            </select>
        </div>
        <?php if ($role !== 'coach'): ?>
        <div>
            <label style="font-size:12px;">Coach</label>
            <select name="coach_id">
                <option value="0">All</option>
                <?php if ($coaches_res): ?>
                    <?php while ($c = $coaches_res->fetch_assoc()): ?>
                        <option value="<?php echo $c['id']; ?>"
                            <?php if ($filter_coach == $c['id']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($c['name']); ?>
                        </option>
                    <?php endwhile; ?>
                <?php endif; ?>
            </select>
        </div>
        <?php endif; ?>
        <div>
            <label style="font-size:12px;">From Date</label>
            <input type="date" name="from" value="<?php echo htmlspecialchars($filter_from); ?>">
        </div>
        <div>
            <label style="font-size:12px;">To Date</label>
            <input type="date" name="to" value="<?php echo htmlspecialchars($filter_to); ?>">
        </div>
        <div>
            <button type="submit" class="button-primary">Filter</button>
        </div>
        <div style="margin-left:auto;">
            <a href="session-add.php" class="button">➕ Add Session</a>
        </div>
    </form>
</div>

<div class="table-card">
    <div class="table-header">
        <h2>All Training Sessions</h2>
    </div>
    <table class="acatable">
        <thead>
            <tr>
                <th>Date</th>
                <th>Batch</th>
                <th>Coach</th>
                <th>Ground</th>
                <th>Notes (short)</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($res && $res->num_rows > 0): ?>
            <?php while ($r = $res->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($r['session_date']); ?></td>
                    <td><?php echo htmlspecialchars($r['batch_name']); ?></td>
                    <td><?php echo htmlspecialchars($r['coach_name']); ?></td>
                    <td><?php echo htmlspecialchars($r['ground_name']); ?></td>
                    <td>
                        <?php
                        $short = mb_substr($r['notes'], 0, 60);
                        if (mb_strlen($r['notes']) > 60) $short .= "...";
                        echo htmlspecialchars($short);
                        ?>
                    </td>
                    <td>
                        <a href="session-view.php?id=<?php echo $r['id']; ?>" class="text-link">View</a>
                        <?php if (in_array($role, ['admin','superadmin','coach'])): ?>
                            | <a href="session-edit.php?id=<?php echo $r['id']; ?>" class="text-link">Edit</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="6">No sessions found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include "includes/footer.php"; ?>
