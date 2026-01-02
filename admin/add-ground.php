<?php
require_once __DIR__ . '/_bootstrap.php';

AdminGuard::requirePermission(Permissions::GROUNDS_MANAGE);

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $code     = trim($_POST['code'] ?? '');
    $address  = trim($_POST['address'] ?? '');
    $city     = trim($_POST['city'] ?? '');
    $province = trim($_POST['province'] ?? '');
    $country  = trim($_POST['country'] ?? 'Canada');
    $password = trim($_POST['password'] ?? '');
    $indoor   = isset($_POST['indoor']) ? 1 : 0;
    $status   = $_POST['status'] ?? 'active';

    if ($name === '') {
        $message = 'Name required.';
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO grounds
            (name, code, address, city, province, country, password, indoor, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param(
            'sssssssis',
            $name, $code, $address, $city,
            $province, $country, $password,
            $indoor, $status
        );

        if ($stmt->execute()) {
            $message = 'Ground added.';
        } else {
            $message = 'Database error.';
        }
        $stmt->close();
    }
}
?>

<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Add Ground</h1>

<div class="form-card">
    <?php if ($message): ?><p><?= htmlspecialchars($message) ?></p><?php endif; ?>

    <form method="POST">
        <?= Csrf::field(); ?>

        <input name="name" required placeholder="Ground Name">
        <input name="code" placeholder="Code">
        <input name="address" placeholder="Address">
        <input name="city" placeholder="City">
        <input name="province" placeholder="Province">
        <input name="country" value="Canada">
        <input name="password" placeholder="Attendance Password">
        <label><input type="checkbox" name="indoor"> Indoor</label>

        <select name="status">
            <option value="active">active</option>
            <option value="disabled">disabled</option>
        </select>

        <button class="button-primary">Save Ground</button>
    </form>
</div>

<?php include "includes/footer.php"; ?>
