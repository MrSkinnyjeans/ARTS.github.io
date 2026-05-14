<?php
/**
 * principal.php
 * Exclusive dashboard for the School Principal.
 *
 * Responsibilities:
 *   - Review and approve / reject scholarship applications
 *   - Approve or reject system access requests from staff
 *
 * Access: role = 'principal' only (enforced by requireRole)
 */

require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/layout.php';

requireRole('principal');   // redirects anyone who isn't the principal

$db      = getDB();
$user    = currentUser();
$msg     = '';
$msgType = 'success';

// ── Auto-migrate: safely add new columns/tables if missing ────
// This runs silently so the page works even on the old DB schema.
require_once 'includes/migrate_check.php';

// ── Handle form submissions ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'scholarship') {
        // Update scholarship decision for a passed applicant
        $id     = (int)($_POST['result_id']       ?? 0);
        $status = $_POST['scholarship_status']     ?? '';
        $type   = trim($_POST['scholarship_type']  ?? '');
        $notes  = trim($_POST['scholarship_notes'] ?? '');

        $allowed = ['Approved', 'Rejected', 'For Review', 'Not Evaluated'];

        if ($id && in_array($status, $allowed, true)) {
            // Only stamp approved_by / approved_at when actually approving
            $approvedBy = ($status === 'Approved') ? $user['id'] : null;
            $approvedAt = ($status === 'Approved') ? date('Y-m-d H:i:s') : null;

            $db->prepare(
                'UPDATE exam_results
                 SET scholarship_status = ?,
                     scholarship_type   = ?,
                     scholarship_notes  = ?,
                     approved_by        = ?,
                     approved_at        = ?
                 WHERE id = ?'
            )->execute([$status, $type ?: null, $notes ?: null, $approvedBy, $approvedAt, $id]);

            $msg = 'Scholarship status updated.';
        }
    }

    if ($action === 'review_request') {
        // Simple reject — no account created
        $id     = (int)($_POST['request_id'] ?? 0);
        $status = $_POST['req_status']       ?? '';

        if ($id && $status === 'rejected') {
            $db->prepare(
                'UPDATE access_requests
                 SET status = "rejected", reviewed_by = ?, reviewed_at = NOW()
                 WHERE id = ?'
            )->execute([$user['id'], $id]);

            $msg     = 'Access request rejected.';
            $msgType = 'danger';
        }
    }

    if ($action === 'delete_request') {
        $id = (int)($_POST['request_id'] ?? 0);
        if ($id) {
            $db->prepare('DELETE FROM access_requests WHERE id = ? AND status = "rejected"')
               ->execute([$id]);
            $msg     = 'Rejected request deleted.';
            $msgType = 'danger';
        }
    }

    if ($action === 'edit_account') {
        $id            = (int)($_POST['user_id']       ?? 0);
        $fullName      = trim($_POST['full_name']      ?? '');
        $username      = trim($_POST['username']       ?? '');
        $approvedGmail = strtolower(trim($_POST['approved_gmail'] ?? ''));
        $newPassword   = trim($_POST['new_password']   ?? '');

        if ($id && $fullName && $username && $approvedGmail) {
            // Check username not taken by someone else
            $check = $db->prepare('SELECT id FROM users WHERE username = ? AND id != ?');
            $check->execute([$username, $id]);
            if ($check->fetch()) {
                $msg     = 'Username "' . htmlspecialchars($username) . '" is already taken.';
                $msgType = 'danger';
            } else {
                if ($newPassword) {
                    $db->prepare('UPDATE users SET full_name=?, username=?, approved_gmail=?, password=? WHERE id=?')
                       ->execute([$fullName, $username, $approvedGmail, password_hash($newPassword, PASSWORD_BCRYPT), $id]);
                } else {
                    $db->prepare('UPDATE users SET full_name=?, username=?, approved_gmail=? WHERE id=?')
                       ->execute([$fullName, $username, $approvedGmail, $id]);
                }
                // Keep access_requests in sync
                $db->prepare('UPDATE access_requests SET username=? WHERE granted_user_id=?')
                   ->execute([$username, $id]);
                $msg = 'Account updated for ' . htmlspecialchars($fullName) . '.';
            }
        } else {
            $msg     = 'Please fill in all required fields.';
            $msgType = 'danger';
        }
    }

    if ($action === 'revoke_access') {        $id = (int)($_POST['user_id'] ?? 0);
        if ($id) {
            $target = $db->prepare('SELECT role, full_name, username FROM users WHERE id = ?');
            $target->execute([$id]);
            $targetUser = $target->fetch();

            if ($targetUser && $targetUser['role'] !== 'principal') {
                // Remove their approved access request entry too
                $db->prepare(
                    'DELETE FROM access_requests WHERE username = ? AND status = "approved"'
                )->execute([$targetUser['username']]);

                $db->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
                $msg     = 'Access revoked for ' . htmlspecialchars($targetUser['full_name']) . '.';
                $msgType = 'danger';
            } else {
                $msg     = 'Cannot revoke access for this account.';
                $msgType = 'danger';
            }
        }
    }
    if ($action === 'grant_access') {
        $id            = (int)($_POST['request_id']  ?? 0);
        $username      = trim($_POST['new_username'] ?? '');
        $fullName      = trim($_POST['req_full_name'] ?? '');
        $approvedGmail = strtolower(trim($_POST['approved_gmail'] ?? ''));

        if (!preg_match('/^[^@\s]+@gmail\.com$/', $approvedGmail)) {
            $msg     = 'Please enter a valid @gmail.com address.';
            $msgType = 'danger';
        } elseif (!$id || !$username) {
            $msg     = 'Please fill in all fields.';
            $msgType = 'danger';
        } else {
            $exists = $db->prepare('SELECT id FROM users WHERE username = ?');
            $exists->execute([$username]);
            if ($exists->fetch()) {
                $msg     = 'Username "' . htmlspecialchars($username) . '" is already taken.';
                $msgType = 'danger';
            } else {
                $gmailTaken = $db->prepare('SELECT id FROM users WHERE approved_gmail = ?');
                $gmailTaken->execute([$approvedGmail]);
                if ($gmailTaken->fetch()) {
                    $msg     = 'That Gmail is already assigned to another account.';
                    $msgType = 'danger';
                } else {
                    // Generate a secure token — account has no usable password yet
                    $token   = bin2hex(random_bytes(32));
                    $expires = date('Y-m-d H:i:s', time() + 86400); // 24 hours

                    $db->prepare(
                        'INSERT INTO users (username, password, full_name, approved_gmail, role, password_reset_token, token_expires)
                         VALUES (?, ?, ?, ?, "admin", ?, ?)'
                    )->execute([$username, password_hash($token, PASSWORD_BCRYPT), $fullName, $approvedGmail, $token, $expires]);

                    $newUserId = (int) $db->lastInsertId();

                    $db->prepare(
                        'UPDATE access_requests
                         SET status="approved", granted_user_id=?, username=?, reviewed_by=?, reviewed_at=NOW()
                         WHERE id=?'
                    )->execute([$newUserId, $username, $user['id'], $id]);

                    // Send set-password email
                    require_once 'includes/mailer.php';
                    $sent = sendSetPasswordEmail($approvedGmail, $fullName, $token);

                    $msg = $sent
                        ? "Account created for {$username}. A set-password email was sent to {$approvedGmail}."
                        : "Account created for {$username}. Email could not be sent — share the setup link manually.";
                    $msgType = 'success';
                }
            }
        }
    }
    // Redirect to avoid form re-submission on refresh
    header('Location: principal.php?msg=' . urlencode($msg) . '&type=' . $msgType);
    exit;
}

