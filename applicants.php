<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/layout.php';
require_once 'includes/migrate_check.php';
requireLogin();

$db      = getDB();
$msg     = '';
$msgType = 'success';

// ── Ensure full_name column exists ────────────────────────
try {
    $db->exec("ALTER TABLE applicants ADD COLUMN IF NOT EXISTS full_name VARCHAR(160) NOT NULL DEFAULT '' AFTER id");
    $db->exec("UPDATE applicants SET full_name = TRIM(CONCAT(fname, ' ', lname)) WHERE full_name = '' AND (fname != '' OR lname != '')");
} catch (PDOException $e) { /* already exists */ }

// ── Ensure soft delete column exists ──────────────────────
try {
    $db->exec("ALTER TABLE applicants ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP NULL DEFAULT NULL");
} catch (PDOException $e) { /* already exists */ }

// ── Auto-assign student IDs to existing applicants ────────
try {
    $unassigned = $db->query("SELECT id FROM applicants WHERE (student_id IS NULL OR student_id = '') AND deleted_at IS NULL ORDER BY id ASC")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($unassigned as $uid) {
        $year = date('Y');
        $count = (int)$db->query("SELECT COUNT(*) FROM applicants WHERE student_id IS NOT NULL AND student_id != ''")->fetchColumn();
        $sid = 'ARTS-' . $year . '-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
        $db->prepare("UPDATE applicants SET student_id = ? WHERE id = ?")->execute([$sid, $uid]);
    }
} catch (PDOException $e) { /* skip */ }

