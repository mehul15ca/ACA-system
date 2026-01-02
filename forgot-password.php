<?php
require "config.php";

use ACA\Core\Csrf;

$info = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify($_POST['csrf'] ?? null);

    $username = trim($_POST['username'] ?? '');
    $info = "If this account exists, a reset email has been sent.";

    if ($username !== '') {
        $stmt = $conn->prepare("SELECT id FROM users WHERE username=? LIMIT 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($user = $res->fetch_assoc()) {
            $token = bin2hex(random_bytes(32));
            $hash  = hash('sha256', $token);
            $exp   = (new DateTime('+15 minutes'))->format('Y-m-d H:i:s');

            $stmt = $conn->prepare("
                INSERT INTO password_resets (user_id, token, expires_at, used)
                VALUES (?, ?, ?, 0)
            ");
            $stmt->bind_param("iss", $user['id'], $hash, $exp);
            $stmt->execute();

            // SEND EMAIL HERE ONLY
        }
    }
}
?>
<form method="post">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
    <input name="username">
    <button>Reset</button>
</form>
