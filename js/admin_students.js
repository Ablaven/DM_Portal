(function () {
  "use strict";

  const { fetchJson, escapeHtml, setStatusById, renderEmptyState, showSuccess, showError, showInfo } = window.dmportal || {};

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
      renderEmptyState(list, {
        icon: "\u{1F393}",
        title: q || year ? "No students found" : "No students yet",
        subtitle: q || year ? "Try adjusting your search or year filter." : "Get started by adding your first student using the form above.",
        ctaText: (q || year) ? "" : "Refresh students",
        ctaOnClick: (q || year) ? null : () => { loadStudents().then(renderStudentsList); },
        className: "admin-students-empty"
      });
      return;
    }

    // Clear any existing empty state when there are results
    list.querySelectorAll(".empty-state").forEach(el => el.remove());

    list.innerHTML = "";
    for (const s of filtered) {
      const card = document.createElement("div");
      card.className = "student-card";

      const studentCodeLabel = s.student_code ? `${escapeHtml(s.student_code)}` : `${escapeHtml(s.student_id)}`;
      const yearLabel = `Year ${escapeHtml(s.year_level)}`;
      
      // Year badge colors
      const yearColors = {
        1: 'background: rgba(59, 130, 246, 0.15); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.3);',
        2: 'background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3);',
        3: 'background: rgba(245, 158, 11, 0.15); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.3);'
      };
      const yearStyle = yearColors[Number(s.year_level)] || yearColors[1];

      card.innerHTML = `
        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap;">
          <div style="flex: 1; min-width: 200px;">
            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
              <h3 style="margin: 0; font-size: 1.1rem; font-weight: 600;">${escapeHtml(s.full_name || "")}</h3>
              <span style="display: inline-block; padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 600; ${yearStyle}">${yearLabel}</span>
            </div>
            <div style="margin-bottom: 6px; color: var(--muted);">${escapeHtml(s.email || "")}</div>
            <div style="display: flex; gap: 16px; flex-wrap: wrap; font-size: 0.9rem; color: var(--muted);">
              <span>Student ID: <strong style="color: var(--text);">${studentCodeLabel}</strong></span>
              <span>DB ID: <strong style="color: var(--text);">${escapeHtml(s.student_id)}</strong></span>
              <span>Program: <strong style="color: var(--text);">${escapeHtml(s.program || "")}</strong></span>
            </div>
          </div>
          <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <button class="btn btn-small btn-secondary" type="button" data-action="edit" data-id="${escapeHtml(s.student_id)}">Edit</button>
            <button class="btn btn-small btn-secondary" type="button" data-action="delete" data-id="${escapeHtml(s.student_id)}" style="border-color: rgba(239, 68, 68, 0.4); color: #ef4444;">Delete</button>
          </div>
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
      calculateSummaryStats();
      setStatusById("adminStudentsStatus", "");
    } catch (err) {
      setStatusById("adminStudentsStatus", err.message || "Failed to load students.", "error");
    }
  }

  function calculateSummaryStats() {
    const total = state.students.length;
    const year1 = state.students.filter(s => Number(s.year_level) === 1).length;
    const year2 = state.students.filter(s => Number(s.year_level) === 2).length;
    const year3 = state.students.filter(s => Number(s.year_level) === 3).length;
    const dm = state.students.filter(s => s.program === 'Digital Marketing').length;

    document.getElementById('studentsTotalCount').textContent = total;
    document.getElementById('studentsYear1Count').textContent = year1;
    document.getElementById('studentsYear2Count').textContent = year2;
    document.getElementById('studentsYear3Count').textContent = year3;
    document.getElementById('studentsDMCount').textContent = dm;
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
      showSuccess("Student updated successfully!", 3500);
      closeStudentEditModal();
      await loadStudents();
    } catch (err) {
      setStatusById("studentEditStatus", err.message || "Failed to update student.", "error");
      showError(err.message || "Failed to update student.", 5000);
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
        showSuccess("Student added successfully!", 3500);
        form.reset();
        await loadStudents();
      } catch (err) {
        setStatusById("studentStatus", err.message || "Failed to add student.", "error");
        showError(err.message || "Failed to add student.", 5000);
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
          showSuccess("Student deleted successfully!", 3500);
          await loadStudents();
        } catch (err) {
          setStatusById("adminStudentsStatus", err.message || "Failed to delete student.", "error");
          showError(err.message || "Failed to delete student.", 5000);
        }
      }
    });

    await loadStudents();
  }

  window.dmportal = window.dmportal || {};
  window.dmportal.initAdminStudentsPage = initAdminStudentsPage;
})();
