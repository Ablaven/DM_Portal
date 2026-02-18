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

  const statusId = "termStatus";

  const state = {
    terms: [],
    years: [],
    activeYearId: null,
    selectedYearId: null,
  };

  function renderTerms() {
    const tbody = document.querySelector("#termsTable tbody");
    if (!tbody) return;
    tbody.innerHTML = "";

    const filtered = state.selectedYearId
      ? state.terms.filter((term) => Number(term.academic_year_id) === Number(state.selectedYearId))
      : state.terms;

    filtered.forEach((term) => {
      const tr = document.createElement("tr");
      const isActive = term.status === "active";
      tr.innerHTML = `
        <td>${escapeHtml(term.label || "")}</td>
        <td>${escapeHtml(term.semester)}</td>
        <td>${escapeHtml(term.status)}</td>
        <td>${escapeHtml(term.start_date || "")}</td>
        <td>${escapeHtml(term.end_date || "")}</td>
        <td>
          <button class="btn btn-secondary" data-action="activate" data-id="${term.term_id}" ${isActive ? "disabled" : ""}>Activate</button>
          <button class="btn btn-secondary" data-action="reset" data-id="${term.term_id}">Reset Weeks</button>
        </td>
      `;
      tbody.appendChild(tr);
    });
  }

  async function loadTerms() {
    const res = await fetchJson("php/get_terms.php");
    state.terms = res.data || [];
    renderTerms();
    renderTermSelect();
  }

  async function loadAcademicYears() {
    const res = await fetchJson("php/get_academic_years.php");
    state.years = res.data || [];
    state.activeYearId = res.active_academic_year_id || null;
    if (!state.selectedYearId) {
      state.selectedYearId = state.activeYearId || (state.years[0] ? Number(state.years[0].academic_year_id) : null);
    }
    renderAcademicYearSelect();
  }

  function renderAcademicYearSelect() {
    const select = document.getElementById("academicYearSelect");
    if (!select) return;
    select.innerHTML = "";

    if (!state.years.length) {
      const opt = document.createElement("option");
      opt.value = "";
      opt.textContent = "No academic years";
      opt.disabled = true;
      opt.selected = true;
      select.appendChild(opt);
      return;
    }

    for (const year of state.years) {
      const opt = document.createElement("option");
      opt.value = String(year.academic_year_id);
      const statusTag = year.status === "active" ? " (active)" : "";
      opt.textContent = `${year.label}${statusTag}`;
      if (Number(year.academic_year_id) === Number(state.selectedYearId)) {
        opt.selected = true;
      }
      select.appendChild(opt);
    }
  }

  function renderTermSelect() {
    const select = document.getElementById("termSelect");
    if (!select) return;
    select.innerHTML = "";

    const filtered = state.selectedYearId
      ? state.terms.filter((term) => Number(term.academic_year_id) === Number(state.selectedYearId))
      : state.terms;

    if (!filtered.length) {
      const opt = document.createElement("option");
      opt.value = "";
      opt.textContent = "No terms";
      opt.disabled = true;
      opt.selected = true;
      select.appendChild(opt);
      return;
    }

    for (const term of filtered) {
      const opt = document.createElement("option");
      opt.value = String(term.term_id);
      const statusTag = term.status === "active" ? " (active)" : "";
      const yearTag = term.academic_year_label ? ` — ${term.academic_year_label}` : "";
      opt.textContent = `${term.label}${yearTag}${statusTag}`;
      if (term.status === "active") {
        opt.selected = true;
      }
      select.appendChild(opt);
    }
  }

  document.addEventListener("dmportal:terms-updated", () => {
    loadTerms();
  });

  async function handleCreate(e) {
    e.preventDefault();
    const form = e.currentTarget;
    const payload = new FormData(form);
    const label = String(payload.get("label") || "").trim();
    const semester = String(payload.get("semester") || "").trim();
    if (!label || !semester) {
      setStatusById(statusId, "Label and semester are required.", "error");
      return;
    }

    try {
      await fetchJson("php/create_term.php", { method: "POST", body: payload });
      setStatusById(statusId, "Term created successfully.", "success");
      form.reset();
      await loadTerms();
    } catch (err) {
      setStatusById(statusId, err.message || "Failed to create term.", "error");
    }
  }

  async function handleTableClick(e) {
    const btn = e.target.closest("button[data-action]");
    if (!btn) return;

    const action = btn.dataset.action;
    const termId = Number(btn.dataset.id || 0);
    if (!termId) return;

    if (action === "activate") {
      try {
        await fetchJson("php/activate_term.php", {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          body: new URLSearchParams({ term_id: termId }),
        });
        setStatusById(statusId, "Term activated.", "success");
        await loadTerms();
      } catch (err) {
        setStatusById(statusId, err.message || "Failed to activate term.", "error");
      }
    }

    if (action === "reset") {
      const startDate = prompt("Start date for the new Week 1 (YYYY-MM-DD)", "");
      if (startDate === null) return;
      try {
        await fetchJson("php/reset_term_weeks.php", {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          body: new URLSearchParams({ term_id: termId, start_date: startDate.trim() }),
        });
        setStatusById(statusId, "Weeks reset and Week 1 created.", "success");
        await loadTerms();
      } catch (err) {
        setStatusById(statusId, err.message || "Failed to reset weeks.", "error");
      }
    }
  }

  function init() {
    const form = document.getElementById("termCreateForm");
    if (form) {
      form.addEventListener("submit", handleCreate);
    }
    const table = document.getElementById("termsTable");
    if (table) {
      table.addEventListener("click", handleTableClick);
    }

    document.getElementById("academicYearSelect")?.addEventListener("change", (e) => {
      state.selectedYearId = Number(e.target.value || 0) || null;
      renderTerms();
      renderTermSelect();
    });

    document.getElementById("activateSelectedTerm")?.addEventListener("click", async () => {
      const select = document.getElementById("termSelect");
      if (!select) return;
      const termId = Number(select.value || 0);
      if (!termId) return;
      try {
        await fetchJson("php/activate_term.php", {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          body: new URLSearchParams({ term_id: termId }),
        });
        setStatusById(statusId, "Term activated.", "success");
        await loadTerms();
      } catch (err) {
        setStatusById(statusId, err.message || "Failed to activate term.", "error");
      }
    });

    loadAcademicYears().then(loadTerms);
  }

  init();
})();
