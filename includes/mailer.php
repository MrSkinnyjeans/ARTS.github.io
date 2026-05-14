<?php
// ── ARTS · Mailer ─────────────────────────────────────────
// Single place for all outgoing emails.
// Requires: composer require phpmailer/phpmailer
// Config:   config/mail.php

function mailerReady(): bool {
    return file_exists(__DIR__ . '/../vendor/autoload.php');
}

function buildMailer(): ?PHPMailer\PHPMailer\PHPMailer {
    if (!mailerReady()) {
        error_log('PHPMailer not found. Run: composer require phpmailer/phpmailer');
        return null;
    }
    require_once __DIR__ . '/../vendor/autoload.php';

    $cfg  = require __DIR__ . '/../config/mail.php';
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    $mail->isSMTP();
    $mail->Host       = $cfg['host'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $cfg['username'];
    $mail->Password   = $cfg['password'];
    $mail->SMTPSecure = $cfg['encryption'] ?? 'tls';
    $mail->Port       = $cfg['port'];
    $mail->setFrom($cfg['from_email'], $cfg['from_name']);

    return $mail;
}

// Send OTP code to a user's Gmail
function sendOTPEmail(string $toEmail, string $toName, string $otp): bool {
    $mail = buildMailer();
    if (!$mail) return false;

    try {
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = 'Your ARTS Login Code: ' . $otp;
        $mail->Body    = '
            <div style="font-family:Arial,sans-serif;max-width:480px;margin:0 auto;padding:32px;background:#f9fafb;border-radius:12px;">
              <h2 style="color:#0b1d3a;margin-bottom:8px;">ARTS Login Verification</h2>
              <p style="color:#555;margin-bottom:24px;">Hi ' . htmlspecialchars($toName) . ', use the code below to complete your login. It expires in <strong>5 minutes</strong>.</p>
              <div style="background:#0b1d3a;color:#fff;font-size:36px;font-weight:700;letter-spacing:12px;text-align:center;padding:20px 32px;border-radius:10px;margin-bottom:24px;">' . $otp . '</div>
              <p style="color:#999;font-size:12px;">If you did not attempt to log in, please ignore this email.</p>
            </div>';
        $mail->AltBody = "Your ARTS login code is: $otp\nIt expires in 5 minutes.";
        $mail->send();
        return true;
    } catch (\Exception $e) {
        error_log('OTP email failed: ' . $mail->ErrorInfo);
        return false;
    }
}

// Send access request confirmation to the applicant
function sendAccessRequestConfirmation(string $toEmail, string $toName): bool {
    $mail = buildMailer();
    if (!$mail) return false;

    try {
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = 'ARTS – Access Request Received';
        $mail->Body    = '
            <div style="font-family:Arial,sans-serif;max-width:480px;margin:0 auto;padding:32px;background:#f9fafb;border-radius:12px;">
              <h2 style="color:#0b1d3a;margin-bottom:8px;">Request Received</h2>
              <p style="color:#555;margin-bottom:16px;">Hi <strong>' . htmlspecialchars($toName) . '</strong>,</p>
              <p style="color:#555;margin-bottom:16px;">Your access request for the <strong>Academic Referral Tracking System (ARTS)</strong> has been received and is pending review by the Principal.</p>
              <p style="color:#555;">You will be notified once a decision has been made.</p>
              <p style="color:#999;font-size:12px;margin-top:24px;">If you did not submit this request, please ignore this email.</p>
            </div>';
        $mail->AltBody = "Hi $toName,\n\nYour ARTS access request is pending review by the Principal.\n\nIf you did not submit this, please ignore this email.";
        $mail->send();
        return true;
    } catch (\Exception $e) {
        error_log('Access request email failed: ' . $mail->ErrorInfo);
        if (session_status() === PHP_SESSION_NONE) session_start();
        $_SESSION['last_mail_error'] = $mail->ErrorInfo ?: $e->getMessage();
        return false;
    }
}

// Forward a contact form message to the admin Gmail
function sendContactMessage(string $fromName, string $fromEmail, string $subject, string $message): bool {
    $mail = buildMailer();
    if (!$mail) return false;

    // Send to the principal's approved Gmail dynamically
    $toEmail = 'joshuamission17@gmail.com'; // fallback
    try {
        require_once __DIR__ . '/db.php';
        $db   = getDB();
        $stmt = $db->prepare("SELECT approved_gmail FROM users WHERE role = 'principal' LIMIT 1");
        $stmt->execute();
        $row = $stmt->fetch();
        if ($row && $row['approved_gmail']) {
            $toEmail = $row['approved_gmail'];
        }
    } catch (\Exception $e) { /* use fallback */ }

    try {
        $mail->addAddress($toEmail, 'ARTS Principal');
        $mail->addReplyTo($fromEmail, $fromName);
        $mail->isHTML(true);
        $mail->Subject = 'ARTS Contact: ' . $subject;
        $mail->Body    = '
            <div style="font-family:Arial,sans-serif;max-width:520px;margin:0 auto;padding:32px;background:#f9fafb;border-radius:12px;">
              <h2 style="color:#0b1d3a;margin-bottom:4px;">New Contact Message</h2>
              <p style="color:#999;font-size:13px;margin-bottom:24px;">Sent via the ARTS landing page contact form</p>
              <table style="width:100%;font-size:14px;margin-bottom:20px">
                <tr><td style="color:#555;padding:6px 0;width:100px"><strong>From</strong></td><td style="color:#222">' . htmlspecialchars($fromName) . '</td></tr>
                <tr><td style="color:#555;padding:6px 0"><strong>Email</strong></td><td><a href="mailto:' . htmlspecialchars($fromEmail) . '" style="color:#1e5a9a">' . htmlspecialchars($fromEmail) . '</a></td></tr>
                <tr><td style="color:#555;padding:6px 0"><strong>Subject</strong></td><td style="color:#222">' . htmlspecialchars($subject) . '</td></tr>
              </table>
              <div style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:16px 20px;font-size:14px;color:#333;line-height:1.7;white-space:pre-wrap">' . htmlspecialchars($message) . '</div>
            </div>';
        $mail->AltBody = "From: $fromName <$fromEmail>\nSubject: $subject\n\n$message";
        $mail->send();
        return true;
    } catch (\Exception $e) {
        error_log('Contact email failed: ' . $mail->ErrorInfo);
        if (session_status() === PHP_SESSION_NONE) session_start();
        $_SESSION['last_mail_error'] = $mail->ErrorInfo ?: $e->getMessage();
        return false;
    }
}

// Send schedule notification to an applicant
function sendScheduleEmail(string $toEmail, string $toName, ?string $interviewDate, ?string $examSchedule, ?string $notes): bool {
    $mail = buildMailer();
    if (!$mail) return false;

    try {
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = 'ARTS – Your Schedule at ACLC Mandaue';

        $interviewRow = $interviewDate
            ? '<tr><td style="padding:8px 0;color:#555;width:160px"><strong>Interview</strong></td><td style="padding:8px 0;color:#222">' . date('F j, Y \a\t g:i A', strtotime($interviewDate)) . '</td></tr>'
            : '';
        $examRow = $examSchedule
            ? '<tr><td style="padding:8px 0;color:#555"><strong>Entrance Exam</strong></td><td style="padding:8px 0;color:#222;font-weight:700;color:#0b1d3a">' . date('F j, Y \a\t g:i A', strtotime($examSchedule)) . '</td></tr>'
            : '';
        $notesRow = $notes
            ? '<div style="background:#f0f4ff;border-left:4px solid #1e5a9a;padding:12px 16px;border-radius:0 8px 8px 0;margin-top:20px;font-size:14px;color:#333">' . nl2br(htmlspecialchars($notes)) . '</div>'
            : '';

        $mail->Body = '
            <div style="font-family:Arial,sans-serif;max-width:520px;margin:0 auto;padding:32px;background:#f9fafb;border-radius:12px;">
              <div style="background:#0b1d3a;border-radius:10px 10px 0 0;padding:24px 28px;margin:-32px -32px 28px;">
                <h2 style="color:#fff;margin:0;font-size:20px">ACLC College of Mandaue</h2>
                <p style="color:rgba(255,255,255,.6);margin:4px 0 0;font-size:13px">Academic Referral Tracking System</p>
              </div>
              <p style="color:#555;margin-bottom:20px">Dear <strong>' . htmlspecialchars($toName) . '</strong>,</p>
              <p style="color:#555;margin-bottom:20px">We are pleased to inform you that your schedule has been set. Please take note of the following:</p>
              <table style="width:100%;border-collapse:collapse;font-size:14px;margin-bottom:8px">
                ' . $interviewRow . $examRow . '
              </table>
              ' . $notesRow . '
              <p style="color:#555;margin-top:24px;font-size:13px">Please arrive <strong>15 minutes early</strong> and bring a valid ID. If you have any questions, contact the admissions office.</p>
              <p style="color:#999;font-size:12px;margin-top:28px;border-top:1px solid #e5e7eb;padding-top:16px">This is an automated message from the ARTS system. Do not reply to this email.</p>
            </div>';
        $mail->AltBody = "Dear $toName,\n\nYour schedule at ACLC Mandaue:\n"
            . ($interviewDate ? "Interview: " . date('F j, Y \a\t g:i A', strtotime($interviewDate)) . "\n" : '')
            . ($examSchedule  ? "Entrance Exam: " . date('F j, Y \a\t g:i A', strtotime($examSchedule)) . "\n" : '')
            . ($notes ? "\nNotes: $notes\n" : '')
            . "\nPlease arrive 15 minutes early and bring a valid ID.";

        $mail->send();
        return true;
    } catch (\Exception $e) {
        error_log('Schedule email failed: ' . $mail->ErrorInfo);
        return false;
    }
}

// Notify the principal that a new access request was submitted
function sendAccessRequestNotification(string $applicantName, string $applicantEmail): bool {
    $mail = buildMailer();
    if (!$mail) return false;

    // Get principal's Gmail from DB
    $toEmail = 'joshuamission17@gmail.com'; // fallback
    try {
        require_once __DIR__ . '/db.php';
        $db   = getDB();
        $stmt = $db->prepare("SELECT approved_gmail FROM users WHERE role = 'principal' LIMIT 1");
        $stmt->execute();
        $row = $stmt->fetch();
        if ($row && $row['approved_gmail']) $toEmail = $row['approved_gmail'];
    } catch (\Exception $e) { /* use fallback */ }

    try {
        $mail->addAddress($toEmail, 'ARTS Principal');
        $mail->isHTML(true);
        $mail->Subject = 'ARTS – New Access Request from ' . $applicantName;
        $mail->Body    = '
            <div style="font-family:Arial,sans-serif;max-width:480px;margin:0 auto;padding:32px;background:#f9fafb;border-radius:12px;">
              <h2 style="color:#0b1d3a;margin-bottom:8px;">New Access Request</h2>
              <p style="color:#555;margin-bottom:20px;">Someone has submitted a request for system access and is waiting for your approval.</p>
              <table style="width:100%;font-size:14px;margin-bottom:20px">
                <tr><td style="padding:8px 0;color:#555;width:120px"><strong>Name</strong></td><td style="color:#222">' . htmlspecialchars($applicantName) . '</td></tr>
                <tr><td style="padding:8px 0;color:#555"><strong>Gmail</strong></td><td><a href="mailto:' . htmlspecialchars($applicantEmail) . '" style="color:#1e5a9a">' . htmlspecialchars($applicantEmail) . '</a></td></tr>
              </table>
              <p style="color:#555;font-size:13px">Log in to the <strong>Principal Dashboard</strong> to review and approve or reject this request.</p>
              <p style="color:#999;font-size:12px;margin-top:24px;border-top:1px solid #e5e7eb;padding-top:16px">This is an automated message from the ARTS system.</p>
            </div>';
        $mail->AltBody = "New access request from $applicantName ($applicantEmail).\n\nLog in to the Principal Dashboard to review it.";
        $mail->send();
        return true;
    } catch (\Exception $e) {
        error_log('Access request notification failed: ' . $mail->ErrorInfo);
        return false;
    }
}

// Send set-password link to a newly approved account
function sendSetPasswordEmail(string $toEmail, string $toName, string $token): bool {
    $mail = buildMailer();
    if (!$mail) return false;

    $link = 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/arts_complete/set_password.php?token=' . urlencode($token);

    try {
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = 'ARTS – Set Your Account Password';
        $mail->Body    = '
            <div style="font-family:Arial,sans-serif;max-width:480px;margin:0 auto;padding:32px;background:#f9fafb;border-radius:12px;">
              <div style="background:#0b1d3a;border-radius:10px 10px 0 0;padding:24px 28px;margin:-32px -32px 28px;">
                <h2 style="color:#fff;margin:0;font-size:20px">ACLC College of Mandaue</h2>
                <p style="color:rgba(255,255,255,.6);margin:4px 0 0;font-size:13px">Academic Referral Tracking System</p>
              </div>
              <p style="color:#555;margin-bottom:16px">Hi <strong>' . htmlspecialchars($toName) . '</strong>,</p>
              <p style="color:#555;margin-bottom:24px">Your account has been approved by the Principal. Click the button below to set your password and activate your account.</p>
              <div style="text-align:center;margin-bottom:24px">
                <a href="' . $link . '" style="background:linear-gradient(135deg,#1e5a9a,#1a8a7a);color:#fff;padding:14px 32px;border-radius:10px;text-decoration:none;font-weight:700;font-size:15px;display:inline-block">
                  Set My Password
                </a>
              </div>
              <p style="color:#999;font-size:12px">This link expires in <strong>24 hours</strong>. If you did not request this, please ignore this email.</p>
              <p style="color:#bbb;font-size:11px;margin-top:8px;word-break:break-all">Or copy this link: ' . $link . '</p>
            </div>';
        $mail->AltBody = "Hi $toName,\n\nYour ARTS account has been approved. Set your password here:\n$link\n\nThis link expires in 24 hours.";
        $mail->send();
        return true;
    } catch (\Exception $e) {
        error_log('Set password email failed: ' . $mail->ErrorInfo);
        return false;
    }
}

// Send email verification link for access request
function sendEmailVerification(string $toEmail, string $toName, string $token): bool {
    $mail = buildMailer();
    if (!$mail) return false;

    $link = 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/arts_complete/verify_email.php?token=' . urlencode($token);

    try {
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = 'ARTS – Verify Your Email Address';
        $mail->Body    = '
            <div style="font-family:Arial,sans-serif;max-width:480px;margin:0 auto;padding:32px;background:#f9fafb;border-radius:12px;">
              <div style="background:#0b1d3a;border-radius:10px 10px 0 0;padding:24px 28px;margin:-32px -32px 28px;">
                <h2 style="color:#fff;margin:0;font-size:20px">ACLC College of Mandaue</h2>
                <p style="color:rgba(255,255,255,.6);margin:4px 0 0;font-size:13px">Academic Referral Tracking System</p>
              </div>
              <p style="color:#555;margin-bottom:16px">Hi <strong>' . htmlspecialchars($toName) . '</strong>,</p>
              <p style="color:#555;margin-bottom:24px">Please verify your email address to complete your access request. Click the button below:</p>
              <div style="text-align:center;margin-bottom:24px">
                <a href="' . $link . '" style="background:linear-gradient(135deg,#1e5a9a,#1a8a7a);color:#fff;padding:14px 32px;border-radius:10px;text-decoration:none;font-weight:700;font-size:15px;display:inline-block">
                  Verify My Email
                </a>
              </div>
              <p style="color:#999;font-size:12px">This link expires in <strong>1 hour</strong>. If you did not submit this request, please ignore this email.</p>
              <p style="color:#bbb;font-size:11px;margin-top:8px;word-break:break-all">Or copy: ' . $link . '</p>
            </div>';
        $mail->AltBody = "Hi $toName,\n\nVerify your email for your ARTS access request:\n$link\n\nExpires in 1 hour.";
        $mail->send();
        return true;
    } catch (\Exception $e) {
        error_log('Email verification failed: ' . $mail->ErrorInfo);
        return false;
    }
}
