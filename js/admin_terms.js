(() => {
  const { fetchJson, setStatusById } = window.dmportal || {};
  if (!fetchJson) return;

  const escapeHtml = (value) => {
    const str = String(value ?? "");
    return str
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/\"/g, "&quot;")
      .replace(/'/g, "&#039;");
  };

  const state = {
    terms: [],
    years: [],
    activeYearId: null,
    selectedYearId: null,
    activeTerm: null,
  };

  // ── Reset Weeks mini-modal ──────────────────────────────────────────────────
  let resetModal = null;
  let resetPendingTermId = null;

  function getOrCreateResetModal() {
    if (resetModal) return resetModal;
    resetModal = document.createElement("div");
    resetModal.className = "modal";
    resetModal.setAttribute("aria-hidden", "true");
    resetModal.innerHTML = `
      <div class="modal-backdrop" data-reset-close="1"></div>
      <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="resetWeeksTitle" style="max-width:420px;">
        <div class="modal-header">
          <h3 id="resetWeeksTitle">Reset Weeks</h3>
          <button class="btn btn-small btn-secondary" type="button" data-reset-close="1">Close</button>
        </div>
        <div class="modal-body">
          <p class="muted" style="margin:0 0 14px;">All existing weeks for this semester will be closed and a fresh <strong>Week 1</strong> will be created.</p>
          <div class="field" style="margin:0;">
            <label for="resetWeeksStartDate">Start date for new Week 1</label>
            <input id="resetWeeksStartDate" type="date" style="width:100%; padding:8px 10px;" />
          </div>
        </div>
        <div class="modal-actions">
          <button id="resetWeeksConfirm" class="btn" type="button">Reset Weeks</button>
          <button class="btn btn-secondary" type="button" data-reset-close="1">Cancel</button>
        </div>
        <div id="resetWeeksStatus" class="status" role="status" style="margin:10px 16px 0;"></div>
      </div>
    `;
    document.body.appendChild(resetModal);
    resetModal.querySelectorAll("[data-reset-close='1']").forEach((el) => {
      el.addEventListener("click", closeResetModal);
    });
    document.getElementById("resetWeeksConfirm")?.addEventListener("click", async () => {
      if (!resetPendingTermId) return;
      const startDate = String(document.getElementById("resetWeeksStartDate")?.value || "").trim();
      const btn = document.getElementById("resetWeeksConfirm");
      if (btn) btn.disabled = true;
      try {
        setStatusById("resetWeeksStatus", "Resetting…");
        await fetchJson("php/reset_term_weeks.php", {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          body: new URLSearchParams({ term_id: resetPendingTermId, start_date: startDate }),
        });
        closeResetModal();
        setStatusById("termStatus", "Weeks reset — Week 1 created.", "success");
        await loadData();
      } catch (err) {
        setStatusById("resetWeeksStatus", err.message || "Failed to reset weeks.", "error");
      } finally {
        if (btn) btn.disabled = false;
      }
    });
    return resetModal;
  }

  function openResetModal(termId) {
    resetPendingTermId = termId;
    const m = getOrCreateResetModal();
    const dateInput = document.getElementById("resetWeeksStartDate");
    if (dateInput) dateInput.value = new Date().toISOString().slice(0, 10);
    setStatusById("resetWeeksStatus", "");
    m.classList.add("open");
    m.setAttribute("aria-hidden", "false");
    dateInput?.focus();
  }

  function closeResetModal() {
    if (!resetModal) return;
    resetModal.classList.remove("open");
    resetModal.setAttribute("aria-hidden", "true");
    resetPendingTermId = null;
  }

  // ── Data loading ────────────────────────────────────────────────────────────
  async function loadData() {
    const [termsRes, yearsRes] = await Promise.all([
      fetchJson("php/get_terms.php"),
      fetchJson("php/get_academic_years.php"),
    ]);

    state.terms = termsRes.data || [];
    state.activeTerm = termsRes.active_term || null;
    state.years = yearsRes.data || [];
    state.activeYearId = yearsRes.active_academic_year_id || null;
    if (!state.selectedYearId) {
      state.selectedYearId = state.activeYearId || (state.years[0] ? Number(state.years[0].academic_year_id) : null);
    }

    renderAcademicYearSelect();
    renderTermSelect();
    renderTermsTable();

    // Notify wizard
    document.dispatchEvent(new CustomEvent("dmportal:active-term-loaded", { detail: { activeTerm: state.activeTerm } }));
  }

  // ── Renders ─────────────────────────────────────────────────────────────────
  function renderAcademicYearSelect() {
    const select = document.getElementById("academicYearSelect");
    if (!select) return;
    select.innerHTML = "";
    if (!state.years.length) {
      select.innerHTML = '<option value="" disabled selected>No academic years</option>';
      return;
    }
    for (const year of state.years) {
      const opt = document.createElement("option");
      opt.value = String(year.academic_year_id);
      opt.textContent = year.label + (year.status === "active" ? " (active)" : "");
      if (Number(year.academic_year_id) === Number(state.selectedYearId)) opt.selected = true;
      select.appendChild(opt);
    }
  }

  function renderTermSelect() {
    const select = document.getElementById("termSelect");
    if (!select) return;
    select.innerHTML = "";
    const filtered = state.selectedYearId
      ? state.terms.filter((t) => Number(t.academic_year_id) === Number(state.selectedYearId))
      : state.terms;
    if (!filtered.length) {
      select.innerHTML = '<option value="" disabled selected>No semesters</option>';
      return;
    }
    for (const term of filtered) {
      const opt = document.createElement("option");
      opt.value = String(term.term_id);
      opt.textContent = term.label + (term.status === "active" ? " (active)" : "");
      if (term.status === "active") opt.selected = true;
      select.appendChild(opt);
    }
  }

  function renderTermsTable() {
    const tbody = document.querySelector("#termsTable tbody");
    if (!tbody) return;
    tbody.innerHTML = "";

    // Show all terms grouped — filter by selected year
    const filtered = state.selectedYearId
      ? state.terms.filter((t) => Number(t.academic_year_id) === Number(state.selectedYearId))
      : state.terms;

    if (!filtered.length) {
      tbody.innerHTML = '<tr><td colspan="5" class="muted" style="padding:12px;">No semesters found.</td></tr>';
      return;
    }

    for (const term of filtered) {
      const isActive = term.status === "active";
      const tr = document.createElement("tr");
      tr.innerHTML = `
        <td>${escapeHtml(term.label)}</td>
        <td>${escapeHtml(term.semester)}</td>
        <td>${escapeHtml(term.academic_year_label || "")}</td>
        <td><span style="font-weight:${isActive ? "700" : "400"}; color:${isActive ? "var(--color-primary, #1a6)" : "inherit"};">${escapeHtml(term.status)}</span></td>
        <td style="display:flex; gap:6px; flex-wrap:wrap;">
          <button class="btn btn-small btn-secondary" data-action="activate" data-id="${term.term_id}" ${isActive ? "disabled" : ""}>Activate</button>
          <button class="btn btn-small btn-secondary" data-action="reset" data-id="${term.term_id}">Reset Weeks</button>
        </td>
      `;
      tbody.appendChild(tr);
    }
  }

  // ── Event handlers ──────────────────────────────────────────────────────────
  document.addEventListener("dmportal:terms-updated", loadData);

  async function handleCreate(e) {
    e.preventDefault();
    const form = e.currentTarget;
    const payload = new FormData(form);
    // Label is now auto-generated server-side from the semester number.
    // Only semester number (and optional dates) need to be present.
    if (!payload.get("semester")) {
      setStatusById("createTermStatus", "Please select a semester.", "error");
      return;
    }
    try {
      await fetchJson("php/create_term.php", { method: "POST", body: payload });
      setStatusById("createTermStatus", "Semester created.", "success");
      form.reset();
      await loadData();
    } catch (err) {
      setStatusById("createTermStatus", err.message || "Failed to create semester.", "error");
    }
  }

  async function handleTableClick(e) {
    const btn = e.target.closest("button[data-action]");
    if (!btn) return;
    const action = btn.dataset.action;
    const termId = Number(btn.dataset.id || 0);
    if (!termId) return;

    if (action === "activate") {
      btn.disabled = true;
      try {
        await fetchJson("php/activate_term.php", {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          body: new URLSearchParams({ term_id: termId }),
        });
        setStatusById("termStatus", "Semester activated.", "success");
        await loadData();
      } catch (err) {
        setStatusById("termStatus", err.message || "Failed to activate.", "error");
        btn.disabled = false;
      }
    }

    if (action === "reset") openResetModal(termId);
  }

  function init() {
    document.getElementById("termCreateForm")?.addEventListener("submit", handleCreate);
    document.getElementById("termsTable")?.addEventListener("click", handleTableClick);

    document.getElementById("academicYearSelect")?.addEventListener("change", (e) => {
      state.selectedYearId = Number(e.target.value) || null;
      renderTermSelect();
      renderTermsTable();
    });

    document.getElementById("activateSelectedTerm")?.addEventListener("click", async () => {
      const termId = Number(document.getElementById("termSelect")?.value || 0);
      if (!termId) { setStatusById("termStatus", "Select a semester first.", "error"); return; }
      try {
        await fetchJson("php/activate_term.php", {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          body: new URLSearchParams({ term_id: termId }),
        });
        setStatusById("termStatus", "Semester activated.", "success");
        await loadData();
      } catch (err) {
        setStatusById("termStatus", err.message || "Failed to activate.", "error");
      }
    });

    // Wire up Manual Options toggle to open <details> programmatically
    document.getElementById("wizManualBtn")?.addEventListener("click", () => {
      const details = document.getElementById("manualOptionsPanel");
      if (details) {
        details.open = true;
        details.scrollIntoView({ behavior: "smooth", block: "start" });
      }
    });

    loadData();
  }

  init();
})();
