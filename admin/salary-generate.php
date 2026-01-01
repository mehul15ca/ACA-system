<?php
// This file is meant to be included from salary.php when POST[action]=generate.
// It expects $month, $year, $fromDate, $toDate, $conn to be defined.

$gen_msg = "";
$gen_errors = [];

// 1) Helper: get rate for coach/batch on a date
function getRateForSession($conn, $coach_id, $batch_id, $session_date) {
    $coach_id = intval($coach_id);
    $batch_id = intval($batch_id);
    $date = $conn->real_escape_string($session_date);

    // Batch-specific first
    $sqlBatch = "
        SELECT *
        FROM coach_salary_rates
        WHERE coach_id = {$coach_id}
          AND batch_id = {$batch_id}
          AND (effective_from IS NULL OR effective_from <= '{$date}')
          AND rate_type IN ('per_session','per_hour')
        ORDER BY effective_from DESC, created_at DESC
        LIMIT 1
    ";
    $resBatch = $conn->query($sqlBatch);
    if ($resBatch && $resBatch->num_rows > 0) {
        return $resBatch->fetch_assoc();
    }

    // Global rate (batch_id IS NULL)
    $sqlGlobal = "
        SELECT *
        FROM coach_salary_rates
        WHERE coach_id = {$coach_id}
          AND batch_id IS NULL
          AND (effective_from IS NULL OR effective_from <= '{$date}')
          AND rate_type IN ('per_session','per_hour')
        ORDER BY effective_from DESC, created_at DESC
        LIMIT 1
    ";
    $resGlobal = $conn->query($sqlGlobal);
    if ($resGlobal && $resGlobal->num_rows > 0) {
        return $resGlobal->fetch_assoc();
    }

    return null;
}

// 2) Generate per-session and per-hour lines from training_sessions
$sqlTS = "
    SELECT ts.id AS session_id,
           ts.session_date,
           ts.batch_id,
           ts.coach_id
    FROM training_sessions ts
    WHERE ts.session_date BETWEEN '{$fromDate}' AND '{$toDate}'
      AND ts.coach_id IS NOT NULL
      AND ts.batch_id IS NOT NULL
";
$resTS = $conn->query($sqlTS);
if ($resTS) {
    while ($ts = $resTS->fetch_assoc()) {
        $session_id  = intval($ts['session_id']);
        $session_date= $ts['session_date'];
        $batch_id    = intval($ts['batch_id']);
        $coach_id    = intval($ts['coach_id']);

        // Skip if salary already exists for this session_id
        $checkRes = $conn->query("
            SELECT id FROM coach_salary_sessions
            WHERE session_id = {$session_id}
              AND coach_id = {$coach_id}
        ");
        if ($checkRes && $checkRes->num_rows > 0) {
            continue;
        }

        // Find rate
        $rateRow = getRateForSession($conn, $coach_id, $batch_id, $session_date);
        if (!$rateRow) {
            $gen_errors[] = "No rate for coach {$coach_id}, batch {$batch_id} on {$session_date}";
            continue;
        }

        $rate_type   = $rateRow['rate_type'];
        $rate_amount = floatval($rateRow['rate_amount']);
        $hours = 0.0;
        $amount = 0.0;

        if ($rate_type === 'per_session') {
            $hours = 0.0;
            $amount = $rate_amount;
        } elseif ($rate_type === 'per_hour') {
            // compute hours from batch_schedule
            $dow = date('N', strtotime($session_date)); // 1 (Mon) ... 7 (Sun)
            $sqlH = "
                SELECT
                    SUM(TIMESTAMPDIFF(MINUTE, start_time, end_time)) AS total_minutes
                FROM batch_schedule
                WHERE batch_id = {$batch_id}
                  AND coach_id = {$coach_id}
                  AND day_of_week = {$dow}
            ";
            $resH = $conn->query($sqlH);
            $total_minutes = 0;
            if ($resH && ($rowH = $resH->fetch_assoc())) {
                $total_minutes = intval($rowH['total_minutes']);
            }
            if ($total_minutes <= 0) {
                // no schedule found; skip this session
                $gen_errors[] = "No schedule hours for coach {$coach_id}, batch {$batch_id} on weekday {$dow}";
                continue;
            }
            $hours = round($total_minutes / 60, 2);
            $amount = round($hours * $rate_amount, 2);
        } else {
            // per_month is handled separately; skip here
            continue;
        }

        if ($amount <= 0) {
            continue;
        }

        $monthVal = intval(date('n', strtotime($session_date)));
        $yearVal  = intval(date('Y', strtotime($session_date)));

        $sqlIns = "
            INSERT INTO coach_salary_sessions
                (coach_id, session_id, batch_id, session_date, hours, rate_type, rate_amount, amount, month, year, status)
            VALUES
                ({$coach_id}, {$session_id}, {$batch_id}, '{$session_date}', {$hours},
                 '{$rate_type}', {$rate_amount}, {$amount}, {$monthVal}, {$yearVal}, 'unpaid')
        ";
        if (!$conn->query($sqlIns)) {
            $gen_errors[] = "Insert error for session {$session_id}: " . $conn->error;
        }
    }
}

// 3) Generate monthly fixed lines (per_month)
$sqlMonthlyRates = "
    SELECT r.*, c.name AS coach_name, b.name AS batch_name
    FROM coach_salary_rates r
    JOIN coaches c ON r.coach_id = c.id
    LEFT JOIN batches b ON r.batch_id = b.id
    WHERE r.rate_type='per_month'
      AND (r.effective_from IS NULL OR r.effective_from <= '{$toDate}')
";
$resMR = $conn->query($sqlMonthlyRates);
if ($resMR) {
    while ($r = $resMR->fetch_assoc()) {
        $coach_id = intval($r['coach_id']);
        $batch_id = $r['batch_id'] ? intval($r['batch_id']) : 0;
        $rate_amount = floatval($r['rate_amount']);

        // Check if a monthly line already exists for this coach+batch+month+year
        $check = $conn->query("
            SELECT id FROM coach_salary_sessions
            WHERE coach_id = {$coach_id}
              AND batch_id = {$batch_id}
              AND month = {$month}
              AND year = {$year}
              AND rate_type='per_month'
        ");
        if ($check && $check->num_rows > 0) {
            continue;
        }

        $session_date = date('Y-m-t', strtotime($fromDate)); // last day of month
        $hours = 0.0;
        $amount = $rate_amount;

        $sqlInsM = "
            INSERT INTO coach_salary_sessions
                (coach_id, session_id, batch_id, session_date, hours, rate_type, rate_amount, amount, month, year, status)
            VALUES
                ({$coach_id}, NULL, {$batch_id}, '{$session_date}', {$hours},
                 'per_month', {$rate_amount}, {$amount}, {$month}, {$year}, 'unpaid')
        ";
        if (!$conn->query($sqlInsM)) {
            $gen_errors[] = "Insert error for monthly salary coach {$coach_id}: " . $conn->error;
        }
    }
}

if (!empty($gen_errors)) {
    $message = "Generation completed with some warnings. Check rate & schedule settings.";
} else {
    $success = "Salary lines generated / refreshed successfully for this month.";
}
