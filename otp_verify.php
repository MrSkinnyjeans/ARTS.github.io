<?php
// ── ARTS · OTP Verification ───────────────────────────────
require_once 'includes/auth.php';

if (!isset($_SESSION['arts_user'])) { header('Location: index.php'); exit; }

if ($_SESSION['otp_verified'] ?? false) {
    $role = currentUser()['role'] ?? 'admin';
    header('Location: ' . ($role === 'principal' ? 'principal.php' : 'dashboard.php'));
    exit;
}

$error  = '';
$notice = '';

// Resend OTP
if (isset($_GET['resend'])) {
    $otp   = generateOTP();
    storeOTP($otp);
    $gmail = $_SESSION['otp_gmail'] ?? (currentUser()['email'] ?? '');
    if ($gmail) {
        sendOTPEmail($gmail, currentUser()['full_name'] ?? 'User', $otp);
        $_SESSION['otp_email_sent'] = true;
        $_SESSION['otp_gmail']      = $gmail;
        $notice = 'A new code has been sent to ' . htmlspecialchars($gmail) . '.';
    } else {
        $notice = 'No Gmail address on file. Please contact the administrator.';
    }
    unset($_SESSION['demo_otp']);
}

// Remove any leftover demo OTP from session — never show it
unset($_SESSION['demo_otp']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = verifyOTP(trim($_POST['otp_code'] ?? ''));
    if ($result['success']) {
        $role = currentUser()['role'] ?? 'admin';
        header('Location: ' . ($role === 'principal' ? 'principal.php' : 'dashboard.php'));
        exit;
    }
    $error = $result['message'];
    if ($result['lockout'] ?? false) logout();
}

$user        = currentUser();
$otpGmail    = $_SESSION['otp_gmail'] ?? ($user['email'] ?? '');
$secondsLeft = max(0, ($_SESSION['otp_expires'] ?? (time() + 300)) - time());
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Verify Login · ARTS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/otp.css">
</head>
<body>
<div class="grid-overlay"></div>

<div class="otp-card">
  <div class="otp-icon">
    <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.6">
      <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
      <polyline points="9 12 11 14 15 10"/>
    </svg>
  </div>

  <h1 class="otp-title">Verify Your Identity</h1>
  <p class="otp-sub">
    Welcome back, <span class="otp-user-name"><?= htmlspecialchars($user['full_name'] ?? 'User') ?></span>.<br>
    Enter the 6-digit code below to continue.
  </p>

  <?php if ($otpGmail): ?>
  <div class="demo-box" style="background:rgba(45,212,191,.1);border-color:rgba(45,212,191,.3);">
    <div>
      <div class="demo-label" style="color:rgba(45,212,191,.85);">Code Sent</div>
      <div style="font-size:14px;color:rgba(255,255,255,.75);margin-top:4px;">
        A 6-digit code was sent to <strong style="color:#fff;"><?= htmlspecialchars($otpGmail) ?></strong>.<br>
        Check your inbox (and spam folder).
      </div>
    </div>
  </div>
  <?php endif; ?>

  <div class="timer-row">
    <div class="timer-bar-wrap">
      <div class="timer-bar" id="timer-bar" style="width:<?= round(($secondsLeft / 300) * 100) ?>%" data-secs="<?= $secondsLeft ?>"></div>
    </div>
    <div class="timer-text" id="timer-text"><?= gmdate('i:s', $secondsLeft) ?></div>
  </div>

  <?php if ($error):  ?><div class="otp-error"  id="otp-error"><?= htmlspecialchars($error)  ?></div><?php endif; ?>
  <?php if ($notice): ?><div class="otp-notice"><?= htmlspecialchars($notice) ?></div><?php endif; ?>

  <form method="POST" action="otp_verify.php" id="otp-form">
    <input type="hidden" name="otp_code" id="otp-hidden">
    <div class="otp-inputs" id="otp-inputs">
      <?php for ($i = 0; $i < 6; $i++): ?>
        <input type="text" inputmode="numeric" maxlength="1" pattern="[0-9]"
               class="otp-digit" id="otp-d<?= $i ?>"
               autocomplete="<?= $i === 0 ? 'one-time-code' : 'off' ?>">
      <?php endfor; ?>
    </div>
    <button type="submit" class="otp-btn" id="otp-btn" disabled>Verify &amp; Continue</button>
  </form>

  <div class="otp-links">
    <a href="logout.php" class="otp-link">← Back to Login</a>
    <a href="otp_verify.php?resend=1" class="otp-link">Resend Code</a>
  </div>
</div>

<script src="assets/js/otp.js"></script>
</body>
</html>
