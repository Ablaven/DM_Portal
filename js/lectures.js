(function () {
  "use strict";

  const {
    fetchJson,
    setStatusById,
    escapeHtml,
    showError,
    showSuccess,
    renderEmptyState,
  } = window.dmportal || {};

  // Authenticated user object — populated in initLecturesPage and shared across views.
  let currentUser = null;

  // ─────────────────────────────────────────────────────────────────
  // STUB VIEW INITIALISERS — implementations added in tasks 7.2 – 7.4
  // ─────────────────────────────────────────────────────────────────

  // -----------------------------------------------------------------
  // PROFESSOR VIEW  (Task 7.2)
  // -----------------------------------------------------------------

  function initProfessorView() {
    const root = document.getElementById("lecturesRoot");
    if (!root) return;
    root.innerHTML = "";
    
    // Enhanced header
    const header = document.createElement("div");
    header.className = "lectures-header";
    const headerContent = document.createElement("div");
    headerContent.className = "lectures-header-content";
    const title = document.createElement("h1");
    title.className = "lectures-title";
    title.textContent = "Lecture Materials";
    const subtitle = document.createElement("p");
    subtitle.className = "lectures-subtitle";
    subtitle.textContent = "Select a year level to view and manage your course materials";
    headerContent.appendChild(title);
    headerContent.appendChild(subtitle);
    header.appendChild(headerContent);
    root.appendChild(header);
    
    const section = document.createElement("div");
    section.className = "lectures-section";
    const sectionHeader = document.createElement("div");
    sectionHeader.className = "lectures-section-header";
    const sectionTitle = document.createElement("h2");
    sectionTitle.className = "lectures-section-title";
    sectionTitle.textContent = "Select Year Level";
    sectionHeader.appendChild(sectionTitle);
    section.appendChild(sectionHeader);
    root.appendChild(section);
    const yearGrid = document.createElement("div");
    yearGrid.className = "lectures-grid";
    root.appendChild(yearGrid);
    [1, 2, 3].forEach(function (year) {
      const card = document.createElement("button");
      card.className = "card card-clickable year-selector-card";
      card.type = "button";
      card.setAttribute("aria-label", "Year " + year);
      const iconEl = document.createElement("span");
      iconEl.className = "selector-card-icon";
      iconEl.setAttribute("aria-hidden", "true");
      iconEl.textContent = "📅";
      const labelEl = document.createElement("span");
      labelEl.className = "selector-card-label";
      labelEl.textContent = "Year " + year;
      card.appendChild(iconEl);
      card.appendChild(labelEl);
      card.addEventListener("click", function () { renderProfessorSemesterSelection(year); });
      yearGrid.appendChild(card);
    });
  }

  function renderProfessorSemesterSelection(year) {
    const root = document.getElementById("lecturesRoot");
    if (!root) return;
    root.innerHTML = "";
    root.appendChild(makeProfessorBackBtn("← Back to Years", initProfessorView));
    const heading = document.createElement("h2");
    heading.className = "section-heading";
    heading.textContent = "Year " + escapeHtml(String(year)) + " — Select Semester";
    root.appendChild(heading);
    const semGrid = document.createElement("div");
    semGrid.className = "lectures-grid";
    root.appendChild(semGrid);
    [1, 2].forEach(function (sem) {
      const card = document.createElement("button");
      card.className = "card card-clickable year-selector-card";
      card.type = "button";
      card.setAttribute("aria-label", "Semester " + sem);
      const iconEl = document.createElement("span");
      iconEl.className = "selector-card-icon";
      iconEl.setAttribute("aria-hidden", "true");
      iconEl.textContent = "📖";
      const labelEl = document.createElement("span");
      labelEl.className = "selector-card-label";
      labelEl.textContent = "Semester " + sem;
      card.appendChild(iconEl);
      card.appendChild(labelEl);
      card.addEventListener("click", function () { loadProfessorCourses(year, sem); });
      semGrid.appendChild(card);
    });
  }

  async function loadProfessorCourses(year, sem) {
    const root = document.getElementById("lecturesRoot");
    if (!root) return;
    root.innerHTML = "";
    root.appendChild(
      makeProfessorBackBtn(
        "← Back to Year " + escapeHtml(String(year)) + " Semesters",
        function () { renderProfessorSemesterSelection(year); }
      )
    );
    const heading = document.createElement("h2");
    heading.className = "section-heading";
    heading.textContent =
      "Year " + escapeHtml(String(year)) +
      ", Semester " + escapeHtml(String(sem)) +
      " — My Courses";
    root.appendChild(heading);
    const loadingEl = document.createElement("p");
    loadingEl.className = "status";
    loadingEl.textContent = "Loading courses…";
    root.appendChild(loadingEl);
    let doctors;
    try {
      const params = new URLSearchParams({
        year_level: String(year),
        semester: String(sem),
        program: "Digital Marketing",
      });
      const resp = await fetchJson("php/get_lecture_doctors.php?" + params);
      doctors = Array.isArray(resp && resp.data) ? resp.data : [];
    } catch (err) {
      loadingEl.remove();
      const errEl = document.createElement("p");
      errEl.className = "status status-error";
      errEl.textContent = "Failed to load courses. Please try again.";
      root.appendChild(errEl);
      return;
    }
    loadingEl.remove();
    const doctorId = Number(currentUser && currentUser.doctor_id || 0);
    const myEntry = doctors.find(function (d) { return Number(d.doctor_id) === doctorId; });
    const courses = myEntry ? (Array.isArray(myEntry.courses) ? myEntry.courses : []) : [];
    if (!courses.length) {
      renderEmptyState(root, {
        icon: "📭",
        title: "No courses assigned",
        subtitle: "No courses assigned for this year and semester.",
      });
      return;
    }
    const courseGrid = document.createElement("div");
    courseGrid.className = "lectures-grid";
    root.appendChild(courseGrid);
    courses.forEach(function (course) {
      const card = document.createElement("button");
      card.className = "card card-clickable year-selector-card";
      card.type = "button";
      card.setAttribute("aria-label", "Open course " + escapeHtml(String(course.course_name || "")));
      const iconEl = document.createElement("span");
      iconEl.className = "selector-card-icon";
      iconEl.setAttribute("aria-hidden", "true");
      iconEl.textContent = "🗂️";
      const labelEl = document.createElement("span");
      labelEl.className = "selector-card-label";
      labelEl.textContent = course.course_name || "Unnamed Course";
      card.appendChild(iconEl);
      card.appendChild(labelEl);
      if (course.subject_code) {
        const subEl = document.createElement("span");
        subEl.className = "selector-card-sub";
        subEl.textContent = course.subject_code;
        card.appendChild(subEl);
      }
      card.addEventListener("click", function () {
        renderProfessorCourseDetail(
          Number(course.course_id),
          course.course_name || "Course",
          year,
          sem
        );
      });
      courseGrid.appendChild(card);
    });
  }

  async function renderProfessorCourseDetail(courseId, courseName, year, sem) {
    const root = document.getElementById("lecturesRoot");
    if (!root) return;
    root.innerHTML = "";
    root.appendChild(
      makeProfessorBackBtn("← Back to Courses", function () { loadProfessorCourses(year, sem); })
    );
    const heading = document.createElement("h2");
    heading.className = "section-heading";
    heading.textContent = "📁 " + escapeHtml(courseName);
    root.appendChild(heading);
    // Upload section
    const uploadSection = document.createElement("section");
    uploadSection.className = "lectures-upload-section";
    const uploadTitle = document.createElement("h3");
    uploadTitle.className = "subsection-heading";
    uploadTitle.textContent = "Upload Material";
    uploadSection.appendChild(uploadTitle);
    const form = document.createElement("form");
    form.className = "lectures-upload-form";
    const fileLabel = document.createElement("label");
    fileLabel.htmlFor = "lmFileInput";
    fileLabel.className = "file-input-label";
    fileLabel.textContent = "Choose file (.pdf or .pptx, max 50 MB):";
    const fileInput = document.createElement("input");
    fileInput.type = "file";
    fileInput.id = "lmFileInput";
    fileInput.name = "file";
    fileInput.accept = ".pdf,.pptx";
    fileInput.className = "file-input";
    fileInput.required = true;
    const sizeNote = document.createElement("p");
    sizeNote.className = "file-size-note";
    sizeNote.textContent = "Maximum file size: 50 MB. Allowed types: PDF, PPTX.";
    const uploadStatusEl = document.createElement("p");
    uploadStatusEl.className = "status";
    uploadStatusEl.style.display = "none";
    uploadStatusEl.setAttribute("aria-live", "polite");
    const submitBtn = document.createElement("button");
    submitBtn.type = "submit";
    submitBtn.className = "btn btn-primary";
    submitBtn.textContent = "Upload";
    form.appendChild(fileLabel);
    form.appendChild(fileInput);
    form.appendChild(sizeNote);
    form.appendChild(uploadStatusEl);
    form.appendChild(submitBtn);
    uploadSection.appendChild(form);
    root.appendChild(uploadSection);
    // Materials section
    const materialsSection = document.createElement("section");
    materialsSection.className = "lectures-materials-section";
    const materialsTitle = document.createElement("h3");
    materialsTitle.className = "subsection-heading";
    materialsTitle.textContent = "Uploaded Materials";
    materialsSection.appendChild(materialsTitle);
    const materialsContainer = document.createElement("div");
    materialsContainer.className = "lectures-materials-container";
    materialsSection.appendChild(materialsContainer);
    root.appendChild(materialsSection);
    form.addEventListener("submit", async function (e) {
      e.preventDefault();
      await handleProfessorUpload(courseId, fileInput, submitBtn, uploadStatusEl, function () {
        return refreshProfessorMaterials(courseId, materialsContainer);
      });
    });
    await refreshProfessorMaterials(courseId, materialsContainer);
  }

  async function refreshProfessorMaterials(courseId, container) {
    container.innerHTML = "";
    const loadingEl = document.createElement("p");
    loadingEl.className = "status";
    loadingEl.textContent = "Loading materials…";
    container.appendChild(loadingEl);
    let materials;
    try {
      const resp = await fetchJson(
        "php/get_lecture_materials.php?course_id=" + encodeURIComponent(courseId)
      );
      materials = Array.isArray(resp && resp.data) ? resp.data : [];
    } catch (err) {
      loadingEl.remove();
      const errEl = document.createElement("p");
      errEl.className = "status status-error";
      errEl.textContent = err && err.message ? err.message : "Failed to load materials.";
      container.appendChild(errEl);
      return;
    }
    loadingEl.remove();
    if (!materials.length) {
      renderEmptyState(container, {
        icon: "📄",
        title: "No files yet",
        subtitle: "No files have been uploaded yet.",
      });
      return;
    }
    const tableWrap = document.createElement("div");
    tableWrap.className = "table-responsive";
    const table = document.createElement("table");
    table.className = "data-table";
    table.setAttribute("role", "table");
    const thead = document.createElement("thead");
    thead.innerHTML =
      "<tr>" +
      "<th scope=\"col\">Filename</th>" +
      "<th scope=\"col\">Type</th>" +
      "<th scope=\"col\">Size</th>" +
      "<th scope=\"col\">Uploaded by</th>" +
      "<th scope=\"col\">Actions</th>" +
      "</tr>";
    table.appendChild(thead);
    const tbody = document.createElement("tbody");
    materials.forEach(function (material) {
      const sizeKb = (Number(material.file_size_bytes || 0) / 1024).toFixed(1) + " KB";
      const typeLabel = String(material.file_type || "").toUpperCase();
      const tr = document.createElement("tr");
      const tdName = document.createElement("td");
      tdName.textContent = material.original_filename || "";
      const tdType = document.createElement("td");
      tdType.textContent = typeLabel;
      const tdSize = document.createElement("td");
      tdSize.textContent = sizeKb;
      const tdUploader = document.createElement("td");
      tdUploader.textContent = material.uploader_name || "";
      const tdActions = document.createElement("td");
      tdActions.className = "actions-cell";
      const previewBtn = document.createElement("button");
      previewBtn.type = "button";
      previewBtn.className = "btn btn-secondary btn-sm";
      previewBtn.textContent = "Preview";
      previewBtn.setAttribute(
        "aria-label",
        "Preview " + escapeHtml(String(material.original_filename || ""))
      );
      previewBtn.addEventListener("click", function () { openPreview(material); });
      const downloadBtn = document.createElement("button");
      downloadBtn.type = "button";
      downloadBtn.className = "btn btn-secondary btn-sm";
      downloadBtn.textContent = "Download";
      downloadBtn.setAttribute(
        "aria-label",
        "Download " + escapeHtml(String(material.original_filename || ""))
      );
      downloadBtn.addEventListener("click", function () { triggerDownload(material.material_id); });
      tdActions.appendChild(previewBtn);
      tdActions.appendChild(downloadBtn);
      const myDoctorId = Number(currentUser && currentUser.doctor_id || 0);
      if (Number(material.doctor_id) === myDoctorId) {
        const deleteBtn = document.createElement("button");
        deleteBtn.type = "button";
        deleteBtn.className = "btn btn-danger btn-sm";
        deleteBtn.textContent = "Delete";
        deleteBtn.setAttribute(
          "aria-label",
          "Delete " + escapeHtml(String(material.original_filename || ""))
        );
        (function (mat, btn) {
          btn.addEventListener("click", function () {
            handleProfessorDelete(mat, container, courseId, btn);
          });
        }(material, deleteBtn));
        tdActions.appendChild(deleteBtn);
      }
      tr.appendChild(tdName);
      tr.appendChild(tdType);
      tr.appendChild(tdSize);
      tr.appendChild(tdUploader);
      tr.appendChild(tdActions);
      tbody.appendChild(tr);
    });
    table.appendChild(tbody);
    tableWrap.appendChild(table);
    container.appendChild(tableWrap);
  }

  async function handleProfessorDelete(material, container, courseId, deleteBtn) {
    if (!confirm("Delete \"" + material.original_filename + "\"? This cannot be undone.")) return;
    deleteBtn.disabled = true;
    deleteBtn.textContent = "Deleting…";
    try {
      await fetchJson("php/delete_lecture_material.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        credentials: "same-origin",
        body: JSON.stringify({ material_id: material.material_id }),
      });
      showSuccess("File deleted successfully.");
      await refreshProfessorMaterials(courseId, container);
    } catch (err) {
      showError(err && err.message ? err.message : "Failed to delete file.");
      deleteBtn.disabled = false;
      deleteBtn.textContent = "Delete";
    }
  }

  async function handleProfessorUpload(courseId, fileInput, submitBtn, statusEl, onSuccess) {
    const file = fileInput.files && fileInput.files[0];
    if (!file) {
      statusEl.textContent = "Please select a file to upload.";
      statusEl.className = "status status-error";
      statusEl.style.display = "";
      return;
    }
    // Client-side size check — do NOT POST if over 50 MB
    if (file.size > 52428800) {
      statusEl.textContent = "File exceeds the 50 MB size limit. Please choose a smaller file.";
      statusEl.className = "status status-error";
      statusEl.style.display = "";
      return;
    }
    submitBtn.disabled = true;
    submitBtn.textContent = "Uploading…";
    statusEl.textContent = "Uploading, please wait…";
    statusEl.className = "status";
    statusEl.style.display = "";
    const formData = new FormData();
    formData.append("course_id", String(courseId));
    formData.append("file", file);
    try {
      const resp = await fetch("php/upload_lecture_material.php", {
        method: "POST",
        credentials: "same-origin",
        body: formData,
      });
      let data;
      try { data = await resp.json(); } catch (_) { throw new Error("Invalid server response."); }
      if (!resp.ok || !data.success) {
        throw new Error(data.error || "Upload failed (HTTP " + resp.status + ").");
      }
      statusEl.textContent = "File uploaded successfully.";
      statusEl.className = "status status-success";
      statusEl.style.display = "";
      fileInput.value = "";
      if (typeof onSuccess === "function") { await onSuccess(); }
    } catch (err) {
      const msg = err && err.message ? err.message : "Upload failed. Please try again.";
      showError(msg);
      statusEl.textContent = msg;
      statusEl.className = "status status-error";
      statusEl.style.display = "";
    } finally {
      submitBtn.disabled = false;
      submitBtn.textContent = "Upload";
    }
  }

  function makeProfessorBackBtn(label, onClick) {
    const btn = document.createElement("button");
    btn.type = "button";
    btn.className = "btn btn-secondary back-btn";
    btn.textContent = label;
    btn.addEventListener("click", onClick);
    return btn;
  }


  // ─────────────────────────────────────────────────────────────────
  // ADMIN VIEW  (Task 7.3)
  // ─────────────────────────────────────────────────────────────────

  /**
   * Pure filter function.
   * Requirements 13.3, 13.7
   *
   * @param {Array}  materials  Full list of material objects.
   * @param {Object} filters    { yearLevel: string, semester: string, search: string }
   * @returns {Array} Subset of materials matching every active filter.
   */
  function applyAdminFilters(materials, filters) {
    const { yearLevel, semester, search } = filters;
    const needle = String(search || "").trim().toLowerCase();

    return materials.filter(function (m) {
      // Year level filter (skip when "All")
      if (yearLevel && yearLevel !== "All") {
        if (String(m.year_level) !== String(yearLevel)) return false;
      }
      // Semester filter (skip when "All")
      if (semester && semester !== "All") {
        if (String(m.semester) !== String(semester)) return false;
      }
      // Text search — case-insensitive against filename and course name
      if (needle) {
        const filename = String(m.original_filename || "").toLowerCase();
        const courseName = String(m.course_name || "").toLowerCase();
        if (!filename.includes(needle) && !courseName.includes(needle)) return false;
      }
      return true;
    });
  }

  /**
   * Format a byte count as KB rounded to 1 decimal place.
   * e.g. 1048576 → "1024.0 KB"
   */
  function formatKb(bytes) {
    const kb = Number(bytes || 0) / 1024;
    return kb.toFixed(1) + " KB";
  }

  /**
   * Format an ISO datetime string into a human-readable local date.
   */
  function formatAdminDate(isoString) {
    if (!isoString) return "—";
    const d = new Date(isoString);
    if (isNaN(d.getTime())) return escapeHtml(String(isoString));
    return d.toLocaleDateString(undefined, { year: "numeric", month: "short", day: "numeric" });
  }

  /**
   * Render the materials table body rows for the admin view.
   * All user-supplied strings are passed through escapeHtml.
   * Requirements 13.2, 13.5, 13.6, 14.1
   */
  function renderAdminTable(materials, tbody) {
    if (!tbody) return;

    if (!materials || materials.length === 0) {
      tbody.innerHTML =
        '<tr><td colspan="9" class="muted" style="text-align:center;padding:1.5rem;">No materials found.</td></tr>';
      return;
    }

    const rows = materials.map(function (m) {
      const mid = encodeURIComponent(m.material_id);
      return (
        "<tr>" +
        '<td class="lm-col-filename">' + escapeHtml(m.original_filename || "") + "</td>" +
        '<td class="lm-col-type">'     + escapeHtml(String(m.file_type || "").toUpperCase()) + "</td>" +
        '<td class="lm-col-size">'     + escapeHtml(formatKb(m.file_size_bytes)) + "</td>" +
        '<td class="lm-col-course">'   + escapeHtml(m.course_name || "—") + "</td>" +
        '<td class="lm-col-year">'     + escapeHtml(String(m.year_level ?? "—")) + "</td>" +
        '<td class="lm-col-sem">'      + escapeHtml(String(m.semester ?? "—")) + "</td>" +
        '<td class="lm-col-uploader">' + escapeHtml(m.uploader_name || "—") + "</td>" +
        '<td class="lm-col-date">'     + escapeHtml(formatAdminDate(m.created_at)) + "</td>" +
        '<td class="lm-col-actions">' +
          '<button class="btn btn-sm btn-secondary lm-btn-preview"  data-id="' + mid + '" data-type="' + escapeHtml(m.file_type || "") + '" data-name="' + escapeHtml(m.original_filename || "") + '" aria-label="Preview ' + escapeHtml(m.original_filename || "") + '">Preview</button> ' +
          '<button class="btn btn-sm btn-secondary lm-btn-download" data-id="' + mid + '" aria-label="Download ' + escapeHtml(m.original_filename || "") + '">Download</button> ' +
          '<button class="btn btn-sm btn-danger    lm-btn-delete"   data-id="' + mid + '" aria-label="Delete ' + escapeHtml(m.original_filename || "") + '">Delete</button> ' +
          '<button class="btn btn-sm btn-primary   lm-btn-move"     data-id="' + mid + '" data-courseid="' + encodeURIComponent(m.course_id) + '" aria-label="Move ' + escapeHtml(m.original_filename || "") + '">Move</button>' +
        "</td>" +
        "</tr>"
      );
    });

    tbody.innerHTML = rows.join("");
  }

  /**
   * Build and inject the Move Material modal into the DOM (or reuse existing).
   * Requirement 14.2
   */
  function getOrCreateMoveModal() {
    let modal = document.getElementById("lmMoveModal");
    if (modal) return modal;

    modal = document.createElement("div");
    modal.id = "lmMoveModal";
    modal.className = "modal-overlay";
    modal.setAttribute("role", "dialog");
    modal.setAttribute("aria-modal", "true");
    modal.setAttribute("aria-labelledby", "lmMoveModalTitle");
    modal.style.display = "none";
    modal.innerHTML = [
      '<div class="modal-box">',
        '<h2 id="lmMoveModalTitle" class="modal-title">Move Material to Another Course</h2>',
        '<label for="lmMoveCourseSearch" class="form-label">Search courses</label>',
        '<input id="lmMoveCourseSearch" class="form-control" type="text" placeholder="Type to filter…" autocomplete="off" />',
        '<label for="lmMoveCourseSelect" class="form-label" style="margin-top:.75rem;">Select target course</label>',
        '<select id="lmMoveCourseSelect" class="form-control" size="6" style="height:auto;min-height:8rem;"></select>',
        '<p id="lmMoveModalStatus" class="status" style="min-height:1.4em;margin-top:.5rem;"></p>',
        '<div class="modal-actions" style="margin-top:1rem;display:flex;gap:.5rem;justify-content:flex-end;">',
          '<button id="lmMoveCancelBtn"  class="btn btn-secondary">Cancel</button>',
          '<button id="lmMoveConfirmBtn" class="btn btn-primary">Move</button>',
        "</div>",
      "</div>",
    ].join("");

    document.body.appendChild(modal);
    return modal;
  }

  /**
   * Open the Move modal for a given materialId / currentCourseId.
   * Fetches the full course list, populates a searchable <select>,
   * then POSTs to move_lecture_material.php on confirm.
   * Requirements 14.2, 14.3
   */
  function showMoveMaterialModal(materialId, currentCourseId, onSuccess) {
    const modal       = getOrCreateMoveModal();
    const searchInput = document.getElementById("lmMoveCourseSearch");
    const select      = document.getElementById("lmMoveCourseSelect");
    const statusEl    = document.getElementById("lmMoveModalStatus");
    const confirmBtn  = document.getElementById("lmMoveConfirmBtn");
    const cancelBtn   = document.getElementById("lmMoveCancelBtn");

    // Reset state
    searchInput.value = "";
    statusEl.textContent = "";
    statusEl.className = "status";
    select.innerHTML = '<option disabled>Loading courses…</option>';
    confirmBtn.disabled = true;
    modal.style.display = "flex";

    let allCourses = [];

    function applyMoveSearch(term) {
      const needle = String(term || "").trim().toLowerCase();
      const filtered = needle
        ? allCourses.filter(function (c) {
            return String(c.course_name || "").toLowerCase().includes(needle);
          })
        : allCourses;

      if (filtered.length === 0) {
        select.innerHTML = '<option disabled>No courses match.</option>';
        confirmBtn.disabled = true;
        return;
      }

      select.innerHTML = filtered
        .map(function (c) {
          const label =
            escapeHtml(c.course_name || "") +
            " (Y" + escapeHtml(String(c.year_level ?? "")) +
            " S" + escapeHtml(String(c.semester ?? "")) + ")";
          return '<option value="' + encodeURIComponent(c.course_id) + '">' + label + "</option>";
        })
        .join("");

      // Pre-select first option
      if (filtered.length > 0) {
        select.selectedIndex = 0;
        confirmBtn.disabled = false;
      }
    }

    searchInput.addEventListener("input", function () {
      applyMoveSearch(searchInput.value);
    });

    select.addEventListener("change", function () {
      confirmBtn.disabled = !select.value;
    });

    // Fetch the full course list; exclude the current course so the same-course
    // move error is prevented before it even reaches the server.
    fetchJson("php/get_courses.php")
      .then(function (res) {
        if (!res.success) throw new Error(res.error || "Failed to load courses.");
        allCourses = (res.data || []).filter(function (c) {
          return String(c.course_id) !== String(currentCourseId);
        });
        applyMoveSearch("");
      })
      .catch(function (err) {
        select.innerHTML = '<option disabled>Failed to load courses.</option>';
        statusEl.textContent = err.message || "Could not load course list.";
        statusEl.className = "status status-error";
      });

    // Confirm handler (defined once per modal open, removed on close)
    function handleConfirm() {
      const targetCourseId = select.value ? decodeURIComponent(select.value) : "";
      if (!targetCourseId) {
        statusEl.textContent = "Please select a target course.";
        statusEl.className = "status status-error";
        return;
      }

      confirmBtn.disabled = true;
      statusEl.textContent = "Moving…";
      statusEl.className = "status";

      fetchJson("php/move_lecture_material.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        credentials: "same-origin",
        body: JSON.stringify({
          material_id: materialId,
          target_course_id: Number(targetCourseId),
        }),
      })
        .then(function (res) {
          if (!res.success) throw new Error(res.error || "Move failed.");
          closeModal();
          if (typeof onSuccess === "function") onSuccess();
          showSuccess("Material moved successfully.");
        })
        .catch(function (err) {
          statusEl.textContent = err.message || "Failed to move material.";
          statusEl.className = "status status-error";
          confirmBtn.disabled = false;
        });
    }

    function closeModal() {
      modal.style.display = "none";
      confirmBtn.removeEventListener("click", handleConfirm);
      cancelBtn.removeEventListener("click", handleCancel);
      modal.removeEventListener("click", handleBackdropClick);
    }

    function handleCancel() { closeModal(); }

    function handleBackdropClick(e) {
      if (e.target === modal) closeModal();
    }

    confirmBtn.addEventListener("click", handleConfirm);
    cancelBtn.addEventListener("click", handleCancel);
    modal.addEventListener("click", handleBackdropClick);
  }

  /**
   * Post a delete request and refresh on success.
   * Requirements 12.5, 12.6, 13.6
   */
  function adminDeleteMaterial(materialId, onSuccess) {
    if (!window.confirm("Delete this material? This cannot be undone.")) return;

    fetchJson("php/delete_lecture_material.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      credentials: "same-origin",
      body: JSON.stringify({ material_id: materialId }),
    })
      .then(function (res) {
        if (!res.success) throw new Error(res.error || "Delete failed.");
        showSuccess("Material deleted.");
        if (typeof onSuccess === "function") onSuccess();
      })
      .catch(function (err) {
        showError(err.message || "Failed to delete material.");
      });
  }

  /**
   * Main admin view entry point.
   * Requirements 13.1–13.8, 14.1–14.3
   */
  async function initAdminView() {
    const root = document.getElementById("lecturesRoot");
    if (!root) return;

    // 1. Show loading state
    root.innerHTML = '<p class="status">Loading materials…</p>';

    // 2. Fetch all materials (no course_id → admin all-materials path)
    let allMaterials = [];
    try {
      const res = await fetchJson("php/get_lecture_materials.php");
      if (!res.success) throw new Error(res.error || "Failed to load materials.");
      allMaterials = res.data || [];
    } catch (err) {
      root.innerHTML =
        '<p class="status status-error">Could not load materials: ' +
        escapeHtml(err.message || "Unknown error.") +
        "</p>";
      return;
    }

    // 3. Build UI shell with schedule builder style: main-header + panel + filter-bar + table
    root.innerHTML = [
      '<header class="main-header" style="margin-bottom:16px;">',
        '<div>',
          '<h2>Lecture Materials</h2>',
          '<p class="muted">Manage all uploaded lecture files across courses, years, and semesters.</p>',
        "</div>",
      "</header>",

      '<section class="panel">',
        // ── Filter bar (schedule builder style) ──────────────────────────────
        '<div class="filter-bar" role="group" aria-label="Filter materials">',
          '<div class="field">',
            '<label class="muted" style="font-size:0.85rem;" for="lmFilterYear">Academic Year</label>',
            '<select id="lmFilterYear" class="navlink" aria-label="Filter by year level">',
              '<option value="All">All Years</option>',
              '<option value="1">Year 1</option>',
              '<option value="2">Year 2</option>',
              '<option value="3">Year 3</option>',
            "</select>",
          "</div>",
          '<div class="field">',
            '<label class="muted" style="font-size:0.85rem;" for="lmFilterSem">Semester</label>',
            '<select id="lmFilterSem" class="navlink" aria-label="Filter by semester">',
              '<option value="All">All Sem</option>',
              '<option value="1">Sem 1</option>',
              '<option value="2">Sem 2</option>',
            "</select>",
          "</div>",
          '<div class="field" style="flex:1;min-width:200px;">',
            '<label class="muted" style="font-size:0.85rem;" for="lmFilterSearch">Search</label>',
            '<input id="lmFilterSearch" class="navlink" type="text"',
              ' placeholder="Filename or course…" autocomplete="off"',
              ' aria-label="Search materials by filename or course name" />',
          "</div>",
        "</div>",

        // ── Materials table ──────────────────────────────────────────────────────
        '<div class="schedule-wrap" style="margin-top:16px;">',
          '<table class="data-table schedule-grid" style="width:100%;" role="table" aria-label="All lecture materials">',
            "<thead>",
              "<tr>",
                '<th scope="col">Filename</th>',
                '<th scope="col">Type</th>',
                '<th scope="col">Size</th>',
                '<th scope="col">Course</th>',
                '<th scope="col">Year</th>',
                '<th scope="col">Sem</th>',
                '<th scope="col">Uploader</th>',
                '<th scope="col">Uploaded</th>',
                '<th scope="col">Actions</th>',
              "</tr>",
            "</thead>",
            '<tbody id="lmAdminTbody"></tbody>',
          "</table>",
        "</div>",
      "</section>",
    ].join("");

    const tbody       = document.getElementById("lmAdminTbody");
    const filterYear  = document.getElementById("lmFilterYear");
    const filterSem   = document.getElementById("lmFilterSem");
    const filterSearch = document.getElementById("lmFilterSearch");

    // 4. Helpers
    function getCurrentFilters() {
      return {
        yearLevel: filterYear.value,
        semester:  filterSem.value,
        search:    filterSearch.value,
      };
    }

    // 5. Re-render table from cached data (requirement 13.7 — no page reload)
    function refreshTable() {
      const filtered = applyAdminFilters(allMaterials, getCurrentFilters());
      renderAdminTable(filtered, tbody);
      bindTableActions();
    }

    // 6. Re-fetch from server then re-render (used after mutating operations)
    async function reloadAndRefresh() {
      try {
        const res = await fetchJson("php/get_lecture_materials.php");
        if (res.success) allMaterials = res.data || [];
      } catch (_) {
        // Keep stale cache; user will see the old list
      }
      refreshTable();
    }

    // 7. Bind per-row action buttons after each render
    function bindTableActions() {
      // Preview — calls openPreview() defined in task 7.5
      tbody.querySelectorAll(".lm-btn-preview").forEach(function (btn) {
        btn.addEventListener("click", function () {
          openPreview({
            material_id:       decodeURIComponent(btn.dataset.id),
            file_type:         btn.dataset.type,
            original_filename: btn.dataset.name,
          });
        });
      });

      // Download — calls triggerDownload() defined in task 7.5
      tbody.querySelectorAll(".lm-btn-download").forEach(function (btn) {
        btn.addEventListener("click", function () {
          triggerDownload(decodeURIComponent(btn.dataset.id));
        });
      });

      // Delete
      tbody.querySelectorAll(".lm-btn-delete").forEach(function (btn) {
        btn.addEventListener("click", function () {
          adminDeleteMaterial(decodeURIComponent(btn.dataset.id), reloadAndRefresh);
        });
      });

      // Move
      tbody.querySelectorAll(".lm-btn-move").forEach(function (btn) {
        btn.addEventListener("click", function () {
          showMoveMaterialModal(
            Number(decodeURIComponent(btn.dataset.id)),
            decodeURIComponent(btn.dataset.courseid),
            reloadAndRefresh
          );
        });
      });
    }

    // 8. Wire filter controls (requirement 13.7)
    filterYear.addEventListener("change", refreshTable);
    filterSem.addEventListener("change",  refreshTable);
    filterSearch.addEventListener("input", refreshTable);

    // 9. Initial render
    refreshTable();
  }

  async function initStudentView() {
    const rootEl = document.getElementById("lecturesRoot");
    if (!rootEl) return;

    // ── Step 1: fetch the student's academic context ──────────────────────────
    let ctx;
    try {
      const ctxResp = await fetchJson("php/get_lecture_materials_student_context.php");
      ctx = ctxResp?.data ?? ctxResp ?? {};
    } catch (err) {
      renderEmptyState(rootEl, {
        icon: "⚠️",
        title: "Unable to load profile",
        subtitle: "Could not retrieve your academic profile. Please refresh the page.",
      });
      return;
    }

    const yearLevel = Number(ctx.year_level || 0);
    const semester  = Number(ctx.semester   || 0);
    const program   = String(ctx.program    || "").trim();

    if (!yearLevel || !semester) {
      renderEmptyState(rootEl, {
        icon: "⚠️",
        title: "Profile not configured",
        subtitle: "Your academic profile is not yet configured. Please contact an administrator.",
      });
      return;
    }

    // ── Step 2: fetch doctors that teach in the student's context ─────────────
    rootEl.innerHTML = "";

    const studentCtx = { year_level: yearLevel, semester, program };

    let doctors = [];
    try {
      const params = new URLSearchParams({
        year_level: String(yearLevel),
        semester:   String(semester),
        program,
      });
      const resp = await fetchJson(`php/get_lecture_doctors.php?${params}`);
      doctors = Array.isArray(resp?.data) ? resp.data : [];
    } catch (err) {
      renderEmptyState(rootEl, {
        icon: "⚠️",
        title: "Unable to load teachers",
        subtitle: err?.message || "Could not load the list of teachers. Please try again.",
      });
      return;
    }

    // ── Step 3: render Doctor cards ───────────────────────────────────────────
    renderDoctorCards(rootEl, doctors, studentCtx);
  }

  // ---------------------------------------------------------------------------
  // renderDoctorCards — list of doctor selection cards
  // ---------------------------------------------------------------------------
  function renderDoctorCards(rootEl, doctors, studentCtx) {
    rootEl.innerHTML = "";

    // Breadcrumb / heading
    const heading = document.createElement("h2");
    heading.className = "section-heading";
    heading.textContent = "Your Teachers";
    rootEl.appendChild(heading);

    if (!doctors.length) {
      renderEmptyState(rootEl, {
        icon: "👨‍🏫",
        title: "No teachers found",
        subtitle: "No teachers are assigned to your current semester.",
      });
      return;
    }

    const grid = document.createElement("div");
    grid.className = "lectures-grid";

    for (const doctor of doctors) {
      const card = document.createElement("div");
      card.className = "card card-clickable teacher-card";
      card.setAttribute("role", "button");
      card.setAttribute("tabindex", "0");
      card.setAttribute("aria-label", `View courses for ${escapeHtml(doctor.full_name)}`);

      // Avatar image
      const avatarContainer = document.createElement("div");
      avatarContainer.className = "teacher-avatar-container";
      
      const avatarImg = document.createElement("img");
      avatarImg.className = "teacher-avatar-img";
      
      // Generate image filename from doctor name (lowercase, spaces to underscores)
      const imageName = String(doctor.full_name || "default")
        .toLowerCase()
        .replace(/\s+/g, "_")
        .replace(/[^a-z0-9_]/g, "");
      
      // Add cache buster to prevent browser from caching old images
      const cacheBuster = Date.now();
      avatarImg.src = `images/professors/${imageName}.jpg?v=${cacheBuster}`;
      avatarImg.alt = doctor.full_name;
      
      // Fallback to default image if professor image not found
      avatarImg.onerror = function() {
        this.onerror = null;
        this.src = `images/professors/default.jpg?v=${cacheBuster}`;
      };
      
      avatarContainer.appendChild(avatarImg);
      card.appendChild(avatarContainer);

      // Name
      const nameEl = document.createElement("h3");
      nameEl.className = "teacher-name";
      nameEl.textContent = doctor.full_name;
      card.appendChild(nameEl);

      // Subtitle
      const subtitleEl = document.createElement("p");
      subtitleEl.className = "teacher-subtitle";
      subtitleEl.textContent = "View lecture materials";
      card.appendChild(subtitleEl);

      // Professor quote
      const quoteEl = document.createElement("p");
      quoteEl.className = "teacher-quote";
      // You can add quotes to the database later, for now using placeholder
      quoteEl.textContent = doctor.quote || '"Knowledge is power."';
      card.appendChild(quoteEl);

      const handler = () => renderStudentCourses(rootEl, doctor, studentCtx);
      card.addEventListener("click", handler);
      card.addEventListener("keydown", (e) => {
        if (e.key === "Enter" || e.key === " ") {
          e.preventDefault();
          handler();
        }
      });

      grid.appendChild(card);
    }

    rootEl.appendChild(grid);
  }

  // ---------------------------------------------------------------------------
  // renderStudentCourses — courses for a selected doctor
  // ---------------------------------------------------------------------------
  function renderStudentCourses(rootEl, doctor, studentCtx) {
    rootEl.innerHTML = "";

    // Back button
    const backBtn = document.createElement("button");
    backBtn.className = "btn btn-secondary back-btn";
    backBtn.textContent = "← Back to Teachers";
    backBtn.addEventListener("click", () => {
      // Re-fetch and re-render doctor cards
      fetchJson(
        `php/get_lecture_doctors.php?${new URLSearchParams({
          year_level: String(studentCtx.year_level),
          semester:   String(studentCtx.semester),
          program:    studentCtx.program,
        })}`
      )
        .then((resp) => {
          const doctors = Array.isArray(resp?.data) ? resp.data : [];
          renderDoctorCards(rootEl, doctors, studentCtx);
        })
        .catch((err) => {
          renderEmptyState(rootEl, {
            icon: "⚠️",
            title: "Unable to load teachers",
            subtitle: err?.message || "Could not reload the list of teachers.",
          });
        });
    });
    rootEl.appendChild(backBtn);

    // Heading
    const heading = document.createElement("h2");
    heading.className = "section-heading";
    heading.textContent = doctor.full_name; // textContent — safe
    rootEl.appendChild(heading);

    // Courses are already filtered by the server to match student context
    const courses = Array.isArray(doctor.courses) ? doctor.courses : [];

    if (!courses.length) {
      renderEmptyState(rootEl, {
        icon: "📚",
        title: "No courses found",
        subtitle: "This teacher has no courses assigned for your current semester.",
      });
      return;
    }

    const grid = document.createElement("div");
    grid.className = "lectures-grid";

    for (const course of courses) {
      const card = document.createElement("div");
      card.className = "card card-clickable";
      card.setAttribute("role", "button");
      card.setAttribute("tabindex", "0");
      card.setAttribute("aria-label", `View materials for ${escapeHtml(course.course_name)}`);

      const nameEl = document.createElement("h3");
      nameEl.className = "card-title";
      nameEl.textContent = course.course_name; // textContent — safe

      const codeEl = document.createElement("p");
      codeEl.className = "card-subtitle";
      codeEl.textContent = course.subject_code || "";

      card.appendChild(nameEl);
      card.appendChild(codeEl);

      const handler = () => renderStudentMaterials(rootEl, course, doctor, studentCtx);
      card.addEventListener("click", handler);
      card.addEventListener("keydown", (e) => {
        if (e.key === "Enter" || e.key === " ") {
          e.preventDefault();
          handler();
        }
      });

      grid.appendChild(card);
    }

    rootEl.appendChild(grid);
  }

  // ---------------------------------------------------------------------------
  // renderStudentMaterials — material list for a selected course (student view)
  // ---------------------------------------------------------------------------
  async function renderStudentMaterials(rootEl, course, doctor, studentCtx) {
    rootEl.innerHTML = "";

    // Back button — returns to course list for this doctor
    const backBtn = document.createElement("button");
    backBtn.className = "btn btn-secondary back-btn";
    backBtn.textContent = "← Back to Courses";
    backBtn.addEventListener("click", () => renderStudentCourses(rootEl, doctor, studentCtx));
    rootEl.appendChild(backBtn);

    // Heading
    const heading = document.createElement("h2");
    heading.className = "section-heading";
    heading.textContent = course.course_name;
    if (course.subject_code) {
      const sub = document.createElement("span");
      sub.className = "section-heading-sub";
      sub.textContent = ` — ${course.subject_code}`;
      heading.appendChild(sub);
    }
    rootEl.appendChild(heading);

    // Loading indicator
    const loadingEl = document.createElement("p");
    loadingEl.className = "status";
    loadingEl.textContent = "Loading materials…";
    rootEl.appendChild(loadingEl);

    let materials = [];
    try {
      const resp = await fetchJson(
        `php/get_lecture_materials.php?course_id=${encodeURIComponent(course.course_id)}`
      );
      materials = Array.isArray(resp?.data) ? resp.data : [];
    } catch (err) {
      loadingEl.remove();
      const errEl = document.createElement("p");
      errEl.className = "status status-error";
      errEl.textContent = err?.message || "Could not load materials. Please try again.";
      rootEl.appendChild(errEl);
      return;
    }

    loadingEl.remove();

    if (!materials.length) {
      renderEmptyState(rootEl, {
        icon: "📄",
        title: "No materials yet",
        subtitle: "No materials have been uploaded for this course yet.",
      });
      return;
    }

    // ── Materials table ────────────────────────────────────────────────────────
    const tableWrap = document.createElement("div");
    tableWrap.className = "table-responsive";

    const table = document.createElement("table");
    table.className = "data-table";
    table.setAttribute("role", "table");

    // thead
    const thead = document.createElement("thead");
    thead.innerHTML =
      "<tr>" +
      "<th scope=\"col\">File</th>" +
      "<th scope=\"col\">Type</th>" +
      "<th scope=\"col\">Size</th>" +
      "<th scope=\"col\">Actions</th>" +
      "</tr>";
    table.appendChild(thead);

    // tbody
    const tbody = document.createElement("tbody");

    for (const mat of materials) {
      const sizeKb = (Number(mat.file_size_bytes || 0) / 1024).toFixed(1);

      const tr = document.createElement("tr");

      // File name cell
      const tdName = document.createElement("td");
      tdName.textContent = mat.original_filename; // textContent — safe

      // Type cell
      const tdType = document.createElement("td");
      tdType.textContent = escapeHtml(String(mat.file_type || "").toLowerCase());

      // Size cell
      const tdSize = document.createElement("td");
      tdSize.textContent = `${sizeKb} KB`;

      // Actions cell — Preview + Download only (no upload form, no delete)
      const tdActions = document.createElement("td");
      tdActions.className = "actions-cell";

      const previewBtn = document.createElement("button");
      previewBtn.className = "btn btn-secondary btn-sm";
      previewBtn.textContent = "Preview";
      previewBtn.setAttribute("aria-label", `Preview ${escapeHtml(mat.original_filename)}`);
      previewBtn.addEventListener("click", () => openPreview(mat));

      const downloadBtn = document.createElement("button");
      downloadBtn.className = "btn btn-primary btn-sm";
      downloadBtn.textContent = "Download";
      downloadBtn.setAttribute("aria-label", `Download ${escapeHtml(mat.original_filename)}`);
      downloadBtn.addEventListener("click", () => triggerDownload(mat.material_id));

      tdActions.appendChild(previewBtn);
      tdActions.appendChild(downloadBtn);

      tr.appendChild(tdName);
      tr.appendChild(tdType);
      tr.appendChild(tdSize);
      tr.appendChild(tdActions);
      tbody.appendChild(tr);
    }

    table.appendChild(tbody);
    tableWrap.appendChild(table);
    rootEl.appendChild(tableWrap);
  }

  // ─────────────────────────────────────────────────────────────────
  // PREVIEW AND DOWNLOAD HELPERS  (Task 7.5)
  // ─────────────────────────────────────────────────────────────────

  /**
   * Open a preview for the given material.
   * - PDF  → inject an <iframe> into a dedicated preview container on the page.
   * - PPTX → open the download endpoint in a new tab (can't render inline).
   * - Other → open the preview endpoint in a new tab.
   * Requirements 11.4, 11.5, 11.6, 7.9, 7.10, 16.8
   *
   * @param {{ material_id: number|string, file_type: string, original_filename?: string }} material
   */
  function openPreview(material) {
    var type = String(material.file_type || "").toLowerCase();
    var id   = encodeURIComponent(material.material_id);

    if (type === "pdf") {
      // Reuse or create a dedicated preview container
      var container = document.getElementById("lmPreviewContainer");
      if (!container) {
        container = document.createElement("div");
        container.id = "lmPreviewContainer";
        container.className = "lm-preview-container";
        var root = document.getElementById("lecturesRoot");
        if (root) {
          root.appendChild(container);
        } else {
          document.body.appendChild(container);
        }
      }

      // Replace any previous iframe
      container.innerHTML =
        '<iframe' +
        ' src="php/preview_lecture_material.php?material_id=' + id + '"' +
        ' style="width:100%;height:600px;border:0;"' +
        ' title="Preview"' +
        '></iframe>';

      container.scrollIntoView({ behavior: "smooth", block: "start" });
    } else if (type === "pptx") {
      window.open(
        "php/download_lecture_material.php?material_id=" + id,
        "_blank"
      );
    } else {
      window.open(
        "php/preview_lecture_material.php?material_id=" + id,
        "_blank"
      );
    }
  }

  /**
   * Trigger a browser file download for the given material ID.
   * Creates a temporary <a download> element, clicks it, then removes it.
   * Requirements 10.1–10.8
   *
   * @param {number|string} materialId
   */
  function triggerDownload(materialId) {
    var a = document.createElement("a");
    a.href = "php/download_lecture_material.php?material_id=" +
             encodeURIComponent(materialId);
    a.setAttribute("download", "");
    a.style.display = "none";
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
  }

  // ─────────────────────────────────────────────────────────────────
  // ENTRY POINT
  // ─────────────────────────────────────────────────────────────────

  async function initLecturesPage() {
    const rootId = "lecturesRoot";

    // 1. Fetch the authenticated user.
    let response;
    try {
      response = await fetchJson("php/auth_me.php");
    } catch (err) {
      setStatusById(rootId, "Unable to load your session. Please refresh the page.", "status-error");
      return;
    }

    // 2. Extract user data from response
    const user = response?.data || null;

    // 3. Guard against unauthenticated or missing responses.
    if (!user || !user.role) {
      setStatusById(rootId, "You are not logged in. Please log in to continue.", "status-error");
      return;
    }

    // 4. Store for use by view functions.
    currentUser = user;

    // 5. Dispatch to the correct role view.
    const role = String(user.role);

    if (role === "teacher") {
      initProfessorView();
    } else if (role === "admin" || role === "management") {
      initAdminView();
    } else if (role === "student") {
      initStudentView();
    } else {
      setStatusById(rootId, "Access denied: your role does not have permission to view this page.", "status-error");
    }
  }

  // ─────────────────────────────────────────────────────────────────
  // EXPORT
  // ─────────────────────────────────────────────────────────────────

  window.dmportal = window.dmportal || {};
  window.dmportal.initLecturesPage = initLecturesPage;
})();


