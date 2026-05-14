<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/layout.php';
requireLogin();

$db      = getDB();
$msg     = '';
$msgType = 'success';

// ── POST actions ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $apId = (int)($_POST['applicant_id'] ?? 0);
        $type = $_POST['ref_type']    ?? '';
        $by   = trim($_POST['referred_by']  ?? '');
        if ($by === 'others') $by = trim($_POST['referred_by_other'] ?? '');
        $org  = trim($_POST['organization'] ?? '');
        if ($org === 'others') $org = trim($_POST['organization_other'] ?? '');
        $date = $_POST['date_referred'] ?? null;
        $notes = trim($_POST['notes']   ?? '');

        if (!$apId || !$type) {
            $msg = 'Applicant and referral type are required.';
            $msgType = 'danger';
        } else {
            // One referral per applicant – replace if exists
            $db->prepare('DELETE FROM referrals WHERE applicant_id=?')->execute([$apId]);
            $stmt = $db->prepare(
                'INSERT INTO referrals (applicant_id,ref_type,referred_by,organization,date_referred,notes)
                 VALUES (?,?,?,?,?,?)'
            );
            $stmt->execute([$apId, $type, $by ?: null, $org ?: null, $date ?: null, $notes ?: null]);
            $msg = 'Referral recorded successfully.';
        }
    }

    if ($action === 'edit') {
        $id   = (int)($_POST['id'] ?? 0);
        $type = $_POST['ref_type']    ?? '';
        $by   = trim($_POST['referred_by']  ?? '');
        if ($by === 'others') $by = trim($_POST['referred_by_other'] ?? '');
        $org  = trim($_POST['organization'] ?? '');
        if ($org === 'others') $org = trim($_POST['organization_other'] ?? '');
        $date = $_POST['date_referred'] ?? null;
        $notes= trim($_POST['notes']    ?? '');

        $stmt = $db->prepare(
            'UPDATE referrals SET ref_type=?,referred_by=?,organization=?,date_referred=?,notes=?
             WHERE id=?'
        );
        $stmt->execute([$type, $by ?: null, $org ?: null, $date ?: null, $notes ?: null, $id]);
        $msg = 'Referral updated.';
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $db->prepare('DELETE FROM referrals WHERE id=?')->execute([$id]);
        $msg = 'Referral deleted.';
        $msgType = 'danger';
    }

    header('Location: referrals.php?msg=' . urlencode($msg) . '&type=' . $msgType);
    exit;
}

if (!empty($_GET['msg'])) {
    $msg     = $_GET['msg'];
    $msgType = $_GET['type'] ?? 'success';
}

// ── Data ───────────────────────────────────────────────────
$filterType = $_GET['ref_type'] ?? '';

$sql = 'SELECT r.*, a.fname, a.lname
        FROM referrals r
        JOIN applicants a ON a.id = r.applicant_id
        WHERE 1=1';
$params = [];
if ($filterType) { $sql .= ' AND r.ref_type=?'; $params[] = $filterType; }
$sql .= ' ORDER BY r.id DESC';

$stmt = $db->prepare($sql);
$stmt->execute($params);
$referrals = $stmt->fetchAll();

// All applicants for the add dropdown
$allAps = $db->query('SELECT id, fname, lname FROM applicants ORDER BY lname, fname')->fetchAll();

startLayout('Referrals', 'referrals');
?>

<div class="topbar">
  <div>
    <div class="topbar-title">Referrals</div>
    <div class="topbar-sub">Track all referral sources and contacts</div>
  </div>
  <div class="topbar-actions">
    <button class="btn btn-gold btn-sm" onclick="openModal('modal-add-ref')">+ Add Referral</button>
  </div>
</div>

