<?php
require_once __DIR__ . '/_bootstrap.php';

AdminGuard::requirePermission(Permissions::COACHES_MANAGE);

$coach_id = (int)($_GET['id'] ?? 0);
if ($coach_id <= 0) {
    http_response_code(400);
    echo "Invalid coach id.";
    exit;
}

$message = "";
$errors = [];

$spec_options = ["", "Batting", "Bowling", "All-rounder", "Fielding", "Wicket-keeping", "Fitness"];

// Load coach
$stmt = $conn->prepare("SELECT * FROM coaches WHERE id = ?");
$stmt->bind_param("i", $coach_id);
$stmt->execute();
$coach = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$coach) {
    http_response_code(404);
    echo "Coach not found.";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::validateRequest();

    $coach_code = trim($_POST['coach_code'] ?? '');
    $name       = trim($_POST['name'] ?? '');
    $phone      = trim($_POST['phone'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $spec       = $_POST['specialization'] ?? '';
    $status     = ($_POST['status'] ?? 'active') === 'disabled' ? 'disabled' : 'active';

    if ($name === '') $errors[] = 'Name is required.';
    if ($email === '') $errors[] = 'Email is required.';
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email format.';
    if (!in_array($spec, $spec_options, true)) $errors[] = 'Invalid specialization.';

    if (!$errors) {
        $up = $conn->prepare("
            UPDATE coaches
            SET coach_code = ?, name = ?, phone = ?, email = ?, specialization = ?, status = ?
            WHERE id = ?
        ");
        $up->bind_param("ssssssi", $coach_code, $name, $phone, $email, $spec, $status, $coach_id);

        if ($up->execute()) {
            // keep linked user (if any) in sync
            $u = $conn->prepare("UPDATE users SET username = ?, status = ? WHERE coach_id = ?");
            $u->bind_param("ssi", $email, $status, $coach_id);
            $u->execute();
            $u->close();

            $message = "Coach updated successfully.";
            $coach['coach_code'] = $coach_code;
            $coach['name'] = $name;
            $coach['phone'] = $phone;
            $coach['email'] = $email;
            $coach['specialization'] = $spec;
            $coach['status'] = $status;
        } else {
            $errors[] = 'Database error: ' . htmlspecialchars($conn->error);
        }
        $up->close();
    }
}
?>

<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Edit Coach</h1>

<div class="form-card">
    <?php if ($message): ?>
        <div class="alert-success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php foreach ($errors as $e): ?>
        <div class="alert-error"><?php echo htmlspecialchars($e); ?></div>
    <?php endforeach; ?>

    <form method="POST">
        <?php echo Csrf::field(); ?>

        <div class="form-row">
            <label>Coach Code</label>
            <input type="text" name="coach_code" value="<?php echo htmlspecialchars($coach['coach_code'] ?? ''); ?>">
        </div>

        <div class="form-row">
            <label>Name</label>
            <input type="text" name="name" required value="<?php echo htmlspecialchars($coach['name'] ?? ''); ?>">
        </div>

        <div class="form-row">
            <label>Phone</label>
            <input type="text" name="phone" value="<?php echo htmlspecialchars($coach['phone'] ?? ''); ?>">
        </div>

        <div class="form-row">
            <label>Email</label>
            <input type="email" name="email" required value="<?php echo htmlspecialchars($coach['email'] ?? ''); ?>">
        </div>

        <div class="form-row">
            <label>Specialization</label>
            <select name="specialization">
                <?php foreach ($spec_options as $opt): ?>
                    <option value="<?php echo htmlspecialchars($opt); ?>" <?php echo (($coach['specialization'] ?? '') === $opt) ? 'selected' : ''; ?>>
                        <?php echo $opt === '' ? '-- Select --' : htmlspecialchars($opt); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-row">
            <label>Status</label>
            <select name="status">
                <option value="active"   <?php if (($coach['status'] ?? '') === 'active') echo 'selected'; ?>>active</option>
                <option value="disabled" <?php if (($coach['status'] ?? '') === 'disabled') echo 'selected'; ?>>disabled</option>
            </select>
        </div>

        <button type="submit" class="button-primary">Save Changes</button>
        <a href="coaches.php" class="button">Back</a>
    </form>
</div>

<?php include "includes/footer.php"; ?>
