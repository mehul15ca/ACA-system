<?php
include "../config.php";
checkLogin();

$role = currentUserRole();
if ($role !== 'student') {
    http_response_code(403);
    echo "Access denied.";
    exit;
}
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$userId = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
if ($userId <= 0) die("Missing user.");

$stmtU = $conn->prepare("SELECT student_id FROM users WHERE id = ?");
$stmtU->bind_param("i", $userId);
$stmtU->execute();
$u = $stmtU->get_result()->fetch_assoc();
if (!$u || !$u['student_id']) die("No student linked.");

$studentId = intval($u['student_id']);

$stmtS = $conn->prepare("SELECT * FROM students WHERE id = ?");
$stmtS->bind_param("i", $studentId);
$stmtS->execute();
$student = $stmtS->get_result()->fetch_assoc();
if (!$student) die("Student not found.");

$stmtI = $conn->prepare("
    SELECT ir.*, c.name AS coach_name
    FROM injury_reports ir
    LEFT JOIN coaches c ON ir.coach_id = c.id
    WHERE ir.student_id = ?
    ORDER BY ir.incident_date DESC, ir.reported_at DESC
");
$stmtI->bind_param("i", $studentId);
$stmtI->execute();
$injuries = $stmtI->get_result();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>My Injuries - Australasia Cricket Academy</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            margin:0;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background:#050b12;
            color:#f9fafb;
        }
        .wrap {
            max-width:960px;
            margin:0 auto;
            padding:16px;
        }
        h1 { font-size:22px; margin:4px 0 2px; }
        .sub { font-size:12px; color:#9ca3af; margin-bottom:12px; }
        .card {
            background:#0b1724;
            border-radius:16px;
            padding:14px 16px;
            margin-bottom:14px;
            box-shadow:0 16px 30px rgba(0,0,0,0.55);
        }
        table {
            width:100%;
            border-collapse:collapse;
            margin-top:8px;
            font-size:12px;
        }
        th, td {
            padding:6px 4px;
            border-bottom:1px solid #111827;
        }
        th {
            text-align:left;
            color:#9ca3af;
            font-size:11px;
        }
        .status-pill {
            padding:2px 8px;
            border-radius:999px;
            font-size:11px;
            display:inline-block;
        }
        .status-open { background:#451a03; color:#fed7aa; }
        .status-pending { background:#1e293b; color:#e5e7eb; }
        .status-closed { background:#022c22; color:#6ee7b7; }
        .sev-pill {
            padding:2px 8px;
            border-radius:999px;
            font-size:11px;
            display:inline-block;
        }
        .sev-minor { background:#022c22; color:#6ee7b7; }
        .sev-moderate { background:#1d2a4d; color:#bfdbfe; }
        .sev-serious { background:#4b1d17; color:#fecaca; }
        .sev-critical { background:#450a0a; color:#fee2e2; }
        .notes {
            font-size:11px;
            color:#e5e7eb;
        }
    </style>
</head>
<body>
<div class="wrap">
    <h1>My Injury History</h1>
    <div class="sub">
        <?php echo htmlspecialchars($student['first_name'] . " " . $student['last_name']); ?> ·
        Admission: <?php echo htmlspecialchars($student['admission_no']); ?>
    </div>

    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>Incident Date</th>
                    <th>Coach</th>
                    <th>Area</th>
                    <th>Severity</th>
                    <th>Status</th>
                    <th>Notes (short)</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($injuries && $injuries->num_rows > 0): ?>
                <?php while ($r = $injuries->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($r['incident_date']); ?></td>
                        <td><?php echo htmlspecialchars($r['coach_name']); ?></td>
                        <td><?php echo htmlspecialchars($r['injury_area']); ?></td>
                        <td>
                            <?php
                            $sevCls = 'sev-minor';
                            if ($r['severity'] === 'moderate') $sevCls = 'sev-moderate';
                            elseif ($r['severity'] === 'serious') $sevCls = 'sev-serious';
                            elseif ($r['severity'] === 'critical') $sevCls = 'sev-critical';
                            ?>
                            <span class="sev-pill <?php echo $sevCls; ?>">
                                <?php echo htmlspecialchars($r['severity']); ?>
                            </span>
                        </td>
                        <td>
                            <?php
                            $stCls = 'status-open';
                            if ($r['status'] === 'pending') $stCls = 'status-pending';
                            elseif ($r['status'] === 'closed') $stCls = 'status-closed';
                            ?>
                            <span class="status-pill <?php echo $stCls; ?>">
                                <?php echo htmlspecialchars($r['status']); ?>
                            </span>
                        </td>
                        <td class="notes">
                            <?php
                            $short = mb_substr($r['notes'], 0, 50);
                            if (mb_strlen($r['notes']) > 50) $short .= "...";
                            echo htmlspecialchars($short);
                            ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="6">No injury reports recorded.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>

        <p style="font-size:11px;color:#9ca3af;margin-top:8px;">
            If you have any questions about your injury history or return-to-play plan,
            please contact your coach or academy admin.
        </p>
    </div>
</div>
</body>
</html>
