<?php
require_once __DIR__ . '/_bootstrap.php';

$token = $_GET['token'] ?? '';
$errors = [];
$success = '';

if ($token === '') {
    exit('Invalid reset token.');
}

$tokenHash = hash('sha256', $token);

$stmt = $conn->prepare("
    SELECT id FROM users
    WHERE reset_token = ?
      AND reset_expires > NOW()
");
$stmt->bind_param("s", $tokenHash);
$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();

if (!$user) {
    exit('Reset link expired or invalid.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::validateRequest();

    $pass = $_POST['password'] ?? '';
    if (strlen($pass) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    } else {
        $hash = password_hash($pass, PASSWORD_DEFAULT);

        $up = $conn->prepare("
            UPDATE users
            SET password = ?, reset_token = NULL, reset_expires = NULL
            WHERE id = ?
        ");
        $up->bind_param("si", $hash, $user['id']);
        $up->execute();

        $success = 'Password reset successful.';
    }
}
?>

<form method="POST">
    <?= Csrf::field(); ?>
    <input type="password" name="password" placeholder="New password" required>
    <button type="submit">Set Password</button>
</form>

<?php if ($errors): foreach ($errors as $e): ?>
<p><?= htmlspecialchars($e) ?></p>
<?php endforeach; endif; ?>

<?php if ($success): ?>
<p><?= htmlspecialchars($success) ?></p>
<?php endif; ?>
