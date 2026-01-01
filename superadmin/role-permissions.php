<?php
include "../config.php";
checkLogin();
requireSuperadmin();

$validRoles = ['superadmin','admin','coach','student'];
$selectedRole = (isset($_GET['role']) && in_array($_GET['role'], $validRoles)) ? $_GET['role'] : 'admin';

// Get all permissions
$perms = [];
$res = $conn->query("SELECT id, code, label FROM permissions ORDER BY code ASC");
if ($res && $res->num_rows) {
    while ($row = $res->fetch_assoc()) {
        $perms[] = $row;
    }
}

// Get role permissions
$rolePermIds = [];
$stmt = $conn->prepare("SELECT permission_id FROM role_permissions WHERE role = ?");
$stmt->bind_param("s", $selectedRole);
$stmt->execute();
$r2 = $stmt->get_result();
if ($r2 && $r2->num_rows) {
    while ($row = $r2->fetch_assoc()) {
        $rolePermIds[] = (int)$row['permission_id'];
    }
}
$stmt->close();

include "includes/header.php";
include "includes/sidebar.php";
?>
<header class="sa-topbar">
    <div class="sa-topbar-left">
        <div class="sa-topbar-title">Role Permissions</div>
        <div class="sa-topbar-sub">
            Control what each role is allowed to do. Changes apply immediately.
        </div>
    </div>
</header>

<main class="sa-content">
    <section class="sa-card">
        <div class="sa-card-header">
            <div class="sa-card-title">Select Role</div>
        </div>
        <form method="get" style="margin-top:6px;">
            <select name="role" class="sa-select" onchange="this.form.submit()">
                <?php foreach ($validRoles as $r): ?>
                    <option value="<?php echo $r; ?>" <?php echo $r === $selectedRole ? 'selected' : ''; ?>>
                        <?php echo ucfirst($r); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
        <div class="sa-card-sub" style="margin-top:6px;">
            Superadmin always has full access, regardless of these settings.
        </div>
    </section>

    <section class="sa-card" style="margin-top:10px;">
        <div class="sa-card-header">
            <div class="sa-card-title">Permissions for role: <?php echo ucfirst($selectedRole); ?></div>
        </div>

        <?php if (empty($perms)): ?>
            <div class="sa-card-sub">No permissions found.</div>
        <?php else: ?>
            <table class="sa-table" id="rolePermTable" data-role="<?php echo htmlspecialchars($selectedRole); ?>">
                <thead>
                    <tr>
                        <th style="width:28%;">Code</th>
                        <th style="width:52%;">Label</th>
                        <th style="width:20%;">Allowed</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($perms as $p): ?>
                    <?php
                        $pid = (int)$p['id'];
                        $checked = in_array($pid, $rolePermIds);
                        $disabled = ($selectedRole === 'superadmin') ? 'disabled' : '';
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($p['code']); ?></td>
                        <td><?php echo htmlspecialchars($p['label']); ?></td>
                        <td>
                            <input type="checkbox"
                                   class="rp-toggle"
                                   data-permission-id="<?php echo $pid; ?>"
                                   <?php echo $checked ? 'checked' : ''; ?>
                                   <?php echo $disabled; ?>>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php if ($selectedRole === 'superadmin'): ?>
                <div class="sa-card-sub" style="margin-top:6px;">
                    Superadmin always has all permissions. Checkboxes are disabled for this role.
                </div>
            <?php else: ?>
                <div class="sa-card-sub" id="rpStatus" style="margin-top:6px;">
                    Changes are saved automatically when you toggle a checkbox.
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const table = document.getElementById('rolePermTable');
    if (!table) return;
    const role = table.getAttribute('data-role');
    const statusEl = document.getElementById('rpStatus');

    table.addEventListener('change', function(e) {
        if (!e.target.classList.contains('rp-toggle')) return;
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
                mode: 'toggle_role',
                role: role,
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
