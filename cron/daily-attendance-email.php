
<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

require_once __DIR__ . '/_bootstrap.php'; // CLI-only guard + DB

date_default_timezone_set('America/Toronto');

$adminEmail = "mehul15.ca@gmail.com";
$date = $argv[1] ?? date('Y-m-d');

// Fetch present students
$stmt = $conn->prepare("
    SELECT s.admission_no, s.first_name, s.last_name,
           b.name AS batch_name,
           MIN(al.log_time) AS first_log_time
    FROM attendance_logs al
    JOIN students s ON al.student_id = s.id
    LEFT JOIN batches b ON s.batch_id = b.id
    WHERE al.log_date = ?
    GROUP BY s.id
    ORDER BY b.name ASC, s.first_name ASC
");
$stmt->bind_param("s", $date);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo "No attendance for $date\n";
    exit(0);
}

// Group by batch
$groups = [];
while ($r = $res->fetch_assoc()) {
    $groups[$r['batch_name'] ?: 'Unassigned'][] = $r;
}

// Email body
$subject = "ACA – Present Students ($date)";
$body = "<h2>Present Students – $date</h2>";

foreach ($groups as $batch => $rows) {
    $body .= "<h3>Batch: ".htmlspecialchars($batch)."</h3><table border='1' cellpadding='4'>";
    $body .= "<tr><th>#</th><th>Admission</th><th>Name</th><th>First Tap</th></tr>";
    $i=1;
    foreach ($rows as $r) {
        $body .= "<tr>
            <td>{$i}</td>
            <td>".htmlspecialchars($r['admission_no'])."</td>
            <td>".htmlspecialchars($r['first_name'].' '.$r['last_name'])."</td>
            <td>".htmlspecialchars(substr($r['first_log_time'],0,5))."</td>
        </tr>";
        $i++;
    }
    $body .= "</table>";
}

$headers = "MIME-Version: 1.0\r\nContent-type:text/html;charset=UTF-8\r\nFrom: ACA <no-reply@aca.local>\r\n";

mail($adminEmail, $subject, $body, $headers);

echo "Email sent for $date\n";
