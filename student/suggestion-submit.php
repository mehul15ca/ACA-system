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

$stmtS = $conn->prepare("SELECT first_name, last_name, admission_no FROM students WHERE id = ?");
$stmtS->bind_param("i", $studentId);
$stmtS->execute();
$student = $stmtS->get_result()->fetch_assoc();
if (!$student) die("Student not found.");

$message = "";
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date      = $_POST['date'] !== "" ? $_POST['date'] : date('Y-m-d');
    $text      = trim($_POST['suggestion']);
    $drive_id  = trim($_POST['drive_file_id']);

    if ($text === "") {
        $message = "Please write your suggestion or feedback.";
    } else {
        $status = 'open';
        $sql = "
            INSERT INTO suggestions (student_id, `date`, suggestion, status, drive_file_id)
            VALUES (?, ?, ?, ?, ?)
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "issss",
            $studentId,
            $date,
            $text,
            $status,
            $drive_id
        );
        if ($stmt->execute()) {
            $success = "Thank you! Your suggestion has been submitted.";
        } else {
            $message = "Database error: " . $conn->error;
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Submit Suggestion - Australasia Cricket Academy</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            margin:0;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background:#050b12;
            color:#f9fafb;
        }
        .wrap {
            max-width:640px;
            margin:0 auto;
            padding:16px;
        }
        h1 { font-size:20px; margin:4px 0 2px; }
        .sub { font-size:12px; color:#9ca3af; margin-bottom:12px; }
        .card {
            background:#0b1724;
            border-radius:16px;
            padding:14px 16px;
            box-shadow:0 16px 30px rgba(0,0,0,0.55);
        }
        label { display:block; font-size:12px; margin-bottom:4px; }
        input[type="date"],
        input[type="text"],
        textarea {
            width:100%;
            border-radius:10px;
            border:1px solid #1f2933;
            padding:7px 9px;
            font-size:13px;
            background:#020617;
            color:#f9fafb;
            box-sizing:border-box;
        }
        textarea { resize:vertical; min-height:100px; }
        button {
            margin-top:10px;
            padding:8px 16px;
            border-radius:999px;
            border:none;
            font-size:13px;
            cursor:pointer;
            background:#22c55e;
            color:#022c22;
        }
        .msg-error {
            background:#450a0a;
            color:#fee2e2;
            padding:8px 10px;
            border-radius:10px;
            font-size:12px;
            margin-bottom:8px;
        }
        .msg-success {
            background:#022c22;
            color:#bbf7d0;
            padding:8px 10px;
            border-radius:10px;
            font-size:12px;
            margin-bottom:8px;
        }
        p.helper {
            font-size:11px;
            color:#9ca3af;
            margin-top:4px;
        }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Submit Suggestion / Feedback</h1>
    <div class="sub">
        <?php echo htmlspecialchars($student['first_name'] . " " . $student['last_name']); ?> ·
        Admission: <?php echo htmlspecialchars($student['admission_no']); ?>
    </div>

    <div class="card">
        <?php if ($message): ?><div class="msg-error"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
        <?php if ($success): ?><div class="msg-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Date</label>
                <input type="date" name="date"
                       value="<?php echo htmlspecialchars(date('Y-m-d')); ?>">
            </div>

            <div class="form-group" style="margin-top:8px;">
                <label>Your Suggestion / Feedback</label>
                <textarea name="suggestion" placeholder="Share any ideas, issues, or feedback about training, facilities, communication, etc."></textarea>
            </div>

            <div class="form-group" style="margin-top:8px;">
                <label>Google Drive File ID (optional)</label>
                <input type="text" name="drive_file_id" placeholder="If you have a screenshot or document, paste its Drive file ID here">
                <p class="helper">
                    Example link:
                    https://drive.google.com/file/d/<strong>FILE_ID_HERE</strong>/view
                </p>
            </div>

            <button type="submit">Submit Suggestion</button>
        </form>
    </div>
</div>
</body>
</html>
