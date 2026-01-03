<?php
require_once __DIR__ . '/_bootstrap.php';

requireSuperadmin();

if (!isset($_GET['id'])) {
    die("User ID missing.");
}
$user_id = intval($_GET['id']);
$message = "";

// Fetch user
$stmt = $conn->prepare("SELECT id, username, role, status, coach_id, student_id FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
    die("User not found.");
}
$user = $res->fetch_assoc();

// Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username   = trim($_POST['username']);
    $role       = $_POST['role'];
    $status     = $_POST['status'];
    $coach_id   = !empty($_POST['coach_id']) ? intval($_POST['coach_id']) : null;
    $student_id = !empty($_POST['student_id']) ? intval($_POST['student_id']) : null;

    if ($username === "") {
        $message = "Username is required.";
    } else {
        $up = $conn->prepare("UPDATE users SET username = ?, role = ?, status = ?, coach_id = ?, student_id = ? WHERE id = ?");
        $up->bind_param("sssisi", $username, $role, $status, $coach_id, $student_id, $user_id);
        if ($up->execute()) {
            $message = "User updated successfully.";
            // refresh user data
            $user['username'] = $username;
            $user['role'] = $role;
            $user['status'] = $status;
            $user['coach_id'] = $coach_id;
            $user['student_id'] = $student_id;
        } else {
            $message = "Error: " . $conn->error;
        }
    }
}

// Fetch coaches and students
$coaches = $conn->query("SELECT id, coach_code, name FROM coaches ORDER BY name ASC");
$students = $conn->query("SELECT id, admission_no, first_name, last_name FROM students ORDER BY first_name ASC");
?>
<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Edit User</h1>

<div class="form-card">
    <?php if ($message): ?>
        <p style="margin-bottom:10px;"><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>

    <form method="POST">
        <?php echo Csrf::field(); ?>

        <div class="form-row">
            <label>Username</label>
            <input type="text" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
        </div>

        <div class="form-row">
            <label>Role</label>
            <select name="role">
                <option value="superadmin" <?php if ($user['role'] === 'superadmin') echo 'selected'; ?>>Superadmin</option>
                <option value="admin" <?php if ($user['role'] === 'admin') echo 'selected'; ?>>Admin</option>
                <option value="coach" <?php if ($user['role'] === 'coach') echo 'selected'; ?>>Coach</option>
                <option value="student" <?php if ($user['role'] === 'student') echo 'selected'; ?>>Student</option>
            </select>
        </div>

        <div class="form-row">
            <label>Status</label>
            <select name="status">
                <option value="active" <?php if ($user['status'] === 'active') echo 'selected'; ?>>active</option>
                <option value="disabled" <?php if ($user['status'] === 'disabled') echo 'selected'; ?>>disabled</option>
            </select>
        </div>

        <div class="form-row">
            <label>Link to Coach</label>
            <select name="coach_id">
                <option value="">-- None --</option>
                <?php while ($c = $coaches->fetch_assoc()): ?>
                    <option value="<?php echo $c['id']; ?>" <?php if ($user['coach_id'] == $c['id']) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($c['name'] . " (" . $c['coach_code'] . ")"); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="form-row">
            <label>Link to Student</label>
            <select name="student_id">
                <option value="">-- None --</option>
                <?php while ($s = $students->fetch_assoc()): ?>
                    <option value="<?php echo $s['id']; ?>" <?php if ($user['student_id'] == $s['id']) echo 'selected'; ?>>
                        <?php
                        $full = trim($s['first_name'] . " " . $s['last_name']);
                        echo htmlspecialchars($full . " (" . $s['admission_no'] . ")");
                        ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <button type="submit" class="button-primary">Save Changes</button>
    </form>
</div>

<p style="margin-top:10px;">
    <a class="text-link" href="users.php">⬅ Back to Users</a>
</p>

<?php include "includes/footer.php"; ?>
