<?php
include "../config.php";
checkLogin();

$role = currentUserRole();
if (!in_array($role, ['admin','superadmin'])) {
    http_response_code(403);
    echo "Access denied. Admin/Superadmin only.";
    exit;
}

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name']);
    $code     = trim($_POST['code']);
    $address  = trim($_POST['address']);
    $city     = trim($_POST['city']);
    $province = trim($_POST['province']);
    $country  = trim($_POST['country']);
    $password = trim($_POST['password']); // plain text as per decision
    $indoor   = isset($_POST['indoor']) ? 1 : 0;
    $status   = $_POST['status'];

    if ($name === "") {
        $message = "Name is required.";
    } else {
        $stmt = $conn->prepare("
            INSERT INTO grounds (name, code, address, city, province, country, password, indoor, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            "sssssssis",
            $name,
            $code,
            $address,
            $city,
            $province,
            $country,
            $password,
            $indoor,
            $status
        );

        if ($stmt->execute()) {
            $message = "Ground added successfully.";
        } else {
            $message = "Error: " . $conn->error;
        }
    }
}
?>
<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Add Ground / Location</h1>

<div class="form-card">
    <?php if ($message): ?>
        <p style="margin-bottom:10px;"><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>

    <form method="POST">
        <div class="form-row">
            <label>Ground Name</label>
            <input type="text" name="name" required>
        </div>

        <div class="form-row">
            <label>Code (short identifier, optional)</label>
            <input type="text" name="code">
        </div>

        <div class="form-row">
            <label>Address</label>
            <input type="text" name="address">
        </div>

        <div class="form-row">
            <label>City</label>
            <input type="text" name="city">
        </div>

        <div class="form-row">
            <label>Province</label>
            <input type="text" name="province">
        </div>

        <div class="form-row">
            <label>Country</label>
            <input type="text" name="country" value="Canada">
        </div>

        <div class="form-row">
            <label>Attendance Login Password (plain text)</label>
            <input type="text" name="password">
        </div>

        <div class="form-row">
            <label>
                <input type="checkbox" name="indoor" value="1">
                Indoor facility
            </label>
        </div>

        <div class="form-row">
            <label>Status</label>
            <select name="status">
                <option value="active">active</option>
                <option value="disabled">disabled</option>
            </select>
        </div>

        <button type="submit" class="button-primary">Save Ground</button>
    </form>
</div>

<p style="margin-top:10px;">
    <a class="text-link" href="grounds.php">⬅ Back to Grounds</a>
</p>

<?php include "includes/footer.php"; ?>
