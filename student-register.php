<?php
include "config.php";

// Helper to generate Student ID format: ACA-ST-YYMM-0001
function generateStudentAdmissionNo($conn) {
    $prefix = "ACA-ST-";
    $ym = date('ym'); // YYMM
    $like = $prefix . $ym . "-%";
    $sql = "SELECT admission_no FROM students WHERE admission_no LIKE ? ORDER BY admission_no DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $like);
    $stmt->execute();
    $res = $stmt->get_result();
    $next = 1;
    if ($row = $res->fetch_assoc()) {
        $last = $row['admission_no']; // e.g. ACA-ST-2512-0003
        $parts = explode("-", $last);
        $seq = intval(end($parts));
        $next = $seq + 1;
    }
    $seqStr = str_pad($next, 3, "0", STR_PAD_LEFT);
    return $prefix . $ym . "-" . $seqStr;
}

// Stub for Google Docs template → PDF generation
function createStudentWaiverFromTemplate($conn, $studentId, $studentData) {
    // TODO: integrate Google Docs + Drive:
    // 1) Read waiver_template_doc_id from google_settings
    // 2) Copy the template Doc
    // 3) Replace placeholders
    // 4) Export as PDF
    // 5) Get Drive file ID
    // 6) Insert into documents table
    //
    // For now, return null so the rest of the registration still works.
    return null;
}

// Fetch token
$token = $_GET['token'] ?? '';
$token = trim($token);

if ($token === '') {
    die("Invalid registration link.");
}

$tokenEsc = $conn->real_escape_string($token);
$sqlT = "
    SELECT rt.*, b.name AS batch_name
    FROM registration_tokens rt
    JOIN batches b ON rt.batch_id = b.id
    WHERE rt.token = '{$tokenEsc}' AND rt.status = 'pending'
";
$resT = $conn->query($sqlT);
$tok = $resT ? $resT->fetch_assoc() : null;

if (!$tok) {
    die("This registration link is invalid or already used.");
}

$message = "";
$success = false;

