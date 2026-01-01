<?php
include "../config.php";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['attendance_ground_id'])) {
    header("Location: login.php");
    exit;
}
$ground_id   = $_SESSION['attendance_ground_id'];
$ground_name = $_SESSION['attendance_ground_name'];

// For display of approximate active batches (optional, not strict)
date_default_timezone_set('America/Toronto');
$dayOfWeek = (int)date('N'); // 1-7 (Mon–Sun)
$timeNow   = date('H:i:s');

$batchesLabel = "";
$bsql = "
    SELECT b.name, b.age_group
    FROM batch_schedule bs
    JOIN batches b ON bs.batch_id = b.id
    WHERE bs.day_of_week = ?
      AND bs.start_time <= ?
      AND bs.end_time >= ?
";
$stmt = $conn->prepare($bsql);
$stmt->bind_param("iss", $dayOfWeek, $timeNow, $timeNow);
$stmt->execute();
$res = $stmt->get_result();
$names = [];
while ($row = $res->fetch_assoc()) {
    $names[] = $row['name'] . " (" . $row['age_group'] . ")";
}
if (!empty($names)) {
    $batchesLabel = implode(", ", $names);
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Attendance Screen - ACA</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        html, body {
            margin:0;
            padding:0;
            height:100%;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background:#050b12;
            color:#fff;
        }
        .wrap {
            height:100%;
            display:flex;
            flex-direction:column;
        }
        .topbar {
            padding:10px 16px;
            background:#0b1724;
            display:flex;
            align-items:center;
            justify-content:space-between;
            font-size:13px;
        }
        .tag {
            padding:3px 10px;
            border-radius:999px;
            background:#132b45;
            font-size:11px;
            color:#b7c8e0;
        }
        .main {
            flex:1;
            display:flex;
            flex-direction:column;
            align-items:center;
            justify-content:center;
            text-align:center;
            padding:16px;
        }
        h1 {
            margin:8px 0 6px;
            font-size:26px;
        }
        p.sub {
            margin:0 0 12px;
            color:#b0c1da;
            font-size:13px;
        }
        .tap-box {
            margin-top:10px;
            width:260px;
            height:260px;
            border-radius:32px;
            background:radial-gradient(circle at top, #1f8ef1 0, #0b1524 55%, #02060d 100%);
            display:flex;
            align-items:center;
            justify-content:center;
            box-shadow:0 22px 60px rgba(0,0,0,0.75);
            position:relative;
        }
        .tap-box::after {
            content:"";
            position:absolute;
            inset:12px;
            border-radius:26px;
            border:1px solid rgba(255,255,255,0.08);
        }
        .tap-text {
            position:relative;
            z-index:2;
            font-size:18px;
            font-weight:600;
        }
        .hidden-input {
            opacity:0;
            position:absolute;
            pointer-events:none;
        }
        .status-bar {
            margin-top:18px;
            min-height:40px;
            font-size:14px;
        }
        .status-ok {
            color:#5cffb3;
        }
        .status-dup {
            color:#ffd36b;
        }
        .status-err {
            color:#ff8b8b;
        }
        .student-card {
            margin-top:8px;
            display:inline-flex;
            align-items:center;
            gap:12px;
            padding:10px 14px;
            border-radius:14px;
            background:#0c1725;
        }
        .student-photo {
            width:52px;
            height:52px;
            border-radius:50%;
            background:#111c2b;
            display:flex;
            align-items:center;
            justify-content:center;
            font-size:22px;
        }
        .student-meta {
            text-align:left;
            font-size:13px;
        }
        .student-meta div.name {
            font-weight:600;
            font-size:14px;
        }
        .student-meta div.batch {
            color:#9fb0c8;
        }
        .footer {
            padding:8px 14px;
            font-size:11px;
            color:#75839b;
            text-align:center;
        }
    </style>
</head>
<body>
<div class="wrap">
    <div class="topbar">
        <div>
            <strong><?php echo htmlspecialchars($ground_name); ?></strong>
            <span style="margin-left:6px; font-size:11px; color:#8ea0bc;">
                Attendance Terminal
            </span>
        </div>
        <div>
            <span class="tag" id="clockTag"></span>
            <?php if ($batchesLabel): ?>
                <span class="tag" style="margin-left:6px;">Current batch: <?php echo htmlspecialchars($batchesLabel); ?></span>
            <?php endif; ?>
        </div>
    </div>

    <div class="main" onclick="focusHiddenInput()">
        <h1>Tap Your Card</h1>
        <p class="sub">Hold your ACA attendance card near the reader.<br>Screen will confirm your name and batch.</p>

        <div class="tap-box">
            <div class="tap-text">Tap Here</div>
        </div>

        <input type="text" id="cardInput" class="hidden-input" autocomplete="off" autofocus>

        <div class="status-bar" id="statusBar">
            Ready for next tap.
        </div>
        <div id="studentCard" style="display:none;">
            <div class="student-card">
                <div class="student-photo" id="studentInitials">A</div>
                <div class="student-meta">
                    <div class="name" id="studentName">Student Name</div>
                    <div class="batch" id="studentBatch">Batch</div>
                    <div class="batch" id="studentAdmission">Admission No.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="footer">
        If the reader is not responding, click anywhere on screen to refocus.
    </div>
</div>

<script>
function updateClock() {
    const el = document.getElementById('clockTag');
    const d = new Date();
    const options = { weekday: 'short', hour: '2-digit', minute: '2-digit' };
    el.textContent = d.toLocaleString(undefined, options);
}
setInterval(updateClock, 1000);
updateClock();

const input = document.getElementById('cardInput');
function focusHiddenInput() {
    input.focus();
}
focusHiddenInput();

// Buffer scanner input until Enter
let buffer = "";
input.addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        const uid = buffer.trim();
        buffer = "";
        input.value = "";
        if (uid) {
            handleCardTap(uid);
        }
    } else {
        // Most keyboard wedge readers send characters like a keyboard
        if (e.key.length === 1) {
            buffer += e.key;
        } else if (e.key === 'Backspace') {
            buffer = buffer.slice(0, -1);
        }
    }
});

