<?php
// ── ARTS · Email Verification for Access Requests ─────────
require_once 'includes/db.php';
require_once 'includes/migrate_check.php';
require_once 'includes/mailer.php';

$token = trim($_GET['token'] ?? '');
$status = 'error';
$message = 'Invalid or expired verification link.';

if ($token) {
    $db   = getDB();
    $stmt = $db->prepare('SELECT * FROM pending_access_requests WHERE token = ? AND expires_at > NOW() LIMIT 1');
    $stmt->execute([$token]);
    $pending = $stmt->fetch();

    if ($pending) {
        try {
            // Save to actual access_requests
            $db->prepare(
                'INSERT INTO access_requests (full_name, email, position, department, reason)
                 VALUES (?, ?, ?, ?, ?)'
            )->execute([$pending['full_name'], $pending['email'], '', null, '']);

            // Remove from pending
            $db->prepare('DELETE FROM pending_access_requests WHERE token = ?')->execute([$token]);

            // Notify principal
            sendAccessRequestNotification($pending['full_name'], $pending['email']);

            $status  = 'success';
            $message = 'Your email has been verified! Your access request has been submitted and is pending review by the Principal.';
        } catch (Exception $e) {
            $message = 'Something went wrong. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Email Verification · ARTS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/otp.css">
</head>
<body>
<div class="grid-overlay"></div>
<div class="set-card" style="position:relative;z-index:1;background:rgba(255,255,255,.07);backdrop-filter:blur(28px);border:1px solid rgba(255,255,255,.16);border-radius:22px;padding:48px;width:100%;max-width:460px;text-align:center">
  <?php if ($status === 'success'): ?>
    <div style="font-size:56px;margin-bottom:20px">✅</div>
    <h2 style="font-family:'DM Serif Display',serif;color:#fff;margin-bottom:12px">Email Verified!</h2>
    <p style="color:rgba(255,255,255,.65);font-size:14px;line-height:1.7;margin-bottom:28px"><?= htmlspecialchars($message) ?></p>
  <?php else: ?>
    <div style="font-size:56px;margin-bottom:20px">❌</div>
    <h2 style="font-family:'DM Serif Display',serif;color:#fff;margin-bottom:12px">Verification Failed</h2>
    <p style="color:rgba(255,255,255,.65);font-size:14px;line-height:1.7;margin-bottom:28px"><?= htmlspecialchars($message) ?></p>
  <?php endif; ?>
  <a href="index.php" style="color:rgba(255,255,255,.6);font-size:13px;text-decoration:underline">← Back to Home</a>
</div>
</body>
</html>
