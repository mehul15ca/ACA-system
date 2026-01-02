<?php
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit;
}

require __DIR__ . '/../includes/cron_guard.php';
// Fees reminders cron – with parent_email CC support
include "../config.php";

// Optional: simple IP or key check can be added here

$today = new DateTime();
$todayDate = $today->format('Y-m-d');

// --- CONFIG --- //
$baseUrl = "http://localhost/ACA-System";
$superadminEmail = "mehul15.ca@gmail.com";

// Helper to queue notification
function queue_notification($conn, $userId, $toEmail, $ccEmail, $subject, $message, $templateCode) {
    $channel = 'email';
    $status = 'pending';

    $sql = "INSERT INTO notifications_queue
            (user_id, receiver_email, cc_email, channel, subject, message, status, template_code, created_at)
            VALUES (?,?,?,?,?,?,?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "isssssss",
        $userId,
        $toEmail,
        $ccEmail,
        $channel,
        $subject,
        $message,
        $status,
        $templateCode
    );
    $stmt->execute();
    return $conn->insert_id;
}

// --- A) 3 days BEFORE due date ---
$beforeDue = (new DateTime())->modify('+3 days')->format('Y-m-d');

$sqlBefore = "
    SELECT i.id AS invoice_id,
           i.invoice_no,
           i.due_date,
           i.amount,
           s.id AS student_id,
           s.first_name,
           s.last_name,
           s.email,
           s.parent_email
    FROM fees_invoices i
    JOIN students s ON s.id = i.student_id
    WHERE i.status IN ('unpaid','partial')
      AND i.due_date = ?
";
$stmtBefore = $conn->prepare($sqlBefore);
$stmtBefore->bind_param("s", $beforeDue);
$stmtBefore->execute();
$resBefore = $stmtBefore->get_result();

while ($row = $resBefore->fetch_assoc()) {
    $studentEmail = $row['email'];
    if (!$studentEmail) continue;

    $parentEmail = $row['parent_email'] ?? '';
    $ccEmail = $parentEmail !== '' ? $parentEmail : null;

    $subject = "Fee Reminder – Invoice ".$row['invoice_no'];
    $loginLink = $baseUrl."/my-fees.php";
    $msg = "Dear ".$row['first_name'].",\n\n".
           "This is a friendly reminder that your invoice ".$row['invoice_no']." ".
           "of amount $".$row['amount']." is due on ".$row['due_date'].".\n\n".
           "You can review your fees at: ".$loginLink."\n\n".
           "Australasia Cricket Academy";

    $notifId = queue_notification(
        $conn,
        $row['student_id'],
        $studentEmail,
        $ccEmail,
        $subject,
        $msg,
        "FEES_BEFORE_DUE"
    );

    $stmtLog = $conn->prepare("
        INSERT INTO fees_reminder_logs (invoice_id, student_id, reminder_type, sent_for_date, notification_id, status)
        VALUES (?, ?, 'before_due', ?, ?, 'queued')
    ");
    $stmtLog->bind_param("iisi", $row['invoice_id'], $row['student_id'], $beforeDue, $notifId);
    $stmtLog->execute();
}

// --- B) ON due date ---
$onDue = $todayDate;

$sqlOn = "
    SELECT i.id AS invoice_id,
           i.invoice_no,
           i.due_date,
           i.amount,
           s.id AS student_id,
           s.first_name,
           s.last_name,
           s.email,
           s.parent_email
    FROM fees_invoices i
    JOIN students s ON s.id = i.student_id
    WHERE i.status IN ('unpaid','partial')
      AND i.due_date = ?
";
$stmtOn = $conn->prepare($sqlOn);
$stmtOn->bind_param("s", $onDue);
$stmtOn->execute();
$resOn = $stmtOn->get_result();

while ($row = $resOn->fetch_assoc()) {
    $studentEmail = $row['email'];
    if (!$studentEmail) continue;

    $parentEmail = $row['parent_email'] ?? '';
    $ccEmail = $parentEmail !== '' ? $parentEmail : null;

    $subject = "Fee Due Today – Invoice ".$row['invoice_no'];
    $loginLink = $baseUrl."/my-fees.php";
    $msg = "Dear ".$row['first_name'].",\n\n".
           "Your invoice ".$row['invoice_no']." of amount $".$row['amount']." is due today (".$row['due_date'].").\n\n".
           "Please complete the payment at: ".$loginLink."\n\n".
           "Australasia Cricket Academy";

    $notifId = queue_notification(
        $conn,
        $row['student_id'],
        $studentEmail,
        $ccEmail,
        $subject,
        $msg,
        "FEES_ON_DUE"
    );

    $stmtLog = $conn->prepare("
        INSERT INTO fees_reminder_logs (invoice_id, student_id, reminder_type, sent_for_date, notification_id, status)
        VALUES (?, ?, 'on_due', ?, ?, 'queued')
    ");
    $stmtLog->bind_param("iisi", $row['invoice_id'], $row['student_id'], $onDue, $notifId);
    $stmtLog->execute();
}

// --- C) Monthly summary to superadmin on 1st of month ---
if ((int)$today->format('d') === 1) {
    $year = (int)$today->format('Y');
    $month = (int)$today->format('m');

    $monthStart = sprintf("%04d-%02d-01 00:00:00", $year, $month);
    $monthEndObj = new DateTime($monthStart);
    $monthEndObj->modify('last day of this month')->setTime(23,59,59);
    $monthEnd = $monthEndObj->format("Y-m-d H:i:s");

    $stmtUnpaid = $conn->prepare("
        SELECT COUNT(*) AS cnt, SUM(amount) AS total
        FROM fees_invoices
        WHERE status IN ('unpaid','partial')
          AND due_date BETWEEN ? AND ?
    ");
    $stmtUnpaid->bind_param("ss", $monthStart, $monthEnd);
    $stmtUnpaid->execute();
    $rowUnpaid = $stmtUnpaid->get_result()->fetch_assoc();
    $cntUnpaid = (int)($rowUnpaid['cnt'] ?? 0);
    $totalUnpaid = (float)($rowUnpaid['total'] ?? 0);

    $subject = "Monthly Fees Summary – {$year}-".str_pad($month,2,"0",STR_PAD_LEFT);
    $msg = "Monthly fees summary for {$year}-".str_pad($month,2,"0",STR_PAD_LEFT)."\n\n".
           "Unpaid / partial invoices: {$cntUnpaid}\n".
           "Total outstanding: $".number_format($totalUnpaid,2)."\n\n".
           "Australasia Cricket Academy";

    queue_notification(
        $conn,
        null,
        $superadminEmail,
        null,
        $subject,
        $msg,
        "FEES_MONTHLY_SUMMARY"
    );
}

echo "Fees reminders cron executed.";
