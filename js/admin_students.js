(function () {
  "use strict";

  const { fetchJson, escapeHtml, setStatusById } = window.dmportal || {};

  const state = { students: [] };

  function matchesStudent(s, query) {
    const q = String(query || "").trim().toLowerCase();
    if (!q) return true;
    const hay = [s.full_name, s.email, s.student_code, s.student_id]
      .map((x) => (x == null ? "" : String(x)))
      .join(" ")
      .toLowerCase();
    return hay.includes(q);
  }

  function getYearFilter() {
    const sel = document.getElementById("studentsYearFilter");
    return sel?.value ? Number(sel.value) : 0;
  }

  function renderStudentsList() {
    const list = document.getElementById("adminStudentsList");
    if (!list) return;

    const q = String(document.getElementById("studentSearch")?.value || "");
    const year = getYearFilter();

    const filtered = (state.students || []).filter((s) => {
      if (year && Number(s.year_level) !== year) return false;
      return matchesStudent(s, q);
    });

    if (!filtered.length) {
      list.innerHTML = '<div class="muted">No students found.</div>';
      return;
    }

    list.innerHTML = "";
    for (const s of filtered) {
      const card = document.createElement("div");
      card.className = "course-item";

      const meta = `ID ${escapeHtml(s.student_code || s.student_id)} • ${escapeHtml(s.program || "")}`;
      const yearLabel = `Year ${escapeHtml(s.year_level)}`;

      card.innerHTML = `
        <div class="course-top">
          <div>
            <div class="course-title">${escapeHtml(s.full_name || "")}</div>
            <div class="muted" style="margin-top:4px;">${escapeHtml(s.email || "")}</div>
            <div class="muted" style="margin-top:4px;">${meta}</div>
          </div>
          <span class="badge">${yearLabel}</span>
        </div>
        <div class="actions" style="margin-top:10px; justify-content:flex-end;">
          <button class="btn btn-secondary btn-small" type="button" data-action="edit" data-id="${escapeHtml(s.student_id)}">Edit</button>
          <button class="btn btn-secondary btn-small" type="button" data-action="delete" data-id="${escapeHtml(s.student_id)}" style="border-color: rgba(255,106,122,.35);">Delete</button>
        </div>
      `;

      list.appendChild(card);
    }
  }

  async function loadStudents() {
    setStatusById("adminStudentsStatus", "Loading…");
    try {
      const qs = new URLSearchParams();
      const year = getYearFilter();
      if (year) qs.set("year_level", String(year));
      const url = `php/get_students.php${qs.toString() ? `?${qs}` : ""}`;
      const payload = await fetchJson(url);
      if (!payload.success) throw new Error(payload.error || "Failed to load students.");
      state.students = payload.data || [];
      renderStudentsList();
      setStatusById("adminStudentsStatus", "");
    } catch (err) {
      setStatusById("adminStudentsStatus", err.message || "Failed to load students.", "error");
    }
  }

  function openStudentEditModal(student) {
    const modal = document.getElementById("studentEditModal");
    if (!modal) return;

    document.getElementById("edit_student_id").value = String(student.student_id || "");
    document.getElementById("edit_student_full_name").value = student.full_name || "";
    document.getElementById("edit_student_email").value = student.email || "";
    document.getElementById("edit_student_code").value = student.student_code || "";
    document.getElementById("edit_student_program").value = student.program || "Digital Marketing";
    document.getElementById("edit_student_year_level").value = String(student.year_level || 1);

    setStatusById("studentEditStatus", "");
    modal.classList.add("open");
    modal.setAttribute("aria-hidden", "false");
  }

  function closeStudentEditModal() {
    const modal = document.getElementById("studentEditModal");
    if (!modal) return;
    modal.classList.remove("open");
    modal.setAttribute("aria-hidden", "true");
  }

  async function saveStudentEditModal() {
    const id = document.getElementById("edit_student_id").value;
    if (!id) return;

    const fd = new FormData();
    fd.append("student_id", id);
    fd.append("full_name", document.getElementById("edit_student_full_name").value);
    fd.append("email", document.getElementById("edit_student_email").value);
    fd.append("student_code", document.getElementById("edit_student_code").value);
    fd.append("program", document.getElementById("edit_student_program").value);
    fd.append("year_level", document.getElementById("edit_student_year_level").value);

    try {
      setStatusById("studentEditStatus", "Saving…");
      const payload = await fetchJson("php/update_student.php", { method: "POST", body: fd });
      if (!payload.success) throw new Error(payload.error || "Failed to update student.");
      setStatusById("studentEditStatus", "Saved.", "success");
      closeStudentEditModal();
      await loadStudents();
    } catch (err) {
      setStatusById("studentEditStatus", err.message || "Failed to update student.", "error");
    }
  }

  async function initAdminStudentsPage() {
    const form = document.getElementById("studentForm");
    if (!form) return;

    form.addEventListener("submit", async (e) => {
      e.preventDefault();
      setStatusById("studentStatus", "Saving…");
      const fd = new FormData(form);
      try {
        const payload = await fetchJson("php/add_student.php", { method: "POST", body: fd });
        if (!payload.success) throw new Error(payload.error || "Failed to add student.");
        setStatusById("studentStatus", "Saved.", "success");
        form.reset();
        await loadStudents();
      } catch (err) {
        setStatusById("studentStatus", err.message || "Failed to add student.", "error");
      }
    });

    document.getElementById("refreshStudentsAdmin")?.addEventListener("click", loadStudents);
    document.getElementById("studentSearch")?.addEventListener("input", renderStudentsList);
    document.getElementById("studentsYearFilter")?.addEventListener("change", loadStudents);

    document.querySelectorAll("#studentEditModal [data-close='1']")?.forEach((el) => {
      el.addEventListener("click", closeStudentEditModal);
    });
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape") closeStudentEditModal();
    });
    document.getElementById("studentEditSave")?.addEventListener("click", saveStudentEditModal);

    document.getElementById("adminStudentsList")?.addEventListener("click", async (e) => {
      const btn = e.target?.closest?.("button[data-action]");
      if (!btn) return;
      const action = btn.dataset.action;
      const id = Number(btn.dataset.id || 0);
      const student = (state.students || []).find((s) => Number(s.student_id) === id);
      if (!student) return;

      if (action === "edit") {
        openStudentEditModal(student);
        return;
      }

      if (action === "delete") {
        const ok = confirm("Delete this student? This cannot be undone.");
        if (!ok) return;
        try {
          setStatusById("adminStudentsStatus", "Deleting…");
          const fd = new FormData();
          fd.append("student_id", String(id));
          const payload = await fetchJson("php/delete_student.php", { method: "POST", body: fd });
          if (!payload.success) throw new Error(payload.error || "Failed to delete student.");
          setStatusById("adminStudentsStatus", "Deleted.", "success");
          await loadStudents();
        } catch (err) {
          setStatusById("adminStudentsStatus", err.message || "Failed to delete student.", "error");
        }
      }
    });

    await loadStudents();
  }

  window.dmportal = window.dmportal || {};
  window.dmportal.initAdminStudentsPage = initAdminStudentsPage;
})();
