<?php
require_once __DIR__ . '/_bootstrap.php';

AdminGuard::requirePermission(Permissions::API_LOGS_VIEW);
?>

<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>API Logs</h1>

<div class="table-card">
    <p>This is a placeholder page for the <strong>API Logs</strong> module.</p>
    <p>It is secured and ready for backend logic.</p>
</div>

<?php include "includes/footer.php"; ?>
