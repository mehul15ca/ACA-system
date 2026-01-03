<?php
require_once __DIR__ . '/_bootstrap.php';
AdminGuard::requirePermission(Permissions::STUDENTS_MANAGE);

$id=(int)($_GET['id']??0);
if ($id<=0) die("Invalid student");

$stmt=$conn->prepare("SELECT * FROM students WHERE id=?");
$stmt->bind_param("i",$id);
$stmt->execute();
$student=$stmt->get_result()->fetch_assoc();
if (!$student) die("Not found");

if ($_SERVER['REQUEST_METHOD']==='POST') {
    Csrf::validateRequest();

    $up=$conn->prepare("
        UPDATE students SET admission_no=?,first_name=?,last_name=?,status=?
        WHERE id=?
    ");
    $up->bind_param(
        "ssssi",
        $_POST['admission_no'],
        $_POST['first_name'],
        $_POST['last_name'],
        $_POST['status'],
        $id
    );
    $up->execute();
}
?>

<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<form method="POST">
<?php echo Csrf::field(); ?>
<input name="admission_no" value="<?php echo htmlspecialchars($student['admission_no']); ?>">
<input name="first_name" value="<?php echo htmlspecialchars($student['first_name']); ?>">
<button class="button-primary">Update</button>
</form>

<?php include "includes/footer.php"; ?>
