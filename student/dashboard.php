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

$user_id = $_SESSION['user_id'];

// Fetch user + student
$stmt = $conn->prepare("
    SELECT u.id AS user_id, u.username, s.*
    FROM users u
    LEFT JOIN students s ON s.id = u.student_id
    WHERE u.id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

if (!$student || !$student['id']) {
    echo "Student profile not linked to this user.";
    exit;
}

$studentId = (int)$student['id'];
$studentName = trim($student['first_name'] . " " . $student['last_name']);
$batchName = $student['batch_id'] ? '' : '';

// Get batch name if exists
if (!empty($student['batch_id'])) {
    $bid = (int)$student['batch_id'];
    $resB = $conn->query("SELECT name FROM batches WHERE id = $bid");
    if ($resB && $rowB = $resB->fetch_assoc()) {
        $batchName = $rowB['name'];
    }
}

// Attendance stats
// Total presents
$resAttTotal = $conn->query("
    SELECT COUNT(*) AS c 
    FROM attendance_logs 
    WHERE student_id = $studentId
");
$totalPresent = $resAttTotal ? (int)$resAttTotal->fetch_assoc()['c'] : 0;

// This month
$ym = date('Y-m');
$resAttMonth = $conn->query("
    SELECT COUNT(*) AS c
    FROM attendance_logs
    WHERE student_id = $studentId
      AND DATE_FORMAT(log_date, '%Y-%m') = '$ym'
");
$monthPresent = $resAttMonth ? (int)$resAttMonth->fetch_assoc()['c'] : 0;

// Upcoming sessions (today and next few)
$dayOfWeek = date('N'); // 1=Mon..7=Sun
$batchId = (int)$student['batch_id'];
$upcomingSessions = [];

if ($batchId > 0) {
    $sqlSess = "
        SELECT bs.*, g.name AS ground_name
        FROM batch_schedule bs
        LEFT JOIN grounds g ON g.id = bs.ground_id
        WHERE bs.batch_id = $batchId
        ORDER BY bs.day_of_week, bs.start_time
        LIMIT 10
    ";
    $resSess = $conn->query($sqlSess);
    if ($resSess) {
        while ($row = $resSess->fetch_assoc()) {
            $upcomingSessions[] = $row;
        }
    }
}

// Announcements (for students)
$announcements = [];
$sqlAnn = "
    SELECT title, body, published_from, published_to
    FROM announcements
    WHERE (audience = 'all' OR audience = 'students')
      AND (published_from IS NULL OR published_from <= NOW())
      AND (published_to IS NULL OR published_to >= NOW())
    ORDER BY created_at DESC
    LIMIT 5
";
$resAnn = $conn->query($sqlAnn);
if ($resAnn) {
    while ($row = $resAnn->fetch_assoc()) {
        $announcements[] = $row;
    }
}

// Pending fees
$pendingInvoices = [];
$sqlFees = "
    SELECT invoice_no, amount, currency, due_date, status
    FROM fees_invoices
    WHERE student_id = $studentId
      AND status IN ('unpaid','partial')
    ORDER BY due_date ASC
    LIMIT 5
";
$resFees = $conn->query($sqlFees);
if ($resFees) {
    while ($row = $resFees->fetch_assoc()) {
        $pendingInvoices[] = $row;
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Student Dashboard – ACA</title>
    <link rel="stylesheet" href="../assets/css/portal-dashboard.css">
</head>
<body>
<div class="portal-layout">
    <!-- SIDEBAR -->
    <aside class="portal-sidebar">
        <div class="portal-logo">ACA Portal</div>
        <div class="portal-role">Student</div>

        <ul class="portal-nav">
            <li><a href="dashboard.php" class="active">🏠 Dashboard</a></li>
            <li><a href="attendance.php">✅ My Attendance</a></li>
            <li><a href="evaluations.php">⭐ Evaluations</a></li>
            <li><a href="matches.php">🏏 My Matches</a></li>
            <li><a href="store-orders.php">🛍️ My Orders</a></li>
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
                Welcome, <?php echo htmlspecialchars($studentName ?: $student['username']); ?>
            </div>
            <div class="portal-topbar-user">
                Batch: <?php echo htmlspecialchars($batchName ?: 'Not assigned'); ?>
            </div>
        </header>

        <main class="portal-content">
            <!-- TOP STATS -->
            <section class="card-grid">
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">Total Sessions Attended</div>
                        <div class="card-badge">All time</div>
                    </div>
                    <div class="card-value"><?php echo $totalPresent; ?></div>
                    <div class="card-sub">Based on recorded attendance</div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <div class="card-title">Attendance This Month</div>
                        <div class="card-badge"><?php echo date('M Y'); ?></div>
                    </div>
                    <div class="card-value"><?php echo $monthPresent; ?></div>
                    <div class="card-sub">Present days in current month</div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <div class="card-title">Pending Fees</div>
                        <div class="card-badge">Invoices</div>
                    </div>
                    <?php
                    $pendingTotal = 0;
                    foreach ($pendingInvoices as $inv) {
                        $pendingTotal += (float)$inv['amount'];
                    }
                    ?>
                    <div class="card-value">
                        <?php echo $pendingTotal > 0 ? htmlspecialchars(($pendingInvoices[0]['currency'] ?? 'CAD') . ' ' . number_format($pendingTotal, 2)) : '0.00'; ?>
                    </div>
                    <div class="card-sub">
                        <?php echo count($pendingInvoices); ?> invoice(s) pending
                    </div>
                </div>
            </section>

            <!-- TODAY / UPCOMING SESSIONS -->
            <section class="card-grid">
                <div class="card" style="grid-column: span 2;">
                    <div class="card-header">
                        <div class="card-title">Your Training Schedule</div>
                        <button class="btn-ghost" onclick="window.location.href='schedule.php'">View full schedule</button>
                    </div>
                    <?php if (empty($upcomingSessions)): ?>
                        <div class="card-sub">No schedule found for your batch yet.</div>
                    <?php else: ?>
                        <table class="table-simple">
                            <thead>
                            <tr>
                                <th>Day</th>
                                <th>Time</th>
                                <th>Ground</th>
                                <th>Status</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php
                            $days = [1=>'Mon',2=>'Tue',3=>'Wed',4=>'Thu',5=>'Fri',6=>'Sat',7=>'Sun'];
                            foreach ($upcomingSessions as $s):
                                $dow = (int)$s['day_of_week'];
                                $isToday = ($dow === (int)date('N'));
                                ?>
                                <tr>
                                    <td><?php echo $days[$dow] ?? $dow; ?></td>
                                    <td><?php echo htmlspecialchars(substr($s['start_time'],0,5) . ' – ' . substr($s['end_time'],0,5)); ?></td>
                                    <td><?php echo htmlspecialchars($s['ground_name'] ?: 'TBA'); ?></td>
                                    <td>
                                        <?php if ($isToday): ?>
                                            <span class="chip chip-green">Today</span>
                                        <?php else: ?>
                                            <span class="chip chip-blue">Upcoming</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <!-- ANNOUNCEMENTS -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">Announcements</div>
                        <button class="btn-ghost" onclick="window.location.href='announcements.php'">View all</button>
                    </div>
                    <?php if (empty($announcements)): ?>
                        <div class="card-sub">No announcements right now.</div>
                    <?php else: ?>
                        <ul style="list-style:none; margin:0; padding:0; font-size:12px;">
                            <?php foreach ($announcements as $a): ?>
                                <li style="margin-bottom:8px;">
                                    <strong><?php echo htmlspecialchars($a['title']); ?></strong><br>
                                    <span style="color:#9CA3AF;">
                                        <?php echo htmlspecialchars(mb_strimwidth(strip_tags($a['body']), 0, 80, '…')); ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </section>

            <!-- PENDING FEES LIST -->
            <section class="card" style="margin-top:6px;">
                <div class="card-header">
                    <div class="card-title">Pending Invoices</div>
                    <button class="btn-ghost" onclick="window.location.href='../admin/login.php'">Contact admin to pay</button>
                </div>
                <?php if (empty($pendingInvoices)): ?>
                    <div class="card-sub">No pending fees. You’re all clear.</div>
                <?php else: ?>
                    <table class="table-simple">
                        <thead>
                        <tr>
                            <th>Invoice</th>
                            <th>Due</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($pendingInvoices as $inv): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($inv['invoice_no']); ?></td>
                                <td><?php echo htmlspecialchars($inv['due_date']); ?></td>
                                <td><?php echo htmlspecialchars($inv['currency'].' '.number_format($inv['amount'],2)); ?></td>
                                <td>
                                    <?php if ($inv['status'] === 'unpaid'): ?>
                                        <span class="chip chip-red">Unpaid</span>
                                    <?php else: ?>
                                        <span class="chip chip-yellow">Partial</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>
        </main>
    </div>
</div>
</body>
</html>
