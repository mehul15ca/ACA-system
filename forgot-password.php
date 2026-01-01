<?php
include "config.php";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$info = "";
$error = "";
$reset_link = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');

    if ($username === '') {
        $error = "Please enter your username.";
    } else {
        $stmt = $conn->prepare("SELECT id, username, role FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $res = $stmt->get_result();
        $user = $res->fetch_assoc();

        if (!$user) {
            $error = "No account found with that username.";
        } else {
            $user_id = $user['id'];
            $token = bin2hex(random_bytes(32)); // 64 chars
            $expires_at = (new DateTime('+15 minutes'))->format('Y-m-d H:i:s');

            $stmt2 = $conn->prepare("
                INSERT INTO password_resets (user_id, token, expires_at, used)
                VALUES (?, ?, ?, 0)
            ");
            $stmt2->bind_param("iss", $user_id, $token, $expires_at);
            $stmt2->execute();

            // Build reset link (update base URL when on real domain)
            $baseUrl = "http://localhost/ACA-System";
            $reset_link = $baseUrl . "/reset-password.php?token=" . urlencode($token);

            $info = "If this username exists, a reset link has been generated.";
            // For now, show link directly (dev mode). Later you will email this.
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Forgot Password – ACA</title>
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
        input[type="text"] {
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
        .info { margin-top:10px; font-size:12px; color:#A7F3D0; }
        .link-back { margin-top:12px; font-size:12px; }
        .link-back a { color:#39E7FF; text-decoration:none; }
        .dev-link { margin-top:8px; font-size:11px; color:#9CA3AF; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h1>Forgot Password</h1>
        <p>Enter your username and we'll generate a reset link. Later this will be emailed automatically.</p>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($info): ?>
            <div class="info"><?php echo htmlspecialchars($info); ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="field">
                <label for="username">Username</label>
                <input type="text" name="username" id="username"
                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
            </div>
            <button type="submit" class="btn">Generate Reset Link</button>
        </form>

        <div class="link-back">
            <a href="login.php">← Back to login</a>
        </div>

        <?php if ($reset_link): ?>
            <div class="dev-link">
                Dev reset link (for now):<br>
                <a href="<?php echo htmlspecialchars($reset_link); ?>" style="color:#39E7FF;">
                    <?php echo htmlspecialchars($reset_link); ?>
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
