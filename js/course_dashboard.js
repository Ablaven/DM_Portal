(function () {
  "use strict";

  const {
    fetchJson,
    setStatusById,
    escapeHtml,
    formatHours,
    getEffectivePageFilters,
    applyGlobalFiltersToCourses,
    initPageFiltersUI,
    getGlobalFilters,
  } = window.dmportal || {};

  const state = { courses: [] };

  async function loadCourses() {
    const payload = await fetchJson("php/get_courses.php");
    if (!payload.success) throw new Error(payload.error || "Failed to load courses");
    state.courses = payload.data || [];
  }

  // get_courses.php returns: total_hours, remaining_hours
  function computeCourseDoneHours(course) {
    const total = Number(course?.total_hours || 0);
    const remaining = Number(course?.remaining_hours || 0);
    const done = total - remaining;
    return {
      total:     Number.isFinite(total)     && total     > 0 ? total     : 0,
      remaining: Number.isFinite(remaining) && remaining > 0 ? remaining : 0,
      done:      Number.isFinite(done)      && done      > 0 ? done      : 0,
    };
  }

  function getDashboardCoursesSorted(courses) {
    const filtered = applyGlobalFiltersToCourses(courses || []);
    filtered.sort((a, b) => {
      const ya = Number(a.year_level || 0), yb = Number(b.year_level || 0);
      if (ya !== yb) return ya - yb;
      const sa = Number(a.semester || 0), sb = Number(b.semester || 0);
      if (sa !== sb) return sa - sb;
      return String(a.course_name || "").localeCompare(String(b.course_name || ""));
    });
    return filtered;
  }

  function prepareCanvas2d(canvas, { minW = 260, minH = 200 } = {}) {
    const dpr = window.devicePixelRatio || 1;
    const rect = canvas.getBoundingClientRect();
    const cssWidth  = Math.max(minW, Math.floor(rect.width  || 0));
    const cssHeight = Math.max(minH, Math.floor(rect.height || 0));
    canvas.width  = Math.floor(cssWidth  * dpr);
    canvas.height = Math.floor(cssHeight * dpr);
    const ctx = canvas.getContext("2d");
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    return { ctx, w: cssWidth, h: cssHeight };
  }

  function getDashboardPalette() {
    const s = getComputedStyle(document.documentElement);
    const successChart = s.getPropertyValue("--success-chart-rgb").trim();
    const dangerChart  = s.getPropertyValue("--danger-chart-rgb").trim();
    const success = s.getPropertyValue("--success-rgb").trim();
    const danger  = s.getPropertyValue("--danger-rgb").trim();
    const accent  = s.getPropertyValue("--accent-rgb").trim();
    return {
      done:     `rgba(${successChart || success || "0,220,140"}, 0.92)`,
      remain:   `rgba(${dangerChart  || danger  || "239,65,53"}, 0.88)`,
      assigned: "rgba(99,102,241,0.70)",
      accent:   `rgba(${accent || "0,204,255"}, 0.82)`,
      grid:      s.getPropertyValue("--card-border").trim()  || "rgba(255,255,255,0.10)",
      text:      s.getPropertyValue("--text-dark").trim()    || s.getPropertyValue("--text").trim()    || "#ffffff",
      muted:     s.getPropertyValue("--muted-dark").trim()   || s.getPropertyValue("--muted").trim()   || "rgba(255,255,255,0.65)",
      track:     s.getPropertyValue("--track-dark").trim()   || s.getPropertyValue("--surface-3").trim() || "rgba(0,0,0,0.22)",
    };
  }

  // ---------- arc text helpers ----------
  function fitFontToArc(ctx, text, radius, arcSpan, baseSize) {
    const maxWidth = Math.max(0, arcSpan * Math.max(1, radius) * 0.92);
    let size = baseSize;
    ctx.font = `700 ${size}px system-ui,-apple-system,Segoe UI,Roboto,Arial`;
    while (ctx.measureText(text).width > maxWidth && size > 9) {
      size--;
      ctx.font = `700 ${size}px system-ui,-apple-system,Segoe UI,Roboto,Arial`;
    }
    return size;
  }

  function drawTextAlongArc(ctx, text, cx, cy, radius, startAngle, endAngle, { color = "#000", baseSize = 13 } = {}) {
    if (!text) return;
    let span = endAngle - startAngle;
    if (span < 0) span += Math.PI * 2;
    if (span < 0.35) return;
    const size = fitFontToArc(ctx, text, radius, span, baseSize);
    ctx.font = `700 ${size}px system-ui,-apple-system,Segoe UI,Roboto,Arial`;
    const glyphs = Array.from(String(text));
    const widths = glyphs.map(g => ctx.measureText(g).width);
    const totalW = widths.reduce((s, w) => s + w, 0);
    const textAngle = totalW / Math.max(1, radius);
    let cur = startAngle + span / 2 - textAngle / 2;
    ctx.fillStyle = color;
    ctx.textBaseline = "middle";
    ctx.textAlign = "center";
    glyphs.forEach((g, i) => {
      const w = widths[i];
      const angle = cur + w / (2 * Math.max(1, radius));
      ctx.save();
      ctx.translate(cx, cy);
      ctx.rotate(angle + Math.PI / 2);
      ctx.fillText(g, 0, -radius);
      ctx.restore();
      cur += w / Math.max(1, radius);
    });
  }

  // ---------- DONUT ----------
  // Two arcs: purple (assigned) + green (done) drawn over a grey track.
  function drawCourseDashboardDonut(courses) {
    const canvas = document.getElementById("courseDashboardDonut");
    if (!canvas) return;
    const { ctx, w, h } = prepareCanvas2d(canvas, { minW: 260, minH: 200 });
    const C = getDashboardPalette();

    const items = getDashboardCoursesSorted(courses || []);
    const totals = items.reduce((acc, c) => {
      const { total, done } = computeCourseDoneHours(c);
      acc.total    += total;
      acc.assigned += Number(c.assigned_hours || 0);
      acc.done     += done;
      return acc;
    }, { total: 0, assigned: 0, done: 0 });

    ctx.clearRect(0, 0, w, h);

    const total    = totals.total    || 0;
    const assigned = Math.min(totals.assigned || 0, total);
    const done     = totals.done     || 0;
    const assignedPct = total > 0 ? Math.max(0, Math.min(1, assigned / total)) : 0;
    const donePct     = total > 0 ? Math.max(0, Math.min(1, done     / total)) : 0;

    const cx = w / 2, cy = h / 2;
    const r  = Math.min(w, h) * 0.36;
    const thick = Math.max(10, r * 0.28);
    const start = -Math.PI / 2;

    // Track (grey background ring)
    ctx.beginPath();
    ctx.strokeStyle = C.track;
    ctx.lineWidth = thick;
    ctx.arc(cx, cy, r, 0, Math.PI * 2);
    ctx.stroke();

    // Assigned arc (purple, drawn first / behind)
    if (assignedPct > 0) {
      ctx.beginPath();
      ctx.strokeStyle = C.assigned;
      ctx.lineCap = "round";
      ctx.lineWidth = thick;
      ctx.arc(cx, cy, r, start, start + Math.PI * 2 * assignedPct);
      ctx.stroke();
    }

    // Done arc (green, drawn on top of assigned)
    if (donePct > 0) {
      ctx.beginPath();
      ctx.strokeStyle = C.done;
      ctx.lineCap = "round";
      ctx.lineWidth = thick;
      ctx.arc(cx, cy, r, start, start + Math.PI * 2 * donePct);
      ctx.stroke();
    }

    // Inner text
    ctx.fillStyle = C.text;
    ctx.textAlign = "center";
    ctx.textBaseline = "middle";
    ctx.font = "700 20px system-ui,-apple-system,Segoe UI,Roboto,Arial";
    ctx.fillText(`${Math.round(donePct * 100)}%`, cx, cy - 6);
    ctx.fillStyle = C.muted;
    ctx.font = "12px system-ui,-apple-system,Segoe UI,Roboto,Arial";
    ctx.fillText(`${formatHours(done)}h / ${formatHours(total)}h`, cx, cy + 16);

    const t = document.getElementById("courseDashboardDonutText");
    if (t) {
      if (total > 0) {
        t.innerHTML = `
          <span class="badge" style="background:transparent;border:1px solid #6366f1;color:#6366f1;margin-right:6px;">Assigned ${formatHours(assigned)}h</span>
          <span class="badge badge-success" style="margin-right:6px;">Done ${formatHours(done)}h</span>
          <span class="badge badge-danger">Remaining ${formatHours(Math.max(0, total - done))}h</span>
        `;
      } else {
        t.textContent = "No course hours yet.";
      }
    }
  }

  // ---------- MISSIONNAIRE PIE ----------
  async function drawMissionnairePieChart() {
    const canvas = document.getElementById("missionnairePie");
    if (!canvas) return;
    const { ctx, w, h } = prepareCanvas2d(canvas, { minW: 260, minH: 220 });
    const C = getDashboardPalette();
    ctx.clearRect(0, 0, w, h);

    const f = getGlobalFilters();
    const qs = new URLSearchParams();
    if (f?.year_level) qs.set("year_level", String(f.year_level));
    if (f?.semester)   qs.set("semester",   String(f.semester));

    let egyptianName = "Egyptian", egyptianTotal = 0;
    let frenchName   = "French",   frenchTotal   = 0;

    try {
      const url = "php/get_missionnaire_hours_pie.php" + (qs.toString() ? `?${qs.toString()}` : "");
      const payload = await fetchJson(url);
      if (!payload?.success) throw new Error(payload?.error || "Failed");
      const egyptian = payload?.data?.egyptian;
      const french   = payload?.data?.french;
      if (egyptian && french) {
        egyptianName  = String(egyptian?.label || "Egyptian");
        egyptianTotal = Number(egyptian?.total_hours || 0);
        frenchName    = String(french?.label || "French");
        frenchTotal   = Number(french?.total_hours || 0);
      } else {
        for (const d of (Array.isArray(payload?.data?.doctors) ? payload.data.doctors : [])) {
          const tot = Number(d?.total_hours || 0);
          if (!tot) continue;
          if (String(d?.doctor_type || "").toLowerCase() === "french") frenchTotal += tot;
          else egyptianTotal += tot;
        }
      }
    } catch {
      ctx.fillStyle = C.muted;
      ctx.font = "14px system-ui,-apple-system,Segoe UI,Roboto,Arial";
      ctx.fillText("Failed to load chart.", 12, 22);
      const t = document.getElementById("missionnairePieText");
      if (t) t.textContent = "";
      return;
    }

    egyptianTotal = Math.max(0, egyptianTotal);
    frenchTotal   = Math.max(0, frenchTotal);
    const total = egyptianTotal + frenchTotal;

    if (total <= 0) {
      ctx.fillStyle = C.muted;
      ctx.font = "14px system-ui,-apple-system,Segoe UI,Roboto,Arial";
      ctx.fillText("No course hours found.", 12, 22);
      const t = document.getElementById("missionnairePieText");
      if (t) t.textContent = "";
      return;
    }

    const cx = w / 2, cy = h / 2, r = Math.min(w, h) * 0.38;
    const startAngle = -Math.PI / 2;
    const egyptianPct = egyptianTotal / total;
    const aEgyptianEnd = startAngle + Math.PI * 2 * egyptianPct;

    ctx.beginPath(); ctx.moveTo(cx, cy); ctx.fillStyle = C.accent;
    ctx.arc(cx, cy, r, startAngle, aEgyptianEnd); ctx.closePath(); ctx.fill();

    ctx.beginPath(); ctx.moveTo(cx, cy); ctx.fillStyle = C.done;
    ctx.arc(cx, cy, r, aEgyptianEnd, startAngle + Math.PI * 2); ctx.closePath(); ctx.fill();

    ctx.strokeStyle = C.grid; ctx.lineWidth = 1;
    ctx.beginPath(); ctx.arc(cx, cy, r, 0, Math.PI * 2); ctx.stroke();

    drawTextAlongArc(ctx, `${egyptianName} ${Math.round(egyptianPct * 100)}%`, cx, cy, r * 0.68, startAngle, aEgyptianEnd, { color: "#000", baseSize: 13 });
    drawTextAlongArc(ctx, `${frenchName} ${Math.round((1 - egyptianPct) * 100)}%`, cx, cy, r * 0.68, aEgyptianEnd, startAngle + Math.PI * 2, { color: "#000", baseSize: 13 });

    const t = document.getElementById("missionnairePieText");
    if (t) {
      t.innerHTML = `
        <div class="badge" style="display:inline-block;margin-bottom:8px;background:var(--surface-1);border:1px solid var(--card-border);color:var(--text);">Total ${formatHours(total)}h</div>
        <div style="margin-top:4px;">
          <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;padding:4px 0;border-top:1px solid var(--card-border);">
            <div style="display:flex;align-items:center;gap:8px;min-width:0;">
              <span style="width:10px;height:10px;border-radius:2px;background:${C.accent};flex:0 0 auto;"></span>
              <span style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${escapeHtml(egyptianName)}</span>
            </div>
            <div style="white-space:nowrap;">${formatHours(egyptianTotal)}h</div>
          </div>
          <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;padding:4px 0;border-top:1px solid var(--card-border);">
            <div style="display:flex;align-items:center;gap:8px;min-width:0;">
              <span style="width:10px;height:10px;border-radius:2px;background:${C.done};flex:0 0 auto;"></span>
              <span style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${escapeHtml(frenchName)}</span>
            </div>
            <div style="white-space:nowrap;">${formatHours(frenchTotal)}h</div>
          </div>
        </div>
      `;
    }
  }

  // ---------- EGYPTIAN / FRENCH DONE vs REMAINING pies ----------
  // These use assigned_hours from the API. The API (get_doctor_type_hours_summary.php)
  // needs to return assigned_hours — if it doesn't yet, we show Done/Remaining as before
  // and add Assigned to the legend text only.
  async function drawDoctorTypeHoursCharts() {
    const egyptCanvas = document.getElementById("dashboardEgyptianHours");
    const frenchCanvas = document.getElementById("dashboardFrenchHours");
    if (!egyptCanvas || !frenchCanvas) return;

    const egyptCtx  = prepareCanvas2d(egyptCanvas,  { minW: 260, minH: 200 });
    const frenchCtx = prepareCanvas2d(frenchCanvas, { minW: 260, minH: 200 });
    const palette   = getDashboardPalette();

    egyptCtx.ctx.clearRect(0, 0, egyptCtx.w, egyptCtx.h);
    frenchCtx.ctx.clearRect(0, 0, frenchCtx.w, frenchCtx.h);

    const f = getGlobalFilters();
    const qs = new URLSearchParams();
    if (f?.year_level) qs.set("year_level", String(f.year_level));
    if (f?.semester)   qs.set("semester",   String(f.semester));

    try {
      const url = "php/get_doctor_type_hours_summary.php" + (qs.toString() ? `?${qs.toString()}` : "");
      const payload = await fetchJson(url);
      if (!payload?.success) throw new Error(payload?.error || "Failed to load summary");

      const egypt  = payload?.data?.egyptian || { label: "Egyptian", done_hours: 0, remaining_hours: 0, assigned_hours: 0 };
      const french = payload?.data?.french   || { label: "French",   done_hours: 0, remaining_hours: 0, assigned_hours: 0 };

      const charts = [
        { type: "Egyptian", data: egypt,  canvas: egyptCtx,  labelId: "dashboardEgyptianHoursText" },
        { type: "French",   data: french, canvas: frenchCtx, labelId: "dashboardFrenchHoursText"   },
      ];

      charts.forEach(({ data, canvas, labelId, type }) => {
        const done      = Number(data?.done_hours      || 0);
        const remaining = Number(data?.remaining_hours || 0);
        const assigned  = Number(data?.assigned_hours  || 0);
        const total     = done + remaining;
        const { ctx, w, h } = canvas;

        if (total <= 0) {
          ctx.fillStyle = palette.muted;
          ctx.font = "14px system-ui,-apple-system,Segoe UI,Roboto,Arial";
          ctx.fillText("No hours yet.", 12, 22);
          const t = document.getElementById(labelId);
          if (t) t.textContent = "";
          return;
        }

        const cx = w / 2, cy = h / 2 + 4;
        const r = Math.min(w, h) * 0.32;
        const startAngle  = -Math.PI / 2;
        const assignedPct = total > 0 ? Math.min(1, assigned / total) : 0;
        const donePct     = total > 0 ? done / total : 0;
        const aAssigned   = startAngle + Math.PI * 2 * assignedPct;
        const aDone       = startAngle + Math.PI * 2 * donePct;

        // Draw 3 slices: Done | Assigned-only (assigned > done) | Remaining
        // Simplest correct approach: draw remaining (full remaining arc), then
        // assigned arc on top, then done arc on top of assigned.

        // Full remaining slice (background for the whole pie first)
        ctx.beginPath(); ctx.moveTo(cx, cy);
        ctx.fillStyle = palette.remain;
        ctx.arc(cx, cy, r, startAngle, startAngle + Math.PI * 2);
        ctx.closePath(); ctx.fill();

        // Assigned slice (purple) — from start up to assigned%
        if (assignedPct > 0) {
          ctx.beginPath(); ctx.moveTo(cx, cy);
          ctx.fillStyle = palette.assigned;
          ctx.arc(cx, cy, r, startAngle, aAssigned);
          ctx.closePath(); ctx.fill();
        }

        // Done slice (green) — from start up to done%, overlaps assigned
        if (donePct > 0) {
          ctx.beginPath(); ctx.moveTo(cx, cy);
          ctx.fillStyle = palette.done;
          ctx.arc(cx, cy, r, startAngle, aDone);
          ctx.closePath(); ctx.fill();
        }

        ctx.strokeStyle = palette.grid; ctx.lineWidth = 1;
        ctx.beginPath(); ctx.arc(cx, cy, r, 0, Math.PI * 2); ctx.stroke();

        // Labels — Done slice, Assigned-only slice (between done and assigned), Remaining slice
        const doneLabel     = donePct > 0.05 ? `${Math.round(donePct * 100)}% Done` : "";
        const assignedLabel = (assignedPct > donePct && (assignedPct - donePct) > 0.05) ? `${Math.round((assignedPct - donePct) * 100)}% Assign.` : "";
        const remainLabel   = (1 - donePct) > 0.05 ? `${Math.round((1 - donePct) * 100)}% Rem.` : "";
        drawTextAlongArc(ctx, doneLabel,     cx, cy, r * 0.68, startAngle, aDone,                   { color: "#000", baseSize: 12 });
        drawTextAlongArc(ctx, assignedLabel, cx, cy, r * 0.68, aDone,     aAssigned,                { color: "#000", baseSize: 12 });
        drawTextAlongArc(ctx, remainLabel,   cx, cy, r * 0.68, aAssigned, startAngle + Math.PI * 2, { color: "#000", baseSize: 12 });

        const t = document.getElementById(labelId);
        if (t) {
          const pct = Math.round(donePct * 100);
          t.innerHTML = `
            <div class="badge" style="display:inline-block;margin-bottom:8px;background:var(--surface-1);border:1px solid var(--card-border);color:var(--text);">Total ${formatHours(total)}h</div>
            <div style="margin-top:4px;">
              <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;padding:4px 0;border-top:1px solid var(--card-border);">
                <div style="display:flex;align-items:center;gap:8px;min-width:0;">
                  <span style="width:10px;height:10px;border-radius:2px;background:rgba(99,102,241,0.70);flex:0 0 auto;"></span>
                  <span>Assigned</span>
                </div>
                <div style="white-space:nowrap;">${formatHours(assigned)}h</div>
              </div>
              <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;padding:4px 0;border-top:1px solid var(--card-border);">
                <div style="display:flex;align-items:center;gap:8px;min-width:0;">
                  <span style="width:10px;height:10px;border-radius:2px;background:${palette.done};flex:0 0 auto;"></span>
                  <span>Done</span>
                </div>
                <div style="white-space:nowrap;">${formatHours(done)}h</div>
              </div>
              <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;padding:4px 0;border-top:1px solid var(--card-border);">
                <div style="display:flex;align-items:center;gap:8px;min-width:0;">
                  <span style="width:10px;height:10px;border-radius:2px;background:${palette.remain};flex:0 0 auto;"></span>
                  <span>Remaining</span>
                </div>
                <div style="white-space:nowrap;">${formatHours(remaining)}h</div>
              </div>
              <div class="muted" style="margin-top:4px;">${pct}% done (${type})</div>
            </div>
          `;
        }
      });
    } catch {
      [
        { canvas: egyptCtx,  labelId: "dashboardEgyptianHoursText" },
        { canvas: frenchCtx, labelId: "dashboardFrenchHoursText" },
      ].forEach(({ canvas, labelId }) => {
        canvas.ctx.fillStyle = palette.muted;
        canvas.ctx.font = "14px system-ui,-apple-system,Segoe UI,Roboto,Arial";
        canvas.ctx.fillText("Failed to load.", 12, 22);
        const t = document.getElementById(labelId);
        if (t) t.textContent = "";
      });
    }
  }

  // ---------- BAR CHART ----------
  // Each bar from bottom to top: Done (green) | Assigned-only (purple, assigned > done) | Remaining (red at top)
  function drawCourseDashboardChart(courses) {
    const canvas = document.getElementById("courseDashboardChart");
    if (!canvas) return;
    const { ctx, w, h } = prepareCanvas2d(canvas, { minW: 520, minH: 320 });
    const C = getDashboardPalette();
    const padding = { top: 18, right: 18, bottom: 92, left: 52 };

    function truncateToWidth(text, maxPx) {
      const raw = String(text || "").trim();
      if (!raw) return "";
      if (ctx.measureText(raw).width <= maxPx) return raw;
      const ell = "\u2026";
      let lo = 0, hi = raw.length;
      while (lo < hi) {
        const mid = Math.ceil((lo + hi) / 2);
        const candidate = raw.slice(0, mid) + ell;
        if (ctx.measureText(candidate).width <= maxPx) lo = mid;
        else hi = mid - 1;
      }
      return raw.slice(0, Math.max(0, lo)) + ell;
    }

    function drawAngledLabel(text, x, y, maxPx) {
      ctx.save();
      ctx.translate(x, y);
      ctx.rotate(-Math.PI / 4);
      ctx.textAlign = "right";
      ctx.textBaseline = "middle";
      ctx.fillText(truncateToWidth(text, maxPx), 0, 0);
      ctx.restore();
    }

    ctx.clearRect(0, 0, w, h);

    const items = getDashboardCoursesSorted(courses || []);
    if (!items.length) {
      ctx.fillStyle = C.muted;
      ctx.font = "14px system-ui,-apple-system,Segoe UI,Roboto,Arial";
      ctx.fillText("No courses for selected filters.", padding.left, padding.top + 20);
      return;
    }

    const maxTotal = Math.max(1, ...items.map(c => computeCourseDoneHours(c).total));
    const chartW = w - padding.left - padding.right;
    const chartH = h - padding.top  - padding.bottom;

    // Grid lines + Y axis labels
    ctx.strokeStyle = C.grid; ctx.lineWidth = 1;
    ctx.font = "12px system-ui,-apple-system,Segoe UI,Roboto,Arial";
    ctx.fillStyle = C.muted;
    for (let i = 0; i <= 5; i++) {
      const t = i / 5;
      const y = padding.top + chartH - t * chartH;
      ctx.beginPath(); ctx.moveTo(padding.left, y); ctx.lineTo(padding.left + chartW, y); ctx.stroke();
      ctx.fillText((t * maxTotal).toFixed(0), 10, y + 4);
    }

    // Bar sizing
    const barCount = items.length;
    let barW  = Math.max(10, Math.min(14, (chartW / Math.max(1, barCount)) * 0.65));
    let barGap = Math.max(10, Math.min(18, barW * 0.9));
    if (barCount * barW + (barCount - 1) * barGap > chartW) {
      barW   = Math.max(9, (chartW - 10 * (barCount - 1)) / barCount);
      barGap = 10;
    }
    const baseX = padding.left + Math.max(0, (chartW - barCount * barW - (barCount - 1) * barGap) / 2);

    ctx.textAlign = "center";
    ctx.textBaseline = "top";

    for (let i = 0; i < barCount; i++) {
      const c = items[i];
      const { total, done } = computeCourseDoneHours(c);
      const assigned  = Math.min(Number(c.assigned_hours || 0), total);
      const remaining = Math.max(0, total - done);

      const x = baseX + i * (barW + barGap);
      const y0 = padding.top + chartH; // bottom of chart area

      const totalH    = (total    / maxTotal) * chartH;
      const doneH     = (done     / maxTotal) * chartH;
      const assignedH = (assigned / maxTotal) * chartH;
      const remainH   = (remaining / maxTotal) * chartH;

      // Draw from bottom up: done (green) at base, assigned (purple) above done
      // if assigned > done, then remaining (red) at the very top.
      // The bar starts at y0 - totalH and goes down to y0.
      // done:     y0 - doneH  to  y0
      // assigned: y0 - assignedH  to  y0  (overlaps done, purple only visible where assigned > done)
      // remaining fills from y0 - totalH  to  y0 - doneH (the top unfilled portion)

      // 1. Remaining (red) — top section
      if (remainH > 0) {
        ctx.fillStyle = C.remain;
        ctx.fillRect(x, y0 - totalH, barW, remainH);
      }

      // 2. Assigned (purple) — from bottom, behind green
      if (assignedH > 0) {
        ctx.fillStyle = C.assigned;
        ctx.fillRect(x, y0 - assignedH, barW, assignedH);
      }

      // 3. Done (green) — from bottom, overlaps purple
      if (doneH > 0) {
        ctx.fillStyle = C.done;
        ctx.fillRect(x, y0 - doneH, barW, doneH);
      }

      ctx.fillStyle = C.text;
      ctx.font = "11px system-ui,-apple-system,Segoe UI,Roboto,Arial";
      drawAngledLabel(String(c.course_name || "").trim(), x + barW / 2, padding.top + chartH + 46, Math.max(70, barGap * 3.2));
    }

    // Axis labels
    ctx.save();
    ctx.translate(18, padding.top + chartH / 2);
    ctx.rotate(-Math.PI / 2);
    ctx.fillStyle = C.muted;
    ctx.font = "12px system-ui,-apple-system,Segoe UI,Roboto,Arial";
    ctx.textAlign = "center";
    ctx.fillText("Hours", 0, 0);
    ctx.restore();

    ctx.fillStyle = C.muted;
    ctx.font = "11px system-ui,-apple-system,Segoe UI,Roboto,Arial";
    ctx.textAlign = "left";
    ctx.textBaseline = "top";
    ctx.fillText("Courses", padding.left, padding.top + chartH + 8);
  }

  // ---------- PROGRESS LIST (bottom of page) ----------
  function renderCourseProgressList(courses) {
    const wrap = document.getElementById("courseDashboardList");
    if (!wrap) return;

    const filtered = applyGlobalFiltersToCourses(courses || []);
    if (!filtered.length) {
      wrap.innerHTML = `<div class="muted">No courses found for the selected filters.</div>`;
      return;
    }

    filtered.sort((a, b) => {
      const ta = Number(a.total_hours || 0), tb = Number(b.total_hours || 0);
      const ra = Number(a.remaining_hours || 0), rb = Number(b.remaining_hours || 0);
      const da = Math.max(0, ta - ra), db = Math.max(0, tb - rb);
      const pa = ta > 0 ? da / ta : 0, pb = tb > 0 ? db / tb : 0;
      if (pa !== pb) return pb - pa;
      if (ra !== rb) return ra - rb;
      return String(a.course_name || "").localeCompare(String(b.course_name || ""));
    });

    wrap.innerHTML = "";

    for (const c of filtered) {
      const { total, done } = computeCourseDoneHours(c);
      const assigned     = Number(c.assigned_hours || 0);
      const assignedPct  = total > 0 ? Math.max(0, Math.min(100, (assigned / total) * 100)) : 0;
      const donePct      = total > 0 ? Math.max(0, Math.min(100, (done    / total) * 100)) : 0;

      const item = document.createElement("div");
      item.className = "course-progress-item";

      const code = String(c.subject_code || "").trim();
      const codeLabel = code ? ` \u2022 ${escapeHtml(code)}` : "";

      item.innerHTML = `
        <div class="course-progress-top">
          <div>
            <div class="course-progress-title">${escapeHtml(c.course_name || "(Unnamed course)")}</div>
            <div class="course-progress-meta">${escapeHtml(c.program || "")}${codeLabel} \u2022 Year ${escapeHtml(c.year_level)} \u2022 Sem ${escapeHtml(c.semester)}</div>
          </div>
          <span class="muted">${formatHours(done)}h / ${formatHours(total)}h</span>
        </div>
        <div class="course-progress-bar" aria-label="Course progress">
          <div class="course-progress-fill-assigned" style="width:${assignedPct.toFixed(2)}%"></div>
          <div class="course-progress-fill" style="width:${donePct.toFixed(2)}%"></div>
        </div>
        <div class="course-progress-legend">
          <span class="badge" style="background:transparent;border:1px solid #6366f1;color:#6366f1;">Assigned: ${formatHours(assigned)}h</span>
          <span class="badge badge-success">Done: ${formatHours(done)}h</span>
          <span class="badge badge-danger">Remaining: ${formatHours(Math.max(0, total - done))}h</span>
          <span class="muted">${donePct.toFixed(0)}%</span>
        </div>
      `;

      wrap.appendChild(item);
    }
  }

  function redrawAllDashboardCharts() {
    drawCourseDashboardDonut(state.courses || []);
    drawCourseDashboardChart(state.courses || []);
    drawDoctorTypeHoursCharts();
    drawMissionnairePieChart();
  }

  async function initCourseDashboardPage() {
    try {
      setStatusById("courseDashboardStatus", "Loading\u2026");
      initPageFiltersUI({ yearSelectId: "dashboardYearFilter", semesterSelectId: "dashboardSemesterFilter" });
      await loadCourses();
      redrawAllDashboardCharts();
      renderCourseProgressList(state.courses || []);
      setStatusById("courseDashboardStatus", "");

      document.getElementById("refreshCourseDashboard")?.addEventListener("click", async () => {
        try {
          setStatusById("courseDashboardStatus", "Refreshing\u2026");
          await loadCourses();
          redrawAllDashboardCharts();
          renderCourseProgressList(state.courses || []);
          setStatusById("courseDashboardStatus", "");
        } catch (err) {
          setStatusById("courseDashboardStatus", err.message, "error");
        }
      });

      window.addEventListener("dmportal:pageFiltersChanged", () => {
        redrawAllDashboardCharts();
        renderCourseProgressList(state.courses || []);
      });

      let resizeT;
      window.addEventListener("resize", () => {
        clearTimeout(resizeT);
        resizeT = setTimeout(() => redrawAllDashboardCharts(), 120);
      });

      window.addEventListener("dmportal:themeChanged", () => {
        redrawAllDashboardCharts();
      });
    } catch (err) {
      setStatusById("courseDashboardStatus", err.message, "error");
    }
  }

  window.dmportal = window.dmportal || {};
  window.dmportal.initCourseDashboardPage = initCourseDashboardPage;
})();
