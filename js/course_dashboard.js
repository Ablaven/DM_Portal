(function () {
  "use strict";

  const {
    fetchJson,
    setStatusById,
    escapeHtml,
    getEffectivePageFilters,
    applyGlobalFiltersToCourses,
    initPageFiltersUI,
    getGlobalFilters,
  } = window.dmportal || {};

  const state = { courses: [] };

  function formatHours(n) {
    const num = Number(n);
    if (Number.isNaN(num)) return "0.00";
    return num.toFixed(2);
  }

  async function loadCourses() {
    const payload = await fetchJson("php/get_courses.php");
    if (!payload.success) throw new Error(payload.error || "Failed to load courses");
    state.courses = payload.data || [];
  }

  // Helpers copied from admin_courses.js (dashboard.php logic)
// Page: Course Dashboard (dashboard.php)
// -----------------------------
function computeCourseDoneHours(course) {
  // get_courses.php returns: total_hours, remaining_hours
  const total = Number(course?.total_hours || 0);
  const remaining = Number(course?.remaining_hours || 0);
  const done = total - remaining;
  return {
    total: Number.isFinite(total) && total > 0 ? total : 0,
    remaining: Number.isFinite(remaining) && remaining > 0 ? remaining : 0,
    done: Number.isFinite(done) && done > 0 ? done : 0,
  };
}

function getDashboardCoursesSorted(courses) {
  const filtered = applyGlobalFiltersToCourses(courses || []);
  filtered.sort((a, b) => {
    const ya = Number(a.year_level || 0);
    const yb = Number(b.year_level || 0);
    if (ya !== yb) return ya - yb;
    const sa = Number(a.semester || 0);
    const sb = Number(b.semester || 0);
    if (sa !== sb) return sa - sb;
    return String(a.course_name || "").localeCompare(String(b.course_name || ""));
  });
  return filtered;
}

function prepareCanvas2d(canvas, { minW = 260, minH = 200 } = {}) {
  const dpr = window.devicePixelRatio || 1;
  const rect = canvas.getBoundingClientRect();
  const cssWidth = Math.max(minW, Math.floor(rect.width || 0));
  const cssHeight = Math.max(minH, Math.floor(rect.height || 0));

  canvas.width = Math.floor(cssWidth * dpr);
  canvas.height = Math.floor(cssHeight * dpr);

  const ctx = canvas.getContext("2d");
  ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
  return { ctx, w: cssWidth, h: cssHeight };
}

function getDashboardPalette() {
  const styles = getComputedStyle(document.documentElement);
  const success = styles.getPropertyValue("--success-rgb").trim();
  const danger = styles.getPropertyValue("--danger-rgb").trim();
  const accent = styles.getPropertyValue("--accent-rgb").trim();
  const grid = styles.getPropertyValue("--card-border").trim();
  const text = styles.getPropertyValue("--text").trim();
  const muted = styles.getPropertyValue("--muted").trim();
  const track = styles.getPropertyValue("--surface-3").trim();
  const textDark = styles.getPropertyValue("--text-dark").trim();
  const mutedDark = styles.getPropertyValue("--muted-dark").trim();
  const trackDark = styles.getPropertyValue("--track-dark").trim();

  return {
    done: `rgba(${success || '0, 220, 140'}, 0.92)`,
    remain: `rgba(${danger || '239, 65, 53'}, 0.88)`,
    accent: `rgba(${accent || '0, 204, 255'}, 0.82)`,
    grid: grid || "rgba(255,255,255,0.10)",
    text: textDark || text || "#ffffff",
    muted: mutedDark || muted || "rgba(255,255,255,0.65)",
    track: trackDark || track || "rgba(0,0,0,0.22)",
  };
}

function drawCourseDashboardDonut(courses) {
  const canvas = document.getElementById("courseDashboardDonut");
  if (!canvas) return;

  const { ctx, w, h } = prepareCanvas2d(canvas, { minW: 260, minH: 200 });
  const C = getDashboardPalette();

  const items = getDashboardCoursesSorted(courses || []);
  const totals = items.reduce(
    (acc, c) => {
      const { total, done } = computeCourseDoneHours(c);
      acc.total += total;
      acc.done += done;
      return acc;
    },
    { total: 0, done: 0 }
  );

  ctx.clearRect(0, 0, w, h);

  const total = totals.total || 0;
  const done = totals.done || 0;
  const pct = total > 0 ? Math.max(0, Math.min(1, done / total)) : 0;

  const cx = w / 2;
  const cy = h / 2;
  const r = Math.min(w, h) * 0.36;
  const thick = Math.max(10, r * 0.28);

  // Track
  ctx.beginPath();
  ctx.strokeStyle = C.track;
  ctx.lineWidth = thick;
  ctx.arc(cx, cy, r, 0, Math.PI * 2);
  ctx.stroke();

  // Progress
  const start = -Math.PI / 2;
  const end = start + Math.PI * 2 * pct;
  ctx.beginPath();
  ctx.strokeStyle = C.done;
  ctx.lineCap = "round";
  ctx.lineWidth = thick;
  ctx.arc(cx, cy, r, start, end);
  ctx.stroke();

  // Inner text
  ctx.fillStyle = C.text;
  ctx.textAlign = "center";
  ctx.textBaseline = "middle";
  ctx.font = "700 20px system-ui, -apple-system, Segoe UI, Roboto, Arial";
  ctx.fillText(`${Math.round(pct * 100)}%`, cx, cy - 6);

  ctx.fillStyle = C.muted;
  ctx.font = "12px system-ui, -apple-system, Segoe UI, Roboto, Arial";
  ctx.fillText(`${formatHours(done)}h / ${formatHours(total)}h`, cx, cy + 16);

  const t = document.getElementById("courseDashboardDonutText");
  if (t) {
    if (total > 0) {
      t.innerHTML = `
        <span class="badge badge-success" style="margin-right:8px;">Done ${formatHours(done)}h</span>
        <span class="badge badge-danger">Remaining ${formatHours(Math.max(0, total - done))}h</span>
      `;
    } else {
      t.textContent = "No course hours yet.";
    }
  }
}

function drawCourseDashboardByYear(courses) {
  const canvas = document.getElementById("courseDashboardByYear");
  if (!canvas) return;

  const { ctx, w, h } = prepareCanvas2d(canvas, { minW: 260, minH: 220 });
  const C = getDashboardPalette();

  const items = getDashboardCoursesSorted(courses || []);
  ctx.clearRect(0, 0, w, h);

  if (!items.length) {
    ctx.fillStyle = C.muted;
    ctx.font = "14px system-ui, -apple-system, Segoe UI, Roboto, Arial";
    ctx.fillText("No data.", 12, 20);
    return;
  }

  // Aggregate totals by (year, sem)
  const buckets = new Map();
  for (const c of items) {
    const y = Number(c.year_level || 0) || 0;
    const s = Number(c.semester || 0) || 0;
    const key = `Y${y}S${s}`;
    const { total } = computeCourseDoneHours(c);
    buckets.set(key, (buckets.get(key) || 0) + total);
  }

  const labels = [
    { k: "Y1S1", label: "Y1 S1" },
    { k: "Y1S2", label: "Y1 S2" },
    { k: "Y2S1", label: "Y2 S1" },
    { k: "Y2S2", label: "Y2 S2" },
    { k: "Y3S1", label: "Y3 S1" },
    { k: "Y3S2", label: "Y3 S2" },
  ];

  const values = labels.map((x) => buckets.get(x.k) || 0);
  const maxV = Math.max(1, ...values);

  const pad = { top: 14, right: 12, bottom: 32, left: 36 };
  const chartW = w - pad.left - pad.right;
  const chartH = h - pad.top - pad.bottom;

  // Grid + ticks
  ctx.strokeStyle = C.grid;
  ctx.fillStyle = C.muted;
  ctx.font = "11px system-ui, -apple-system, Segoe UI, Roboto, Arial";

  const ticks = 4;
  for (let i = 0; i <= ticks; i++) {
    const t = i / ticks;
    const y = pad.top + chartH - t * chartH;
    ctx.beginPath();
    ctx.moveTo(pad.left, y);
    ctx.lineTo(pad.left + chartW, y);
    ctx.stroke();
    ctx.fillText(String(Math.round(t * maxV)), 6, y + 4);
  }

  const gap = 10;
  const barW = Math.max(10, (chartW - gap * (labels.length - 1)) / labels.length);

  for (let i = 0; i < labels.length; i++) {
    const v = values[i];
    const bh = (v / maxV) * chartH;
    const x = pad.left + i * (barW + gap);
    const y = pad.top + chartH - bh;

    ctx.fillStyle = i % 2 === 0 ? C.accent : C.done;
    ctx.fillRect(x, y, barW, bh);

    ctx.fillStyle = C.text;
    ctx.textAlign = "center";
    ctx.textBaseline = "top";
    ctx.font = "10px system-ui, -apple-system, Segoe UI, Roboto, Arial";
    ctx.fillText(labels[i].label, x + barW / 2, pad.top + chartH + 8);
  }
}

async function drawMissionnairePieChart() {
  const canvas = document.getElementById("missionnairePie");
  if (!canvas) return;

  const { ctx, w, h } = prepareCanvas2d(canvas, { minW: 260, minH: 220 });
  const C = getDashboardPalette();

  ctx.clearRect(0, 0, w, h);

  // Apply same global Year/Sem filters (if user set them on dashboard)
  const f = getGlobalFilters();
  const qs = new URLSearchParams();
  if (f?.year_level) qs.set("year_level", String(f.year_level));
  if (f?.semester) qs.set("semester", String(f.semester));

  let missionName = "Missionnaire";
  let missionTotal = 0;
  let othersTotal = 0;

  try {
    const url = "php/get_missionnaire_hours_pie.php" + (qs.toString() ? `?${qs.toString()}` : "");
    const payload = await fetchJson(url);
    if (!payload?.success) throw new Error(payload?.error || "Failed to load pie data");

    // Preferred: use explicit aggregated fields if present.
    const m = payload?.data?.missionnaire;
    if (m && typeof m === "object") {
      missionName = String(m?.full_name || "Missionnaire");
      missionTotal = Number(m?.total_hours || 0);
      othersTotal = Number(payload?.data?.others_total_hours || 0);
    } else {
      // Fallback: aggregate from per-doctor breakdown.
      const doctors = Array.isArray(payload?.data?.doctors) ? payload.data.doctors : [];
      for (const d of doctors) {
        const total = Number(d?.total_hours || 0);
        if (!Number.isFinite(total) || total <= 0) continue;
        const isM = Boolean(d?.is_missionnaire) || String(d?.full_name || "").toLowerCase() === "missionnaire";
        if (isM) {
          missionName = String(d?.full_name || missionName);
          missionTotal += total;
        } else {
          othersTotal += total;
        }
      }
    }
  } catch (err) {
    ctx.fillStyle = C.muted;
    ctx.font = "14px system-ui, -apple-system, Segoe UI, Roboto, Arial";
    ctx.fillText("Failed to load chart.", 12, 22);

    const t = document.getElementById("missionnairePieText");
    if (t) t.textContent = "";
    return;
  }

  missionTotal = Number.isFinite(missionTotal) ? Math.max(0, missionTotal) : 0;
  othersTotal = Number.isFinite(othersTotal) ? Math.max(0, othersTotal) : 0;
  const total = missionTotal + othersTotal;

  if (total <= 0) {
    ctx.fillStyle = C.muted;
    ctx.font = "14px system-ui, -apple-system, Segoe UI, Roboto, Arial";
    ctx.fillText("No course hours found.", 12, 22);
    const t = document.getElementById("missionnairePieText");
    if (t) t.textContent = "";
    return;
  }

  // Standard 2-slice pie chart: Missionnaire vs Others
  const cx = w / 2;
  const cy = h / 2;
  const r = Math.min(w, h) * 0.38;

  const startAngle = -Math.PI / 2;
  const missionPct = total > 0 ? missionTotal / total : 0;
  const aMissionEnd = startAngle + Math.PI * 2 * missionPct;

  // Missionnaire slice
  ctx.beginPath();
  ctx.moveTo(cx, cy);
  ctx.fillStyle = C.accent;
  ctx.arc(cx, cy, r, startAngle, aMissionEnd);
  ctx.closePath();
  ctx.fill();

  // Others slice
  ctx.beginPath();
  ctx.moveTo(cx, cy);
  ctx.fillStyle = getDashboardPalette().done;
  ctx.arc(cx, cy, r, aMissionEnd, startAngle + Math.PI * 2);
  ctx.closePath();
  ctx.fill();

  // Separators + border
  ctx.strokeStyle = getDashboardPalette().grid;
  ctx.lineWidth = 1;
  ctx.beginPath();
  ctx.arc(cx, cy, r, startAngle, startAngle + Math.PI * 2);
  ctx.stroke();

  // Slice labels (outside) with callout lines
  const labelFont = "600 12px system-ui, -apple-system, Segoe UI, Roboto, Arial";
  const subFont = "11px system-ui, -apple-system, Segoe UI, Roboto, Arial";

  /**
   * @param {number} midAngle
   * @param {string} title
   * @param {string} detail
   * @param {string} color
   */
  function drawSliceLabel(midAngle, title, detail, color) {
    // points
    const r1 = r * 0.92;
    const r2 = r * 1.12;
    const x1 = cx + Math.cos(midAngle) * r1;
    const y1 = cy + Math.sin(midAngle) * r1;
    const x2 = cx + Math.cos(midAngle) * r2;
    const y2 = cy + Math.sin(midAngle) * r2;

    const isRight = Math.cos(midAngle) >= 0;
    const elbow = 16;
    const x3 = x2 + (isRight ? elbow : -elbow);
    const y3 = y2;

    // line
    ctx.strokeStyle = getDashboardPalette().muted;
    ctx.lineWidth = 1;
    ctx.beginPath();
    ctx.moveTo(x1, y1);
    ctx.lineTo(x2, y2);
    ctx.lineTo(x3, y3);
    ctx.stroke();

    // dot
    ctx.fillStyle = color;
    ctx.beginPath();
    ctx.arc(x1, y1, 2.2, 0, Math.PI * 2);
    ctx.fill();

    // text
    ctx.textAlign = isRight ? "left" : "right";
    ctx.textBaseline = "bottom";
    ctx.fillStyle = C.text;
    ctx.font = labelFont;
    ctx.fillText(title, x3 + (isRight ? 2 : -2), y3 - 1);

    ctx.textBaseline = "top";
    ctx.fillStyle = C.muted;
    ctx.font = subFont;
    ctx.fillText(detail, x3 + (isRight ? 2 : -2), y3 + 2);
  }

  const missionAngleMid = (startAngle + aMissionEnd) / 2;
  const othersAngleMid = (aMissionEnd + (startAngle + Math.PI * 2)) / 2;

  const missionLabel = `${missionName}`;
  const missionDetail = `${Math.round(missionPct * 100)}% (${formatHours(missionTotal)}h)`;
  drawSliceLabel(missionAngleMid, missionLabel, missionDetail, C.accent);

  const othersPct = total > 0 ? othersTotal / total : 0;
  const othersLabel = "Others";
  const othersDetail = `${Math.round(othersPct * 100)}% (${formatHours(othersTotal)}h)`;
  drawSliceLabel(othersAngleMid, othersLabel, othersDetail, getDashboardPalette().done);

  // Two-line legend under the chart
  const t = document.getElementById("missionnairePieText");
  if (t) {
    t.innerHTML = `
      <div class="badge" style="display:inline-block; margin-bottom:8px; background: var(--surface-1); border: 1px solid var(--card-border); color: var(--text);">Total ${formatHours(total)}h</div>
      <div style="margin-top:4px;">
        <div style="display:flex; align-items:center; justify-content:space-between; gap:10px; padding:4px 0; border-top:1px solid var(--card-border);">
          <div style="display:flex; align-items:center; gap:8px; min-width:0;">
            <span style="width:10px; height:10px; border-radius:2px; background:${C.accent}; flex:0 0 auto;"></span>
            <span style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${escapeHtml(missionName)}</span>
          </div>
          <div style="white-space:nowrap;">${formatHours(missionTotal)}h</div>
        </div>
        <div style="display:flex; align-items:center; justify-content:space-between; gap:10px; padding:4px 0; border-top:1px solid var(--card-border);">
          <div style="display:flex; align-items:center; gap:8px; min-width:0;">
            <span style="width:10px; height:10px; border-radius:2px; background: ${getDashboardPalette().done}; flex:0 0 auto;"></span>
            <span style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">Others</span>
          </div>
          <div style="white-space:nowrap;">${formatHours(othersTotal)}h</div>
        </div>
      </div>
    `;
  }
}

function drawCourseDashboardTopRemaining(courses) {
  const canvas = document.getElementById("courseDashboardTopRemaining");
  if (!canvas) return;

  const { ctx, w, h } = prepareCanvas2d(canvas, { minW: 260, minH: 220 });
  const C = getDashboardPalette();

  const items = getDashboardCoursesSorted(courses || []).map((c) => {
    const { total, done } = computeCourseDoneHours(c);
    return { c, remaining: Math.max(0, total - done) };
  });

  items.sort((a, b) => b.remaining - a.remaining);
  const top = items.filter((x) => x.remaining > 0).slice(0, 5);

  ctx.clearRect(0, 0, w, h);

  if (!top.length) {
    ctx.fillStyle = C.muted;
    ctx.font = "14px system-ui, -apple-system, Segoe UI, Roboto, Arial";
    ctx.fillText("No remaining hours.", 12, 20);
    return;
  }

  const pad = { top: 10, right: 10, bottom: 10, left: 10 };
  const rowH = Math.floor((h - pad.top - pad.bottom) / top.length);
  const maxR = Math.max(1, ...top.map((x) => x.remaining));

  for (let i = 0; i < top.length; i++) {
    const { c, remaining } = top[i];
    const y = pad.top + i * rowH;
    const barY = y + rowH * 0.48;
    const barH = Math.max(10, rowH * 0.28);

    ctx.fillStyle = C.muted;
    ctx.font = "11px system-ui, -apple-system, Segoe UI, Roboto, Arial";
    ctx.textAlign = "left";
    ctx.textBaseline = "top";

    const name = String(c.course_name || "").trim();
    const label = name.length > 26 ? name.slice(0, 26) + "…" : name;
    ctx.fillText(label, pad.left, y + 2);

    // Track
    const trackX = pad.left;
    const trackW = w - pad.left - pad.right;
    ctx.fillStyle = getDashboardPalette().track;
    ctx.fillRect(trackX, barY, trackW, barH);

    // Bar
    const bw = (remaining / maxR) * trackW;
    ctx.fillStyle = C.remain;
    ctx.fillRect(trackX, barY, bw, barH);

    // Value
    ctx.fillStyle = C.text;
    ctx.textAlign = "right";
    ctx.textBaseline = "middle";
    ctx.font = "700 11px system-ui, -apple-system, Segoe UI, Roboto, Arial";
    ctx.fillText(`${formatHours(remaining)}h`, w - pad.right, barY + barH / 2);
  }
}

function drawCourseDashboardChart(courses) {
  const canvas = document.getElementById("courseDashboardChart");
  if (!canvas) return;

  const { ctx, w, h } = prepareCanvas2d(canvas, { minW: 520, minH: 320 });
  const C = getDashboardPalette();

  // Extra bottom space so course labels are readable (especially when rotated).
  const padding = { top: 18, right: 18, bottom: 92, left: 52 };

  function truncateToWidth(text, maxPx) {
    const raw = String(text || "").trim();
    if (!raw) return "";
    if (ctx.measureText(raw).width <= maxPx) return raw;
    const ell = "…";
    let lo = 0;
    let hi = raw.length;
    while (lo < hi) {
      const mid = Math.ceil((lo + hi) / 2);
      const candidate = raw.slice(0, mid) + ell;
      if (ctx.measureText(candidate).width <= maxPx) lo = mid;
      else hi = mid - 1;
    }
    return raw.slice(0, Math.max(0, lo)) + ell;
  }

  function drawAngledLabel(text, x, y, maxPx) {
    // Rotate labels so they don't smash together when many courses exist.
    // Anchor at the bar center, angled up-left.
    ctx.save();
    ctx.translate(x, y);
    ctx.rotate(-Math.PI / 4);
    ctx.textAlign = "right";
    ctx.textBaseline = "middle";
    const t = truncateToWidth(text, maxPx);
    ctx.fillText(t, 0, 0);
    ctx.restore();
  }

  ctx.clearRect(0, 0, w, h);

  const items = getDashboardCoursesSorted(courses || []);

  if (!items.length) {
    ctx.fillStyle = C.muted;
    ctx.font = "14px system-ui, -apple-system, Segoe UI, Roboto, Arial";
    ctx.fillText("No courses for selected filters.", padding.left, padding.top + 20);
    return;
  }

  const totals = items.map((c) => computeCourseDoneHours(c).total);
  const maxTotal = Math.max(1, ...totals);

  const tickCount = 5;
  const chartW = w - padding.left - padding.right;
  const chartH = h - padding.top - padding.bottom;

  ctx.strokeStyle = C.grid;
  ctx.lineWidth = 1;
  ctx.font = "12px system-ui, -apple-system, Segoe UI, Roboto, Arial";
  ctx.fillStyle = C.muted;

  for (let i = 0; i <= tickCount; i++) {
    const t = i / tickCount;
    const y = padding.top + chartH - t * chartH;
    ctx.beginPath();
    ctx.moveTo(padding.left, y);
    ctx.lineTo(padding.left + chartW, y);
    ctx.stroke();
    const v = (t * maxTotal).toFixed(0);
    ctx.fillText(v, 10, y + 4);
  }

  // Narrower bars with more breathing room.
  // Use gap as a fraction of bar width for better scaling with many courses.
  const barCount = items.length;
  const targetBarW = 14; // visually narrow
  const minGap = 10;
  const maxGap = 18;

  let barW = Math.max(10, Math.min(targetBarW, (chartW / Math.max(1, barCount)) * 0.65));
  let barGap = Math.max(minGap, Math.min(maxGap, barW * 0.9));

  // If total width still overflows, recompute based on available width.
  const totalNeeded = barCount * barW + (barCount - 1) * barGap;
  if (totalNeeded > chartW) {
    barW = Math.max(9, (chartW - minGap * (barCount - 1)) / barCount);
    barGap = minGap;
  }

  // Center the bars instead of anchoring them hard-left.
  const usedW = barCount * barW + (barCount - 1) * barGap;
  const baseX = padding.left + Math.max(0, (chartW - usedW) / 2);

  ctx.textAlign = "center";
  ctx.textBaseline = "top";

  for (let i = 0; i < barCount; i++) {
    const c = items[i];
    const { total, done } = computeCourseDoneHours(c);
    const remaining = Math.max(0, total - done);

    const x = baseX + i * (barW + barGap);

    const totalH = (total / maxTotal) * chartH;
    const doneH = (done / maxTotal) * chartH;
    const remainH = (remaining / maxTotal) * chartH;

    const y0 = padding.top + chartH;

    // Remaining
    ctx.fillStyle = C.remain;
    ctx.fillRect(x, y0 - totalH, barW, remainH);

    // Done
    ctx.fillStyle = C.done;
    ctx.fillRect(x, y0 - totalH + remainH, barW, doneH);

    const label = String(c.course_name || "").trim();

    ctx.fillStyle = C.text;
    ctx.font = "11px system-ui, -apple-system, Segoe UI, Roboto, Arial";

    // Give each subject its own readable label space:
    // - rotate labels
    // - truncate to a pixel width (not just character count)
    const labelY = padding.top + chartH + 46;
    const maxLabelPx = Math.max(70, barGap * 3.2);
    drawAngledLabel(label, x + barW / 2, labelY, maxLabelPx);
  }

  // Axis label
  ctx.save();
  ctx.translate(18, padding.top + chartH / 2);
  ctx.rotate(-Math.PI / 2);
  ctx.fillStyle = C.muted;
  ctx.font = "12px system-ui, -apple-system, Segoe UI, Roboto, Arial";
  ctx.textAlign = "center";
  ctx.fillText("Hours", 0, 0);
  ctx.restore();

  // X-axis label hint
  ctx.fillStyle = C.muted;
  ctx.font = "11px system-ui, -apple-system, Segoe UI, Roboto, Arial";
  ctx.textAlign = "left";
  ctx.textBaseline = "top";
  ctx.fillText("Courses", padding.left, padding.top + chartH + 8);
}

function redrawAllDashboardCharts() {
  drawCourseDashboardDonut(state.courses || []);
  drawCourseDashboardChart(state.courses || []);
  // Removed Hours by Year/Sem chart from the dashboard UI.
  // drawCourseDashboardByYear(state.courses || []);
  drawCourseDashboardTopRemaining(state.courses || []);
  drawMissionnairePieChart();
}

function renderCourseProgressList(courses) {
  const wrap = document.getElementById("courseDashboardList");
  if (!wrap) return;

  const filtered = applyGlobalFiltersToCourses(courses || []);

  if (!filtered.length) {
    wrap.innerHTML = `<div class="muted">No courses found for the selected filters.</div>`;
    return;
  }

  // Sort by completion (most done first), then by remaining hours, then by name.
  filtered.sort((a, b) => {
    const ta = Number(a.total_hours || 0);
    const tb = Number(b.total_hours || 0);
    const ra = Number(a.remaining_hours || 0);
    const rb = Number(b.remaining_hours || 0);
    const da = Math.max(0, ta - ra);
    const db = Math.max(0, tb - rb);
    const pa = ta > 0 ? da / ta : 0;
    const pb = tb > 0 ? db / tb : 0;
    if (pa !== pb) return pb - pa;
    if (ra !== rb) return ra - rb;
    return String(a.course_name || "").localeCompare(String(b.course_name || ""));
  });

  wrap.innerHTML = "";

  for (const c of filtered) {
    const { total, done } = computeCourseDoneHours(c);
    const pct = total > 0 ? Math.max(0, Math.min(100, (done / total) * 100)) : 0;

    const item = document.createElement("div");
    item.className = "course-progress-item";

    const code = String(c.subject_code || "").trim();
    const codeLabel = code ? ` • ${escapeHtml(code)}` : "";

    item.innerHTML = `
      <div class="course-progress-top">
        <div>
          <div class="course-progress-title">${escapeHtml(c.course_name || "(Unnamed course)")}</div>
          <div class="course-progress-meta">${escapeHtml(c.program || "")}${codeLabel} • Year ${escapeHtml(c.year_level)} • Sem ${escapeHtml(c.semester)}</div>
        </div>
        <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap; justify-content:flex-end;">
          <span class="badge badge-success">Done ${formatHours(done)}h</span>
          <span class="badge badge-danger">Remaining ${formatHours(Math.max(0, total - done))}h</span>
          <span class="muted">${formatHours(done)}h / ${formatHours(total)}h</span>
        </div>
      </div>

      <div class="course-progress-bar" aria-label="Course progress">
        <div class="course-progress-fill" style="width:${pct.toFixed(2)}%"></div>
      </div>

      <div class="course-progress-legend">
        <span class="badge badge-success">Done: ${formatHours(done)}h</span>
        <span class="badge badge-danger">Remaining: ${formatHours(Math.max(0, total - done))}h</span>
        <span class="muted">${pct.toFixed(0)}%</span>
      </div>
    `;

    wrap.appendChild(item);
  }
}



  async function initCourseDashboardPage() {
    try {
      setStatusById("courseDashboardStatus", "Loading�");
      initPageFiltersUI({ yearSelectId: "dashboardYearFilter", semesterSelectId: "dashboardSemesterFilter" });
      await loadCourses();
      redrawAllDashboardCharts();
      renderCourseProgressList(state.courses || []);
      setStatusById("courseDashboardStatus", "");

      document.getElementById("refreshCourseDashboard")?.addEventListener("click", async () => {
        try {
          setStatusById("courseDashboardStatus", "Refreshing�");
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
    } catch (err) {
      setStatusById("courseDashboardStatus", err.message, "error");
    }
  }

  window.dmportal = window.dmportal || {};
  window.dmportal.initCourseDashboardPage = initCourseDashboardPage;
})();
