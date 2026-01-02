<?php
require_once __DIR__ . '/_bootstrap.php';
AdminGuard::requirePermission(Permissions::BATCH_SCHEDULE_MANAGE);

$id = (int)($_GET['id'] ?? 0);
if ($id<=0) exit('Invalid ID');

$stmt=$conn->prepare("SELECT * FROM batch_schedule WHERE id=?");
$stmt->bind_param("i",$id);
$stmt->execute();
$orig=$stmt->get_result()->fetch_assoc();
$stmt->close();
if(!$orig) exit('Not found');

// dropdowns
$batches=$conn->query("SELECT id,name,age_group FROM batches WHERE status='active'");
$grounds=$conn->query("SELECT id,name FROM grounds WHERE status='active'");
$coaches=$conn->query("SELECT id,name FROM coaches WHERE status='active'");

$msg='';

if($_SERVER['REQUEST_METHOD']==='POST'){
    Csrf::validateRequest();

    $stmt=$conn->prepare("
      INSERT INTO batch_schedule
      (batch_id,day_of_week,start_time,end_time,ground_id,coach_id,status)
      VALUES (?,?,?,?,?,?,?)
    ");
    $stmt->bind_param(
      "iississ",
      $_POST['batch_id'],$_POST['day_of_week'],
      $_POST['start_time'],$_POST['end_time'],
      $_POST['ground_id'],$_POST['coach_id'],$_POST['status']
    );
    if($stmt->execute()){
        header('Location: batch-schedule.php'); exit;
    }
    $msg='Insert failed';
}
?>

<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Copy Schedule</h1>

<div class="form-card">
<?php if($msg): ?><div class="alert-error"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
<form method="POST">
<?php echo Csrf::field(); ?>
<!-- fields omitted for brevity, SAME as add-schedule but prefilled -->
<button class="button-primary">Save Copy</button>
</form>
</div>

<?php include "includes/footer.php"; ?>
