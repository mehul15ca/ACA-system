<?php
include "../config.php";
checkLogin();

$role = currentUserRole();
if ($role !== 'student') {
    http_response_code(403);
    echo "Access denied. Students only.";
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$userId = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
if ($userId <= 0) {
    die("User session missing.");
}

// Find linked student
$stmtU = $conn->prepare("SELECT student_id FROM users WHERE id = ?");
$stmtU->bind_param("i", $userId);
$stmtU->execute();
$resU = $stmtU->get_result();
if ($resU->num_rows === 0) {
    die("User not found.");
}
$rowU = $resU->fetch_assoc();
$studentId = intval($rowU['student_id']);
if ($studentId <= 0) {
    die("No student linked to this login.");
}

// Student info
$stmtS = $conn->prepare("
    SELECT s.*, b.name AS batch_name, b.age_group
    FROM students s
    LEFT JOIN batches b ON s.batch_id = b.id
    WHERE s.id = ?
");
$stmtS->bind_param("i", $studentId);
$stmtS->execute();
$student = $stmtS->get_result()->fetch_assoc();
if (!$student) {
    die("Student record not found.");
}

date_default_timezone_set('America/Toronto');

// Month selection
$monthParam = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $monthParam)) {
    $monthParam = date('Y-m');
}
list($year, $month) = explode('-', $monthParam);
$year  = (int)$year;
$month = (int)$month;
$firstDay = sprintf('%04d-%02d-01', $year, $month);
$daysInMonth = (int)date('t', strtotime($firstDay));
$lastDay = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);

