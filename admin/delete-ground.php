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
    die("Ground ID missing.");
}
$ground_id = intval($_GET['id']);

// Fetch ground
$stmt = $conn->prepare("SELECT id, name, status FROM grounds WHERE id = ?");
$stmt->bind_param("i", $ground_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
    die("Ground not found.");
}
$ground = $res->fetch_assoc();

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $up = $conn->prepare("UPDATE grounds SET status = 'disabled' WHERE id = ?");
    $up->bind_param("i", $ground_id);
    if ($up->execute()) {
        header("Location: grounds.php");
        exit;
    } else {
        $message = "Error disabling ground: " . $conn->error;
    }
}
?>
<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Disable Ground</h1>

<div class="form-card">
    <p>You are about to <strong>disable</strong> this ground:</p>
    <p style="margin:8px 0;">
        ID: <strong><?php echo $ground['id']; ?></strong><br>
        Name: <strong><?php echo htmlspecialchars($ground['name']); ?></strong><br>
        Current status: <strong><?php echo htmlspecialchars($ground['status']); ?></strong>
    </p>

    <?php if ($message): ?>
        <p style="margin:8px 0;"><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>

    <p style="margin:8px 0; font-size:13px;">
        Disabling will not delete any data. It will just mark this ground as <strong>disabled</strong> and it can be excluded from active lists.
    </p>

    <form method="POST">
        <button type="submit" class="button-primary">Confirm Disable</button>
    </form>
</div>

<p style="margin-top:10px;">
    <a class="text-link" href="grounds.php">⬅ Back to Grounds</a>
</p>

<?php include "includes/footer.php"; ?>
