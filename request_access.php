<?php
// ── ARTS · Request Access ─────────────────────────────────
require_once 'includes/db.php';
require_once 'includes/migrate_check.php';
require_once 'includes/mailer.php';
require_once 'includes/auth.php';
require_once 'config/recaptcha.php';

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$fullName       = trim($_POST['full_name'] ?? '');
$email          = strtolower(trim($_POST['email'] ?? ''));
$recaptchaToken = $_POST['g-recaptcha-response'] ?? '';

// ── Validate fields ───────────────────────────────────────
if (!$fullName || !$email) {
    echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
    exit;
}

if (!isValidGmail($email)) {
    echo json_encode(['success' => false, 'message' => 'Only @gmail.com addresses are accepted.']);
    exit;
}

// ── Verify reCAPTCHA ──────────────────────────────────────
if (!$recaptchaToken) {
    echo json_encode(['success' => false, 'message' => 'Please complete the reCAPTCHA verification.']);
    exit;
}

$ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 5,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query([
        'secret'   => RECAPTCHA_SECRET_KEY,
        'response' => $recaptchaToken,
    ]),
]);
$verifyRaw = curl_exec($ch);
$curlError = curl_error($ch);
curl_close($ch);

if (!$curlError && $verifyRaw) {
    $verifyData = json_decode($verifyRaw, true);
    if (!$verifyData || !$verifyData['success']) {
        echo json_encode(['success' => false, 'message' => 'reCAPTCHA verification failed. Please try again.']);
        exit;
    }
}

// ── Check for duplicate ───────────────────────────────────
$db = getDB();
$existing = $db->prepare("SELECT id FROM access_requests WHERE email = ? AND status IN ('pending','approved') LIMIT 1");
$existing->execute([$email]);
if ($existing->fetch()) {
    echo json_encode(['success' => false, 'message' => 'An access request for this Gmail already exists.']);
    exit;
}

// ── Save directly to access_requests ─────────────────────
try {
    $db->prepare(
        'INSERT INTO access_requests (full_name, email, position, department, reason)
         VALUES (?, ?, ?, ?, ?)'
    )->execute([$fullName, $email, '', null, '']);

    // Notify principal by email (best-effort)
    sendAccessRequestNotification($fullName, $email);

    // Send confirmation to applicant (best-effort)
    sendAccessRequestConfirmation($email, $fullName);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
