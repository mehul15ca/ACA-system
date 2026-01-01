<?php
// Daily email of PRESENT students grouped by batch.
// Run this via cron or hit manually in browser.
include "../config.php";

date_default_timezone_set('America/Toronto');

$adminEmail = "mehul15.ca@gmail.com"; // Change if needed

$date = isset($_GET['date']) && $_GET['date'] !== '' ? $_GET['date'] : date('Y-m-d');

// Fetch present students (distinct) with joins
$sql = "
    SELECT s.id, s.admission_no, s.first_name, s.last_name,
           b.name AS batch_name, b.age_group,
           MIN(al.log_time) AS first_log_time
    FROM attendance_logs al
    JOIN students s ON al.student_id = s.id
    LEFT JOIN batches b ON s.batch_id = b.id
    WHERE al.log_date = ?
    GROUP BY s.id, s.admission_no, s.first_name, s.last_name, b.name, b.age_group
    ORDER BY b.name ASC, s.first_name ASC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $date);
$stmt->execute();
$res = $stmt->get_result();

$rows = [];
while ($r = $res->fetch_assoc()) {
    $rows[] = $r;
}

if (empty($rows)) {
    echo "No present students on $date. No email sent.";
    exit;
}

// Group by batch
$byBatch = [];
foreach ($rows as $r) {
    $key = $r['batch_name'] ?: 'Unassigned';
    if (!isset($byBatch[$key])) $byBatch[$key] = [];
    $byBatch[$key][] = $r;
}

// Build HTML body
$subject = "ACA – Present Students Report for $date";

$body  = "<html><body>";
$body .= "<h2>Australasia Cricket Academy – Present Students</h2>";
$body .= "<p>Date: <strong>$date</strong></p>";

foreach ($byBatch as $batch => $list) {
    $body .= "<h3>Batch: " . htmlspecialchars($batch) . "</h3>";
    $body .= "<table border='1' cellspacing='0' cellpadding='4' style='border-collapse:collapse;font-size:12px;'>";
    $body .= "<tr style='background:#f3f4f6;'>
                <th>#</th>
                <th>Admission No</th>
                <th>Name</th>
                <th>First Tap Time</th>
              </tr>";
    $i = 1;
    foreach ($list as $r) {
        $body .= "<tr>";
        $body .= "<td>" . $i++ . "</td>";
        $body .= "<td>" . htmlspecialchars($r['admission_no']) . "</td>";
        $body .= "<td>" . htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) . "</td>";
        $body .= "<td>" . htmlspecialchars(substr($r['first_log_time'], 0, 5)) . "</td>";
        $body .= "</tr>";
    }
    $body .= "</table>";
}
$body .= "<p style='font-size:11px;color:#6b7280;margin-top:10px;'>
            This is an automated report from the ACA Attendance System.
          </p>";
$body .= "</body></html>";

// Send email - requires mail() configured on server
$headers  = "MIME-Version: 1.0" . "\r";
$headers .= "Content-type:text/html;charset=UTF-8" . "\r";
$headers .= "From: ACA Attendance <no-reply@aca.local>" . "\r";

$sent = @mail($adminEmail, $subject, $body, $headers);

if ($sent) {
    echo "Present students report emailed to $adminEmail for $date.";
} else {
    echo "Failed to send email. Check mail server configuration.";
}
