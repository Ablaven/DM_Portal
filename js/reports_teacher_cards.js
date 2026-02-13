(function () {
  "use strict";

  const { fetchJson, escapeHtml } = window.dmportal || {};

  function buildDonut(done, remaining) {
    const total = done + remaining;
    const pct = total > 0 ? Math.round((done / total) * 100) : 0;
    return `
      <div class="donut" style="--donut-progress:${pct};">
        <div class="donut-center">
          <div class="donut-value">${pct}%</div>
          <div class="donut-label">done</div>
        </div>
      </div>
      <div class="donut-legend">
        <span><span class="legend-dot legend-done"></span>${done.toFixed(1)}h Done</span>
        <span><span class="legend-dot legend-remaining"></span>${remaining.toFixed(1)}h Left</span>
        <span class="donut-total">Total ${(total).toFixed(1)}h</span>
      </div>
    `;
  }

  function renderCards(container, data) {
    if (!container) return;
    container.innerHTML = "";

    if (!Array.isArray(data) || !data.length) {
      container.innerHTML = '<div class="muted">No hours data available yet.</div>';
      return;
    }

    data.forEach((year) => {
      const card = document.createElement("div");
      card.className = "teacher-year-card";

      const sem1 = year.semesters?.[1] || { done: 0, remaining: 0 };
      const sem2 = year.semesters?.[2] || { done: 0, remaining: 0 };

      card.innerHTML = `
        <div class="teacher-year-header">
          <div>
            <div class="teacher-year-title">Year ${escapeHtml(year.year_level)}</div>
            <div class="muted">Hours done vs remaining</div>
          </div>
        </div>
        <div class="teacher-year-splits">
          <div class="teacher-sem-card">
            <div class="teacher-sem-title">Semester 1</div>
            ${buildDonut(Number(sem1.done || 0), Number(sem1.remaining || 0))}
          </div>
          <div class="teacher-sem-card">
            <div class="teacher-sem-title">Semester 2</div>
            ${buildDonut(Number(sem2.done || 0), Number(sem2.remaining || 0))}
          </div>
        </div>
      `;

      container.appendChild(card);
    });
  }

  async function loadTeacherCards() {
    const container = document.getElementById("teacherReportsCards");
    if (!container || !fetchJson) return;

    const status = document.getElementById("teacherReportsStatus");
    if (status) status.textContent = "Loading your hours...";

    try {
      const payload = await fetchJson("php/get_hours_report_semester_summary.php");
      if (!payload?.success) throw new Error(payload?.error || "Failed to load hours summary.");
      renderCards(container, payload?.data || []);
      if (status) status.textContent = "";
    } catch (err) {
      if (status) status.textContent = err.message || "Failed to load hours summary.";
    }
  }

  function initTeacherReportCards() {
    const container = document.getElementById("teacherReportsCards");
    if (!container) return;
    loadTeacherCards();
  }

  window.dmportal = window.dmportal || {};
  window.dmportal.initTeacherReportCards = initTeacherReportCards;
})();
