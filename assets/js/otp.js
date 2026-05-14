/* ── ARTS · OTP Verify JS ────────────────────────────────── */
(function () {
  const digits = Array.from(document.querySelectorAll('.otp-digit'));
  const hidden = document.getElementById('otp-hidden');
  const btn    = document.getElementById('otp-btn');

  function getCode() { return digits.map(d => d.value).join(''); }

  function updateBtn() {
    var code = getCode();
    btn.disabled = code.length !== 6;
    hidden.value = code;
  }

  digits.forEach(function (d, i) {
    d.addEventListener('input', function () {
      this.value = this.value.replace(/\D/g, '').slice(-1);
      this.classList.toggle('filled', this.value !== '');
      if (this.value && i < 5) digits[i + 1].focus();
      updateBtn();
    });
    d.addEventListener('keydown', function (e) {
      if (e.key === 'Backspace' && !this.value && i > 0) {
        digits[i - 1].value = '';
        digits[i - 1].classList.remove('filled');
        digits[i - 1].focus();
        updateBtn();
      }
    });
    d.addEventListener('paste', function (e) {
      e.preventDefault();
      var pasted = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '').slice(0, 6);
      pasted.split('').forEach(function (ch, j) {
        if (digits[j]) { digits[j].value = ch; digits[j].classList.add('filled'); }
      });
      updateBtn();
      if (pasted.length === 6) btn.focus();
    });
  });

  digits[0].focus();

  // Shake + clear on error (injected by PHP via data attribute)
  var errEl = document.getElementById('otp-error');
  if (errEl) {
    digits.forEach(function (d) { d.classList.add('error'); });
    setTimeout(function () {
      digits.forEach(function (d) { d.classList.remove('error'); d.value = ''; d.classList.remove('filled'); });
      digits[0].focus();
      hidden.value = '';
      btn.disabled = true;
    }, 700);
  }

  // Countdown timer
  var secsLeft  = parseInt(document.getElementById('timer-bar').dataset.secs || '300', 10);
  var totalSecs = 300;
  var bar = document.getElementById('timer-bar');
  var txt = document.getElementById('timer-text');

  function updateTimer() {
    if (secsLeft <= 0) {
      bar.style.width = '0%'; txt.textContent = '0:00'; txt.classList.add('urgent');
      btn.disabled = true; btn.textContent = 'Code Expired'; return;
    }
    var mm = Math.floor(secsLeft / 60), ss = secsLeft % 60;
    txt.textContent = mm + ':' + (ss < 10 ? '0' : '') + ss;
    bar.style.width = Math.round((secsLeft / totalSecs) * 100) + '%';
    if (secsLeft <= 60) { bar.style.background = 'linear-gradient(90deg,#ef4444,#f87171)'; txt.classList.add('urgent'); }
    secsLeft--;
  }

  updateTimer();
  var timer = setInterval(function () { updateTimer(); if (secsLeft < 0) clearInterval(timer); }, 1000);
})();
