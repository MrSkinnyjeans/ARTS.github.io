<?php
// ── ARTS · Contact Form Handler (AJAX endpoint) ───────────
// POST: name, email, subject, message
// Sends the message to joshuamission17@gmail.com

require_once 'includes/mailer.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$name    = trim($_POST['name']    ?? '');
$email   = trim($_POST['email']   ?? '');
$subject = trim($_POST['subject'] ?? '');
$message = trim($_POST['message'] ?? '');

if (!$name || !$email || !$subject || !$message) {
    echo json_encode(['success' => false, 'message' => 'Please fill in all fields.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
    exit;
}

$sent = sendContactMessage($name, $email, $subject, $message);

if ($sent) {
    echo json_encode(['success' => true]);
} else {
    $error = $_SESSION['last_mail_error'] ?? 'Unknown SMTP error.';
    echo json_encode(['success' => false, 'message' => 'Failed to send: ' . $error]);
}
