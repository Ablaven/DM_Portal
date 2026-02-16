(() => {
  const { fetchJson, setStatusById, escapeHtml } = window.dmportal || {};
  if (!fetchJson || !escapeHtml) return;

  const statusId = "advanceStatus";

  const modal = document.getElementById("customAdvanceModal");
  const searchInput = document.getElementById("customAdvanceSearch");
  const programSelect = document.getElementById("customAdvanceProgram");
  const yearSelect = document.getElementById("customAdvanceYear");
  const presetSelect = document.getElementById("customAdvancePreset");

  let students = [];
  const studentMap = new Map();
  let filteredIds = [];

  async function handleAdvance() {
    const startDateInput = document.getElementById("advanceStartDate");
    const startDate = String(startDateInput?.value || "").trim();
    if (!startDate) {
      setStatusById(statusId, "Pick a start date first.", "error");
      return;
    }

    const ok = confirm(
      "This will advance to the next semester or academic year depending on the current term. Weeks will reset and hours will restart. Continue?"
    );
    if (!ok) return;

    try {
      setStatusById(statusId, "Advancing…");
      const payload = await fetchJson("php/advance_term.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: new URLSearchParams({ start_date: startDate, advance_mode: "auto" }),
      });
      if (!payload.success) throw new Error(payload.error || "Advance failed.");
      const action = payload.data?.action || "advance";
      setStatusById(statusId, `Advance complete: ${action}.`, "success");
      document.dispatchEvent(new CustomEvent("dmportal:terms-updated"));
    } catch (err) {
      setStatusById(statusId, err.message || "Advance failed.", "error");
    }
  }

  function openModal() {
    if (!modal) return;
    modal.classList.add("open");
    modal.setAttribute("aria-hidden", "false");
  }

  function closeModal() {
    if (!modal) return;
    modal.classList.remove("open");
    modal.setAttribute("aria-hidden", "true");
  }

  function renderStudents() {
    const tbody = document.querySelector("#customAdvanceTable tbody");
    if (!tbody) return;
    tbody.innerHTML = "";
    const q = String(searchInput?.value || "").toLowerCase();
    const program = String(programSelect?.value || "").trim();
    const year = Number(yearSelect?.value || 0);

    const filtered = students.filter((s) => {
      if (program && String(s.program || "") !== program) return false;
      if (year > 0 && Number(s.year_level) !== year) return false;
      const hay = [s.full_name, s.email, s.student_code]
        .map((v) => (v ? String(v).toLowerCase() : ""))
        .join(" ");
      return !q || hay.includes(q);
    });

    filteredIds = filtered.map((s) => Number(s.student_id));

    if (!filtered.length) {
      tbody.innerHTML = '<tr><td colspan="4" class="muted">No students found.</td></tr>';
      return;
    }

    for (const s of filtered) {
      const tr = document.createElement("tr");
      tr.dataset.studentId = String(s.student_id);
      tr.innerHTML = `
        <td>
          <div>${escapeHtml(s.full_name || "")}</div>
          <div class="muted" style="font-size:0.85rem;">${escapeHtml(s.email || "")}</div>
        </td>
        <td>${escapeHtml(s.year_level)}</td>
        <td>
          <select class="navlink" data-action="select" data-id="${s.student_id}" style="padding:6px 8px;">
            <option value="advance">Advance</option>
            <option value="repeat">Repeat</option>
            <option value="graduate">Graduate</option>
          </select>
        </td>
        <td>
          <input type="number" min="1" max="3" class="navlink" data-action="year" data-id="${s.student_id}" style="width:70px; padding:6px 8px;" value="${Math.min((Number(s.year_level) || 1) + 1, 3)}" />
        </td>
      `;
      tbody.appendChild(tr);
    }
  }

  async function loadStudents() {
    const payload = await fetchJson("php/get_students.php");
    if (!payload.success) throw new Error(payload.error || "Failed to load students.");
    students = payload.data || [];
    studentMap.clear();
    students.forEach((s) => {
      studentMap.set(Number(s.student_id), s);
    });
    renderProgramOptions();
    renderStudents();
  }

  function renderProgramOptions() {
    if (!programSelect) return;
    const programs = Array.from(new Set(students.map((s) => String(s.program || "")))).filter(Boolean).sort();
    const current = programSelect.value;
    programSelect.innerHTML = "<option value=\"\">All Programs</option>";
    programs.forEach((p) => {
      const opt = document.createElement("option");
      opt.value = p;
      opt.textContent = p;
      if (p === current) opt.selected = true;
      programSelect.appendChild(opt);
    });
  }

  function collectStudentActions() {
    const rows = document.querySelectorAll("#customAdvanceTable tbody tr");
    const advance = [];
    const repeat = [];
    const graduate = [];

    rows.forEach((row) => {
      const select = row.querySelector("select[data-action='select']");
      const input = row.querySelector("input[data-action='year']");
      const id = select?.dataset?.id ? Number(select.dataset.id) : 0;
      if (!id) return;
      const action = select?.value || "advance";
      const yearLevel = input?.value ? Number(input.value) : 0;

      if (action === "advance") {
        advance.push({ student_id: id, year_level: yearLevel });
      } else if (action === "repeat") {
        repeat.push(id);
      } else if (action === "graduate") {
        graduate.push(id);
      }
    });

    return { advance, repeat, graduate };
  }

  function applyPreset() {
    const preset = presetSelect?.value || "";
    if (!preset) return;

    const rows = document.querySelectorAll("#customAdvanceTable tbody tr");
    rows.forEach((row) => {
      const id = Number(row.dataset.studentId || 0);
      if (!id || (filteredIds.length && !filteredIds.includes(id))) return;
      const student = studentMap.get(id);
      if (!student) return;

      const select = row.querySelector("select[data-action='select']");
      const input = row.querySelector("input[data-action='year']");
      const currentYear = Number(student.year_level) || 1;
      const nextYear = Math.min(currentYear + 1, 3);

      if (preset === "advance_all") {
        if (select) select.value = "advance";
        if (input) input.value = String(nextYear);
      }

      if (preset === "repeat_all") {
        if (select) select.value = "repeat";
      }

      if (preset === "graduate_final") {
        if (currentYear >= 3) {
          if (select) select.value = "graduate";
        }
      }

      if (preset === "advance_except_final") {
        if (currentYear >= 3) {
          if (select) select.value = "graduate";
        } else {
          if (select) select.value = "advance";
          if (input) input.value = String(nextYear);
        }
      }
    });
  }

  function applyBulkAction(action) {
    const rows = document.querySelectorAll("#customAdvanceTable tbody tr");
    rows.forEach((row) => {
      const id = Number(row.dataset.studentId || 0);
      if (!id || (filteredIds.length && !filteredIds.includes(id))) return;
      const student = studentMap.get(id);
      if (!student) return;

      const select = row.querySelector("select[data-action='select']");
      const input = row.querySelector("input[data-action='year']");
      const currentYear = Number(student.year_level) || 1;
      const nextYear = Math.min(currentYear + 1, 3);

      if (select) select.value = action;
      if (action === "advance" && input) input.value = String(nextYear);
    });
  }

  async function submitCustomAdvance() {
    try {
      setStatusById("customAdvanceStatus", "Advancing…");
      const actions = collectStudentActions();
      const startDate = String(document.getElementById("customAdvanceStartDate")?.value || "").trim();
      const payload = await fetchJson("php/advance_term.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: new URLSearchParams({
          start_date: startDate,
          advance_mode: "custom",
          student_actions: JSON.stringify(actions),
        }),
      });
      if (!payload.success) throw new Error(payload.error || "Advance failed.");
      setStatusById("customAdvanceStatus", "Custom advance complete.", "success");
      closeModal();
      document.dispatchEvent(new CustomEvent("dmportal:terms-updated"));
    } catch (err) {
      setStatusById("customAdvanceStatus", err.message || "Advance failed.", "error");
    }
  }

  function init() {
    const btn = document.getElementById("advanceTermButton");
    if (btn) btn.addEventListener("click", handleAdvance);

    document.getElementById("advanceStartDatePick")?.addEventListener("click", () => {
      const input = document.getElementById("advanceStartDate");
      if (!input) return;
      if (typeof input.showPicker === "function") {
        input.showPicker();
      } else {
        input.focus();
      }
    });

    document.getElementById("customAdvanceButton")?.addEventListener("click", async () => {
      try {
        await loadStudents();
        openModal();
      } catch (err) {
        setStatusById(statusId, err.message || "Failed to load students.", "error");
      }
    });

    document.querySelectorAll("#customAdvanceModal [data-close='1']")?.forEach((el) => {
      el.addEventListener("click", closeModal);
    });

    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape") closeModal();
    });

    document.getElementById("customAdvanceSubmit")?.addEventListener("click", submitCustomAdvance);
    document.getElementById("applyAdvancePreset")?.addEventListener("click", applyPreset);
    document.getElementById("bulkAdvanceAll")?.addEventListener("click", () => applyBulkAction("advance"));
    document.getElementById("bulkRepeatAll")?.addEventListener("click", () => applyBulkAction("repeat"));
    document.getElementById("bulkGraduateAll")?.addEventListener("click", () => applyBulkAction("graduate"));
    searchInput?.addEventListener("input", renderStudents);
    programSelect?.addEventListener("change", renderStudents);
    yearSelect?.addEventListener("change", renderStudents);
  }

  init();
})();
