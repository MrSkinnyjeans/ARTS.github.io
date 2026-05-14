<?php
// ── ARTS · Auth ───────────────────────────────────────────

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/mailer.php';

// ── Session helpers ───────────────────────────────────────

function isLoggedIn(): bool {
    return isset($_SESSION['arts_user']) && ($_SESSION['otp_verified'] ?? false);
}

function currentUser(): array {
    return $_SESSION['arts_user'] ?? [];
}

function requireLogin(): void {
    if (!isset($_SESSION['arts_user'])) { header('Location: index.php'); exit; }
    if (!($_SESSION['otp_verified'] ?? false)) { header('Location: otp_verify.php'); exit; }
}

function requireRole(string $role): void {
    requireLogin();
    if ((currentUser()['role'] ?? '') !== $role) { header('Location: dashboard.php'); exit; }
}

function logout(): void {
    session_destroy();
    header('Location: index.php');
    exit;
}

// ── Gmail validation ──────────────────────────────────────

function isValidGmail(string $email): bool {
    return (bool) preg_match('/^[^@\s]+@gmail\.com$/i', $email);
}

// ── OTP helpers ───────────────────────────────────────────

function generateOTP(): string {
    return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function storeOTP(string $otp): void {
    $_SESSION['otp_code']     = $otp;
    $_SESSION['otp_expires']  = time() + 300;
    $_SESSION['otp_attempts'] = 0;
}

function verifyOTP(string $input): array {
    $_SESSION['otp_attempts'] = ($_SESSION['otp_attempts'] ?? 0) + 1;

    if ($_SESSION['otp_attempts'] > 5)
        return ['success' => false, 'message' => 'Too many attempts. Please log in again.', 'lockout' => true];
    if (!isset($_SESSION['otp_code'], $_SESSION['otp_expires']))
        return ['success' => false, 'message' => 'No OTP found. Please log in again.', 'lockout' => true];
    if (time() > $_SESSION['otp_expires'])
        return ['success' => false, 'message' => 'Your code has expired. Please log in again.', 'lockout' => true];
    if (!hash_equals($_SESSION['otp_code'], trim($input))) {
        $left = max(0, 5 - $_SESSION['otp_attempts']);
        return ['success' => false, 'message' => "Incorrect code. {$left} attempt(s) remaining."];
    }

    $_SESSION['otp_verified'] = true;
    unset($_SESSION['otp_code'], $_SESSION['otp_expires'], $_SESSION['otp_attempts']);
    return ['success' => true];
}

// ── Login ─────────────────────────────────────────────────

function login(string $username, string $password, string $gmail = ''): array {
    require_once __DIR__ . '/db.php';

    if (!isValidGmail($gmail))
        return ['success' => false, 'message' => 'Only @gmail.com addresses are allowed.'];

    $db   = getDB();
    $stmt = $db->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([trim($username)]);
    $user = $stmt->fetch();

    if (!$user)
        return ['success' => false, 'message' => 'Invalid username or password.'];

    // Verify password (supports bcrypt, legacy md5, and plaintext — upgrades on match)
    $hash     = $user['password'];
    $verified = false;

    if (str_starts_with($hash, '$2y$')) {
        $verified = password_verify($password, $hash);
    } elseif (strlen($hash) === 32 && ctype_xdigit($hash)) {
        $verified = (md5($password) === $hash);
        if ($verified)
            $db->prepare('UPDATE users SET password=? WHERE id=?')
               ->execute([password_hash($password, PASSWORD_BCRYPT), $user['id']]);
    } else {
        $verified = ($password === $hash);
        if ($verified)
            $db->prepare('UPDATE users SET password=? WHERE id=?')
               ->execute([password_hash($password, PASSWORD_BCRYPT), $user['id']]);
    }

    if (!$verified)
        return ['success' => false, 'message' => 'Invalid username or password.'];

    // Check Gmail matches the one the principal approved
    $approvedGmail = strtolower(trim($user['approved_gmail'] ?? ''));
    if ($approvedGmail && strtolower($gmail) !== $approvedGmail)
        return ['success' => false, 'message' => 'The Gmail address you entered is not authorized for this account.'];

    // Set session
    $_SESSION['arts_user']    = [
        'id'        => $user['id'],
        'username'  => $user['username'],
        'full_name' => $user['full_name'],
        'role'      => $user['role'],
        'email'     => $gmail,
    ];
    $_SESSION['otp_verified'] = false;

    // Generate and send OTP
    $otp = generateOTP();
    storeOTP($otp);

    // Always attempt to send — never expose the code on screen regardless of outcome
    sendOTPEmail($gmail, $user['full_name'], $otp);
    $_SESSION['otp_email_sent'] = true;
    $_SESSION['otp_gmail']      = $gmail;
    unset($_SESSION['demo_otp']);

    return ['success' => true];
}

// ── JSON response helper ──────────────────────────────────

function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
