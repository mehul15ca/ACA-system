<?php
require_once __DIR__ . '/_bootstrap.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) die("Invalid card ID.");

$message = "";
$success = "";

// Load card
$stmt = $conn->prepare("SELECT * FROM cards WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
$card = $res->fetch_assoc();
if (!$card) die("Card not found.");

// Fetch students & coaches
$students_res = $conn->query("
    SELECT id, admission_no, first_name, last_name
    FROM students
    ORDER BY first_name ASC
");
$coaches_res = $conn->query("
    SELECT id, coach_code, name
    FROM coaches
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
        $sql = "
            UPDATE cards
            SET card_number = ?, uid = ?, card_type = ?, assigned_to_type = ?, assigned_to_id = ?, issued_on = ?, status = ?
            WHERE id = ?
        ";
        $stmtU = $conn->prepare($sql);
        $stmtU->bind_param("ssssissi", $card_number, $uid, $card_type, $owner_type, $owner_id, $issued_on, $status, $id);

        if ($stmtU->execute()) {
            $success = "Card updated successfully.";
            // reload card
            $stmt = $conn->prepare("SELECT * FROM cards WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $card = $stmt->get_result()->fetch_assoc();
        } else {
            $message = "Database error: " . $conn->error;
        }
    }
}
?>
<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Edit Card</h1>

<div class="form-card">
    <?php if ($message): ?>
        <div class="alert-error"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <form method="POST">
        <?php echo Csrf::field(); ?>

        <div class="form-grid-2">
            <div class="form-group">
                <label>Card Number</label>
                <input type="text" name="card_number" value="<?php echo htmlspecialchars($card['card_number']); ?>" required>
            </div>
            <div class="form-group">
                <label>UID</label>
                <input type="text" name="uid" value="<?php echo htmlspecialchars($card['uid']); ?>" required>
            </div>
        </div>

        <div class="form-grid-2">
            <div class="form-group">
                <label>Card Type</label>
                <select name="card_type" required>
                    <option value="Student" <?php if ($card['card_type'] === 'Student') echo 'selected'; ?>>Student</option>
                    <option value="Coach"   <?php if ($card['card_type'] === 'Coach')   echo 'selected'; ?>>Coach</option>
                    <option value="Staff"   <?php if ($card['card_type'] === 'Staff')   echo 'selected'; ?>>Staff</option>
                </select>
            </div>
            <div class="form-group">
                <label>Issued On</label>
                <input type="date" name="issued_on" value="<?php echo htmlspecialchars($card['issued_on']); ?>">
            </div>
        </div>

        <div class="form-grid-2">
            <div class="form-group">
                <label>Assign To (Type)</label>
                <select name="assigned_to_type" id="assigned_to_type" required>
                    <option value="student" <?php if ($card['assigned_to_type'] === 'student') echo 'selected'; ?>>Student</option>
                    <option value="coach"   <?php if ($card['assigned_to_type'] === 'coach')   echo 'selected'; ?>>Coach</option>
                </select>
            </div>
            <div class="form-group" id="studentSelectWrap">
                <label>Student</label>
                <select name="assigned_to_id" id="studentSelect">
                    <option value="">-- Select Student --</option>
                    <?php if ($students_res): ?>
                        <?php while ($s = $students_res->fetch_assoc()): ?>
                            <option value="<?php echo $s['id']; ?>"
                                <?php if ($card['assigned_to_type'] === 'student' && $card['assigned_to_id'] == $s['id']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($s['admission_no'] . " - " . $s['first_name'] . " " . $s['last_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>
            </div>
            <div class="form-group" id="coachSelectWrap">
                <label>Coach</label>
                <select name="assigned_to_id" id="coachSelect">
                    <option value="">-- Select Coach --</option>
                    <?php if ($coaches_res): ?>
                        <?php while ($c = $coaches_res->fetch_assoc()): ?>
                            <option value="<?php echo $c['id']; ?>"
                                <?php if ($card['assigned_to_type'] === 'coach' && $card['assigned_to_id'] == $c['id']) echo 'selected'; ?>>
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
                <option value="active"   <?php if ($card['status'] === 'active')   echo 'selected'; ?>>active</option>
                <option value="inactive" <?php if ($card['status'] === 'inactive') echo 'selected'; ?>>inactive</option>
            </select>
        </div>

        <button type="submit" class="button-primary">Update Card</button>
        <a href="cards.php" class="button">Back to list</a>
    </form>
</div>

<script>
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
</script>

<?php include "includes/footer.php"; ?>
