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
        $apId    = (int)($_POST['applicant_id'] ?? 0);
        $score   = $_POST['score']        ?? '';
        $cutoff  = $_POST['cutoff_score'] ?? 75;
        $date    = $_POST['exam_date']    ?? null;
        $remarks = trim($_POST['remarks'] ?? '');

        if (!$apId || $score === '') {
            $msg = 'Applicant and score are required.';
            $msgType = 'danger';
        } elseif (!is_numeric($score) || $score < 0 || $score > 100) {
            $msg = 'Score must be a number between 0 and 100.';
            $msgType = 'danger';
        } else {
            // One result per applicant – replace if exists
            $db->prepare('DELETE FROM exam_results WHERE applicant_id=?')->execute([$apId]);
            $computedStatus = ((int)$score >= (int)$cutoff) ? 'Passed' : 'Failed';
            $stmt = $db->prepare(
                'INSERT INTO exam_results (applicant_id, exam_date, score, cutoff_score, status, remarks)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$apId, $date ?: null, (int)$score, (int)$cutoff, $computedStatus, $remarks ?: null]);
            $msg = 'Exam result recorded successfully.';
        }
    }

    if ($action === 'edit') {
        $id      = (int)($_POST['id'] ?? 0);
        $score   = $_POST['score']        ?? '';
        $cutoff  = $_POST['cutoff_score'] ?? 75;
        $date    = $_POST['exam_date']    ?? null;
        $remarks = trim($_POST['remarks'] ?? '');

        if ($score === '' || !is_numeric($score) || $score < 0 || $score > 100) {
            $msg = 'Score must be a number between 0 and 100.';
            $msgType = 'danger';
        } else {
            $computedStatus = ((int)$score >= (int)$cutoff) ? 'Passed' : 'Failed';
            $stmt = $db->prepare(
                'UPDATE exam_results SET exam_date=?, score=?, cutoff_score=?, status=?, remarks=?
                 WHERE id=?'
            );
            $stmt->execute([$date ?: null, (int)$score, (int)$cutoff, $computedStatus, $remarks ?: null, $id]);
            $msg = 'Exam result updated.';
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $db->prepare('DELETE FROM exam_results WHERE id=?')->execute([$id]);
        $msg = 'Exam result deleted.';
        $msgType = 'danger';
    }

    header('Location: results.php?msg=' . urlencode($msg) . '&type=' . $msgType);
    exit;
}

if (!empty($_GET['msg'])) {
    $msg     = $_GET['msg'];
    $msgType = $_GET['type'] ?? 'success';
}

// ── Filters ────────────────────────────────────────────────
$filterStatus = $_GET['status'] ?? '';

$sql = 'SELECT er.*, a.fname, a.lname, a.school_from
        FROM exam_results er
        JOIN applicants a ON a.id = er.applicant_id
        WHERE 1=1';
$params = [];
if ($filterStatus) { $sql .= ' AND er.status=?'; $params[] = $filterStatus; }
$sql .= ' ORDER BY er.id DESC';

$stmt = $db->prepare($sql);
$stmt->execute($params);
$results = $stmt->fetchAll();

// Applicants without a result yet (for add dropdown)
$noResult = $db->query(
    'SELECT id, fname, lname FROM applicants
     WHERE id NOT IN (SELECT applicant_id FROM exam_results)
     ORDER BY lname, fname'
)->fetchAll();

// All applicants (for edit reference)
$allAps = $db->query('SELECT id, fname, lname FROM applicants ORDER BY lname, fname')->fetchAll();

startLayout('Exam Results', 'results');
?>

<div class="topbar">
  <div>
    <div class="topbar-title">Exam Results</div>
    <div class="topbar-sub">Record and review entrance examination scores</div>
  </div>
  <div class="topbar-actions">
    <button class="btn btn-gold btn-sm" onclick="openModal('modal-add-res')">+ Record Result</button>
  </div>
</div>

