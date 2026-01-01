<?php
include "../config.php";
checkLogin();
requireSuperadmin();

include "includes/header.php";
include "includes/sidebar.php";

// Helper
function sa_val($conn, $sql) {
    $res = $conn->query($sql);
    if ($res && $res->num_rows) {
        $row = $res->fetch_array();
        return $row[0] ?? 0;
    }
    return 0;
}

// Dates
$today   = date('Y-m-d');
$ym      = date('Y-m');
$weekAgo = date('Y-m-d', strtotime('-6 days'));
$yesterday = date('Y-m-d H:i:s', strtotime('-1 day'));

// Core counts
$totalUsers    = sa_val($conn, "SELECT COUNT(*) FROM users");
$totalStudents = sa_val($conn, "SELECT COUNT(*) FROM students");
$totalCoaches  = sa_val($conn, "SELECT COUNT(*) FROM coaches");
$totalBatches  = sa_val($conn, "SELECT COUNT(*) FROM batches");

// Attendance last 7 days
$att7 = sa_val($conn, "
    SELECT COUNT(*) 
    FROM attendance_logs
    WHERE log_date BETWEEN '$weekAgo' AND '$today'
");
$totalPossible = $totalStudents * 7;
$attPct7 = $totalPossible > 0 ? round(($att7 / $totalPossible) * 100, 1) : 0;

// Today attendance (unique students)
$todayAtt = sa_val($conn, "
    SELECT COUNT(DISTINCT student_id)
    FROM attendance_logs
    WHERE log_date = '$today'
");

// Finance – current month
$feesCollectedMonth = sa_val($conn, "
    SELECT SUM(amount) 
    FROM fees_payments
    WHERE DATE_FORMAT(paid_on, '%Y-%m') = '$ym'
");

$expensesMonth = sa_val($conn, "
    SELECT SUM(total_amount)
    FROM expenses
    WHERE DATE_FORMAT(expense_date, '%Y-%m') = '$ym'
");

$feesCollectedMonth = (float)$feesCollectedMonth;
$expensesMonth      = (float)$expensesMonth;
$netMonth           = $feesCollectedMonth - $expensesMonth;

// Pending fees & salary
$pendingFees = sa_val($conn, "
    SELECT SUM(amount)
    FROM fees_invoices
    WHERE status IN ('unpaid','partial')
");

$pendingSalary = sa_val($conn, "
    SELECT SUM(amount)
    FROM coach_salary_sessions
    WHERE status != 'paid'
");

// Notifications last 7 days
$notifSent7   = sa_val($conn, "
    SELECT COUNT(*)
    FROM notifications_queue
    WHERE status = 'sent'
    AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
");
$notifFailed7 = sa_val($conn, "
    SELECT COUNT(*)
    FROM notifications_queue
    WHERE status = 'failed'
    AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
");
$notifPending = sa_val($conn, "
    SELECT COUNT(*)
    FROM notifications_queue
    WHERE status = 'pending'
");

// Logs last 24h
$err24 = sa_val($conn, "
    SELECT COUNT(*)
    FROM error_logs
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
");
$sys24 = sa_val($conn, "
    SELECT COUNT(*)
    FROM system_logs
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
");
$api24 = sa_val($conn, "
    SELECT COUNT(*)
    FROM api_logs
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
");

// Recent errors
$recentErrors = [];
$resErr = $conn->query("
    SELECT error_message, file, line, created_at
    FROM error_logs
    ORDER BY created_at DESC
    LIMIT 5
");
if ($resErr && $resErr->num_rows) {
    while ($row = $resErr->fetch_assoc()) {
        $recentErrors[] = $row;
    }
}

// Recent failed notifications
$recentNotifFail = [];
$resNF = $conn->query("
    SELECT channel, receiver_email, receiver_phone, subject, error_message, created_at
    FROM notifications_queue
    WHERE status = 'failed'
    ORDER BY created_at DESC
    LIMIT 5
");
if ($resNF && $resNF->num_rows) {
    while ($row = $resNF->fetch_assoc()) {
        $recentNotifFail[] = $row;
    }
}

// Recent system activity
$recentSys = [];
$resSys = $conn->query("
    SELECT action, details, created_at
    FROM system_logs
    ORDER BY created_at DESC
    LIMIT 10
");
if ($resSys && $resSys->num_rows) {
    while ($row = $resSys->fetch_assoc()) {
        $recentSys[] = $row;
    }
}
?>

<header class="sa-topbar">
    <div class="sa-topbar-left">
        <div class="sa-topbar-title">Superadmin Power Dashboard</div>
        <div class="sa-topbar-sub">
            High-level health, finance and activity overview of Australasia Cricket Academy system.
        </div>
    </div>
</header>

<main class="sa-content">

    <!-- Top row: users & attendance -->
    <section class="sa-grid sa-grid-4">
        <div class="sa-card">
            <div class="sa-card-label">Total Users</div>
            <div class="sa-card-value"><?php echo (int)$totalUsers; ?></div>
            <div class="sa-card-sub">All login accounts (admins, coaches, students)</div>
        </div>
        <div class="sa-card">
            <div class="sa-card-label">Students</div>
            <div class="sa-card-value"><?php echo (int)$totalStudents; ?></div>
            <div class="sa-card-sub">Active + inactive records</div>
        </div>
        <div class="sa-card">
            <div class="sa-card-label">Coaches</div>
            <div class="sa-card-value"><?php echo (int)$totalCoaches; ?></div>
            <div class="sa-card-sub">Total coaches onboarded</div>
        </div>
        <div class="sa-card">
            <div class="sa-card-label">Batches</div>
            <div class="sa-card-value"><?php echo (int)$totalBatches; ?></div>
            <div class="sa-card-sub">Programs / groups configured</div>
        </div>
    </section>

    <!-- Attendance & notifications -->
    <section class="sa-grid sa-grid-3" style="margin-top:12px;">
        <div class="sa-card">
            <div class="sa-card-label">Today Attendance</div>
            <div class="sa-card-value"><?php echo (int)$todayAtt; ?></div>
            <div class="sa-card-sub">
                Unique students present today (<?php echo htmlspecialchars($today); ?>)
            </div>
        </div>
        <div class="sa-card">
            <div class="sa-card-label">7-Day Attendance Compliance</div>
            <div class="sa-card-value"><?php echo $attPct7; ?>%</div>
            <div class="sa-card-sub">
                Logged entries vs possible (students × 7 days)
            </div>
        </div>
        <div class="sa-card">
            <div class="sa-card-label">Notifications (7 days)</div>
            <div class="sa-card-value">
                ✔ <?php echo (int)$notifSent7; ?> &nbsp; ✖ <?php echo (int)$notifFailed7; ?>
            </div>
            <div class="sa-card-sub">
                Pending now: <?php echo (int)$notifPending; ?>
            </div>
        </div>
    </section>

    <!-- Finance snapshot -->
    <section class="sa-grid sa-grid-3" style="margin-top:12px;">
        <div class="sa-card">
            <div class="sa-card-label">Fees Collected (<?php echo date('M Y'); ?>)</div>
            <div class="sa-card-value">
                CAD <?php echo number_format($feesCollectedMonth, 2); ?>
            </div>
            <div class="sa-card-sub">From recorded payments</div>
        </div>
        <div class="sa-card">
            <div class="sa-card-label">Expenses (<?php echo date('M Y'); ?>)</div>
            <div class="sa-card-value">
                CAD <?php echo number_format($expensesMonth, 2); ?>
            </div>
            <div class="sa-card-sub">All expense entries</div>
        </div>
        <div class="sa-card">
            <div class="sa-card-label">Net (<?php echo date('M Y'); ?>)</div>
            <div class="sa-card-value <?php echo $netMonth >= 0 ? 'sa-positive' : 'sa-negative'; ?>">
                CAD <?php echo number_format($netMonth, 2); ?>
            </div>
            <div class="sa-card-sub">Fees – Expenses (approx, before salary)</div>
        </div>
    </section>

    <!-- Pending money -->
    <section class="sa-grid sa-grid-2" style="margin-top:12px;">
        <div class="sa-card">
            <div class="sa-card-label">Pending Fees</div>
            <div class="sa-card-value">
                CAD <?php echo number_format((float)$pendingFees, 2); ?>
            </div>
            <div class="sa-card-sub">
                Unpaid + partial invoices
            </div>
        </div>
        <div class="sa-card">
            <div class="sa-card-label">Pending Coach Salary</div>
            <div class="sa-card-value">
                CAD <?php echo number_format((float)$pendingSalary, 2); ?>
            </div>
            <div class="sa-card-sub">
                Based on coach_salary_sessions where status != 'paid'
            </div>
        </div>
    </section>

    <!-- Logs & health -->
    <section class="sa-grid sa-grid-3" style="margin-top:12px;">
        <div class="sa-card">
            <div class="sa-card-label">Error Logs (24h)</div>
            <div class="sa-card-value"><?php echo (int)$err24; ?></div>
            <div class="sa-card-sub">
                From error_logs table
            </div>
        </div>
        <div class="sa-card">
            <div class="sa-card-label">System Logs (24h)</div>
            <div class="sa-card-value"><?php echo (int)$sys24; ?></div>
            <div class="sa-card-sub">
                General system actions
            </div>
        </div>
        <div class="sa-card">
            <div class="sa-card-label">API Logs (24h)</div>
            <div class="sa-card-value"><?php echo (int)$api24; ?></div>
            <div class="sa-card-sub">
                Attendance/API endpoints
            </div>
        </div>
    </section>

    <!-- Recent errors + failed notifications -->
    <section class="sa-grid sa-grid-2" style="margin-top:16px;">
        <div class="sa-card">
            <div class="sa-card-header">
                <div class="sa-card-title">Recent Errors</div>
            </div>
            <?php if (empty($recentErrors)): ?>
                <div class="sa-card-sub">No errors logged yet.</div>
            <?php else: ?>
                <table class="sa-table">
                    <thead>
                        <tr>
                            <th>When</th>
                            <th>Message</th>
                            <th>Location</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($recentErrors as $e): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($e['created_at']); ?></td>
                            <td><?php echo htmlspecialchars(mb_strimwidth($e['error_message'], 0, 60, '…')); ?></td>
                            <td><?php echo htmlspecialchars($e['file'] . ':' . $e['line']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div class="sa-card">
            <div class="sa-card-header">
                <div class="sa-card-title">Failed Notifications</div>
            </div>
            <?php if (empty($recentNotifFail)): ?>
                <div class="sa-card-sub">No failed notifications in recent history.</div>
            <?php else: ?>
                <table class="sa-table">
                    <thead>
                        <tr>
                            <th>When</th>
                            <th>Channel</th>
                            <th>To</th>
                            <th>Error</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($recentNotifFail as $n): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($n['created_at']); ?></td>
                            <td><?php echo htmlspecialchars($n['channel']); ?></td>
                            <td>
                                <?php
                                    $to = $n['receiver_email'] ?: $n['receiver_phone'];
                                    echo htmlspecialchars(mb_strimwidth((string)$to, 0, 30, '…'));
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars(mb_strimwidth((string)$n['error_message'], 0, 40, '…')); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </section>

    <!-- Recent system events -->
    <section class="sa-card" style="margin-top:16px;">
        <div class="sa-card-header">
            <div class="sa-card-title">Recent System Activity</div>
        </div>
        <?php if (empty($recentSys)): ?>
            <div class="sa-card-sub">No system logs yet.</div>
        <?php else: ?>
            <table class="sa-table">
                <thead>
                    <tr>
                        <th>When</th>
                        <th>Action</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($recentSys as $log): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($log['created_at']); ?></td>
                        <td><?php echo htmlspecialchars($log['action']); ?></td>
                        <td><?php echo htmlspecialchars(mb_strimwidth((string)$log['details'], 0, 80, '…')); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>
</main>

<?php include "includes/footer.php"; ?>