// LocalStorage for offline queue
function getOfflineQueue() {
    try {
        const raw = localStorage.getItem('aca_offline_attendance');
        if (!raw) return [];
        return JSON.parse(raw);
    } catch (e) {
        return [];
    }
}
function setOfflineQueue(arr) {
    localStorage.setItem('aca_offline_attendance', JSON.stringify(arr));
}

function showStatus(type, msg, student) {
    const bar = document.getElementById('statusBar');
    bar.className = 'status-bar';
    if (type === 'ok') bar.classList.add('status-ok');
    if (type === 'dup') bar.classList.add('status-dup');
    if (type === 'err') bar.classList.add('status-err');
    bar.textContent = msg;

    const cardWrap = document.getElementById('studentCard');
    if (student) {
        const initials = (student.first_name?.[0] || '') + (student.last_name?.[0] || '');
        document.getElementById('studentInitials').textContent = initials || 'A';
        document.getElementById('studentName').textContent =
            (student.first_name || '') + ' ' + (student.last_name || '');
        document.getElementById('studentBatch').textContent = student.batch_name || '';
        document.getElementById('studentAdmission').textContent = student.admission_no || '';
        cardWrap.style.display = 'block';
    } else {
        cardWrap.style.display = 'none';
    }
}

async function handleCardTap(uid) {
    showStatus('ok', 'Processing...', null);

    const payload = { uid: uid, ts: new Date().toISOString() };
    try {
        const res = await fetch('api.php', {
            method:'POST',
            headers:{ 'Content-Type':'application/json' },
            body: JSON.stringify(payload)
        });
        if (!res.ok) {
            throw new Error('HTTP ' + res.status);
        }
        const data = await res.json();
        if (data.status === 'ok') {
            showStatus('ok', 'Attendance marked: ' + (data.student_name || ''), {
                first_name: data.first_name,
                last_name: data.last_name,
                batch_name: data.batch_name,
                admission_no: data.admission_no
            });
        } else if (data.status === 'duplicate') {
            showStatus('dup', 'Already marked today for ' + (data.student_name || ''), {
                first_name: data.first_name,
                last_name: data.last_name,
                batch_name: data.batch_name,
                admission_no: data.admission_no
            });
        } else {
            showStatus('err', data.message || 'Error', null);
        }
    } catch (e) {
        // network error -> store offline
        const q = getOfflineQueue();
        q.push(payload);
        setOfflineQueue(q);
        showStatus('dup', 'Saved offline. Will sync when online.', null);
    }
}

// Try to sync offline queue periodically
async function syncOfflineQueue() {
    const q = getOfflineQueue();
    if (!q.length) return;
    try {
        const res = await fetch('sync.php', {
            method:'POST',
            headers:{ 'Content-Type':'application/json' },
            body: JSON.stringify({ records: q })
        });
        if (!res.ok) return;
        const data = await res.json();
        if (data.status === 'ok') {
            setOfflineQueue([]);
        }
    } catch (e) {
        // ignore, try again later
    }
}
setInterval(syncOfflineQueue, 15000);
syncOfflineQueue();
</script>
</body>
</html>