// Fetch student's attendance logs for month
$stmtL = $conn->prepare("
    SELECT log_date, log_time
    FROM attendance_logs
    WHERE student_id = ?
      AND log_date BETWEEN ? AND ?
    ORDER BY log_date ASC, log_time ASC
");
$stmtL->bind_param("iss", $studentId, $firstDay, $lastDay);
$stmtL->execute();
$resL = $stmtL->get_result();
$logsByDate = [];
while ($r = $resL->fetch_assoc()) {
    $d = $r['log_date'];
    if (!isset($logsByDate[$d])) {
        $logsByDate[$d] = [];
    }
    $logsByDate[$d][] = $r['log_time'];
}

// Load batch schedule to determine training days and late logic
$batchId = $student['batch_id'];
$scheduleByDay = []; // day_of_week => array of start_time
if ($batchId) {
    $sqlSch = "
        SELECT day_of_week, start_time, end_time
        FROM batch_schedule
        WHERE batch_id = ?
    ";
    $stmtSch = $conn->prepare($sqlSch);
    $stmtSch->bind_param("i", $batchId);
    $stmtSch->execute();
    $resSch = $stmtSch->get_result();
    while ($rowSch = $resSch->fetch_assoc()) {
        $dow = (int)$rowSch['day_of_week'];
        if (!isset($scheduleByDay[$dow])) {
            $scheduleByDay[$dow] = [];
        }
        $scheduleByDay[$dow][] = [
            'start_time' => $rowSch['start_time'],
            'end_time'   => $rowSch['end_time'],
        ];
    }
}

// Determine status for each day
$dayStatus = []; // date => present/late/absent/no_session
$totalTrainingDays = 0;
$presentCount = 0;
$lateCount = 0;
$absentCount = 0;

for ($d = 1; $d <= $daysInMonth; $d++) {
    $date = sprintf('%04d-%02d-%02d', $year, $month, $d);
    $dow = (int)date('N', strtotime($date)); // 1-7

    if (!isset($scheduleByDay[$dow]) || empty($scheduleByDay[$dow])) {
        $dayStatus[$date] = 'no_session';
        continue;
    }

    $totalTrainingDays++;

    if (!isset($logsByDate[$date])) {
        $dayStatus[$date] = 'absent';
        $absentCount++;
        continue;
    }

    // there is at least one attendance log this date
    $logTime = $logsByDate[$date][0]; // earliest tap
    $logTs = strtotime($date . ' ' . $logTime);
    $isLate = true;

    foreach ($scheduleByDay[$dow] as $sch) {
        $startTs = strtotime($date . ' ' . $sch['start_time']);
        $endTs   = strtotime($date . ' ' . $sch['end_time']);
        // only consider if inside scheduled window
        if ($logTs >= $startTs && $logTs <= $endTs) {
            // Late = more than 1 minute after scheduled start
            if ($logTs > $startTs + 60) {
                $isLate = true;
            } else {
                $isLate = false;
            }
            break;
        }
    }

    if ($isLate) {
        $dayStatus[$date] = 'late';
        $lateCount++;
    } else {
        $dayStatus[$date] = 'present';
        $presentCount++;
    }
}

$attendancePercent = $totalTrainingDays > 0 ? round(($presentCount / $totalTrainingDays) * 100) : 0;

// Build calendar weeks
$weeks = [];
$week = [];
$firstWeekday = (int)date('N', strtotime($firstDay)); // 1 (Mon) to 7 (Sun)
for ($i = 1; $i < $firstWeekday; $i++) {
    $week[] = null;
}
for ($day = 1; $day <= $daysInMonth; $day++) {
    $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
    $week[] = $date;
    if (count($week) == 7) {
        $weeks[] = $week;
        $week = [];
    }
}
if (!empty($week)) {
    while (count($week) < 7) {
        $week[] = null;
    }
    $weeks[] = $week;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>My Attendance - Australasia Cricket Academy</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            margin:0;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background:#050b12;
            color:#f9fafb;
        }
        .wrap {
            max-width:900px;
            margin:0 auto;
            padding:16px;
        }
        h1 {
            font-size:22px;
            margin:4px 0 2px;
        }
        .sub {
            font-size:12px;
            color:#9ca3af;
            margin-bottom:12px;
        }
        .card {
            background:#0b1724;
            border-radius:16px;
            padding:14px 16px;
            margin-bottom:14px;
            box-shadow:0 16px 30px rgba(0,0,0,0.55);
        }
        .profile {
            display:flex;
            align-items:center;
            gap:12px;
        }
        .avatar {
            width:48px;
            height:48px;
            border-radius:50%;
            background:#111827;
            display:flex;
            align-items:center;
            justify-content:center;
            font-size:20px;
        }
        .meta-main {
            font-size:13px;
        }
        .meta-main div.name {
            font-weight:600;
            font-size:15px;
        }
        .meta-main div.line {
            color:#9ca3af;
            font-size:12px;
        }
        .summary-grid {
            display:flex;
            flex-wrap:wrap;
            gap:10px;
            margin-top:8px;
        }
        .summary-item {
            flex:1 1 150px;
            padding:10px;
            border-radius:12px;
            background:#020617;
            font-size:12px;
        }
        .summary-label {
            color:#9ca3af;
            text-transform:uppercase;
            font-size:10px;
            letter-spacing:0.04em;
        }
        .summary-value {
            margin-top:4px;
            font-size:18px;
            font-weight:600;
        }
        .summary-note {
            font-size:10px;
            color:#6b7280;
            margin-top:2px;
        }
        .month-select {
            margin-top:8px;
        }
        .month-select label {
            font-size:12px;
        }
        .month-select input {
            padding:4px 6px;
            border-radius:8px;
            border:1px solid #374151;
            background:#020617;
            color:#f9fafb;
            font-size:12px;
        }
        .month-select button {
            padding:6px 10px;
            border-radius:999px;
            border:none;
            background:#1f8ef1;
            color:#fff;
            font-size:12px;
            margin-left:6px;
            cursor:pointer;
        }
        .calendar-card {
            margin-top:10px;
        }
        table.calendar {
            width:100%;
            border-collapse:collapse;
            table-layout:fixed;
        }
        table.calendar th {
            font-size:11px;
            padding:4px 0;
            color:#9ca3af;
        }
        table.calendar td {
            height:42px;
            padding:2px;
        }
        .day-box {
            border-radius:10px;
            height:100%;
            display:flex;
            align-items:flex-start;
            justify-content:flex-end;
            padding:3px 5px;
            font-size:10px;
            position:relative;
        }
        .day-box span {
            position:relative;
            z-index:2;
        }
        .day-present {
            background:linear-gradient(135deg,#16a34a,#22c55e);
            color:#052e16;
        }
        .day-late {
            background:linear-gradient(135deg,#f97316,#facc15);
            color:#451a03;
        }
        .day-absent {
            background:linear-gradient(135deg,#ef4444,#b91c1c);
            color:#fef2f2;
        }
        .day-no_session {
            background:#020617;
            color:#4b5563;
        }
        .legend {
            margin-top:8px;
            font-size:11px;
            color:#9ca3af;
            display:flex;
            gap:10px;
            flex-wrap:wrap;
        }
        .legend-item {
            display:flex;
            align-items:center;
            gap:4px;
        }
        .legend-dot {
            width:10px;
            height:10px;
            border-radius:50%;
        }
        .dot-present {
            background:linear-gradient(135deg,#16a34a,#22c55e);
        }
        .dot-late {
            background:linear-gradient(135deg,#f97316,#facc15);
        }
        .dot-absent {
            background:linear-gradient(135deg,#ef4444,#b91c1c);
        }
        .dot-no_session {
            background:#020617;
            border:1px solid #374151;
        }
    </style>
</head>
<body>
<div class="wrap">
    <h1>My Attendance</h1>
    <div class="sub">Australasia Cricket Academy &middot; Personal attendance summary</div>

    <div class="card">
        <div class="profile">
            <div class="avatar">
                <?php
                    $initials = strtoupper(($student['first_name'][0] ?? '') . ($student['last_name'][0] ?? ''));
                    echo htmlspecialchars($initials ?: 'A');
                ?>
            </div>
            <div class="meta-main">
                <div class="name">
                    <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                </div>
                <div class="line">
                    Admission: <?php echo htmlspecialchars($student['admission_no']); ?>
                    <?php if ($student['batch_name']): ?>
                        &middot; Batch: <?php echo htmlspecialchars($student['batch_name']); ?>
                    <?php endif; ?>
                </div>
                <div class="line">
                    Month: <?php echo htmlspecialchars(date('F Y', strtotime($firstDay))); ?>
                </div>
            </div>
        </div>

        <div class="month-select">
            <form method="GET">
                <label>Choose Month:</label>
                <input type="month" name="month" value="<?php echo htmlspecialchars($monthParam); ?>">
                <button type="submit">Go</button>
            </form>
        </div>

        <div class="summary-grid">
            <div class="summary-item">
                <div class="summary-label">Training Days</div>
                <div class="summary-value"><?php echo $totalTrainingDays; ?></div>
                <div class="summary-note">Days where your batch had scheduled training.</div>
            </div>
            <div class="summary-item">
                <div class="summary-label">Present</div>
                <div class="summary-value"><?php echo $presentCount; ?></div>
                <div class="summary-note">On time according to schedule.</div>
            </div>
            <div class="summary-item">
                <div class="summary-label">Late</div>
                <div class="summary-value"><?php echo $lateCount; ?></div>
                <div class="summary-note">Marked present but tapped after start time.</div>
            </div>
            <div class="summary-item">
                <div class="summary-label">Absent</div>
                <div class="summary-value"><?php echo $absentCount; ?></div>
                <div class="summary-note">Scheduled day but no attendance recorded.</div>
            </div>
            <div class="summary-item">
                <div class="summary-label">Attendance %</div>
                <div class="summary-value"><?php echo $attendancePercent; ?>%</div>
                <div class="summary-note">Present (including late) vs training days.</div>
            </div>
        </div>

        <div class="calendar-card">
            <table class="calendar">
                <thead>
                    <tr>
                        <th>Mon</th>
                        <th>Tue</th>
                        <th>Wed</th>
                        <th>Thu</th>
                        <th>Fri</th>
                        <th>Sat</th>
                        <th>Sun</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($weeks as $week): ?>
                    <tr>
                        <?php foreach ($week as $date): ?>
                            <td>
                                <?php if ($date === null): ?>
                                    &nbsp;
                                <?php else:
                                    $dayNum = (int)substr($date, 8, 2);
                                    $status = isset($dayStatus[$date]) ? $dayStatus[$date] : 'no_session';
                                    $class = 'day-' . $status;
                                ?>
                                    <div class="day-box <?php echo $class; ?>">
                                        <span><?php echo $dayNum; ?></span>
                                    </div>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <div class="legend">
                <div class="legend-item">
                    <div class="legend-dot dot-present"></div> Present
                </div>
                <div class="legend-item">
                    <div class="legend-dot dot-late"></div> Late
                </div>
                <div class="legend-item">
                    <div class="legend-dot dot-absent"></div> Absent
                </div>
                <div class="legend-item">
                    <div class="legend-dot dot-no_session"></div> No Session
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
