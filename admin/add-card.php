<?php
require_once __DIR__ . '/_bootstrap.php';

AdminGuard::requirePermission(Permissions::ATTENDANCE_VIEW);

$message = '';
$success = '';

$students = $conn->query(
    "SELECT id, admission_no, first_name, last_name
     FROM students WHERE status='active' ORDER BY first_name"
);

$coaches = $conn->query(
    "SELECT id, coach_code, name
     FROM coaches WHERE status='active' ORDER BY name"
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $card_number = trim($_POST['card_number'] ?? '');
    $uid         = trim($_POST['uid'] ?? '');
    $card_type   = $_POST['card_type'] ?? '';
    $owner_type  = $_POST['assigned_to_type'] ?? '';
    $owner_id    = (int)($_POST['assigned_to_id'] ?? 0);
    $issued_on   = $_POST['issued_on'] ?: null;
    $status      = $_POST['status'] ?? 'active';

    if ($card_number === '' || $uid === '') {
        $message = 'Card number and UID are required.';
    } elseif (!in_array($card_type, ['Student','Coach','Staff'], true)) {
        $message = 'Invalid card type.';
    } elseif (!in_array($owner_type, ['student','coach'], true)) {
        $message = 'Invalid owner type.';
    } elseif ($owner_id <= 0) {
        $message = 'Invalid owner.';
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO cards
            (card_number, uid, card_type, assigned_to_type, assigned_to_id, issued_on, status)
            VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param(
            'ssssiss',
            $card_number,
            $uid,
            $card_type,
            $owner_type,
            $owner_id,
            $issued_on,
            $status
        );

        if ($stmt->execute()) {
            $success = 'Card added successfully.';
        } else {
            $message = 'Database error.';
        }
        $stmt->close();
    }
}
?>

<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Add Card</h1>

<div class="form-card">
    <?php if ($message): ?><div class="alert-error"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

    <form method="POST">
        <?= Csrf::field(); ?>

        <div class="form-grid-2">
            <div>
                <label>Card Number</label>
                <input type="text" name="card_number" required>
            </div>
            <div>
                <label>UID</label>
                <input type="text" name="uid" required autofocus>
            </div>
        </div>

        <div class="form-grid-2">
            <div>
                <label>Card Type</label>
                <select name="card_type">
                    <option>Student</option>
                    <option>Coach</option>
                    <option>Staff</option>
                </select>
            </div>
            <div>
                <label>Issued On</label>
                <input type="date" name="issued_on">
            </div>
        </div>

        <div class="form-grid-2">
            <div>
                <label>Assign To</label>
                <select name="assigned_to_type" id="assignType">
                    <option value="student">Student</option>
                    <option value="coach">Coach</option>
                </select>
            </div>
            <div>
                <select name="assigned_to_id" id="assignTarget">
                    <?php while ($s = $students->fetch_assoc()): ?>
                        <option value="<?= $s['id'] ?>">
                            <?= htmlspecialchars($s['admission_no'].' '.$s['first_name'].' '.$s['last_name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
        </div>

        <div>
            <label>Status</label>
            <select name="status">
                <option value="active">active</option>
                <option value="inactive">inactive</option>
            </select>
        </div>

        <button class="button-primary">Save Card</button>
    </form>
</div>

<?php include "includes/footer.php"; ?>
