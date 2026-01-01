<?php
include "../config.php";
checkLogin();
requireSuperadmin();

include "includes/header.php";
include "includes/sidebar.php";

// Fetch all permissions
$perms = [];
$res = $conn->query("SELECT id, code, label, created_at FROM permissions ORDER BY code ASC");
if ($res && $res->num_rows) {
    while ($row = $res->fetch_assoc()) {
        $perms[] = $row;
    }
}
?>
<header class="sa-topbar">
    <div class="sa-topbar-left">
        <div class="sa-topbar-title">Permissions Catalog</div>
        <div class="sa-topbar-sub">
            Master list of all actions your ACA system understands. Roles & users are granted access using these codes.
        </div>
    </div>
</header>

<main class="sa-content">
    <section class="sa-card">
        <div class="sa-card-header">
            <div class="sa-card-title">Overview</div>
        </div>
        <div class="sa-card-small">
            • Use <strong>Role Permissions</strong> to define what each role (admin, coach, student) can do.<br>
            • Use <strong>User Overrides</strong> to give or remove specific permissions for individual users.
        </div>
        <div style="margin-top:8px; display:flex; gap:8px; flex-wrap:wrap;">
            <button class="sa-btn-primary" onclick="window.location.href='role-permissions.php'">
                Manage Role Permissions
            </button>
            <button class="sa-btn" onclick="window.location.href='user-permissions.php'">
                User Overrides
            </button>
        </div>
    </section>

    <section class="sa-card" style="margin-top:10px;">
        <div class="sa-card-header">
            <div class="sa-card-title">All Permissions</div>
        </div>
        <?php if (empty($perms)): ?>
            <div class="sa-card-sub">No permissions defined yet.</div>
        <?php else: ?>
            <table class="sa-table">
                <thead>
                    <tr>
                        <th style="width:32%;">Code</th>
                        <th style="width:40%;">Label</th>
                        <th style="width:18%;">Created</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($perms as $p): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($p['code']); ?></td>
                        <td><?php echo htmlspecialchars($p['label']); ?></td>
                        <td><?php echo htmlspecialchars($p['created_at']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>
</main>

<?php include "includes/footer.php"; ?>
