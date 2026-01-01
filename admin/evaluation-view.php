<?php
include "../config.php";
checkLogin();

$role = currentUserRole();
if (!in_array($role, ['admin','superadmin','coach','student'])) {
    http_response_code(403);
    echo "Access denied.";
    exit;
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$print = isset($_GET['print']) && $_GET['print'] == '1';

if ($id <= 0) die("Invalid evaluation ID.");

$sql = "
    SELECT pe.*,
           s.admission_no,
           s.first_name AS s_first,
           s.last_name  AS s_last,
           c.name       AS coach_name
    FROM player_evaluation pe
    JOIN students s ON pe.student_id = s.id
    LEFT JOIN coaches c ON pe.coach_id = c.id
    WHERE pe.id = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$ev = $stmt->get_result()->fetch_assoc();
if (!$ev) die("Evaluation not found.");

// Student visibility: if logged in as student, ensure this is THEIR evaluation
if ($role === 'student') {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $userId = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
    $stmtU = $conn->prepare("SELECT student_id FROM users WHERE id = ?");
    $stmtU->bind_param("i", $userId);
    $stmtU->execute();
    $u = $stmtU->get_result()->fetch_assoc();
    if (!$u || intval($u['student_id']) !== intval($ev['student_id'])) {
        http_response_code(403);
        echo "Access denied.";
        exit;
    }
}

$cats = [
    'batting_rating'    => 'Batting',
    'bowling_rating'    => 'Bowling',
    'fielding_rating'   => 'Fielding',
    'fitness_rating'    => 'Fitness',
    'discipline_rating' => 'Discipline',
    'attitude_rating'   => 'Attitude',
    'technique_rating'  => 'Technique'
];

if ($print):
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Player Evaluation</title>
    <style>
        body { font-family: system-ui, -apple-system, "Segoe UI", sans-serif; padding:20px; color:#111827; }
        h1 { margin-bottom:4px; }
        h2 { margin-top:18px; margin-bottom:6px; }
        table { border-collapse:collapse; width:100%; margin-top:8px; }
        th, td { border:1px solid #d1d5db; padding:6px 8px; font-size:13px; }
        th { background:#f3f4f6; text-align:left; }
        .meta { font-size:13px; color:#4b5563; line-height:1.6; }
        .notes { margin-top:10px; font-size:13px; white-space:pre-wrap; }
    </style>
</head>
<body onload="window.print()">
    <h1>Player Evaluation Report</h1>
    <div class="meta">
        Student: <?php echo htmlspecialchars($ev['s_first'] . " " . $ev['s_last']); ?>
        (<?php echo htmlspecialchars($ev['admission_no']); ?>)<br>
        Coach: <?php echo htmlspecialchars($ev['coach_name']); ?><br>
        Evaluation Time: <?php echo htmlspecialchars($ev['eval_time']); ?><br>
        Status: <?php echo htmlspecialchars($ev['status']); ?><br>
        Overall Score: <?php echo $ev['overall_score'] !== null ? number_format($ev['overall_score'], 2) : '-'; ?>
    </div>

    <h2>Category Ratings (1–10)</h2>
    <table>
        <thead>
            <tr>
                <th>Category</th>
                <th>Rating</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($cats as $field => $label): ?>
            <tr>
                <td><?php echo htmlspecialchars($label); ?></td>
                <td>
                    <?php
                    $val = $ev[$field];
                    echo ($val !== null ? intval($val) : '-');
                    ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <h2>Coach Notes</h2>
    <div class="notes">
        <?php echo nl2br(htmlspecialchars($ev['notes'])); ?>
    </div>
</body>
</html>
<?php
exit;
endif;
?>

<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Player Evaluation</h1>

<div class="form-card">
    <p style="font-size:13px;color:#6b7280;line-height:1.6;">
        Student: <strong><?php echo htmlspecialchars($ev['s_first'] . " " . $ev['s_last']); ?></strong>
        (<?php echo htmlspecialchars($ev['admission_no']); ?>)<br>
        Coach: <?php echo htmlspecialchars($ev['coach_name']); ?><br>
        Evaluation Time: <?php echo htmlspecialchars($ev['eval_time']); ?><br>
        Status: <strong><?php echo htmlspecialchars($ev['status']); ?></strong><br>
        Overall Score: <?php echo $ev['overall_score'] !== null ? number_format($ev['overall_score'], 2) : '-'; ?>
    </p>

    <div style="margin-top:8px;">
        <a href="evaluation-view.php?id=<?php echo $ev['id']; ?>&print=1" class="button" target="_blank">🧾 Print / PDF</a>
        <?php if (in_array($role, ['admin','superadmin','coach'])): ?>
            <a href="evaluation-edit.php?id=<?php echo $ev['id']; ?>" class="button">Edit</a>
        <?php endif; ?>
        <a href="evaluations.php" class="button">Back to list</a>
    </div>
</div>

<div class="table-card">
    <div class="table-header">
        <h2>Category Ratings (1–10)</h2>
    </div>
    <table class="acatable">
        <thead>
            <tr>
                <th>Category</th>
                <th>Rating</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($cats as $field => $label): ?>
            <tr>
                <td><?php echo htmlspecialchars($label); ?></td>
                <td>
                    <?php
                    $val = $ev[$field];
                    echo ($val !== null ? intval($val) : '-');
                    ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="form-card">
    <h2 style="font-size:16px;margin-bottom:6px;">Coach Notes</h2>
    <p style="white-space:pre-wrap;font-size:13px;"><?php echo htmlspecialchars($ev['notes']); ?></p>
</div>

<?php include "includes/footer.php"; ?>
