<?php
include "../config.php";
checkLogin();

$role = currentUserRole();
if (!in_array($role, ['admin','superadmin'], true)) {
    http_response_code(403);
    exit("Access denied.");
}

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $coach_code     = trim($_POST['coach_code']);
    $name           = trim($_POST['name']);
    $phone          = trim($_POST['phone']);
    $email          = trim($_POST['email']);
    $specialization = $_POST['specialization'];
    $status         = $_POST['status'];

    if ($name === "") {
        $message = "Name is required.";
    } elseif ($email === "") {
        $message = "Email is required.";
    } else {
        $stmt = $conn->prepare("
            INSERT INTO coaches (coach_code, name, phone, email, specialization, status)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("ssssss", $coach_code, $name, $phone, $email, $specialization, $status);

        if ($stmt->execute()) {
            $coach_id = $conn->insert_id;

            try {
                $temp_password = bin2hex(random_bytes(8)); // 16 chars
            } catch (Throwable $e) {
                http_response_code(500);
                exit("Password generation failed.");
            }

            $hash = password_hash($temp_password, PASSWORD_DEFAULT);

            $u = $conn->prepare("
                INSERT INTO users
                  (username, password_hash, role, coach_id, status, must_change_password)
                VALUES (?, ?, 'coach', ?, 'active', 1)
            ");
            $u->bind_param("ssi", $email, $hash, $coach_id);

            if ($u->execute()) {
                $message =
                    "Coach created. Username: " . htmlspecialchars($email) .
                    " | Temporary password: " . htmlspecialchars($temp_password);
            } else {
                $message = "Coach created, but login failed.";
            }
        } else {
            $message = "Error creating coach.";
        }
    }
}
?>
<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Add Coach</h1>

<div class="form-card">
    <?php if ($message): ?>
        <p><?php echo $message; ?></p>
    <?php endif; ?>

    <form method="POST">
        <div class="form-row">
            <label>Coach Code</label>
            <input type="text" name="coach_code">
        </div>

        <div class="form-row">
            <label>Name</label>
            <input type="text" name="name" required>
        </div>

        <div class="form-row">
            <label>Phone</label>
            <input type="text" name="phone">
        </div>

        <div class="form-row">
            <label>Email (login username)</label>
            <input type="email" name="email" required>
        </div>

        <div class="form-row">
            <label>Specialization</label>
            <select name="specialization">
                <option value="">-- Select --</option>
                <option value="Batting">Batting</option>
                <option value="Bowling">Bowling</option>
                <option value="All-rounder">All-rounder</option>
                <option value="Fielding">Fielding</option>
                <option value="Wicket-keeping">Wicket-keeping</option>
                <option value="Fitness">Fitness</option>
            </select>
        </div>

        <div class="form-row">
            <label>Status</label>
            <select name="status">
                <option value="active">active</option>
                <option value="disabled">disabled</option>
            </select>
        </div>

        <button type="submit" class="button-primary">Save Coach</button>
    </form>
</div>

<p style="margin-top:10px;">
    <a class="text-link" href="coaches.php">⬅ Back to Coaches</a>
</p>

<?php include "includes/footer.php"; ?>
