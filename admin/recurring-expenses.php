<?php
include "../config.php";
checkLogin();
$role = currentUserRole();
if (!in_array($role, ['admin','superadmin'])) {
    http_response_code(403);
    echo "Access denied.";
    exit;
}

$res = $conn->query("SELECT * FROM recurring_expenses ORDER BY status DESC, day_of_month ASC, id DESC");
$rows = [];
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $rows[] = $r;
    }
}
?>
<?php include "includes/header.php"; ?>
<?php include "includes/sidebar.php"; ?>

<h1>Recurring Expenses</h1>
<p style="font-size:13px;color:#9ca3af;margin-bottom:12px;">
    Define monthly recurring expenses like rent, subscriptions, etc. Cron will auto-create instances in the Expenses table.
</p>

<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
        <h2 class="card-title" style="margin:0;">Templates</h2>
        <a href="recurring-expenses-edit.php" class="button-primary">+ Add Recurring Expense</a>
    </div>

    <?php if (!$rows): ?>
        <p style="font-size:13px;color:#9ca3af;">No recurring expenses defined yet.</p>
    <?php else: ?>
        <table class="table-basic">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Category</th>
                    <th>Amount</th>
                    <th>Day of Month</th>
                    <th>Next Run</th>
                    <th>Status</th>
                    <th style="width:80px;">Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?php echo htmlspecialchars($r['title']); ?></td>
                    <td><?php echo htmlspecialchars($r['category']); ?></td>
                    <td>
                        $<?php echo number_format((float)$r['total_amount'],2); ?>
                    </td>
                    <td><?php echo (int)$r['day_of_month']; ?></td>
                    <td><?php echo htmlspecialchars($r['next_run_date']); ?></td>
                    <td>
                        <span class="status-pill <?php echo $r['status'] === 'active' ? 'status-active' : 'status-inactive'; ?>">
                            <?php echo ucfirst($r['status']); ?>
                        </span>
                    </td>
                    <td>
                        <a href="recurring-expenses-edit.php?id=<?php echo (int)$r['id']; ?>" class="link-small">Edit</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php include "includes/footer.php"; ?>
