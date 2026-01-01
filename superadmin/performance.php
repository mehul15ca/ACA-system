<?php
include "../config.php";
checkLogin();
requireSuperadmin();

include "includes/header.php";
include "includes/sidebar.php";

// Simple env diagnostics
$phpVersion = PHP_VERSION;
$memoryLimit = ini_get('memory_limit');
$maxExecTime = ini_get('max_execution_time');

// Cron / system actions (from system_logs)
$recentCron = [];
$resCron = $conn->query("
    SELECT action, details, created_at
    FROM system_logs
    WHERE action LIKE 'cron_%'
    ORDER BY created_at DESC
    LIMIT 10
");
if ($resCron && $resCron->num_rows) {
    while ($row = $resCron->fetch_assoc()) {
        $recentCron[] = $row;
    }
}

// Error frequency last 7 days
$errorCounts = [];
$resErr = $conn->query("
    SELECT DATE(created_at) AS d, COUNT(*) AS c
    FROM error_logs
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(created_at)
    ORDER BY d DESC
");
if ($resErr && $resErr->num_rows) {
    while ($row = $resErr->fetch_assoc()) {
        $errorCounts[] = $row;
    }
}
?>

<header class="sa-topbar">
    <div class="sa-topbar-left">
        <div class="sa-topbar-title">System Performance</div>
        <div class="sa-topbar-sub">
            Technical overview of environment and background activity.
        </div>
    </div>
</header>

<main class="sa-content">

    <section class="sa-grid sa-grid-3">
        <div class="sa-card">
            <div class="sa-card-label">PHP Version</div>
            <div class="sa-card-value"><?php echo htmlspecialchars($phpVersion); ?></div>
            <div class="sa-card-sub">XAMPP PHP runtime</div>
        </div>
        <div class="sa-card">
            <div class="sa-card-label">Memory Limit</div>
            <div class="sa-card-value"><?php echo htmlspecialchars($memoryLimit); ?></div>
            <div class="sa-card-sub">Configured in php.ini</div>
        </div>
        <div class="sa-card">
            <div class="sa-card-label">Max Execution Time</div>
            <div class="sa-card-value"><?php echo htmlspecialchars($maxExecTime); ?>s</div>
            <div class="sa-card-sub">Script timeout</div>
        </div>
    </section>

    <section class="sa-card" style="margin-top:16px;">
        <div class="sa-card-header">
            <div class="sa-card-title">Cron & Background Jobs (system_logs)</div>
        </div>
        <?php if (empty($recentCron)): ?>
            <div class="sa-card-sub">
                No cron-related entries found yet. Once daily reports / reminders run, they will appear here.
            </div>
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
                <?php foreach ($recentCron as $c): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($c['created_at']); ?></td>
                        <td><?php echo htmlspecialchars($c['action']); ?></td>
                        <td><?php echo htmlspecialchars(mb_strimwidth((string)$c['details'], 0, 80, '…')); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <section class="sa-card" style="margin-top:16px;">
        <div class="sa-card-header">
            <div class="sa-card-title">Error Frequency (Last 7 Days)</div>
        </div>
        <?php if (empty($errorCounts)): ?>
            <div class="sa-card-sub">No errors logged in the last 7 days. ✅</div>
        <?php else: ?>
            <table class="sa-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Errors</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($errorCounts as $e): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($e['d']); ?></td>
                        <td><?php echo (int)$e['c']; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

</main>

<?php include "includes/footer.php"; ?>
