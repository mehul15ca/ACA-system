<?php
require_once __DIR__ . '/_bootstrap.php';

AdminGuard::requirePermission(Permissions::GROUNDS_MANAGE);

$ground_id = (int)($_GET['id'] ?? 0);
if ($ground_id <= 0) {
    http_response_code(400);
    echo "Invalid ground id.";
    exit;
}

$errors = [];
$success = "";

// Load ground
$stmt = $conn->prepare("SELECT * FROM grounds WHERE id = ?");
$stmt->bind_param("i", $ground_id);
$stmt->execute();
$ground = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$ground) {
    http_response_code(404);
    echo "Ground not found.";
    exit;
}

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

    if ($name === '') $errors[] = 'Name is required.';

    if (!$errors) {
        $up = $conn->prepare("
            UPDATE grounds
            SET name = ?, code = ?, address = ?, city = ?, province = ?, country = ?, password = ?, indoor = ?, status = ?
            WHERE id = ?
        ");
        $up->bind_param("sssssssisi", $name, $code, $address, $city, $province, $country, $password, $indoor, $status, $ground_id);

        if ($up->execute()) {
            $success = "Ground updated successfully.";
            $ground['name']=$name; $ground['code']=$code; $ground['address']=$address; $ground['city']=$city;
            $ground['province']=$province; $ground['country']=$country; $ground['password']=$password; $ground['indoor']=$indoor; $ground['status']=$status;
        } else {
            $errors[] = 'Database error: ' . htmlspecialchars($conn->error);
        }
        $up->close();
    }
}
?>

<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Edit Ground / Location</h1>

<div class="form-card">
    <?php if ($success): ?><div class="alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
    <?php foreach ($errors as $e): ?><div class="alert-error"><?php echo htmlspecialchars($e); ?></div><?php endforeach; ?>

    <form method="POST">
        <?php echo Csrf::field(); ?>

        <div class="form-row">
            <label>Ground Name</label>
            <input type="text" name="name" required value="<?php echo htmlspecialchars($ground['name'] ?? ''); ?>">
        </div>

        <div class="form-row">
            <label>Code</label>
            <input type="text" name="code" value="<?php echo htmlspecialchars($ground['code'] ?? ''); ?>">
        </div>

        <div class="form-row">
            <label>Address</label>
            <input type="text" name="address" value="<?php echo htmlspecialchars($ground['address'] ?? ''); ?>">
        </div>

        <div class="form-row">
            <label>City</label>
            <input type="text" name="city" value="<?php echo htmlspecialchars($ground['city'] ?? ''); ?>">
        </div>

        <div class="form-row">
            <label>Province</label>
            <input type="text" name="province" value="<?php echo htmlspecialchars($ground['province'] ?? ''); ?>">
        </div>

        <div class="form-row">
            <label>Country</label>
            <input type="text" name="country" value="<?php echo htmlspecialchars($ground['country'] ?? ''); ?>">
        </div>

        <div class="form-row">
            <label>Attendance Login Password</label>
            <input type="text" name="password" value="<?php echo htmlspecialchars($ground['password'] ?? ''); ?>">
        </div>

        <div class="form-row">
            <label>
                <input type="checkbox" name="indoor" value="1" <?php echo ((int)($ground['indoor'] ?? 0) === 1) ? 'checked' : ''; ?>>
                Indoor facility
            </label>
        </div>

        <div class="form-row">
            <label>Status</label>
            <select name="status">
                <option value="active"   <?php if (($ground['status'] ?? '') === 'active') echo 'selected'; ?>>active</option>
                <option value="disabled" <?php if (($ground['status'] ?? '') === 'disabled') echo 'selected'; ?>>disabled</option>
            </select>
        </div>

        <button type="submit" class="button-primary">Save Changes</button>
        <a href="grounds.php" class="button">Back</a>
    </form>
</div>

<?php include "includes/footer.php"; ?>
