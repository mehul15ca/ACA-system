<?php
include "../config.php";
checkLogin();
$role = currentUserRole();

// Get ID
if (!isset($_GET['id'])) {
    header("Location: students.php");
    exit;
}
$student_id = (int)$_GET['id'];

// Fetch student
$stmt = $conn->prepare("
    SELECT s.*, b.name AS batch_name
    FROM students s
    LEFT JOIN batches b ON s.batch_id = b.id
    WHERE s.id = ?
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

if (!$student) {
    echo "Student not found.";
    exit;
}

include "includes/header.php";
include "includes/sidebar.php";

// Include document helper
include "includes/documents-helper.php";
?>

<div class="page-header">
    <h1>View Student</h1>
    <a href="students.php" class="button-secondary">← Back to Students</a>
</div>

<div class="card">
    <h2 style="margin-top:0;">Student Information</h2>

    <table class="table-profile">
        <tr><th>Admission No:</th><td><?php echo htmlspecialchars($student['admission_no']); ?></td></tr>
        <tr><th>Name:</th><td><?php echo htmlspecialchars($student['first_name']." ".$student['last_name']); ?></td></tr>
        <tr><th>Date of Birth:</th><td><?php echo htmlspecialchars($student['dob']); ?></td></tr>
        <tr><th>Batch:</th><td><?php echo htmlspecialchars($student['batch_name']); ?></td></tr>
        <tr><th>Phone:</th><td><?php echo htmlspecialchars($student['phone']); ?></td></tr>
        <tr><th>Email:</th><td><?php echo htmlspecialchars($student['email']); ?></td></tr>
        <tr><th>Parent Email:</th><td><?php echo htmlspecialchars($student['parent_email'] ?? ''); ?></td></tr>
        <tr><th>Address:</th><td><?php echo htmlspecialchars($student['address']); ?></td></tr>
        <tr><th>Emergency Contact:</th>
            <td>
                <?php echo htmlspecialchars($student['emergency_contact_name']); ?>
                (<?php echo htmlspecialchars($student['emergency_contact_relation']); ?>)
                – <?php echo htmlspecialchars($student['emergency_contact_phone']); ?>
            </td>
        </tr>
        <tr><th>Status:</th><td><?php echo htmlspecialchars($student['status']); ?></td></tr>
        <tr><th>Joined:</th><td><?php echo htmlspecialchars($student['join_date']); ?></td></tr>
    </table>

    <a href="edit-student.php?id=<?php echo $student_id; ?>" class="button-primary">Edit Student</a>
</div>

<!-- 🔥 DOCUMENTS SECTION -->
<?php
$canManageDocs = in_array($role, ['admin','superadmin']);
renderDocumentsSection($conn, 'student', $student_id, $canManageDocs);
?>

<?php include "includes/footer.php"; ?>