// ── POST actions ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $fullName = trim($_POST['full_name'] ?? '');
        $contact  = preg_replace('/\D/', '', trim($_POST['contact'] ?? ''));
        $email    = trim($_POST['email']     ?? '');
        $school   = trim($_POST['school']    ?? '');
        $date     = $_POST['date_applied']   ?? null;

        if (!$fullName) {
            $msg     = 'Full name is required.';
            $msgType = 'danger';
        } else {
            // Generate student ID: ARTS-YYYY-XXXX
            $year    = date('Y');
            $lastId  = (int)$db->query("SELECT COUNT(*) FROM applicants")->fetchColumn();
            $studentId = 'ARTS-' . $year . '-' . str_pad($lastId + 1, 4, '0', STR_PAD_LEFT);

            $db->prepare(
                'INSERT INTO applicants (student_id, full_name, fname, lname, contact, email, school_from, date_applied)
                 VALUES (?,?,?,?,?,?,?,?)'
            )->execute([$studentId, $fullName, $fullName, '', $contact ?: null, $email ?: null, $school ?: null, $date ?: null]);

            $newId = (int)$db->lastInsertId();

            // Handle grade slip upload
            if (!empty($_FILES['grade_slip']['name'])) {
                $ext  = strtolower(pathinfo($_FILES['grade_slip']['name'], PATHINFO_EXTENSION));
                $allowed = ['pdf','jpg','jpeg','png'];
                if (in_array($ext, $allowed)) {
                    $fname2 = 'gs_' . $newId . '_' . time() . '.' . $ext;
                    move_uploaded_file($_FILES['grade_slip']['tmp_name'], "uploads/grade_slips/{$fname2}");
                    $db->prepare('UPDATE applicants SET grade_slip=? WHERE id=?')->execute([$fname2, $newId]);
                }
            }
            // Handle ID photo upload
            if (!empty($_FILES['id_photo']['name'])) {
                $ext  = strtolower(pathinfo($_FILES['id_photo']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg','jpeg','png'];
                if (in_array($ext, $allowed)) {
                    $fname2 = 'id_' . $newId . '_' . time() . '.' . $ext;
                    move_uploaded_file($_FILES['id_photo']['tmp_name'], "uploads/id_photos/{$fname2}");
                    $db->prepare('UPDATE applicants SET id_photo=? WHERE id=?')->execute([$fname2, $newId]);
                }
            }

            $msg = "Applicant {$fullName} added. Student ID: {$studentId}";
        }
    }

    if ($action === 'edit') {
        $id       = (int)($_POST['id']       ?? 0);
        $fullName = trim($_POST['full_name'] ?? '');
        $contact  = preg_replace('/\D/', '', trim($_POST['contact'] ?? ''));
        $email    = trim($_POST['email']     ?? '');
        $school   = trim($_POST['school']    ?? '');
        $date     = $_POST['date_applied']   ?? null;

        $db->prepare(
            'UPDATE applicants SET full_name=?, fname=?, lname=?, contact=?, email=?, school_from=?, date_applied=? WHERE id=?'
        )->execute([$fullName, $fullName, '', $contact ?: null, $email ?: null, $school ?: null, $date ?: null, $id]);

        // Handle grade slip upload
        if (!empty($_FILES['grade_slip']['name'])) {
            $ext = strtolower(pathinfo($_FILES['grade_slip']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['pdf','jpg','jpeg','png'])) {
                $fname2 = 'gs_' . $id . '_' . time() . '.' . $ext;
                move_uploaded_file($_FILES['grade_slip']['tmp_name'], "uploads/grade_slips/{$fname2}");
                $db->prepare('UPDATE applicants SET grade_slip=? WHERE id=?')->execute([$fname2, $id]);
            }
        }
        // Handle ID photo upload
        if (!empty($_FILES['id_photo']['name'])) {
            $ext = strtolower(pathinfo($_FILES['id_photo']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png'])) {
                $fname2 = 'id_' . $id . '_' . time() . '.' . $ext;
                move_uploaded_file($_FILES['id_photo']['tmp_name'], "uploads/id_photos/{$fname2}");
                $db->prepare('UPDATE applicants SET id_photo=? WHERE id=?')->execute([$fname2, $id]);
            }
        }

        $msg = 'Applicant updated.';
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        // Soft delete — just mark deleted_at, don't remove from DB
        $db->prepare('UPDATE applicants SET deleted_at = NOW() WHERE id=?')->execute([$id]);
        $msg     = 'Applicant removed.';
        $msgType = 'danger';
    }

    if ($action === 'schedule') {
        $id            = (int)($_POST['id']             ?? 0);
        $interviewDate = $_POST['interview_date']        ?: null;
        $examSchedule  = $_POST['exam_schedule']         ?: null;
        $scheduleNotes = trim($_POST['schedule_notes']  ?? '');
        $sendEmail     = isset($_POST['send_email']);

        $db->prepare(
            'UPDATE applicants SET interview_date=?, exam_schedule=?, schedule_notes=? WHERE id=?'
        )->execute([$interviewDate, $examSchedule, $scheduleNotes ?: null, $id]);

        $msg = 'Schedule updated.';

        if ($sendEmail) {
            require_once 'includes/mailer.php';
            $ap = $db->prepare('SELECT full_name, fname, lname, email FROM applicants WHERE id=?');
            $ap->execute([$id]);
            $apRow = $ap->fetch();
            $name  = $apRow['full_name'] ?: trim(($apRow['fname'] ?? '') . ' ' . ($apRow['lname'] ?? ''));

            if ($apRow && $apRow['email']) {
                $sent    = sendScheduleEmail($apRow['email'], $name, $interviewDate, $examSchedule, $scheduleNotes);
                $msg     = $sent ? 'Schedule updated and email sent to ' . htmlspecialchars($apRow['email']) . '.'
                                 : 'Schedule updated but email could not be sent.';
                $msgType = $sent ? 'success' : 'danger';
            } else {
                $msg     = 'Schedule updated. No email on file for this applicant.';
                $msgType = 'danger';
            }
        }
    }

    header('Location: applicants.php?msg=' . urlencode($msg) . '&type=' . $msgType);
    exit;
}

if (!empty($_GET['msg'])) {
    $msg     = $_GET['msg'];
    $msgType = $_GET['type'] ?? 'success';
}

// ── Filters ────────────────────────────────────────────────
$filterStatus = $_GET['status'] ?? '';
$filterRef    = $_GET['ref']    ?? '';

$sql = 'SELECT a.*, r.ref_type, er.score, er.status AS exam_status
        FROM applicants a
        LEFT JOIN referrals r     ON r.applicant_id = a.id
        LEFT JOIN exam_results er ON er.applicant_id = a.id
        WHERE a.deleted_at IS NULL';
$params = [];

if ($filterStatus === 'Passed')  { $sql .= " AND er.status = 'Passed'"; }
if ($filterStatus === 'Failed')  { $sql .= " AND er.status = 'Failed'"; }
if ($filterStatus === 'Pending') { $sql .= ' AND er.score IS NULL'; }
if ($filterRef) { $sql .= ' AND r.ref_type = ?'; $params[] = $filterRef; }

$sql .= ' ORDER BY a.full_name ASC, a.lname ASC, a.fname ASC';
$stmt = $db->prepare($sql);
$stmt->execute($params);
$applicants = $stmt->fetchAll();

startLayout('Applicants', 'applicants');
?>

<div class="topbar">
  <div>
    <div class="topbar-title">Applicants</div>
    <div class="topbar-sub">View and manage all applicant records</div>
  </div>
  <div class="topbar-actions">
    <button class="btn btn-gold btn-sm" onclick="openModal('modal-add')">+ Add Applicant</button>
  </div>
</div>

<div class="page-content">

  <?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?>" data-auto-dismiss><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <!-- FILTER BAR -->
  <form method="GET" action="applicants.php">
    <div class="filter-bar">
      <div class="search-wrap">
        <span class="search-icon">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
          </svg>
        </span>
        <input class="search-input" type="text" id="search-applicants" placeholder="Search by name, school…">
      </div>
      <select name="status" class="filter-select" onchange="this.form.submit()">
        <option value="">All Statuses</option>
        <option value="Passed"  <?= $filterStatus==='Passed' ?'selected':'' ?>>Passed</option>
        <option value="Failed"  <?= $filterStatus==='Failed' ?'selected':'' ?>>Failed</option>
        <option value="Pending" <?= $filterStatus==='Pending'?'selected':'' ?>>Pending</option>
      </select>
      <select name="ref" class="filter-select" onchange="this.form.submit()">
        <option value="">All Referral Types</option>
        <?php foreach (['Teacher','Alumni','Partner School','Walk-in','Online'] as $t): ?>
          <option <?= $filterRef===$t?'selected':'' ?>><?= $t ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </form>

  <!-- TABLE -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">Applicant Records
        <span style="font-size:13px;font-weight:400;color:var(--text-muted)">(<?= count($applicants) ?>)</span>
      </span>
    </div>
    <div class="table-wrap">
      <table id="tbl-applicants">
        <thead>
          <tr>
            <th>Student ID</th><th>#</th><th>Full Name</th><th>Contact</th><th>School From</th>
            <th>Grade Slip</th><th>ID Photo</th>
            <th>Referral</th><th>Date Applied</th><th>Interview</th><th>Exam Schedule</th><th>Status</th><th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$applicants): ?>
            <tr><td colspan="13" class="empty-state"><p>No applicants found.</p></td></tr>
          <?php else: foreach ($applicants as $i => $ap):
            $status   = $ap['exam_status'] ?? 'Pending';
            $dispName = $ap['full_name'] ?: trim(($ap['fname'] ?? '') . ' ' . ($ap['lname'] ?? ''));
          ?>
          <tr>
            <td style="font-family:monospace;font-size:12px;color:var(--info);font-weight:600">
              <?= htmlspecialchars($ap['student_id'] ?? '—') ?>
            </td>
            <td style="color:var(--text-muted);font-size:13px"><?= $i+1 ?></td>
            <td>
              <strong><?= htmlspecialchars($dispName) ?></strong>
              <?php if ($ap['email']): ?>
                <br><span style="font-size:12px;color:var(--text-muted)"><?= htmlspecialchars($ap['email']) ?></span>
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($ap['contact'] ?? '—') ?></td>
            <td><?= htmlspecialchars($ap['school_from'] ?? '—') ?></td>
            <td style="font-size:13px">
              <?php if (!empty($ap['grade_slip'])): ?>
                <a href="uploads/grade_slips/<?= urlencode($ap['grade_slip']) ?>" target="_blank"
                   style="color:var(--info);text-decoration:underline">View</a>
              <?php else: ?><span style="color:var(--text-muted)">—</span><?php endif; ?>
            </td>
            <td style="font-size:13px">
              <?php if (!empty($ap['id_photo'])): ?>
                <a href="uploads/id_photos/<?= urlencode($ap['id_photo']) ?>" target="_blank"
                   style="color:var(--info);text-decoration:underline">View</a>
              <?php else: ?><span style="color:var(--text-muted)">—</span><?php endif; ?>
            </td>
            <td><?= $ap['ref_type'] ? refBadge($ap['ref_type']) : '<span style="color:var(--text-muted)">—</span>' ?></td>
            <td><?= $ap['date_applied'] ? date('M j, Y', strtotime($ap['date_applied'])) : '—' ?></td>
            <td style="font-size:13px">
              <?php if (!empty($ap['interview_date'])): ?>
                <span style="color:var(--info);font-weight:600"><?= date('M j, Y', strtotime($ap['interview_date'])) ?></span>
                <br><span style="font-size:11px;color:var(--text-muted)"><?= date('g:i A', strtotime($ap['interview_date'])) ?></span>
              <?php else: ?><span style="color:var(--text-muted)">—</span><?php endif; ?>
            </td>
            <td style="font-size:13px">
              <?php if (!empty($ap['exam_schedule'])): ?>
                <span style="color:var(--warning);font-weight:600"><?= date('M j, Y', strtotime($ap['exam_schedule'])) ?></span>
                <br><span style="font-size:11px;color:var(--text-muted)"><?= date('g:i A', strtotime($ap['exam_schedule'])) ?></span>
              <?php else: ?><span style="color:var(--text-muted)">—</span><?php endif; ?>
            </td>
            <td><?= statusBadge($status) ?></td>
            <td>
              <div style="display:flex;gap:6px;flex-wrap:wrap">
                <button class="btn btn-outline btn-sm"
                        onclick="openEditModal(<?= htmlspecialchars(json_encode($ap)) ?>)">Edit</button>
                <button class="btn btn-gold btn-sm"
                        onclick="openScheduleModal(<?= htmlspecialchars(json_encode($ap)) ?>)">Schedule</button>
                <div style="position:relative;display:inline-block" class="del-wrap">
                  <button type="button" class="btn btn-outline btn-sm"
                          onclick="this.nextElementSibling.style.display=this.nextElementSibling.style.display==='block'?'none':'block'"
                          title="More options">⋯</button>
                  <div style="display:none;position:absolute;right:0;top:100%;margin-top:4px;background:#fff;border:1px solid var(--border);border-radius:8px;box-shadow:var(--shadow-md);z-index:50;min-width:130px;padding:4px">
                    <form method="POST" action="applicants.php">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="id" value="<?= $ap['id'] ?>">
                      <button type="submit" class="btn btn-danger btn-sm"
                              style="width:100%;justify-content:flex-start;border-radius:6px"
                              data-confirm="Delete <?= htmlspecialchars($dispName) ?>? This also removes their referral and exam result.">
                        🗑 Delete
                      </button>
                    </form>
                  </div>
                </div>
              </div>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<!-- ── ADD MODAL ── -->
