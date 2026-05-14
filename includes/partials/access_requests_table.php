<div class="card" id="access-requests">
  <div class="card-header">
    <span class="card-title">Access Requests</span>
    <?php if ($pendingReqs > 0): ?>
      <span class="badge badge-warning"><?= $pendingReqs ?> Pending</span>
    <?php endif; ?>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>#</th><th>Name</th><th>Position</th><th>Department</th>
          <th>Reason</th><th>Status</th><th>Submitted</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$accessRequests): ?>
          <tr><td colspan="8" class="empty-state"><p>No access requests yet.</p></td></tr>
        <?php else: foreach ($accessRequests as $i => $req): ?>
        <tr>
          <td style="color:var(--text-muted);font-size:13px"><?= $i+1 ?></td>
          <td>
            <strong><?= htmlspecialchars($req['full_name']) ?></strong>
            <br><span style="font-size:12px;color:var(--text-muted)"><?= htmlspecialchars($req['email']) ?></span>
          </td>
          <td style="font-size:13px"><?= htmlspecialchars($req['position']) ?></td>
          <td style="font-size:13px"><?= htmlspecialchars($req['department'] ?? '—') ?></td>
          <td style="font-size:13px;max-width:200px;color:var(--text-muted)">
            <?= htmlspecialchars(mb_strimwidth($req['reason'] ?? '', 0, 80, '…')) ?>
          </td>
          <td>
            <?php
              $sc = ['pending'=>'badge-warning','approved'=>'badge-success','rejected'=>'badge-danger'];
              echo '<span class="badge '.($sc[$req['status']]??'badge-neutral').'">'.ucfirst($req['status']).'</span>';
            ?>
          </td>
          <td style="font-size:12px;color:var(--text-muted)">
            <?= date('M j, Y', strtotime($req['created_at'])) ?>
          </td>
          <td>
            <?php if ($req['status'] === 'pending'): ?>
              <div style="display:flex;gap:6px">
                <button class="btn btn-success btn-sm"
                        onclick="openGrantModal(<?= htmlspecialchars(json_encode($req)) ?>)">
                  Grant
                </button>
                <form method="POST" action="principal.php" style="display:inline">
                  <input type="hidden" name="action" value="review_request">
                  <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                  <input type="hidden" name="req_status" value="rejected">
                  <button type="submit" class="btn btn-danger btn-sm"
                          data-confirm="Reject this access request?">Reject</button>
                </form>
              </div>
            <?php elseif ($req['status'] === 'approved'): ?>
              <span style="font-size:12px;color:var(--success)">
                Account: <strong><?= htmlspecialchars($req['username'] ?? '—') ?></strong>
              </span>
            <?php else: ?>
              <div style="display:flex;align-items:center;gap:8px">
                <span style="font-size:12px;color:var(--text-muted)">Reviewed</span>
                <form method="POST" action="principal.php" style="display:inline">
                  <input type="hidden" name="action" value="delete_request">
                  <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                  <button type="submit" class="btn btn-danger btn-sm"
                          data-confirm="Delete this rejected request permanently?">Delete</button>
                </form>
              </div>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