// Set your login URL here (later the live domain)
$loginUrl = "http://localhost/ACA-System/login.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name'] ?? '');
    $dob        = trim($_POST['dob'] ?? '');
    $phone      = trim($_POST['phone'] ?? '');
    $address    = trim($_POST['address'] ?? '');
    $em_name    = trim($_POST['emergency_contact_name'] ?? '');
    $em_rel     = trim($_POST['emergency_contact_relation'] ?? '');
    $em_phone   = trim($_POST['emergency_contact_phone'] ?? '');
    $accept_waiver = isset($_POST['accept_waiver']) ? 1 : 0;
    $signature_type = $_POST['signature_type'] ?? '';
    $signature_draw = $_POST['signature_draw'] ?? '';
    $signature_text = trim($_POST['signature_text'] ?? '');

    if ($first_name === '' || $dob === '' || $phone === '' || $address === '' || $em_name === '' || $em_phone === '') {
        $message = "Please fill all required fields.";
    } elseif (!$accept_waiver) {
        $message = "You must accept the waiver to continue.";
    } elseif ($signature_type === '' || ($signature_type === 'draw' && $signature_draw === '') || ($signature_type === 'text' && $signature_text === '')) {
        $message = "Signature is required.";
    } else {
        // Email and batch from token
        $email = $tok['email'];
        $batch_id = intval($tok['batch_id']);

        // Start transaction
        $conn->begin_transaction();
        try {
            // Generate admission_no
            $admission_no = generateStudentAdmissionNo($conn);
            $admEsc = $conn->real_escape_string($admission_no);

            $firstEsc = $conn->real_escape_string($first_name);
            $lastEsc  = $conn->real_escape_string($last_name);
            $dobEsc   = $dob !== '' ? $conn->real_escape_string($dob) : null;
            $parent_name = ''; // can be extended later
            $parentEsc = $conn->real_escape_string($parent_name);
            $phoneEsc = $conn->real_escape_string($phone);
            $emailEsc = $conn->real_escape_string($email);
            $addrEsc  = $conn->real_escape_string($address);
            $emNameEsc = $conn->real_escape_string($em_name);
            $emRelEsc  = $conn->real_escape_string($em_rel);
            $emPhoneEsc= $conn->real_escape_string($em_phone);
            $join_date = date('Y-m-d');

            $sqlStu = "
                INSERT INTO students
                    (admission_no, first_name, last_name, dob, parent_name, phone, email, address,
                     emergency_contact_name, emergency_contact_relation, emergency_contact_phone,
                     batch_id, join_date, status, profile_photo_drive_id)
                VALUES
                    ('{$admEsc}', '{$firstEsc}', '{$lastEsc}', ".($dobEsc ? "'{$dobEsc}'" : "NULL").",
                     '{$parentEsc}', '{$phoneEsc}', '{$emailEsc}', '{$addrEsc}',
                     '{$emNameEsc}', '{$emRelEsc}', '{$emPhoneEsc}',
                     {$batch_id}, '{$join_date}', 'active', NULL)
            ";
            if (!$conn->query($sqlStu)) {
                throw new Exception("Student insert failed: " . $conn->error);
            }
            $student_id = $conn->insert_id;

            // Create user login (role=student)
            $username = $email;
            $tempPasswordPlain = bin2hex(random_bytes(4)); // 8 hex chars
            $passwordHash = password_hash($tempPasswordPlain, PASSWORD_BCRYPT);

            $userStmt = $conn->prepare("
                INSERT INTO users (username, password_hash, role, coach_id, student_id)
                VALUES (?, ?, 'student', NULL, ?)
            ");
            $userStmt->bind_param("ssi", $username, $passwordHash, $student_id);
            if (!$userStmt->execute()) {
                throw new Exception("User insert failed: " . $userStmt->error);
            }
            $newUserId = $userStmt->insert_id;

            // Mark token as completed
            $conn->query("
                UPDATE registration_tokens
                SET status='completed', completed_at=NOW()
                WHERE id = " . intval($tok['id'])
            );

            // Call waiver creation stub
            $studentData = [
                'admission_no' => $admission_no,
                'first_name'   => $first_name,
                'last_name'    => $last_name,
                'email'        => $email,
                'batch_name'   => $tok['batch_name'],
                'signed_at'    => date('Y-m-d H:i:s'),
            ];
            $waiverFileId = createStudentWaiverFromTemplate($conn, $student_id, $studentData);
            if ($waiverFileId) {
                $fileEsc = $conn->real_escape_string($waiverFileId);
                $conn->query("
                    INSERT INTO documents (owner_type, owner_id, title, file_type, drive_file_id)
                    VALUES ('student', {$student_id}, 'Waiver Form', 'application/pdf', '{$fileEsc}')
                ");
            }

            // Queue emails: Student + Superadmin
            $subjectStu = "Welcome to Australasia Cricket Academy – Registration Complete";
            $msgStu = "Dear {$first_name},\n\n".
                      "Your registration with Australasia Cricket Academy is now complete.\n\n".
                      "Student ID: {$admission_no}\n".
                      "Login Username: {$username}\n".
                      "Temporary Password: {$tempPasswordPlain}\n\n".
                      "You can log in at: {$loginUrl}\n\n".
                      "Please change your password after first login.\n\n".
                      "Thank you,\nAustralasia Cricket Academy";

            $subEsc = $conn->real_escape_string($subjectStu);
            $msgEsc = $conn->real_escape_string($msgStu);
            $conn->query("
                INSERT INTO notifications_queue
                    (user_id, receiver_email, channel, subject, message, status, template_code)
                VALUES
                    ({$newUserId}, '{$emailEsc}', 'email', '{$subEsc}', '{$msgEsc}', 'pending', 'STUDENT_REG_COMPLETE')
            ");

            // Superadmin notification
            $adminEmail = "mehul15.ca@gmail.com";
            $adminEsc = $conn->real_escape_string($adminEmail);
            $subjectAdmin = "New student registration – {$admission_no}";
            $msgAdmin = "A new student has completed registration.\n\n".
                        "Name: {$first_name} {$last_name}\n".
                        "Student ID: {$admission_no}\n".
                        "Email: {$email}\n".
                        "Batch: {$tok['batch_name']}\n";

            $subAdmEsc = $conn->real_escape_string($subjectAdmin);
            $msgAdmEsc = $conn->real_escape_string($msgAdmin);
            $conn->query("
                INSERT INTO notifications_queue
                    (user_id, receiver_email, channel, subject, message, status, template_code)
                VALUES
                    ({$newUserId}, '{$adminEsc}', 'email', '{$subAdmEsc}', '{$msgAdmEsc}', 'pending', 'STUDENT_REG_ADMIN')
            ");

            $conn->commit();
            $success = true;
            header("Location: student-register-success.php");
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $message = "Registration failed: " . $e->getMessage();
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
        body {
            margin:0;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background:#020617;
            color:#e5e7eb;
        }
        .wrap {
            max-width:700px;
            margin:0 auto;
            padding:16px;
        }
        h1 {
            font-size:22px;
            margin:8px 0 4px;
        }
        .sub {
            font-size:13px;
            color:#9ca3af;
            margin-bottom:16px;
        }
        .card {
            background:#020817;
            border-radius:16px;
            border:1px solid #1f2937;
            padding:16px;
        }
        .group {
            margin-bottom:10px;
        }
        label {
            display:block;
            font-size:12px;
            margin-bottom:3px;
        }
        input[type=text],
        input[type=email],
        input[type=date],
        textarea {
            width:100%;
            box-sizing:border-box;
            background:#020617;
            border-radius:10px;
            border:1px solid #1f2937;
            color:#e5e7eb;
            padding:6px 8px;
            font-size:13px;
        }
        textarea {
            min-height:60px;
            resize:vertical;
        }
        .row2 {
            display:flex;
            gap:10px;
        }
        .row2 > div { flex:1; }
        .message {
            margin-bottom:10px;
            padding:8px 10px;
            border-radius:8px;
            font-size:13px;
        }
        .message.err { background:#450a0a; color:#fecaca; }
        .note {
            font-size:11px;
            color:#9ca3af;
        }
        .waiver-box {
            margin-top:12px;
            padding:10px;
            border-radius:10px;
            background:#020617;
            border:1px dashed #374151;
            font-size:12px;
            color:#d1d5db;
        }
        .sig-type {
            display:flex;
            gap:10px;
            margin-top:6px;
            font-size:12px;
        }
        .sig-type label { display:flex; align-items:center; gap:4px; }
        .btn {
            border:none;
            border-radius:999px;
            padding:8px 14px;
            font-size:13px;
            cursor:pointer;
        }
        .btn-primary { background:#22c55e; color:#021014; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Student Registration</h1>
    <div class="sub">
        Australasia Cricket Academy – Batch: <?php echo htmlspecialchars($tok['batch_name']); ?><br>
        Email: <?php echo htmlspecialchars($tok['email']); ?>
    </div>

    <div class="card">
        <?php if ($message): ?>
            <div class="message err"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <form method="POST">
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
                        <label>
                            <input type="radio" name="signature_type" value="draw">
                            Draw Signature (to be implemented)
                        </label>
                        <label>
                            <input type="radio" name="signature_type" value="text">
                            Type Name as Signature
                        </label>
                    </div>
                    <div class="group" style="margin-top:6px;">
                        <label style="font-size:11px;">If you selected "Type Name", enter your full name here:</label>
                        <input type="text" name="signature_text" placeholder="Type your full name as signature">
                    </div>
                    <input type="hidden" name="signature_draw" value="">
                    <p class="note">
                        Note: Signature drawing box can be added later; for now you may use typed signature.
                    </p>
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