<div class="page-content">

  <?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?>" data-auto-dismiss><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <!-- FILTER -->
  <form method="GET" action="referrals.php">
    <div class="filter-bar">
      <div class="search-wrap">
        <span class="search-icon">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
          </svg>
        </span>
        <input class="search-input" type="text" id="search-referrals"
               placeholder="Search by name, referred by…">
      </div>
      <select name="ref_type" class="filter-select" onchange="this.form.submit()">
        <option value="">All Types</option>
        <?php foreach (['Teacher','Alumni','Partner School','Walk-in','Online'] as $t): ?>
          <option <?= $filterType===$t?'selected':'' ?>><?= $t ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </form>

  <div class="card">
    <div class="card-header">
      <span class="card-title">Referral Records
        <span style="font-size:13px;font-weight:400;color:var(--text-muted)">(<?= count($referrals) ?>)</span>
      </span>
    </div>
    <div class="table-wrap">
      <table id="tbl-referrals">
        <thead>
          <tr><th>#</th><th>Applicant</th><th>Type</th><th>Referred By</th>
              <th>School/Org</th><th>Date Referred</th><th>Notes</th><th>Actions</th></tr>
        </thead>
        <tbody>
          <?php if (!$referrals): ?>
            <tr><td colspan="8" class="empty-state"><p>No referrals found.</p></td></tr>
          <?php else: foreach ($referrals as $i => $r): ?>
          <tr>
            <td style="color:var(--text-muted);font-size:13px"><?= $i+1 ?></td>
            <td><strong><?= htmlspecialchars($r['fname'].' '.$r['lname']) ?></strong></td>
            <td><?= refBadge($r['ref_type']) ?></td>
            <td><?= htmlspecialchars($r['referred_by'] ?? '—') ?></td>
            <td><?= htmlspecialchars($r['organization'] ?? '—') ?></td>
            <td><?= $r['date_referred'] ? date('M j, Y', strtotime($r['date_referred'])) : '—' ?></td>
            <td style="max-width:160px;font-size:13px;color:var(--text-muted)">
              <?= htmlspecialchars($r['notes'] ?? '—') ?>
            </td>
            <td>
              <div style="display:flex;gap:6px">
                <button class="btn btn-outline btn-sm"
                        onclick="openEditRefModal(<?= htmlspecialchars(json_encode($r)) ?>)">Edit</button>
                <form method="POST" action="referrals.php" style="display:inline">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= $r['id'] ?>">
                  <button type="submit" class="btn btn-danger btn-sm"
                          data-confirm="Delete this referral record?">Delete</button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<!-- ADD MODAL -->
