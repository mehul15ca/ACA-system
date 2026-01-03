<?php
require_once __DIR__ . '/_bootstrap.php';

requireSuperadmin();

if (!isset($_GET['id'])) {
    exit("User ID missing.");
}
$user_id = (int)$_GET['id'];

$stmt = $conn->prepare("SELECT id, username FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();

if (!$res || !$res->num_rows) {
    exit("User not found.");
}
$user = $res->fetch_assoc();

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $temp_password = bin2hex(random_bytes(8)); // 16 chars
    } catch (Throwable $e) {
        http_response_code(500);
        exit("Password generation failed.");
    }

    $hash = password_hash($temp_password, PASSWORD_DEFAULT);

    $up = $conn->prepare("
        UPDATE users
        SET password_hash = ?, must_change_password = 1
        WHERE id = ?
    ");
    $up->bind_param("si", $hash, $user_id);

    if ($up->execute()) {
        $message = "Password reset successfully. Temporary password: " . htmlspecialchars($temp_password);
    } else {
        $message = "Password reset failed.";
    }
}
?>
<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Reset Password</h1>

<div class="form-card">
    <p>User: <strong><?php echo htmlspecialchars($user['username']); ?></strong></p>

    <?php if ($message): ?>
        <p><?php echo $message; ?></p>
    <?php else: ?>
        <p>Are you sure you want to reset this user's password?</p>
    <?php endif; ?>

    <form method="POST">
        <?php echo Csrf::field(); ?>

        <button type="submit" class="button-primary">Reset Password</button>
    </form>
</div>

<p style="margin-top:10px;">
    <a class="text-link" href="users.php">⬅ Back to Users</a>
</p>

<?php include "includes/footer.php"; ?>
