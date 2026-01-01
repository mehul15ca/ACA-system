<?php
include "config.php";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$token = trim($_GET['token'] ?? '');

$step = 'check'; // check | form | done | error
$error = "";
$success = "";
$user_id = null;

if ($token === '') {
    $error = "Invalid reset token.";
    $step = 'error';
} else {
    $token_hash = hash('sha256', $token);

    $stmt = $conn->prepare("
        SELECT pr.id, pr.user_id, pr.expires_at, pr.used, u.username
        FROM password_resets pr
        JOIN users u ON u.id = pr.user_id
        WHERE pr.token = ?
        LIMIT 1
    ");
    $stmt->bind_param("s", $token_hash);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();

    if (!$row) {
        $error = "Reset link is invalid.";
        $step = 'error';
    } else {
        $now = new DateTime();
        $expires = new DateTime($row['expires_at']);

        if ((int)$row['used'] === 1) {
            $error = "This reset link has already been used.";
            $step = 'error';
        } elseif ($now > $expires) {
            $error = "This reset link has expired.";
            $step = 'error';
        } else {
            $step = 'form';
            $user_id = (int)$row['user_id'];

            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $pass1 = $_POST['password'] ?? '';
                $pass2 = $_POST['password_confirm'] ?? '';

                if ($pass1 === '' || $pass2 === '') {
                    $error = "Please enter and confirm your new password.";
                } elseif ($pass1 !== $pass2) {
                    $error = "Passwords do not match.";
                } elseif (strlen($pass1) < 6) {
                    $error = "Password must be at least 6 characters.";
                } else {
                    $hash = password_hash($pass1, PASSWORD_BCRYPT);

                    // Update password
                    $stmt2 = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                    $stmt2->bind_param("si", $hash, $user_id);
                    $stmt2->execute();

                    // Invalidate token
                    $stmt3 = $conn->prepare("UPDATE password_resets SET used = 1 WHERE id = ?");
                    $stmt3->bind_param("i", $row['id']);
                    $stmt3->execute();

                    $success = "Your password has been reset successfully.";
                    $step = 'done';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Reset Password – ACA</title>
    <!-- styles unchanged -->
</head>
<body>
<div class="wrap">
    <div class="card">
        <h1>Reset Password</h1>

        <?php if ($step === 'error'): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <div class="link-back"><a href="login.php">← Back to login</a></div>
        <?php elseif ($step === 'done'): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
            <div class="link-back"><a href="login.php">Go to login</a></div>
        <?php elseif ($step === 'form'): ?>
            <p>Set a new password.</p>

            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="field">
                    <label>New Password</label>
                    <input type="password" name="password">
                </div>
                <div class="field">
                    <label>Confirm New Password</label>
                    <input type="password" name="password_confirm">
                </div>
                <button type="submit" class="btn">Update Password</button>
            </form>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
