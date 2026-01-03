<?php
require_once __DIR__ . '/_bootstrap.php';

$userId = currentUserId(); 

$message = "";
$success = "";

// Load batches for dropdown
$batches = [];
$resB = $conn->query("SELECT id, name, code FROM batches WHERE status='active' ORDER BY name");
if ($resB) {
    while ($row = $resB->fetch_assoc()) {
        $batches[] = $row;
    }
}

$generatedUrl = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $batch_id = intval($_POST['batch_id'] ?? 0);

    if ($email === '' || $batch_id <= 0) {
        $message = "Email and batch are required.";
    } else {
        // Simple email validation
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "Invalid email address.";
        } else {
            // Create token
            $token = bin2hex(random_bytes(16));
            $emailEsc = $conn->real_escape_string($email);

            $sql = "
                INSERT INTO registration_tokens (token, email, batch_id, invited_by_user_id, status)
                VALUES ('{$token}', '{$emailEsc}', {$batch_id}, ".intval($userId).", 'pending')
            ";
            if ($conn->query($sql)) {
                // Build registration URL (adjust domain as needed)
                $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http")
                           . "://" . $_SERVER['HTTP_HOST']
                           . rtrim(dirname($_SERVER['PHP_SELF']), '/admin');
                // Expecting student-register.php in root of ACA-System
                $generatedUrl = $baseUrl . "/student-register.php?token=" . urlencode($token);
                $success = "Registration link generated.";
            } else {
                $message = "Database error: " . $conn->error;
            }
        }
    }
}
?>
<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Generate Student Registration Link</h1>

<div class="form-card">
    <?php if ($message): ?>
        <div class="alert-error"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <form method="POST">
        <?php echo Csrf::field(); ?>

        <div class="form-group">
            <label>Student Email</label>
            <input type="email" name="email" required placeholder="student@example.com">
        </div>
        <div class="form-group">
            <label>Assign Batch</label>
            <select name="batch_id" required>
                <option value="">-- Select Batch --</option>
                <?php foreach ($batches as $b): ?>
                    <option value="<?php echo $b['id']; ?>">
                        <?php echo htmlspecialchars($b['name'] . " (" . $b['code'] . ")"); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="button-primary">Generate Link</button>
    </form>

    <?php if ($generatedUrl): ?>
        <div class="form-group" style="margin-top:16px;">
            <label>Registration URL</label>
            <textarea readonly style="width:100%;height:60px;font-size:12px;"><?php echo htmlspecialchars($generatedUrl); ?></textarea>
            <p style="font-size:11px;color:#9ca3af;margin-top:4px;">
                Copy this link and send it to the student. In the future, we can also auto-send it via email.
            </p>
        </div>
    <?php endif; ?>
</div>

<?php include "includes/footer.php"; ?>
