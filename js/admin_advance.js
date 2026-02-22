(() => {
  const { fetchJson, setStatusById, escapeHtml } = window.dmportal || {};
  if (!fetchJson || !escapeHtml) return;

  // ── State ─────────────────────────────────────────────────────────────────────
  let activeTerm = null;   // { term_id, label, semester, academic_year_id }
  let wizStartDate = "";
  let students = [];
  const studentMap = new Map();

  // ── Step helpers ──────────────────────────────────────────────────────────────
  const steps = ["wizStep0", "wizStep1", "wizStep2a", "wizStep2b", "wizStep3b"];

  function showStep(id) {
    steps.forEach((s) => {
      const el = document.getElementById(s);
      if (el) el.style.display = s === id ? "" : "none";
    });
  }

  function resetToStep0() {
    wizStartDate = "";
    const dateInput = document.getElementById("wizStartDate");
    if (dateInput) dateInput.value = "";
    ["wizStep0Status","wizStep1Status","wizStep2aStatus","wizStep2bStatus","wizStep3bStatus"]
      .forEach((id) => setStatusById(id, ""));
    showStep("wizStep0");
  }

  // ── Header bar ────────────────────────────────────────────────────────────────
  function updateHeader() {
    const labelEl  = document.getElementById("wizardCurrentLabel");
    const hintEl   = document.getElementById("wizardNextHint");
    if (!labelEl) return;

    if (!activeTerm) {
      labelEl.textContent = "No active semester";
      if (hintEl) hintEl.textContent = "";
      return;
    }

    const sem = Number(activeTerm.semester);
    labelEl.textContent = activeTerm.label || `Semester ${sem}`;
    if (hintEl) {
      hintEl.textContent = sem === 1
        ? "→ Semester 2 next"
        : "→ New Academic Year next";
    }
  }

  // ── Step 0: idle ──────────────────────────────────────────────────────────────
  function updateStep0() {
    const btn  = document.getElementById("wizPrimaryBtn");
    const hint = document.getElementById("wizStep0Hint");
    if (!btn) return;

    const sem = activeTerm ? Number(activeTerm.semester) : 0;

    if (sem === 1) {
      btn.textContent  = "Advance to Semester 2";
      if (hint) hint.textContent = "Semester 1 is active. Click to close it, activate Semester 2, and reset weeks to Week 1. Student year levels will not change.";
    } else if (sem === 2) {
      btn.textContent  = "Start New Academic Year";
      if (hint) hint.textContent = "Semester 2 is active. Click to close this academic year, start the next one, reset weeks to Week 1, and update student year levels.";
    } else {
      btn.textContent  = "Advance Semester";
      if (hint) hint.textContent = "Click to advance the current semester.";
    }
  }

  // ── Step 1: pick date ─────────────────────────────────────────────────────────
  function goToStep1() {
    const dateInput = document.getElementById("wizStartDate");
    if (dateInput && !dateInput.value) {
      dateInput.value = new Date().toISOString().slice(0, 10);
    }
    setStatusById("wizStep1Status", "");
    showStep("wizStep1");
    dateInput?.focus();
  }

  function handleStep1Next() {
    const dateInput = document.getElementById("wizStartDate");
    const val = String(dateInput?.value || "").trim();
    if (!val) {
      setStatusById("wizStep1Status", "Please pick a start date.", "error");
      dateInput?.focus();
      return;
    }
    wizStartDate = val;
    const sem = activeTerm ? Number(activeTerm.semester) : 0;
    sem === 2 ? goToStep2b() : goToStep2a();
  }

  // ── Summary builder ───────────────────────────────────────────────────────────
  function buildSummaryHtml(rows) {
    return rows.map(([label, value]) =>
      `<div style="display:flex; gap:12px; align-items:baseline;">
         <span class="muted" style="min-width:140px; font-size:0.85rem;">${escapeHtml(label)}</span>
         <span style="font-weight:500;">${escapeHtml(value)}</span>
       </div>`
    ).join("");
  }

  // ── Step 2a: Sem 1→2 confirm ──────────────────────────────────────────────────
  function goToStep2a() {
    const summary = document.getElementById("wizStep2aSummary");
    if (summary) {
      summary.innerHTML = buildSummaryHtml([
        ["Week 1 starts",  wizStartDate],
        ["Action",         "Close Semester 1, activate Semester 2"],
        ["Weeks",          "Reset to Week 1"],
        ["Students",       "No change to year levels"],
      ]);
    }
    setStatusById("wizStep2aStatus", "");
    showStep("wizStep2a");
  }

  async function handleStep2aConfirm() {
    const btn = document.getElementById("wizStep2aConfirm");
    if (btn) btn.disabled = true;
    try {
      setStatusById("wizStep2aStatus", "Advancing…");
      const res = await fetchJson("php/advance_term.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: new URLSearchParams({ start_date: wizStartDate, advance_mode: "auto" }),
      });
      if (!res.success) throw new Error(res.error || "Advance failed.");
      setStatusById("wizStep0Status", "Semester 2 is now active. Week 1 has been created.", "success");
      resetToStep0();
      document.dispatchEvent(new CustomEvent("dmportal:terms-updated"));
    } catch (err) {
      setStatusById("wizStep2aStatus", err.message || "Advance failed.", "error");
    } finally {
      if (btn) btn.disabled = false;
    }
  }

  // ── Step 2b: Year advance — student rule ──────────────────────────────────────
  async function goToStep2b() {
    setStatusById("wizStep2bStatus", "");
    showStep("wizStep2b");
    if (!students.length) {
      try {
        setStatusById("wizStep2bStatus", "Loading students…");
        const res = await fetchJson("php/get_students.php");
        if (!res.success) throw new Error(res.error || "Failed to load students.");
        students = res.data || [];
        studentMap.clear();
        students.forEach((s) => studentMap.set(Number(s.student_id), s));
        setStatusById("wizStep2bStatus", "");
      } catch (err) {
        setStatusById("wizStep2bStatus", err.message || "Failed to load students.", "error");
      }
    }
    handlePresetChange();
  }

  function handlePresetChange() {
    const preset = document.getElementById("wizStudentPreset")?.value || "";
    const panel  = document.getElementById("wizCustomStudentPanel");
    if (panel) panel.style.display = preset === "custom" ? "" : "none";
    if (preset === "custom") renderWizStudentTable();
  }

  function renderWizStudentTable() {
    const tbody = document.querySelector("#wizStudentTable tbody");
    if (!tbody) return;
    const q    = String(document.getElementById("wizStudentSearch")?.value || "").toLowerCase();
    const year = Number(document.getElementById("wizStudentFilterYear")?.value || 0);

    const filtered = students.filter((s) => {
      if (year > 0 && Number(s.year_level) !== year) return false;
      const hay = [s.full_name, s.email, s.student_code]
        .map((v) => String(v || "").toLowerCase()).join(" ");
      return !q || hay.includes(q);
    });

    if (!filtered.length) {
      tbody.innerHTML = '<tr><td colspan="3" class="muted" style="padding:10px;">No students found.</td></tr>';
      return;
    }

    tbody.innerHTML = "";
    for (const s of filtered) {
      const cur     = Number(s.year_level) || 1;
      const next    = Math.min(cur + 1, 3);
      const isFinal = cur >= 3;
      const tr      = document.createElement("tr");
      tr.innerHTML  = `
        <td>
          <div style="font-weight:500;">${escapeHtml(s.full_name || "")}</div>
          <div class="muted" style="font-size:0.82rem;">${escapeHtml(s.student_code || s.email || "")}</div>
        </td>
        <td>Year ${cur}</td>
        <td>
          <select class="navlink" data-wiz-action="select" data-id="${s.student_id}" style="padding:5px 8px;">
            <option value="advance" ${!isFinal ? "selected" : ""}>→ Year ${next}</option>
            <option value="repeat">Stay Year ${cur}</option>
            <option value="graduate" ${isFinal ? "selected" : ""}>Graduate</option>
          </select>
        </td>`;
      tbody.appendChild(tr);
    }
  }

  function collectWizStudentActions() {
    const preset = document.getElementById("wizStudentPreset")?.value || "advance_except_final";
    if (preset !== "custom") return { preset };

    const advance = [], repeat = [], graduate = [];
    document.querySelectorAll("#wizStudentTable tbody tr").forEach((row) => {
      const sel    = row.querySelector("select[data-wiz-action='select']");
      const id     = Number(sel?.dataset?.id || 0);
      if (!id) return;
      const action  = sel?.value || "advance";
      const student = studentMap.get(id);
      const cur     = Number(student?.year_level || 1);
      const next    = Math.min(cur + 1, 3);
      if (action === "advance")  advance.push({ student_id: id, year_level: next });
      else if (action === "repeat")   repeat.push(id);
      else if (action === "graduate") graduate.push(id);
    });
    return { advance, repeat, graduate };
  }

  // ── Step 3b: Year advance confirm ─────────────────────────────────────────────
  function goToStep3b() {
    const preset = document.getElementById("wizStudentPreset")?.value || "advance_except_final";
    const presetLabels = {
      advance_except_final: "Advance all, graduate final year",
      advance_all:          "Advance everyone by one year",
      repeat_all:           "Keep everyone in their current year",
      custom:               "Per-student (custom)",
    };

    const summary = document.getElementById("wizStep3bSummary");
    if (summary) {
      summary.innerHTML = buildSummaryHtml([
        ["Week 1 starts",  wizStartDate],
        ["Action",         "Close current year, create next academic year"],
        ["Weeks",          "Reset to Week 1"],
        ["Students",       presetLabels[preset] || preset],
      ]);
    }
    setStatusById("wizStep3bStatus", "");
    showStep("wizStep3b");
  }

  async function handleStep3bConfirm() {
    const btn = document.getElementById("wizStep3bConfirm");
    if (btn) btn.disabled = true;

    try {
      setStatusById("wizStep3bStatus", "Advancing…");
      const actions = collectWizStudentActions();
      const preset  = actions.preset;

      let body;
      if (preset === "repeat_all") {
        body = new URLSearchParams({
          start_date:      wizStartDate,
          advance_mode:    "custom",
          student_actions: JSON.stringify({ advance: [], repeat: students.map((s) => Number(s.student_id)), graduate: [] }),
        });
      } else if (preset === "advance_all") {
        const allAdvance = students.map((s) => ({
          student_id: Number(s.student_id),
          year_level: Math.min((Number(s.year_level) || 1) + 1, 3),
        }));
        body = new URLSearchParams({
          start_date:      wizStartDate,
          advance_mode:    "custom",
          student_actions: JSON.stringify({ advance: allAdvance, repeat: [], graduate: [] }),
        });
      } else if (preset === "custom") {
        body = new URLSearchParams({
          start_date:      wizStartDate,
          advance_mode:    "custom",
          student_actions: JSON.stringify(actions),
        });
      } else {
        // advance_except_final — auto mode
        body = new URLSearchParams({ start_date: wizStartDate, advance_mode: "auto" });
      }

      const res = await fetchJson("php/advance_term.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body,
      });
      if (!res.success) throw new Error(res.error || "Advance failed.");

      students = [];
      setStatusById("wizStep0Status", "New academic year started. Week 1 has been created and students have been updated.", "success");
      resetToStep0();
      document.dispatchEvent(new CustomEvent("dmportal:terms-updated"));
    } catch (err) {
      setStatusById("wizStep3bStatus", err.message || "Advance failed.", "error");
    } finally {
      if (btn) btn.disabled = false;
    }
  }

  // ── Listen for active term ────────────────────────────────────────────────────
  document.addEventListener("dmportal:active-term-loaded", (e) => {
    activeTerm = e.detail?.activeTerm || null;
    updateHeader();
    updateStep0();
  });

  // ── Wire up ───────────────────────────────────────────────────────────────────
  function init() {
    document.getElementById("wizPrimaryBtn")?.addEventListener("click", goToStep1);

    document.getElementById("wizStep1Next")?.addEventListener("click", handleStep1Next);
    document.getElementById("wizStep1Cancel")?.addEventListener("click", resetToStep0);
    document.getElementById("wizStartDate")?.addEventListener("keydown", (e) => {
      if (e.key === "Enter") handleStep1Next();
    });

    document.getElementById("wizStep2aConfirm")?.addEventListener("click", handleStep2aConfirm);
    document.getElementById("wizStep2aBack")?.addEventListener("click", goToStep1);

    document.getElementById("wizStudentPreset")?.addEventListener("change", handlePresetChange);
    document.getElementById("wizStudentSearch")?.addEventListener("input", renderWizStudentTable);
    document.getElementById("wizStudentFilterYear")?.addEventListener("change", renderWizStudentTable);
    document.getElementById("wizStep2bNext")?.addEventListener("click", goToStep3b);
    document.getElementById("wizStep2bBack")?.addEventListener("click", goToStep1);
    document.getElementById("wizStep2bCancel")?.addEventListener("click", resetToStep0);

    document.getElementById("wizStep3bConfirm")?.addEventListener("click", handleStep3bConfirm);
    document.getElementById("wizStep3bBack")?.addEventListener("click", goToStep2b);

    updateHeader();
    updateStep0();
  }

  init();
})();
