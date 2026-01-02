<?php
declare(strict_types=1);

require "config.php"; // already starts session + sets cookie flags

// -------------------------
// Minimal CSRF helpers
// -------------------------
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}
function csrf_verify(?string $token): void {
    if (!$token || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $token)) {
        http_response_code(403);
        exit('Invalid CSRF token');
    }
}

// If already logged in, redirect based on role
if (isset($_SESSION['user_id'], $_SESSION['user_role'])) {
    $role = $_SESSION['user_role'];
    $target = match ($role) {
        'admin'      => 'admin/dashboard.php',
        'coach'      => 'coach/dashboard.php',
        'student'    => 'student/dashboard.php',
        'superadmin' => 'superadmin/dashboard.php',
        default      => 'admin/dashboard.php',
    };
    header("Location: $target");
    exit;
}

$error = "";
$username_prefill = $_COOKIE['aca_remember_username'] ?? "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify($_POST['csrf'] ?? null);

    $username = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $remember = !empty($_POST['remember_me']);

    if ($username === '' || $password === '') {
        $error = "Please enter both username and password.";
    } else {
        $stmt = $conn->prepare("SELECT id, username, password_hash, role FROM users WHERE username = ? LIMIT 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $res = $stmt->get_result();
        $user = $res ? $res->fetch_assoc() : null;

        if (!$user || empty($user['password_hash']) || !password_verify($password, (string)$user['password_hash'])) {
            $error = "Invalid credentials.";
        } else {
            // Session fixation protection
            session_regenerate_id(true);

            $_SESSION['user_id']   = (int)$user['id'];
            $_SESSION['username']  = (string)$user['username'];
            $_SESSION['user_role'] = (string)$user['role'];

            // Remember username (prefill only) with safe attributes
            if ($remember) {
                setcookie('aca_remember_username', (string)$user['username'], [
                    'expires'  => time() + 60 * 60 * 24 * 30,
                    'path'     => '/',
                    'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
            } else {
                setcookie('aca_remember_username', '', [
                    'expires'  => time() - 3600,
                    'path'     => '/',
                    'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
            }

            $role = (string)$user['role'];
            $target = match ($role) {
                'admin'      => 'admin/dashboard.php',
                'coach'      => 'coach/dashboard.php',
                'student'    => 'student/dashboard.php',
                'superadmin' => 'superadmin/dashboard.php',
                default      => 'admin/dashboard.php',
            };

            header("Location: $target");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>ACA Portal Login</title>
    <style>
        body{margin:0;font-family:system-ui,-apple-system,"Segoe UI",sans-serif;background:radial-gradient(circle at top,#004BA0 0%,#001D3D 40%,#000814 100%);color:#E5E7EB;min-height:100vh;display:flex;align-items:center;justify-content:center}
        .login-wrap{width:100%;max-width:420px;padding:16px}
        .login-card{background:rgba(0,0,20,0.82);border-radius:18px;padding:24px 24px 20px;box-shadow:0 18px 45px rgba(0,0,0,0.55);border:1px solid rgba(57,231,255,0.16);position:relative;overflow:hidden}
        .login-card::before{content:"";position:absolute;inset:-40%;background:radial-gradient(circle at top left,rgba(57,231,255,0.08),transparent 60%),radial-gradient(circle at bottom right,rgba(245,196,0,0.12),transparent 55%);opacity:.9}
        .login-inner{position:relative;z-index:2}
        .login-header{text-align:center;margin-bottom:18px}
        .login-title{font-size:22px;font-weight:700;letter-spacing:.03em}
        .login-subtitle{font-size:13px;color:#9CA3AF;margin-top:6px}
        .badge{display:inline-flex;align-items:center;gap:6px;padding:3px 9px;border-radius:999px;font-size:11px;background:rgba(0,0,0,0.55);border:1px solid rgba(245,196,0,0.5);color:#F5C400;margin-bottom:8px}
        .field{margin-bottom:12px}
        .field label{display:block;font-size:12px;color:#E5E7EB;margin-bottom:4px}
        .field input[type="text"],.field input[type="password"]{width:100%;padding:9px 11px;border-radius:10px;border:1px solid #1F2937;background:rgba(15,23,42,0.9);color:#F9FAFB;font-size:13px;outline:none}
        .field input:focus{border-color:#39E7FF;box-shadow:0 0 0 1px rgba(57,231,255,0.5)}
        .row-between{display:flex;justify-content:space-between;align-items:center;font-size:12px;color:#9CA3AF;margin-bottom:12px}
        .row-between a{color:#39E7FF;text-decoration:none}
        .row-between a:hover{text-decoration:underline}
        .btn-primary{width:100%;padding:9px 12px;border-radius:999px;border:none;background:linear-gradient(135deg,#003566,#004BA0);color:#F9FAFB;font-weight:600;font-size:14px;cursor:pointer}
        .btn-primary:hover{background:linear-gradient(135deg,#004BA0,#003566)}
        .error{background:rgba(220,38,38,0.12);border-radius:10px;padding:7px 9px;font-size:12px;color:#FCA5A5;margin-bottom:12px;border:1px solid rgba(248,113,113,0.4)}
        .remember{display:flex;align-items:center;gap:6px}
        .remember input{width:14px;height:14px}
        .footer{margin-top:10px;font-size:11px;text-align:center;color:#6B7280}
        .loading-overlay{position:fixed;inset:0;background:rgba(0,0,0,0.7);display:none;align-items:center;justify-content:center;z-index:9999;color:#F9FAFB;font-size:14px;flex-direction:column;gap:8px}
        .spinner{width:28px;height:28px;border-radius:50%;border:3px solid rgba(57,231,255,0.2);border-top-color:#39E7FF;animation:spin .8s linear infinite}
        @keyframes spin{to{transform:rotate(360deg)}}
    </style>
</head>
<body>
<div class="loading-overlay" id="loadingOverlay">
    <div class="spinner"></div>
    <div>Logging in… Redirecting to your dashboard</div>
</div>

<div class="login-wrap">
    <div class="login-card">
        <div class="login-inner">
            <div class="login-header">
                <div class="badge"><span>🏏</span><span>ACA Unified Portal</span></div>
                <div class="login-title">Australasia Cricket Academy</div>
                <div class="login-subtitle">Login with your academy credentials<br>(Students • Coaches • Admins)</div>
            </div>

            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <form method="POST" id="loginForm">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">

                <div class="field">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username"
                           value="<?php echo htmlspecialchars($username_prefill ?: ($_POST['username'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                           autocomplete="username">
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" autocomplete="current-password">
                </div>

                <div class="row-between">
                    <label class="remember">
                        <input type="checkbox" name="remember_me" <?php echo $username_prefill ? 'checked' : ''; ?>>
                        <span>Remember me</span>
                    </label>
                    <a href="forgot-password.php">Forgot password?</a>
                </div>

                <button type="submit" class="btn-primary">Login</button>
            </form>

            <div class="footer">© <?php echo date('Y'); ?> Australasia Cricket Academy</div>
        </div>
    </div>
</div>

<script>
const form=document.getElementById('loginForm');
const overlay=document.getElementById('loadingOverlay');
if(form && overlay){ form.addEventListener('submit',()=>{overlay.style.display='flex';}); }
</script>
</body>
</html>
