<div class="card">
  <div class="card-header"><span class="card-title">Recent Approvals</span></div>
  <div class="card-body" style="padding:0">
    <?php if (!$recentApprovals): ?>
      <p style="padding:18px;font-size:13px;color:var(--text-muted)">No scholarships approved yet.</p>
    <?php else: foreach ($recentApprovals as $ra): ?>
      <div style="padding:14px 18px;border-bottom:1px solid var(--border)">
        <div style="font-weight:600;font-size:13px;color:var(--navy)">
          <?= htmlspecialchars($ra['fname'].' '.$ra['lname']) ?>
        </div>
        <?php if ($ra['scholarship_type']): ?>
          <div style="font-size:12px;color:var(--success);font-weight:600;margin:2px 0">
            <?= htmlspecialchars($ra['scholarship_type']) ?>
          </div>
        <?php endif; ?>
        <div style="font-size:12px;color:var(--text-muted)">
          Score: <?= number_format($ra['score'],1) ?> &nbsp;·&nbsp;
          <?= $ra['approved_at'] ? date('M j', strtotime($ra['approved_at'])) : '' ?>
        </div>
      </div>
    <?php endforeach; endif; ?>
  </div>
</div>
