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

$batchId = $student['batch_id'] ? intval($student['batch_id']) : 0;

if ($batchId > 0) {
    $stmtA = $conn->prepare("
        SELECT DISTINCT a.*
        FROM announcements a
        LEFT JOIN batch_schedule bs
            ON a.ground_id IS NOT NULL
           AND bs.ground_id = a.ground_id
        WHERE a.status = 'active'
          AND a.audience IN ('all','students')
          AND (a.batch_id IS NULL OR a.batch_id = ?)
          AND (a.ground_id IS NULL OR bs.batch_id = ?)
        ORDER BY a.created_at DESC, a.id DESC
        LIMIT 100
    ");
    $stmtA->bind_param("ii", $batchId, $batchId);
} else {
    $stmtA = $conn->prepare("
        SELECT DISTINCT a.*
        FROM announcements a
        WHERE a.status = 'active'
          AND a.audience IN ('all','students')
        ORDER BY a.created_at DESC, a.id DESC
        LIMIT 100
    ");
}
$stmtA->execute();
$anns = $stmtA->get_result();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Announcements - Australasia Cricket Academy</title>
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
            padding:12px 14px;
            margin-bottom:10px;
            box-shadow:0 16px 30px rgba(0,0,0,0.55);
        }
        .title {
            font-size:14px;
            font-weight:600;
            margin-bottom:2px;
        }
        .meta {
            font-size:11px;
            color:#9ca3af;
            margin-bottom:6px;
        }
        .body {
            font-size:12px;
            color:#e5e7eb;
            white-space:pre-wrap;
        }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Announcements</h1>
    <div class="sub">
        <?php echo htmlspecialchars($student['first_name'] . " " . $student['last_name']); ?> ·
        Admission: <?php echo htmlspecialchars($student['admission_no']); ?>
    </div>

    <?php if ($anns && $anns->num_rows > 0): ?>
        <?php while ($a = $anns->fetch_assoc()): ?>
            <div class="card">
                <div class="title"><?php echo htmlspecialchars($a['title']); ?></div>
                <div class="meta">
                    <?php echo htmlspecialchars(date('Y-m-d', strtotime($a['created_at']))); ?>
                </div>
                <div class="body"><?php echo nl2br(htmlspecialchars($a['body'])); ?></div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="card">
            <div class="body">No announcements at the moment.</div>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
