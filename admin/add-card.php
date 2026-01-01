<?php
include "../config.php";
checkLogin();

$role = currentUserRole();
if (!in_array($role, ['admin','superadmin'])) {
    http_response_code(403);
    echo "Access denied.";
    exit;
}

$message = "";
$success = "";

// Fetch students & coaches for assignment
$students_res = $conn->query("
    SELECT id, admission_no, first_name, last_name
    FROM students
    WHERE status = 'active'
    ORDER BY first_name ASC
");
$coaches_res = $conn->query("
    SELECT id, coach_code, name
    FROM coaches
    WHERE status = 'active'
    ORDER BY name ASC
");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $card_number = trim($_POST['card_number']);
    $uid         = trim($_POST['uid']);
    $card_type   = $_POST['card_type'];
    $owner_type  = $_POST['assigned_to_type'];
    $owner_id    = intval($_POST['assigned_to_id']);
    $issued_on   = $_POST['issued_on'] !== "" ? $_POST['issued_on'] : null;
    $status      = $_POST['status'];

    if ($card_number === "" || $uid === "") {
        $message = "Card number and UID are required.";
    } elseif (!in_array($card_type, ['Student','Coach','Staff'])) {
        $message = "Invalid card type.";
    } elseif (!in_array($owner_type, ['student','coach'])) {
        $message = "Invalid owner type.";
    } elseif ($owner_id <= 0) {
        $message = "Please choose a valid owner.";
    } else {
        // Insert new card
        $sql = "
            INSERT INTO cards (card_number, uid, card_type, assigned_to_type, assigned_to_id, issued_on, status)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssiss", $card_number, $uid, $card_type, $owner_type, $owner_id, $issued_on, $status);

        if ($stmt->execute()) {
            $success = "Card added successfully.";
            // Clear POST fields
            $card_number = $uid = "";
        } else {
            $message = "Database error: " . $conn->error;
        }
    }
}
?>
<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Add Card</h1>

<div class="form-card">
    <?php if ($message): ?>
        <div class="alert-error"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-grid-2">
            <div class="form-group">
                <label>Card Number</label>
                <input type="text" name="card_number" required>
            </div>
            <div class="form-group">
                <label>UID
                    <span style="font-size:11px;color:#9ca3af;">
                        (Tap card on reader or type manually)
                    </span>
                </label>
                <input type="text" name="uid" id="uidField" required>
            </div>
        </div>

        <div class="form-grid-2">
            <div class="form-group">
                <label>Card Type</label>
                <select name="card_type" required>
                    <option value="Student">Student</option>
                    <option value="Coach">Coach</option>
                    <option value="Staff">Staff</option>
                </select>
            </div>
            <div class="form-group">
                <label>Issued On</label>
                <input type="date" name="issued_on">
            </div>
        </div>

        <div class="form-grid-2">
            <div class="form-group">
                <label>Assign To (Type)</label>
                <select name="assigned_to_type" id="assigned_to_type" required>
                    <option value="student">Student</option>
                    <option value="coach">Coach</option>
                </select>
            </div>
            <div class="form-group" id="studentSelectWrap">
                <label>Student</label>
                <select name="assigned_to_id" id="studentSelect">
                    <option value="">-- Select Student --</option>
                    <?php if ($students_res): ?>
                        <?php while ($s = $students_res->fetch_assoc()): ?>
                            <option value="<?php echo $s['id']; ?>">
                                <?php echo htmlspecialchars($s['admission_no'] . " - " . $s['first_name'] . " " . $s['last_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>
            </div>
            <div class="form-group" id="coachSelectWrap" style="display:none;">
                <label>Coach</label>
                <select name="assigned_to_id" id="coachSelect">
                    <option value="">-- Select Coach --</option>
                    <?php if ($coaches_res): ?>
                        <?php while ($c = $coaches_res->fetch_assoc()): ?>
                            <option value="<?php echo $c['id']; ?>">
                                <?php echo htmlspecialchars($c['coach_code'] . " - " . $c['name']); ?>
                            </option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label>Status</label>
            <select name="status">
                <option value="active">active</option>
                <option value="inactive">inactive</option>
            </select>
        </div>

        <button type="submit" class="button-primary">Save Card</button>
        <a href="cards.php" class="button">Back to list</a>
    </form>
</div>

<script>
// Toggle student/coach select
const typeSelect = document.getElementById('assigned_to_type');
const studentWrap = document.getElementById('studentSelectWrap');
const coachWrap = document.getElementById('coachSelectWrap');
const studentSelect = document.getElementById('studentSelect');
const coachSelect = document.getElementById('coachSelect');

function updateOwnerSelect() {
    if (typeSelect.value === 'student') {
        studentWrap.style.display = '';
        coachWrap.style.display = 'none';
        coachSelect.name = 'x_ignore';
        studentSelect.name = 'assigned_to_id';
    } else {
        studentWrap.style.display = 'none';
        coachWrap.style.display = '';
        studentSelect.name = 'x_ignore';
        coachSelect.name = 'assigned_to_id';
    }
}
typeSelect.addEventListener('change', updateOwnerSelect);
updateOwnerSelect();

// Auto-focus UID field for scanner input
document.getElementById('uidField').focus();
</script>

<?php include "includes/footer.php"; ?>
