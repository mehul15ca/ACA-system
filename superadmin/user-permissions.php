<?php
include "../config.php";
checkLogin();
requireSuperadmin();

// Fetch all users
$users = [];
$resU = $conn->query("SELECT id, username, role FROM users ORDER BY role, username");
if ($resU && $resU->num_rows) {
    while ($row = $resU->fetch_assoc()) {
        $users[] = $row;
    }
}

$selectedUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

// Fetch permissions
$perms = [];
$resP = $conn->query("SELECT id, code, label FROM permissions ORDER BY code ASC");
if ($resP && $resP->num_rows) {
    while ($row = $resP->fetch_assoc()) {
        $perms[] = $row;
    }
}

$userOverrides = [];
if ($selectedUserId > 0) {
    $stmt = $conn->prepare("SELECT permission_id FROM user_permissions WHERE user_id = ?");
    $stmt->bind_param("i", $selectedUserId);
    $stmt->execute();
    $r2 = $stmt->get_result();
    if ($r2 && $r2->num_rows) {
        while ($row = $r2->fetch_assoc()) {
            $userOverrides[] = (int)$row['permission_id'];
        }
    }
    $stmt->close();
}

include "includes/header.php";
include "includes/sidebar.php";
?>
<header class="sa-topbar">
    <div class="sa-topbar-left">
        <div class="sa-topbar-title">User Permission Overrides</div>
        <div class="sa-topbar-sub">
            Give or remove specific permissions for individual users. This is applied on top of their role.
        </div>
    </div>
</header>

<main class="sa-content">
    <section class="sa-card">
        <div class="sa-card-header">
            <div class="sa-card-title">Select User</div>
        </div>
        <form method="get" style="margin-top:6px;">
            <select name="user_id" class="sa-select" onchange="this.form.submit()">
                <option value="0">Choose a user...</option>
                <?php foreach ($users as $u): ?>
                    <option value="<?php echo (int)$u['id']; ?>" <?php echo $selectedUserId === (int)$u['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($u['username'] . ' (' . $u['role'] . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
        <div class="sa-card-sub" style="margin-top:6px;">
            Overrides add or remove specific permissions for this user, on top of their base role.
        </div>
    </section>

    <?php if ($selectedUserId > 0): ?>
        <section class="sa-card" style="margin-top:10px;">
            <div class="sa-card-header">
                <div class="sa-card-title">Overrides for selected user</div>
            </div>

            <?php if (empty($perms)): ?>
                <div class="sa-card-sub">No permissions defined.</div>
            <?php else: ?>
                <table class="sa-table" id="userPermTable" data-user-id="<?php echo $selectedUserId; ?>">
                    <thead>
                        <tr>
                            <th style="width:28%;">Code</th>
                            <th style="width:52%;">Label</th>
                            <th style="width:20%;">Override</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($perms as $p): ?>
                        <?php
                            $pid = (int)$p['id'];
                            $checked = in_array($pid, $userOverrides);
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($p['code']); ?></td>
                            <td><?php echo htmlspecialchars($p['label']); ?></td>
                            <td>
                                <input type="checkbox"
                                       class="up-toggle"
                                       data-permission-id="<?php echo $pid; ?>"
                                       <?php echo $checked ? 'checked' : ''; ?>>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="sa-card-sub" id="upStatus" style="margin-top:6px;">
                    Changes are saved automatically when you toggle a checkbox.
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const table = document.getElementById('userPermTable');
    if (!table) return;
    const userId = table.getAttribute('data-user-id');
    const statusEl = document.getElementById('upStatus');

    table.addEventListener('change', function(e) {
        if (!e.target.classList.contains('up-toggle')) return;
        const cb = e.target;
        const permId = cb.getAttribute('data-permission-id');
        const allowed = cb.checked ? 1 : 0;

        if (statusEl) statusEl.textContent = 'Saving...';

        fetch('permissions-ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: new URLSearchParams({
                mode: 'toggle_user',
                user_id: userId,
                permission_id: permId,
                allowed: allowed
            })
        })
        .then(r => r.json())
        .then(data => {
            if (statusEl) {
                if (data.success) {
                    statusEl.textContent = 'Saved ✔';
                } else {
                    statusEl.textContent = 'Error: ' + (data.message || 'Unable to save');
                }
            }
        })
        .catch(err => {
            if (statusEl) statusEl.textContent = 'Request failed.';
        });
    });
});
</script>

<?php include "includes/footer.php"; ?>
