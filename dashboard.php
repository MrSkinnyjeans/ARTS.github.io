<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/layout.php';
requireLogin();

$db = getDB();

// ── Stats ──────────────────────────────────────────────────
$total    = (int) $db->query('SELECT COUNT(*) FROM applicants')->fetchColumn();
$refs     = (int) $db->query('SELECT COUNT(*) FROM referrals')->fetchColumn();
$passed   = (int) $db->query("SELECT COUNT(*) FROM exam_results WHERE status='Passed'")->fetchColumn();
$failed   = (int) $db->query("SELECT COUNT(*) FROM exam_results WHERE status='Failed'")->fetchColumn();
$pending  = $total - $passed - $failed;

$rated    = $passed + $failed;
$passRate = $rated ? round(($passed / $rated) * 100) : 0;
$failRate = $rated ? round(($failed / $rated) * 100) : 0;

$avgScore = $db->query('SELECT AVG(score) FROM exam_results')->fetchColumn();
$avgScore = $avgScore ? number_format($avgScore, 1) : 'N/A';

// ── Referral source breakdown ──────────────────────────────
$srcRows = $db->query(
    'SELECT ref_type, COUNT(*) AS cnt FROM referrals GROUP BY ref_type ORDER BY cnt DESC'
)->fetchAll();

// ── Recent applicants (5) ──────────────────────────────────
$recent = $db->query(
    'SELECT a.id, a.fname, a.lname, a.school_from,
            r.ref_type, r.referred_by,
            er.score, er.status
     FROM applicants a
     LEFT JOIN referrals r   ON r.applicant_id = a.id
     LEFT JOIN exam_results er ON er.applicant_id = a.id
     ORDER BY a.id DESC LIMIT 5'
)->fetchAll();

startLayout('Dashboard', 'dashboard');
?>

<!-- TOPBAR -->
<div class="topbar">
  <div>
    <div class="topbar-title">Dashboard</div>
    <div class="topbar-sub">Overview of referral tracking activity</div>
  </div>
  <div class="topbar-actions">
    <a href="applicants.php?modal=add" class="btn btn-gold btn-sm">+ Add Applicant</a>
  </div>
</div>

