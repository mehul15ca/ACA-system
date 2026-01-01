<?php
require __DIR__ . '/../includes/cron_guard.php';
// Daily fees reminders processor
// Run via: http://localhost/ACA-System/cron/fees-reminders-daily.php (or your domain)

include "../config.php";

date_default_timezone_set('America/Toronto'); // adjust if needed

$today = new DateTimeImmutable('today');
$todayStr = $today->format('Y-m-d');

// Reminder offsets
$daysBeforeDue = 3; // 3 days before due date
$superadminEmail = "mehul15.ca@gmail.com";

$summary = [
    'before_due_sent' => 0,
    'on_due_sent' => 0,
    'before_due_failed' => 0,
    'on_due_failed' => 0,
];

function aca_date($dt) {
    return (new DateTimeImmutable($dt))->format('Y-m-d');
}

// Find invoices for reminders
// 1) 3 days before due date
$targetBefore = $today->modify("+{$daysBeforeDue} days")->format('Y-m-d');
// 2) on due date
$targetOn = $todayStr;

// Helper: outstanding balance
function get_outstanding_balance($conn, $invoice_id) {
    $invoice_id = (int)$invoice_id;
    $resInv = $conn->query("SELECT amount FROM fees_invoices WHERE id={$invoice_id}");
    if (!$resInv || !$inv = $resInv->fetch_assoc()) {
        return null;
    }
    $amount = (float)$inv['amount'];
    $paid = 0.0;
    $resPay = $conn->query("SELECT SUM(amount) AS total_paid FROM fees_payments WHERE invoice_id={$invoice_id}");
    if ($resPay && ($rowP = $resPay->fetch_assoc()) && $rowP['total_paid'] !== null) {
        $paid = (float)$rowP['total_paid'];
    }
    return $amount - $paid;
}

