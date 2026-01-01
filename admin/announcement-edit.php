<?php
include "../config.php";
checkLogin();

$role = currentUserRole();
if (!in_array($role, ['admin','superadmin'])) {
    http_response_code(403);
    echo "Access denied.";
    exit;
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) die("Invalid announcement ID.");

$message = "";
$success = "";

$sql = "SELECT * FROM announcements WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$a = $stmt->get_result()->fetch_assoc();
if (!$a) die("Announcement not found.");

$batches_res = $conn->query("SELECT id, name FROM batches WHERE status='active' ORDER BY name ASC");
$grounds_res = $conn->query("SELECT id, name FROM grounds WHERE status='active' ORDER BY name ASC");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title   = trim($_POST['title']);
    $body    = trim($_POST['body']);
    $status  = $_POST['status'] ?? 'active';
    $scope   = $_POST['scope'] ?? 'all';
    $batchId = isset($_POST['batch_id']) ? intval($_POST['batch_id']) : 0;
    $groundId= isset($_POST['ground_id']) ? intval($_POST['ground_id']) : 0;

    if ($title === '') {
        $message = "Title is required.";
    } elseif ($body === '') {
        $message = "Message body is required.";
    } elseif (!in_array($status, ['active','archived'])) {
        $message = "Invalid status.";
    } else {
        $audience = 'all';
        $batch_id = null;
        $ground_id = null;

        if ($scope === 'students') {
            $audience = 'students';
        } elseif ($scope === 'coaches') {
            $audience = 'coaches';
        } elseif ($scope === 'batch_students') {
            $audience = 'students';
            if ($batchId > 0) $batch_id = $batchId;
        } elseif ($scope === 'ground_students') {
            $audience = 'students';
            if ($groundId > 0) $ground_id = $groundId;
        } else {
            $audience = 'all';
        }

        $sqlU = "
            UPDATE announcements
            SET title = ?, body = ?, audience = ?, status = ?, batch_id = ?, ground_id = ?
            WHERE id = ?
        ";
        $stmtU = $conn->prepare($sqlU);
        $stmtU->bind_param(
            "ssssiii",
            $title,
            $body,
            $audience,
            $status,
            $batch_id,
            $ground_id,
            $id
        );
        if ($stmtU->execute()) {
            $success = "Announcement updated.";

            $a['title']    = $title;
            $a['body']     = $body;
            $a['audience'] = $audience;
            $a['status']   = $status;
            $a['batch_id'] = $batch_id;
            $a['ground_id']= $ground_id;
        } else {
            $message = "Database error: " . $conn->error;
        }
    }
}

// infer scope from current data
$scopeCurrent = 'all';
if ($a['audience'] === 'students' && empty($a['batch_id']) && empty($a['ground_id'])) {
    $scopeCurrent = 'students';
} elseif ($a['audience'] === 'coaches') {
    $scopeCurrent = 'coaches';
} elseif ($a['audience'] === 'students' && !empty($a['batch_id'])) {
    $scopeCurrent = 'batch_students';
} elseif ($a['audience'] === 'students' && !empty($a['ground_id'])) {
    $scopeCurrent = 'ground_students';
} else {
    $scopeCurrent = 'all';
}
?>
<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Edit Announcement</h1>

<div class="form-card">
    <?php if ($message): ?><div class="alert-error"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label>Title</label>
            <input type="text" name="title"
                   value="<?php echo htmlspecialchars($a['title']); ?>" required>
        </div>

        <div class="form-group">
            <label>Message</label>
            <textarea name="body" rows="6" required><?php echo htmlspecialchars($a['body']); ?></textarea>
        </div>

        <div class="form-group">
            <label>Status</label>
            <select name="status">
                <option value="active" <?php if ($a['status']==='active') echo 'selected'; ?>>active</option>
                <option value="archived" <?php if ($a['status']==='archived') echo 'selected'; ?>>archived</option>
            </select>
        </div>

        <div class="form-group">
            <label>Audience / Scope</label>
            <select name="scope" id="scope-select" onchange="toggleScopeFields()">
                <option value="all" <?php if ($scopeCurrent==='all') echo 'selected'; ?>>All students + coaches</option>
                <option value="students" <?php if ($scopeCurrent==='students') echo 'selected'; ?>>All students</option>
                <option value="coaches" <?php if ($scopeCurrent==='coaches') echo 'selected'; ?>>All coaches</option>
                <option value="batch_students" <?php if ($scopeCurrent==='batch_students') echo 'selected'; ?>>Students of a specific batch</option>
                <option value="ground_students" <?php if ($scopeCurrent==='ground_students') echo 'selected'; ?>>Students of a specific ground</option>
            </select>
        </div>

        <div class="form-grid-2">
            <div class="form-group" id="batch-field" style="display:none;">
                <label>Batch</label>
                <select name="batch_id">
                    <option value="0">-- Select Batch --</option>
                    <?php if ($batches_res): ?>
                        <?php while ($b = $batches_res->fetch_assoc()): ?>
                            <option value="<?php echo $b['id']; ?>"
                                <?php if (!empty($a['batch_id']) && $a['batch_id']==$b['id']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($b['name']); ?>
                            </option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>
            </div>
            <div class="form-group" id="ground-field" style="display:none;">
                <label>Ground</label>
                <select name="ground_id">
                    <option value="0">-- Select Ground --</option>
                    <?php if ($grounds_res): ?>
                        <?php while ($g = $grounds_res->fetch_assoc()): ?>
                            <option value="<?php echo $g['id']; ?>"
                                <?php if (!empty($a['ground_id']) && $a['ground_id']==$g['id']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($g['name']); ?>
                            </option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>
            </div>
        </div>

        <button type="submit" class="button-primary">Update Announcement</button>
        <a href="announcement-view.php?id=<?php echo $a['id']; ?>" class="button">Back</a>
    </form>
</div>

<script>
function toggleScopeFields() {
    var scope = document.getElementById('scope-select').value;
    var bf = document.getElementById('batch-field');
    var gf = document.getElementById('ground-field');
    bf.style.display = 'none';
    gf.style.display = 'none';
    if (scope === 'batch_students') {
        bf.style.display = 'block';
    } else if (scope === 'ground_students') {
        gf.style.display = 'block';
    }
}
document.addEventListener('DOMContentLoaded', toggleScopeFields);
</script>

<?php include "includes/footer.php"; ?>
