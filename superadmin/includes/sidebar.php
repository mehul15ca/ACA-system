<?php
// Sidebar for Superadmin
$role = currentUserRole();
if ($role !== 'superadmin') {
    http_response_code(403);
    echo "Access denied.";
    exit;
}

$current = basename($_SERVER['PHP_SELF']);

function sa_active($file) {
    global $current;
    return $current === $file ? 'active' : '';
}
global $debugMode;
?>
<aside class="sa-sidebar">
    <div class="sa-logo">
        <div class="sa-logo-title">ACA CONTROL</div>
        <div class="sa-logo-sub">Superadmin Command Center</div>
    </div>
    <div class="sa-role-badge">
        <span>🛡️</span>
        <span>Superadmin</span>
        <?php if ($debugMode === 'on'): ?>
            <span style="margin-left:6px; padding:2px 6px; border-radius:999px; background:#b91c1c; font-size:10px; color:#fff;">
                DEBUG
            </span>
        <?php endif; ?>
    </div>

    <ul class="sa-nav">
        <li class="sa-nav-group-label">Overview</li>
        <li>
            <a href="dashboard.php" class="<?php echo sa_active('dashboard.php'); ?>">
                📊 Analytics Overview
            </a>
        </li>
        <li>
            <a href="performance.php" class="<?php echo sa_active('performance.php'); ?>">
                ⚙️ System Performance
            </a>
        </li>

        <li class="sa-nav-group-label">Logs</li>
        <li>
            <a href="logs-email.php" class="<?php echo sa_active('logs-email.php'); ?>">
                ✉️ Email & Notifications
            </a>
        </li>
        <li>
            <a href="logs-errors.php" class="<?php echo sa_active('logs-errors.php'); ?>">
                🚨 Error Logs
            </a>
        </li>
        <li>
            <a href="logs-system.php" class="<?php echo sa_active('logs-system.php'); ?>">
                📜 System Logs
            </a>
        </li>
        <li>
            <a href="logs-api.php" class="<?php echo sa_active('logs-api.php'); ?>">
                🔌 API Logs
            </a>
        </li>

        <li class="sa-nav-group-label">Access Control</li>
        <li>
            <a href="permissions.php" class="<?php echo sa_active('permissions.php'); ?>">
                🔐 Permissions
            </a>
        </li>
        <li>
            <a href="role-permissions.php" class="<?php echo sa_active('role-permissions.php'); ?>">
                🧩 Role Permissions
            </a>
        </li>
        <li>
            <a href="user-permissions.php" class="<?php echo sa_active('user-permissions.php'); ?>">
                🙍 User Overrides
            </a>
        </li>

        <li class="sa-nav-group-label">Admin Modules</li>
        <li><a href="../admin/students.php">👨‍🎓 Students</a></li>
        <li><a href="../admin/coaches.php">👨‍🏫 Coaches</a></li>
        <li><a href="../admin/batches.php">🗂️ Batches</a></li>
        <li><a href="../admin/batch-schedule.php">📅 Batch Schedule</a></li>
        <li><a href="../admin/attendance-reports.php">✅ Attendance</a></li>
        <li><a href="../admin/matches.php">🏏 Matches</a></li>
        <li><a href="../admin/cards.php">💳 Cards</a></li>
        <li><a href="../admin/evaluation.php">⭐ Evaluations</a></li>
        <li><a href="../admin/injuries.php">🚑 Injury Reports</a></li>
        <li><a href="../admin/training-sessions.php">📘 Training Sessions</a></li>
        <li><a href="../admin/top-players.php">🏅 Top Players</a></li>
        <li><a href="../admin/fees-invoices.php">💳 Fees & Invoices</a></li>
        <li><a href="../admin/expenses-dashboard.php">📊 Expenses</a></li>
        <li><a href="../admin/coach-salary.php">🧾 Coach Salary</a></li>
        <li><a href="../admin/store-orders.php">🛒 Merchandise Store</a></li>
        <li><a href="../admin/announcements.php">📢 Announcements</a></li>
        <li><a href="../admin/notifications-log.php">✉️ Notifications Log</a></li>
        <li><a href="../admin/users.php">👤 Users</a></li>
        <li><a href="../admin/documents.php">📂 Documents</a></li>

        <li class="sa-nav-group-label">System</li>
        <li>
            <a href="debug.php" class="<?php echo sa_active('debug.php'); ?>">
                🧪 Debug Mode
            </a>
        </li>
        <li>
            <a href="settings.php" class="<?php echo sa_active('settings.php'); ?>">
                ⚙️ Master Settings
            </a>
        </li>
        <li>
            <a href="../admin/dashboard.php">
                🧭 Go to Admin Panel
            </a>
        </li>
    </ul>

    <div class="sa-sidebar-footer">
        <a href="../logout.php" style="color:#9CA3AF; text-decoration:none;">Logout</a>
    </div>
</aside>

<div class="sa-main">
