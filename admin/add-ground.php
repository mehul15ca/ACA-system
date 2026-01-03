<?php
require_once __DIR__ . '/_bootstrap.php';

AdminGuard::requirePermission(Permissions::GROUNDS_MANAGE);

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::validateRequest();

    $name     = trim($_POST['name'] ?? '');
    $code     = trim($_POST['code'] ?? '');
    $address  = trim($_POST['address'] ?? '');
    $city     = trim($_POST['city'] ?? '');
    $province = trim($_POST['province'] ?? '');
    $country  = trim($_POST['country'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $indoor   = isset($_POST['indoor']) ? 1 : 0;
    $status   = ($_POST['status'] ?? 'active') === 'disabled' ? 'disabled' : 'active';

    if ($name === '') $errors[] = 'Ground name is required.';

    if (!$errors) {
        $stmt = $conn->prepare("
            INSERT INTO grounds (name, code, address, city, province, country, password, indoor, status)
            VALUES (?,?,?,?,?,?,?,?,?)
        ");
        $stmt->bind_param("sssssssis", $name, $code, $address, $city, $province, $country, $password, $indoor, $status);

        if ($stmt->execute()) {
            header("Location: grounds.php?created=1");
            exit;
        }
        $errors[] = 'Database error: ' . htmlspecialchars($conn->error);
        $stmt->close();
    }
}
?>

<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Add Ground / Location</h1>

<div class="form-card">
    <?php foreach ($errors as $e): ?>
        <div class="alert-error"><?php echo htmlspecialchars($e); ?></div>
    <?php endforeach; ?>

    <form method="POST">
        <?php echo Csrf::field(); ?>

        <div class="form-row">
            <label>Ground Name</label>
            <input type="text" name="name" required value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
        </div>

        <div class="form-row">
            <label>Code</label>
            <input type="text" name="code" value="<?php echo htmlspecialchars($_POST['code'] ?? ''); ?>">
        </div>

        <div class="form-row">
            <label>Address</label>
            <input type="text" name="address" value="<?php echo htmlspecialchars($_POST['address'] ?? ''); ?>">
        </div>

        <div class="form-row">
            <label>City</label>
            <input type="text" name="city" value="<?php echo htmlspecialchars($_POST['city'] ?? ''); ?>">
        </div>

        <div class="form-row">
            <label>Province</label>
            <input type="text" name="province" value="<?php echo htmlspecialchars($_POST['province'] ?? ''); ?>">
        </div>

        <div class="form-row">
            <label>Country</label>
            <input type="text" name="country" value="<?php echo htmlspecialchars($_POST['country'] ?? ''); ?>">
        </div>

        <div class="form-row">
            <label>Attendance Login Password</label>
            <input type="text" name="password" value="<?php echo htmlspecialchars($_POST['password'] ?? ''); ?>">
        </div>

        <div class="form-row">
            <label>
                <input type="checkbox" name="indoor" value="1" <?php echo !empty($_POST['indoor']) ? 'checked' : ''; ?>>
                Indoor facility
            </label>
        </div>

        <div class="form-row">
            <label>Status</label>
            <?php $st = $_POST['status'] ?? 'active'; ?>
            <select name="status">
                <option value="active"   <?php echo $st === 'active' ? 'selected' : ''; ?>>active</option>
                <option value="disabled" <?php echo $st === 'disabled' ? 'selected' : ''; ?>>disabled</option>
            </select>
        </div>

        <button type="submit" class="button-primary">Save</button>
        <a href="grounds.php" class="button">Back</a>
    </form>
</div>

<?php include "includes/footer.php"; ?>
