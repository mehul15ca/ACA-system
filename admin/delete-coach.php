<?php
include "../config.php";
checkLogin();

$role = currentUserRole();
if (!in_array($role, ['admin','superadmin'])) {
    http_response_code(403);
    echo "Access denied. Admin/Superadmin only.";
    exit;
}

if (!isset($_GET['id'])) {
    die("Coach ID missing.");
}
$coach_id = intval($_GET['id']);

// Fetch coach
$stmt = $conn->prepare("SELECT id, name, status FROM coaches WHERE id = ?");
$stmt->bind_param("i", $coach_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
    die("Coach not found.");
}
$coach = $res->fetch_assoc();

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Soft-disable coach
    $up = $conn->prepare("UPDATE coaches SET status = 'disabled' WHERE id = ?");
    $up->bind_param("i", $coach_id);
    $ok1 = $up->execute();

    // Also disable linked user accounts (if any)
    $up2 = $conn->prepare("UPDATE users SET status = 'disabled' WHERE coach_id = ?");
    $up2->bind_param("i", $coach_id);
    $ok2 = $up2->execute();

    if ($ok1) {
        header("Location: coaches.php");
        exit;
    } else {
        $message = "Error disabling coach: " . $conn->error;
    }
}
?>
<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Disable Coach</h1>

<div class="form-card">
    <p>You are about to <strong>disable</strong> this coach:</p>
    <p style="margin:8px 0;">
        ID: <strong><?php echo $coach['id']; ?></strong><br>
        Name: <strong><?php echo htmlspecialchars($coach['name']); ?></strong><br>
        Current status: <strong><?php echo htmlspecialchars($coach['status']); ?></strong>
    </p>

    <?php if ($message): ?>
        <p style="margin:8px 0;"><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>

    <p style="margin:8px 0; font-size:13px;">
        Disabling will not delete any data. It will mark this coach as <strong>disabled</strong> and set any linked login account to disabled as well.
    </p>

    <form method="POST">
        <button type="submit" class="button-primary">Confirm Disable</button>
    </form>
</div>

<p style="margin-top:10px;">
    <a class="text-link" href="coaches.php">⬅ Back to Coaches</a>
</p>

<?php include "includes/footer.php"; ?>