<div class="modal-overlay" id="modal-add">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Add Applicant</span>
      <button class="modal-close" onclick="closeModal('modal-add')">×</button>
    </div>
    <form method="POST" action="applicants.php" enctype="multipart/form-data">
      <input type="hidden" name="action" value="add">
      <div class="modal-body">
        <div class="form-group"><label>Full Name *</label><input name="full_name" placeholder="e.g. Juan dela Cruz" required></div>
        <div class="form-row">
          <div class="form-group"><label>Contact Number</label><input name="contact" placeholder="09XXXXXXXXX" pattern="[0-9]{7,15}" inputmode="numeric" title="Numbers only, no spaces or special characters"></div>
          <div class="form-group"><label>Email</label><input name="email" type="email" placeholder="email@domain.com"></div>
        </div>
        <div class="form-group"><label>School / Institution From</label><input name="school" placeholder="e.g. Mandaue City National HS"></div>
        <div class="form-group"><label>Date Applied</label><input name="date_applied" type="date"></div>
        <div class="form-row">
          <div class="form-group">
            <label>Grade Slip <span style="font-weight:400;color:var(--text-muted);font-size:11px">(PDF, JPG, PNG)</span></label>
            <input type="file" name="grade_slip" accept=".pdf,.jpg,.jpeg,.png" style="font-size:13px">
          </div>
          <div class="form-group">
            <label>ID Photo <span style="font-weight:400;color:var(--text-muted);font-size:11px">(holding national ID)</span></label>
            <input type="file" name="id_photo" accept=".jpg,.jpeg,.png" style="font-size:13px">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modal-add')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Applicant</button>
      </div>
    </form>
  </div>
