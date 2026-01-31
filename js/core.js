(function () {
  "use strict";

  async function fetchJson(url, options = {}) {
    const res = await fetch(url, options);
    let data;
    try {
      data = await res.json();
    } catch (e) {
      throw new Error(`Invalid JSON response from ${url}`);
    }

    if (!res.ok) {
      const msg = data?.error || data?.message || `Request failed (${res.status})`;
      throw new Error(msg);
    }

    return data;
  }

  function setStatusById(id, message, type = "") {
    const el = document.getElementById(id);
    if (!el) return;
    el.textContent = message;
    el.className = `status ${type}`.trim();
  }

  // -----------------------------
  // Page Filters (Year + Semester)
  // -----------------------------
  const PAGE_FILTERS_KEY_PREFIX = "dmportal_page_filters_v1:";
  let memoryPageFilters = { year_level: 0, semester: 0 };

  function normalizePageFilters(v) {
    const year_level = Number(v?.year_level || 0);
    const semester = Number(v?.semester || 0);
    return {
      year_level: year_level >= 1 && year_level <= 3 ? year_level : 0,
      semester: semester >= 1 && semester <= 2 ? semester : 0,
    };
  }

  function getPageFiltersStorageKey() {
    return PAGE_FILTERS_KEY_PREFIX + String(window.location.pathname || "");
  }

  function getPageFilters() {
    try {
      const raw = localStorage.getItem(getPageFiltersStorageKey());
      const parsed = raw ? JSON.parse(raw) : {};
      const clean = normalizePageFilters(parsed);
      memoryPageFilters = clean;
      return clean;
    } catch {
      return normalizePageFilters(memoryPageFilters);
    }
  }

  function setPageFilters(next) {
    const clean = normalizePageFilters(next);
    memoryPageFilters = clean;
    try {
      localStorage.setItem(getPageFiltersStorageKey(), JSON.stringify(clean));
    } catch {
      // ignore; memory fallback still works
    }

    window.dispatchEvent(new CustomEvent("dmportal:pageFiltersChanged", { detail: clean }));
    window.dispatchEvent(new CustomEvent("dmportal:globalFiltersChanged", { detail: clean }));
  }

  function getGlobalFilters() {
    return getPageFilters();
  }

  function setGlobalFilters(next) {
    return setPageFilters(next);
  }

  function applyGlobalFiltersToCourses(courses) {
    return applyPageFiltersToCourses(courses);
  }

  function doesItemMatchGlobalFilters(item) {
    return doesItemMatchPageFilters(item);
  }

  function applyPageFiltersToCourses(courses) {
    const { year_level, semester } = getEffectivePageFilters();
    return (courses || []).filter((c) => {
      if (year_level && Number(c.year_level) !== year_level) return false;
      if (semester && Number(c.semester) !== semester) return false;
      return true;
    });
  }

  function doesItemMatchPageFilters(item) {
    const { year_level, semester } = getEffectivePageFilters();
    if (year_level && Number(item?.year_level) !== year_level) return false;
    if (semester && Number(item?.semester) !== semester) return false;
    return true;
  }

  function getEffectivePageFilters() {
    const stored = getPageFilters();

    const yearEl = document.querySelector("[data-page-filter='year']");
    const semEl = document.querySelector("[data-page-filter='semester']");

    const next = { ...stored };

    if (yearEl && yearEl.value !== undefined) {
      const v = String(yearEl.value || "").trim();
      next.year_level = v ? Number(v) : 0;
    }

    if (semEl && semEl.value !== undefined) {
      const v = String(semEl.value || "").trim();
      next.semester = v ? Number(v) : 0;
    }

    return normalizePageFilters(next);
  }

  function initPageFiltersUI({ yearSelectId, semesterSelectId } = {}) {
    const yearSel = yearSelectId ? document.getElementById(yearSelectId) : null;
    const semSel = semesterSelectId ? document.getElementById(semesterSelectId) : null;
    if (!yearSel && !semSel) return;

    if (yearSel) yearSel.dataset.pageFilter = "year";
    if (semSel) semSel.dataset.pageFilter = "semester";
    const current = getPageFilters();
    if (yearSel) yearSel.value = current.year_level ? String(current.year_level) : "";
    if (semSel) semSel.value = current.semester ? String(current.semester) : "";

    function commit() {
      const next = getPageFilters();
      if (yearSel) next.year_level = yearSel.value ? Number(yearSel.value) : 0;
      if (semSel) next.semester = semSel.value ? Number(semSel.value) : 0;
      setPageFilters(next);
    }

    yearSel?.addEventListener("change", commit);
    yearSel?.addEventListener("input", commit);
    semSel?.addEventListener("change", commit);
    semSel?.addEventListener("input", commit);
  }

  function bindEasterEggGlobalShortcut() {
    const isIndex = /\/index\.php$/i.test(window.location.pathname) || window.location.pathname === "/";
    const isDashboard = document.body?.classList?.contains("course-dashboard");
    if (!isIndex && !isDashboard) return;

    let buffer = "";
    let timer;
    function reset() {
      buffer = "";
      if (timer) window.clearTimeout(timer);
      timer = window.setTimeout(() => (buffer = ""), 2000);
    }

    async function handleDigitEvent(e) {
      let digit = null;
      if (/^[0-9]$/.test(e.key)) digit = e.key;
      else if (e.code?.startsWith("Numpad")) {
        const num = e.code.replace("Numpad", "");
        if (/^[0-9]$/.test(num)) digit = num;
      }
      if (digit === null) return;

      buffer += digit;
      reset();
      if (!buffer.endsWith("700")) return;

      try {
        const res = await fetch("php/easter_egg_entry.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          credentials: "same-origin",
          body: JSON.stringify({ code: "700" }),
        });
        if (res.ok) {
          window.location.href = "ablaven.php";
        }
      } catch {
        // ignore
      }
    }

    document.addEventListener("keydown", handleDigitEvent, true);
    document.addEventListener("keypress", handleDigitEvent, true);
    document.addEventListener("keyup", handleDigitEvent, true);
    window.addEventListener("keydown", handleDigitEvent, true);
    window.addEventListener("keypress", handleDigitEvent, true);
    window.addEventListener("keyup", handleDigitEvent, true);

    const testInput = document.getElementById("easterEggInput");
    if (testInput) {
      testInput.addEventListener("input", async () => {
        const value = String(testInput.value || "").trim();
        if (value !== "700") return;
        try {
          const res = await fetch("php/easter_egg_entry.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            credentials: "same-origin",
            body: JSON.stringify({ code: "700" }),
          });
          if (res.ok) {
            window.location.href = "ablaven.php";
          }
        } catch {
          // ignore
        }
      });
    }
  }

  bindEasterEggGlobalShortcut();

  function initThemeToggle() {
    const root = document.documentElement;
    const stored = localStorage.getItem("dmportal-theme");
    const prefersLight = window.matchMedia && window.matchMedia("(prefers-color-scheme: light)").matches;
    const initial = stored || (prefersLight ? "light" : "dark");
    root.setAttribute("data-theme", initial);

    const toggle = document.getElementById("themeToggle");
    if (toggle) {
      toggle.innerHTML = initial === "light" ? "🌙" : "☀️";
      toggle.addEventListener("click", () => {
        const current = root.getAttribute("data-theme") === "light" ? "light" : "dark";
        const next = current === "light" ? "dark" : "light";
        root.setAttribute("data-theme", next);
        localStorage.setItem("dmportal-theme", next);
        toggle.innerHTML = next === "light" ? "🌙" : "☀️";
      });
    }
  }

  initThemeToggle();

  window.dmportal = window.dmportal || {};
  function escapeHtml(s) {
    return String(s)
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;");
  }

  function makeCourseLabel(courseType, subjectCode) {
    const t = String(courseType || "").trim().toUpperCase();
    const code = String(subjectCode || "").trim();
    return code ? `${t} ${code}` : t;
  }

  function parseDoctorIdsCsv(csv) {
    if (!csv) return [];
    return String(csv)
      .split(",")
      .map((x) => x.trim())
      .filter((x) => x !== "" && /^\d+$/.test(x))
      .map((x) => Number(x));
  }

  window.dmportal.fetchJson = fetchJson;
  window.dmportal.setStatusById = setStatusById;
  window.dmportal.escapeHtml = escapeHtml;
  window.dmportal.makeCourseLabel = makeCourseLabel;
  window.dmportal.parseDoctorIdsCsv = parseDoctorIdsCsv;
  window.dmportal.getPageFilters = getPageFilters;
  window.dmportal.setPageFilters = setPageFilters;
  window.dmportal.getGlobalFilters = getGlobalFilters;
  window.dmportal.setGlobalFilters = setGlobalFilters;
  window.dmportal.applyGlobalFiltersToCourses = applyGlobalFiltersToCourses;
  window.dmportal.doesItemMatchGlobalFilters = doesItemMatchGlobalFilters;
  window.dmportal.applyPageFiltersToCourses = applyPageFiltersToCourses;
  window.dmportal.doesItemMatchPageFilters = doesItemMatchPageFilters;
  window.dmportal.getEffectivePageFilters = getEffectivePageFilters;
  window.dmportal.initPageFiltersUI = initPageFiltersUI;
})();
