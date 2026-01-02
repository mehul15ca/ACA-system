<?php
require "../config.php";
checkLogin();
requireSuperadmin();

use ACA\Core\Csrf;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify($_POST['csrf'] ?? null);

    $new = ($_POST['debug_mode'] === 'on') ? 'on' : 'off';
    $stmt = $conn->prepare("
        UPDATE system_settings SET setting_value=? WHERE setting_key='debug_mode'
    ");
    $stmt->bind_param("s", $new);
    $stmt->execute();
}

$res = $conn->query("
    SELECT setting_value FROM system_settings WHERE setting_key='debug_mode' LIMIT 1
");
$current = $res ? ($res->fetch_assoc()['setting_value'] ?? 'off') : 'off';
?>
<form method="post">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
    <label>
        <input type="checkbox" name="debug_mode" value="on" <?= $current === 'on' ? 'checked' : '' ?>>
        Enable Debug Mode
    </label>
    <button type="submit">Save</button>
</form>

<?php if ($current === 'on'): ?>
<pre>
<?php
$res = $conn->query("SELECT created_at,error_message FROM error_logs ORDER BY created_at DESC LIMIT 20");
while ($row = $res->fetch_assoc()) {
    echo htmlspecialchars($row['created_at'].' '.$row['error_message'], ENT_QUOTES)."\n";
}
?>
</pre>
<?php endif; ?>
