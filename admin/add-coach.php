<?php
require_once __DIR__ . '/_bootstrap.php';

AdminGuard::requirePermission(Permissions::COACHES_MANAGE);

$message = '';
$errors = [];

$spec_options = ["", "Batting", "Bowling", "All-rounder", "Fielding", "Wicket-keeping", "Fitness"];

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
        $stmt = $conn->prepare("
            INSERT INTO coaches (coach_code, name, phone, email, specialization, status)
            VALUES (?,?,?,?,?,?)
        ");
        $stmt->bind_param("ssssss", $coach_code, $name, $phone, $email, $spec, $status);

        if ($stmt->execute()) {
            header("Location: coaches.php?created=1");
            exit;
        }
        $errors[] = 'Database error: ' . htmlspecialchars($conn->error);
        $stmt->close();
    }
}
?>

<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Add Coach</h1>

<div class="form-card">
    <?php foreach ($errors as $e): ?>
        <div class="alert-error"><?php echo htmlspecialchars($e); ?></div>
    <?php endforeach; ?>

    <form method="POST">
        <?php echo Csrf::field(); ?>

        <div class="form-row">
            <label>Coach Code</label>
            <input type="text" name="coach_code" value="<?php echo htmlspecialchars($_POST['coach_code'] ?? ''); ?>">
        </div>

        <div class="form-row">
            <label>Name</label>
            <input type="text" name="name" required value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
        </div>

        <div class="form-row">
            <label>Phone</label>
            <input type="text" name="phone" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
        </div>

        <div class="form-row">
            <label>Email</label>
            <input type="email" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
        </div>

        <div class="form-row">
            <label>Specialization</label>
            <select name="specialization">
                <?php $sel = $_POST['specialization'] ?? ''; ?>
                <?php foreach ($spec_options as $opt): ?>
                    <option value="<?php echo htmlspecialchars($opt); ?>" <?php echo $sel === $opt ? 'selected' : ''; ?>>
                        <?php echo $opt === '' ? '-- Select --' : htmlspecialchars($opt); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-row">
            <label>Status</label>
            <?php $st = $_POST['status'] ?? 'active'; ?>
            <select name="status">
                <option value="active"   <?php echo $st === 'active' ? 'selected' : ''; ?>>active</option>
                <option value="disabled" <?php echo $st === 'disabled' ? 'selected' : ''; ?>>disabled</option>
            </select>
        </div>

        <button type="submit" class="button-primary">Save</button>
        <a href="coaches.php" class="button">Back</a>
    </form>
</div>

<?php include "includes/footer.php"; ?>
