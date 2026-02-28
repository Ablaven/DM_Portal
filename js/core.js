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
    // Only active on the course dashboard page
    const isIndex = /\/index\.php$/i.test(window.location.pathname) || window.location.pathname === "/";
    const isDashboard = document.body?.classList?.contains("course-dashboard");
    if (!isIndex && !isDashboard) return;

    let buffer = "";
    let timer = null;
    let triggered = false; // prevent double-fire

    function resetTimer() {
      if (timer) window.clearTimeout(timer);
      timer = window.setTimeout(() => { buffer = ""; }, 2000);
    }

    async function triggerEasterEgg() {
      if (triggered) return;
      triggered = true;
      buffer = "";
      try {
        const res = await fetch("php/easter_egg_entry.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          credentials: "same-origin",
          body: JSON.stringify({ code: "700" }),
        });
        if (res.ok) {
          window.location.href = "ablaven.php";
        } else {
          triggered = false; // allow retry if server rejected
        }
      } catch {
        triggered = false;
      }
    }

    // Only listen to keydown — keypress and keyup would triple-fire the same event
    document.addEventListener("keydown", function handleDigitKey(e) {
      // Skip if user is typing in an input/textarea/select
      const tag = document.activeElement?.tagName?.toLowerCase();
      if (tag === "input" || tag === "textarea" || tag === "select") return;

      let digit = null;
      if (/^[0-9]$/.test(e.key)) {
        digit = e.key;
      } else if (e.code?.startsWith("Numpad")) {
        const num = e.code.replace("Numpad", "");
        if (/^[0-9]$/.test(num)) digit = num;
      }
      if (digit === null) return;

      buffer += digit;
      resetTimer();

      if (buffer.endsWith("700")) {
        triggerEasterEgg();
      }
    });

    // Hidden input fallback (no placeholder hint — it's a secret)
    const testInput = document.getElementById("easterEggInput");
    if (testInput) {
      testInput.addEventListener("input", () => {
        const value = String(testInput.value || "").trim();
        if (value === "700") {
          testInput.value = "";
          triggerEasterEgg();
        }
      });
    }
  }

  // Bind after DOM is ready so easterEggInput lookup works
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", bindEasterEggGlobalShortcut);
  } else {
    bindEasterEggGlobalShortcut();
  }

  function initPageTransitions() {
    const prefersReduced = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;

    requestAnimationFrame(() => {
      document.body.classList.add("page-transition-ready");
    });

    if (prefersReduced) return;

    function isInternalLink(anchor) {
      if (!anchor || !anchor.href) return false;
      const url = new URL(anchor.href, window.location.href);
      if (url.origin !== window.location.origin) return false;
      return true;
    }

    document.addEventListener("click", (event) => {
      const anchor = event.target.closest("a");
      if (!anchor) return;
      if (anchor.hasAttribute("download")) return;
      if (anchor.target && anchor.target !== "_self") return;
      if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
      if (!isInternalLink(anchor)) return;

      const url = new URL(anchor.href, window.location.href);
      if (url.hash && url.pathname === window.location.pathname) return;

      event.preventDefault();
      document.body.classList.add("page-transitioning");
      window.setTimeout(() => {
        window.location.href = url.toString();
      }, 180);
    }, true);
  }

  initPageTransitions();

  // ─────────────────────────────────────────────
  // THEME GLITCH TRANSITION
  // Captures the page via a cloned frozen snapshot, then slices + displaces it
  // to create a real content-glitch effect as the theme switches underneath.
  // ─────────────────────────────────────────────
  function fireThemeGlitch() {
    if (window.matchMedia?.("(prefers-reduced-motion: reduce)").matches) return;

    const W = window.innerWidth;
    const H = window.innerHeight;
    const DPR = Math.max(1, window.devicePixelRatio || 1);
    const scrollY = window.scrollY || 0;

    // ── 1. Clone the entire page into a frozen screenshot div ──
    const snapshot = document.createElement("div");
    snapshot.style.cssText =
      "position:fixed;inset:0;z-index:99998;pointer-events:none;overflow:hidden;" +
      "width:" + W + "px;height:" + H + "px;";

    // Clone body content as a visually frozen layer
    const clone = document.body.cloneNode(true);
    clone.style.cssText =
      "position:absolute;top:" + (-scrollY) + "px;left:0;" +
      "width:" + W + "px;margin:0;pointer-events:none;" +
      "transform-origin:top left;";
    // Remove scripts and canvases from clone (they can't be cloned meaningfully)
    clone.querySelectorAll("script,canvas,video,audio,iframe").forEach(el => el.remove());
    snapshot.appendChild(clone);
    document.body.appendChild(snapshot);

    // ── 2. Canvas on top for RGB echo layers ──
    const cv = document.createElement("canvas");
    cv.width  = Math.floor(W * DPR);
    cv.height = Math.floor(H * DPR);
    cv.style.cssText =
      "position:fixed;inset:0;z-index:99999;pointer-events:none;" +
      "width:" + W + "px;height:" + H + "px;";
    document.body.appendChild(cv);
    const ctx = cv.getContext("2d");
    ctx.scale(DPR, DPR);

    // ── 3. Build glitch strip data ──
    // Big displaced slices (content displacement)
    const slices = Array.from({ length: 18 }, (_, i) => ({
      y:    Math.random() * H,
      h:    Math.random() * H * 0.12 + 4,
      dx:   (Math.random() - 0.5) * 80,   // how far slice shifts horizontally
      active: Math.random() > 0.3,         // not all active at once
    }));

    const DURATION = 500;
    const start = performance.now();

    // ── 4. Animation loop ──
    function frame(now) {
      const t  = Math.min(1, (now - start) / DURATION);

      // Intensity: spike hard at start, decay exponentially
      const intensity = Math.pow(1 - t, 1.8);

      ctx.clearRect(0, 0, W, H);

      // Re-randomise some slices each frame for jitter
      if (Math.random() < 0.6) {
        const s = slices[Math.floor(Math.random() * slices.length)];
        s.y  = Math.random() * H;
        s.h  = Math.random() * H * 0.12 + 4;
        s.dx = (Math.random() - 0.5) * 80;
        s.active = Math.random() > 0.25;
      }

      // ── Apply slice displacement to the snapshot clone ──
      // Reset all clip regions first
      clone.style.clip = "";
      clone.style.transform = "";

      // Apply displaced slices via absolutely-positioned cut strips
      // (remove old strips each frame)
      snapshot.querySelectorAll(".glitch-strip").forEach(el => el.remove());

      for (const s of slices) {
        if (!s.active || intensity < 0.05) continue;
        // Clamp displacement by intensity
        const dx = s.dx * intensity;
        if (Math.abs(dx) < 1) continue;

        // Create a strip that shows a shifted slice of the clone
        const strip = document.createElement("div");
        strip.className = "glitch-strip";
        strip.style.cssText =
          "position:absolute;" +
          "top:" + s.y + "px;" +
          "left:0;right:0;" +
          "height:" + s.h + "px;" +
          "overflow:hidden;" +
          "z-index:2;pointer-events:none;";

        const inner = clone.cloneNode(true);
        inner.style.cssText =
          "position:absolute;" +
          "top:" + (-s.y) + "px;" +
          "left:" + dx + "px;" +
          "width:" + W + "px;" +
          "margin:0;pointer-events:none;";
        inner.querySelectorAll("script,canvas,video,audio,iframe").forEach(el => el.remove());
        strip.appendChild(inner);
        snapshot.appendChild(strip);
      }

      // ── RGB echo strips on canvas (cyan left ghost, pink right ghost) ──
      for (const s of slices) {
        if (!s.active) continue;
        const a = intensity * 0.28;
        if (a < 0.01) continue;

        // Cyan echo shifted left
        ctx.fillStyle = "rgba(92,242,255," + a + ")";
        ctx.fillRect(0, s.y, W, Math.max(1, s.h * 0.4));

        // Pink echo shifted right
        ctx.fillStyle = "rgba(255,102,216," + (a * 0.8) + ")";
        ctx.fillRect(0, s.y + s.h * 0.55, W, Math.max(1, s.h * 0.3));
      }

      // ── Scanline overlay ──
      const scanAlpha = intensity * 0.10;
      if (scanAlpha > 0.005) {
        for (let y = 0; y < H; y += 3) {
          ctx.fillStyle = "rgba(0,0,0," + scanAlpha + ")";
          ctx.fillRect(0, y, W, 1);
        }
      }

      // ── Bright flash at the very start ──
      if (t < 0.06) {
        const flashA = (0.06 - t) / 0.06 * 0.55;
        ctx.fillStyle = "rgba(255,255,255," + flashA + ")";
        ctx.fillRect(0, 0, W, H);
      }

      // ── Neon tear lines ──
      const tears = Math.floor(intensity * 8);
      for (let i = 0; i < tears; i++) {
        const ty = Math.random() * H;
        const col = i % 3 === 0 ? "92,242,255" : i % 3 === 1 ? "162,64,255" : "255,102,216";
        ctx.fillStyle = "rgba(" + col + "," + (intensity * 0.9) + ")";
        ctx.fillRect(0, ty, W, 1);
        // Double line occasionally
        if (Math.random() < 0.3) {
          ctx.fillStyle = "rgba(255,255,255," + (intensity * 0.4) + ")";
          ctx.fillRect(0, ty + 1, W, 1);
        }
      }

      // ── Fade out the snapshot as intensity drops ──
      snapshot.style.opacity = intensity.toFixed(3);

      if (t < 1) {
        requestAnimationFrame(frame);
      } else {
        snapshot.remove();
        cv.remove();
      }
    }

    requestAnimationFrame(frame);
  }

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
        fireThemeGlitch();
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
