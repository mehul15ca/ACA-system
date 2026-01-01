<?php
include "config.php";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$token = $_GET['token'] ?? '';
$token = trim($token);

$step = 'check'; // check | form | done
$error = "";
$success = "";
$user_id = null;

if ($token === '') {
    $error = "Invalid reset token.";
    $step = 'error';
} else {
    // Check token
    $stmt = $conn->prepare("
        SELECT pr.id, pr.user_id, pr.expires_at, pr.used, u.username
        FROM password_resets pr
        JOIN users u ON u.id = pr.user_id
        WHERE pr.token = ?
        LIMIT 1
    ");
    $stmt->bind_param("s", $token);
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
            $username = $row['username'];

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
                    // Update password
                    $hash = password_hash($pass1, PASSWORD_BCRYPT);

                    $stmt2 = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                    $stmt2->bind_param("si", $hash, $user_id);
                    $stmt2->execute();

                    // Mark token used
                    $stmt3 = $conn->prepare("UPDATE password_resets SET used = 1 WHERE id = ?");
                    $stmt3->bind_param("i", $row['id']);
                    $stmt3->execute();

                    $success = "Your password has been reset successfully. You can now login with your new password.";
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
    <style>
        body {
            margin:0;
            font-family: system-ui,-apple-system,"Segoe UI",sans-serif;
            background:#020617;
            color:#E5E7EB;
            min-height:100vh;
            display:flex;
            align-items:center;
            justify-content:center;
        }
        .wrap { max-width:420px; width:100%; padding:16px; }
        .card {
            background:#020617;
            border-radius:18px;
            padding:22px;
            border:1px solid #111827;
            box-shadow:0 18px 40px rgba(0,0,0,0.6);
        }
        h1 { margin:0 0 6px; font-size:20px; }
        p { font-size:13px; color:#9CA3AF; }
        .field { margin-top:12px; }
        label { display:block; font-size:12px; margin-bottom:4px; }
        input[type="password"] {
            width:100%; padding:9px 11px;
            border-radius:10px; border:1px solid #1F2937;
            background:#020617; color:#F9FAFB; font-size:13px;
        }
        input:focus { outline:none; border-color:#39E7FF; }
        .btn {
            margin-top:14px;
            width:100%; padding:9px 12px;
            border-radius:999px; border:none;
            background:linear-gradient(135deg,#003566,#004BA0);
            color:#F9FAFB; font-size:14px; font-weight:600;
            cursor:pointer;
        }
        .error { margin-top:10px; font-size:12px; color:#FCA5A5; }
        .success { margin-top:10px; font-size:12px; color:#A7F3D0; }
        .link-back { margin-top:12px; font-size:12px; }
        .link-back a { color:#39E7FF; text-decoration:none; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h1>Reset Password</h1>

        <?php if ($step === 'error'): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <div class="link-back">
                <a href="login.php">← Back to login</a>
            </div>
        <?php elseif ($step === 'done'): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
            <div class="link-back">
                <a href="login.php">Go to login</a>
            </div>
        <?php elseif ($step === 'form'): ?>
            <p>Set a new password for your ACA account.</p>

            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="field">
                    <label for="password">New Password</label>
                    <input type="password" name="password" id="password">
                </div>
                <div class="field">
                    <label for="password_confirm">Confirm New Password</label>
                    <input type="password" name="password_confirm" id="password_confirm">
                </div>
                <button type="submit" class="btn">Update Password</button>
            </form>

            <div class="link-back">
                <a href="login.php">← Back to login</a>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
