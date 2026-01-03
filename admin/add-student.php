<?php
require_once __DIR__ . '/_bootstrap.php';

AdminGuard::requirePermission(Permissions::STUDENTS_MANAGE);

$errors = [];
$success = '';

// Load batches
$batches = $conn->query("SELECT id, name FROM batches WHERE status='active' ORDER BY name ASC");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::validateRequest();

    $admission_no = trim($_POST['admission_no'] ?? '');
    $first_name   = trim($_POST['first_name'] ?? '');
    $last_name    = trim($_POST['last_name'] ?? '');
    $dob          = ($_POST['dob'] ?? '') !== '' ? $_POST['dob'] : null;
    $parent_name  = trim($_POST['parent_name'] ?? '');
    $phone        = trim($_POST['phone'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $parent_email = trim($_POST['parent_email'] ?? '');
    $address      = trim($_POST['address'] ?? '');
    $batch_id     = (int)($_POST['batch_id'] ?? 0);
    $join_date    = ($_POST['join_date'] ?? '') !== '' ? $_POST['join_date'] : null;
    $status       = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

    if ($admission_no === '') $errors[] = 'Admission number is required.';
    if ($first_name === '') $errors[] = 'First name is required.';
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Student email is invalid.';
    if ($parent_email !== '' && !filter_var($parent_email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Parent email is invalid.';

    if (!$errors) {
        $stmt = $conn->prepare(
            "INSERT INTO students
            (admission_no, first_name, last_name, dob,
             parent_name, phone, email, parent_email,
             address, batch_id, join_date, status)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)"
        );
        $stmt->bind_param(
            "ssssssssisss",
            $admission_no,
            $first_name,
            $last_name,
            $dob,
            $parent_name,
            $phone,
            $email,
            $parent_email,
            $address,
            $batch_id,
            $join_date,
            $status
        );

        if ($stmt->execute()) {
            header("Location: students.php?created=1");
            exit;
        }
        $errors[] = 'Database error: ' . htmlspecialchars($conn->error);
        $stmt->close();
    }
}
?>

<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Add Student</h1>

<div class="form-card">
    <?php foreach ($errors as $e): ?>
        <div class="alert-error"><?php echo htmlspecialchars($e); ?></div>
    <?php endforeach; ?>
    <?php if ($success): ?>
        <div class="alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <form method="POST">
        <?php echo Csrf::field(); ?>

        <div class="form-row">
            <label>Admission No</label>
            <input type="text" name="admission_no" required value="<?php echo htmlspecialchars($_POST['admission_no'] ?? ''); ?>">
        </div>

        <div class="form-row">
            <label>First Name</label>
            <input type="text" name="first_name" required value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>">
        </div>

        <div class="form-row">
            <label>Last Name</label>
            <input type="text" name="last_name" value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>">
        </div>

        <div class="form-row">
            <label>Date of Birth</label>
            <input type="date" name="dob" value="<?php echo htmlspecialchars($_POST['dob'] ?? ''); ?>">
        </div>

        <div class="form-row">
            <label>Parent Name</label>
            <input type="text" name="parent_name" value="<?php echo htmlspecialchars($_POST['parent_name'] ?? ''); ?>">
        </div>

        <div class="form-row">
            <label>Phone</label>
            <input type="text" name="phone" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
        </div>

        <div class="form-row">
            <label>Student Email</label>
            <input type="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
        </div>

        <div class="form-row">
            <label>Parent Email</label>
            <input type="email" name="parent_email" value="<?php echo htmlspecialchars($_POST['parent_email'] ?? ''); ?>">
        </div>

        <div class="form-row">
            <label>Address</label>
            <input type="text" name="address" value="<?php echo htmlspecialchars($_POST['address'] ?? ''); ?>">
        </div>

        <div class="form-row">
            <label>Batch</label>
            <select name="batch_id">
                <option value="">-- Select Batch --</option>
                <?php if ($batches): while ($b = $batches->fetch_assoc()): ?>
                    <option value="<?php echo (int)$b['id']; ?>" <?php echo (string)$b['id'] === (string)($_POST['batch_id'] ?? '') ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($b['name']); ?>
                    </option>
                <?php endwhile; endif; ?>
            </select>
        </div>

        <div class="form-row">
            <label>Join Date</label>
            <input type="date" name="join_date" value="<?php echo htmlspecialchars($_POST['join_date'] ?? ''); ?>">
        </div>

        <div class="form-row">
            <label>Status</label>
            <?php $st = $_POST['status'] ?? 'active'; ?>
            <select name="status">
                <option value="active"   <?php echo $st==='active'?'selected':''; ?>>active</option>
                <option value="inactive" <?php echo $st==='inactive'?'selected':''; ?>>inactive</option>
            </select>
        </div>

        <button type="submit" class="button-primary">Save Student</button>
        <a href="students.php" class="button">Back</a>
    </form>
</div>

<?php include "includes/footer.php"; ?>
