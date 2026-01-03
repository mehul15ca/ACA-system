<?php
require_once __DIR__ . '/_bootstrap.php';

$role = currentUserRole();

if (!isset($_GET['id'])) {
    header("Location: coaches.php");
    exit;
}
$coach_id = (int)$_GET['id'];

$stmt = $conn->prepare("
    SELECT *
    FROM coaches
    WHERE id = ?
");
$stmt->bind_param("i", $coach_id);
$stmt->execute();
$coach = $stmt->get_result()->fetch_assoc();

if (!$coach) {
    echo "Coach not found.";
    exit;
}

include "includes/header.php";
include "includes/sidebar.php";

// Include document helper
include "includes/documents-helper.php";
?>

<div class="page-header">
    <h1>View Coach</h1>
    <a href="coaches.php" class="button-secondary">← Back to Coaches</a>
</div>

<div class="card">
    <h2 style="margin-top:0;">Coach Information</h2>

    <table class="table-profile">
        <tr><th>Coach Code:</th><td><?php echo htmlspecialchars($coach['coach_code']); ?></td></tr>
        <tr><th>Name:</th><td><?php echo htmlspecialchars($coach['name']); ?></td></tr>
        <tr><th>Email:</th><td><?php echo htmlspecialchars($coach['email']); ?></td></tr>
        <tr><th>Phone:</th><td><?php echo htmlspecialchars($coach['phone']); ?></td></tr>
        <tr><th>Specialization:</th><td><?php echo htmlspecialchars($coach['specialization']); ?></td></tr>
        <tr><th>Status:</th><td><?php echo htmlspecialchars($coach['status']); ?></td></tr>
        <tr><th>Created:</th><td><?php echo htmlspecialchars($coach['created_at']); ?></td></tr>
    </table>

    <a href="edit-coach.php?id=<?php echo $coach_id; ?>" class="button-primary">Edit Coach</a>
</div>

<!-- 🔥 DOCUMENTS SECTION -->
<?php
$canManageDocs = in_array($role, ['admin','superadmin']);
renderDocumentsSection($conn, 'coach', $coach_id, $canManageDocs);
?>

<?php include "includes/footer.php"; ?>
