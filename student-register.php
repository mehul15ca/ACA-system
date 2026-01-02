<?php
declare(strict_types=1);

require "config.php";

// CSRF
function csrf_token(): string {
    if (empty($_SESSION['csrf_reg'])) $_SESSION['csrf_reg'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_reg'];
}
function csrf_verify(?string $token): void {
    if (!$token || empty($_SESSION['csrf_reg']) || !hash_equals($_SESSION['csrf_reg'], $token)) {
        http_response_code(403);
        exit('Invalid CSRF token');
    }
}

// Helper: ACA-ST-YYMM-0001 (4 digits)
function generateStudentAdmissionNo(mysqli $conn): string {
    $prefix = "ACA-ST-";
    $ym = date('ym');
    $like = $prefix . $ym . "-%";

    $stmt = $conn->prepare("SELECT admission_no FROM students WHERE admission_no LIKE ? ORDER BY admission_no DESC LIMIT 1");
    $stmt->bind_param("s", $like);
    $stmt->execute();
    $res = $stmt->get_result();

    $next = 1;
    if ($row = $res->fetch_assoc()) {
        $parts = explode("-", (string)$row['admission_no']);
        $seq = (int)end($parts);
        $next = $seq + 1;
    }

    return $prefix . $ym . "-" . str_pad((string)$next, 4, "0", STR_PAD_LEFT);
}

// Stub
function createStudentWaiverFromTemplate(mysqli $conn, int $studentId, array $studentData): ?string {
    return null;
}

$token = trim((string)($_GET['token'] ?? ''));
if ($token === '') {
    exit("Invalid registration link.");
}

// Token lookup (prepared)
$stmtT = $conn->prepare("
    SELECT rt.*, b.name AS batch_name
    FROM registration_tokens rt
    JOIN batches b ON rt.batch_id = b.id
    WHERE rt.token = ? AND rt.status = 'pending'
    LIMIT 1
");
$stmtT->bind_param("s", $token);
$stmtT->execute();
$tok = $stmtT->get_result()->fetch_assoc();

if (!$tok) {
    exit("This registration link is invalid or already used.");
}

$message = "";
$loginUrl = "http://localhost/ACA-System/login.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['csrf'] ?? null);

    $first_name = trim((string)($_POST['first_name'] ?? ''));
    $last_name  = trim((string)($_POST['last_name'] ?? ''));
    $dob        = trim((string)($_POST['dob'] ?? ''));
    $phone      = trim((string)($_POST['phone'] ?? ''));
    $address    = trim((string)($_POST['address'] ?? ''));
    $em_name    = trim((string)($_POST['emergency_contact_name'] ?? ''));
    $em_rel     = trim((string)($_POST['emergency_contact_relation'] ?? ''));
    $em_phone   = trim((string)($_POST['emergency_contact_phone'] ?? ''));

    $accept_waiver = isset($_POST['accept_waiver']) ? 1 : 0;
    $signature_type = (string)($_POST['signature_type'] ?? '');
    $signature_draw = (string)($_POST['signature_draw'] ?? '');
    $signature_text = trim((string)($_POST['signature_text'] ?? ''));

    if ($first_name === '' || $dob === '' || $phone === '' || $address === '' || $em_name === '' || $em_phone === '') {
        $message = "Please fill all required fields.";
    } elseif (!$accept_waiver) {
        $message = "You must accept the waiver to continue.";
    } elseif ($signature_type === '' || ($signature_type === 'draw' && $signature_draw === '') || ($signature_type === 'text' && $signature_text === '')) {
        $message = "Signature is required.";
    } else {
        $email = (string)$tok['email'];
        $batch_id = (int)$tok['batch_id'];
        $join_date = date('Y-m-d');

        $conn->begin_transaction();

        try {
            $admission_no = generateStudentAdmissionNo($conn);

            // Insert student (prepared)
            $parent_name = '';
            $status = 'active';
            $null = null;

            $stmtStu = $conn->prepare("
                INSERT INTO students
                    (admission_no, first_name, last_name, dob, parent_name, phone, email, address,
                     emergency_contact_name, emergency_contact_relation, emergency_contact_phone,
                     batch_id, join_date, status, profile_photo_drive_id)
                VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmtStu->bind_param(
                "sssssssssssisss",
                $admission_no,
                $first_name,
                $last_name,
                $dob,
                $parent_name,
                $phone,
                $email,
                $address,
                $em_name,
                $em_rel,
                $em_phone,
                $batch_id,
                $join_date,
                $status,
                $null
            );

            if (!$stmtStu->execute()) {
                throw new Exception("Student insert failed: " . $stmtStu->error);
            }
            $student_id = (int)$stmtStu->insert_id;

            // Create user (prepared)
            $username = $email;

            try {
                $tempPasswordPlain = bin2hex(random_bytes(8));
            } catch (Throwable) {
                throw new Exception("Password generation failed.");
            }

            $passwordHash = password_hash($tempPasswordPlain, PASSWORD_BCRYPT);

            $stmtUser = $conn->prepare("
                INSERT INTO users (username, password_hash, role, coach_id, student_id)
                VALUES (?, ?, 'student', NULL, ?)
            ");
            $stmtUser->bind_param("ssi", $username, $passwordHash, $student_id);

            if (!$stmtUser->execute()) {
                throw new Exception("User insert failed: " . $stmtUser->error);
            }
            $newUserId = (int)$stmtUser->insert_id;

            // Mark token completed (prepared)
            $tokId = (int)$tok['id'];
            $stmtTok = $conn->prepare("
                UPDATE registration_tokens
                SET status='completed', completed_at=NOW()
                WHERE id = ?
            ");
            $stmtTok->bind_param("i", $tokId);
            $stmtTok->execute();

            // Optional waiver doc
            $studentData = [
                'admission_no' => $admission_no,
                'first_name'   => $first_name,
                'last_name'    => $last_name,
                'email'        => $email,
                'batch_name'   => (string)$tok['batch_name'],
                'signed_at'    => date('Y-m-d H:i:s'),
            ];
            $waiverFileId = createStudentWaiverFromTemplate($conn, $student_id, $studentData);

            if ($waiverFileId) {
                $ownerType = 'student';
                $title = 'Waiver Form';
                $fileType = 'application/pdf';

                $stmtDoc = $conn->prepare("
                    INSERT INTO documents (owner_type, owner_id, title, file_type, drive_file_id)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmtDoc->bind_param("sisss", $ownerType, $student_id, $title, $fileType, $waiverFileId);
                $stmtDoc->execute();
            }

            // Queue emails (prepared)
            $subjectStu = "Welcome to Australasia Cricket Academy – Registration Complete";
            $msgStu =
                "Dear {$first_name},\n\n".
                "Your registration with Australasia Cricket Academy is now complete.\n\n".
                "Student ID: {$admission_no}\n".
                "Login Username: {$username}\n".
                "Temporary Password: {$tempPasswordPlain}\n\n".
                "You can log in at: {$loginUrl}\n\n".
                "Please change your password after first login.\n\n".
                "Thank you,\nAustralasia Cricket Academy";

            $channel = 'email';
            $statusQ = 'pending';
            $tplStu = 'STUDENT_REG_COMPLETE';

            $stmtQ1 = $conn->prepare("
                INSERT INTO notifications_queue
                    (user_id, receiver_email, channel, subject, message, status, template_code)
                VALUES
                    (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmtQ1->bind_param("issssss", $newUserId, $email, $channel, $subjectStu, $msgStu, $statusQ, $tplStu);
            $stmtQ1->execute();

            $adminEmail = "mehul15.ca@gmail.com";
            $subjectAdmin = "New student registration – {$admission_no}";
            $msgAdmin =
                "A new student has completed registration.\n\n".
                "Name: {$first_name} {$last_name}\n".
                "Student ID: {$admission_no}\n".
                "Email: {$email}\n".
                "Batch: {$tok['batch_name']}\n";

            $tplAdm = 'STUDENT_REG_ADMIN';

            $stmtQ2 = $conn->prepare("
                INSERT INTO notifications_queue
                    (user_id, receiver_email, channel, subject, message, status, template_code)
                VALUES
                    (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmtQ2->bind_param("issssss", $newUserId, $adminEmail, $channel, $subjectAdmin, $msgAdmin, $statusQ, $tplAdm);
            $stmtQ2->execute();

            $conn->commit();

            header("Location: student-register-success.php");
            exit;
        } catch (Throwable $e) {
            $conn->rollback();
            $message = "Registration failed. Please try again.";
            if (isset($debugMode) && $debugMode === 'on') {
                $message .= " (" . $e->getMessage() . ")";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Australasia Cricket Academy – Student Registration</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body{margin:0;font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:#020617;color:#e5e7eb}
        .wrap{max-width:700px;margin:0 auto;padding:16px}
        h1{font-size:22px;margin:8px 0 4px}
        .sub{font-size:13px;color:#9ca3af;margin-bottom:16px}
        .card{background:#020817;border-radius:16px;border:1px solid #1f2937;padding:16px}
        .group{margin-bottom:10px}
        label{display:block;font-size:12px;margin-bottom:3px}
        input[type=text],input[type=date],textarea{width:100%;box-sizing:border-box;background:#020617;border-radius:10px;border:1px solid #1f2937;color:#e5e7eb;padding:6px 8px;font-size:13px}
        textarea{min-height:60px;resize:vertical}
        .row2{display:flex;gap:10px}
        .row2>div{flex:1}
        .message{margin-bottom:10px;padding:8px 10px;border-radius:8px;font-size:13px}
        .message.err{background:#450a0a;color:#fecaca}
        .note{font-size:11px;color:#9ca3af}
        .waiver-box{margin-top:12px;padding:10px;border-radius:10px;background:#020617;border:1px dashed #374151;font-size:12px;color:#d1d5db}
        .sig-type{display:flex;gap:10px;margin-top:6px;font-size:12px}
        .sig-type label{display:flex;align-items:center;gap:4px}
        .btn{border:none;border-radius:999px;padding:8px 14px;font-size:13px;cursor:pointer}
        .btn-primary{background:#22c55e;color:#021014}
    </style>
</head>
<body>
<div class="wrap">
    <h1>Student Registration</h1>
    <div class="sub">
        Australasia Cricket Academy – Batch: <?php echo htmlspecialchars((string)$tok['batch_name'], ENT_QUOTES, 'UTF-8'); ?><br>
        Email: <?php echo htmlspecialchars((string)$tok['email'], ENT_QUOTES, 'UTF-8'); ?>
    </div>

    <div class="card">
        <?php if ($message): ?>
            <div class="message err"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">

            <h2 style="font-size:14px;margin-bottom:6px;">Personal Details</h2>
            <div class="row2 group">
                <div>
                    <label>First Name *</label>
                    <input type="text" name="first_name" required>
                </div>
                <div>
                    <label>Last Name</label>
                    <input type="text" name="last_name">
                </div>
            </div>

            <div class="row2 group">
                <div>
                    <label>Date of Birth *</label>
                    <input type="date" name="dob" required>
                </div>
                <div>
                    <label>Phone *</label>
                    <input type="text" name="phone" required>
                </div>
            </div>

            <div class="group">
                <label>Address *</label>
                <textarea name="address" required></textarea>
            </div>

            <h2 style="font-size:14px;margin:12px 0 6px;">Emergency Contact</h2>
            <div class="group">
                <label>Contact Name *</label>
                <input type="text" name="emergency_contact_name" required>
            </div>

            <div class="row2 group">
                <div>
                    <label>Relationship</label>
                    <input type="text" name="emergency_contact_relation">
                </div>
                <div>
                    <label>Contact Phone *</label>
                    <input type="text" name="emergency_contact_phone" required>
                </div>
            </div>

            <h2 style="font-size:14px;margin:12px 0 6px;">Waiver & Consent</h2>
            <div class="waiver-box">
                <p style="margin:0 0 8px;">
                    By checking the box below, you confirm that you have read and agree to the
                    Australasia Cricket Academy participation terms, code of conduct, and
                    liability waiver as provided by the academy.
                </p>
                <label style="font-size:12px;margin-top:4px;">
                    <input type="checkbox" name="accept_waiver" value="1">
                    I have read and agree to the academy waiver and terms.
                </label>

                <div style="margin-top:10px;">
                    <div style="font-size:12px;font-weight:600;margin-bottom:4px;">Signature</div>
                    <div class="sig-type">
                        <label><input type="radio" name="signature_type" value="draw"> Draw Signature (to be implemented)</label>
                        <label><input type="radio" name="signature_type" value="text"> Type Name as Signature</label>
                    </div>

                    <div class="group" style="margin-top:6px;">
                        <label style="font-size:11px;">If you selected "Type Name", enter your full name here:</label>
                        <input type="text" name="signature_text" placeholder="Type your full name as signature">
                    </div>

                    <input type="hidden" name="signature_draw" value="">
                    <p class="note">Note: Signature drawing box can be added later; for now you may use typed signature.</p>
                </div>
            </div>

            <div class="group" style="margin-top:14px;">
                <button type="submit" class="btn btn-primary">Submit Registration</button>
            </div>
        </form>
    </div>
</div>
</body>
</html>
