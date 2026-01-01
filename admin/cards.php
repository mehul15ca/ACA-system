<?php
include "../config.php";
checkLogin();

$role = currentUserRole();
if (!in_array($role, ['admin','superadmin'])) {
    http_response_code(403);
    echo "Access denied.";
    exit;
}

$search_uid       = isset($_GET['uid']) ? trim($_GET['uid']) : '';
$search_number    = isset($_GET['card_number']) ? trim($_GET['card_number']) : '';
$search_name      = isset($_GET['name']) ? trim($_GET['name']) : '';
$filter_status    = isset($_GET['status']) ? trim($_GET['status']) : '';
$filter_type      = isset($_GET['card_type']) ? trim($_GET['card_type']) : '';
$show_unassigned  = isset($_GET['unassigned']) && $_GET['unassigned'] === '1';

$sql = "
    SELECT c.*,
           CASE
               WHEN c.assigned_to_type = 'student' THEN s.first_name
               WHEN c.assigned_to_type = 'coach'   THEN ch.name
               ELSE NULL
           END AS owner_first_name,
           CASE
               WHEN c.assigned_to_type = 'student' THEN s.last_name
               ELSE ''
           END AS owner_last_name,
           CASE
               WHEN c.assigned_to_type = 'student' THEN s.admission_no
               ELSE ''
           END AS admission_no,
           c.assigned_to_type
    FROM cards c
    LEFT JOIN students s
        ON c.assigned_to_type = 'student'
       AND c.assigned_to_id = s.id
    LEFT JOIN coaches ch
        ON c.assigned_to_type = 'coach'
       AND c.assigned_to_id = ch.id
    WHERE 1=1
";

$params = [];
$types  = "";

// Filters
if ($search_uid !== "") {
    $sql .= " AND c.uid LIKE ? ";
    $params[] = "%" . $search_uid . "%";
    $types   .= "s";
}
if ($search_number !== "") {
    $sql .= " AND c.card_number LIKE ? ";
    $params[] = "%" . $search_number . "%";
    $types   .= "s";
}
if ($search_name !== "") {
    $sql .= " AND (
        (c.assigned_to_type = 'student' AND CONCAT(s.first_name, ' ', s.last_name) LIKE ?)
        OR
        (c.assigned_to_type = 'coach' AND ch.name LIKE ?)
    ) ";
    $params[] = "%" . $search_name . "%";
    $params[] = "%" . $search_name . "%";
    $types   .= "ss";
}
if ($filter_status !== "") {
    $sql .= " AND c.status = ? ";
    $params[] = $filter_status;
    $types   .= "s";
}
if ($filter_type !== "") {
    $sql .= " AND c.card_type = ? ";
    $params[] = $filter_type;
    $types   .= "s";
}
if ($show_unassigned) {
    $sql .= " AND (
        (c.assigned_to_type = 'student' AND s.id IS NULL)
        OR
        (c.assigned_to_type = 'coach' AND ch.id IS NULL)
    ) ";
}

$sql .= " ORDER BY c.created_at DESC ";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
?>
<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Cards Management</h1>

<div class="form-card" style="margin-bottom:16px;">
    <form method="GET" style="display:flex; flex-wrap:wrap; gap:10px; align-items:flex-end;">
        <div>
            <label style="font-size:12px;">UID</label>
            <input type="text" name="uid" value="<?php echo htmlspecialchars($search_uid); ?>">
        </div>
        <div>
            <label style="font-size:12px;">Card Number</label>
            <input type="text" name="card_number" value="<?php echo htmlspecialchars($search_number); ?>">
        </div>
        <div>
            <label style="font-size:12px;">Owner Name</label>
            <input type="text" name="name" value="<?php echo htmlspecialchars($search_name); ?>">
        </div>
        <div>
            <label style="font-size:12px;">Status</label>
            <select name="status">
                <option value="">All</option>
                <option value="active"   <?php if ($filter_status === 'active')   echo 'selected'; ?>>active</option>
                <option value="inactive" <?php if ($filter_status === 'inactive') echo 'selected'; ?>>inactive</option>
            </select>
        </div>
        <div>
            <label style="font-size:12px;">Card Type</label>
            <select name="card_type">
                <option value="">All</option>
                <option value="Student" <?php if ($filter_type === 'Student') echo 'selected'; ?>>Student</option>
                <option value="Coach"   <?php if ($filter_type === 'Coach')   echo 'selected'; ?>>Coach</option>
                <option value="Staff"   <?php if ($filter_type === 'Staff')   echo 'selected'; ?>>Staff</option>
            </select>
        </div>
        <div style="display:flex; align-items:center; gap:4px;">
            <input type="checkbox" id="unassigned" name="unassigned" value="1" <?php if ($show_unassigned) echo 'checked'; ?>>
            <label for="unassigned" style="font-size:12px;">Show cards without owner</label>
        </div>
        <div>
            <button type="submit" class="button-primary">Filter</button>
        </div>
        <div style="margin-left:auto;">
            <a href="add-card.php" class="button">➕ Add Card</a>
        </div>
    </form>
</div>

<div class="table-card">
    <div class="table-header">
        <h2>All Cards</h2>
    </div>

    <table class="acatable">
        <thead>
            <tr>
                <th>ID</th>
                <th>Card No.</th>
                <th>UID</th>
                <th>Type</th>
                <th>Owner</th>
                <th>Admission/Code</th>
                <th>Status</th>
                <th>Issued On</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($result && $result->num_rows > 0): ?>
            <?php while ($c = $result->fetch_assoc()): ?>
                <?php
                    $ownerName = "";
                    if ($c['assigned_to_type'] === 'student') {
                        $ownerName = trim($c['owner_first_name'] . " " . $c['owner_last_name']);
                    } elseif ($c['assigned_to_type'] === 'coach') {
                        $ownerName = $c['owner_first_name'];
                    }
                ?>
                <tr>
                    <td><?php echo $c['id']; ?></td>
                    <td><?php echo htmlspecialchars($c['card_number']); ?></td>
                    <td><?php echo htmlspecialchars($c['uid']); ?></td>
                    <td><?php echo htmlspecialchars($c['card_type']); ?></td>
                    <td><?php echo htmlspecialchars($ownerName ?: '-'); ?></td>
                    <td>
                        <?php
                        if ($c['assigned_to_type'] === 'student') {
                            echo htmlspecialchars($c['admission_no']);
                        } else {
                            echo "-";
                        }
                        ?>
                    </td>
                    <td><?php echo htmlspecialchars($c['status']); ?></td>
                    <td><?php echo htmlspecialchars($c['issued_on']); ?></td>
                    <td>
                        <a href="view-card.php?id=<?php echo $c['id']; ?>" class="text-link">View</a>
                        |
                        <a href="edit-card.php?id=<?php echo $c['id']; ?>" class="text-link">Edit</a>
                        |
                        <a href="replace-card.php?id=<?php echo $c['id']; ?>" class="text-link">Replace</a>
                        |
                        <a href="toggle-card-status.php?id=<?php echo $c['id']; ?>" class="text-link">
                            <?php echo $c['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>
                        </a>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="9">No cards found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include "includes/footer.php"; ?>
