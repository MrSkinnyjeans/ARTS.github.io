<?php
// ── ARTS · Set Password (new account activation) ─────────
require_once 'includes/db.php';
require_once 'includes/migrate_check.php';

$token = trim($_GET['token'] ?? '');
$error = '';
$done  = false;

if (!$token) {
    die('Invalid or missing token.');
}

$db   = getDB();
$stmt = $db->prepare('SELECT * FROM users WHERE password_reset_token = ? AND token_expires > NOW() LIMIT 1');
$stmt->execute([$token]);
$user = $stmt->fetch();

if (!$user) {
    die('This link has expired or is invalid. Please contact the administrator.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pass1 = $_POST['password']  ?? '';
    $pass2 = $_POST['password2'] ?? '';

    if (strlen($pass1) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($pass1 !== $pass2) {
        $error = 'Passwords do not match. Please try again.';
    } else {
        $db->prepare(
            'UPDATE users SET password=?, password_reset_token=NULL, token_expires=NULL WHERE id=?'
        )->execute([password_hash($pass1, PASSWORD_BCRYPT), $user['id']]);
        $done = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Set Your Password · ARTS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/otp.css">
<style>
.set-card{position:relative;z-index:1;background:rgba(255,255,255,.07);backdrop-filter:blur(28px);border:1px solid rgba(255,255,255,.16);border-radius:22px;padding:48px 48px 40px;width:100%;max-width:420px;box-shadow:0 12px 50px rgba(0,0,0,.4)}
.set-title{font-family:'DM Serif Display',serif;font-size:26px;color:#fff;text-align:center;margin-bottom:8px}
.set-sub{font-size:13.5px;color:rgba(255,255,255,.55);text-align:center;margin-bottom:28px;line-height:1.6}
.set-group{margin-bottom:18px}
.set-group label{display:block;font-size:12px;font-weight:600;color:rgba(255,255,255,.6);letter-spacing:.3px;text-transform:uppercase;margin-bottom:7px}
.set-group input{width:100%;padding:13px 16px;border:1.5px solid rgba(255,255,255,.18);border-radius:10px;font-size:14px;font-family:'DM Sans',sans-serif;color:#fff;background:rgba(255,255,255,.08);transition:all .22s}
.set-group input:focus{outline:none;border-color:rgba(255,255,255,.45);background:rgba(255,255,255,.13)}
.set-group input::placeholder{color:rgba(255,255,255,.35)}
.set-btn{width:100%;padding:14px;background:linear-gradient(135deg,#1e5a9a,#1a8a7a);color:#fff;border:none;border-radius:11px;font-size:15px;font-weight:600;font-family:'DM Sans',sans-serif;cursor:pointer;transition:all .25s;margin-top:4px}
.set-btn:hover{background:linear-gradient(135deg,#2468b0,#1fa090);transform:translateY(-1px)}
.set-error{background:rgba(248,113,113,.12);border:1px solid rgba(248,113,113,.3);border-radius:9px;padding:11px 15px;font-size:13px;color:#fca5a5;margin-bottom:18px;text-align:center}
.set-success{text-align:center;padding:10px 0}
.set-success p{font-family:'DM Serif Display',serif;font-size:22px;color:#fff;margin-bottom:8px}
.set-success span{font-size:13.5px;color:rgba(255,255,255,.55)}
.hint{font-size:11.5px;color:rgba(255,255,255,.35);margin-top:6px}
</style>
</head>
<body>
<div class="grid-overlay"></div>

<div class="set-card">
  <div class="otp-icon" style="margin-bottom:20px">
    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.6">
      <rect x="3" y="11" width="18" height="11" rx="2"/>
      <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
    </svg>
  </div>

  <?php if ($done): ?>
    <div class="set-success">
      <div style="font-size:48px;margin-bottom:16px">✓</div>
      <p>Password Set!</p>
      <span>Your account is now active. You can log in.</span>
      <br><br>
      <a href="index.php" style="color:rgba(255,255,255,.7);font-size:13px;text-decoration:underline">Go to Login →</a>
    </div>
  <?php else: ?>
    <h1 class="set-title">Set Your Password</h1>
    <p class="set-sub">
      Welcome, <strong style="color:#fff"><?= htmlspecialchars($user['full_name']) ?></strong>.<br>
      Create a password to activate your account.
    </p>

    <?php if ($error): ?>
      <div class="set-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
      <div class="set-group">
        <label>New Password</label>
        <input type="password" name="password" placeholder="At least 8 characters"
               autocomplete="new-password" required minlength="8">
        <div class="hint">Minimum 8 characters.</div>
      </div>
      <div class="set-group">
        <label>Re-enter Password</label>
        <input type="password" name="password2" placeholder="Repeat your password"
               autocomplete="new-password" required minlength="8">
      </div>
      <button type="submit" class="set-btn">Activate Account</button>
    </form>
  <?php endif; ?>
</div>

</body>
</html>