// Flash message from redirect
if (!empty($_GET['msg'])) {
    $msg     = $_GET['msg'];
    $msgType = $_GET['type'] ?? 'success';
}

// ── Fetch stats ───────────────────────────────────────────────
$passed      = (int) $db->query("SELECT COUNT(*) FROM exam_results WHERE status = 'Passed'")->fetchColumn();
$forReview   = (int) $db->query("SELECT COUNT(*) FROM exam_results WHERE scholarship_status = 'For Review'")->fetchColumn();
$approved    = (int) $db->query("SELECT COUNT(*) FROM exam_results WHERE scholarship_status = 'Approved'")->fetchColumn();
$rejected    = (int) $db->query("SELECT COUNT(*) FROM exam_results WHERE scholarship_status = 'Rejected'")->fetchColumn();
$pendingReqs = (int) $db->query("SELECT COUNT(*) FROM access_requests WHERE status = 'pending'")->fetchColumn();
$adminCount  = (int) $db->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();

// ── Fetch scholarship candidates (all exam passers) ───────────
$candidates = $db->query(
    "SELECT er.id AS result_id,
            er.score, er.exam_date,
            er.scholarship_status, er.scholarship_type,
            er.scholarship_notes, er.approved_at,
            a.fname, a.lname, a.school_from, a.email,
            r.ref_type,
            u.full_name AS approved_by_name
     FROM exam_results er
     JOIN applicants a  ON a.id = er.applicant_id
     LEFT JOIN referrals r ON r.applicant_id = a.id
     LEFT JOIN users u     ON u.id = er.approved_by
     WHERE er.status = 'Passed'
     ORDER BY er.score DESC"
)->fetchAll();

