<?php
include "../config.php";
checkLogin();

$user_id = $_SESSION['user_id'];
$message = "";

// Fetch current user info
$stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
    die("User not found.");
}
$user = $res->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_pass = $_POST['new_password'];
    $confirm  = $_POST['confirm_password'];

    if ($new_pass === "" || $confirm === "") {
        $message = "Please enter and confirm your new password.";
    } elseif ($new_pass !== $confirm) {
        $message = "Passwords do not match.";
    } elseif (strlen($new_pass) < 8) {
        $message = "Password must be at least 8 characters.";
    } else {
        $hash = password_hash($new_pass, PASSWORD_DEFAULT);
        $up = $conn->prepare("UPDATE users SET password_hash = ?, must_change_password = 0 WHERE id = ?");
        $up->bind_param("si", $hash, $user_id);
        if ($up->execute()) {
            header("Location: dashboard.php");
            exit;
        } else {
            $message = "Error updating password: " . $conn->error;
        }
    }
}
?>
<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Set New Password</h1>

<div class="form-card">
    <p>Username: <strong><?php echo htmlspecialchars($user['username']); ?></strong></p>
    <p style="margin-bottom:10px;">Please set your new password to continue.</p>

    <?php if ($message): ?>
        <p style="margin-bottom:10px;"><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>

    <form method="POST">
        <div class="form-row">
            <label>New Password</label>
            <input type="password" name="new_password" required>
        </div>

        <div class="form-row">
            <label>Confirm Password</label>
            <input type="password" name="confirm_password" required>
        </div>

        <button type="submit" class="button-primary">Save Password</button>
    </form>
</div>

<?php include "includes/footer.php"; ?>
