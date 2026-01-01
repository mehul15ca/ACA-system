<?php
include "../config.php";
checkLogin();
requireSuperadmin();

include "includes/header.php";
include "includes/sidebar.php";

// Get current setting
$res = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key='debug_mode' LIMIT 1");
$current = $res ? $res->fetch_assoc()['setting_value'] : 'off';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new = ($_POST['debug_mode'] === 'on') ? 'on' : 'off';
    $conn->query("
        UPDATE system_settings 
        SET setting_value='$new' 
        WHERE setting_key='debug_mode'
    ");
    $current = $new;
}
?>

<div class="sa-topbar">
    <div class="sa-topbar-title">Debug Mode</div>
    <div class="sa-topbar-sub">Advanced system diagnostics for Superadmin only.</div>
</div>

<div class="sa-content">
    <div class="sa-card">
        <h3>Toggle Debug Mode</h3>

        <form method="post">
            <label style="font-size:18px;">
                <input type="checkbox" name="debug_mode" value="on"
                       <?php echo ($current === 'on') ? 'checked' : ''; ?>>
                Enable Debug Mode
            </label>

            <br><br>

            <button type="submit" class="btn-blue">Save</button>
        </form>

        <p style="margin-top:18px;">
            <strong>Current Status:</strong>
            <?php echo strtoupper($current); ?>
        </p>
    </div>

    <?php if ($current === 'on'): ?>
    <div class="sa-card" style="margin-top:18px;">
        <h3>Debug Information</h3>

        <h4>Session Data:</h4>
        <pre><?php print_r($_SESSION); ?></pre>

        <h4>Recent Error Logs (latest 20):</h4>
        <pre>
<?php
$res = $conn->query("SELECT * FROM error_logs ORDER BY created_at DESC LIMIT 20");
if ($res && $res->num_rows) {
    while ($row = $res->fetch_assoc()) {
        echo $row['created_at']." - ".$row['error_message']."\n";
    }
}
?>
        </pre>

        <h4>Recent Notifications:</h4>
        <pre>
<?php
$res = $conn->query("SELECT * FROM notifications_queue ORDER BY created_at DESC LIMIT 20");
if ($res && $res->num_rows) {
    while ($row = $res->fetch_assoc()) {
        echo $row['status']." - ".$row['subject']."\n";
    }
}
?>
        </pre>

    </div>
    <?php endif; ?>
</div>

<?php include "includes/footer.php"; ?>
