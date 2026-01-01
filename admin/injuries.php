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
$filter_severity= isset($_GET['severity']) ? trim($_GET['severity']) : '';
$filter_status  = isset($_GET['status']) ? trim($_GET['status']) : '';
$filter_from    = isset($_GET['from']) ? $_GET['from'] : '';
$filter_to      = isset($_GET['to']) ? $_GET['to'] : '';

$sql = "
    SELECT ir.*,
           s.admission_no,
           s.first_name AS s_first,
           s.last_name  AS s_last,
           c.name       AS coach_name
    FROM injury_reports ir
    JOIN students s ON ir.student_id = s.id
    LEFT JOIN coaches c ON ir.coach_id = c.id
    WHERE 1=1
";

$params = [];
$types  = "";

// Coach restriction: only see their own reports
if ($role === 'coach') {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $userId = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;

    $stmt = $conn->prepare("SELECT coach_id FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $coachId = $r && $r['coach_id'] ? intval($r['coach_id']) : 0;

    if ($coachId > 0) {
        $sql .= " AND ir.coach_id = ? ";
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

if ($filter_severity !== '') {
    $sql .= " AND ir.severity = ? ";
    $params[] = $filter_severity;
    $types   .= "s";
}

if ($filter_status !== '') {
    $sql .= " AND ir.status = ? ";
    $params[] = $filter_status;
    $types   .= "s";
}

if ($filter_from !== '') {
    $sql .= " AND ir.incident_date >= ? ";
    $params[] = $filter_from;
    $types   .= "s";
}
if ($filter_to !== '') {
    $sql .= " AND ir.incident_date <= ? ";
    $params[] = $filter_to;
    $types   .= "s";
}

$sql .= " ORDER BY ir.incident_date DESC, ir.reported_at DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$res = $stmt->get_result();
?>
<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Injury Reports</h1>

<div class="form-card" style="margin-bottom:16px;">
    <form method="GET" style="display:flex; flex-wrap:wrap; gap:10px; align-items:flex-end;">
        <div>
            <label style="font-size:12px;">Student (name or admission)</label>
            <input type="text" name="student" value="<?php echo htmlspecialchars($filter_student); ?>">
        </div>
        <div>
            <label style="font-size:12px;">Severity</label>
            <select name="severity">
                <option value="">All</option>
                <option value="minor" <?php if ($filter_severity==='minor') echo 'selected'; ?>>minor</option>
                <option value="moderate" <?php if ($filter_severity==='moderate') echo 'selected'; ?>>moderate</option>
                <option value="serious" <?php if ($filter_severity==='serious') echo 'selected'; ?>>serious</option>
                <option value="critical" <?php if ($filter_severity==='critical') echo 'selected'; ?>>critical</option>
            </select>
        </div>
        <div>
            <label style="font-size:12px;">Status</label>
            <select name="status">
                <option value="">All</option>
                <option value="open" <?php if ($filter_status==='open') echo 'selected'; ?>>open</option>
                <option value="pending" <?php if ($filter_status==='pending') echo 'selected'; ?>>pending</option>
                <option value="closed" <?php if ($filter_status==='closed') echo 'selected'; ?>>closed</option>
            </select>
        </div>
        <div>
            <label style="font-size:12px;">Incident From</label>
            <input type="date" name="from" value="<?php echo htmlspecialchars($filter_from); ?>">
        </div>
        <div>
            <label style="font-size:12px;">Incident To</label>
            <input type="date" name="to" value="<?php echo htmlspecialchars($filter_to); ?>">
        </div>
        <div>
            <button type="submit" class="button-primary">Filter</button>
        </div>
        <?php if (in_array($role, ['admin','superadmin','coach'])): ?>
            <div style="margin-left:auto;">
                <a href="injury-add.php" class="button">➕ New Injury Report</a>
            </div>
        <?php endif; ?>
    </form>
</div>

<div class="table-card">
    <div class="table-header">
        <h2>All Injury Reports</h2>
    </div>
    <table class="acatable">
        <thead>
            <tr>
                <th>Incident Date</th>
                <th>Student</th>
                <th>Coach</th>
                <th>Area</th>
                <th>Severity</th>
                <th>Status</th>
                <th>Reported At</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($res && $res->num_rows > 0): ?>
            <?php while ($r = $res->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($r['incident_date']); ?></td>
                    <td><?php echo htmlspecialchars($r['admission_no'] . " - " . $r['s_first'] . " " . $r['s_last']); ?></td>
                    <td><?php echo htmlspecialchars($r['coach_name']); ?></td>
                    <td><?php echo htmlspecialchars($r['injury_area']); ?></td>
                    <td><?php echo htmlspecialchars($r['severity']); ?></td>
                    <td><?php echo htmlspecialchars($r['status']); ?></td>
                    <td><?php echo htmlspecialchars($r['reported_at']); ?></td>
                    <td>
                        <a href="injury-view.php?id=<?php echo $r['id']; ?>" class="text-link">View</a>
                        <?php if (in_array($role, ['admin','superadmin','coach'])): ?>
                            | <a href="injury-edit.php?id=<?php echo $r['id']; ?>" class="text-link">Edit</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="8">No injury reports found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include "includes/footer.php"; ?>