</div>

<!-- ── EDIT MODAL ── -->
<div class="modal-overlay" id="modal-edit">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Edit Applicant</span>
      <button class="modal-close" onclick="closeModal('modal-edit')">×</button>
    </div>
    <form method="POST" action="applicants.php" enctype="multipart/form-data">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" id="edit-id">
      <div class="modal-body">
        <div class="form-group"><label>Full Name *</label><input name="full_name" id="edit-fullname" required></div>
        <div class="form-row">
          <div class="form-group"><label>Contact Number</label><input name="contact" id="edit-contact" placeholder="09XXXXXXXXX" pattern="[0-9]{7,15}" inputmode="numeric" title="Numbers only, no spaces or special characters"></div>
          <div class="form-group"><label>Email</label><input name="email" id="edit-email" type="email"></div>
        </div>
        <div class="form-group"><label>School / Institution From</label><input name="school" id="edit-school"></div>
        <div class="form-group"><label>Date Applied</label><input name="date_applied" id="edit-date" type="date"></div>
        <div class="form-row">
          <div class="form-group">
            <label>Replace Grade Slip <span style="font-weight:400;color:var(--text-muted);font-size:11px">(leave blank to keep)</span></label>
            <input type="file" name="grade_slip" accept=".pdf,.jpg,.jpeg,.png" style="font-size:13px">
          </div>
          <div class="form-group">
            <label>Replace ID Photo <span style="font-weight:400;color:var(--text-muted);font-size:11px">(leave blank to keep)</span></label>
            <input type="file" name="id_photo" accept=".jpg,.jpeg,.png" style="font-size:13px">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modal-edit')">Cancel</button>
        <button type="submit" class="btn btn-primary">Update Applicant</button>
      </div>
    </form>
  </div>
