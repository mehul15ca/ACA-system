<?php
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit;
}

// Daily cron to create expense instances from recurring templates
include __DIR__ . "/../config.php";

$today = new DateTimeImmutable('today');
$todayStr = $today->format('Y-m-d');

// Fetch active templates whose next_run_date <= today
$stmt = $conn->prepare("
    SELECT * FROM recurring_expenses
    WHERE status = 'active' AND next_run_date <= ?
");
$stmt->bind_param("s", $todayStr);
$stmt->execute();
$res = $stmt->get_result();

while ($tpl = $res->fetch_assoc()) {
    $tplId = (int)$tpl['id'];

    $expenseDate = $tpl['next_run_date'];
    $category = $tpl['category'];
    $baseAmount = (float)$tpl['base_amount'];
    $taxAmount = (float)$tpl['tax_amount'];
    $totalAmount = (float)$tpl['total_amount'];

    $description = $tpl['title'];

    $stmtIns = $conn->prepare("
        INSERT INTO expenses
            (expense_date, category, amount, tax_amount, total_amount,
             description, status, is_recurring_instance, recurring_id)
        VALUES
            (?, ?, ?, ?, ?, ?, 'paid', 1, ?)
    ");
    $stmtIns->bind_param(
        "ssdddsi",
        $expenseDate, $category, $baseAmount, $taxAmount, $totalAmount,
        $description, $tplId
    );
    $stmtIns->execute();

    // Calculate next_run_date = next month same day (safe up to 28)
    $current = new DateTimeImmutable($tpl['next_run_date']);
    $next = $current->modify('+1 month');
    $nextRun = $next->format('Y-m-d');

    $stmtUpd = $conn->prepare("
        UPDATE recurring_expenses
        SET next_run_date = ?
        WHERE id = ?
    ");
    $stmtUpd->bind_param("si", $nextRun, $tplId);
    $stmtUpd->execute();
}

echo "Recurring expenses cron executed on {$todayStr}";
