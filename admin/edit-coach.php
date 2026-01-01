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
    die("Coach ID missing.");
}
$coach_id = intval($_GET['id']);
$message = "";

// Fetch coach
$stmt = $conn->prepare("SELECT * FROM coaches WHERE id = ?");
$stmt->bind_param("i", $coach_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
    die("Coach not found.");
}
$coach = $res->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $coach_code    = trim($_POST['coach_code']);
    $name          = trim($_POST['name']);
    $phone         = trim($_POST['phone']);
    $email         = trim($_POST['email']);
    $specialization = $_POST['specialization'];
    $status        = $_POST['status'];

    if ($name === "") {
        $message = "Name is required.";
    } elseif ($email === "") {
        $message = "Email is required.";
    } else {
        $up = $conn->prepare("
            UPDATE coaches
            SET coach_code = ?, name = ?, phone = ?, email = ?, specialization = ?, status = ?
            WHERE id = ?
        ");
        $up->bind_param(
            "ssssssi",
            $coach_code,
            $name,
            $phone,
            $email,
            $specialization,
            $status,
            $coach_id
        );

        if ($up->execute()) {
            // If there is a linked user for this coach, update username and status to match
            $user_up = $conn->prepare("
                UPDATE users
                SET username = ?, status = ?
                WHERE coach_id = ?
            ");
            $user_up->bind_param(
                "ssi",
                $email,
                $status,
                $coach_id
            );
            $user_up->execute();

            $message = "Coach updated successfully.";
            $coach['coach_code']    = $coach_code;
            $coach['name']          = $name;
            $coach['phone']         = $phone;
            $coach['email']         = $email;
            $coach['specialization'] = $specialization;
            $coach['status']        = $status;
        } else {
            $message = "Error updating coach: " . $conn->error;
        }
    }
}
?>
<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Edit Coach</h1>

<div class="form-card">
    <?php if ($message): ?>
        <p style="margin-bottom:10px;"><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>

    <form method="POST">
        <div class="form-row">
            <label>Coach Code</label>
            <input type="text" name="coach_code" value="<?php echo htmlspecialchars($coach['coach_code']); ?>">
        </div>

        <div class="form-row">
            <label>Name</label>
            <input type="text" name="name" value="<?php echo htmlspecialchars($coach['name']); ?>" required>
        </div>

        <div class="form-row">
            <label>Phone</label>
            <input type="text" name="phone" value="<?php echo htmlspecialchars($coach['phone']); ?>">
        </div>

        <div class="form-row">
            <label>Email</label>
            <input type="email" name="email" value="<?php echo htmlspecialchars($coach['email']); ?>" required>
        </div>

        <div class="form-row">
            <label>Specialization</label>
            <select name="specialization">
                <?php
                $spec_options = ["", "Batting", "Bowling", "All-rounder", "Fielding", "Wicket-keeping", "Fitness"];
                foreach ($spec_options as $opt):
                    $sel = ($coach['specialization'] === $opt) ? 'selected' : '';
                ?>
                    <option value="<?php echo htmlspecialchars($opt); ?>" <?php echo $sel; ?>>
                        <?php echo $opt === "" ? "-- Select --" : htmlspecialchars($opt); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-row">
            <label>Status</label>
            <select name="status">
                <option value="active" <?php if ($coach['status'] === 'active') echo 'selected'; ?>>active</option>
                <option value="disabled" <?php if ($coach['status'] === 'disabled') echo 'selected'; ?>>disabled</option>
            </select>
        </div>

        <button type="submit" class="button-primary">Save Changes</button>
    </form>
</div>

<p style="margin-top:10px;">
    <a class="text-link" href="coaches.php">⬅ Back to Coaches</a>
</p>

<?php include "includes/footer.php"; ?>
