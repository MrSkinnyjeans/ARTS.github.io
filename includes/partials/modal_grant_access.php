<div class="modal-overlay" id="modal-grant-access">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Grant System Access</span>
      <button class="modal-close" onclick="closeModal('modal-grant-access')">×</button>
    </div>
    <form method="POST" action="principal.php">
      <input type="hidden" name="action" value="grant_access">
      <input type="hidden" name="request_id" id="grant-request-id">
      <input type="hidden" name="req_full_name" id="grant-full-name">
      <div class="modal-body">

        <!-- Request summary -->
        <div style="background:var(--warning-bg);border:1px solid var(--border);border-radius:8px;padding:14px 16px;margin-bottom:20px;font-size:13px">
          <div style="font-weight:700;color:var(--navy);font-size:14px" id="grant-full-name-display"></div>
          <div style="margin-top:6px">
            Email: <strong id="grant-email" style="color:var(--navy)"></strong>
          </div>
        </div>

        <div class="form-group">
          <label>Assign Username *</label>
          <input name="new_username" id="grant-username" required placeholder="e.g. jdelacruz">
        </div>
        <div class="form-group">
          <label>Approved Gmail Address *</label>
          <input name="approved_gmail" id="grant-approved-gmail" type="email" required
                 placeholder="e.g. jdelacruz@gmail.com"
                 pattern="[^@\s]+@gmail\.com"
                 title="Only @gmail.com addresses are allowed">
          <small style="color:var(--text-muted);font-size:11.5px">This is the only Gmail this user can log in with.</small>
        </div>
        <div style="background:var(--info-bg);border:1px solid var(--border);border-radius:8px;padding:12px 14px;font-size:12px;color:var(--info)">
          ℹ️ An email will be sent to the approved Gmail with a link to set their password.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modal-grant-access')">Cancel</button>
        <button type="submit" class="btn btn-primary">Create Account &amp; Approve</button>
      </div>
    </form>
  </div>
</div>

<script>
var _origOpenGrantModal = window.openGrantModal;
window.openGrantModal = function(req) {
  document.getElementById('grant-full-name-display').textContent = req.full_name;
  _origOpenGrantModal(req);
};
</script>
