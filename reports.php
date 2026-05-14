<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/layout.php';
requireLogin();

$db = getDB();

// ── CSV Export ─────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $rows = $db->query(
        'SELECT a.fname, a.lname, a.contact, a.email, a.school_from, a.date_applied,
                r.ref_type, r.referred_by, r.organization, r.date_referred,
                er.exam_date, er.score, er.cutoff_score, er.status, er.remarks
         FROM applicants a
         LEFT JOIN referrals r      ON r.applicant_id  = a.id
         LEFT JOIN exam_results er  ON er.applicant_id = a.id
         ORDER BY a.lname, a.fname'
    )->fetchAll();

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="ARTS_Report_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, [
        'First Name','Last Name','Contact','Email','School From','Date Applied',
        'Referral Type','Referred By','Organization','Date Referred',
        'Exam Date','Score','Cutoff','Status','Remarks'
    ]);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['fname'], $r['lname'], $r['contact'] ?? '', $r['email'] ?? '',
            $r['school_from'] ?? '', $r['date_applied'] ?? '',
            $r['ref_type'] ?? '', $r['referred_by'] ?? '', $r['organization'] ?? '',
            $r['date_referred'] ?? '', $r['exam_date'] ?? '',
            $r['score'] ?? '', $r['cutoff_score'] ?? '', $r['status'] ?? 'Pending',
            $r['remarks'] ?? ''
        ]);
    }
    fclose($out);
    exit;
}

// ── Stats ──────────────────────────────────────────────────
$total   = (int) $db->query('SELECT COUNT(*) FROM applicants')->fetchColumn();
$refs    = (int) $db->query('SELECT COUNT(*) FROM referrals')->fetchColumn();
$passed  = (int) $db->query("SELECT COUNT(*) FROM exam_results WHERE status='Passed'")->fetchColumn();
$failed  = (int) $db->query("SELECT COUNT(*) FROM exam_results WHERE status='Failed'")->fetchColumn();
$pending = $total - $passed - $failed;
$rated   = $passed + $failed;
$passRate= $rated ? round(($passed / $rated) * 100) : 0;

$avgScore = $db->query('SELECT ROUND(AVG(score),2) FROM exam_results')->fetchColumn();
$maxScore = $db->query('SELECT MAX(score) FROM exam_results')->fetchColumn();
$minScore = $db->query('SELECT MIN(score) FROM exam_results')->fetchColumn();

// ── Referral source breakdown ──────────────────────────────
$srcRows = $db->query(
    'SELECT ref_type, COUNT(*) AS cnt FROM referrals GROUP BY ref_type ORDER BY cnt DESC'
)->fetchAll();

// ── Score brackets ─────────────────────────────────────────
$brackets = [
    '0–49'   => 0, '50–59' => 0, '60–69' => 0,
    '70–74'  => 0, '75–84' => 0, '85–94' => 0, '95–100' => 0
];
$scoreRows = $db->query('SELECT score FROM exam_results')->fetchAll(PDO::FETCH_COLUMN);
foreach ($scoreRows as $s) {
    $s = (float)$s;
    if      ($s <= 49)  $brackets['0–49']++;
    elseif  ($s <= 59)  $brackets['50–59']++;
    elseif  ($s <= 69)  $brackets['60–69']++;
    elseif  ($s <= 74)  $brackets['70–74']++;
    elseif  ($s <= 84)  $brackets['75–84']++;
    elseif  ($s <= 94)  $brackets['85–94']++;
    else                $brackets['95–100']++;
}
$maxBracket = max(array_values($brackets)) ?: 1;

// ── Full report table ──────────────────────────────────────
$fullReport = $db->query(
    'SELECT a.fname, a.lname, a.school_from, a.date_applied,
            r.ref_type, r.referred_by, r.organization,
            er.score, er.cutoff_score, er.status, er.exam_date, er.remarks
     FROM applicants a
     LEFT JOIN referrals r      ON r.applicant_id  = a.id
     LEFT JOIN exam_results er  ON er.applicant_id = a.id
     ORDER BY a.lname, a.fname'
)->fetchAll();

startLayout('Reports', 'reports');
?>

<div class="topbar">
  <div>
    <div class="topbar-title">Reports &amp; Analytics</div>
    <div class="topbar-sub">Data summaries, analytics, and exportable reports</div>
  </div>
  <div class="topbar-actions">
    <a href="reports.php?export=csv" class="btn btn-gold btn-sm">⬇ Export CSV</a>
  </div>
</div>

