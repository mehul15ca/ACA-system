<?php
require_once __DIR__ . '/_bootstrap.php';
AdminGuard::requireAnyRole(['admin','superadmin']);
?>

<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Documents</h1>

<div class="table-card">
  <p>Module placeholder. Secured and ready.</p>
</div>

<?php include "includes/footer.php"; ?>
