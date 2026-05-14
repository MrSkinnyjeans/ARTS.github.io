<?php
// ── ARTS · Login / Landing Page ───────────────────────────
require_once 'includes/auth.php';

if (isLoggedIn()) {
    $role = currentUser()['role'] ?? 'admin';
    header('Location: ' . ($role === 'principal' ? 'principal.php' : 'dashboard.php'));
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $gmail = trim($_POST['gmail'] ?? '');
    if (!isValidGmail($gmail)) {
        $error = 'Please enter a valid @gmail.com address.';
    } else {
        $result = login(trim($_POST['username'] ?? ''), $_POST['password'] ?? '', $gmail);
        if ($result['success']) {
            header('Location: otp_verify.php');
            exit;
        }
        $error = $result['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Login · ARTS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;1,400&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/index.css">
<script src="https://www.google.com/recaptcha/api.js" async defer></script>
</head>
<body>

<div class="page-bg"><div class="blob-mid"></div><div class="page-grid"></div></div>

<div class="page-wrap">

  <!-- Navbar -->
  <header class="login-navbar">
    <div class="login-navbar-brand">Academic Referral Tracking System (for Entrance Exams)</div>
    <nav class="login-navbar-links">
      <a href="#home"    class="lnav-link active">Home</a>
      <a href="#about"   class="lnav-link">About</a>
      <a href="#login"   class="lnav-link">Login</a>
      <a href="#contact" class="lnav-link">Contact</a>
    </nav>
  </header>

  <?php include 'includes/partials/index_hero.php'; ?>

  <!-- Login card -->
  <section class="login-section" id="login">
    <div class="login-card">
      <h2 class="login-card-title">Staff Login</h2>
      <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <form method="POST" action="index.php" autocomplete="off">
        <div class="lform-group">
          <span class="lform-icon"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="8" r="4"/><path d="M4 20v-1a8 8 0 0 1 16 0v1"/></svg></span>
          <input type="text" name="username" placeholder="Username" required autofocus autocomplete="new-password">
        </div>
        <div class="lform-group">
          <span class="lform-icon"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></span>
          <input type="password" name="password" placeholder="Password" required autocomplete="new-password">
        </div>
        <div class="lform-group">
          <span class="lform-icon"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg></span>
          <input type="email" name="gmail" placeholder="yourname@gmail.com"
                 pattern="[^@\s]+@gmail\.com" title="Only @gmail.com addresses are allowed" required autocomplete="new-password">
        </div>
        <p style="font-size:11.5px;color:rgba(255,255,255,.4);margin:-10px 0 14px 4px;">OTP will be sent to this Gmail address.</p>
        <button type="submit" class="lbtn-login">Login</button>
      </form>
      <div class="login-card-links">
        <a href="#">Forgot Password?</a>
        <a href="#" onclick="document.getElementById('modal-request').classList.add('open');return false">Request Access</a>
      </div>
    </div>
  </section>

  <?php include 'includes/partials/index_modal_request.php'; ?>
  <?php include 'includes/partials/index_about.php'; ?>
  <?php include 'includes/partials/index_contact.php'; ?>

</div><!-- /.page-wrap -->

<button class="scroll-top" onclick="window.scrollTo({top:0,behavior:'smooth'})" title="Back to top">&#8679;</button>

<script>
// Clear form on page load
function clearAllForms() {
  document.getElementById('req-form').reset();
  document.getElementById('contact-form').reset();
  const loginForm = document.querySelector('.login-card form');
  if (loginForm) {
    loginForm.reset();
    loginForm.querySelectorAll('input').forEach(input => {
      input.value = '';
    });
  }
}

document.addEventListener('DOMContentLoaded', clearAllForms);
window.addEventListener('load', clearAllForms);

// Scroll spy
const navLinks = document.querySelectorAll('.lnav-link');
const anchors  = ['home','login','about','contact'];
function updateNav() {
  let current = 'home';
  anchors.forEach(id => { const el = document.getElementById(id); if (el && window.scrollY >= el.offsetTop - 100) current = id; });
  navLinks.forEach(l => { l.classList.remove('active'); if (l.getAttribute('href') === '#' + current) l.classList.add('active'); });
}
window.addEventListener('scroll', updateNav, { passive: true });
updateNav();

// Close modal on backdrop click — handled in modal partial


// Contact form AJAX
document.getElementById('contact-form').addEventListener('submit', function(e) {
  e.preventDefault();
  const btn = document.getElementById('contact-btn');
  const err = document.getElementById('contact-error');
  btn.textContent = 'Sending…'; btn.disabled = true; err.style.display = 'none';
  fetch('contact.php', { method: 'POST', body: new FormData(this) })
    .then(r => r.json())
    .then(res => {
      if (res.success) {
        document.getElementById('contact-form').style.display = 'none';
        document.getElementById('contact-success').style.display = 'block';
      } else {
        err.textContent = res.message || 'Something went wrong.';
        err.style.display = 'block';
        btn.textContent = 'Send Message'; btn.disabled = false;
      }
    })
    .catch(() => {
      err.textContent = 'Network error. Please try again.';
      err.style.display = 'block';
      btn.textContent = 'Send Message'; btn.disabled = false;
    });
});
</script>
</body>
</html>
