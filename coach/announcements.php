<?php
include "../config.php";
checkLogin();

$role = currentUserRole();
if ($role !== 'coach') {
    http_response_code(403);
    echo "Access denied.";
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$userId = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
if ($userId <= 0) die("Missing user.");

$stmtU = $conn->prepare("SELECT coach_id FROM users WHERE id = ?");
$stmtU->bind_param("i", $userId);
$stmtU->execute();
$u = $stmtU->get_result()->fetch_assoc();
if (!$u || !$u['coach_id']) die("No coach linked.");

$coachId = intval($u['coach_id']);

$stmtC = $conn->prepare("SELECT name FROM coaches WHERE id = ?");
$stmtC->bind_param("i", $coachId);
$stmtC->execute();
$coach = $stmtC->get_result()->fetch_assoc();
if (!$coach) die("Coach not found.");

$stmtA = $conn->prepare("
    SELECT DISTINCT a.*
    FROM announcements a
    LEFT JOIN batch_schedule bs
      ON (
            (a.batch_id IS NOT NULL AND bs.batch_id = a.batch_id)
         OR (a.ground_id IS NOT NULL AND bs.ground_id = a.ground_id)
         )
    WHERE a.status = 'active'
      AND a.audience IN ('all','coaches')
      AND (
            (a.batch_id IS NULL AND a.ground_id IS NULL)
         OR bs.coach_id = ?
          )
    ORDER BY a.created_at DESC, a.id DESC
    LIMIT 100
");
$stmtA->bind_param("i", $coachId);
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
        Coach: <?php echo htmlspecialchars($coach['name']); ?>
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
