<!-- Modal: Create New Project -->
<div class="modal-overlay" id="createProjectModalOverlay">
  <div class="modal-card">
    <div class="modal-header">
      <h3 class="modal-title">Create New Project</h3>
      <button type="button" class="modal-close-btn" id="btnCloseProjectModal" aria-label="Close modal">&times;</button>
    </div>
    <form id="createProjectForm">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label" for="projName">Project Name</label>
          <input type="text" id="projName" class="form-input" placeholder="e.g. Core App Redesign" required />
        </div>
        <div class="form-group">
          <label class="form-label" for="projCode">Project Code (Short 2-4 letters)</label>
          <input type="text" id="projCode" class="form-input" placeholder="e.g. CR" maxlength="4" value="PRJ" required />
        </div>
        <div class="form-group">
          <label class="form-label" for="projCategory">Category</label>
          <select id="projCategory" class="form-input">
            <option value="Engineering">Engineering</option>
            <option value="Design">Design</option>
            <option value="Product">Product</option>
            <option value="Operations">Operations</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label" for="projLevel">Priority</label>
          <select id="projLevel" class="form-input">
            <option value="Standard">Standard</option>
            <option value="High" selected>High</option>
            <option value="Urgent">Urgent</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-cancel" id="btnCancelProjectModal">Cancel</button>
        <button type="submit" class="btn-save" id="btnSubmitProjectModal">Create Project</button>
      </div>
    </form>
  </div>
</div>
