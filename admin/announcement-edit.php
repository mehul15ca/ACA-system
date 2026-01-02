<?php
require_once __DIR__ . '/_bootstrap.php';

AdminGuard::requirePermission(Permissions::ANNOUNCEMENTS_MANAGE);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(400); exit('Invalid announcement ID.'); }

$message = '';
$success = '';

// Load current
$stmt = $conn->prepare("SELECT * FROM announcements WHERE id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$a = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$a) { http_response_code(404); exit('Announcement not found.'); }

// Dropdown data
$batches = [];
$grounds = [];
$r = $conn->query("SELECT id, name FROM batches WHERE status='active' ORDER BY name ASC");
while ($row = $r->fetch_assoc()) { $batches[] = $row; }
$r = $conn->query("SELECT id, name FROM grounds WHERE status='active' ORDER BY name ASC");
while ($row = $r->fetch_assoc()) { $grounds[] = $row; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title   = trim($_POST['title'] ?? '');
    $body    = trim($_POST['body'] ?? '');
    $status  = $_POST['status'] ?? 'active';
    $scope   = $_POST['scope'] ?? 'all';
    $batchId = (int)($_POST['batch_id'] ?? 0);
    $groundId= (int)($_POST['ground_id'] ?? 0);

    if ($title === '') $message = 'Title is required.';
    elseif ($body === '') $message = 'Message body is required.';
    elseif (!in_array($status, ['active','archived'], true)) $message = 'Invalid status.';
    elseif (!in_array($scope, ['all','students','coaches','batch_students','ground_students'], true)) $message = 'Invalid scope.';
    else {
        $audience = 'all';
        $batch_id = null;
        $ground_id = null;

        if ($scope === 'students') $audience = 'students';
        elseif ($scope === 'coaches') $audience = 'coaches';
        elseif ($scope === 'batch_students') { $audience = 'students'; $batch_id = $batchId > 0 ? $batchId : null; }
        elseif ($scope === 'ground_students') { $audience = 'students'; $ground_id = $groundId > 0 ? $groundId : null; }

        $upd = $conn->prepare(
            "UPDATE announcements
             SET title=?, body=?, audience=?, status=?, batch_id=?, ground_id=?
             WHERE id=?"
        );
        $upd->bind_param("ssssiii", $title, $body, $audience, $status, $batch_id, $ground_id, $id);

        if ($upd->execute()) {
            $success = 'Announcement updated.';
            $a['title'] = $title;
            $a['body'] = $body;
            $a['audience'] = $audience;
            $a['status'] = $status;
            $a['batch_id'] = $batch_id;
            $a['ground_id'] = $ground_id;
        } else {
            $message = 'Database error.';
        }
        $upd->close();
    }
}

// infer scope
$scopeCurrent = 'all';
if ($a['audience'] === 'students' && empty($a['batch_id']) && empty($a['ground_id'])) $scopeCurrent = 'students';
elseif ($a['audience'] === 'coaches') $scopeCurrent = 'coaches';
elseif ($a['audience'] === 'students' && !empty($a['batch_id'])) $scopeCurrent = 'batch_students';
elseif ($a['audience'] === 'students' && !empty($a['ground_id'])) $scopeCurrent = 'ground_students';
?>

<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Edit Announcement</h1>

<div class="form-card">
    <?php if ($message): ?><div class="alert-error"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

    <form method="POST">
        <?php echo Csrf::field(); ?>

        <div class="form-group">
            <label>Title</label>
            <input type="text" name="title" value="<?php echo htmlspecialchars($a['title']); ?>" required>
        </div>

        <div class="form-group">
            <label>Message</label>
            <textarea name="body" rows="6" required><?php echo htmlspecialchars($a['body']); ?></textarea>
        </div>

        <div class="form-group">
            <label>Status</label>
            <select name="status">
                <option value="active"<?php if ($a['status']==='active') echo ' selected'; ?>>active</option>
                <option value="archived"<?php if ($a['status']==='archived') echo ' selected'; ?>>archived</option>
            </select>
        </div>

        <div class="form-group">
            <label>Audience / Scope</label>
            <select name="scope" id="scope-select" onchange="toggleScopeFields()">
                <option value="all"<?php if ($scopeCurrent==='all') echo ' selected'; ?>>All students + coaches</option>
                <option value="students"<?php if ($scopeCurrent==='students') echo ' selected'; ?>>All students</option>
                <option value="coaches"<?php if ($scopeCurrent==='coaches') echo ' selected'; ?>>All coaches</option>
                <option value="batch_students"<?php if ($scopeCurrent==='batch_students') echo ' selected'; ?>>Students of a specific batch</option>
                <option value="ground_students"<?php if ($scopeCurrent==='ground_students') echo ' selected'; ?>>Students of a specific ground</option>
            </select>
        </div>

        <div class="form-grid-2">
            <div class="form-group" id="batch-field" style="display:none;">
                <label>Batch</label>
                <select name="batch_id">
                    <option value="0">-- Select Batch --</option>
                    <?php foreach ($batches as $b): ?>
                        <option value="<?php echo (int)$b['id']; ?>"<?php if (!empty($a['batch_id']) && (int)$a['batch_id']===(int)$b['id']) echo ' selected'; ?>>
                            <?php echo htmlspecialchars($b['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" id="ground-field" style="display:none;">
                <label>Ground</label>
                <select name="ground_id">
                    <option value="0">-- Select Ground --</option>
                    <?php foreach ($grounds as $g): ?>
                        <option value="<?php echo (int)$g['id']; ?>"<?php if (!empty($a['ground_id']) && (int)$a['ground_id']===(int)$g['id']) echo ' selected'; ?>>
                            <?php echo htmlspecialchars($g['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <button class="button-primary">Update Announcement</button>
        <a href="announcement-view.php?id=<?php echo (int)$a['id']; ?>" class="button">Back</a>
    </form>
</div>

<script>
function toggleScopeFields(){
  var v = document.getElementById('scope-select').value;
  var bf = document.getElementById('batch-field');
  var gf = document.getElementById('ground-field');
  bf.style.display = 'none';
  gf.style.display = 'none';
  if (v === 'batch_students') bf.style.display = 'block';
  if (v === 'ground_students') gf.style.display = 'block';
}
document.addEventListener('DOMContentLoaded', toggleScopeFields);
</script>

<?php include "includes/footer.php"; ?>
