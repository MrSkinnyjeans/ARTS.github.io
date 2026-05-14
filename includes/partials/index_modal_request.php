<!-- ── REQUEST ACCESS MODAL ── -->
<div class="req-overlay" id="modal-request">
  <div class="req-modal">
    <button class="req-close" onclick="document.getElementById('modal-request').classList.remove('open')" title="Close">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </button>
    <div class="req-header">
      <div class="req-icon">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.8"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
      </div>
      <div>
        <h3 class="req-title">Request System Access</h3>
        <p class="req-sub">Submit your details — the Principal will review your request.</p>
      </div>
    </div>

    <!-- Success state -->
    <div id="req-success" style="display:none" class="req-success-msg">
      <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#34d399" stroke-width="1.8"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
      <p>Request Submitted!</p>
      <span id="req-success-msg">Your request has been sent to the Principal for review. You will be notified once a decision is made.</span>
    </div>

    <form id="req-form">
      <div class="req-row">
        <div class="req-group">
          <label>Full Name *</label>
          <input type="text" name="full_name" placeholder="e.g. Juan dela Cruz" required>
        </div>
        <div class="req-group">
          <label>Gmail Address * <span style="font-weight:400;color:rgba(255,255,255,.35);font-size:10px">(Gmail only)</span></label>
          <input type="email" name="email" placeholder="yourname@gmail.com"
                 pattern="[^@\s]+@gmail\.com" title="Only @gmail.com addresses are accepted" required>
        </div>
      </div>

      <!-- Google reCAPTCHA -->
      <div class="req-group">
        <div class="g-recaptcha"
             data-sitekey="6LejWuEsAAAAAMvDk9hk_PjokHdCL0n1epB2jBXd"
             data-theme="dark"></div>
      </div>

      <div id="req-error" style="display:none;font-size:13px;color:#fca5a5;margin-bottom:10px;padding:10px 14px;background:rgba(248,113,113,.1);border-radius:8px;border:1px solid rgba(248,113,113,.3)"></div>

      <button type="submit" class="req-btn" id="req-submit-btn">Submit Request</button>
    </form>
  </div>
</div>

<script>
document.getElementById('req-form').addEventListener('submit', function(e) {
  e.preventDefault();
  var btn = document.getElementById('req-submit-btn');
  var err = document.getElementById('req-error');

  // Check reCAPTCHA
  var recaptchaResponse = grecaptcha.getResponse();
  if (!recaptchaResponse) {
    err.textContent = 'Please complete the reCAPTCHA verification.';
    err.style.display = 'block';
    return;
  }

  btn.textContent = 'Submitting…'; btn.disabled = true;
  err.style.display = 'none';

  var formData = new FormData(this);
  formData.append('g-recaptcha-response', recaptchaResponse);

  fetch('request_access.php', { method: 'POST', body: formData })
    .then(function(r) { return r.json(); })
    .then(function(res) {
      if (res.success) {
        document.getElementById('req-form').style.display = 'none';
        var successEl = document.getElementById('req-success');
        var msgEl = document.getElementById('req-success-msg');
        if (res.message) msgEl.textContent = res.message;
        successEl.style.display = 'block';
        setTimeout(function() {
          document.getElementById('modal-request').classList.remove('open');
          document.getElementById('req-form').style.display = 'block';
          successEl.style.display = 'none';
          document.getElementById('req-form').reset();
          grecaptcha.reset();
          btn.textContent = 'Submit Request'; btn.disabled = false;
        }, 5000);
      } else {
        err.textContent = res.message || 'Something went wrong.';
        err.style.display = 'block';
        btn.textContent = 'Submit Request'; btn.disabled = false;
        grecaptcha.reset();
      }
    })
    .catch(function() {
      err.textContent = 'Network error. Please try again.';
      err.style.display = 'block';
      btn.textContent = 'Submit Request'; btn.disabled = false;
      grecaptcha.reset();
    });
});

document.getElementById('modal-request').addEventListener('click', function(e) {
  if (e.target === this) this.classList.remove('open');
});
</script>
