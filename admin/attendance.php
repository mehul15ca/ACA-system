<?php
require_once __DIR__ . '/_bootstrap.php';

AdminGuard::requirePermission(Permissions::ATTENDANCE_VIEW);
?>

<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Attendance</h1>

<div class="table-card">
  <p>This module is ready. Detailed logic will be added here.</p>
</div>

<?php include "includes/footer.php"; ?>
