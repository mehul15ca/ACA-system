<?php
include_once "../config.php";
checkLogin();

$role    = currentUserRole();
$current = basename($_SERVER['PHP_SELF']);

function admin_active($file) {
    global $current;
    return $current === $file ? 'active' : '';
}
?>
<div class="sidebar">
    <div class="sidebar-logo">
        <div class="logo-text">ACA Admin</div>
        <div class="logo-role">
            <?php echo ucfirst($role); ?>
        </div>
    </div>

    <ul class="sidebar-menu">
        <?php if (hasPermission('view_dashboard')): ?>
            <li>
                <a href="dashboard.php" class="<?php echo admin_active('dashboard.php'); ?>">
                    🏠 Dashboard
                </a>
            </li>
        <?php endif; ?>

        <?php if (
            hasPermission('manage_students') ||
            hasPermission('manage_coaches') ||
            hasPermission('manage_batches') ||
            hasPermission('manage_batch_schedule') ||
            hasPermission('manage_attendance') ||
            hasPermission('manage_matches') ||
            hasPermission('manage_cards') ||
            hasPermission('manage_evaluations') ||
            hasPermission('manage_injury_reports') ||
            hasPermission('manage_sessions') ||
            hasPermission('manage_top_players')
        ): ?>
            <li class="sidebar-section">Academy</li>
        <?php endif; ?>

        <?php if (hasPermission('manage_students')): ?>
            <li>
                <a href="students.php" class="<?php echo admin_active('students.php'); ?>">
                    👨‍🎓 Students
                </a>
            </li>
        <?php endif; ?>

        <?php if (hasPermission('manage_coaches')): ?>
            <li>
                <a href="coaches.php" class="<?php echo admin_active('coaches.php'); ?>">
                    👨‍🏫 Coaches
                </a>
            </li>
        <?php endif; ?>

        <?php if (hasPermission('manage_batches')): ?>
            <li>
                <a href="batches.php" class="<?php echo admin_active('batches.php'); ?>">
                    🗂️ Batches
                </a>
            </li>
        <?php endif; ?>

        <?php if (hasPermission('manage_batch_schedule')): ?>
            <li>
                <a href="batch-schedule.php" class="<?php echo admin_active('batch-schedule.php'); ?>">
                    📅 Batch Schedule
                </a>
            </li>
        <?php endif; ?>

        <?php if (hasPermission('view_attendance_reports')): ?>
            <li>
                <a href="attendance-reports.php" class="<?php echo admin_active('attendance-reports.php'); ?>">
                    ✅ Attendance Reports
                </a>
            </li>
        <?php endif; ?>

        <?php if (hasPermission('manage_matches') || hasPermission('view_matches')): ?>
            <li>
                <a href="matches.php" class="<?php echo admin_active('matches.php'); ?>">
                    🏏 Matches
                </a>
            </li>
        <?php endif; ?>

        <?php if (hasPermission('manage_cards')): ?>
            <li>
                <a href="cards.php" class="<?php echo admin_active('cards.php'); ?>">
                    💳 Cards
                </a>
            </li>
        <?php endif; ?>

        <?php if (hasPermission('manage_evaluations') || hasPermission('view_evaluations')): ?>
            <li>
                <a href="evaluation.php" class="<?php echo admin_active('evaluation.php'); ?>">
                    ⭐ Player Evaluations
                </a>
            </li>
        <?php endif; ?>

        <?php if (hasPermission('manage_injury_reports') || hasPermission('view_injury_reports')): ?>
            <li>
                <a href="injuries.php" class="<?php echo admin_active('injuries.php'); ?>">
                    🚑 Injury Reports
                </a>
            </li>
        <?php endif; ?>

        <?php if (hasPermission('manage_sessions') || hasPermission('view_sessions')): ?>
            <li>
                <a href="training-sessions.php" class="<?php echo admin_active('training-sessions.php'); ?>">
                    📘 Training Sessions
                </a>
            </li>
        <?php endif; ?>

        <?php if (hasPermission('manage_top_players')): ?>
            <li>
                <a href="top-players.php" class="<?php echo admin_active('top-players.php'); ?>">
                    🏅 Top Players
                </a>
            </li>
        <?php endif; ?>

        <?php if (
            hasPermission('manage_fees') ||
            hasPermission('manage_expenses') ||
            hasPermission('manage_salary') ||
            hasPermission('manage_store')
        ): ?>
            <li class="sidebar-section">Finance</li>
        <?php endif; ?>

        <?php if (hasPermission('manage_fees')): ?>
            <li>
                <a href="fees-invoices.php" class="<?php echo admin_active('fees-invoices.php'); ?>">
                    💳 Fees & Invoices
                </a>
            </li>
        <?php endif; ?>

        <?php if (hasPermission('manage_expenses') || hasPermission('view_expenses')): ?>
            <li>
                <a href="expenses-dashboard.php" class="<?php echo admin_active('expenses-dashboard.php'); ?>">
                    📊 Expenses Dashboard
                </a>
            </li>
        <?php endif; ?>

        <?php if (hasPermission('manage_salary') || hasPermission('view_salary')): ?>
            <li>
                <a href="coach-salary.php" class="<?php echo admin_active('coach-salary.php'); ?>">
                    🧾 Coach Salary
                </a>
            </li>
        <?php endif; ?>

        <?php if (hasPermission('manage_store') || hasPermission('view_store_orders')): ?>
            <li>
                <a href="store-orders.php" class="<?php echo admin_active('store-orders.php'); ?>">
                    🛒 Merchandise Orders
                </a>
            </li>
        <?php endif; ?>

        <?php if (hasPermission('manage_announcements') || hasPermission('view_announcements') || hasPermission('manage_notifications')): ?>
            <li class="sidebar-section">Communication</li>
        <?php endif; ?>

        <?php if (hasPermission('manage_announcements') || hasPermission('view_announcements')): ?>
            <li>
                <a href="announcements.php" class="<?php echo admin_active('announcements.php'); ?>">
                    📢 Announcements
                </a>
            </li>
        <?php endif; ?>

        <?php if (hasPermission('manage_notifications')): ?>
            <li>
                <a href="notifications-log.php" class="<?php echo admin_active('notifications-log.php'); ?>">
                    ✉️ Notifications Log
                </a>
            </li>
        <?php endif; ?>

        <?php if (
            hasPermission('manage_users') ||
            hasPermission('manage_documents') ||
            hasPermission('view_logs') ||
            hasPermission('view_api_logs') ||
            hasPermission('view_error_logs')
        ): ?>
            <li class="sidebar-section">System</li>
        <?php endif; ?>

        <?php if (hasPermission('manage_users')): ?>
            <li>
                <a href="users.php" class="<?php echo admin_active('users.php'); ?>">
                    👤 Users
                </a>
            </li>
        <?php endif; ?>

        <?php if (hasPermission('manage_documents')): ?>
            <li>
                <a href="documents.php" class="<?php echo admin_active('documents.php'); ?>">
                    📂 Documents
                </a>
            </li>
        <?php endif; ?>

        <?php if (hasPermission('view_logs')): ?>
            <li>
                <a href="logs.php" class="<?php echo admin_active('logs.php'); ?>">
                    📜 System Logs
                </a>
            </li>
        <?php endif; ?>

        <?php if (hasPermission('view_api_logs')): ?>
            <li>
                <a href="api-logs.php" class="<?php echo admin_active('api-logs.php'); ?>">
                    🔌 API Logs
                </a>
            </li>
        <?php endif; ?>

        <?php if (hasPermission('view_error_logs')): ?>
            <li>
                <a href="logs-errors.php" class="<?php echo admin_active('logs-errors.php'); ?>">
                    🚨 Error Logs
                </a>
            </li>
        <?php endif; ?>
    </ul>

    <div class="sidebar-footer">
        <a href="../logout.php">🚪 Logout</a>
    </div>
</div>

<div class="admin-content">
    <div class="admin-topbar">
        <div class="topbar-title">Australasia Cricket Academy – Admin</div>
        <div class="topbar-user">
            Logged in as: <strong><?php echo ucfirst($role); ?></strong>
            <?php global $debugMode; if ($debugMode === 'on'): ?>
                <span style="margin-left:10px; padding:3px 6px; border-radius:4px; background:#b91c1c; color:#fff; font-size:11px;">
                    DEBUG MODE
                </span>
            <?php endif; ?>
        </div>
    </div>
    <div class="admin-page">
