<?php
require_once __DIR__ . '/_bootstrap.php';

AdminGuard::requireAnyRole(['admin','superadmin','coach']);

if (!isset($_GET['id'])) {
    die("Match ID missing.");
}
$match_id = (int)$_GET['id'];
$message = "";

/* ------------------ FETCH MATCH ------------------ */
$stmt = $conn->prepare("
    SELECT m.*, g.name AS ground_name
    FROM matches m
    LEFT JOIN grounds g ON m.ground_id = g.id
    WHERE m.id = ?
");
$stmt->bind_param("i", $match_id);
$stmt->execute();
$match = $stmt->get_result()->fetch_assoc();
if (!$match) die("Match not found.");

/* ------------------ BATCH FILTER ------------------ */
$selected_batch_id = (int)($_GET['batch_id'] ?? 0);
$batches_res = $conn->query("
    SELECT id, name, age_group
    FROM batches
    WHERE status='active'
    ORDER BY name ASC
");

/* ------------------ POST HANDLERS ------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::validateRequest();

    /* ADD PLAYER */
    if (isset($_POST['add_player'])) {
        $student_id = (int)$_POST['student_id'];
        $role_text  = trim($_POST['role']);
        $is_xi      = isset($_POST['is_playing_xi']) ? 1 : 0;

        if ($student_id <= 0) {
            $message = "Please select a student.";
        } else {
            $chk = $conn->prepare("
                SELECT id FROM match_players
                WHERE match_id=? AND student_id=?
            ");
            $chk->bind_param("ii", $match_id, $student_id);
            $chk->execute();

            if ($chk->get_result()->num_rows) {
                $message = "Student already added.";
            } else {
                $ins = $conn->prepare("
                    INSERT INTO match_players
                    (match_id, student_id, role, is_playing_xi)
                    VALUES (?,?,?,?)
                ");
                $ins->bind_param("iisi", $match_id, $student_id, $role_text, $is_xi);
                $ins->execute();
                $message = "Player added.";
            }
        }
    }

    /* UPDATE PLAYER */
    if (isset($_POST['update_player'])) {
        $mp_id   = (int)$_POST['mp_id'];
        $roleTxt = trim($_POST['role']);
        $is_xi   = isset($_POST['is_playing_xi']) ? 1 : 0;

        $up = $conn->prepare("
            UPDATE match_players
            SET role=?, is_playing_xi=?
            WHERE id=? AND match_id=?
        ");
        $up->bind_param("siii", $roleTxt, $is_xi, $mp_id, $match_id);
        $up->execute();
        $message = "Player updated.";
    }
}

/* ------------------ STUDENTS LIST ------------------ */
$students_sql = "
    SELECT s.id, s.admission_no, s.first_name, s.last_name, b.name AS batch_name
    FROM students s
    LEFT JOIN batches b ON s.batch_id=b.id
    WHERE s.status='active'
";
if ($selected_batch_id > 0) {
    $students_sql .= " AND s.batch_id=?";
    $stmtS = $conn->prepare($students_sql . " ORDER BY s.first_name");
    $stmtS->bind_param("i", $selected_batch_id);
    $stmtS->execute();
    $students_res = $stmtS->get_result();
} else {
    $students_sql .= " ORDER BY s.first_name";
    $students_res = $conn->query($students_sql);
}

/* ------------------ MATCH PLAYERS ------------------ */
$ps = $conn->prepare("
    SELECT mp.*, s.admission_no, s.first_name, s.last_name, b.name AS batch_name
    FROM match_players mp
    JOIN students s ON mp.student_id=s.id
    LEFT JOIN batches b ON s.batch_id=b.id
    WHERE mp.match_id=?
    ORDER BY mp.is_playing_xi DESC, s.first_name
");
$ps->bind_param("i", $match_id);
$ps->execute();
$playersRes = $ps->get_result();
?>

<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Manage Players – Match #<?php echo (int)$match['id']; ?></h1>

<?php if ($message): ?>
<p style="color:red"><?php echo htmlspecialchars($message); ?></p>
<?php endif; ?>

<div class="form-card">
<h2>Add Player</h2>

<form method="POST">
<?php echo Csrf::field(); ?>
<input type="hidden" name="add_player" value="1">

<select name="student_id" required>
<option value="">-- Select Student --</option>
<?php while ($s=$students_res->fetch_assoc()): ?>
<option value="<?php echo (int)$s['id']; ?>">
<?php echo htmlspecialchars($s['admission_no']." - ".$s['first_name']." ".$s['last_name']); ?>
</option>
<?php endwhile; ?>
</select>

<input name="role" placeholder="Role" required>
<label><input type="checkbox" name="is_playing_xi" checked> Playing XI</label>
<button class="button-primary">Add</button>
</form>
</div>

<div class="table-card">
<h2>Current Players</h2>
<table class="acatable">
<thead>
<tr><th>#</th><th>Name</th><th>Batch</th><th>Role</th><th>XI</th><th>Action</th></tr>
</thead>
<tbody>
<?php $i=1; while ($p=$playersRes->fetch_assoc()): ?>
<tr>
<td><?php echo $i++; ?></td>
<td><?php echo htmlspecialchars($p['first_name']." ".$p['last_name']); ?></td>
<td><?php echo htmlspecialchars($p['batch_name']); ?></td>
<td>
<form method="POST" style="display:inline">
<?php echo Csrf::field(); ?>
<input type="hidden" name="update_player" value="1">
<input type="hidden" name="mp_id" value="<?php echo (int)$p['id']; ?>">
<input name="role" value="<?php echo htmlspecialchars($p['role']); ?>">
</td>
<td>
<input type="checkbox" name="is_playing_xi" <?php if ($p['is_playing_xi']) echo 'checked'; ?>>
</td>
<td>
<button class="button-small">Save</button>
</form>

<form method="POST" action="delete-match-player.php" style="display:inline">
<?php echo Csrf::field(); ?>
<input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>">
<input type="hidden" name="match_id" value="<?php echo $match_id; ?>">
<button class="text-link" onclick="return confirm('Remove player?')">Remove</button>
</form>
</td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
</div>

<?php include "includes/footer.php"; ?>
