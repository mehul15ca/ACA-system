<?php
include "../config.php";
checkLogin();

$role = currentUserRole();
if (!in_array($role, ['admin','superadmin'])) {
    http_response_code(403);
    echo "Access denied. Admin/Superadmin only.";
    exit;
}

csrfInit();

if (!isset($_GET['id'])) die("Ground ID missing.");
$ground_id = intval($_GET['id']);
$message = "";

// Fetch existing ground
$stmt = $conn->prepare("SELECT * FROM grounds WHERE id = ?");
$stmt->bind_param("i", $ground_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) die("Ground not found.");
$ground = $res->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $name     = trim($_POST['name'] ?? '');
    $code     = trim($_POST['code'] ?? '');
    $address  = trim($_POST['address'] ?? '');
    $city     = trim($_POST['city'] ?? '');
    $province = trim($_POST['province'] ?? '');
    $country  = trim($_POST['country'] ?? '');
    $password = trim($_POST['password'] ?? ''); // plain input
    $indoor   = isset($_POST['indoor']) ? 1 : 0;
    $status   = $_POST['status'] ?? 'active';

    if ($name === "") {
        $message = "Name is required.";
    } else {
        // Hash password ONLY if user entered a new one, otherwise keep existing hash
        $pwd_sql = $ground['password'];
        if ($password !== '') {
            $pwd_sql = password_hash($password, PASSWORD_DEFAULT);
        }

        $up = $conn->prepare("
            UPDATE grounds
            SET name = ?, code = ?, address = ?, city = ?, province = ?, country = ?,
                password = ?, indoor = ?, status = ?
            WHERE id = ?
        ");
        $up->bind_param(
            "sssssssisi",
            $name,
            $code,
            $address,
            $city,
            $province,
            $country,
            $pwd_sql,
            $indoor,
            $status,
            $ground_id
        );

        if ($up->execute()) {
            $message = "Ground updated successfully.";
            // refresh view state (do not store plaintext)
            $ground['name']     = $name;
            $ground['code']     = $code;
            $ground['address']  = $address;
            $ground['city']     = $city;
            $ground['province'] = $province;
            $ground['country']  = $country;
            $ground['password'] = $pwd_sql;
            $ground['indoor']   = $indoor;
            $ground['status']   = $status;
        } else {
            $message = "Error: " . $up->error;
        }
    }
}
?>
<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Edit Ground / Location</h1>

<div class="form-card">
    <?php if ($message): ?>
        <p style="margin-bottom:10px;"><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">

        <div class="form-row">
            <label>Ground Name</label>
            <input type="text" name="name" value="<?php echo htmlspecialchars($ground['name']); ?>" required>
        </div>

        <div class="form-row">
            <label>Code</label>
            <input type="text" name="code" value="<?php echo htmlspecialchars($ground['code']); ?>">
        </div>

        <div class="form-row">
            <label>Address</label>
            <input type="text" name="address" value="<?php echo htmlspecialchars($ground['address']); ?>">
        </div>

        <div class="form-row">
            <label>City</label>
            <input type="text" name="city" value="<?php echo htmlspecialchars($ground['city']); ?>">
        </div>

        <div class="form-row">
            <label>Province</label>
            <input type="text" name="province" value="<?php echo htmlspecialchars($ground['province']); ?>">
        </div>

        <div class="form-row">
            <label>Country</label>
            <input type="text" name="country" value="<?php echo htmlspecialchars($ground['country']); ?>">
        </div>

        <div class="form-row">
            <label>Attendance Login Password</label>
            <input type="text" name="password" value="" placeholder="Leave blank to keep current password">
            <small style="color:#6b7280;">Password is stored securely (hashed). Leaving blank keeps the current one.</small>
        </div>

        <div class="form-row">
            <label>
                <input type="checkbox" name="indoor" value="1" <?php if ((int)$ground['indoor'] === 1) echo 'checked'; ?>>
                Indoor facility
            </label>
        </div>

        <div class="form-row">
            <label>Status</label>
            <select name="status">
                <option value="active" <?php if ($ground['status'] === 'active') echo 'selected'; ?>>active</option>
                <option value="disabled" <?php if ($ground['status'] === 'disabled') echo 'selected'; ?>>disabled</option>
            </select>
        </div>

        <button type="submit" class="button-primary">Save Changes</button>
    </form>
</div>

<p style="margin-top:10px;">
    <a class="text-link" href="grounds.php">⬅ Back to Grounds</a>
</p>

<?php include "includes/footer.php"; ?>