<div class="page-content">

  <?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?>" data-auto-dismiss><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <!-- FILTER BAR -->
  <form method="GET" action="results.php">
    <div class="filter-bar">
      <div class="search-wrap">
        <span class="search-icon">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
          </svg>
        </span>
        <input class="search-input" type="text" id="search-results" placeholder="Search by name…">
      </div>
      <select name="status" class="filter-select" onchange="this.form.submit()">
        <option value="">All Statuses</option>
        <option value="Passed" <?= $filterStatus==='Passed'?'selected':'' ?>>Passed</option>
        <option value="Failed" <?= $filterStatus==='Failed'?'selected':'' ?>>Failed</option>
      </select>
    </div>
  </form>

  <!-- TABLE -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">Entrance Exam Records
        <span style="font-size:13px;font-weight:400;color:var(--text-muted)">(<?= count($results) ?>)</span>
      </span>
    </div>
    <div class="table-wrap">
      <table id="tbl-results">
        <thead>
          <tr>
            <th>#</th><th>Applicant</th><th>School From</th>
            <th>Exam Date</th><th>Score</th><th>Cutoff</th>
            <th>Status</th><th>Remarks</th><th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$results): ?>
            <tr><td colspan="9" class="empty-state"><p>No exam results recorded yet.</p></td></tr>
          <?php else: foreach ($results as $i => $r):
            $pct = round(($r['score'] / 100) * 100);
            $barClass = $r['status'] === 'Passed' ? 'success' : 'danger';
          ?>
          <tr>
            <td style="color:var(--text-muted);font-size:13px"><?= $i+1 ?></td>
            <td><strong><?= htmlspecialchars($r['fname'].' '.$r['lname']) ?></strong></td>
            <td style="font-size:13px;color:var(--text-muted)"><?= htmlspecialchars($r['school_from'] ?? '—') ?></td>
            <td><?= $r['exam_date'] ? date('M j, Y', strtotime($r['exam_date'])) : '—' ?></td>
            <td>
              <div class="score-cell">
                <span class="score-val"><?= (int)$r['score'] ?><span style="font-size:12px;color:var(--text-muted)">/100</span></span>
                <div class="progress-bar" style="width:90px">
                  <div class="progress-fill <?= $barClass ?>" style="width:<?= $pct ?>%"></div>
                </div>
              </div>
            </td>
            <td><?= (int)$r['cutoff_score'] ?></td>
            <td><?= statusBadge($r['status']) ?></td>
            <td style="font-size:13px;color:var(--text-muted)"><?= htmlspecialchars($r['remarks'] ?? '—') ?></td>
            <td>
              <div style="display:flex;gap:6px">
                <button class="btn btn-outline btn-sm"
                        onclick="openEditResModal(<?= htmlspecialchars(json_encode($r)) ?>)">Edit</button>
                <form method="POST" action="results.php" style="display:inline">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= $r['id'] ?>">
                  <button type="submit" class="btn btn-danger btn-sm"
                          data-confirm="Delete this exam result?">Delete</button>
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
<div class="modal-overlay" id="modal-add-res">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Record Exam Result</span>
      <button class="modal-close" onclick="closeModal('modal-add-res')">×</button>
    </div>
    <form method="POST" action="results.php">
      <input type="hidden" name="action" value="add">
      <div class="modal-body">
        <div class="form-group">
          <label>Applicant *</label>
          <select name="applicant_id" required>
            <option value="">— Select Applicant —</option>
            <?php if (!$noResult): ?>
              <option disabled>All applicants already have results.</option>
            <?php else: foreach ($noResult as $ap): ?>
              <option value="<?= $ap['id'] ?>"><?= htmlspecialchars($ap['lname'].', '.$ap['fname']) ?></option>
            <?php endforeach; endif; ?>
          </select>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Exam Date</label><input name="exam_date" type="date"></div>
          <div class="form-group">
            <label>Score (0–100) *</label>
            <input name="score" type="number" step="1" min="0" max="100"
                   placeholder="e.g. 82" required id="add-score" oninput="previewStatus('add')">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Passing Cutoff</label>
            <input name="cutoff_score" type="number" step="1" min="0" max="100"
                   value="75" id="add-cutoff" oninput="previewStatus('add')">
          </div>
          <div class="form-group">
            <label>Preview Status</label>
            <div id="add-status-preview" style="margin-top:8px">—</div>
          </div>
        </div>
        <div class="form-group"><label>Remarks</label><input name="remarks" placeholder="e.g. Excellent, Good, Needs improvement"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modal-add-res')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Result</button>
      </div>
    </form>
  </div>
</div>

<!-- EDIT MODAL -->
<div class="modal-overlay" id="modal-edit-res">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Edit Exam Result</span>
      <button class="modal-close" onclick="closeModal('modal-edit-res')">×</button>
    </div>
    <form method="POST" action="results.php">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" id="eres-id">
      <div class="modal-body">
        <div class="form-group">
          <label>Applicant</label>
          <input id="eres-name" disabled style="background:var(--cream);color:var(--text-muted)">
        </div>
        <div class="form-row">
          <div class="form-group"><label>Exam Date</label><input name="exam_date" id="eres-date" type="date"></div>
          <div class="form-group">
            <label>Score (0–100) *</label>
            <input name="score" id="eres-score" type="number" step="1"
                   min="0" max="100" required oninput="previewStatus('edit')">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Passing Cutoff</label>
            <input name="cutoff_score" id="eres-cutoff" type="number" step="1" min="0" max="100" oninput="previewStatus('edit')">
          </div>
          <div class="form-group">
            <label>Preview Status</label>
            <div id="edit-status-preview" style="margin-top:8px">—</div>
          </div>
        </div>
        <div class="form-group"><label>Remarks</label><input name="remarks" id="eres-remarks"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modal-edit-res')">Cancel</button>
        <button type="submit" class="btn btn-primary">Update Result</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEditResModal(r) {
  document.getElementById('eres-id').value      = r.id;
  document.getElementById('eres-name').value    = r.fname + ' ' + r.lname;
  document.getElementById('eres-date').value    = r.exam_date     || '';
  document.getElementById('eres-score').value   = r.score         || '';
  document.getElementById('eres-cutoff').value  = r.cutoff_score  || 75;
  document.getElementById('eres-remarks').value = r.remarks       || '';
  previewStatus('edit');
  openModal('modal-edit-res');
}

function previewStatus(prefix) {
  var scoreEl  = document.getElementById((prefix==='add'?'add':'eres') + '-score');
  var cutoffEl = document.getElementById((prefix==='add'?'add':'eres') + '-cutoff');
  var previewEl= document.getElementById((prefix==='add'?'add':'edit') + '-status-preview');
  if (!scoreEl || !previewEl) return;
  var score  = parseFloat(scoreEl.value);
  var cutoff = parseFloat(cutoffEl ? cutoffEl.value : 75) || 75;
  if (isNaN(score)) { previewEl.innerHTML = '—'; return; }
  var passed = score >= cutoff;
  previewEl.innerHTML = passed
    ? '<span class="badge badge-success">Passed</span>'
    : '<span class="badge badge-danger">Failed</span>';
}
</script>

<?php endLayout(); ?>
