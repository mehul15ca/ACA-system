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
        $stmt = $conn->prepare("SELECT id, username FROM users WHERE username = ? LIMIT 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $res = $stmt->get_result();
        $user = $res->fetch_assoc();

        // Always show generic message (avoid user enumeration)
        $info = "If this username exists, a reset link has been generated.";

        if ($user) {
            $user_id = (int)$user['id'];

            // Generate raw token (sent to user)
            $token = bin2hex(random_bytes(32));

            // Hash token for DB storage
            $token_hash = hash('sha256', $token);

            $expires_at = (new DateTime('+15 minutes'))->format('Y-m-d H:i:s');

            $stmt2 = $conn->prepare("
                INSERT INTO password_resets (user_id, token, expires_at, used)
                VALUES (?, ?, ?, 0)
            ");
            $stmt2->bind_param("iss", $user_id, $token_hash, $expires_at);
            $stmt2->execute();

            // Build reset link (raw token only)
            $baseUrl = "http://localhost/ACA-System";
            $reset_link = $baseUrl . "/reset-password.php?token=" . urlencode($token);
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Forgot Password – ACA</title>
    <!-- styles unchanged -->
</head>
<body>
<div class="wrap">
    <div class="card">
        <h1>Forgot Password</h1>
        <p>Enter your username and we'll generate a reset link.</p>

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
                Dev reset link:<br>
                <a href="<?php echo htmlspecialchars($reset_link); ?>">
                    <?php echo htmlspecialchars($reset_link); ?>
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