// ── Fetch access requests (pending first, then reviewed) ──────
$accessRequests = $db->query(
    "SELECT ar.*, u.full_name AS reviewer_name
     FROM access_requests ar
     LEFT JOIN users u ON u.id = ar.reviewed_by
     ORDER BY FIELD(ar.status, 'pending', 'approved', 'rejected'), ar.created_at DESC"
)->fetchAll();

// ── Fetch last 5 approved scholarships for the sidebar ────────
$recentApprovals = $db->query(
    "SELECT er.score, er.scholarship_type, er.approved_at,
            a.fname, a.lname, a.school_from
     FROM exam_results er
     JOIN applicants a ON a.id = er.applicant_id
     WHERE er.scholarship_status = 'Approved'
     ORDER BY er.approved_at DESC
     LIMIT 5"
)->fetchAll();

// ── Fetch all registered admin/staff accounts ─────────────────
$adminAccounts = $db->query(
    "SELECT id, username, full_name, approved_gmail, role, created_at
     FROM users WHERE role = 'admin' ORDER BY created_at DESC"
)->fetchAll();

// ── Start page layout ─────────────────────────────────────────
startLayout('Principal Dashboard', 'principal');
?>

<!-- ══ TOPBAR ═══════════════════════════════════════════════ -->
<div class="topbar">
  <div>
    <div class="topbar-title">Principal Dashboard</div>
    <div class="topbar-sub">Scholarship approvals &amp; system access management</div>
  </div>
  <div class="topbar-actions">
    <?php if ($pendingReqs > 0): ?>
      <!-- Pulsing alert badge when there are pending access requests -->
      <a href="#access-requests" class="btn btn-sm"
         style="border:1.5px solid #fcd34d;color:#d97706;background:#fef3c7;
                animation:pulse-badge 2s ease-in-out infinite">
        🔔 <?= $pendingReqs ?> New Access Request<?= $pendingReqs > 1 ? 's' : '' ?>
      </a>
    <?php endif; ?>
    <a href="#admin-accounts" class="btn btn-outline btn-sm">
      👥 <?= $adminCount ?> Admin Account<?= $adminCount !== 1 ? 's' : '' ?>
    </a>
  </div>
</div>

