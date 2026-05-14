/* ── ARTS · main.js ───────────────────────────────────────── */

// ── Modal helpers ──────────────────────────────────────────
function openModal(id) {
  document.getElementById(id).classList.add('open');
}
function closeModal(id) {
  document.getElementById(id).classList.remove('open');
}

// Close modal when clicking the backdrop
document.querySelectorAll('.modal-overlay').forEach(function(m) {
  m.addEventListener('click', function(e) {
    if (e.target === m) m.classList.remove('open');
  });
});

// ── Flash alert auto-dismiss ───────────────────────────────
document.querySelectorAll('.alert[data-auto-dismiss]').forEach(function(el) {
  setTimeout(function() {
    el.style.opacity = '0';
    el.style.transition = 'opacity .4s';
    setTimeout(function() { el.remove(); }, 400);
  }, 3500);
});

// ── Live search/filter tables (client-side instant filter) ──
function liveFilter(inputId, tableId, colIndexes) {
  var input = document.getElementById(inputId);
  if (!input) return;
  input.addEventListener('input', function() {
    var q = this.value.toLowerCase();
    var rows = document.querySelectorAll('#' + tableId + ' tbody tr');
    rows.forEach(function(row) {
      var text = '';
      colIndexes.forEach(function(i) {
        var cell = row.cells[i];
        if (cell) text += cell.textContent.toLowerCase() + ' ';
      });
      row.style.display = text.includes(q) ? '' : 'none';
    });
  });
}

// Applicants page
liveFilter('search-applicants', 'tbl-applicants', [1, 3, 4]);
// Referrals page
liveFilter('search-referrals', 'tbl-referrals', [1, 2, 3, 4]);
// Exam results page
liveFilter('search-results', 'tbl-results', [1]);

// ── Confirm delete ─────────────────────────────────────────
document.querySelectorAll('[data-confirm]').forEach(function(el) {
  el.addEventListener('click', function(e) {
    if (!confirm(this.dataset.confirm)) e.preventDefault();
  });
});