<div class="page-content">

  <!-- STATS -->
  <div class="stat-grid">
    <div class="stat-card gold">
      <div class="stat-label">Total Applicants</div>
      <div class="stat-value"><?= $total ?></div>
      <div class="stat-sub">All registered applicants</div>
    </div>
    <div class="stat-card navy">
      <div class="stat-label">Total Referrals</div>
      <div class="stat-value"><?= $refs ?></div>
      <div class="stat-sub">Recorded referral sources</div>
    </div>
    <div class="stat-card success">
      <div class="stat-label">Passed</div>
      <div class="stat-value"><?= $passed ?></div>
      <div class="stat-sub">Entrance exam passers</div>
    </div>
    <div class="stat-card danger">
      <div class="stat-label">Failed / Pending</div>
      <div class="stat-value"><?= $failed + $pending ?></div>
      <div class="stat-sub"><?= $failed ?> failed · <?= $pending ?> no result yet</div>
    </div>
  </div>

  <!-- RATE + SOURCES -->
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:22px">

    <div class="card">
      <div class="card-header">
        <span class="card-title">Admission Rate</span>
        <span style="font-size:12px;color:var(--text-muted);background:var(--info-bg);border:1px solid var(--info-bdr);color:var(--info);padding:2px 10px;border-radius:20px;font-weight:600">Avg: <?= $avgScore ?></span>
      </div>
      <div class="card-body">
        <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px">
          <span style="color:var(--text-muted)">Pass rate</span>
          <strong style="color:var(--success)"><?= $passRate ?>%</strong>
        </div>
        <div class="progress-bar" style="height:10px">
          <div class="progress-fill success" style="width:<?= $passRate ?>%"></div>
        </div>
        <div style="display:flex;justify-content:space-between;font-size:13px;margin:14px 0 6px">
          <span style="color:var(--text-muted)">Fail rate</span>
          <strong style="color:var(--danger)"><?= $failRate ?>%</strong>
        </div>
        <div class="progress-bar" style="height:10px">
          <div class="progress-fill danger" style="width:<?= $failRate ?>%"></div>
        </div>
        <div style="display:flex;gap:16px;margin-top:16px;padding-top:14px;border-top:1px solid var(--border)">
          <div style="flex:1;text-align:center;background:var(--success-bg);border:1px solid var(--success-bdr);border-radius:8px;padding:10px 6px">
            <div style="font-size:20px;font-weight:700;color:var(--success)"><?= $passed ?></div>
            <div style="font-size:11px;color:var(--success);font-weight:600;margin-top:2px">Passed</div>
          </div>
          <div style="flex:1;text-align:center;background:var(--danger-bg);border:1px solid var(--danger-bdr);border-radius:8px;padding:10px 6px">
            <div style="font-size:20px;font-weight:700;color:var(--danger)"><?= $failed ?></div>
            <div style="font-size:11px;color:var(--danger);font-weight:600;margin-top:2px">Failed</div>
          </div>
          <div style="flex:1;text-align:center;background:var(--warning-bg);border:1px solid var(--warning-bdr);border-radius:8px;padding:10px 6px">
            <div style="font-size:20px;font-weight:700;color:var(--warning)"><?= $pending ?></div>
            <div style="font-size:11px;color:var(--warning);font-weight:600;margin-top:2px">Pending</div>
          </div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><span class="card-title">Referral Sources</span>
        <span style="font-size:12px;background:var(--teal-soft);border:1px solid #5eead4;color:var(--teal);padding:2px 10px;border-radius:20px;font-weight:600"><?= $refs ?> total</span>
      </div>
      <div class="card-body">
        <?php if (!$srcRows): ?>
          <p style="color:var(--text-muted);font-size:14px">No referrals recorded yet.</p>
        <?php else:
          $colors = ['#2563eb','#059669','#d97706','#7c3aed','#0d9488'];
          foreach ($srcRows as $i => $s):
            $pct = $refs ? round(($s['cnt'] / $refs) * 100) : 0;
            $c   = $colors[$i % count($colors)]; ?>
          <div style="margin-bottom:12px">
            <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:5px;align-items:center">
              <span style="display:flex;align-items:center;gap:7px">
                <span style="width:8px;height:8px;border-radius:50%;background:<?= $c ?>;display:inline-block;flex-shrink:0"></span>
                <?= htmlspecialchars($s['ref_type']) ?>
              </span>
              <strong style="color:<?= $c ?>"><?= $s['cnt'] ?> <span style="color:var(--text-muted);font-weight:400">(<?= $pct ?>%)</span></strong>
            </div>
            <div class="progress-bar">
              <div class="progress-fill" style="width:<?= $pct ?>%;background:<?= $c ?>"></div>
            </div>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>

  </div>

  <!-- RECENT -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">Recent Applicants</span>
      <a href="applicants.php" class="btn btn-outline btn-sm">View All</a>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Name</th><th>School From</th><th>Referred By</th>
            <th>Exam Score</th><th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$recent): ?>
            <tr><td colspan="5" class="empty-state">No applicants yet.</td></tr>
          <?php else: foreach ($recent as $row):
            $status = $row['status'] ?? 'Pending';
            $score  = $row['score']  !== null ? $row['score'] . '/100' : '—';
          ?>
          <tr>
            <td><strong><?= htmlspecialchars($row['fname'] . ' ' . $row['lname']) ?></strong></td>
            <td><?= htmlspecialchars($row['school_from'] ?? '—') ?></td>
            <td><?= htmlspecialchars($row['referred_by'] ?? $row['ref_type'] ?? '—') ?></td>
            <td><?= $score ?></td>
            <td><?= statusBadge($status) ?></td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<?php endLayout(); ?>
