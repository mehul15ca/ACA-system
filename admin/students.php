<?php
require_once __DIR__ . '/_bootstrap.php';

if (!hasPermission('manage_students')) {
    http_response_code(403);
    echo "Access denied.";
    exit;
}

?>
<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Students</h1>

<div class="table-card">
    <p>This is a placeholder page for the <strong>Students</strong> module.</p>
    <p>It is loading correctly and ready for backend logic to be added later.</p>
</div>

<?php include "includes/footer.php"; ?>
