<?php
require __DIR__ . '/../includes/cron_guard.php';
// This script should be called daily (via cron or manually) to generate
// monthly invoices based on each student's join_date (billing day).
// Logic:
// - For each active student with join_date set
// - If DAY(join_date) == today_day
// - And there is no existing invoice for this student with period_from = first day of THIS month
//   (status not cancelled)
// - Then create an invoice for the month (current month dates)
require_once __DIR__ . '/_bootstrap.php';
include "fees-helpers.php";

date_default_timezone_set('America/Toronto');

$today = date('Y-m-d');
$day   = date('d', strtotime($today));

list($period_from, $period_to) = aca_month_period_for_date($today);

// choose default currency & base amount per student using their batch or a default plan
// For now we'll use a simple default: you pick a default monthly plan_id, or
// leave NULL and fill amount manually later.

$default_plan_id = null; // you can set a specific plan ID here
$default_amount  = 0.00; // if 0, we will skip student (no auto-fee known)

$created = 0;
$skipped = 0;

$sql = "SELECT id, admission_no, first_name, last_name, join_date FROM students WHERE status='active' AND join_date IS NOT NULL";
$res = $conn->query($sql);

while ($s = $res->fetch_assoc()) {
    $sid = (int)$s['id'];
    $join = $s['join_date'];
    $joinDay = date('d', strtotime($join));

    if ($joinDay !== $day) {
        $skipped++;
        continue;
    }

    // check existing invoice for this student & month
    $stmtC = $conn->prepare("
        SELECT COUNT(*) AS cnt
        FROM fees_invoices
        WHERE student_id = ?
          AND period_from = ?
          AND status <> 'cancelled'
    ");
    $stmtC->bind_param("is", $sid, $period_from);
    $stmtC->execute();
    $cntRow = $stmtC->get_result()->fetch_assoc();
    if ($cntRow['cnt'] > 0) {
        $skipped++;
        continue; // already billed this month
    }

    // Determine amount & plan_id
    $plan_id = $default_plan_id;
    $amount  = $default_amount;
    $currency = "CAD";

    if ($plan_id !== null) {
        $stmtP = $conn->prepare("SELECT amount, currency FROM fees_plans WHERE id = ?");
        $stmtP->bind_param("i", $plan_id);
        $stmtP->execute();
        $plan = $stmtP->get_result()->fetch_assoc();
        if ($plan) {
            $amount  = floatval($plan['amount']);
            $currency= $plan['currency'];
        }
    }

    if ($amount <= 0) {
        // No defined amount, skip auto creation
        $skipped++;
        continue;
    }

    $due_date = $today; // due date can be same as generation date; adjust manually if needed
    $invoice_no = aca_generate_invoice_no($conn, $due_date);
    $status = 'unpaid';

    $stmtI = $conn->prepare("
        INSERT INTO fees_invoices
            (invoice_no, student_id, plan_id, amount, currency, due_date, status, period_from, period_to)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmtI->bind_param(
        "siidsssss",
        $invoice_no, $sid, $plan_id, $amount, $currency, $due_date, $status, $period_from, $period_to
    );
    if ($stmtI->execute()) {
        $created++;
    } else {
        // log error if needed
    }
}

echo "Auto generation complete. Created: $created, skipped: $skipped, date: $today, period: $period_from to $period_to.";
