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
$message = "";

// Fetch existing ground
$stmt = $conn->prepare("SELECT * FROM grounds WHERE id = ?");
$stmt->bind_param("i", $ground_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
    die("Ground not found.");
}
$ground = $res->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name']);
    $code     = trim($_POST['code']);
    $address  = trim($_POST['address']);
    $city     = trim($_POST['city']);
    $province = trim($_POST['province']);
    $country  = trim($_POST['country']);
    $password = trim($_POST['password']);
    $indoor   = isset($_POST['indoor']) ? 1 : 0;
    $status   = $_POST['status'];

    if ($name === "") {
        $message = "Name is required.";
    } else {
        $up = $conn->prepare("
            UPDATE grounds
            SET name = ?, code = ?, address = ?, city = ?, province = ?, country = ?, password = ?, indoor = ?, status = ?
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
            $password,
            $indoor,
            $status,
            $ground_id
        );

        if ($up->execute()) {
            $message = "Ground updated successfully.";
            // refresh array
            $ground['name']     = $name;
            $ground['code']     = $code;
            $ground['address']  = $address;
            $ground['city']     = $city;
            $ground['province'] = $province;
            $ground['country']  = $country;
            $ground['password'] = $password;
            $ground['indoor']   = $indoor;
            $ground['status']   = $status;
        } else {
            $message = "Error: " . $conn->error;
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
            <input type="text" name="password" value="<?php echo htmlspecialchars($ground['password']); ?>">
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
