(function () {
  "use strict";

  const { fetchJson, escapeHtml, initPageFiltersUI, getEffectivePageFilters } = window.dmportal || {};

  async function initHoursReportPage() {
    const root = document.getElementById("hoursReportRoot");
    if (!root) return;

    const status = document.getElementById("hoursReportStatus");
    const refreshBtn = document.getElementById("hoursReportRefresh");

    function setStatus(msg, type) {
      if (!status) return;
      status.textContent = msg || "";
      status.classList.remove("success", "error");
      if (type) status.classList.add(type);
    }

    function render(doctors) {
      root.innerHTML = "";

      if (!Array.isArray(doctors) || !doctors.length) {
        root.innerHTML = '<div class="muted">No data yet. Assign doctors to courses first.</div>';
        return;
      }

      for (const d of doctors) {
        const card = document.createElement("div");
        card.className = "course-item";

        const totals = d.totals || {};
        const allocT = Number(totals.allocated_hours || 0);
        const doneT = Number(totals.done_hours || 0);
        const remT = Number(totals.remaining_hours || 0);

        const pct = allocT > 0 ? Math.min(100, Math.max(0, Math.round((doneT / allocT) * 100))) : 0;

        const rows = (d.courses || [])
          .map((c) => {
            const alloc = Number(c.allocated_hours || 0);
            const done = Number(c.done_hours || 0);
            const rem = Number(c.remaining_hours || 0);
            const label = `${escapeHtml(c.course_type || "")} ${escapeHtml(c.subject_code || "")}`.trim();
            return `
              <tr>
                <td style="padding:8px 10px; border-top:1px solid rgba(255,255,255,0.10);">
                  <div style="font-weight:800;">${label || escapeHtml(c.course_name || "(Unnamed)")}</div>
                  <div class="muted" style="font-size:0.85rem; margin-top:2px;">${escapeHtml(c.course_name || "")}</div>
                </td>
                <td style="padding:8px 10px; border-top:1px solid rgba(255,255,255,0.10); text-align:right; white-space:nowrap;">${alloc.toFixed(2)}</td>
                <td style="padding:8px 10px; border-top:1px solid rgba(255,255,255,0.10); text-align:right; white-space:nowrap;">${done.toFixed(2)}</td>
                <td style="padding:8px 10px; border-top:1px solid rgba(255,255,255,0.10); text-align:right; white-space:nowrap;">${rem.toFixed(2)}</td>
              </tr>
            `;
          })
          .join("");

        card.innerHTML = `
          <div class="course-top">
            <div>
              <div style="font-weight:900; font-size:1.05rem;">${escapeHtml(d.full_name || "")}</div>
              <div class="muted" style="margin-top:2px; font-size:0.9rem;">Doctor ID: ${escapeHtml(d.doctor_id)}</div>
            </div>
            <span class="badge badge-hours">${pct}% done</span>
          </div>

          <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:10px;">
            <span class="badge">Allocated: <strong>${allocT.toFixed(2)}</strong></span>
            <span class="badge badge-success">Done: <strong>${doneT.toFixed(2)}</strong></span>
            <span class="badge badge-danger">Remaining: <strong>${remT.toFixed(2)}</strong></span>
          </div>

          <div class="schedule-wrap" style="margin-top:10px;">
            <table style="width:100%; border-collapse:separate; border-spacing:0; min-width:520px;">
              <thead>
                <tr>
                  <th style="text-align:left; padding:8px 10px; color: rgba(232, 237, 247, 0.78);">Subject / Course</th>
                  <th style="text-align:right; padding:8px 10px; color: rgba(232, 237, 247, 0.78);">Allocated</th>
                  <th style="text-align:right; padding:8px 10px; color: rgba(232, 237, 247, 0.78);">Done</th>
                  <th style="text-align:right; padding:8px 10px; color: rgba(232, 237, 247, 0.78);">Remaining</th>
                </tr>
              </thead>
              <tbody>
                ${rows}
              </tbody>
            </table>
          </div>
        `;

        root.appendChild(card);
      }
    }

    async function load() {
      const f = getEffectivePageFilters();
      const qs = new URLSearchParams();
      if (f.year_level) qs.set("year_level", String(f.year_level));
      if (f.semester) qs.set("semester", String(f.semester));
      setStatus("Loading…");
      try {
        const url = "php/get_hours_report.php" + (qs.toString() ? `?${qs.toString()}` : "");
        const payload = await fetchJson(url);
        if (!payload?.success) throw new Error(payload?.error || "Failed to load hours report.");
        render(payload?.data?.doctors || []);
        setStatus("");
      } catch (err) {
        setStatus(err.message || "Failed to load hours report.", "error");
      }
    }

    initPageFiltersUI({
      yearSelectId: "hoursReportYearFilter",
      semesterSelectId: "hoursReportSemesterFilter",
    });

    window.addEventListener("dmportal:pageFiltersChanged", load);
    refreshBtn?.addEventListener("click", load);

    await load();
  }

  window.dmportal = window.dmportal || {};
  window.dmportal.initHoursReportPage = initHoursReportPage;
})();
