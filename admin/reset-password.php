<?php
include "../config.php";
checkLogin();
requireSuperadmin();

if (!isset($_GET['id'])) {
    die("User ID missing.");
}
$user_id = intval($_GET['id']);

$stmt = $conn->prepare("SELECT id, username FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
    die("User not found.");
}
$user = $res->fetch_assoc();

$message = "";
$temp_password = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $temp_password = bin2hex(random_bytes(4));
    } catch (Exception $e) {
        $temp_password = "Welcome123";
    }
    $hash = password_hash($temp_password, PASSWORD_DEFAULT);

    $up = $conn->prepare("UPDATE users SET password_hash = ?, must_change_password = 1 WHERE id = ?");
    $up->bind_param("si", $hash, $user_id);
    if ($up->execute()) {
        $message = "Password reset successfully. New temporary password: " . $temp_password;
    } else {
        $message = "Error: " . $conn->error;
    }
}
?>
<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Reset Password</h1>

<div class="form-card">
    <p>User: <strong><?php echo htmlspecialchars($user['username']); ?></strong></p>

    <?php if ($message): ?>
        <p style="margin:10px 0;"><?php echo htmlspecialchars($message); ?></p>
    <?php else: ?>
        <p style="margin:10px 0;">Are you sure you want to reset this user's password?</p>
    <?php endif; ?>

    <form method="POST">
        <button type="submit" class="button-primary">Reset Password</button>
    </form>

    <p style="margin-top:10px; font-size:12px;">
        After reset, share the temporary password with the user. They will be forced to set a new one on next login.
    </p>
</div>

<p style="margin-top:10px;">
    <a class="text-link" href="users.php">⬅ Back to Users</a>
</p>

<?php include "includes/footer.php"; ?>
