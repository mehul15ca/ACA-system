<?php
include "../config.php";
checkLogin();
requireSuperadmin();

$message = "";
$temp_password = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username   = trim($_POST['username']);
    $role       = $_POST['role'];
    $status     = $_POST['status'];
    $coach_id   = !empty($_POST['coach_id']) ? intval($_POST['coach_id']) : null;
    $student_id = !empty($_POST['student_id']) ? intval($_POST['student_id']) : null;

    if ($username === "") {
        $message = "Username is required.";
    } else {
        try {
            $temp_password = bin2hex(random_bytes(8)); // 16 chars
        } catch (Throwable $e) {
            http_response_code(500);
            exit("Password generation failed. Please retry.");
        }

        $hash = password_hash($temp_password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("
            INSERT INTO users
              (username, password_hash, role, coach_id, student_id, status, must_change_password)
            VALUES (?, ?, ?, ?, ?, ?, 1)
        ");
        $stmt->bind_param(
            "sssiss",
            $username,
            $hash,
            $role,
            $coach_id,
            $student_id,
            $status
        );

        if ($stmt->execute()) {
            $message = "User created successfully. Temporary password: " . htmlspecialchars($temp_password);
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

<h1>Add User</h1>

<div class="form-card">
    <?php if ($message): ?>
        <p style="margin-bottom:10px;"><?php echo $message; ?></p>
    <?php endif; ?>

    <form method="POST">
        <div class="form-row">
            <label>Username</label>
            <input type="text" name="username" required>
        </div>

        <div class="form-row">
            <label>Role</label>
            <select name="role" required>
                <option value="admin">Admin</option>
                <option value="coach">Coach</option>
                <option value="student">Student</option>
            </select>
        </div>

        <div class="form-row">
            <label>Link to Coach</label>
            <select name="coach_id">
                <option value="">-- None --</option>
                <?php while ($c = $coaches->fetch_assoc()): ?>
                    <option value="<?php echo $c['id']; ?>">
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
                    <option value="<?php echo $s['id']; ?>">
                        <?php
                        $full = trim($s['first_name'] . " " . $s['last_name']);
                        echo htmlspecialchars($full . " (" . $s['admission_no'] . ")");
                        ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="form-row">
            <label>Status</label>
            <select name="status">
                <option value="active">active</option>
                <option value="disabled">disabled</option>
            </select>
        </div>

        <button type="submit" class="button-primary">Create User</button>
    </form>

    <p style="margin-top:10px; font-size:12px;">
        User will be forced to change password at first login.
    </p>
</div>

<p style="margin-top:10px;">
    <a class="text-link" href="users.php">⬅ Back to Users</a>
</p>

<?php include "includes/footer.php"; ?>