<div class="modal-overlay" id="modal-add-ref">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Add Referral</span>
      <button class="modal-close" onclick="closeModal('modal-add-ref')">×</button>
    </div>
    <form method="POST" action="referrals.php">
      <input type="hidden" name="action" value="add">
      <div class="modal-body">
        <div class="form-group">
          <label>Applicant *</label>
          <select name="applicant_id" required>
            <option value="">— Select Applicant —</option>
            <?php foreach ($allAps as $ap): ?>
              <option value="<?= $ap['id'] ?>"><?= htmlspecialchars($ap['lname'].', '.$ap['fname']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Referral Type *</label>
            <select name="ref_type" required>
              <option value="">— Select —</option>
              <?php foreach (['Teacher','Alumni','Partner School','Walk-in','Online'] as $t): ?>
                <option><?= $t ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label>Date Referred</label><input name="date_referred" type="date"></div>
        </div>
        <div class="form-group"><label>Referred By (Name)</label>
          <select name="referred_by" id="add-ref-by-select" onchange="toggleRefByOther('add')">
            <option value="">— Select —</option>
          </select>
          <input type="text" name="referred_by_other" id="add-ref-by-other"
                 placeholder="Enter name…" style="display:none;margin-top:8px">
        </div>
        <div class="form-group">
          <label>School / Organization</label>
          <select name="organization" id="add-org-select" onchange="toggleOrgOther('add')">
            <option value="">— Select —</option>
            <optgroup label="Government Scholarships">
              <option>CHED (Commission on Higher Education)</option>
              <option>DOST-SEI (Science Education Institute)</option>
              <option>TESDA</option>
              <option>DepEd</option>
              <option>DSWD (Pantawid Pamilya)</option>
              <option>GSIS Educational Assistance</option>
              <option>SSS Educational Assistance</option>
              <option>AFP Educational Benefit System</option>
              <option>PNP Educational Assistance</option>
            </optgroup>
            <optgroup label="LGU / Local Scholarships">
              <option>City Government Scholarship</option>
              <option>Provincial Government Scholarship</option>
              <option>Barangay Scholarship</option>
            </optgroup>
            <optgroup label="Private / Corporate">
              <option>SM Foundation</option>
              <option>Ayala Foundation</option>
              <option>Jollibee Foundation</option>
              <option>PLDT-Smart Foundation</option>
              <option>Globe Bridgecom</option>
              <option>BDO Foundation</option>
              <option>Metrobank Foundation</option>
              <option>Gokongwei Brothers Foundation</option>
            </optgroup>
            <optgroup label="School-Based">
              <option>Partner School</option>
              <option>Alumni Association</option>
            </optgroup>
            <option value="others">Others (specify below)</option>
          </select>
          <input type="text" name="organization_other" id="add-org-other"
                 placeholder="Specify school or organization…"
                 style="display:none;margin-top:8px">
        </div>
        <div class="form-group"><label>Notes</label><textarea name="notes" rows="2"></textarea></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modal-add-ref')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Referral</button>
      </div>
    </form>
  </div>
</div>

<!-- EDIT MODAL -->
<div class="modal-overlay" id="modal-edit-ref">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Edit Referral</span>
      <button class="modal-close" onclick="closeModal('modal-edit-ref')">×</button>
    </div>
    <form method="POST" action="referrals.php">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" id="eref-id">
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group">
            <label>Referral Type *</label>
            <select name="ref_type" id="eref-type" required>
              <?php foreach (['Teacher','Alumni','Partner School','Walk-in','Online'] as $t): ?>
                <option><?= $t ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label>Date Referred</label><input name="date_referred" id="eref-date" type="date"></div>
        </div>
        <div class="form-group"><label>Referred By</label>
          <select name="referred_by" id="edit-ref-by-select" onchange="toggleRefByOther('edit')">
            <option value="">— Select —</option>
          </select>
          <input type="text" name="referred_by_other" id="edit-ref-by-other"
                 placeholder="Enter name…" style="display:none;margin-top:8px">
        </div>
        <div class="form-group">
          <label>School / Organization</label>
          <select name="organization" id="edit-org-select" onchange="toggleOrgOther('edit')">
            <option value="">— Select —</option>
            <optgroup label="Government Scholarships">
              <option>CHED (Commission on Higher Education)</option>
              <option>DOST-SEI (Science Education Institute)</option>
              <option>TESDA</option>
              <option>DepEd</option>
              <option>DSWD (Pantawid Pamilya)</option>
              <option>GSIS Educational Assistance</option>
              <option>SSS Educational Assistance</option>
              <option>AFP Educational Benefit System</option>
              <option>PNP Educational Assistance</option>
            </optgroup>
            <optgroup label="LGU / Local Scholarships">
              <option>City Government Scholarship</option>
              <option>Provincial Government Scholarship</option>
              <option>Barangay Scholarship</option>
            </optgroup>
            <optgroup label="Private / Corporate">
              <option>SM Foundation</option>
              <option>Ayala Foundation</option>
              <option>Jollibee Foundation</option>
              <option>PLDT-Smart Foundation</option>
              <option>Globe Bridgecom</option>
              <option>BDO Foundation</option>
              <option>Metrobank Foundation</option>
              <option>Gokongwei Brothers Foundation</option>
            </optgroup>
            <optgroup label="School-Based">
              <option>Partner School</option>
              <option>Alumni Association</option>
            </optgroup>
            <option value="others">Others (specify below)</option>
          </select>
          <input type="text" name="organization_other" id="edit-org-other"
                 placeholder="Specify school or organization…"
                 style="display:none;margin-top:8px">
        </div>
        <div class="form-group"><label>Notes</label><textarea name="notes" id="eref-notes" rows="2"></textarea></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modal-edit-ref')">Cancel</button>
        <button type="submit" class="btn btn-primary">Update Referral</button>
      </div>
    </form>
  </div>
</div>

<script>
var knownOrgs = [
  'CHED (Commission on Higher Education)','DOST-SEI (Science Education Institute)',
  'TESDA','DepEd','DSWD (Pantawid Pamilya)','GSIS Educational Assistance',
  'SSS Educational Assistance','AFP Educational Benefit System','PNP Educational Assistance',
  'City Government Scholarship','Provincial Government Scholarship','Barangay Scholarship',
  'SM Foundation','Ayala Foundation','Jollibee Foundation','PLDT-Smart Foundation',
  'Globe Bridgecom','BDO Foundation','Metrobank Foundation','Gokongwei Brothers Foundation',
  'Partner School','Alumni Association'
];

// Options per referral type
var refByOptions = {
  'Teacher':        ['Class Adviser','Subject Teacher','Guidance Counselor','Department Head','Principal','Others (specify)'],
  'Alumni':         ['Batch Representative','Alumni Association Officer','Former Student','Others (specify)'],
  'Partner School': ['School Principal','Registrar','Guidance Office','Department Coordinator','Others (specify)'],
  'Walk-in':        ['Self','Parent / Guardian','Sibling','Relative','Friend','Others (specify)'],
  'Online':         ['Facebook Ad','School Website','Instagram','YouTube','Flyer / Poster','Others (specify)']
};

function populateRefBy(prefix, type, currentVal) {
  var sel   = document.getElementById(prefix === 'add' ? 'add-ref-by-select' : 'edit-ref-by-select');
  var other = document.getElementById(prefix === 'add' ? 'add-ref-by-other'  : 'edit-ref-by-other');
  if (!sel) return;

  var opts = refByOptions[type] || ['Others (specify)'];
  sel.innerHTML = '<option value="">— Select —</option>';
  opts.forEach(function(o) {
    var val = o === 'Others (specify)' ? 'others' : o;
    var opt = document.createElement('option');
    opt.value = val; opt.textContent = o;
    sel.appendChild(opt);
  });

  // Pre-select if editing
  if (currentVal) {
    var found = false;
    for (var i = 0; i < sel.options.length; i++) {
      if (sel.options[i].value === currentVal) { sel.value = currentVal; found = true; break; }
    }
    if (!found) {
      sel.value = 'others';
      if (other) { other.style.display = 'block'; other.value = currentVal; }
    }
  }
  toggleRefByOther(prefix);
}

function toggleRefByOther(prefix) {
  var sel   = document.getElementById(prefix === 'add' ? 'add-ref-by-select' : 'edit-ref-by-select');
  var other = document.getElementById(prefix === 'add' ? 'add-ref-by-other'  : 'edit-ref-by-other');
  if (!sel || !other) return;
  other.style.display = sel.value === 'others' ? 'block' : 'none';
  other.required = sel.value === 'others';
}

function toggleOrgOther(prefix) {
  var sel   = document.getElementById(prefix === 'add' ? 'add-org-select' : 'edit-org-select');
  var other = document.getElementById(prefix === 'add' ? 'add-org-other'  : 'edit-org-other');
  if (!sel || !other) return;
  other.style.display = sel.value === 'others' ? 'block' : 'none';
  other.required = sel.value === 'others';
}

// Wire up add modal type change
var addTypeEl = document.querySelector('#modal-add-ref select[name="ref_type"]');
if (addTypeEl) {
  addTypeEl.addEventListener('change', function() {
    populateRefBy('add', this.value, '');
  });
  populateRefBy('add', addTypeEl.value, '');
}

function openEditRefModal(r) {
  document.getElementById('eref-id').value    = r.id;
  document.getElementById('eref-type').value  = r.ref_type;
  document.getElementById('eref-date').value  = r.date_referred || '';
  document.getElementById('eref-notes').value = r.notes || '';

  // Populate referred-by options then pre-select
  populateRefBy('edit', r.ref_type, r.referred_by || '');

  // Pre-select org dropdown
  var org   = r.organization || '';
  var sel   = document.getElementById('edit-org-select');
  var other = document.getElementById('edit-org-other');
  if (knownOrgs.indexOf(org) !== -1) {
    sel.value = org; other.style.display = 'none';
  } else if (org) {
    sel.value = 'others'; other.style.display = 'block'; other.value = org;
  } else {
    sel.value = ''; other.style.display = 'none';
  }

  // Update ref-by options when type changes in edit modal
  document.getElementById('eref-type').onchange = function() {
    populateRefBy('edit', this.value, '');
  };

  openModal('modal-edit-ref');
}
</script>

<?php endLayout(); ?>
