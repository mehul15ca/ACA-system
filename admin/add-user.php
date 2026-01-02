<?php
require_once __DIR__ . '/_bootstrap.php';

AdminGuard::requireRole(['superadmin']);

$message = '';

$coaches = $conn->query("SELECT id, name FROM coaches ORDER BY name ASC");
$students = $conn->query("SELECT id, first_name, last_name FROM students ORDER BY first_name ASC");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username   = trim($_POST['username'] ?? '');
    $role       = $_POST['role'] ?? '';
    $status     = $_POST['status'] ?? 'active';
    $coach_id   = !empty($_POST['coach_id']) ? (int)$_POST['coach_id'] : null;
    $student_id = !empty($_POST['student_id']) ? (int)$_POST['student_id'] : null;

    if ($username === '') {
        $message = 'Username required.';
    } elseif (!in_array($role, ['admin','coach','student'], true)) {
        $message = 'Invalid role.';
    } else {
        $temp = bin2hex(random_bytes(8));
        $hash = password_hash($temp, PASSWORD_DEFAULT);

        $stmt = $conn->prepare(
            "INSERT INTO users
             (username, password_hash, role, coach_id, student_id, status, must_change_password)
             VALUES (?, ?, ?, ?, ?, ?, 1)"
        );
        $stmt->bind_param(
            'sssiss',
            $username,
            $hash,
            $role,
            $coach_id,
            $student_id,
            $status
        );

        if ($stmt->execute()) {
            $message = 'User created. Temp password: ' . htmlspecialchars($temp);
        } else {
            $message = 'Database error.';
        }
        $stmt->close();
    }
}
?>

<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Add User</h1>

<div class="form-card">
    <?php if ($message): ?>
        <p><?php echo $message; ?></p>
    <?php endif; ?>

    <form method="POST">
        <?= Csrf::field(); ?>

        <input name="username" placeholder="Username (email recommended)" required>

        <select name="role">
            <option value="admin">Admin</option>
            <option value="coach">Coach</option>
            <option value="student">Student</option>
        </select>

        <select name="coach_id">
            <option value="">-- Link Coach (optional) --</option>
            <?php if ($coaches): while ($c = $coaches->fetch_assoc()): ?>
                <option value="<?php echo (int)$c['id']; ?>">
                    <?php echo htmlspecialchars($c['name']); ?>
                </option>
            <?php endwhile; endif; ?>
        </select>

        <select name="student_id">
            <option value="">-- Link Student (optional) --</option>
            <?php if ($students): while ($s = $students->fetch_assoc()): ?>
                <option value="<?php echo (int)$s['id']; ?>">
                    <?php echo htmlspecialchars($s['first_name'].' '.$s['last_name']); ?>
                </option>
            <?php endwhile; endif; ?>
        </select>

        <select name="status">
            <option value="active">active</option>
            <option value="disabled">disabled</option>
        </select>

        <button class="button-primary">Create User</button>
    </form>
</div>

<?php include "includes/footer.php"; ?>
