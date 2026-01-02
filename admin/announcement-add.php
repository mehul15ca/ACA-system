<?php
require_once __DIR__ . '/_bootstrap.php';

AdminGuard::requirePermission(Permissions::ANNOUNCEMENTS_MANAGE);

$message = '';
$success = '';

// Dropdown data
$batches = [];
$grounds = [];

$r = $conn->query("SELECT id, name FROM batches WHERE status='active' ORDER BY name ASC");
while ($row = $r->fetch_assoc()) { $batches[] = $row; }

$r = $conn->query("SELECT id, name FROM grounds WHERE status='active' ORDER BY name ASC");
while ($row = $r->fetch_assoc()) { $grounds[] = $row; }

function queueAnnouncementEmails(mysqli $conn, string $scope, ?int $batch_id, ?int $ground_id, string $title, string $body): int
{
    $subject  = mb_substr($title, 0, 150);
    $template = "ANNOUNCEMENT";
    $channel  = "email";

    $emails = [];

    if ($scope === 'all') {
        $res = $conn->query("SELECT DISTINCT email FROM students WHERE status='active' AND email<>''");
        while ($res && ($r = $res->fetch_assoc())) { $emails[] = $r['email']; }

        $res = $conn->query("SELECT DISTINCT email FROM coaches WHERE status='active' AND email<>''");
        while ($res && ($r = $res->fetch_assoc())) { $emails[] = $r['email']; }

    } elseif ($scope === 'students') {
        $res = $conn->query("SELECT DISTINCT email FROM students WHERE status='active' AND email<>''");
        while ($res && ($r = $res->fetch_assoc())) { $emails[] = $r['email']; }

    } elseif ($scope === 'coaches') {
        $res = $conn->query("SELECT DISTINCT email FROM coaches WHERE status='active' AND email<>''");
        while ($res && ($r = $res->fetch_assoc())) { $emails[] = $r['email']; }

    } elseif ($scope === 'batch_students' && $batch_id && $batch_id > 0) {
        $stmt = $conn->prepare("SELECT DISTINCT email FROM students WHERE status='active' AND email<>'' AND batch_id=?");
        $stmt->bind_param("i", $batch_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($res && ($r = $res->fetch_assoc())) { $emails[] = $r['email']; }
        $stmt->close();

    } elseif ($scope === 'ground_students' && $ground_id && $ground_id > 0) {
        $stmt = $conn->prepare(
            "SELECT DISTINCT s.email
             FROM students s
             JOIN batch_schedule bs ON bs.batch_id = s.batch_id
             WHERE s.status='active' AND s.email<>'' AND bs.ground_id=?"
        );
        $stmt->bind_param("i", $ground_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($res && ($r = $res->fetch_assoc())) { $emails[] = $r['email']; }
        $stmt->close();
    }

    $emails = array_values(array_unique(array_filter($emails)));
    if (!$emails) return 0;

    $ins = $conn->prepare(
        "INSERT INTO notifications_queue
         (receiver_email, channel, subject, message, status, template_code)
         VALUES (?, ?, ?, ?, 'pending', ?)"
    );

    $queued = 0;
    foreach ($emails as $em) {
        $ins->bind_param("sssss", $em, $channel, $subject, $body, $template);
        if ($ins->execute()) $queued++;
    }
    $ins->close();

    return $queued;
}

$title = '';
$body  = '';
$scope = 'all';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title   = trim($_POST['title'] ?? '');
    $body    = trim($_POST['body'] ?? '');
    $scope   = $_POST['scope'] ?? 'all';
    $batchId = (int)($_POST['batch_id'] ?? 0);
    $groundId= (int)($_POST['ground_id'] ?? 0);

    if ($title === '') $message = "Title is required.";
    elseif ($body === '') $message = "Message body is required.";
    elseif (!in_array($scope, ['all','students','coaches','batch_students','ground_students'], true)) $message = "Invalid scope.";
    else {
        $audience = 'all';
        $batch_id = null;
        $ground_id = null;

        if ($scope === 'students') $audience = 'students';
        elseif ($scope === 'coaches') $audience = 'coaches';
        elseif ($scope === 'batch_students') { $audience = 'students'; $batch_id = $batchId > 0 ? $batchId : null; }
        elseif ($scope === 'ground_students') { $audience = 'students'; $ground_id = $groundId > 0 ? $groundId : null; }

        $status = 'active';

        $stmt = $conn->prepare(
            "INSERT INTO announcements (title, body, audience, status, batch_id, ground_id, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())"
        );
        $stmt->bind_param("ssssii", $title, $body, $audience, $status, $batch_id, $ground_id);

        if ($stmt->execute()) {
            $stmt->close();

            $queued = queueAnnouncementEmails($conn, $scope, $batch_id, $ground_id, $title, $body);

            $success = "Announcement created. Email queue: {$queued} recipient(s).";
            $title = '';
            $body = '';
            $scope = 'all';
        } else {
            $message = "Database error.";
            $stmt->close();
        }
    }
}
?>

<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>New Announcement</h1>

<div class="form-card">
    <?php if ($message): ?><div class="alert-error"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

    <form method="POST">
        <?php echo Csrf::field(); ?>

        <div class="form-group">
            <label>Title</label>
            <input type="text" name="title" value="<?php echo htmlspecialchars($title); ?>" required>
        </div>

        <div class="form-group">
            <label>Message</label>
            <textarea name="body" rows="6" required><?php echo htmlspecialchars($body); ?></textarea>
        </div>

        <div class="form-group">
            <label>Audience / Scope</label>
            <select name="scope" id="scope-select" onchange="toggleScopeFields()">
                <option value="all"<?php if ($scope==='all') echo ' selected'; ?>>All students + coaches</option>
                <option value="students"<?php if ($scope==='students') echo ' selected'; ?>>All students</option>
                <option value="coaches"<?php if ($scope==='coaches') echo ' selected'; ?>>All coaches</option>
                <option value="batch_students"<?php if ($scope==='batch_students') echo ' selected'; ?>>Students of a specific batch</option>
                <option value="ground_students"<?php if ($scope==='ground_students') echo ' selected'; ?>>Students of a specific ground</option>
            </select>
        </div>

        <div class="form-grid-2">
            <div class="form-group" id="batch-field" style="display:none;">
                <label>Batch</label>
                <select name="batch_id">
                    <option value="0">-- Select Batch --</option>
                    <?php foreach ($batches as $b): ?>
                        <option value="<?php echo (int)$b['id']; ?>">
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
                        <option value="<?php echo (int)$g['id']; ?>">
                            <?php echo htmlspecialchars($g['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <button class="button-primary">Create Announcement</button>
        <a href="announcements.php" class="button">Back to list</a>
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
