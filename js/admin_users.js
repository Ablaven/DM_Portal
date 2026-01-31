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
      row.className = "course-row";

      const allowedLabel = Array.isArray(u.allowed_pages) ? u.allowed_pages.join(", ") : "(role default)";
      const activeLabel = Number(u.is_active) === 1 ? "Active" : "Disabled";
      const activePillClass = Number(u.is_active) === 1 ? "pill-r" : "pill-las";

      row.innerHTML = `
        <div class="course-title">
          ${escapeHtml(u.username)}
          <span class="pill pill-r" style="margin-left:8px;">${escapeHtml(u.role)}</span>
          <span class="pill ${activePillClass}" style="margin-left:8px;">${escapeHtml(activeLabel)}</span>
        </div>
        <div class="muted" style="margin-top:4px;">user_id: ${escapeHtml(u.user_id)} • doctor_id: ${u.doctor_id ?? "-"} • student_id: ${u.student_id ?? "-"} • allowed: ${escapeHtml(allowedLabel)}</div>
        <div style="margin-top:8px; display:flex; gap:8px; flex-wrap:wrap;">
          <button class="btn btn-secondary btn-small" type="button" data-user-action="edit" data-user-id="${escapeHtml(u.user_id)}">Edit</button>
          <button class="btn btn-secondary btn-small" type="button" data-user-action="toggle" data-user-id="${escapeHtml(u.user_id)}" data-next-active="${Number(u.is_active) === 1 ? 0 : 1}">
            ${Number(u.is_active) === 1 ? "Disable" : "Enable"}
          </button>
          <button class="btn btn-secondary btn-small" type="button" data-user-action="delete" data-user-id="${escapeHtml(u.user_id)}" style="border-color: rgba(255,106,122,.35);">
            Delete
          </button>
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
      setListStatus("");
    } catch (err) {
      setListStatus(err.message || "Failed to load users.");
    }
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
      for (const p of selected) fd.append("allowed_pages[]", p);

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
