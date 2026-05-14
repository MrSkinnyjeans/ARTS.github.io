<div class="modal-overlay" id="modal-scholarship">
  <div class="modal">
    <div class="modal-header">
      <span class="modal-title">Review Scholarship</span>
      <button class="modal-close" onclick="closeModal('modal-scholarship')">×</button>
    </div>
    <form method="POST" action="principal.php">
      <input type="hidden" name="action" value="scholarship">
      <input type="hidden" name="result_id" id="sch-result-id">
      <div class="modal-body">

        <!-- Student info strip -->
        <div style="background:var(--info-bg);border:1px solid var(--border);border-radius:8px;padding:14px 16px;margin-bottom:20px">
          <div style="font-size:15px;font-weight:700;color:var(--navy)" id="sch-name"></div>
          <div style="font-size:13px;color:var(--text-muted);margin-top:3px">
            <span id="sch-school"></span> &nbsp;·&nbsp;
            Score: <strong id="sch-score"></strong> &nbsp;·&nbsp;
            Referred via: <span id="sch-ref"></span>
          </div>
        </div>

        <div class="form-group">
          <label>Scholarship Status</label>
          <select name="scholarship_status" id="sch-status">
            <option value="Not Evaluated">Not Evaluated</option>
            <option value="For Review">For Review</option>
            <option value="Approved">Approved ✓</option>
            <option value="Rejected">Rejected ✗</option>
          </select>
        </div>
        <div class="form-group">
          <label>Scholarship Type</label>
          <input name="scholarship_type" id="sch-type"
                 placeholder="e.g. Academic Excellence, Financial Assistance, Sports">
        </div>
        <div class="form-group">
          <label>Notes / Remarks</label>
          <textarea name="scholarship_notes" id="sch-notes" rows="3"
                    style="resize:vertical;font-family:'DM Sans',sans-serif;font-size:14px"
                    placeholder="Any additional notes about this scholarship decision…"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modal-scholarship')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Decision</button>
      </div>
    </form>
  </div>
</div>