<div class="page-content">

  <?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?>" data-auto-dismiss>
      <?= htmlspecialchars($msg) ?>
    </div>
  <?php endif; ?>

  <!-- ── Welcome banner ── -->
  <div class="principal-banner">
    <div class="principal-banner-text">
      <div class="principal-banner-eyebrow">Principal's Office</div>
      <h2>Welcome, <?= htmlspecialchars($user['full_name']) ?></h2>
      <p>You have exclusive authority to approve or reject scholarship applications
         for qualified entrance exam passers. Review each candidate carefully.</p>
    </div>
    <div class="principal-banner-icon" aria-hidden="true">
      <svg width="64" height="64" viewBox="0 0 24 24" fill="none"
           stroke="rgba(255,255,255,.25)" stroke-width="1">
        <path d="M22 10v6M2 10l10-5 10 5-10 5z"/>
        <path d="M6 12v5c3 3 9 3 12 0v-5"/>
      </svg>
    </div>
  </div>

  <!-- ── Stats row ── -->
  <div class="stat-grid" style="grid-template-columns:repeat(6,1fr)">
    <div class="stat-card navy">
      <div class="stat-label">Total Passers</div>
      <div class="stat-value"><?= $passed ?></div>
      <div class="stat-sub">Qualified for scholarship</div>
    </div>
    <div class="stat-card gold">
      <div class="stat-label">For Review</div>
      <div class="stat-value"><?= $forReview ?></div>
      <div class="stat-sub">Awaiting your decision</div>
    </div>
    <div class="stat-card success">
      <div class="stat-label">Approved</div>
      <div class="stat-value"><?= $approved ?></div>
      <div class="stat-sub">Scholarships granted</div>
    </div>
    <div class="stat-card danger">
      <div class="stat-label">Rejected</div>
      <div class="stat-value"><?= $rejected ?></div>
      <div class="stat-sub">Not granted</div>
    </div>
    <div class="stat-card purple">
      <div class="stat-label">Access Requests</div>
      <div class="stat-value"><?= $pendingReqs ?></div>
      <div class="stat-sub">Pending review</div>
    </div>
    <div class="stat-card" style="background:linear-gradient(135deg,#f0fdfa,#ccfbf1);border-color:#5eead4">
      <div class="stat-label" style="color:#0f766e">Admin Accounts</div>
      <div class="stat-value" style="color:#134e4a"><?= $adminCount ?></div>
      <div class="stat-sub" style="color:#0d9488">Registered admins</div>
      <div style="position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,#0d9488,#2dd4bf);border-radius:14px 14px 0 0"></div>
    </div>
  </div>

  <!-- ── Two-column: candidates table + recent approvals ── -->
  <div style="display:grid;grid-template-columns:1fr 320px;gap:20px;margin-bottom:22px;align-items:start">

    <!-- Left: scholarship candidates table -->
    <?php include 'includes/partials/scholarship_table.php'; ?>

    <!-- Right: recent approvals sidebar -->
    <?php include 'includes/partials/recent_approvals.php'; ?>

  </div>

  <!-- ── Access requests table ── -->
  <?php include 'includes/partials/access_requests_table.php'; ?>

  <!-- ── Registered admin accounts ── -->
  <?php include 'includes/partials/admin_accounts_table.php'; ?>

</div><!-- /.page-content -->

<!-- ── Scholarship review modal ── -->
<?php include 'includes/partials/modal_scholarship.php'; ?>

<!-- ── Grant access modal ── -->
<?php include 'includes/partials/modal_grant_access.php'; ?>

<!-- ── Page-specific JS ── -->
<script>
// Populate and open the scholarship review modal
function openScholarshipModal(c) {
  document.getElementById('sch-result-id').value   = c.result_id;
  document.getElementById('sch-name').textContent   = c.fname + ' ' + c.lname;
  document.getElementById('sch-school').textContent = c.school_from || '—';
  document.getElementById('sch-score').textContent  = parseFloat(c.score).toFixed(1) + '/100';
  document.getElementById('sch-ref').textContent    = c.ref_type || '—';
  document.getElementById('sch-status').value       = c.scholarship_status || 'Not Evaluated';
  document.getElementById('sch-type').value         = c.scholarship_type   || '';
  document.getElementById('sch-notes').value        = c.scholarship_notes  || '';
  openModal('modal-scholarship');
}

// Populate and open the grant access modal
function openGrantModal(req) {
  document.getElementById('grant-request-id').value  = req.id;
  document.getElementById('grant-full-name').value   = req.full_name;
  document.getElementById('grant-email').textContent = req.email;

  // Pre-fill username suggestion from full name (lowercase, no spaces)
  var suggested = req.full_name.toLowerCase().replace(/\s+/g, '.').replace(/[^a-z0-9.]/g, '');
  document.getElementById('grant-username').value = suggested;

  // Pre-fill Gmail from the request email
  var reqEmail = req.email || '';
  document.getElementById('grant-approved-gmail').value = /gmail\.com$/i.test(reqEmail) ? reqEmail : '';

  openModal('modal-grant-access');
}
</script>

<?php endLayout(); ?>
