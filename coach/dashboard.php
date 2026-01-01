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

$user_id = $_SESSION['user_id'];

// Fetch user + coach
$stmt = $conn->prepare("
    SELECT u.id AS user_id, u.username, c.*
    FROM users u
    LEFT JOIN coaches c ON c.id = u.coach_id
    WHERE u.id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$coach = $stmt->get_result()->fetch_assoc();

if (!$coach || !$coach['id']) {
    echo "Coach profile not linked to this user.";
    exit;
}

$coachId = (int)$coach['id'];
$coachName = $coach['name'] ?: $coach['username'];

// Today’s sessions from batch_schedule
$dayOfWeek = date('N'); // 1=Mon..7=Sun
$todaySessions = [];

$sqlToday = "
    SELECT bs.*, b.name AS batch_name, g.name AS ground_name
    FROM batch_schedule bs
    LEFT JOIN batches b ON b.id = bs.batch_id
    LEFT JOIN grounds g ON g.id = bs.ground_id
    WHERE bs.coach_id = $coachId
      AND bs.day_of_week = $dayOfWeek
    ORDER BY bs.start_time
";
$resToday = $conn->query($sqlToday);
if ($resToday) {
    while ($row = $resToday->fetch_assoc()) {
        $todaySessions[] = $row;
    }
}

// Monthly sessions from training_sessions
$month = date('Y-m');
$resMonth = $conn->query("
    SELECT COUNT(*) AS c
    FROM training_sessions
    WHERE coach_id = $coachId
      AND DATE_FORMAT(session_date, '%Y-%m') = '$month'
");
$monthSessions = $resMonth ? (int)$resMonth->fetch_assoc()['c'] : 0;

// Total evaluations done
$resEval = $conn->query("
    SELECT COUNT(*) AS c
    FROM player_evaluation
    WHERE coach_id = $coachId
");
$totalEvaluations = $resEval ? (int)$resEval->fetch_assoc()['c'] : 0;

// Recent training notes
$recentSessions = [];
$sqlRecent = "
    SELECT ts.session_date, b.name AS batch_name, g.name AS ground_name, ts.notes
    FROM training_sessions ts
    LEFT JOIN batches b ON b.id = ts.batch_id
    LEFT JOIN grounds g ON g.id = ts.ground_id
    WHERE ts.coach_id = $coachId
    ORDER BY ts.session_date DESC, ts.id DESC
    LIMIT 5
";
$resRecent = $conn->query($sqlRecent);
if ($resRecent) {
    while ($row = $resRecent->fetch_assoc()) {
        $recentSessions[] = $row;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Coach Dashboard – ACA</title>
    <link rel="stylesheet" href="../assets/css/portal-dashboard.css">
</head>
<body>
<div class="portal-layout">
    <!-- SIDEBAR -->
    <aside class="portal-sidebar">
        <div class="portal-logo">ACA Portal</div>
        <div class="portal-role">Coach</div>

        <ul class="portal-nav">
            <li><a href="dashboard.php" class="active">🏠 Dashboard</a></li>
            <li><a href="sessions.php">📘 Sessions</a></li>
            <li><a href="evaluations.php">⭐ Evaluations</a></li>
            <li><a href="injuries.php">🚑 Injuries</a></li>
            <li><a href="students.php">👨‍🎓 My Students</a></li>
            <li><a href="profile.php">👤 My Profile</a></li>
        </ul>

        <div class="portal-sidebar-footer">
            <a href="../logout.php" style="color:#9CA3AF; text-decoration:none;">Logout</a>
        </div>
    </aside>

    <!-- MAIN -->
    <div class="portal-main">
        <header class="portal-topbar">
            <div class="portal-topbar-title">
                Welcome, <?php echo htmlspecialchars($coachName); ?>
            </div>
            <div class="portal-topbar-user">
                Today: <?php echo date('D, M j'); ?>
            </div>
        </header>

        <main class="portal-content">
            <!-- TOP STATS -->
            <section class="card-grid">
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">Sessions This Month</div>
                        <div class="card-badge"><?php echo date('M Y'); ?></div>
                    </div>
                    <div class="card-value"><?php echo $monthSessions; ?></div>
                    <div class="card-sub">Recorded training sessions</div>
                </div>
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">Evaluations Completed</div>
                        <div class="card-badge">All time</div>
                    </div>
                    <div class="card-value"><?php echo $totalEvaluations; ?></div>
                    <div class="card-sub">Player evaluations submitted</div>
                </div>
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">Today’s Sessions</div>
                        <div class="card-badge"><?php echo date('D'); ?></div>
                    </div>
                    <div class="card-value"><?php echo count($todaySessions); ?></div>
                    <div class="card-sub">Scheduled for today</div>
                </div>
            </section>

            <!-- TODAY SESSIONS -->
            <section class="card">
                <div class="card-header">
                    <div class="card-title">Today’s Schedule</div>
                    <button class="btn-ghost" onclick="window.location.href='sessions.php'">Go to Sessions</button>
                </div>
                <?php if (empty($todaySessions)): ?>
                    <div class="card-sub">No sessions scheduled for today.</div>
                <?php else: ?>
                    <table class="table-simple">
                        <thead>
                        <tr>
                            <th>Batch</th>
                            <th>Time</th>
                            <th>Ground</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($todaySessions as $s): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($s['batch_name'] ?: 'Batch'); ?></td>
                                <td><?php echo htmlspecialchars(substr($s['start_time'],0,5).' – '.substr($s['end_time'],0,5)); ?></td>
                                <td><?php echo htmlspecialchars($s['ground_name'] ?: 'TBA'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>

            <!-- RECENT NOTES -->
            <section class="card" style="margin-top:8px;">
                <div class="card-header">
                    <div class="card-title">Recent Training Notes</div>
                    <button class="btn-ghost" onclick="window.location.href='sessions.php'">View all</button>
                </div>
                <?php if (empty($recentSessions)): ?>
                    <div class="card-sub">No session notes recorded yet.</div>
                <?php else: ?>
                    <ul style="list-style:none; margin:0; padding:0; font-size:12px;">
                        <?php foreach ($recentSessions as $s): ?>
                            <li style="margin-bottom:8px;">
                                <strong><?php echo htmlspecialchars($s['session_date']); ?></strong>
                                – <?php echo htmlspecialchars($s['batch_name'] ?: 'Batch'); ?>
                                @ <?php echo htmlspecialchars($s['ground_name'] ?: 'Ground'); ?><br>
                                <span style="color:#9CA3AF;">
                                    <?php echo htmlspecialchars(mb_strimwidth(strip_tags($s['notes'] ?: ''), 0, 90, '…')); ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                        </ul>
                <?php endif; ?>
            </section>
        </main>
    </div>
</div>
</body>
</html>
