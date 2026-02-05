(function () {
  "use strict";

  const {
    fetchJson,
    escapeHtml,
    setStatusById,
    normalizePhoneForWhatsApp,
    buildDoctorScheduleGreetingText,
    buildDoctorScheduleExportUrl,
    triggerBackgroundDownload,
    buildMailtoHref,
    buildWhatsAppSendUrl,
  } = window.dmportal || {};

  const state = { doctors: [], weeks: [] };

  async function loadDoctors() {
    const payload = await fetchJson("php/get_doctors.php");
    if (!payload.success) throw new Error(payload.error || "Failed to load doctors.");
    state.doctors = payload.data || [];
  }

  async function fetchDoctorYearColors(doctorId) {
    const payload = await fetchJson(`php/get_doctor_year_colors.php?doctor_id=${doctorId}`);
    if (!payload.success) throw new Error(payload.error || "Failed to load doctor year colors.");
    return payload.data || {};
  }

  async function saveDoctorYearColors(doctorId, colors) {
    const yearLevels = [1, 2, 3];
    await Promise.all(
      yearLevels.map(async (level) => {
        const fd = new FormData();
        fd.append("doctor_id", String(doctorId));
        fd.append("year_level", String(level));
        fd.append("color_code", colors[level] || colors.base || "#0055A4");
        const payload = await fetchJson("php/set_doctor_year_colors.php", { method: "POST", body: fd });
        if (!payload.success) throw new Error(payload.error || "Failed to save doctor year colors.");
      })
    );
  }

  async function loadWeeks() {
    const payload = await fetchJson("php/get_weeks.php");
    if (!payload.success) throw new Error(payload.error || "Failed to load weeks.");
    state.weeks = payload.data || [];
  }

  function renderDoctorsList() {
    const list = document.getElementById("adminDoctorsList");
    if (!list) return;

    const q = String(document.getElementById("doctorSearch")?.value || "").toLowerCase();
    const filtered = (state.doctors || []).filter((d) => {
      if (!q) return true;
      const hay = [d.full_name, d.email, d.phone_number].map((x) => (x ? String(x) : "")).join(" ").toLowerCase();
      return hay.includes(q);
    });

    if (!filtered.length) {
      list.innerHTML = '<div class="muted">No doctors found.</div>';
      return;
    }

    list.innerHTML = "";
    for (const d of filtered) {
      const card = document.createElement("div");
      card.className = "course-item";

      const color = d.color_code || "#0055A4";
      const email = d.email ? escapeHtml(d.email) : "(no email)";
      const phone = d.phone_number ? escapeHtml(d.phone_number) : "";

      card.innerHTML = `
        <div class="course-top">
          <div>
            <div class="course-title">${escapeHtml(d.full_name || "")}</div>
            <div class="muted" style="margin-top:4px;">${email}</div>
            ${phone ? `<div class=\"muted\" style=\"margin-top:4px;\">${phone}</div>` : ""}
          </div>
          <span class="badge" style="background:${escapeHtml(color)}22; border-color:${escapeHtml(color)}88; color:${escapeHtml(color)};">${escapeHtml(color)}</span>
        </div>
        <div class="actions" style="margin-top:10px; justify-content:space-between;">
          <div class="muted" style="font-size:0.85rem;">ID: ${escapeHtml(d.doctor_id)}</div>
          <div style="display:flex; gap:8px; flex-wrap:wrap;">
            <button class="btn btn-secondary btn-small" type="button" data-action="export" data-id="${escapeHtml(d.doctor_id)}">Export</button>
            <button class="btn btn-secondary btn-small" type="button" data-action="edit" data-id="${escapeHtml(d.doctor_id)}">Edit</button>
            <button class="btn btn-secondary btn-small" type="button" data-action="delete" data-id="${escapeHtml(d.doctor_id)}" style="border-color: rgba(255,106,122,.35);">Delete</button>
          </div>
        </div>
      `;

      list.appendChild(card);
    }
  }

  async function openDoctorEditModal(doctor) {
    const modal = document.getElementById("doctorEditModal");
    if (!modal) return;

    document.getElementById("edit_doctor_id").value = String(doctor.doctor_id || "");
    document.getElementById("edit_doctor_full_name").value = doctor.full_name || "";
    document.getElementById("edit_doctor_email").value = doctor.email || "";
    document.getElementById("edit_doctor_phone").value = doctor.phone_number || "";
    document.getElementById("edit_doctor_color").value = doctor.color_code || "#0055A4";

    document.getElementById("edit_doctor_color_y1").value = doctor.color_code || "#0055A4";
    document.getElementById("edit_doctor_color_y2").value = doctor.color_code || "#0055A4";
    document.getElementById("edit_doctor_color_y3").value = doctor.color_code || "#0055A4";

    try {
      const colorsPayload = await fetchDoctorYearColors(doctor.doctor_id);
      const baseColor = colorsPayload.base_color_code || doctor.color_code || "#0055A4";
      const yearColors = colorsPayload.year_colors || {};
      document.getElementById("edit_doctor_color_y1").value = yearColors["1"] || baseColor;
      document.getElementById("edit_doctor_color_y2").value = yearColors["2"] || baseColor;
      document.getElementById("edit_doctor_color_y3").value = yearColors["3"] || baseColor;
    } catch (err) {
      setStatusById("doctorEditStatus", err.message || "Failed to load doctor colors.", "error");
    }

    setStatusById("doctorEditStatus", "");
    modal.classList.add("open");
    modal.setAttribute("aria-hidden", "false");
  }

  function closeDoctorEditModal() {
    const modal = document.getElementById("doctorEditModal");
    if (!modal) return;
    modal.classList.remove("open");
    modal.setAttribute("aria-hidden", "true");
  }

  async function saveDoctorEditModal() {
    const id = document.getElementById("edit_doctor_id").value;
    if (!id) return;

    const fd = new FormData();
    fd.append("doctor_id", id);
    fd.append("full_name", document.getElementById("edit_doctor_full_name").value);
    fd.append("email", document.getElementById("edit_doctor_email").value);
    fd.append("phone_number", document.getElementById("edit_doctor_phone").value);
    fd.append("color_code", document.getElementById("edit_doctor_color").value);

    const yearColors = {
      1: document.getElementById("edit_doctor_color_y1").value,
      2: document.getElementById("edit_doctor_color_y2").value,
      3: document.getElementById("edit_doctor_color_y3").value,
      base: document.getElementById("edit_doctor_color").value,
    };

    try {
      setStatusById("doctorEditStatus", "Saving…");
      const payload = await fetchJson("php/update_doctor.php", { method: "POST", body: fd });
      if (!payload.success) throw new Error(payload.error || "Failed to update doctor.");
      await saveDoctorYearColors(id, yearColors);
      setStatusById("doctorEditStatus", "Saved.", "success");
      closeDoctorEditModal();
      await loadDoctors();
      renderDoctorsList();
    } catch (err) {
      setStatusById("doctorEditStatus", err.message || "Failed to update doctor.", "error");
    }
  }

  async function initAdminDoctorsPage() {
    const form = document.getElementById("doctorForm");
    if (!form) return;

    form.addEventListener("submit", async (e) => {
      e.preventDefault();
      setStatusById("doctorStatus", "Saving…");
      const fd = new FormData(form);
      try {
        const payload = await fetchJson("php/add_doctor.php", { method: "POST", body: fd });
        if (!payload.success) throw new Error(payload.error || "Failed to add doctor.");
        const doctorId = payload.data?.doctor_id;
        if (doctorId) {
          const yearColors = {
            1: document.getElementById("doctor_color_y1").value,
            2: document.getElementById("doctor_color_y2").value,
            3: document.getElementById("doctor_color_y3").value,
            base: document.getElementById("doctor_color").value,
          };
          await saveDoctorYearColors(doctorId, yearColors);
        }
        setStatusById("doctorStatus", "Saved.", "success");
        form.reset();
        await loadDoctors();
        renderDoctorsList();
      } catch (err) {
        setStatusById("doctorStatus", err.message || "Failed to add doctor.", "error");
      }
    });

    document.getElementById("refreshDoctorsAdmin")?.addEventListener("click", async () => {
      await loadDoctors();
      renderDoctorsList();
    });

    document.getElementById("doctorSearch")?.addEventListener("input", renderDoctorsList);

    document.querySelectorAll("#doctorEditModal [data-close='1']")?.forEach((el) => {
      el.addEventListener("click", closeDoctorEditModal);
    });
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape") closeDoctorEditModal();
    });
    document.getElementById("doctorEditSave")?.addEventListener("click", saveDoctorEditModal);

    document.getElementById("adminDoctorsList")?.addEventListener("click", async (e) => {
      const btn = e.target?.closest?.("button[data-action]");
      if (!btn) return;
      const action = btn.dataset.action;
      const id = Number(btn.dataset.id || 0);
      const doctor = (state.doctors || []).find((d) => Number(d.doctor_id) === id);
      if (!doctor) return;

      if (action === "edit") {
        openDoctorEditModal(doctor);
        return;
      }

      if (action === "export") {
        const weekSel = document.getElementById("doctorsWeekSelect");
        const weekId = weekSel?.value ? Number(weekSel.value) : null;
        const url = buildDoctorScheduleExportUrl(id, weekId || undefined);
        triggerBackgroundDownload(url);
        return;
      }

      if (action === "delete") {
        const ok = confirm("Delete this doctor? This cannot be undone.");
        if (!ok) return;
        try {
          setStatusById("adminDoctorsStatus", "Deleting…");
          const fd = new FormData();
          fd.append("doctor_id", String(id));
          const payload = await fetchJson("php/delete_doctor.php", { method: "POST", body: fd });
          if (!payload.success) throw new Error(payload.error || "Failed to delete doctor.");
          setStatusById("adminDoctorsStatus", "Deleted.", "success");
          await loadDoctors();
          renderDoctorsList();
        } catch (err) {
          setStatusById("adminDoctorsStatus", err.message || "Failed to delete doctor.", "error");
        }
      }
    });

    await loadWeeks();
    const weekSel = document.getElementById("doctorsWeekSelect");
    if (weekSel) {
      weekSel.innerHTML = "";
      for (const w of state.weeks) {
        const opt = document.createElement("option");
        opt.value = w.week_id;
        opt.textContent = `${w.label}${w.status === "active" ? " (active)" : ""}`;
        weekSel.appendChild(opt);
      }
    }

    await loadDoctors();
    renderDoctorsList();
  }

  window.dmportal = window.dmportal || {};
  window.dmportal.initAdminDoctorsPage = initAdminDoctorsPage;
})();
