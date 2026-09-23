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
    renderEmptyState,
    showSuccess,
    showError,
    showInfo,
    formatWeekDisplayLabel,
  } = window.dmportal || {};

  const state = { doctors: [], weeks: [], doctorTypes: ["Egyptian", "French"] };

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
      renderEmptyState(list, {
        icon: "\u{1F468}\u200D\u2695\uFE0F}",
        title: q ? "No doctors found" : "No doctors yet",
        subtitle: q ? "Try adjusting your search terms." : "Get started by adding your first doctor using the form above.",
        ctaText: q ? "" : "Refresh doctors",
        ctaOnClick: q ? null : () => { loadDoctors().then(renderDoctorsList); },
        className: "admin-doctors-empty"
      });
      return;
    }

    // Clear any existing empty state when there are results
    list.querySelectorAll(".empty-state").forEach(el => el.remove());

    list.innerHTML = "";
    for (const d of filtered) {
      const card = document.createElement("div");
      card.className = "doctor-card";

      const color = d.color_code || "#0055A4";
      const email = d.email ? escapeHtml(d.email) : "(no email)";
      const phone = d.phone_number ? escapeHtml(d.phone_number) : "";
      const doctorType = d.doctor_type || "Egyptian";
      
      // Type badge colors
      const typeColors = {
        Egyptian: 'background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3);',
        French: 'background: rgba(59, 130, 246, 0.15); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.3);'
      };
      const typeStyle = typeColors[doctorType] || typeColors.Egyptian;

      card.innerHTML = `
        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap;">
          <div style="flex: 1; min-width: 200px;">
            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
              <h3 style="margin: 0; font-size: 1.1rem; font-weight: 600;">${escapeHtml(d.full_name || "")}</h3>
              <span style="display: inline-block; padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; ${typeStyle}">${escapeHtml(doctorType)}</span>
              <span style="display: inline-block; padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 600; background: ${color}22; border: 1px solid ${color}88; color: ${color};">
                <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: ${color}; margin-right: 4px;"></span>
                ${escapeHtml(color)}
              </span>
            </div>
            <div style="margin-bottom: 6px; color: var(--muted);">${email}</div>
            <div style="display: flex; gap: 16px; flex-wrap: wrap; font-size: 0.9rem; color: var(--muted);">
              <span>Doctor ID: <strong style="color: var(--text);">${escapeHtml(d.doctor_id)}</strong></span>
              ${phone ? `<span>Phone: <strong style="color: var(--text);">${phone}</strong></span>` : '<span style="color: var(--muted);">No phone</span>'}
            </div>
          </div>
          <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <button class="btn btn-small btn-secondary" type="button" data-action="export" data-id="${escapeHtml(d.doctor_id)}">Export</button>
            <button class="btn btn-small btn-secondary" type="button" data-action="edit" data-id="${escapeHtml(d.doctor_id)}">Edit</button>
            <button class="btn btn-small btn-secondary" type="button" data-action="delete" data-id="${escapeHtml(d.doctor_id)}" style="border-color: rgba(239, 68, 68, 0.4); color: #ef4444;">Delete</button>
          </div>
        </div>
      `;

      list.appendChild(card);
    }
    calculateSummaryStats();
  }

  function calculateSummaryStats() {
    const total = state.doctors.length;
    const egyptian = state.doctors.filter(d => d.doctor_type === 'Egyptian').length;
    const french = state.doctors.filter(d => d.doctor_type === 'French').length;
    const withPhone = state.doctors.filter(d => d.phone_number && d.phone_number.trim() !== '').length;

    document.getElementById('doctorsTotalCount').textContent = total;
    document.getElementById('doctorsEgyptianCount').textContent = egyptian;
    document.getElementById('doctorsFrenchCount').textContent = french;
    document.getElementById('doctorsWithPhoneCount').textContent = withPhone;
  }

  async function openDoctorEditModal(doctor) {
    const modal = document.getElementById("doctorEditModal");
    if (!modal) return;

    document.getElementById("edit_doctor_id").value = String(doctor.doctor_id || "");
    document.getElementById("edit_doctor_full_name").value = doctor.full_name || "";
    document.getElementById("edit_doctor_email").value = doctor.email || "";
    document.getElementById("edit_doctor_phone").value = doctor.phone_number || "";
    document.getElementById("edit_doctor_color").value = doctor.color_code || "#0055A4";
    const typeInput = document.getElementById("edit_doctor_type");
    if (typeInput) {
      typeInput.value = doctor.doctor_type || "Egyptian";
    }

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
    fd.append("doctor_type", document.getElementById("edit_doctor_type")?.value || "Egyptian");
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
      if (!fd.get("doctor_type")) fd.set("doctor_type", "Egyptian");
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
        showSuccess("Doctor added successfully!", 3500);
        form.reset();
        const typeSelect = document.getElementById("doctor_type");
        if (typeSelect) typeSelect.value = "Egyptian";
        await loadDoctors();
        renderDoctorsList();
      } catch (err) {
        setStatusById("doctorStatus", err.message || "Failed to add doctor.", "error");
        showError(err.message || "Failed to add doctor.", 5000);
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
          showSuccess("Doctor deleted successfully!", 3500);
          await loadDoctors();
          renderDoctorsList();
        } catch (err) {
          setStatusById("adminDoctorsStatus", err.message || "Failed to delete doctor.", "error");
          showError(err.message || "Failed to delete doctor.", 5000);
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
        opt.textContent = formatWeekDisplayLabel ? formatWeekDisplayLabel(w) : String(w.label || `Week ${w.week_id}`);
        weekSel.appendChild(opt);
      }
    }

    await loadDoctors();
    renderDoctorsList();
  }

  window.dmportal = window.dmportal || {};
  window.dmportal.initAdminDoctorsPage = initAdminDoctorsPage;
})();