<div class="page-content">

  <!-- STAT GRID -->
  <div class="stat-grid">
    <div class="stat-card gold">
      <div class="stat-label">Total Applicants</div>
      <div class="stat-value"><?= $total ?></div>
      <div class="stat-sub">Registered in system</div>
    </div>
    <div class="stat-card navy">
      <div class="stat-label">Pass Rate</div>
      <div class="stat-value"><?= $passRate ?>%</div>
      <div class="stat-sub"><?= $passed ?> of <?= $rated ?> tested</div>
    </div>
    <div class="stat-card success">
      <div class="stat-label">Avg. Score</div>
      <div class="stat-value"><?= $avgScore ?? '—' ?></div>
      <div class="stat-sub">Highest: <?= $maxScore ?? '—' ?> · Lowest: <?= $minScore ?? '—' ?></div>
    </div>
    <div class="stat-card danger">
      <div class="stat-label">Pending Results</div>
      <div class="stat-value"><?= $pending ?></div>
      <div class="stat-sub">Applicants not yet tested</div>
    </div>
  </div>

  <!-- SUMMARY + SOURCES -->
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:22px">

    <!-- Summary -->
    <div class="card">
      <div class="card-header"><span class="card-title">Summary Statistics</span></div>
      <div class="card-body">
        <table style="width:100%;font-size:14px">
          <?php
          $summaryRows = [
            ['Total Applicants',    $total,    ''],
            ['With Exam Results',   $rated,    ''],
            ['Passed',              $passed,   'color:var(--success);font-weight:600'],
            ['Failed',              $failed,   'color:var(--danger);font-weight:600'],
            ['Pending (no result)', $pending,  'color:var(--warning);font-weight:600'],
            ['Pass Rate',           $passRate.'%', ''],
            ['Average Score',       ($avgScore ?? 'N/A'), ''],
            ['Highest Score',       ($maxScore ?? 'N/A'), ''],
            ['Lowest Score',        ($minScore ?? 'N/A'), ''],
            ['Total Referrals',     $refs,     ''],
          ];
          foreach ($summaryRows as [$label, $val, $style]): ?>
          <tr>
            <td style="padding:8px 0;border-bottom:1px solid var(--border);color:var(--text-muted)"><?= $label ?></td>
            <td style="text-align:right;padding:8px 0;border-bottom:1px solid var(--border);font-weight:600;<?= $style ?>"><?= $val ?></td>
          </tr>
          <?php endforeach; ?>
        </table>
      </div>
    </div>

    <!-- Referral Sources -->
    <div class="card">
      <div class="card-header"><span class="card-title">Referral Source Breakdown</span></div>
      <div class="card-body">
        <?php if (!$srcRows): ?>
          <p style="color:var(--text-muted);font-size:14px">No referrals recorded yet.</p>
        <?php else: foreach ($srcRows as $s):
          $pct = $refs ? round(($s['cnt'] / $refs) * 100) : 0; ?>
          <div style="margin-bottom:14px">
            <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:5px">
              <span style="font-weight:500"><?= htmlspecialchars($s['ref_type']) ?></span>
              <span><strong><?= $s['cnt'] ?></strong> applicants &nbsp;·&nbsp; <?= $pct ?>%</span>
            </div>
            <div class="progress-bar" style="height:9px">
              <div class="progress-fill" style="width:<?= $pct ?>%"></div>
            </div>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>

  </div>

  <!-- SCORE DISTRIBUTION -->
  <div class="card" style="margin-bottom:22px">
    <div class="card-header"><span class="card-title">Score Distribution</span></div>
    <div class="card-body">
      <?php if (!$scoreRows): ?>
        <p style="color:var(--text-muted);font-size:14px">No exam results recorded yet.</p>
      <?php else: ?>
        <div style="display:flex;align-items:flex-end;gap:12px;height:130px;padding:0 4px 0">
          <?php foreach ($brackets as $range => $cnt):
            $h      = $maxBracket ? round(($cnt / $maxBracket) * 100) : 0;
            $isFail = in_array($range, ['0–49','50–59','60–69','70–74']);
            $color  = $isFail ? 'var(--danger)' : 'var(--success)';
          ?>
          <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px;height:100%;justify-content:flex-end">
            <span style="font-size:12px;font-weight:600;color:var(--text-muted)"><?= $cnt ?></span>
            <div style="width:100%;min-height:4px;height:<?= max($h, $cnt ? 4 : 0) ?>px;background:<?= $color ?>;border-radius:4px 4px 0 0;opacity:.85;transition:height .3s"></div>
            <span style="font-size:11px;color:var(--text-muted);white-space:nowrap;margin-top:4px"><?= $range ?></span>
          </div>
          <?php endforeach; ?>
        </div>
        <div style="display:flex;gap:16px;margin-top:16px;font-size:12px;color:var(--text-muted)">
          <span><span style="display:inline-block;width:10px;height:10px;background:var(--danger);border-radius:2px;margin-right:4px;vertical-align:middle"></span>Below cutoff</span>
          <span><span style="display:inline-block;width:10px;height:10px;background:var(--success);border-radius:2px;margin-right:4px;vertical-align:middle"></span>Passing range</span>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- FULL REPORT TABLE -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">Full Applicant Report</span>
      <a href="reports.php?export=csv" class="btn btn-outline btn-sm">⬇ Export CSV</a>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>#</th><th>Name</th><th>School From</th><th>Date Applied</th>
            <th>Referral Type</th><th>Referred By</th>
            <th>Exam Date</th><th>Score</th><th>Status</th><th>Remarks</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$fullReport): ?>
            <tr><td colspan="10" class="empty-state"><p>No data yet.</p></td></tr>
          <?php else: foreach ($fullReport as $i => $r):
            $status = $r['status'] ?? 'Pending';
            $score  = $r['score'] !== null ? number_format($r['score'], 1) . '/100' : '—';
          ?>
          <tr>
            <td style="color:var(--text-muted);font-size:13px"><?= $i+1 ?></td>
            <td><strong><?= htmlspecialchars($r['fname'].' '.$r['lname']) ?></strong></td>
            <td style="font-size:13px"><?= htmlspecialchars($r['school_from'] ?? '—') ?></td>
            <td style="font-size:13px"><?= $r['date_applied'] ? date('M j, Y', strtotime($r['date_applied'])) : '—' ?></td>
            <td><?= $r['ref_type'] ? refBadge($r['ref_type']) : '—' ?></td>
            <td style="font-size:13px"><?= htmlspecialchars($r['referred_by'] ?? '—') ?></td>
            <td style="font-size:13px"><?= $r['exam_date'] ? date('M j, Y', strtotime($r['exam_date'])) : '—' ?></td>
            <td style="font-weight:600"><?= $score ?></td>
            <td><?= statusBadge($status) ?></td>
            <td style="font-size:13px;color:var(--text-muted)"><?= htmlspecialchars($r['remarks'] ?? '—') ?></td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<?php endLayout(); ?>
