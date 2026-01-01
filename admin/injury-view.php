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

if ($id <= 0) die("Invalid injury ID.");

$sql = "
    SELECT ir.*,
           s.admission_no,
           s.first_name AS s_first,
           s.last_name  AS s_last,
           c.name       AS coach_name
    FROM injury_reports ir
    JOIN students s ON ir.student_id = s.id
    LEFT JOIN coaches c ON ir.coach_id = c.id
    WHERE ir.id = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$inj = $stmt->get_result()->fetch_assoc();
if (!$inj) die("Injury report not found.");

// If student, ensure this is their injury
if ($role === 'student') {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $userId = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
    $stmtU = $conn->prepare("SELECT student_id FROM users WHERE id = ?");
    $stmtU->bind_param("i", $userId);
    $stmtU->execute();
    $u = $stmtU->get_result()->fetch_assoc();
    if (!$u || intval($u['student_id']) !== intval($inj['student_id'])) {
        http_response_code(403);
        echo "Access denied.";
        exit;
    }
}

if ($print):
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Injury Report</title>
    <style>
        body { font-family: system-ui, -apple-system, "Segoe UI", sans-serif; padding:20px; color:#111827; }
        h1 { margin-bottom:4px; }
        h2 { margin-top:18px; margin-bottom:6px; }
        .meta { font-size:13px; color:#4b5563; line-height:1.6; }
        .notes { margin-top:10px; font-size:13px; white-space:pre-wrap; }
        .section { margin-top:12px; }
    </style>
</head>
<body onload="window.print()">
    <h1>Injury Report</h1>
    <div class="meta">
        Student: <?php echo htmlspecialchars($inj['s_first'] . " " . $inj['s_last']); ?>
        (<?php echo htmlspecialchars($inj['admission_no']); ?>)<br>
        Coach (reporting): <?php echo htmlspecialchars($inj['coach_name']); ?><br>
        Incident Date: <?php echo htmlspecialchars($inj['incident_date']); ?><br>
        Reported At: <?php echo htmlspecialchars($inj['reported_at']); ?><br>
        Severity: <?php echo htmlspecialchars($inj['severity']); ?><br>
        Status: <?php echo htmlspecialchars($inj['status']); ?><br>
        Injury Area: <?php echo htmlspecialchars($inj['injury_area']); ?>
    </div>

    <div class="section">
        <h2>What Happened</h2>
        <div class="notes"><?php echo nl2br(htmlspecialchars($inj['notes'])); ?></div>
    </div>

    <div class="section">
        <h2>Action Taken</h2>
        <div class="notes"><?php echo nl2br(htmlspecialchars($inj['action_taken'])); ?></div>
    </div>
</body>
</html>
<?php
exit;
endif;
?>

<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Injury Report</h1>

<div class="form-card">
    <p style="font-size:13px;color:#6b7280;line-height:1.6;">
        Student: <strong><?php echo htmlspecialchars($inj['s_first'] . " " . $inj['s_last']); ?></strong>
        (<?php echo htmlspecialchars($inj['admission_no']); ?>)<br>
        Coach (reporting): <?php echo htmlspecialchars($inj['coach_name']); ?><br>
        Incident Date: <?php echo htmlspecialchars($inj['incident_date']); ?><br>
        Reported At: <?php echo htmlspecialchars($inj['reported_at']); ?><br>
        Severity: <strong><?php echo htmlspecialchars($inj['severity']); ?></strong><br>
        Status: <strong><?php echo htmlspecialchars($inj['status']); ?></strong><br>
        Injury Area: <?php echo htmlspecialchars($inj['injury_area']); ?>
    </p>

    <div style="margin-top:8px;">
        <a href="injury-view.php?id=<?php echo $inj['id']; ?>&print=1" class="button" target="_blank">🧾 Print / PDF</a>
        <?php if (in_array($role, ['admin','superadmin','coach'])): ?>
            <a href="injury-edit.php?id=<?php echo $inj['id']; ?>" class="button">Edit</a>
        <?php endif; ?>
        <a href="injuries.php" class="button">Back to list</a>
    </div>
</div>

<div class="form-card">
    <h2 style="font-size:15px;margin-bottom:6px;">What Happened</h2>
    <p style="white-space:pre-wrap;font-size:13px;"><?php echo htmlspecialchars($inj['notes']); ?></p>
</div>

<div class="form-card">
    <h2 style="font-size:15px;margin-bottom:6px;">Action Taken</h2>
    <p style="white-space:pre-wrap;font-size:13px;"><?php echo htmlspecialchars($inj['action_taken']); ?></p>
</div>

<?php include "includes/footer.php"; ?>
