<?php
require_once __DIR__ . '/_bootstrap.php';
AdminGuard::requirePermission(Permissions::STUDENTS_MANAGE);

$errors=[]; $success='';

$batches=$conn->query("
    SELECT id,name FROM batches
    WHERE status='active'
    ORDER BY name
");

if ($_SERVER['REQUEST_METHOD']==='POST') {
    Csrf::validateRequest();

    $admission_no=trim($_POST['admission_no']??'');
    $first_name=trim($_POST['first_name']??'');
    $email=trim($_POST['email']??'');

    if ($admission_no==='') $errors[]='Admission number required';
    if ($first_name==='') $errors[]='First name required';
    if ($email && !filter_var($email,FILTER_VALIDATE_EMAIL)) $errors[]='Invalid email';

    if (!$errors) {
        $stmt=$conn->prepare("
            INSERT INTO students
            (admission_no,first_name,last_name,dob,parent_name,phone,email,parent_email,address,batch_id,join_date,status)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
        ");
        $stmt->bind_param(
            "ssssssssisss",
            $_POST['admission_no'],
            $_POST['first_name'],
            $_POST['last_name'],
            $_POST['dob'],
            $_POST['parent_name'],
            $_POST['phone'],
            $_POST['email'],
            $_POST['parent_email'],
            $_POST['address'],
            $_POST['batch_id'],
            $_POST['join_date'],
            $_POST['status']
        );
        $stmt->execute();
        $success='Student added';
    }
}
?>

<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<form method="POST">
<?php echo Csrf::field(); ?>
<input name="admission_no" placeholder="Admission No" required>
<input name="first_name" placeholder="First Name" required>
<input name="last_name" placeholder="Last Name">
<button class="button-primary">Save</button>
</form>

<?php include "includes/footer.php"; ?>
