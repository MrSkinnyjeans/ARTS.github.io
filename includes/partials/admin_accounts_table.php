<div class="card" id="admin-accounts" style="margin-top:22px">
  <div class="card-header">
    <span class="card-title">Registered Admin Accounts</span>
    <span style="font-size:13px;color:var(--text-muted)"><?= count($adminAccounts) ?> account<?= count($adminAccounts)!==1?'s':'' ?></span>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>#</th><th>Full Name</th><th>Username</th><th>Approved Gmail</th><th>Role</th><th>Date Created</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php if (!$adminAccounts): ?>
          <tr><td colspan="7" class="empty-state"><p>No admin accounts yet.</p></td></tr>
        <?php else: foreach ($adminAccounts as $i => $acc): ?>
        <tr>
          <td style="color:var(--text-muted);font-size:13px"><?= $i+1 ?></td>
          <td><strong><?= htmlspecialchars($acc['full_name']) ?></strong></td>
          <td style="font-family:monospace;font-size:13px"><?= htmlspecialchars($acc['username']) ?></td>
          <td style="font-size:13px;color:var(--text-muted)">
            <?= $acc['approved_gmail'] ? htmlspecialchars($acc['approved_gmail']) : '<span style="color:#ccc">—</span>' ?>
          </td>
          <td><?= refBadge(ucfirst($acc['role'])) ?></td>
          <td style="font-size:13px;color:var(--text-muted)">
            <?= date('M j, Y', strtotime($acc['created_at'])) ?>
          </td>
          <td>
            <div style="display:flex;gap:6px;flex-wrap:wrap">
              <!-- Edit -->
              <button class="btn btn-outline btn-sm"
                      onclick="openEditAccountModal(<?= htmlspecialchars(json_encode($acc)) ?>)">
                Edit
              </button>
              <!-- Revoke -->
              <form method="POST" action="principal.php" style="display:inline">
                <input type="hidden" name="action" value="revoke_access">
                <input type="hidden" name="user_id" value="<?= $acc['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm"
                        data-confirm="Revoke access for <?= htmlspecialchars($acc['full_name']) ?>? This permanently deletes their account.">
                  Revoke
                </button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ── EDIT ACCOUNT MODAL ── -->
<div class="modal-overlay" id="modal-edit-account">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Edit Admin Account</span>
      <button class="modal-close" onclick="closeModal('modal-edit-account')">×</button>
    </div>
    <form method="POST" action="principal.php">
      <input type="hidden" name="action" value="edit_account">
      <input type="hidden" name="user_id" id="edit-acc-id">
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group">
            <label>Full Name *</label>
            <input name="full_name" id="edit-acc-name" required>
          </div>
          <div class="form-group">
            <label>Username *</label>
            <input name="username" id="edit-acc-username" required>
          </div>
        </div>
        <div class="form-group">
          <label>Approved Gmail *</label>
          <input name="approved_gmail" id="edit-acc-gmail" type="email"
                 pattern="[^@\s]+@gmail\.com" title="Only @gmail.com addresses allowed" required>
        </div>
        <div class="form-group">
          <label>New Password <span style="font-weight:400;color:var(--text-muted);font-size:12px">(leave blank to keep current)</span></label>
          <input name="new_password" id="edit-acc-password" type="text" placeholder="Enter new password or leave blank">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modal-edit-account')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEditAccountModal(acc) {
  document.getElementById('edit-acc-id').value       = acc.id;
  document.getElementById('edit-acc-name').value     = acc.full_name;
  document.getElementById('edit-acc-username').value = acc.username;
  document.getElementById('edit-acc-gmail').value    = acc.approved_gmail || '';
  document.getElementById('edit-acc-password').value = '';
  openModal('modal-edit-account');
}
</script>
