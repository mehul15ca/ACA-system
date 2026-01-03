<?php
require_once __DIR__ . '/_bootstrap.php';

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::validateRequest();

    $_SESSION['pw_reset_last'] ??= 0;
    if (time() - $_SESSION['pw_reset_last'] < 60) {
        $errors[] = 'Please wait before requesting another reset.';
    } else {
        $_SESSION['pw_reset_last'] = time();

        $email = trim($_POST['email'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email.';
        } else {
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND status='active'");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($u = $res->fetch_assoc()) {
                $token = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $token);
                $expires = date('Y-m-d H:i:s', time() + 3600);

                $up = $conn->prepare("
                    UPDATE users
                    SET reset_token = ?, reset_expires = ?
                    WHERE id = ?
                ");
                $up->bind_param("ssi", $tokenHash, $expires, $u['id']);
                $up->execute();

                // SEND EMAIL HERE (no dev link output)
                // reset URL example:
                // https://yourdomain/reset-password.php?token=$token

                $success = 'If the email exists, a reset link has been sent.';
            } else {
                $success = 'If the email exists, a reset link has been sent.';
            }
        }
    }
}
?>

<form method="POST">
    <?= Csrf::field(); ?>
    <input type="email" name="email" placeholder="Your email" required>
    <button type="submit">Reset Password</button>
</form>

<?php if ($errors): foreach ($errors as $e): ?>
<p><?= htmlspecialchars($e) ?></p>
<?php endforeach; endif; ?>

<?php if ($success): ?>
<p><?= htmlspecialchars($success) ?></p>
<?php endif; ?>
