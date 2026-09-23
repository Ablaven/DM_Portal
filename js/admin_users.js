(function () {
  "use strict";

  const { fetchJson, escapeHtml, setStatusById } = window.dmportal || {};

async function initAdminUsersPage() {
  const list = document.getElementById("usersList");
  if (!list) return;

  const status = document.getElementById("usersStatus");
  const createForm = document.getElementById("createUserForm");
  const createStatus = document.getElementById("createUserStatus");
  const refreshBtn = document.getElementById("refreshUsers");
  const searchEl = document.getElementById("userSearch");

  const modal = document.getElementById("userEditModal");
  const modalStatus = document.getElementById("userEditStatus");
  const saveBtn = document.getElementById("userEditSave");

  const fId = document.getElementById("edit_user_id");
  const fUsername = document.getElementById("edit_user_username");
  const fRole = document.getElementById("edit_user_role");
  const fDoctorId = document.getElementById("edit_user_doctor_id");
  const fStudentId = document.getElementById("edit_user_student_id");
  const fAllowed = document.getElementById("edit_user_allowed"); // checkbox container
  const createRole = document.getElementById("u_role");
  const createDoctorWrap = document.getElementById("u_doctor_id_wrap");
  const createStudentWrap = document.getElementById("u_student_id_wrap");
  const createDoctor = document.getElementById("u_doctor_id");
  const createStudent = document.getElementById("u_student_id");

  const editDoctorWrap = document.getElementById("edit_user_doctor_id_wrap");
  const editStudentWrap = document.getElementById("edit_user_student_id_wrap");
  const fActive = document.getElementById("edit_user_active");
  const fNewPassword = document.getElementById("edit_user_new_password");

  let usersCache = [];

  function setListStatus(msg) {
    if (status) status.textContent = msg || "";
  }

  function setCreateStatus(msg, ok) {
    if (!createStatus) return;
    createStatus.textContent = msg || "";
    const rootStyles = getComputedStyle(document.documentElement);
    createStatus.style.color = ok ? rootStyles.getPropertyValue('--success') : rootStyles.getPropertyValue('--danger');
  }

  function setModalStatus(msg, ok) {
    if (!modalStatus) return;
    modalStatus.textContent = msg || "";
    const rootStyles = getComputedStyle(document.documentElement);
    modalStatus.style.color = ok ? rootStyles.getPropertyValue('--success') : rootStyles.getPropertyValue('--danger');
  }

  function setRoleVisibility(roleValue, mode) {
    const role = String(roleValue || "").trim();

    const showDoctor = role === "teacher";
    const showStudent = role === "student";
    // management behaves like admin (no IDs)

    if (mode === "create") {
      if (createDoctorWrap) createDoctorWrap.style.display = showDoctor ? "" : "none";
      if (createStudentWrap) createStudentWrap.style.display = showStudent ? "" : "none";

      // Clear irrelevant values to avoid backend validation errors.
      if (!showDoctor && createDoctor) createDoctor.value = "";
      if (!showStudent && createStudent) createStudent.value = "";
    }

    if (mode === "edit") {
      if (editDoctorWrap) editDoctorWrap.style.display = showDoctor ? "" : "none";
      if (editStudentWrap) editStudentWrap.style.display = showStudent ? "" : "none";

      if (!showDoctor && fDoctorId) fDoctorId.value = "";
      if (!showStudent && fStudentId) fStudentId.value = "";
    }
  }

  function getCheckedPages(container) {
    if (!container) return [];
    return Array.from(container.querySelectorAll('input[type="checkbox"]:checked')).map((cb) => String(cb.value));
  }

  function setCheckedPages(container, allowedList) {
    if (!container) return;
    const allowed = new Set((allowedList || []).map(String));
    for (const cb of container.querySelectorAll('input[type="checkbox"]')) {
      cb.checked = allowed.has(String(cb.value));
    }
  }

  function openUserModal(user) {
    if (!modal) return;

    fId.value = String(user.user_id);
    fUsername.value = user.username || "";
    fRole.value = user.role || "teacher";
    fDoctorId.value = user.doctor_id ? String(user.doctor_id) : "";
    fStudentId.value = user.student_id ? String(user.student_id) : "";
    fActive.value = String(user.is_active ?? 1);
    fNewPassword.value = "";

    // Allowed pages: null means role default.
    const allowed = Array.isArray(user.allowed_pages) ? user.allowed_pages.map(String) : [];
    setCheckedPages(fAllowed, allowed);

    setRoleVisibility(fRole.value, "edit");

    setModalStatus("");
    modal.classList.add("open");
    modal.setAttribute("aria-hidden", "false");
  }

  function closeUserModal() {
    if (!modal) return;
    modal.classList.remove("open");
    modal.setAttribute("aria-hidden", "true");
  }

  // Close modal handlers
  modal?.querySelectorAll("[data-close='1']")?.forEach((el) => {
    el.addEventListener("click", closeUserModal);
  });
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && modal?.classList.contains("open")) closeUserModal();
  });

  function matchesUser(u, q) {
    const needle = String(q || "").trim().toLowerCase();
    if (!needle) return true;

    const fields = [
      u.username,
      u.role,
      u.user_id,
      u.doctor_id,
      u.student_id,
      u.is_active === 1 ? 'active' : 'disabled',
    ]
      .map((x) => (x === null || x === undefined ? "" : String(x)))
      .join(" ")
      .toLowerCase();

    return fields.includes(needle);
  }

  function renderUsers() {
    const q = searchEl ? String(searchEl.value || "") : "";
    const items = usersCache.filter((u) => matchesUser(u, q));

    list.innerHTML = "";
    if (!items.length) {
      list.innerHTML = '<div class="muted">No users found.</div>';
      return;
    }

    for (const u of items) {
      const row = document.createElement("div");
      row.className = "user-card";

      const allowedPages = Array.isArray(u.allowed_pages) && u.allowed_pages.length > 0 
        ? u.allowed_pages.length + " pages" 
        : "Role defaults";
      const isActive = Number(u.is_active) === 1;
      const activeLabel = isActive ? "Active" : "Disabled";
      
      // Role badge colors
      const roleColors = {
        admin: 'background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3);',
        teacher: 'background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3);',
        student: 'background: rgba(59, 130, 246, 0.15); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.3);',
        management: 'background: rgba(147, 51, 234, 0.15); color: #9333ea; border: 1px solid rgba(147, 51, 234, 0.3);'
      };
      const roleStyle = roleColors[u.role] || roleColors.teacher;

      row.innerHTML = `
        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap;">
          <div style="flex: 1; min-width: 200px;">
            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
              <h3 style="margin: 0; font-size: 1.1rem; font-weight: 600;">${escapeHtml(u.username)}</h3>
              <span style="display: inline-block; padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; ${roleStyle}">${escapeHtml(u.role)}</span>
              <span style="display: inline-block; padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 600; ${isActive ? 'background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3);' : 'background: rgba(107, 114, 128, 0.15); color: #6b7280; border: 1px solid rgba(107, 114, 128, 0.3);'}">${escapeHtml(activeLabel)}</span>
            </div>
            <div style="display: flex; gap: 16px; flex-wrap: wrap; font-size: 0.9rem; color: var(--muted);">
              ${u.doctor_id ? `<span>Doctor ID: <strong style="color: var(--text);">${escapeHtml(u.doctor_id)}</strong></span>` : ''}
              ${u.student_id ? `<span>Student ID: <strong style="color: var(--text);">${escapeHtml(u.student_id)}</strong></span>` : ''}
              <span>User ID: <strong style="color: var(--text);">${escapeHtml(u.user_id)}</strong></span>
              <span>Access: <strong style="color: var(--text);">${escapeHtml(allowedPages)}</strong></span>
            </div>
          </div>
          <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <button class="btn btn-small btn-secondary" type="button" data-user-action="edit" data-user-id="${escapeHtml(u.user_id)}">Edit</button>
            <button class="btn btn-small btn-secondary" type="button" data-user-action="toggle" data-user-id="${escapeHtml(u.user_id)}" data-next-active="${isActive ? 0 : 1}">
              ${isActive ? "Disable" : "Enable"}
            </button>
            <button class="btn btn-small btn-secondary" type="button" data-user-action="delete" data-user-id="${escapeHtml(u.user_id)}" style="border-color: rgba(239, 68, 68, 0.4); color: #ef4444;">
              Delete
            </button>
          </div>
        </div>
      `;

      list.appendChild(row);
    }
  }

  async function loadUsers() {
    setListStatus("Loading…");
    try {
      const payload = await fetchJson("php/admin_users_list.php");
      usersCache = payload?.data || [];

      renderUsers();
      calculateSummaryStats();
      setListStatus("");
    } catch (err) {
      setListStatus(err.message || "Failed to load users.");
    }
  }

  function calculateSummaryStats() {
    const total = usersCache.length;
    const adminCount = usersCache.filter(u => u.role === 'admin').length;
    const teacherCount = usersCache.filter(u => u.role === 'teacher').length;
    const studentCount = usersCache.filter(u => u.role === 'student').length;
    const activeCount = usersCache.filter(u => Number(u.is_active) === 1).length;

    document.getElementById('usersTotalCount').textContent = total;
    document.getElementById('usersAdminCount').textContent = adminCount;
    document.getElementById('usersTeacherCount').textContent = teacherCount;
    document.getElementById('usersStudentCount').textContent = studentCount;
    document.getElementById('usersActiveCount').textContent = activeCount;
  }

  list.addEventListener("click", async (e) => {
    const btn = e.target?.closest?.("button[data-user-action]");
    if (!btn) return;

    const action = btn.getAttribute("data-user-action");
    const userId = Number(btn.getAttribute("data-user-id") || 0);
    const user = usersCache.find((x) => Number(x.user_id) === userId);
    if (!user) return;

    if (action === "edit") {
      openUserModal(user);
      return;
    }

    if (action === "delete") {
      const ok = window.confirm("Delete this user permanently? This cannot be undone.");
      if (!ok) return;
      try {
        const fd = new FormData();
        fd.append("user_id", String(userId));
        await fetchJson("php/admin_users_delete.php", { method: "POST", body: fd });
        await loadUsers();
      } catch (err) {
        setListStatus(err.message || "Failed to delete user.");
      }
      return;
    }

    if (action === "toggle") {
      const nextActive = Number(btn.getAttribute("data-next-active") || 0);
      const ok = window.confirm(nextActive === 1 ? "Enable this user?" : "Disable this user?");
      if (!ok) return;

      try {
        const fd = new FormData();
        fd.append("user_id", String(userId));
        fd.append("is_active", String(nextActive));
        await fetchJson("php/admin_users_toggle_active.php", { method: "POST", body: fd });
        await loadUsers();
      } catch (err) {
        setListStatus(err.message || "Failed to update user.");
      }
    }
  });

  saveBtn?.addEventListener("click", async () => {
    const userId = Number(fId.value || 0);
    if (!userId) return;

    setModalStatus("Saving…", true);

    try {
      const fd = new FormData();
      fd.append("user_id", String(userId));
      fd.append("username", String(fUsername.value || "").trim());
      fd.append("role", String(fRole.value || "").trim());
      // Send only the relevant ID field for the selected role.
      const nextRole = String(fRole.value || "").trim();
      if (nextRole === "teacher") {
        fd.append("doctor_id", String(fDoctorId.value || "").trim());
      } else if (nextRole === "student") {
        fd.append("student_id", String(fStudentId.value || "").trim());
      } else {
        // admin => no IDs
      }
      fd.append("is_active", String(fActive.value || "1"));

      const selected = getCheckedPages(fAllowed);
      if (selected.length > 0) {
        for (const p of selected) fd.append("allowed_pages[]", p);
      } else {
        // Send empty string to explicitly indicate "clear all pages, use role defaults"
        fd.append("allowed_pages[]", "");
      }

      await fetchJson("php/admin_users_update.php", { method: "POST", body: fd });

      const pw = String(fNewPassword.value || "");
      if (pw.trim()) {
        const fd2 = new FormData();
        fd2.append("user_id", String(userId));
        fd2.append("password", pw);
        await fetchJson("php/admin_users_set_password.php", { method: "POST", body: fd2 });
      }

      setModalStatus("Saved.", true);
      closeUserModal();
      await loadUsers();
    } catch (err) {
      setModalStatus(err.message || "Failed to save.", false);
    }
  });

  createForm?.addEventListener("submit", async (e) => {
    e.preventDefault();
    setCreateStatus("Creating…", true);
    try {
      const fd = new FormData(createForm);

      // Ensure we don't submit irrelevant IDs (prevents backend "not found" checks).
      const role = String(createRole?.value || "").trim();
      if (role !== "teacher") fd.delete("doctor_id");
      if (role !== "student") fd.delete("student_id");

      const payload = await fetchJson("php/admin_users_create.php", { method: "POST", body: fd });
      if (!payload?.success) throw new Error(payload?.error || "Failed");
      setCreateStatus("User created.", true);
      createForm.reset();
      await loadUsers();
    } catch (err) {
      setCreateStatus(err.message || "Failed to create user.", false);
    }
  });

  // Role-driven field visibility
  createRole?.addEventListener("change", () => setRoleVisibility(createRole.value, "create"));
  fRole?.addEventListener("change", () => setRoleVisibility(fRole.value, "edit"));
  setRoleVisibility(createRole?.value, "create");

  refreshBtn?.addEventListener("click", loadUsers);
  searchEl?.addEventListener("input", () => {
    renderUsers();
  });

  await loadUsers();
}

  window.dmportal = window.dmportal || {};
  window.dmportal.initAdminUsersPage = initAdminUsersPage;
})();
