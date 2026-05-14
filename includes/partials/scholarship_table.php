<div class="card">
  <div class="card-header">
    <span class="card-title">Scholarship Candidates</span>
    <span style="font-size:12px;color:var(--text-muted)"><?= count($candidates) ?> passer<?= count($candidates) !== 1 ? 's' : '' ?></span>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>#</th><th>Student</th><th>School</th><th>Score</th>
          <th>Scholarship Status</th><th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$candidates): ?>
          <tr><td colspan="6" class="empty-state"><p>No exam passers yet.</p></td></tr>
        <?php else: foreach ($candidates as $i => $c): ?>
        <tr>
          <td style="color:var(--text-muted);font-size:13px"><?= $i+1 ?></td>
          <td>
            <strong><?= htmlspecialchars($c['fname'].' '.$c['lname']) ?></strong>
            <?php if ($c['email']): ?>
              <br><span style="font-size:12px;color:var(--text-muted)"><?= htmlspecialchars($c['email']) ?></span>
            <?php endif; ?>
          </td>
          <td style="font-size:13px"><?= htmlspecialchars($c['school_from'] ?? '—') ?></td>
          <td>
            <strong style="font-size:16px"><?= number_format($c['score'],1) ?></strong>
            <span style="font-size:12px;color:var(--text-muted)">/100</span>
          </td>
          <td><?= scholBadge($c['scholarship_status'] ?? 'Not Evaluated') ?></td>
          <td>
            <button class="btn btn-outline btn-sm"
                    onclick="openScholarshipModal(<?= htmlspecialchars(json_encode($c)) ?>)">
              Review
            </button>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
