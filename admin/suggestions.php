<?php
require_once __DIR__ . '/_bootstrap.php';

$filter_student = isset($_GET['student']) ? trim($_GET['student']) : '';
$filter_status  = isset($_GET['status']) ? trim($_GET['status']) : '';
$filter_from    = isset($_GET['from']) ? $_GET['from'] : '';
$filter_to      = isset($_GET['to']) ? $_GET['to'] : '';

$sql = "
    SELECT sg.*,
           st.admission_no,
           st.first_name AS s_first,
           st.last_name  AS s_last
    FROM suggestions sg
    JOIN students st ON sg.student_id = st.id
    WHERE 1=1
";

$params = [];
$types  = "";

if ($filter_student !== '') {
    $sql .= " AND (
        st.admission_no LIKE ?
        OR CONCAT(st.first_name, ' ', st.last_name) LIKE ?
    )";
    $like = "%" . $filter_student . "%";
    $params[] = $like;
    $params[] = $like;
    $types   .= "ss";
}

if ($filter_status !== '') {
    $sql .= " AND sg.status = ? ";
    $params[] = $filter_status;
    $types   .= "s";
}

if ($filter_from !== '') {
    $sql .= " AND sg.`date` >= ? ";
    $params[] = $filter_from;
    $types   .= "s";
}
if ($filter_to !== '') {
    $sql .= " AND sg.`date` <= ? ";
    $params[] = $filter_to;
    $types   .= "s";
}

$sql .= " ORDER BY sg.`date` DESC, sg.created_at DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$res = $stmt->get_result();
?>
<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Suggestions & Feedback</h1>

<div class="form-card" style="margin-bottom:16px;">
    <form method="GET" style="display:flex; flex-wrap:wrap; gap:10px; align-items:flex-end;">
        <div>
            <label style="font-size:12px;">Student (name or admission)</label>
            <input type="text" name="student" value="<?php echo htmlspecialchars($filter_student); ?>">
        </div>
        <div>
            <label style="font-size:12px;">Status</label>
            <select name="status">
                <option value="">All</option>
                <option value="open" <?php if ($filter_status==='open') echo 'selected'; ?>>open</option>
                <option value="closed" <?php if ($filter_status==='closed') echo 'selected'; ?>>closed</option>
            </select>
        </div>
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
    </form>
</div>

<div class="table-card">
    <div class="table-header">
        <h2>All Suggestions</h2>
    </div>
    <table class="acatable">
        <thead>
            <tr>
                <th>Date</th>
                <th>Student</th>
                <th>Status</th>
                <th>Message (short)</th>
                <th>Attachment</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($res && $res->num_rows > 0): ?>
            <?php while ($r = $res->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($r['date']); ?></td>
                    <td><?php echo htmlspecialchars($r['admission_no'] . " - " . $r['s_first'] . " " . $r['s_last']); ?></td>
                    <td><?php echo htmlspecialchars($r['status']); ?></td>
                    <td>
                        <?php
                        $short = mb_substr($r['suggestion'], 0, 60);
                        if (mb_strlen($r['suggestion']) > 60) $short .= "...";
                        echo htmlspecialchars($short);
                        ?>
                    </td>
                    <td>
                        <?php if (!empty($r['drive_file_id'])): ?>
                            <a href="https://drive.google.com/file/d/<?php echo urlencode($r['drive_file_id']); ?>/view"
                               class="text-link" target="_blank">View file</a>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="suggestion-view.php?id=<?php echo $r['id']; ?>" class="text-link">View</a> |
                        <a href="suggestion-edit.php?id=<?php echo $r['id']; ?>" class="text-link">Edit</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="6">No suggestions found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include "includes/footer.php"; ?>
