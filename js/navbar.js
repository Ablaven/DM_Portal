(function () {
  "use strict";

  const { fetchJson } = window.dmportal || {};

  function normalizePhoneForWhatsApp(phone) {
    const digits = String(phone || "").replace(/\D+/g, "");
    return digits.length >= 8 ? digits : "";
  }

  function buildWhatsAppSendUrl(phoneDigits, text) {
    const p = String(phoneDigits || "").trim();
    if (!p) return "";
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

    const q = params
      .map(([k, v]) => `${encodeURIComponent(k)}=${encodeURIComponent(String(v))}`)
      .join("&");

    return `mailto:${encodeURI(to)}${q ? "?" + q : ""}`;
  }

  function getDoctorFirstName(fullName) {
    const s = String(fullName || "").trim();
    if (!s) return "";
    const parts = s.split(/\s+/).filter(Boolean);
    if (parts.length === 0) return "";
    const first = parts[0].replace(/\.+$/, "");
    if (/^dr$/i.test(first) && parts[1]) return parts[1].replace(/\.+$/, "");
    return parts[0];
  }

  function buildDoctorScheduleGreetingText(doctorName) {
    const firstName = getDoctorFirstName(doctorName);
    const namePart = firstName ? ` ${firstName}` : "";
    const msg = `Dear Dr.${namePart}, I hope you are doing well. Please open your email to find your weekly schedule attached. Let me know if you need any changes or clarifications. Best regards,`;
    return msg.replace(/\n/g, "\r\n");
  }

  function buildAbsoluteUrl(relativeOrAbsolute) {
    try {
      return new URL(String(relativeOrAbsolute || ""), window.location.href).href;
    } catch {
      return String(relativeOrAbsolute || "");
    }
  }

  function buildDoctorScheduleExportUrl(doctorId, weekId) {
    if (!doctorId) return "";
    const qs = new URLSearchParams({ doctor_id: String(doctorId) });
    if (weekId) qs.set("week_id", String(weekId));
    return buildAbsoluteUrl(`php/export_doctor_week_xls.php?${qs.toString()}`);
  }

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

      window.setTimeout(() => {
        try {
          iframe.remove();
        } catch {
          // ignore
        }
      }, 60_000);
    } catch {
      try {
        window.open(href, "_blank", "noopener");
      } catch {
        // ignore
      }
    }
  }

  async function populateNavbarDoctors(state) {
    const nav = document.getElementById("doctorsNav");
    if (!nav) return;

    const btn = nav.querySelector("button");
    const menu = nav.querySelector(".dropdown");
    if (!btn || !menu) return;

    try {
      const payload = await fetchJson("php/get_doctors.php");
      const doctors = payload?.data || [];

      menu.innerHTML = "";
      if (!doctors.length) {
        const div = document.createElement("div");
        div.className = "dropdown-item muted";
        div.textContent = "No doctors";
        menu.appendChild(div);
        return;
      }

      for (const d of doctors) {
        const row = document.createElement("div");
        row.className = "dropdown-item dropdown-item-doctor";

        const nameLink = document.createElement("a");
        nameLink.className = "dropdown-doctor-name";
        nameLink.href = `doctor.php?doctor_id=${encodeURIComponent(d.doctor_id)}`;

        const rawName = String(d.full_name || d.fullName || d.doctor_name || d.name || "").trim();
        const fallbackName = rawName || (d.email ? String(d.email).trim() : "") || (d.doctor_id ? `Doctor #${d.doctor_id}` : "Doctor");
        nameLink.textContent = fallbackName || "Doctor";
        nameLink.title = rawName || fallbackName || "Doctor";

        const actions = document.createElement("div");
        actions.className = "dropdown-doctor-actions";

        const emailA = document.createElement("button");
        emailA.type = "button";
        emailA.className = "icon-btn icon-btn-small";
        emailA.title = "Email";
        emailA.setAttribute("aria-label", "Email");
        emailA.innerHTML = `<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path fill="currentColor" d="M20 4H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2Zm0 4-8 5-8-5V6l8 5 8-5v2Z"/></svg>`;

        const p = normalizePhoneForWhatsApp(d.phone_number);
        const waHref = p ? buildWhatsAppSendUrl(p, buildDoctorScheduleGreetingText(d.full_name)) : "";
        const waA = document.createElement("a");
        waA.className = "icon-btn icon-btn-small";
        waA.href = waHref || "";
        waA.target = "_blank";
        waA.rel = "noopener";
        waA.title = "WhatsApp";
        waA.setAttribute("aria-label", "WhatsApp");
        if (!waHref) waA.setAttribute("aria-disabled", "true");
        waA.innerHTML = `<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path fill="currentColor" d="M16.6 14.2c-.3-.2-1.7-.8-1.9-.9-.2-.1-.4-.2-.6.1l-.8.9c-.2.2-.3.2-.5.1-.3-.1-1.2-.4-2.2-1.4-.8-.7-1.3-1.6-1.4-1.9-.1-.3 0-.4.1-.6l.4-.4c.1-.1.2-.3.3-.4.1-.1.1-.3 0-.4-.1-.2-.6-1.5-.8-2-.2-.5-.4-.4-.6-.4h-.5c-.2 0-.4.1-.6.3-.2.2-.8.8-.8 1.9 0 1.1.8 2.2.9 2.3.1.2 1.6 2.5 4 3.4.6.2 1 .3 1.3.4.6.1 1.1.1 1.6.1.5-.1 1.7-.7 1.9-1.3.2-.6.2-1.1.1-1.2 0-.1-.2-.2-.4-.3zM12 2a10 10 0 0 0-8.5 15.3L2 22l4.9-1.5A10 10 0 1 0 12 2z"/></svg>`;

        emailA.addEventListener("click", async (e) => {
          e.preventDefault();
          e.stopPropagation();

          try {
            emailA.disabled = true;
            const payload = await fetchJson("php/email_doctor_schedule.php", {
              method: "POST",
              headers: { "Content-Type": "application/json" },
              body: JSON.stringify({
                doctor_id: d.doctor_id,
                week_id: state?.activeWeekId || 0,
              }),
            });

            if (payload?.success) {
              alert("Schedule emailed successfully.");
            } else {
              alert(payload?.error || "Failed to send email.");
            }
          } catch (err) {
            alert(err?.message || "Failed to send email.");
          } finally {
            emailA.disabled = false;
          }
        });
        waA.addEventListener("click", (e) => {
          if (waA.getAttribute("aria-disabled") === "true") e.preventDefault();
          e.stopPropagation();
        });

        actions.appendChild(emailA);
        actions.appendChild(waA);

        row.appendChild(nameLink);
        row.appendChild(actions);
        menu.appendChild(row);

        if (!rawName) {
          row.classList.add("doctor-name-missing");
        }
      }

      btn.addEventListener("click", (e) => {
        e.preventDefault();
        const isOpen = nav.classList.toggle("open");
        btn.setAttribute("aria-expanded", isOpen ? "true" : "false");
      });

      document.addEventListener("click", (e) => {
        if (!nav.contains(e.target)) {
          nav.classList.remove("open");
          btn.setAttribute("aria-expanded", "false");
        }
      });
    } catch (err) {
      menu.innerHTML = `<div class="dropdown-item muted">Failed to load</div>`;
    }
  }

  async function authFetchMe() {
    try {
      const payload = await fetchJson("php/auth_me.php");
      return payload?.data || null;
    } catch (err) {
      return null;
    }
  }

  function hideNavbarLinksByAllowedPages(allowedPages) {
    if (!Array.isArray(allowedPages)) return;
    const allowed = new Set(allowedPages.map((x) => String(x)));
    const links = document.querySelectorAll(".navlinks a");
    links.forEach((a) => {
      const href = a.getAttribute("href") || "";
      if (!href) return;

      if (href === "profile.php") {
        a.style.display = "";
        return;
      }

      const isInternal = !/^https?:\/\//i.test(href) && /\.php(\?|#|$)/i.test(href);
      const baseHref = href.split("?")[0];
      if (isInternal && allowed && !(allowed.has(href) || allowed.has(baseHref))) {
        a.style.display = "none";
      } else {
        a.style.display = "";
      }
    });
  }

  function bindFailsafeLogoutCode() {
    let clicks = 0;
    let timeoutId;

    function reset() {
      clicks = 0;
      if (timeoutId) clearTimeout(timeoutId);
    }

    document.addEventListener("keydown", (e) => {
      if (e.key !== "Escape") return;
      clicks += 1;
      if (clicks >= 5) {
        reset();
        window.location.href = "php/auth_logout.php";
        return;
      }
      if (timeoutId) clearTimeout(timeoutId);
      timeoutId = setTimeout(reset, 1500);
    });
  }

  async function bindLogoutButton() {
    const btn = document.getElementById("logoutBtn");
    if (!btn) return;
    btn.addEventListener("click", async () => {
      try {
        await fetchJson("php/auth_logout.php", { method: "POST" });
      } catch (err) {
        // ignore
      }
      window.location.href = "login.php";
    });
  }

  function initResponsiveNavbar() {
    const nav = document.querySelector(".navlinks");
    if (!nav) return;
    if (nav.dataset.bound === "1") return;
    nav.dataset.bound = "1";

    const toggle = document.createElement("button");
    toggle.className = "nav-toggle";
    toggle.type = "button";
    toggle.setAttribute("aria-expanded", "false");
    toggle.setAttribute("aria-label", "Toggle navigation menu");

    if (!nav.id) {
      nav.id = "navlinks";
    }
    toggle.setAttribute("aria-controls", nav.id);
    toggle.innerHTML = `<span></span><span></span><span></span>`;

    nav.parentElement?.insertBefore(toggle, nav);

    function setOpen(nextOpen) {
      const navbar = nav.closest(".navbar");
      nav.classList.toggle("open", nextOpen);
      navbar?.classList.toggle("nav-open", nextOpen);
      toggle.setAttribute("aria-expanded", nextOpen ? "true" : "false");

      if (nextOpen) {
        const firstFocusable = nav.querySelector("a, button, input, select, textarea, [tabindex]:not([tabindex='-1'])");
        if (firstFocusable) {
          window.setTimeout(() => firstFocusable.focus(), 60);
        }
      } else {
        toggle.focus();
      }
    }

    toggle.addEventListener("click", () => {
      setOpen(!nav.classList.contains("open"));
    });

    document.addEventListener("keydown", (e) => {
      if (e.key !== "Escape") return;
      if (!nav.classList.contains("open")) return;
      setOpen(false);
    });

    document.addEventListener("click", (e) => {
      if (!nav.classList.contains("open")) return;
      if (nav.contains(e.target) || toggle.contains(e.target)) return;
      setOpen(false);
    });
  }

  async function initNavbar(state) {
    initResponsiveNavbar();

    let me = null;
    try {
      me = await authFetchMe();
      if (me?.allowed_pages?.length) {
        hideNavbarLinksByAllowedPages(me.allowed_pages);
      }
    } catch {
      // ignore
    }

    await populateNavbarDoctors(state || {});
    bindFailsafeLogoutCode();
    bindLogoutButton();
  }

  window.dmportal = window.dmportal || {};
  window.dmportal.initNavbar = initNavbar;
  window.dmportal.normalizePhoneForWhatsApp = normalizePhoneForWhatsApp;
  window.dmportal.buildDoctorScheduleGreetingText = buildDoctorScheduleGreetingText;
  window.dmportal.buildWhatsAppSendUrl = buildWhatsAppSendUrl;
  window.dmportal.buildMailtoHref = buildMailtoHref;
  window.dmportal.buildDoctorScheduleExportUrl = buildDoctorScheduleExportUrl;
  window.dmportal.triggerBackgroundDownload = triggerBackgroundDownload;
})();
