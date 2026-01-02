<?php
require_once __DIR__ . '/_bootstrap.php';
AdminGuard::requirePermission(Permissions::CARDS_MANAGE);

$uid    = trim($_GET['uid'] ?? '');
$num    = trim($_GET['card_number'] ?? '');
$name   = trim($_GET['name'] ?? '');
$status = trim($_GET['status'] ?? '');
$type   = trim($_GET['card_type'] ?? '');

$sql = "
SELECT c.*, 
       s.first_name, s.last_name, s.admission_no,
       ch.name AS coach_name
FROM cards c
LEFT JOIN students s ON c.assigned_to_type='student' AND c.assigned_to_id=s.id
LEFT JOIN coaches ch ON c.assigned_to_type='coach' AND c.assigned_to_id=ch.id
WHERE 1=1
";
$params=[]; $types="";

if ($uid)   { $sql.=" AND c.uid LIKE ?";          $params[]="%$uid%";   $types.="s"; }
if ($num)   { $sql.=" AND c.card_number LIKE ?";  $params[]="%$num%";   $types.="s"; }
if ($status){ $sql.=" AND c.status=?";            $params[]=$status;   $types.="s"; }
if ($type)  { $sql.=" AND c.card_type=?";         $params[]=$type;     $types.="s"; }

$sql.=" ORDER BY c.created_at DESC";

$stmt=$conn->prepare($sql);
if ($params) $stmt->bind_param($types,...$params);
$stmt->execute();
$res=$stmt->get_result();
?>

<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Cards</h1>

<div class="table-card">
<table class="acatable">
<thead>
<tr><th>ID</th><th>Card</th><th>UID</th><th>Type</th><th>Owner</th><th>Status</th></tr>
</thead>
<tbody>
<?php while ($c=$res->fetch_assoc()):
$owner = $c['assigned_to_type']==='student'
  ? trim($c['first_name'].' '.$c['last_name'])
  : ($c['coach_name'] ?? '-');
?>
<tr>
<td><?php echo (int)$c['id']; ?></td>
<td><?php echo htmlspecialchars($c['card_number']); ?></td>
<td><?php echo htmlspecialchars($c['uid']); ?></td>
<td><?php echo htmlspecialchars($c['card_type']); ?></td>
<td><?php echo htmlspecialchars($owner ?: '-'); ?></td>
<td><?php echo htmlspecialchars($c['status']); ?></td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
</div>

<?php include "includes/footer.php"; ?>
