(function () {
  "use strict";

  const { fetchJson, setStatusById, escapeHtml, makeCourseLabel, parseDoctorIdsCsv, applyPageFiltersToCourses, getGlobalFilters, setGlobalFilters, initPageFiltersUI } = window.dmportal || {};

  const state = {
    doctors: [],
    courses: [],
    weeks: [],
  };

  function formatHours(n) {
    const num = Number(n);
    if (Number.isNaN(num)) return "0.00";
    return num.toFixed(2);
  }

  async function loadDoctors() {
    const payload = await fetchJson("php/get_doctors.php");
    if (!payload.success) throw new Error(payload.error || "Failed to load doctors");
    state.doctors = payload.data || [];
  }

  async function loadCourses() {
    const payload = await fetchJson("php/get_courses.php");
    if (!payload.success) throw new Error(payload.error || "Failed to load courses");
    state.courses = payload.data || [];
  }

async function loadDoctorsForCourseForm() {
  // admin_courses.php Add Course form:
  // - new UI uses hidden <input id="doctor_id"> + checkbox dropdown
  // - older UI used <select id="doctor_id">

  const el = document.getElementById("doctor_id");
  if (!el) return;

  try {
    const payload = await fetchJson("php/get_doctors.php");
    if (!payload.success) {
      throw new Error(payload.error || "Failed to load doctors");
    }

    state.doctors = payload.data || [];

    // Backward compatible: if #doctor_id is a SELECT, populate it.
    if (el.tagName === "SELECT") {
      el.innerHTML = `<option value="">Select a doctor</option>`;
      for (const d of state.doctors) {
        const opt = document.createElement("option");
        opt.value = d.doctor_id;
        opt.textContent = d.full_name;
        el.appendChild(opt);
      }

      if (state.doctors.length === 0) {
        el.innerHTML = `<option value="">No doctors found</option>`;
      }
      return;
    }

    // New UI: init checkbox multi-select
    if (document.getElementById("createDoctorsMulti")) {
      initCreateDoctorsMultiSelect(null);
    }
  } catch (err) {
    setStatusById("status", err.message, "error");
  }
}

async function handleCourseCreateSubmit(e) {
  e.preventDefault();
  setStatusById("status", "Submitting…");

  const form = e.currentTarget;
  const fd = new FormData(form);

  // If we are using the checkbox doctor picker on the Add form, ensure doctor_id is set.
  // Backend still expects a single doctor_id (primary doctor).
  const didInput = document.getElementById("doctor_id");
  if (didInput && !String(didInput.value || "").trim()) {
    const first = (createDoctorsSelectedIds || [])[0];
    didInput.value = first ? String(first) : "";
  }

  try {
    const payload = await fetchJson("php/add_course.php", {
      method: "POST",
      body: fd,
    });

    if (!payload.success) {
      throw new Error(payload.error || "Failed to add course");
    }

    // If admin selected multiple doctors, also persist them in course_doctors mapping.
    const ids = (createDoctorsSelectedIds || []).slice();
    try {
      if (ids.length > 0) {
        await setCourseDoctors(payload.data.course_id, ids);
      }
    } catch {
      // ignore (older DB without course_doctors)
    }

    // If 2+ doctors are assigned, ask how to split total course hours among them.
    // (This is required for reports/workload splitting.)
    if (ids.length >= 2) {
      const totalHours = Number(fd.get("course_hours") || 0);
      await openHoursSplitModal({
        course_id: Number(payload.data.course_id),
        doctor_ids: ids,
        total_hours: Number.isFinite(totalHours) ? totalHours : 0,
        course_name: String(fd.get("course_name") || ""),
        subject_code: String(fd.get("subject_code") || ""),
      });
    }

    setStatusById("status", `Course added (ID: ${payload.data.course_id}).`, "success");

    // Preserve these selections across consecutive additions (admin adds many courses)
    const preservedYear = String(fd.get("year_level") || "");
    const preservedSemester = String(fd.get("semester") || "");
    const preservedCourseType = String(fd.get("course_type") || "");

    form.reset();

    // Restore preserved selects (reset() would otherwise revert to defaults)
    const yearSel = document.getElementById("year_level");
    if (yearSel && preservedYear) yearSel.value = preservedYear;

    const semSel = document.getElementById("semester");
    if (semSel && preservedSemester) semSel.value = preservedSemester;

    const typeSel = document.getElementById("course_type");
    if (typeSel && preservedCourseType) typeSel.value = preservedCourseType;

    const hours = document.getElementById("course_hours");
    if (hours) hours.value = 10;

    const coef = document.getElementById("coefficient");
    if (coef) coef.value = 1;

    // reset create doctors UI
    setCreateDoctorsSelectedIds([]);
    renderCreateDoctorsMenu();
    renderCreateDoctorsSummary();

    // Refresh admin list if present
    await maybeInitAdminCourseList(true);
  } catch (err) {
    setStatusById("status", err.message, "error");
  }
}

function normalizePhoneForWhatsApp(phone) {
  // WhatsApp wa.me expects digits only (and country code). We best-effort strip non-digits.
  // If the result is too short, treat as missing.
  const digits = String(phone || "").replace(/\D+/g, "");
  return digits.length >= 8 ? digits : "";
}

function buildAbsoluteUrl(relativeOrAbsolute) {
  try {
    return new URL(String(relativeOrAbsolute || ""), window.location.href).href;
  } catch {
    return String(relativeOrAbsolute || "");
  }
}

function getWeekLabel(weekId) {
  const w = (state.weeks || []).find((x) => String(x.week_id) === String(weekId));
  return String(w?.label || "").trim();
}

function buildDoctorScheduleExportUrl(doctorId, weekId) {
  if (!doctorId) return "";
  const qs = new URLSearchParams({ doctor_id: String(doctorId) });
  if (weekId) qs.set("week_id", String(weekId));
  return buildAbsoluteUrl(`php/export_doctor_week_xls.php?${qs.toString()}`);
}

// Trigger a file download without navigating away.
// (Used for: "Email" button should download the XLS first, then open the mail draft.)
function triggerBackgroundDownload(url) {
  const href = String(url || "").trim();
  if (!href) return;

  try {
    const iframe = document.createElement("iframe");
    iframe.style.width = "0";
    iframe.style.height = "0";
    iframe.style.border = "0";
    iframe.style.position = "absolute";
    iframe.style.left = "-9999px";
    iframe.style.top = "-9999px";
    iframe.setAttribute("aria-hidden", "true");

    iframe.src = href;
    document.body.appendChild(iframe);

    // Cleanup later
    window.setTimeout(() => {
      try {
        iframe.remove();
      } catch {
        // ignore
      }
    }, 60_000);
  } catch {
    // If iframes are blocked, fallback to opening in a new tab.
    // (Still better than nothing, but it may show a blank tab.)
    try {
      window.open(href, "_blank", "noopener");
    } catch {
      // ignore
    }
  }
}

function getDoctorFirstName(fullName) {
  const s = String(fullName || "").trim();
  if (!s) return "";
  // Take the first token (supports: "Dr. Ahmed Ali" -> "Dr."; "Ahmed Ali" -> "Ahmed")
  // If the name starts with "Dr." / "Dr", skip it.
  const parts = s.split(/\s+/).filter(Boolean);
  if (parts.length === 0) return "";
  const first = parts[0].replace(/\.+$/, "");
  if (/^dr$/i.test(first) && parts[1]) return parts[1].replace(/\.+$/, "");
  return parts[0];
}

function buildDoctorScheduleGreetingText(doctorName) {
  const firstName = getDoctorFirstName(doctorName);

  // Keep this in ONE paragraph because WhatsApp is more reliable with simpler text.
  // Still use CRLF (\r\n) when mail clients parse the body.
  const namePart = firstName ? ` ${firstName}` : "";
  const msg = `Dear Dr.${namePart}, I hope you are doing well. Please open your email to find your weekly schedule attached. Let me know if you need any changes or clarifications. Best regards,`;

  // Some handlers prefer CRLF even for single-line strings; normalize anyway.
  return msg.replace(/\n/g, "\r\n");
}

function buildWhatsAppSendUrl(phoneDigits, text) {
  const p = String(phoneDigits || "").trim();
  if (!p) return "";

  // IMPORTANT:
  // WhatsApp requires an INTERNATIONAL phone number in most cases:
  // example: 201012345678 (Egypt) or 14155552671 (USA)
  // If you store a local number like 01012345678, WhatsApp may open but NOT select a chat.

  // Prefer wa.me format (official + usually the most consistent across devices).
  // Docs: https://faq.whatsapp.com/591358685853293/
  if (text) {
    return `https://wa.me/${encodeURIComponent(p)}?text=${encodeURIComponent(String(text))}`;
  }
  return `https://wa.me/${encodeURIComponent(p)}`;
}

function buildMailtoHref(email, subject = "", body = "") {
  const to = String(email || "").trim();
  if (!to) return "";

  const params = [];
  if (subject) params.push(["subject", subject]);
  if (body) params.push(["body", body]);

  // Use encodeURIComponent so spaces become %20 (not +).
  // Some Outlook handlers fail to decode + correctly in mailto query strings.
  const q = params
    .map(([k, v]) => `${encodeURIComponent(k)}=${encodeURIComponent(String(v))}`)
    .join("&");

  // Note: do not encode the @ and other valid mail characters in the address.
  // encodeURI keeps them intact but still escapes spaces (shouldn't exist anyway).
  return `mailto:${encodeURI(to)}${q ? "?" + q : ""}`;
}

function doctorOptionsHtml(selectedId) {
  const opts = [`<option value=\"\">Unassigned</option>`];
  for (const d of state.doctors || []) {
    const sel = String(d.doctor_id) === String(selectedId) ? "selected" : "";
    opts.push(`<option value=\"${escapeHtml(d.doctor_id)}\" ${sel}>${escapeHtml(d.full_name)}</option>`);
  }
  return opts.join("");
}

function doctorMultiOptionsHtml(selectedIds) {
  const selected = new Set((selectedIds || []).map((x) => String(x)));
  const opts = [];
  for (const d of state.doctors || []) {
    const sel = selected.has(String(d.doctor_id)) ? "selected" : "";
    opts.push(`<option value=\"${escapeHtml(d.doctor_id)}\" ${sel}>${escapeHtml(d.full_name)}</option>`);
  }
  return opts.join("");
}

// -----------------------------
// Checkbox multi-select (Course edit modal)
// -----------------------------
let editDoctorsSelectedIds = [];

// -----------------------------
// Checkbox multi-select (Add Course form)
// -----------------------------
let createDoctorsSelectedIds = [];

function setCreateDoctorsSelectedIds(ids) {
  createDoctorsSelectedIds = Array.from(new Set((ids || []).map((x) => Number(x)).filter((n) => !Number.isNaN(n) && n > 0)));
}

function renderCreateDoctorsSummary() {
  const summary = document.getElementById("createDoctorsSummary");
  if (!summary) return;

  if (!createDoctorsSelectedIds.length) {
    summary.textContent = "Select doctors…";
    return;
  }

  const selectedNames = (state.doctors || [])
    .filter((d) => createDoctorsSelectedIds.includes(Number(d.doctor_id)))
    .map((d) => d.full_name);

  summary.textContent = selectedNames.length ? selectedNames.join(", ") : `${createDoctorsSelectedIds.length} selected`;
}

function renderCreateDoctorsMenu() {
  const wrap = document.getElementById("createDoctorsMulti");
  if (!wrap) return;
  const menu = wrap.querySelector(".multi-select-menu");
  if (!menu) return;

  const selected = new Set(createDoctorsSelectedIds.map((x) => String(x)));
  menu.innerHTML = "";

  for (const d of state.doctors || []) {
    const row = document.createElement("label");
    row.className = "multi-select-item";

    const cb = document.createElement("input");
    cb.type = "checkbox";
    cb.value = String(d.doctor_id);
    cb.checked = selected.has(String(d.doctor_id));

    cb.addEventListener("change", () => {
      const v = Number(cb.value);
      if (cb.checked) {
        setCreateDoctorsSelectedIds([...(createDoctorsSelectedIds || []), v]);
      } else {
        setCreateDoctorsSelectedIds((createDoctorsSelectedIds || []).filter((x) => Number(x) !== v));
      }
      // Update hidden doctor_id to primary doctor
      const hid = document.getElementById("doctor_id");
      if (hid) hid.value = createDoctorsSelectedIds[0] ? String(createDoctorsSelectedIds[0]) : "";
      renderCreateDoctorsSummary();
    });

    const text = document.createElement("span");
    text.textContent = d.full_name;

    row.appendChild(cb);
    row.appendChild(text);
    menu.appendChild(row);
  }
}

function wireCreateDoctorsMultiSelectUI() {
  const wrap = document.getElementById("createDoctorsMulti");
  if (!wrap) return;
  const btn = wrap.querySelector(".multi-select-btn");
  if (!btn) return;

  if (btn.dataset.bound === "1") return;
  btn.dataset.bound = "1";

  // Cards use backdrop-filter which creates stacking contexts.
  // When the dropdown opens, ensure the whole card stays above subsequent cards.
  const hostCard = wrap.closest(".card");

  btn.addEventListener("click", () => {
    const isOpen = wrap.classList.toggle("open");
    btn.setAttribute("aria-expanded", isOpen ? "true" : "false");
    if (hostCard) hostCard.classList.toggle("multi-select-host-open", isOpen);
  });

  document.addEventListener("click", (e) => {
    if (!wrap.classList.contains("open")) return;
    if (wrap.contains(e.target)) return;
    wrap.classList.remove("open");
    btn.setAttribute("aria-expanded", "false");
    if (hostCard) hostCard.classList.remove("multi-select-host-open");
  });
}

function initCreateDoctorsMultiSelect(defaultDoctorId) {
  // Start with the existing select default if present
  const initial = defaultDoctorId ? [Number(defaultDoctorId)] : [];
  setCreateDoctorsSelectedIds(initial);

  wireCreateDoctorsMultiSelectUI();
  renderCreateDoctorsMenu();
  renderCreateDoctorsSummary();

  const hid = document.getElementById("doctor_id");
  if (hid) hid.value = createDoctorsSelectedIds[0] ? String(createDoctorsSelectedIds[0]) : "";
}

function getSelectedDoctorIdsFromEditMulti() {
  return (editDoctorsSelectedIds || []).slice();
}

function setEditDoctorsSelectedIds(ids) {
  editDoctorsSelectedIds = Array.from(new Set((ids || []).map((x) => Number(x)).filter((n) => !Number.isNaN(n) && n > 0)));
}

function renderEditDoctorsSummary() {
  const summary = document.getElementById("editDoctorsSummary");
  if (!summary) return;

  if (!editDoctorsSelectedIds.length) {
    summary.textContent = "Select doctors…";
    return;
  }

  const selectedNames = (state.doctors || [])
    .filter((d) => editDoctorsSelectedIds.includes(Number(d.doctor_id)))
    .map((d) => d.full_name);

  summary.textContent = selectedNames.length ? selectedNames.join(", ") : `${editDoctorsSelectedIds.length} selected`;
}

function renderEditDoctorsMenu() {
  const wrap = document.getElementById("editDoctorsMulti");
  if (!wrap) return;
  const menu = wrap.querySelector(".multi-select-menu");
  if (!menu) return;

  const selected = new Set(editDoctorsSelectedIds.map((x) => String(x)));
  menu.innerHTML = "";

  for (const d of state.doctors || []) {
    const row = document.createElement("label");
    row.className = "multi-select-item";

    const cb = document.createElement("input");
    cb.type = "checkbox";
    cb.value = String(d.doctor_id);
    cb.checked = selected.has(String(d.doctor_id));

    cb.addEventListener("change", () => {
      const v = Number(cb.value);
      if (cb.checked) {
        setEditDoctorsSelectedIds([...(editDoctorsSelectedIds || []), v]);
      } else {
        setEditDoctorsSelectedIds((editDoctorsSelectedIds || []).filter((x) => Number(x) !== v));
      }
      renderEditDoctorsSummary();
    });

    const text = document.createElement("span");
    text.textContent = d.full_name;

    row.appendChild(cb);
    row.appendChild(text);
    menu.appendChild(row);
  }
}

function wireEditDoctorsMultiSelectUI() {
  const wrap = document.getElementById("editDoctorsMulti");
  if (!wrap) return;

  const btn = wrap.querySelector(".multi-select-btn");
  if (!btn) return;

  // Avoid double-binding
  if (btn.dataset.bound === "1") return;
  btn.dataset.bound = "1";

  const hostCard = wrap.closest(".card");

  btn.addEventListener("click", () => {
    const isOpen = wrap.classList.toggle("open");
    btn.setAttribute("aria-expanded", isOpen ? "true" : "false");
    if (hostCard) hostCard.classList.toggle("multi-select-host-open", isOpen);
  });

  // Close when clicking outside
  document.addEventListener("click", (e) => {
    if (!wrap.classList.contains("open")) return;
    if (wrap.contains(e.target)) return;
    wrap.classList.remove("open");
    btn.setAttribute("aria-expanded", "false");
    if (hostCard) hostCard.classList.remove("multi-select-host-open");
  });
}

function initEditDoctorsMultiSelect(course) {
  // default selection:
  // - prefer course_doctors mapping (doctor_ids csv)
  // - fallback to legacy doctor_id
  const ids = parseDoctorIdsCsv(course?.doctor_ids);
  const initial = ids.length ? ids : (course?.doctor_id ? [Number(course.doctor_id)] : []);
  setEditDoctorsSelectedIds(initial);

  wireEditDoctorsMultiSelectUI();
  renderEditDoctorsMenu();
  renderEditDoctorsSummary();
}

// -----------------------------
// Admin Add Course form: Code/Name syncing using datalists
// -----------------------------
function initAdminCourseCreateFormEnhancements() {
  // Only for admin_courses.php
  const form = document.getElementById("courseForm");
  if (!form) return;

  const codeInput = document.getElementById("subject_code");
  const nameInput = document.getElementById("course_name");
  const codeList = document.getElementById("course_code_list");
  const nameList = document.getElementById("course_name_list");
  if (!codeInput || !nameInput || !codeList || !nameList) return;

  // Build mappings from existing courses in DB (best-effort suggestions).
  // Note: multiple courses could share code/name; we map by exact match, first hit.
  const byCode = new Map();
  const byName = new Map();

  const courses = state.courses || [];
  for (const c of courses) {
    const code = String(c.subject_code || "").trim();
    const name = String(c.course_name || "").trim();
    if (code && !byCode.has(code)) byCode.set(code, c);
    if (name && !byName.has(name)) byName.set(name, c);
  }

  // Populate datalists
  codeList.innerHTML = "";
  for (const code of Array.from(byCode.keys()).sort()) {
    const opt = document.createElement("option");
    opt.value = code;
    codeList.appendChild(opt);
  }

  nameList.innerHTML = "";
  for (const name of Array.from(byName.keys()).sort()) {
    const opt = document.createElement("option");
    opt.value = name;
    nameList.appendChild(opt);
  }

  let syncing = false;

  function syncFromCode() {
    if (syncing) return;
    syncing = true;
    try {
      const v = String(codeInput.value || "").trim();
      const c = byCode.get(v);
      if (c && !String(nameInput.value || "").trim()) {
        nameInput.value = String(c.course_name || "");
      }
    } finally {
      syncing = false;
    }
  }

  function syncFromName() {
    if (syncing) return;
    syncing = true;
    try {
      const v = String(nameInput.value || "").trim();
      const c = byName.get(v);
      if (c && !String(codeInput.value || "").trim()) {
        codeInput.value = String(c.subject_code || "");
      }
    } finally {
      syncing = false;
    }
  }

  codeInput.addEventListener("change", syncFromCode);
  codeInput.addEventListener("blur", syncFromCode);
  nameInput.addEventListener("change", syncFromName);
  nameInput.addEventListener("blur", syncFromName);

  // Ensure doctor picker UI exists
  if (document.getElementById("createDoctorsMulti")) {
    renderCreateDoctorsMenu();
    renderCreateDoctorsSummary();
  }
}

async function setCourseDoctors(courseId, doctorIds) {
  const fd = new FormData();
  fd.append("course_id", String(courseId));
  fd.append("doctor_ids", (doctorIds || []).join(","));
  const payload = await fetchJson("php/set_course_doctors.php", { method: "POST", body: fd });
  if (!payload.success) throw new Error(payload.error || "Failed to set course doctors.");
}

async function setCourseDoctorHours(courseId, allocations) {
  const fd = new FormData();
  fd.append("course_id", String(courseId));
  fd.append("allocations", JSON.stringify(allocations || []));
  const payload = await fetchJson("php/set_course_doctor_hours.php", { method: "POST", body: fd });
  if (!payload.success) throw new Error(payload.error || "Failed to set course doctor hours.");
}

async function getCourseDoctorHours(courseId) {
  const payload = await fetchJson(`php/get_course_doctor_hours.php?course_id=${encodeURIComponent(courseId)}`);
  if (!payload.success) throw new Error(payload.error || "Failed to load course doctor hours.");
  return payload.data?.allocations || [];
}

function openHoursSplitModal({ course_id, doctor_ids, total_hours, course_name, subject_code, existing_allocations } = {}) {
  const modal = document.getElementById("hoursSplitModal");
  if (!modal) return Promise.resolve();

  const cid = Number(course_id || 0);
  const ids = (doctor_ids || []).map((x) => Number(x)).filter((n) => n > 0);
  const total = Number(total_hours || 0);

  document.getElementById("hoursSplitCourseId").value = cid ? String(cid) : "";
  const meta = document.getElementById("hoursSplitCourseMeta");
  if (meta) {
    const code = subject_code ? ` (${escapeHtml(subject_code)})` : "";
    meta.innerHTML = `<strong>${escapeHtml(course_name || "Course")}</strong>${code}  Total: <strong>${formatHours(total)}h</strong>`;
  }

  const rows = document.getElementById("hoursSplitRows");
  if (!rows) return Promise.resolve();

  const allocMap = new Map();
  for (const a of existing_allocations || []) {
    allocMap.set(String(a.doctor_id), Number(a.allocated_hours ?? a.hours ?? 0));
  }

  rows.innerHTML = "";
  for (const did of ids) {
    const d = (state.doctors || []).find((x) => Number(x.doctor_id) === Number(did));
    const name = d?.full_name || `Doctor ${did}`;
    const def = allocMap.has(String(did)) ? allocMap.get(String(did)) : 0;

    const wrap = document.createElement("div");
    wrap.className = "field";
    wrap.style.margin = "0";
    wrap.innerHTML = `
      <label>${escapeHtml(name)}</label>
      <input type="number" step="0.5" min="0" data-hours-doc-id="${escapeHtml(did)}" value="${escapeHtml(def)}" />
    `;
    rows.appendChild(wrap);
  }

  setStatusById("hoursSplitStatus", "");

  // Open
  modal.classList.add("open", "stack-top");
  modal.setAttribute("aria-hidden", "false");

  // Return a promise that resolves when user saves or cancels.
  return new Promise((resolve) => {
    const saveBtn = document.getElementById("hoursSplitSave");

    function close() {
      modal.classList.remove("open", "stack-top");
      modal.setAttribute("aria-hidden", "true");
      cleanup();
      resolve();
    }

    function cleanup() {
      saveBtn?.removeEventListener("click", onSave);
      modal.querySelectorAll("[data-close='1']")?.forEach((el) => el.removeEventListener("click", close));
      document.removeEventListener("keydown", onEsc);
    }

    function onEsc(e) {
      if (e.key === "Escape") close();
    }

    async function onSave() {
      try {
        setStatusById("hoursSplitStatus", "Saving...");

        const inputs = modal.querySelectorAll("input[data-hours-doc-id]");
        const allocations = [];
        let sum = 0;
        inputs.forEach((inp) => {
          const did2 = Number(inp.dataset.hoursDocId || 0);
          const hrs = Number(inp.value || 0);
          allocations.push({ doctor_id: did2, hours: hrs });
          sum += hrs;
        });

        sum = Math.round(sum * 100) / 100;
        const total2 = Math.round(total * 100) / 100;
        if (sum !== total2) {
          setStatusById("hoursSplitStatus", `Hours must sum to ${formatHours(total2)}h (currently ${formatHours(sum)}h).`, "error");
          return;
        }

        await setCourseDoctorHours(cid, allocations);
        setStatusById("hoursSplitStatus", "Saved.", "success");

        // Refresh cached courses for UI
        await loadCourses();
        renderAdminCoursesList(document.getElementById("courseSearch")?.value || "");

        close();
      } catch (err) {
        setStatusById("hoursSplitStatus", err.message, "error");
      }
    }

    modal.querySelectorAll("[data-close='1']")?.forEach((el) => el.addEventListener("click", close));
    saveBtn?.addEventListener("click", onSave);
    document.addEventListener("keydown", onEsc);
  });
}

// (existing)

function renderAdminCoursesList(filter = "") {
  const list = document.getElementById("adminCoursesList");
  if (!list) return;

  const q = filter.trim().toLowerCase();

  // Respect global Year/Sem filter on Course Management page as well.
  const base = applyPageFiltersToCourses(state.courses || []);

  const courses = base.filter((c) => {
    if (!q) return true;
    return (
      String(c.course_name || "").toLowerCase().includes(q) ||
      String(c.program || "").toLowerCase().includes(q) ||
      String(c.doctor_names || c.doctor_name || "").toLowerCase().includes(q)
    );
  });

  if (!courses.length) {
    list.innerHTML = `<div class=\"muted\">No matching courses.</div>`;
    return;
  }

  list.innerHTML = "";
  for (const c of courses) {
    const card = document.createElement("div");
    card.className = "course-item";

    card.innerHTML = `
      <div class=\"course-top\">
        <div style=\"display:flex; gap:8px; align-items:flex-start; flex-wrap:wrap;\">
          <span class=\"pill\">${escapeHtml(makeCourseLabel(c.course_type, c.subject_code))}</span>
          <div>
            <div class=\"muted\" style=\"font-size:0.85rem; margin-top:2px;\">${escapeHtml(c.program)}</div>
            <div class=\"muted\" style=\"font-size:0.85rem; margin-top:2px;\">Year ${escapeHtml(c.year_level)} • Sem ${escapeHtml(c.semester)}${c.default_room_code ? " • Room " + escapeHtml(c.default_room_code) : ""}</div>
            <div style=\"margin-top:4px;\"><span class=\"pill\">${escapeHtml(makeCourseLabel(c.course_type, c.subject_code))}</span> <strong>${escapeHtml(c.course_name)}</strong></div>
          </div>
        </div>
        <span class=\"badge badge-hours\">${formatHours(c.remaining_hours)}h</span>
      </div>

      <div class=\"grid-2\" style=\"gap:10px;\">
        <div class=\"field\" style=\"margin:0;\">
          <label class=\"muted\" style=\"font-size:0.85rem;\">Doctor</label>
          <select data-action=\"doctor\" data-course-id=\"${escapeHtml(c.course_id)}\">${doctorOptionsHtml(c.doctor_id)}</select>
        </div>

        <div class=\"field\" style=\"margin:0;\">
          <label class=\"muted\" style=\"font-size:0.85rem;\">Total Hours</label>
          <input data-action=\"hours\" data-course-id=\"${escapeHtml(c.course_id)}\" type=\"number\" step=\"0.5\" min=\"0\" value=\"${escapeHtml(c.total_hours ?? "")}\" />
          <small class=\"hint\">Remaining hours are calculated automatically.</small>
        </div>
      </div>

      <div class=\"actions\" style=\"justify-content: space-between;\">
        <div class=\"muted\" style=\"font-size:0.85rem;\">ID: ${escapeHtml(c.course_id)} • ${escapeHtml(c.doctor_names || c.doctor_name || "Unassigned")}</div>
        <div style=\"display:flex; gap:10px;\">
          <button class=\"btn btn-secondary btn-small\" data-action=\"edit\" data-course-id=\"${escapeHtml(c.course_id)}\" type=\"button\">Edit</button>
          <button class=\"btn btn-secondary btn-small\" data-action=\"delete\" data-course-id=\"${escapeHtml(c.course_id)}\" type=\"button\" style=\"border-color: rgba(255,106,122,.35);\">Delete</button>
        </div>
      </div>
    `;

    list.appendChild(card);
  }
}

async function updateCourse(courseId, patch) {
  const c = (state.courses || []).find((x) => String(x.course_id) === String(courseId));
  if (!c) throw new Error("Course not found in state.");

  const fd = new FormData();
  fd.append("course_id", String(courseId));
  fd.append("course_name", patch.course_name ?? c.course_name);
  fd.append("program", patch.program ?? c.program);
  fd.append("year_level", String(patch.year_level ?? c.year_level));
  fd.append("semester", String(patch.semester ?? c.semester));
  fd.append("course_type", patch.course_type ?? c.course_type);
  fd.append("subject_code", patch.subject_code ?? c.subject_code ?? "");
  fd.append("default_room_code", patch.default_room_code ?? c.default_room_code ?? "");
  fd.append("coefficient", String(patch.coefficient ?? c.coefficient ?? 1));
  // IMPORTANT: send total_hours (not remaining_hours) when updating a course
  fd.append("course_hours", String(patch.course_hours ?? c.total_hours));

  const did = patch.doctor_id ?? c.doctor_id;
  fd.append("doctor_id", did === null || did === "" ? "" : String(did));

  const payload = await fetchJson("php/update_course.php", { method: "POST", body: fd });
  if (!payload.success) throw new Error(payload.error || "Failed to update course.");
}

async function deleteCourse(courseId) {
  const fd = new FormData();
  fd.append("course_id", String(courseId));
  const payload = await fetchJson("php/delete_course.php", { method: "POST", body: fd });
  if (!payload.success) throw new Error(payload.error || "Failed to delete course.");
}

async function maybeInitAdminCourseList(force = false) {
  const list = document.getElementById("adminCoursesList");
  if (!list) return;

  if (!force && state.courses?.length) {
    renderAdminCoursesList(document.getElementById("courseSearch")?.value || "");
    return;
  }

  try {
    setStatusById("adminCoursesStatus", "Loading…");
    await loadDoctors();
    await loadCourses();
    renderAdminCoursesList(document.getElementById("courseSearch")?.value || "");
    setStatusById("adminCoursesStatus", "");
  } catch (err) {
    setStatusById("adminCoursesStatus", err.message, "error");
  }
}

function openCourseEditModal(courseId) {
  const modal = document.getElementById("courseEditModal");
  if (!modal) return;

  const c = (state.courses || []).find((x) => String(x.course_id) === String(courseId));
  if (!c) return;

  setStatusById("courseEditStatus", "");

  document.getElementById("edit_course_id").value = String(c.course_id);
  document.getElementById("edit_course_name").value = c.course_name || "";
  document.getElementById("edit_program").value = c.program || "";
  document.getElementById("edit_year_level").value = String(c.year_level);
  document.getElementById("edit_semester").value = String(c.semester);
  document.getElementById("edit_course_type").value = String(c.course_type);
  const sc = document.getElementById("edit_subject_code");
  if (sc) sc.value = String(c.subject_code ?? "");
  document.getElementById("edit_course_hours").value = String(c.total_hours ?? "");
  const coef = document.getElementById("edit_coefficient");
  if (coef) coef.value = String(c.coefficient ?? 1);
  const dr = document.getElementById("edit_default_room_code");
  if (dr) dr.value = String(c.default_room_code ?? "");

  // Initialize checkbox multi-select for doctors
  initEditDoctorsMultiSelect(c);

  // Bind split-hours button (avoid double-binding)
  const splitBtn = document.getElementById("courseEditSplitHours");
  if (splitBtn && splitBtn.dataset.bound !== "1") {
    splitBtn.dataset.bound = "1";
    splitBtn.addEventListener("click", async () => {
      try {
        const courseId = Number(document.getElementById("edit_course_id")?.value || 0);
        if (!courseId) return;

        const ids = getSelectedDoctorIdsFromEditMulti();
        if ((ids || []).length < 2) {
          setStatusById("courseEditStatus", "Select at least 2 doctors to split hours.", "error");
          return;
        }

        const course = (state.courses || []).find((x) => Number(x.course_id) === courseId);

        const selectedSorted = (ids || []).map((x) => Number(x)).filter((n) => n > 0).sort((a, b) => a - b);
        const persistedSorted = String(course?.doctor_ids || "")
          .split(",")
          .map((x) => Number(String(x).trim()))
          .filter((n) => Number.isFinite(n) && n > 0)
          .sort((a, b) => a - b);

        // Backward compatibility: if course_doctors mapping is missing, fall back to legacy doctor_id.
        const effectivePersisted = persistedSorted.length ? persistedSorted : [Number(course?.doctor_id || 0)].filter((n) => n > 0);

        const same =
          effectivePersisted.length === selectedSorted.length &&
          effectivePersisted.every((v, i) => v === selectedSorted[i]);

        // Backend validation for split-hours requires doctors to already be assigned in course_doctors.
        // If the selection differs from what is currently saved, we must either:
        //  - ask user to Save first (safe), OR
        //  - sync the mapping only when it won't destroy existing allocations.
        let allocations = [];
        try {
          allocations = await getCourseDoctorHours(courseId);
        } catch {
          allocations = [];
        }

        if (!same) {
          const hasAlloc = (allocations || []).length > 0;
          if (hasAlloc) {
            setStatusById(
              "courseEditStatus",
              "Doctor assignments have changed. Click Save first to apply doctor changes, then Split Hours.",
              "error"
            );
            return;
          }

          // No allocations exist yet, so it is safe to sync doctor mappings now.
          // (set_course_doctors clears allocations; since none exist, no data loss.)
          await setCourseDoctors(courseId, selectedSorted);
        }

        const totalHours = Number(document.getElementById("edit_course_hours")?.value || course?.total_hours || 0);

        await openHoursSplitModal({
          course_id: courseId,
          doctor_ids: selectedSorted,
          total_hours: Number.isFinite(totalHours) ? totalHours : 0,
          course_name: String(course?.course_name || ""),
          subject_code: String(course?.subject_code || ""),
          existing_allocations: allocations,
        });
      } catch (err) {
        setStatusById("courseEditStatus", err.message, "error");
      }
    });
  }

  modal.classList.add("open");
  modal.setAttribute("aria-hidden", "false");
}

function closeCourseEditModal() {
  const modal = document.getElementById("courseEditModal");
  if (!modal) return;
  modal.classList.remove("open");
  modal.setAttribute("aria-hidden", "true");
  setStatusById("courseEditStatus", "");
}

async function saveCourseEditModal() {
  const cid = document.getElementById("edit_course_id")?.value;
  if (!cid) return;

  const patch = {
    course_name: document.getElementById("edit_course_name")?.value?.trim() || "",
    default_room_code: document.getElementById("edit_default_room_code")?.value?.trim() || "",
    program: document.getElementById("edit_program")?.value?.trim() || "",
    year_level: Number(document.getElementById("edit_year_level")?.value || 0),
    semester: Number(document.getElementById("edit_semester")?.value || 0),
    course_type: String(document.getElementById("edit_course_type")?.value || "").trim().toUpperCase(),
    subject_code: String(document.getElementById("edit_subject_code")?.value || "").trim(),
    course_hours: Number(document.getElementById("edit_course_hours")?.value || 0),
    coefficient: Number(document.getElementById("edit_coefficient")?.value || 1),
    doctor_ids: getSelectedDoctorIdsFromEditMulti(),
  };

  try {
    setStatusById("courseEditStatus", "Saving…");
    // Keep legacy doctor_id aligned with the first selected doctor (if any)
    const firstDoctorId = patch.doctor_ids?.[0] ?? null;
    await updateCourse(cid, { ...patch, doctor_id: firstDoctorId });

    // Multi-doctor mapping
    await setCourseDoctors(cid, patch.doctor_ids || []);

    // If 2+ doctors are assigned, ensure hour split is set (or let user update it).
    if ((patch.doctor_ids || []).length >= 2) {
      let currentAlloc = [];
      try {
        currentAlloc = await getCourseDoctorHours(Number(cid));
      } catch {
        currentAlloc = [];
      }
      const hasAny = (currentAlloc || []).length > 0;
      // If no allocations exist, prompt user now.
      if (!hasAny) {
        await openHoursSplitModal({
          course_id: Number(cid),
          doctor_ids: patch.doctor_ids,
          total_hours: Number(patch.course_hours || 0),
          course_name: String(patch.course_name || ""),
          subject_code: String(patch.subject_code || ""),
          existing_allocations: currentAlloc,
        });
      }
    }

    await loadCourses();
    renderAdminCoursesList(document.getElementById("courseSearch")?.value || "");
    setStatusById("adminCoursesStatus", "Updated.", "success");
    closeCourseEditModal();
  } catch (err) {
    setStatusById("courseEditStatus", err.message, "error");
  }
}

// -----------------------------
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
  // Dashboard semantic colors:
  // - Done: green
  // - Remaining: red
  // Keep slightly translucent RGBA so it blends with the dark/glass UI.
  return {
    done: "rgba(0, 220, 140, 0.92)", // green
    remain: "rgba(239, 65, 53, 0.88)", // red (matches --danger)
    accent: "rgba(0, 204, 255, 0.82)",
    grid: "rgba(255,255,255,0.10)",
    text: "rgba(255,255,255,0.88)",
    muted: "rgba(255,255,255,0.65)",
    track: "rgba(0,0,0,0.22)",
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
  ctx.fillStyle = "rgba(0, 220, 140, 0.92)";
  ctx.arc(cx, cy, r, aMissionEnd, startAngle + Math.PI * 2);
  ctx.closePath();
  ctx.fill();

  // Separators + border
  ctx.strokeStyle = "rgba(0,0,0,0.18)";
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
    ctx.strokeStyle = "rgba(255,255,255,0.55)";
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
  drawSliceLabel(othersAngleMid, othersLabel, othersDetail, "rgba(0, 220, 140, 0.92)");

  // Two-line legend under the chart
  const t = document.getElementById("missionnairePieText");
  if (t) {
    t.innerHTML = `
      <div class="badge" style="display:inline-block; margin-bottom:8px; background: rgba(255, 255, 255, 0.06); border: 1px solid rgba(255, 255, 255, 0.16);">Total ${formatHours(total)}h</div>
      <div style="margin-top:4px;">
        <div style="display:flex; align-items:center; justify-content:space-between; gap:10px; padding:4px 0; border-top:1px solid rgba(255,255,255,0.08);">
          <div style="display:flex; align-items:center; gap:8px; min-width:0;">
            <span style="width:10px; height:10px; border-radius:2px; background:${C.accent}; flex:0 0 auto;"></span>
            <span style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${escapeHtml(missionName)}</span>
          </div>
          <div style="white-space:nowrap;">${formatHours(missionTotal)}h</div>
        </div>
        <div style="display:flex; align-items:center; justify-content:space-between; gap:10px; padding:4px 0; border-top:1px solid rgba(255,255,255,0.08);">
          <div style="display:flex; align-items:center; gap:8px; min-width:0;">
            <span style="width:10px; height:10px; border-radius:2px; background:rgba(0, 220, 140, 0.92); flex:0 0 auto;"></span>
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
    ctx.fillStyle = "rgba(0,0,0,0.22)";
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

  // Sort by remaining hours (lowest first), then by name for stability
  filtered.sort((a, b) => {
    const ra = Number(a.remaining_hours || 0);
    const rb = Number(b.remaining_hours || 0);
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


  function initAdminCoursesPage() {
    const form = document.getElementById("courseForm");
    if (!form) return;

    (async () => {
      initPageFiltersUI({ yearSelectId: "coursesYearFilter", semesterSelectId: "coursesSemesterFilter" });
      await loadDoctorsForCourseForm();
      await loadCourses();

      try {
        const gf = getGlobalFilters();
        const ySel = document.getElementById("year_level");
        const sSel = document.getElementById("semester");
        if (ySel && gf.year_level) ySel.value = String(gf.year_level);
        if (sSel && gf.semester) sSel.value = String(gf.semester);

        ySel?.addEventListener("change", () => {
          const next = getGlobalFilters();
          next.year_level = Number(ySel.value || 0) || 0;
          setGlobalFilters(next);
        });
        sSel?.addEventListener("change", () => {
          const next = getGlobalFilters();
          next.semester = Number(sSel.value || 0) || 0;
          setGlobalFilters(next);
        });
      } catch {
        // ignore
      }

      initAdminCourseCreateFormEnhancements();
      form.addEventListener("submit", handleCourseCreateSubmit);

      await maybeInitAdminCourseList(true);

      document.getElementById("refreshCoursesAdmin")?.addEventListener("click", async () => {
        await maybeInitAdminCourseList(true);
      });

      document.getElementById("courseSearch")?.addEventListener("input", (e) => {
        renderAdminCoursesList(e.target.value || "");
      });

      window.addEventListener("dmportal:globalFiltersChanged", () => {
        renderAdminCoursesList(document.getElementById("courseSearch")?.value || "");
      });

      document.querySelectorAll("#courseEditModal [data-close='1']")?.forEach((el) => {
        el.addEventListener("click", closeCourseEditModal);
      });
      document.getElementById("courseEditSave")?.addEventListener("click", saveCourseEditModal);
      document.addEventListener("keydown", (e) => {
        if (e.key === "Escape") closeCourseEditModal();
      });

      document.getElementById("adminCoursesList")?.addEventListener("change", async (e) => {
        const el = e.target;
        const action = el?.dataset?.action;
        const cid = el?.dataset?.courseId;
        if (!action || !cid) return;

        try {
          if (action === "doctor") {
            setStatusById("adminCoursesStatus", "Saving…");
            const val = el.value;
            const docId = val === "" ? null : Number(val);
            await updateCourse(cid, { doctor_id: docId });
            await setCourseDoctors(cid, docId ? [docId] : []);
            await loadCourses();
            renderAdminCoursesList(document.getElementById("courseSearch")?.value || "");
            setStatusById("adminCoursesStatus", "Saved.", "success");
          }
        } catch (err) {
          setStatusById("adminCoursesStatus", err.message, "error");
          await loadCourses();
          renderAdminCoursesList(document.getElementById("courseSearch")?.value || "");
        }
      });

      document.getElementById("adminCoursesList")?.addEventListener("blur", async (e) => {
        const el = e.target;
        const action = el?.dataset?.action;
        const cid = el?.dataset?.courseId;
        if (action !== "hours" || !cid) return;

        try {
          setStatusById("adminCoursesStatus", "Saving…");
          const hrs = Number(el.value);
          await updateCourse(cid, { course_hours: hrs });
          await loadCourses();
          renderAdminCoursesList(document.getElementById("courseSearch")?.value || "");
          setStatusById("adminCoursesStatus", "Saved.", "success");
        } catch (err) {
          setStatusById("adminCoursesStatus", err.message, "error");
          await loadCourses();
          renderAdminCoursesList(document.getElementById("courseSearch")?.value || "");
        }
      }, true);

      document.getElementById("adminCoursesList")?.addEventListener("click", async (e) => {
        const btn = e.target?.closest?.("button[data-action]");
        if (!btn) return;
        const action = btn.dataset.action;
        const cid = btn.dataset.courseId;
        if (!action || !cid) return;

        try {
          if (action === "edit") {
            openCourseEditModal(cid);
          }

          if (action === "delete") {
            const ok = confirm("Delete this course? This cannot be undone. (If it is scheduled, deletion will be blocked.)");
            if (!ok) return;
            setStatusById("adminCoursesStatus", "Deleting…");
            await deleteCourse(cid);
            await loadCourses();
            renderAdminCoursesList(document.getElementById("courseSearch")?.value || "");
            setStatusById("adminCoursesStatus", "Deleted.", "success");
          }
        } catch (err) {
          setStatusById("adminCoursesStatus", err.message, "error");
        }
      });
    })();
  }

  window.dmportal = window.dmportal || {};
  window.dmportal.initAdminCoursesPage = initAdminCoursesPage;
})();