</div>

<!-- ── SCHEDULE MODAL ── -->
<div class="modal-overlay" id="modal-schedule">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Set Schedule</span>
      <button class="modal-close" onclick="closeModal('modal-schedule')">×</button>
    </div>
    <form method="POST" action="applicants.php">
      <input type="hidden" name="action" value="schedule">
      <input type="hidden" name="id" id="sched-id">
      <div class="modal-body">
        <div style="background:var(--info-bg);border:1px solid var(--border);border-radius:8px;padding:12px 16px;margin-bottom:18px;font-size:14px;color:var(--navy)">
          Setting schedule for: <strong id="sched-name"></strong>
        </div>
        <div class="form-group">
          <label>Interview Date &amp; Time</label>
          <input type="datetime-local" name="interview_date" id="sched-interview">
          <small style="color:var(--text-muted);font-size:12px">Leave blank if no interview is needed.</small>
        </div>
        <div class="form-group">
          <label>Entrance Exam Date &amp; Time</label>
          <input type="datetime-local" name="exam_schedule" id="sched-exam">
        </div>
        <div class="form-group">
          <label>Notes / Instructions</label>
          <textarea name="schedule_notes" id="sched-notes" rows="3"
                    placeholder="e.g. Bring 2 valid IDs, report to Room 201…"></textarea>
        </div>
        <div style="display:flex;align-items:center;gap:10px;padding:12px 0;border-top:1px solid var(--border);margin-top:4px">
          <input type="checkbox" name="send_email" id="sched-send-email" value="1" checked
                 style="width:16px;height:16px;cursor:pointer">
          <label for="sched-send-email" style="font-size:13px;color:var(--text);cursor:pointer;margin:0">
            Send email notification to applicant's Gmail
          </label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modal-schedule')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Schedule</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEditModal(ap) {
  document.getElementById('edit-id').value       = ap.id;
  document.getElementById('edit-fullname').value = ap.full_name || (ap.fname + ' ' + ap.lname).trim();
  document.getElementById('edit-contact').value  = ap.contact || '';
  document.getElementById('edit-email').value    = ap.email   || '';
  document.getElementById('edit-school').value   = ap.school_from || '';
  document.getElementById('edit-date').value     = ap.date_applied || '';
  openModal('modal-edit');
}

function openScheduleModal(ap) {
  document.getElementById('sched-id').value    = ap.id;
  document.getElementById('sched-name').textContent = ap.full_name || (ap.fname + ' ' + ap.lname).trim();
  document.getElementById('sched-interview').value =
    ap.interview_date ? ap.interview_date.replace(' ', 'T').slice(0,16) : '';
  document.getElementById('sched-exam').value =
    ap.exam_schedule  ? ap.exam_schedule.replace(' ', 'T').slice(0,16)  : '';
  document.getElementById('sched-notes').value = ap.schedule_notes || '';
  openModal('modal-schedule');
}

// Close delete dropdowns when clicking outside
document.addEventListener('click', function(e) {
  if (!e.target.closest('.del-wrap')) {
    document.querySelectorAll('.del-wrap div').forEach(function(d) { d.style.display = 'none'; });
  }
});
</script>

<?php endLayout(); ?>
