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

$stmtE = $conn->prepare("
    SELECT pe.*, c.name AS coach_name
    FROM player_evaluation pe
    LEFT JOIN coaches c ON pe.coach_id = c.id
    WHERE pe.student_id = ?
    ORDER BY pe.eval_time ASC
");
$stmtE->bind_param("i", $studentId);
$stmtE->execute();
$evals = $stmtE->get_result();

$rows = [];
while ($row = $evals->fetch_assoc()) {
    $rows[] = $row;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>My Evaluations - Australasia Cricket Academy</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        .status-draft { background:#111827; color:#e5e7eb; }
        .status-final { background:#022c22; color:#6ee7b7; }
        .chart-wrap {
            margin-top:10px;
        }
        .chart-wrap canvas {
            width:100%;
            max-height:260px;
        }
    </style>
</head>
<body>
<div class="wrap">
    <h1>My Evaluations</h1>
    <div class="sub">
        <?php echo htmlspecialchars($student['first_name'] . " " . $student['last_name']); ?> ·
        Admission: <?php echo htmlspecialchars($student['admission_no']); ?>
    </div>

    <div class="card">
        <h2 style="font-size:15px;margin:0 0 6px;">Progress Overview</h2>
        <p style="font-size:11px;color:#9ca3af;margin:0 0 8px;">
            This graph shows how your scores have changed over time. Higher is better (1–10 scale).
        </p>
        <div class="chart-wrap">
            <canvas id="evalChart"></canvas>
        </div>
    </div>

    <div class="card">
        <h2 style="font-size:15px;margin:0 0 6px;">All Evaluations</h2>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Coach</th>
                    <th>Overall</th>
                    <th>Bat</th>
                    <th>Bowl</th>
                    <th>Field</th>
                    <th>Fit</th>
                    <th>Disc</th>
                    <th>Att</th>
                    <th>Tech</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php if (count($rows) > 0): ?>
                <?php foreach ($rows as $e): ?>
                    <tr>
                        <td><?php echo htmlspecialchars(substr($e['eval_time'], 0, 10)); ?></td>
                        <td><?php echo htmlspecialchars($e['coach_name']); ?></td>
                        <td><?php echo $e['overall_score'] !== null ? number_format($e['overall_score'], 1) : '-'; ?></td>
                        <td><?php echo $e['batting_rating']    !== null ? intval($e['batting_rating'])    : '-'; ?></td>
                        <td><?php echo $e['bowling_rating']    !== null ? intval($e['bowling_rating'])    : '-'; ?></td>
                        <td><?php echo $e['fielding_rating']   !== null ? intval($e['fielding_rating'])   : '-'; ?></td>
                        <td><?php echo $e['fitness_rating']    !== null ? intval($e['fitness_rating'])    : '-'; ?></td>
                        <td><?php echo $e['discipline_rating'] !== null ? intval($e['discipline_rating']) : '-'; ?></td>
                        <td><?php echo $e['attitude_rating']   !== null ? intval($e['attitude_rating'])   : '-'; ?></td>
                        <td><?php echo $e['technique_rating']  !== null ? intval($e['technique_rating'])  : '-'; ?></td>
                        <td>
                            <?php $cls = $e['status']==='final' ? 'status-final' : 'status-draft'; ?>
                            <span class="status-pill <?php echo $cls; ?>">
                                <?php echo htmlspecialchars($e['status']); ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="11">No evaluations yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
const evalData = <?php
    $out = [];
    foreach ($rows as $e) {
        $out[] = [
            'date'       => substr($e['eval_time'], 0, 10),
            'overall'    => $e['overall_score'] !== null ? floatval($e['overall_score']) : null,
            'batting'    => $e['batting_rating']    !== null ? intval($e['batting_rating'])    : null,
            'bowling'    => $e['bowling_rating']    !== null ? intval($e['bowling_rating'])    : null,
            'fielding'   => $e['fielding_rating']   !== null ? intval($e['fielding_rating'])   : null,
            'fitness'    => $e['fitness_rating']    !== null ? intval($e['fitness_rating'])    : null,
            'discipline' => $e['discipline_rating'] !== null ? intval($e['discipline_rating']) : null,
            'attitude'   => $e['attitude_rating']   !== null ? intval($e['attitude_rating'])   : null,
            'technique'  => $e['technique_rating']  !== null ? intval($e['technique_rating'])  : null
        ];
    }
    echo json_encode($out);
?>;

if (evalData.length > 0) {
    const labels = evalData.map(e => e.date);
    const buildDataset = (label, key) => ({
        label,
        data: evalData.map(e => e[key]),
        spanGaps: true,
        tension: 0.3
    });

    const ctx = document.getElementById('evalChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels,
            datasets: [
                buildDataset('Overall', 'overall'),
                buildDataset('Batting', 'batting'),
                buildDataset('Bowling', 'bowling'),
                buildDataset('Fielding', 'fielding'),
                buildDataset('Fitness', 'fitness'),
                buildDataset('Discipline', 'discipline'),
                buildDataset('Attitude', 'attitude'),
                buildDataset('Technique', 'technique'),
            ]
        },
        options: {
            scales: {
                y: {
                    suggestedMin: 0,
                    suggestedMax: 10
                }
            }
        }
    });
}
</script>
</body>
</html>
