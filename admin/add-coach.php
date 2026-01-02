<?php
require_once __DIR__ . '/_bootstrap.php';

AdminGuard::requirePermission(Permissions::COACHES_MANAGE);

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $coach_code = trim($_POST['coach_code'] ?? '');
    $name       = trim($_POST['name'] ?? '');
    $phone      = trim($_POST['phone'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $spec       = $_POST['specialization'] ?? '';
    $status     = $_POST['status'] ?? 'active';

    if ($name === '' || $email === '') {
        $message = 'Name and email required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Invalid email.';
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO coaches
             (coach_code, name, phone, email, specialization, status)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param('ssssss', $coach_code, $name, $phone, $email, $spec, $status);

        if ($stmt->execute()) {
            $coach_id = $conn->insert_id;
            $temp = bin2hex(random_bytes(8));
            $hash = password_hash($temp, PASSWORD_DEFAULT);

            $u = $conn->prepare(
                "INSERT INTO users
                (username, password_hash, role, coach_id, status, must_change_password)
                VALUES (?, ?, 'coach', ?, 'active', 1)"
            );
            $u->bind_param('ssi', $email, $hash, $coach_id);
            $u->execute();
            $u->close();

            $message = "Coach created. Temp password: ".htmlspecialchars($temp);
        } else {
            $message = 'Database error.';
        }
        $stmt->close();
    }
}
?>

<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Add Coach</h1>

<div class="form-card">
    <?php if ($message): ?><p><?= $message ?></p><?php endif; ?>

    <form method="POST">
        <?= Csrf::field(); ?>

        <input name="coach_code" placeholder="Coach Code">
        <input name="name" required placeholder="Name">
        <input name="phone" placeholder="Phone">
        <input na
