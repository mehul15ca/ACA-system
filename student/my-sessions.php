<?php
include "../config.php";
checkLogin();

$role = currentUserRole();
if ($role !== 'student') {
    http_response_code(403);
    echo "Access denied.";
    exit;
}
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$userId = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
if ($userId <= 0) die("Missing user.");

$stmtU = $conn->prepare("SELECT student_id FROM users WHERE id = ?");
$stmtU->bind_param("i", $userId);
$stmtU->execute();
$u = $stmtU->get_result()->fetch_assoc();
if (!$u || !$u['student_id']) die("No student linked.");

$studentId = intval($u['student_id']);

$stmtS = $conn->prepare("SELECT * FROM students WHERE id = ?");
$stmtS->bind_param("i", $studentId);
$stmtS->execute();
$student = $stmtS->get_result()->fetch_assoc();
if (!$student) die("Student not found.");

$batchId = $student['batch_id'];

$batchName = "";
if ($batchId) {
    $stmtB = $conn->prepare("SELECT name FROM batches WHERE id = ?");
    $stmtB->bind_param("i", $batchId);
    $stmtB->execute();
    $b = $stmtB->get_result()->fetch_assoc();
    if ($b) $batchName = $b['name'];
}

$stmtTS = $conn->prepare("
    SELECT ts.*, c.name AS coach_name, g.name AS ground_name
    FROM training_sessions ts
    LEFT JOIN coaches c ON ts.coach_id = c.id
    LEFT JOIN grounds g ON ts.ground_id = g.id
    WHERE ts.batch_id = ?
    ORDER BY ts.session_date DESC, ts.created_at DESC
    LIMIT 50
");
$stmtTS->bind_param("i", $batchId);
$stmtTS->execute();
$sessions = $stmtTS->get_result();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>My Training Sessions - Australasia Cricket Academy</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            margin:0;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background:#050b12;
            color:#f9fafb;
        }
        .wrap {
            max-width:960px;
            margin:0 auto;
            padding:16px;
        }
        h1 { font-size:22px; margin:4px 0 2px; }
        .sub { font-size:12px; color:#9ca3af; margin-bottom:12px; }
        .card {
            background:#0b1724;
            border-radius:16px;
            padding:14px 16px;
            margin-bottom:14px;
            box-shadow:0 16px 30px rgba(0,0,0,0.55);
        }
        table {
            width:100%;
            border-collapse:collapse;
            margin-top:8px;
            font-size:12px;
        }
        th, td {
            padding:6px 4px;
            border-bottom:1px solid #111827;
        }
        th {
            text-align:left;
            color:#9ca3af;
            font-size:11px;
        }
        .notes {
            font-size:11px;
            color:#e5e7eb;
        }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Recent Training Sessions</h1>
    <div class="sub">
        <?php echo htmlspecialchars($student['first_name'] . " " . $student['last_name']); ?> ·
        Admission: <?php echo htmlspecialchars($student['admission_no']); ?>
        <?php if ($batchName): ?>
            · Batch: <?php echo htmlspecialchars($batchName); ?>
        <?php endif; ?>
    </div>

    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Coach</th>
                    <th>Ground</th>
                    <th>Summary</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($sessions && $sessions->num_rows > 0): ?>
                <?php while ($r = $sessions->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($r['session_date']); ?></td>
                        <td><?php echo htmlspecialchars($r['coach_name']); ?></td>
                        <td><?php echo htmlspecialchars($r['ground_name']); ?></td>
                        <td class="notes">
                            <?php
                            $short = mb_substr($r['notes'], 0, 80);
                            if (mb_strlen($r['notes']) > 80) $short .= "...";
                            echo htmlspecialchars($short);
                            ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="4">No sessions recorded yet for your batch.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        <p style="font-size:11px;color:#9ca3af;margin-top:8px;">
            You can use this history to revise what was covered in each session and prepare better for upcoming practices.
        </p>
    </div>
</div>
</body>
</html>