// Helper: has reminder already logged?
function has_reminder_logged($conn, $invoice_id, $student_id, $type) {
    $stmt = $conn->prepare("
        SELECT id FROM fees_reminders_log
        WHERE invoice_id = ? AND student_id = ? AND reminder_type = ?
        LIMIT 1
    ");
    $stmt->bind_param("iis", $invoice_id, $student_id, $type);
    $stmt->execute();
    $stmt->store_result();
    $exists = $stmt->num_rows > 0;
    $stmt->close();
    return $exists;
}

// Process block for a given target date and type
function process_reminders_for_date($conn, $targetDate, $type, &$summary) {
    $typeStr = $type; // 'before_due' or 'on_due'
    $typeLabel = ($type === 'before_due') ? '3 days before due date' : 'on due date';

    $sql = "
        SELECT fi.id AS invoice_id,
               fi.invoice_no,
               fi.student_id,
               fi.amount,
               fi.currency,
               fi.due_date,
               fi.status,
               s.first_name,
               s.last_name,
               s.admission_no,
               s.email
        FROM fees_invoices fi
        JOIN students s ON fi.student_id = s.id
        WHERE fi.status IN ('unpaid','partial')
          AND fi.due_date = '".$conn->real_escape_string($targetDate)."'
    ";
    $res = $conn->query($sql);
    if (!$res) {
        return;
    }

    while ($row = $res->fetch_assoc()) {
        $invoice_id = (int)$row['invoice_id'];
        $student_id = (int)$row['student_id'];
        $email = trim($row['email']);

        if (has_reminder_logged($conn, $invoice_id, $student_id, $typeStr)) {
            // already reminded for this type
            continue;
        }

        $statusField = 'sent';
        $errorMessage = '';

        if ($email === '') {
            $statusField = 'failed';
            $errorMessage = 'No student email on record.';
        } else {
            $outstanding = get_outstanding_balance($conn, $invoice_id);
            if ($outstanding === null) {
                $statusField = 'failed';
                $errorMessage = 'Unable to calculate outstanding balance.';
            } else {
                // Prepare email
                $due = $row['due_date'];
                $amount = (float)$row['amount'];
                $currency = $row['currency'] ?: 'CAD';
                $studentName = trim($row['first_name'].' '.$row['last_name']);
                $admission = $row['admission_no'];
                $invoiceNo = $row['invoice_no'];

                $subject = "Fee Reminder – Invoice {$invoiceNo}";
                $body = "Dear {$studentName},\n\n".
                    "This is a reminder regarding your fee invoice at Australasia Cricket Academy.\n\n".
                    "Student ID: {$admission}\n".
                    "Invoice No: {$invoiceNo}\n".
                    "Invoice Amount: ".number_format($amount, 2)." {$currency}\n".
                    "Outstanding Balance: ".number_format($outstanding, 2)." {$currency}\n".
                    "Due Date: {$due}\n".
                    "Reminder Type: {$typeLabel}\n\n".
                    "At this time, online payment is not yet enabled in the portal, but you may settle the amount through the academy's accepted payment methods. ".
                    "Once online payments are enabled, a direct Pay Now button will be provided.\n\n".
                    "If you have already paid, please ignore this message.\n\n".
                    "Thank you.\n".
                    "Australasia Cricket Academy";

                $emailEsc = $conn->real_escape_string($email);
                $subEsc = $conn->real_escape_string($subject);
                $bodyEsc = $conn->real_escape_string($body);

                // Insert into notifications_queue
                $conn->query("
                    INSERT INTO notifications_queue
                        (user_id, receiver_email, channel, subject, message, status, template_code)
                    VALUES
                        (NULL, '{$emailEsc}', 'email', '{$subEsc}', '{$bodyEsc}', 'pending', 'FEES_REMINDER_".strtoupper($typeStr)."')
                ");

                if ($conn->error) {
                    $statusField = 'failed';
                    $errorMessage = "Failed to enqueue email: ".$conn->error;
                }
            }
        }

        // Log reminder
        $stmtLog = $conn->prepare("
            INSERT INTO fees_reminders_log
                (invoice_id, student_id, reminder_type, channel, status, error_message, sent_at)
            VALUES
                (?, ?, ?, 'email', ?, ?, NOW())
        ");
        $stmtLog->bind_param("iisss", $invoice_id, $student_id, $typeStr, $statusField, $errorMessage);
        $stmtLog->execute();
        $stmtLog->close();

        if ($typeStr === 'before_due') {
            if ($statusField === 'sent') $summary['before_due_sent']++;
            else $summary['before_due_failed']++;
        } else {
            if ($statusField === 'sent') $summary['on_due_sent']++;
            else $summary['on_due_failed']++;
        }
    }
}

// Run the two types
process_reminders_for_date($conn, $targetBefore, 'before_due', $summary);
process_reminders_for_date($conn, $targetOn, 'on_due', $summary);

// If there were any reminders, queue summary email to superadmin
if ($summary['before_due_sent'] > 0 || $summary['on_due_sent'] > 0 ||
    $summary['before_due_failed'] > 0 || $summary['on_due_failed'] > 0) {

    $subject = "Daily Fees Reminder Summary – ".$todayStr;
    $body = "Daily fees reminder run completed.\n\n".
        "3 days before due: sent = ".$summary['before_due_sent'].", failed = ".$summary['before_due_failed']."\n".
        "On due date: sent = ".$summary['on_due_sent'].", failed = ".$summary['on_due_failed']."\n\n".
        "You can review detailed logs in the Fees Reminders Log in the admin panel.\n";

    $adminEsc = $conn->real_escape_string($superadminEmail);
    $subEsc = $conn->real_escape_string($subject);
    $bodyEsc = $conn->real_escape_string($body);

    $conn->query("
        INSERT INTO notifications_queue
            (user_id, receiver_email, channel, subject, message, status, template_code)
        VALUES
            (NULL, '{$adminEsc}', 'email', '{$subEsc}', '{$bodyEsc}', 'pending', 'FEES_REMINDER_DAILY_SUMMARY')
    ");
}

// Monthly report on 1st of each month
if ($today->format('d') === '01') {
    $sqlUnpaid = "
        SELECT fi.invoice_no, fi.student_id, fi.amount, fi.currency, fi.due_date,
               s.first_name, s.last_name, s.admission_no
        FROM fees_invoices fi
        JOIN students s ON fi.student_id = s.id
        WHERE fi.status IN ('unpaid','partial')
        ORDER BY fi.due_date ASC
    ";
    $resUnpaid = $conn->query($sqlUnpaid);

    $lines = [];
    if ($resUnpaid && $resUnpaid->num_rows > 0) {
        while ($row = $resUnpaid->fetch_assoc()) {
            $outstanding = get_outstanding_balance($conn, $row['invoice_no']); // bug, will fix below
        }
    }
    // We'll re-run properly to build lines:
    $lines = [];
    if ($resUnpaid && $resUnpaid->num_rows > 0) {
        $resUnpaid->data_seek(0);
        while ($row = $resUnpaid->fetch_assoc()) {
            $invoice_id = null;
            // we don't have fi.id in select; let's fix by requery
        }
    }
    // Simpler: run again with id
    $sqlUnpaid2 = "
        SELECT fi.id, fi.invoice_no, fi.student_id, fi.amount, fi.currency, fi.due_date,
               s.first_name, s.last_name, s.admission_no
        FROM fees_invoices fi
        JOIN students s ON fi.student_id = s.id
        WHERE fi.status IN ('unpaid','partial')
        ORDER BY fi.due_date ASC
    ";
    $resUnpaid2 = $conn->query($sqlUnpaid2);
    if ($resUnpaid2 && $resUnpaid2->num_rows > 0) {
        while ($row2 = $resUnpaid2->fetch_assoc()) {
            $outstanding = get_outstanding_balance($conn, $row2['id']);
            if ($outstanding === null) $outstanding = 0.0;
            $lines[] =
                "Invoice: ".$row2['invoice_no'].
                " | Student: ".$row2['admission_no']." - ".$row2['first_name']." ".$row2['last_name'].
                " | Due: ".$row2['due_date'].
                " | Amount: ".number_format($row2['amount'],2)." ".$row2['currency'].
                " | Outstanding: ".number_format($outstanding,2)." ".$row2['currency'];
        }
    }

    $bodyMonthly = "Monthly unpaid invoices report – as of ".$todayStr."\n\n";
    if (empty($lines)) {
        $bodyMonthly .= "No unpaid or partial invoices at this time.\n";
    } else {
        $bodyMonthly .= implode("\n", $lines);
    }

    $subjectMonthly = "Monthly Unpaid Invoices Report – ".$todayStr;
    $adminEsc = $conn->real_escape_string($superadminEmail);
    $subMEsc = $conn->real_escape_string($subjectMonthly);
    $bodyMEsc = $conn->real_escape_string($bodyMonthly);

    $conn->query("
        INSERT INTO notifications_queue
            (user_id, receiver_email, channel, subject, message, status, template_code)
        VALUES
            (NULL, '{$adminEsc}', 'email', '{$subMEsc}', '{$bodyMEsc}', 'pending', 'FEES_REMINDER_MONTHLY_REPORT')
    ");
}

echo "Fees reminders daily job completed.";
