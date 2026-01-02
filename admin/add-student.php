<?php
require_once __DIR__ . '/_bootstrap.php';

AdminGuard::requirePermission(Permissions::STUDENTS_MANAGE);

$errors = [];
$success = '';

// Load batches
$batches = $conn->query(
    "SELECT id, name FROM batches
     WHERE status='active'
     ORDER BY name ASC"
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $admission_no = trim($_POST['admission_no'] ?? '');
    $first_name   = trim($_POST['first_name'] ?? '');
    $last_name    = trim($_POST['last_name'] ?? '');
    $dob          = $_POST['dob'] ?? null;
    $parent_name  = trim($_POST['parent_name'] ?? '');
    $phone        = trim($_POST['phone'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $parent_email = trim($_POST['parent_email'] ?? '');
    $address      = trim($_POST['address'] ?? '');
    $batch_id     = (int)($_POST['batch_id'] ?? 0);
    $join_date    = $_POST['join_date'] ?? null;
    $status       = $_POST['status'] ?? 'active';

    if ($admission_no === '') $errors[] = 'Admission number required.';
    if ($first_name === '') $errors[] = 'First name required.';
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid student email.';
    if ($parent_email && !filter_var($parent_email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid parent email.';

    if (!$errors) {
        $stmt = $conn->prepare(
            "INSERT INTO students
            (admission_no, first_name, last_name, dob,
             parent_name, phone, email, parent_email,
             address, batch_id, join_date, status)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)"
        );
        $stmt->bind_param(
            'ssssssssisss',
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
            $success = 'Student added successfully.';
        } else {
            $errors[] = 'Database error.';
        }
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
        <?= Csrf::field(); ?>

        <input name="admission_no" placeholder="Admission No" required>
        <input name="first_name" placeholder="First Name" required>
        <input name="last_name" placeholder="Last Name">
        <input type="date" name="dob">
        <input name="parent_name" placeholder="Parent Name">
        <input name="phone" placeholder="Phone">
        <input type="email" name="email" placeholder="Student Email">
        <input type="email" name="parent_email" placeholder="Parent Email">
        <input name="address" placeholder="Address">

        <select name="batch_id">
            <option value="">-- Select Batch --</option>
            <?php if ($batches): while ($b = $batches->fetch_assoc()): ?>
                <option value="<?php echo (int)$b['id']; ?>">
                    <?php echo htmlspecialchars($b['name']); ?>
                </option>
            <?php endwhile; endif; ?>
        </select>

        <input type="date" name="join_date">

        <select name="status">
            <option value="active">active</option>
            <option value="inactive">inactive</option>
        </select>

        <button class="button-primary">Save Student</button>
    </form>
</div>

<?php include "includes/footer.php"; ?>
