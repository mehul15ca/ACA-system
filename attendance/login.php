<?php
// Ground login for attendance tablet
include "../config.php";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$message = "";

// Fetch active grounds
$grounds_res = $conn->query("SELECT id, name FROM grounds WHERE status = 'active' ORDER BY name ASC");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ground_id = intval($_POST['ground_id']);
    $password  = $_POST['password'];

    if ($ground_id <= 0 || $password === '') {
        $message = "Please select a ground and enter password.";
    } else {
        $stmt = $conn->prepare("SELECT id, name, password FROM grounds WHERE id = ? AND status = 'active'");
        $stmt->bind_param("i", $ground_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows === 0) {
            $message = "Invalid ground selected.";
        } else {
            $ground = $res->fetch_assoc();
            // Password stored as plain text per your earlier choice
            if ($password === $ground['password']) {
                $_SESSION['attendance_ground_id']   = $ground['id'];
                $_SESSION['attendance_ground_name'] = $ground['name'];
                header("Location: screen.php");
                exit;
            } else {
                $message = "Incorrect ground password.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Attendance Login - ACA</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            margin:0;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background:#0b1724;
            color:#fff;
            display:flex;
            align-items:center;
            justify-content:center;
            height:100vh;
        }
        .card {
            background:#122133;
            padding:24px 28px;
            border-radius:16px;
            width:95%;
            max-width:420px;
            box-shadow:0 18px 40px rgba(0,0,0,0.45);
        }
        h1 {
            margin:0 0 4px;
            font-size:20px;
        }
        p.sub {
            margin:0 0 16px;
            font-size:13px;
            color:#b8c4d8;
        }
        label {
            display:block;
            margin:10px 0 4px;
            font-size:13px;
        }
        select, input[type="password"] {
            width:100%;
            padding:8px 10px;
            border-radius:8px;
            border:1px solid #2b3b52;
            background:#09121f;
            color:#fff;
            font-size:14px;
            box-sizing:border-box;
        }
        button {
            margin-top:16px;
            width:100%;
            padding:10px;
            border:none;
            border-radius:999px;
            background:#1f8ef1;
            font-size:15px;
            color:#fff;
            font-weight:600;
            cursor:pointer;
        }
        button:hover {
            background:#1872c4;
        }
        .msg {
            margin-top:8px;
            font-size:13px;
            color:#ffb3b3;
        }
        .foot {
            margin-top:10px;
            font-size:11px;
            color:#8895aa;
            text-align:center;
        }
    </style>
</head>
<body>
<div class="card">
    <h1>Attendance Device Login</h1>
    <p class="sub">Select your ground and enter its password to start card-based attendance.</p>

    <?php if ($message): ?>
        <div class="msg"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <form method="POST">
        <label>Ground</label>
        <select name="ground_id" required>
            <option value="">-- Select Ground --</option>
            <?php if ($grounds_res): ?>
                <?php while ($g = $grounds_res->fetch_assoc()): ?>
                    <option value="<?php echo $g['id']; ?>">
                        <?php echo htmlspecialchars($g['name']); ?>
                    </option>
                <?php endwhile; ?>
            <?php endif; ?>
        </select>

        <label>Password</label>
        <input type="password" name="password" required>

        <button type="submit">Login & Start Attendance</button>
    </form>

    <div class="foot">
        Australasia Cricket Academy &middot; Attendance Terminal
    </div>
</div>
</body>
</html>
