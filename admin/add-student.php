<?php
include "../config.php";
checkLogin();
$role = currentUserRole();
if (!in_array($role, ['admin','superadmin'])) {
    http_response_code(403);
    echo "Access denied.";
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$errors = [];
$success = "";

// Load batches for dropdown
$batches = [];
$resB = $conn->query("SELECT id, name FROM batches WHERE status = 'active' ORDER BY name");
if ($resB) {
    while ($row = $resB->fetch_assoc()) {
        $batches[] = $row;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $admission_no = trim($_POST['admission_no'] ?? '');
    $first_name   = trim($_POST['first_name'] ?? '');
    $last_name    = trim($_POST['last_name'] ?? '');
    $dob          = trim($_POST['dob'] ?? '');
    $parent_name  = trim($_POST['parent_name'] ?? '');
    $phone        = trim($_POST['phone'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $parent_email = trim($_POST['parent_email'] ?? '');
    $address      = trim($_POST['address'] ?? '');
    $emg_name     = trim($_POST['emergency_contact_name'] ?? '');
    $emg_rel      = trim($_POST['emergency_contact_relation'] ?? '');
    $emg_phone    = trim($_POST['emergency_contact_phone'] ?? '');
    $batch_id     = isset($_POST['batch_id']) ? (int)$_POST['batch_id'] : null;
    $join_date    = trim($_POST['join_date'] ?? '');
    $status       = trim($_POST['status'] ?? 'active');

    if ($admission_no === '') $errors[] = "Admission number is required.";
    if ($first_name === '') $errors[] = "First name is required.";
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Student email is invalid.";
    }
    if ($parent_email !== '' && !filter_var($parent_email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Parent email is invalid.";
    }

    if (!$errors) {
        $sql = "INSERT INTO students (
                    admission_no, first_name, last_name, dob,
                    parent_name, phone, email, parent_email,
                    address, emergency_contact_name, emergency_contact_relation,
                    emergency_contact_phone, batch_id, join_date, status
                )
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "ssssssssssssiss",
            $admission_no,
            $first_name,
            $last_name,
            $dob,
            $parent_name,
            $phone,
            $email,
            $parent_email,
            $address,
            $emg_name,
            $emg_rel,
            $emg_phone,
            $batch_id,
            $join_date,
            $status
        );

        if ($stmt->execute()) {
            $success = "Student added successfully.";
        } else {
            $errors[] = "Database error while adding student.";
        }
    }
}
?>
<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Add Student</h1>

<div class="card">
    <?php if ($errors): ?>
        <div class="alert-error">
            <?php foreach ($errors as $e): ?>
                <div><?php echo htmlspecialchars($e); ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-grid">
            <div class="form-group">
                <label>Admission No</label>
                <input type="text" name="admission_no"
                       value="<?php echo htmlspecialchars($_POST['admission_no'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label>First Name</label>
                <input type="text" name="first_name"
                       value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label>Last Name</label>
                <input type="text" name="last_name"
                       value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label>Date of Birth</label>
                <input type="date" name="dob"
                       value="<?php echo htmlspecialchars($_POST['dob'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label>Parent Name</label>
                <input type="text" name="parent_name"
                       value="<?php echo htmlspecialchars($_POST['parent_name'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label>Student Email</label>
                <input type="email" name="email"
                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label>Parent Email (optional)</label>
                <input type="email" name="parent_email"
                       value="<?php echo htmlspecialchars($_POST['parent_email'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label>Phone</label>
                <input type="text" name="phone"
                       value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label>Address</label>
                <input type="text" name="address"
                       value="<?php echo htmlspecialchars($_POST['address'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label>Emergency Contact Name</label>
                <input type="text" name="emergency_contact_name"
                       value="<?php echo htmlspecialchars($_POST['emergency_contact_name'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label>Emergency Contact Relation</label>
                <input type="text" name="emergency_contact_relation"
                       value="<?php echo htmlspecialchars($_POST['emergency_contact_relation'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label>Emergency Contact Phone</label>
                <input type="text" name="emergency_contact_phone"
                       value="<?php echo htmlspecialchars($_POST['emergency_contact_phone'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label>Batch</label>
                <select name="batch_id">
                    <option value="">-- Select Batch --</option>
                    <?php foreach ($batches as $b): ?>
                        <option value="<?php echo (int)$b['id']; ?>"
                            <?php echo (isset($_POST['batch_id']) && (int)$_POST['batch_id'] === (int)$b['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($b['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Join Date</label>
                <input type="date" name="join_date"
                       value="<?php echo htmlspecialchars($_POST['join_date'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    <?php
                    $st = $_POST['status'] ?? 'active';
                    ?>
                    <option value="active"<?php echo $st === 'active' ? ' selected' : ''; ?>>Active</option>
                    <option value="inactive"<?php echo $st === 'inactive' ? ' selected' : ''; ?>>Inactive</option>
                </select>
            </div>
        </div>

        <div style="margin-top:12px;">
            <button type="submit" class="button-primary">Save Student</button>
            <a href="students.php" class="button-secondary" style="margin-left:8px;">Back</a>
        </div>
    </form>
</div>

<?php include "includes/footer.php"; ?>
