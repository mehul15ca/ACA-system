<?php
include "../config.php";
checkLogin();

$role = currentUserRole();
if (!in_array($role, ['admin','superadmin'])) {
    http_response_code(403);
    echo "Access denied.";
    exit;
}

$message = "";
$success = "";

$batches_res = $conn->query("SELECT id, name FROM batches WHERE status='active' ORDER BY name ASC");
$grounds_res = $conn->query("SELECT id, name FROM grounds WHERE status='active' ORDER BY name ASC");

function queueAnnouncementEmails($conn, $announcementId, $scope, $audience, $batch_id, $ground_id, $title, $body) {
    $subject = mb_substr($title, 0, 150);
    $message = $body;
    $template = "ANNOUNCEMENT";
    $channel  = "email";

    $emails = [];

    if ($scope === 'all') {
        // all students
        $res = $conn->query("SELECT DISTINCT email FROM students WHERE email <> '' AND status='active'");
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $emails[] = $r['email'];
            }
        }
        // all coaches
        $res2 = $conn->query("SELECT DISTINCT email FROM coaches WHERE email <> '' AND status='active'");
        if ($res2) {
            while ($r = $res2->fetch_assoc()) {
                $emails[] = $r['email'];
            }
        }
    } elseif ($scope === 'students' || ($scope === 'batch_students') || ($scope === 'ground_students')) {
        if ($scope === 'students') {
            $sql = "SELECT DISTINCT email FROM students WHERE email <> '' AND status='active'";
            $res = $conn->query($sql);
        } elseif ($scope === 'batch_students' && $batch_id > 0) {
            $stmt = $conn->prepare("SELECT DISTINCT email FROM students WHERE email <> '' AND status='active' AND batch_id = ?");
            $stmt->bind_param("i", $batch_id);
            $stmt->execute();
            $res = $stmt->get_result();
        } elseif ($scope === 'ground_students' && $ground_id > 0) {
            $stmt = $conn->prepare("
                SELECT DISTINCT s.email
                FROM students s
                JOIN batch_schedule bs ON s.batch_id = bs.batch_id
                WHERE s.email <> '' AND s.status='active' AND bs.ground_id = ?
            ");
            $stmt->bind_param("i", $ground_id);
            $stmt->execute();
            $res = $stmt->get_result();
        } else {
            $res = false;
        }

        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $emails[] = $r['email'];
            }
        }
    } elseif ($scope === 'coaches') {
        $res = $conn->query("SELECT DISTINCT email FROM coaches WHERE email <> '' AND status='active'");
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $emails[] = $r['email'];
            }
        }
    }

    $emails = array_unique(array_filter($emails));

    if (empty($emails)) {
        return;
    }

    $stmtIns = $conn->prepare("
        INSERT INTO notifications_queue
            (receiver_email, channel, subject, message, status, template_code)
        VALUES (?, ?, ?, ?, 'pending', ?)
    ");

    foreach ($emails as $em) {
        $stmtIns->bind_param("sssss", $em, $channel, $subject, $message, $template);
        $stmtIns->execute();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title   = trim($_POST['title']);
    $body    = trim($_POST['body']);
    $scope   = $_POST['scope'] ?? 'all';
    $batchId = isset($_POST['batch_id']) ? intval($_POST['batch_id']) : 0;
    $groundId= isset($_POST['ground_id']) ? intval($_POST['ground_id']) : 0;

    if ($title === '') {
        $message = "Title is required.";
    } elseif ($body === '') {
        $message = "Message body is required.";
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
        } else { // all
            $audience = 'all';
        }

        $status = 'active';

        $sql = "
            INSERT INTO announcements (title, body, audience, status, batch_id, ground_id, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "ssssii",
            $title,
            $body,
            $audience,
            $status,
            $batch_id,
            $ground_id
        );

        if ($stmt->execute()) {
            $announcementId = $stmt->insert_id;

            // Queue emails into notifications_queue (email only)
            queueAnnouncementEmails($conn, $announcementId, $scope, $audience, $batch_id, $ground_id, $title, $body);

            $success = "Announcement created and email notifications queued.";
            $title = "";
            $body = "";
        } else {
            $message = "Database error: " . $conn->error;
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
        <div class="form-group">
            <label>Title</label>
            <input type="text" name="title"
                   value="<?php echo isset($title) ? htmlspecialchars($title) : ''; ?>"
                   required>
        </div>

        <div class="form-group">
            <label>Message</label>
            <textarea name="body" rows="6" required><?php echo isset($body) ? htmlspecialchars($body) : ''; ?></textarea>
        </div>

        <div class="form-group">
            <label>Audience / Scope</label>
            <select name="scope" id="scope-select" onchange="toggleScopeFields()">
                <option value="all">All students + coaches</option>
                <option value="students">All students</option>
                <option value="coaches">All coaches</option>
                <option value="batch_students">Students of a specific batch</option>
                <option value="ground_students">Students of a specific ground</option>
            </select>
            <p style="font-size:11px;color:#9ca3af;margin-top:4px;">
                Email notifications will be queued automatically for the selected group.
            </p>
        </div>

        <div class="form-grid-2">
            <div class="form-group" id="batch-field" style="display:none;">
                <label>Batch</label>
                <select name="batch_id">
                    <option value="0">-- Select Batch --</option>
                    <?php if ($batches_res): ?>
                        <?php while ($b = $batches_res->fetch_assoc()): ?>
                            <option value="<?php echo $b['id']; ?>">
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
                            <option value="<?php echo $g['id']; ?>">
                                <?php echo htmlspecialchars($g['name']); ?>
                            </option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>
            </div>
        </div>

        <button type="submit" class="button-primary">Create Announcement</button>
        <a href="announcements.php" class="button">Back to list</a>
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
