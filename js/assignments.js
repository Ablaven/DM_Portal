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

  // Authenticated user object - populated in initAssignmentsPage
  let currentUser = null;

  // ─────────────────────────────────────────────────────────────────
  // INITIALIZATION
  // ─────────────────────────────────────────────────────────────────

  function initAssignmentsPage() {
    fetchJson("php/get_current_user.php")
      .then(function (res) {
        if (!res.success || !res.data) {
          throw new Error("Failed to get current user.");
        }
        currentUser = res.data;
        const role = currentUser.role || "";

        if (role === "teacher") {
          initTeacherView();
        } else if (role === "student") {
          initStudentView();
        } else if (role === "admin" || role === "management") {
          // Use the new improved admin view
          if (window.dmportal.initAdminView) {
            window.dmportal.initAdminView();
          } else {
            initAdminView();
          }
        } else {
          showError("Access denied.");
        }
      })
      .catch(function (err) {
        showError(err.message || "Failed to initialize assignments page.");
      });
  }

  // Export to window
  if (!window.dmportal) {
    window.dmportal = {};
  }
  window.dmportal.initAssignmentsPage = initAssignmentsPage;

  // ─────────────────────────────────────────────────────────────────
  // TEACHER VIEW
  // ─────────────────────────────────────────────────────────────────

  function initTeacherView() {
    const root = document.getElementById("assignmentsRoot");
    if (!root) return;
    root.innerHTML = "";

    const yearGrid = document.createElement("div");
    yearGrid.className = "assignments-grid";
    root.appendChild(yearGrid);

    [1, 2, 3].forEach(function (year) {
      const card = document.createElement("button");
      card.className = "card card-clickable year-selector-card";
      card.type = "button";
      card.setAttribute("aria-label", "Year " + year);
      const iconEl = document.createElement("span");
      iconEl.className = "selector-card-icon";
      iconEl.textContent = "📅";
      const labelEl = document.createElement("span");
      labelEl.className = "selector-card-label";
      labelEl.textContent = "Year " + year;
      card.appendChild(iconEl);
      card.appendChild(labelEl);
      card.addEventListener("click", function () {
        renderTeacherSemesterSelection(year);
      });
      yearGrid.appendChild(card);
    });
  }

  function renderTeacherSemesterSelection(year) {
    const root = document.getElementById("assignmentsRoot");
    if (!root) return;
    root.innerHTML = "";

    root.appendChild(makeTeacherBackBtn("← Back to Years", initTeacherView));

    const heading = document.createElement("h2");
    heading.className = "section-heading";
    heading.textContent = "Year " + year + " - Select Semester";
    root.appendChild(heading);

    const semGrid = document.createElement("div");
    semGrid.className = "assignments-grid";
    root.appendChild(semGrid);

    [1, 2].forEach(function (sem) {
      const card = document.createElement("button");
      card.className = "card card-clickable year-selector-card";
      card.type = "button";
      card.setAttribute("aria-label", "Semester " + sem);
      const iconEl = document.createElement("span");
      iconEl.className = "selector-card-icon";
      iconEl.textContent = "📖";
      const labelEl = document.createElement("span");
      labelEl.className = "selector-card-label";
      labelEl.textContent = "Semester " + sem;
      card.appendChild(iconEl);
      card.appendChild(labelEl);
      card.addEventListener("click", function () {
        loadTeacherCourses(year, sem);
      });
      semGrid.appendChild(card);
    });
  }

  async function loadTeacherCourses(year, sem) {
    const root = document.getElementById("assignmentsRoot");
    if (!root) return;
    root.innerHTML = "";

    root.appendChild(
      makeTeacherBackBtn(
        "← Back to Year " + year + " Semesters",
        function () { renderTeacherSemesterSelection(year); }
      )
    );

    const heading = document.createElement("h2");
    heading.className = "section-heading";
    heading.textContent = "Year " + year + ", Semester " + sem + " - My Courses";
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
      showError("Failed to load courses.");
      return;
    }

    loadingEl.remove();

    const doctorId = Number(currentUser && currentUser.doctor_id || 0);
    
    // Find the entry for the current teacher in the doctors array
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
    courseGrid.className = "assignments-grid";
    root.appendChild(courseGrid);

    courses.forEach(function (course) {
      const card = document.createElement("button");
      card.className = "card card-clickable year-selector-card";
      card.type = "button";
      const iconEl = document.createElement("span");
      iconEl.className = "selector-card-icon";
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
        renderTeacherCourseAssignments(
          Number(course.course_id),
          course.course_name || "Course",
          year,
          sem
        );
      });
      courseGrid.appendChild(card);
    });
  }

  async function renderTeacherCourseAssignments(courseId, courseName, year, sem) {
    const root = document.getElementById("assignmentsRoot");
    if (!root) return;
    root.innerHTML = "";

    root.appendChild(
      makeTeacherBackBtn("← Back to Courses", function () {
        loadTeacherCourses(year, sem);
      })
    );

    const heading = document.createElement("h2");
    heading.className = "section-heading";
    heading.textContent = "📋 " + escapeHtml(courseName);
    root.appendChild(heading);

    // Create Assignment Button
    const createBtn = document.createElement("button");
    createBtn.className = "btn btn-primary";
    createBtn.textContent = "+ Create Assignment";
    createBtn.style.marginBottom = "2rem";
    createBtn.addEventListener("click", function () {
      showCreateAssignmentModal(courseId, year, sem, function () {
        renderTeacherCourseAssignments(courseId, courseName, year, sem);
      });
    });
    root.appendChild(createBtn);

    // Assignments list
    const assignmentsSection = document.createElement("section");
    assignmentsSection.className = "assignments-section";
    const assignmentsTitle = document.createElement("h3");
    assignmentsTitle.className = "subsection-heading";
    assignmentsTitle.textContent = "Assignments";
    assignmentsSection.appendChild(assignmentsTitle);
    const assignmentsContainer = document.createElement("div");
    assignmentsContainer.className = "assignments-container";
    assignmentsSection.appendChild(assignmentsContainer);
    root.appendChild(assignmentsSection);

    await refreshTeacherAssignments(courseId, assignmentsContainer);
  }

  async function refreshTeacherAssignments(courseId, container) {
    container.innerHTML = "";
    const loadingEl = document.createElement("p");
    loadingEl.className = "status";
    loadingEl.textContent = "Loading assignments…";
    container.appendChild(loadingEl);

    let assignments;
    try {
      const resp = await fetchJson(
        "php/get_assignments.php?course_id=" + encodeURIComponent(courseId)
      );
      assignments = Array.isArray(resp && resp.data) ? resp.data : [];
    } catch (err) {
      loadingEl.remove();
      showError(err.message || "Failed to load assignments.");
      return;
    }

    loadingEl.remove();

    if (!assignments.length) {
      renderEmptyState(container, {
        icon: "📝",
        title: "No assignments yet",
        subtitle: "Create your first assignment to get started.",
      });
      return;
    }

    const grid = document.createElement("div");
    grid.className = "assignments-grid";
    container.appendChild(grid);

    assignments.forEach(function (assignment) {
      const card = createAssignmentCard(assignment, "teacher");
      grid.appendChild(card);
    });
  }

  function showCreateAssignmentModal(courseId, yearLevel, semester, onSuccess) {
    // Create modal with proper structure
    const modal = document.createElement("div");
    modal.className = "modal open";
    modal.style.display = "flex";
    modal.style.position = "fixed";
    modal.style.inset = "0";
    modal.style.background = "rgba(0, 0, 0, 0.75)";
    modal.style.backdropFilter = "blur(8px)";
    modal.style.zIndex = "10000";
    modal.style.alignItems = "center";
    modal.style.justifyContent = "center";
    modal.style.padding = "1rem";

    const modalCard = document.createElement("div");
    modalCard.className = "modal-card";
    modalCard.style.maxWidth = "560px";
    modalCard.style.width = "100%";

    // Modal header
    const modalHeader = document.createElement("div");
    modalHeader.className = "modal-header";
    modalHeader.style.marginBottom = "1.5rem";
    const title = document.createElement("h3");
    title.textContent = "Create New Assignment";
    title.style.margin = "0";
    title.style.fontSize = "1.375rem";
    title.style.fontWeight = "600";
    title.style.color = "var(--text)";
    modalHeader.appendChild(title);
    modalCard.appendChild(modalHeader);

    // Modal body
    const modalBody = document.createElement("div");
    modalBody.className = "modal-body";

    const form = document.createElement("form");
    form.style.display = "flex";
    form.style.flexDirection = "column";
    form.style.gap = "1.25rem";

    // Title field
    const titleField = document.createElement("div");
    titleField.className = "field";
    titleField.style.margin = "0";
    titleField.innerHTML =
      '<label for="assignmentTitle" style="display:block;margin-bottom:0.5rem;font-weight:600;color:var(--text);font-size:0.9375rem;">Assignment Title <span style="color:var(--danger);">*</span></label>' +
      '<input id="assignmentTitle" class="form-control" type="text" required maxlength="255" placeholder="e.g., Week 5 Project Submission" style="width:100%;padding:0.75rem;font-size:0.9375rem;" />';
    form.appendChild(titleField);

    // Description field
    const descField = document.createElement("div");
    descField.className = "field";
    descField.style.margin = "0";
    descField.innerHTML =
      '<label for="assignmentDesc" style="display:block;margin-bottom:0.5rem;font-weight:600;color:var(--text);font-size:0.9375rem;">Description</label>' +
      '<textarea id="assignmentDesc" class="form-control" rows="4" placeholder="Instructions for students..." style="width:100%;padding:0.75rem;font-size:0.9375rem;resize:vertical;font-family:inherit;"></textarea>';
    form.appendChild(descField);

    // Deadline field
    const deadlineField = document.createElement("div");
    deadlineField.className = "field";
    deadlineField.style.margin = "0";
    const minDate = new Date();
    minDate.setHours(minDate.getHours() + 1);
    const minDateStr = minDate.toISOString().slice(0, 16);
    deadlineField.innerHTML =
      '<label for="assignmentDeadline" style="display:block;margin-bottom:0.5rem;font-weight:600;color:var(--text);font-size:0.9375rem;">Deadline <span style="color:var(--danger);">*</span></label>' +
      '<input id="assignmentDeadline" class="form-control" type="datetime-local" required min="' +
      minDateStr + '" style="width:100%;padding:0.75rem;font-size:0.9375rem;" />';
    form.appendChild(deadlineField);

    // Multiple files checkbox
    const multipleField = document.createElement("div");
    multipleField.className = "field";
    multipleField.style.margin = "0";
    multipleField.style.display = "flex";
    multipleField.style.alignItems = "center";
    multipleField.style.gap = "0.625rem";
    multipleField.style.padding = "0.75rem";
    multipleField.style.background = "var(--surface-1)";
    multipleField.style.borderRadius = "8px";
    multipleField.style.border = "1px solid var(--card-border)";
    multipleField.innerHTML =
      '<input id="assignmentMultiple" type="checkbox" style="width:20px;height:20px;cursor:pointer;margin:0;flex-shrink:0;" />' +
      '<label for="assignmentMultiple" style="cursor:pointer;margin:0;font-size:0.9375rem;color:var(--text);">Allow students to upload multiple files</label>';
    form.appendChild(multipleField);

    const statusEl = document.createElement("p");
    statusEl.className = "status";
    statusEl.style.display = "none";
    statusEl.style.margin = "0";
    form.appendChild(statusEl);

    // Modal actions (buttons inside the form)
    const modalActions = document.createElement("div");
    modalActions.className = "modal-actions";
    modalActions.style.display = "flex";
    modalActions.style.gap = "0.75rem";
    modalActions.style.justifyContent = "flex-end";
    modalActions.style.marginTop = "1.5rem";
    const cancelBtn = document.createElement("button");
    cancelBtn.type = "button";
    cancelBtn.className = "btn btn-secondary";
    cancelBtn.textContent = "Cancel";
    const submitBtn = document.createElement("button");
    submitBtn.type = "submit";
    submitBtn.className = "btn btn-primary";
    submitBtn.textContent = "Create Assignment";
    modalActions.appendChild(cancelBtn);
    modalActions.appendChild(submitBtn);
    form.appendChild(modalActions); // Append to form, not modalCard

    modalBody.appendChild(form);
    modalCard.appendChild(modalBody);

    modal.appendChild(modalCard);
    document.body.appendChild(modal);

    // Close on backdrop click
    modal.addEventListener("click", function (e) {
      if (e.target === modal) {
        document.body.removeChild(modal);
      }
    });

    cancelBtn.addEventListener("click", function () {
      document.body.removeChild(modal);
    });

    form.addEventListener("submit", async function (e) {
      e.preventDefault();

      const titleVal = document.getElementById("assignmentTitle").value.trim();
      const descVal = document.getElementById("assignmentDesc").value.trim();
      const deadlineVal = document.getElementById("assignmentDeadline").value;
      const multipleVal = document.getElementById("assignmentMultiple").checked;

      if (!titleVal || !deadlineVal) {
        statusEl.textContent = "Please fill in all required fields.";
        statusEl.className = "status status-error";
        statusEl.style.display = "";
        return;
      }

      submitBtn.disabled = true;
      submitBtn.textContent = "Creating…";
      statusEl.textContent = "Creating assignment…";
      statusEl.className = "status";
      statusEl.style.display = "";

      try {
        const resp = await fetchJson("php/create_assignment.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            course_id: courseId,
            title: titleVal,
            description: descVal || null,
            deadline: deadlineVal,
            allow_multiple_files: multipleVal,
          }),
        });

        showSuccess("Assignment created successfully.");
        document.body.removeChild(modal);
        if (typeof onSuccess === "function") {
          onSuccess();
        }
      } catch (err) {
        statusEl.textContent = err.message || "Failed to create assignment.";
        statusEl.className = "status status-error";
        statusEl.style.display = "";
        submitBtn.disabled = false;
        submitBtn.textContent = "Create Assignment";
      }
    });
  }

  function makeTeacherBackBtn(label, onClick) {
    const btn = document.createElement("button");
    btn.type = "button";
    btn.className = "btn btn-secondary back-btn";
    btn.textContent = label;
    btn.addEventListener("click", onClick);
    return btn;
  }

  // ─────────────────────────────────────────────────────────────────
  // STUDENT VIEW
  // ─────────────────────────────────────────────────────────────────

  async function initStudentView() {
    const root = document.getElementById("assignmentsRoot");
    if (!root) return;
    root.innerHTML = "";

    const loadingEl = document.createElement("p");
    loadingEl.className = "status";
    loadingEl.textContent = "Loading assignments…";
    root.appendChild(loadingEl);

    let assignments;
    try {
      const resp = await fetchJson("php/get_assignments.php");
      assignments = Array.isArray(resp && resp.data) ? resp.data : [];
    } catch (err) {
      loadingEl.remove();
      showError(err.message || "Failed to load assignments.");
      return;
    }

    loadingEl.remove();

    if (!assignments.length) {
      renderEmptyState(root, {
        icon: "📚",
        title: "No assignments",
        subtitle: "You don't have any assignments at the moment.",
      });
      return;
    }

    const grid = document.createElement("div");
    grid.className = "assignments-grid";
    root.appendChild(grid);

    assignments.forEach(function (assignment) {
      const card = createAssignmentCard(assignment, "student");
      grid.appendChild(card);
    });
  }

  // ─────────────────────────────────────────────────────────────────
  // ADMIN VIEW
  // ─────────────────────────────────────────────────────────────────

  async function initAdminView() {
    const root = document.getElementById("assignmentsRoot");
    if (!root) return;

    root.innerHTML = '<p class="status">Loading assignments…</p>';

    let allAssignments = [];
    try {
      const res = await fetchJson("php/get_assignments.php");
      if (!res.success) throw new Error(res.error || "Failed to load assignments.");
      allAssignments = res.data || [];
    } catch (err) {
      root.innerHTML =
        '<p class="status status-error">Could not load assignments: ' +
        escapeHtml(err.message || "Unknown error.") +
        "</p>";
      return;
    }

    // Build UI shell with schedule builder style
    root.innerHTML = [
      '<header class="main-header" style="margin-bottom:16px;">',
        '<div>',
          '<h2>Assignments</h2>',
          '<p class="muted">Manage all assignments across courses, years, and semesters.</p>',
        "</div>",
      "</header>",

      '<section class="panel">',
        '<div class="filter-bar" role="group" aria-label="Filter assignments">',
          '<div class="field">',
            '<label class="muted" style="font-size:0.85rem;" for="filterYear">Academic Year</label>',
            '<select id="filterYear" class="navlink" aria-label="Filter by year level">',
              '<option value="">All Years</option>',
              '<option value="1">Year 1</option>',
              '<option value="2">Year 2</option>',
              '<option value="3">Year 3</option>',
            "</select>",
          "</div>",
          '<div class="field">',
            '<label class="muted" style="font-size:0.85rem;" for="filterSemester">Semester</label>',
            '<select id="filterSemester" class="navlink" aria-label="Filter by semester">',
              '<option value="">All Sem</option>',
              '<option value="1">Sem 1</option>',
              '<option value="2">Sem 2</option>',
            "</select>",
          "</div>",
          '<div class="field" style="flex:1;min-width:200px;">',
            '<label class="muted" style="font-size:0.85rem;" for="filterSearch">Search</label>',
            '<input id="filterSearch" class="navlink" type="text"',
              ' placeholder="Title or course…" autocomplete="off"',
              ' aria-label="Search assignments" />',
          "</div>",
        "</div>",

        '<div class="schedule-wrap" style="margin-top:16px;">',
          '<table class="data-table schedule-grid" style="width:100%;" role="table" aria-label="All assignments">',
            "<thead>",
              "<tr>",
                '<th scope="col">Title</th>',
                '<th scope="col">Course</th>',
                '<th scope="col">Teacher</th>',
                '<th scope="col">Year</th>',
                '<th scope="col">Sem</th>',
                '<th scope="col">Deadline</th>',
                '<th scope="col">Files</th>',
                '<th scope="col">Submissions</th>',
                '<th scope="col">Actions</th>',
              "</tr>",
            "</thead>",
            '<tbody id="assignmentsAdminTbody"></tbody>',
          "</table>",
        "</div>",
      "</section>",
    ].join("");

    const tbody = document.getElementById("assignmentsAdminTbody");
    const filterYear = document.getElementById("filterYear");
    const filterSem = document.getElementById("filterSemester");
    const filterSearch = document.getElementById("filterSearch");

    function getCurrentFilters() {
      return {
        yearLevel: filterYear.value,
        semester: filterSem.value,
        search: filterSearch.value,
      };
    }

    function refreshTable() {
      const filters = getCurrentFilters();
      const needle = (filters.search || "").trim().toLowerCase();

      console.log('Admin view - allAssignments:', allAssignments);
      console.log('Admin view - filters:', filters);

      const filtered = allAssignments.filter(function (a) {
        if (filters.yearLevel && String(a.year_level) !== filters.yearLevel) return false;
        if (filters.semester && String(a.semester) !== filters.semester) return false;
        if (needle) {
          const title = String(a.title || "").toLowerCase();
          const course = String(a.course_name || "").toLowerCase();
          if (!title.includes(needle) && !course.includes(needle)) return false;
        }
        return true;
      });

      console.log('Admin view - filtered assignments:', filtered);

      renderAdminTable(filtered, tbody);
      bindTableActions();
    }

    async function reloadAndRefresh() {
      try {
        const res = await fetchJson("php/get_assignments.php");
        if (res.success) allAssignments = res.data || [];
      } catch (_) {}
      refreshTable();
    }

    function renderAdminTable(assignments, tbody) {
      if (!assignments || assignments.length === 0) {
        tbody.innerHTML =
          '<tr><td colspan="9" class="muted" style="text-align:center;padding:1.5rem;">No assignments found.</td></tr>';
        return;
      }

      const rows = assignments.map(function (a) {
        const aid = encodeURIComponent(a.assignment_id);
        const deadlineDate = new Date(a.deadline);
        const now = new Date();
        const isPast = deadlineDate < now;
        const deadlineClass = isPast ? 'style="color:var(--danger);"' : '';
        const fileMode = a.allow_multiple_files ? "Multiple" : "Single";

        return (
          "<tr>" +
          '<td class="lm-col-filename">' + escapeHtml(a.title || "") + "</td>" +
          '<td class="lm-col-course">' + escapeHtml(a.course_name || "-") + "</td>" +
          '<td class="lm-col-uploader">' + escapeHtml(a.doctor_name || "-") + "</td>" +
          '<td class="lm-col-year">' + escapeHtml(String(a.year_level ?? "-")) + "</td>" +
          '<td class="lm-col-sem">' + escapeHtml(String(a.semester ?? "-")) + "</td>" +
          '<td ' + deadlineClass + '>' + formatDeadline(a.deadline) + "</td>" +
          "<td>" + fileMode + "</td>" +
          "<td>" + (a.submission_count || 0) + "</td>" +
          '<td class="lm-col-actions">' +
          '<button class="btn btn-sm btn-secondary assign-btn-view" data-id="' + aid + '" aria-label="View submissions">View</button> ' +
          '<button class="btn btn-sm btn-danger assign-btn-delete" data-id="' + aid + '" aria-label="Delete assignment">Delete</button>' +
          "</td>" +
          "</tr>"
        );
      });

      tbody.innerHTML = rows.join("");
    }

    function bindTableActions() {
      tbody.querySelectorAll(".assign-btn-view").forEach(function (btn) {
        btn.addEventListener("click", function () {
          const aid = decodeURIComponent(btn.dataset.id);
          const assignment = allAssignments.find(function (a) {
            return String(a.assignment_id) === aid;
          });
          if (assignment) viewAssignmentSubmissions(assignment);
        });
      });

      tbody.querySelectorAll(".assign-btn-delete").forEach(function (btn) {
        btn.addEventListener("click", function () {
          const aid = decodeURIComponent(btn.dataset.id);
          const assignment = allAssignments.find(function (a) {
            return String(a.assignment_id) === aid;
          });
          if (assignment) {
            deleteAssignment(assignment).then(reloadAndRefresh);
          }
        });
      });
    }

    filterYear.addEventListener("change", refreshTable);
    filterSem.addEventListener("change", refreshTable);
    filterSearch.addEventListener("input", refreshTable);

    refreshTable();
  }

  // ─────────────────────────────────────────────────────────────────
  // SHARED HELPERS
  // ─────────────────────────────────────────────────────────────────

  function createAssignmentCard(assignment, viewMode) {
    const card = document.createElement("div");
    card.className = "assignment-card";

    const header = document.createElement("div");
    header.className = "assignment-card-header";

    const titleDiv = document.createElement("div");
    titleDiv.style.flex = "1";
    const title = document.createElement("h3");
    title.className = "assignment-card-title";
    title.textContent = assignment.title || "Untitled";
    titleDiv.appendChild(title);
    header.appendChild(titleDiv);

    // Deadline badge with countdown
    const deadlineBadge = createDeadlineBadge(assignment.deadline);
    header.appendChild(deadlineBadge);

    card.appendChild(header);

    // Description
    if (assignment.description) {
      const desc = document.createElement("p");
      desc.className = "assignment-card-description";
      desc.textContent = assignment.description;
      card.appendChild(desc);
    }

    // Meta info with countdown timer
    const meta = document.createElement("div");
    meta.className = "assignment-card-meta";
    const countdownText = formatDeadlineCountdown(assignment.deadline);
    meta.innerHTML =
      "<div>📚 " + escapeHtml(assignment.course_name || "") + "</div>" +
      "<div>👨‍🏫 " + escapeHtml(assignment.doctor_name || "") + "</div>" +
      "<div class='assignment-countdown'>" + escapeHtml(countdownText) + " • " + formatDeadline(assignment.deadline) + "</div>" +
      "<div>📎 " + (assignment.allow_multiple_files ? "Multiple files allowed" : "Single file only") + "</div>";
    card.appendChild(meta);

    // Update countdown every minute
    const countdownEl = meta.querySelector(".assignment-countdown");
    if (countdownEl) {
      setInterval(function () {
        const newCountdown = formatDeadlineCountdown(assignment.deadline);
        countdownEl.textContent = newCountdown + " • " + formatDeadline(assignment.deadline);
      }, 60000);
    }

    // Footer with actions
    const footer = document.createElement("div");
    footer.className = "assignment-card-footer";

    if (viewMode === "student") {
      const statusBadge = createSubmissionStatusBadge(assignment);
      footer.appendChild(statusBadge);
    } else {
      // Teacher/admin view: show submission count with ratio
      const submissionInfo = document.createElement("div");
      submissionInfo.style.display = "flex";
      submissionInfo.style.alignItems = "center";
      submissionInfo.style.gap = "0.5rem";
      submissionInfo.style.flex = "1";
      
      const submissionCount = document.createElement("span");
      const submitted = parseInt(assignment.submission_count) || 0;
      const total = parseInt(assignment.total_students) || 0;
      const percentage = total > 0 ? Math.round((submitted / total) * 100) : 0;
      
      submissionCount.innerHTML = "📥 <strong>" + submitted + "/" + total + "</strong> submitted (" + percentage + "%)";
      submissionInfo.appendChild(submissionCount);
      
      // Color-coded progress indicator
      const progressBar = document.createElement("div");
      progressBar.style.flex = "1";
      progressBar.style.height = "6px";
      progressBar.style.background = "var(--surface-1)";
      progressBar.style.borderRadius = "3px";
      progressBar.style.overflow = "hidden";
      
      const progressFill = document.createElement("div");
      progressFill.style.height = "100%";
      progressFill.style.width = percentage + "%";
      progressFill.style.background = percentage >= 80 ? "#10b981" : percentage >= 50 ? "#f59e0b" : "#ef4444";
      progressFill.style.transition = "width 0.3s ease";
      progressBar.appendChild(progressFill);
      
      submissionInfo.appendChild(progressBar);
      footer.appendChild(submissionInfo);
    }

    const actions = document.createElement("div");
    actions.className = "assignment-card-actions";

    if (viewMode === "teacher" || viewMode === "admin") {
      const viewBtn = document.createElement("button");
      viewBtn.className = "btn btn-secondary btn-sm";
      viewBtn.textContent = "View Submissions";
      viewBtn.addEventListener("click", function () {
        viewAssignmentSubmissions(assignment);
      });
      actions.appendChild(viewBtn);

      const deleteBtn = document.createElement("button");
      deleteBtn.className = "btn btn-danger btn-sm";
      deleteBtn.textContent = "Delete";
      deleteBtn.addEventListener("click", function (e) {
        e.stopPropagation();
        if (confirm("Delete assignment '" + assignment.title + "'? This will delete all submissions and cannot be undone.")) {
          deleteAssignment(assignment);
        }
      });
      actions.appendChild(deleteBtn);
    }

    footer.appendChild(actions);
    card.appendChild(footer);
    
    // Student view: Show uploaded files list and drag-drop zone AFTER footer
    if (viewMode === "student") {
      // Show uploaded files list if any
      if (assignment.student_submissions && assignment.student_submissions.length > 0) {
        const filesList = document.createElement("div");
        filesList.className = "uploaded-files-list";
        filesList.style.marginTop = "1rem";
        filesList.style.fontSize = "0.875rem";
        
        const filesHeader = document.createElement("div");
        filesHeader.style.fontWeight = "600";
        filesHeader.style.marginBottom = "0.5rem";
        filesHeader.textContent = "Uploaded Files:";
        filesList.appendChild(filesHeader);
        
        assignment.student_submissions.forEach(function (sub) {
          const fileItem = document.createElement("div");
          fileItem.style.display = "flex";
          fileItem.style.alignItems = "center";
          fileItem.style.gap = "0.5rem";
          fileItem.style.padding = "0.5rem";
          fileItem.style.background = "var(--surface-1)";
          fileItem.style.borderRadius = "6px";
          fileItem.style.marginBottom = "0.5rem";
          
          const fileIcon = document.createElement("span");
          fileIcon.textContent = "📄";
          fileItem.appendChild(fileIcon);
          
          const fileName = document.createElement("span");
          fileName.style.flex = "1";
          fileName.style.wordBreak = "break-word";
          fileName.textContent = sub.original_filename;
          fileItem.appendChild(fileName);
          
          const deleteFileBtn = document.createElement("button");
          deleteFileBtn.className = "btn-icon-sm";
          deleteFileBtn.innerHTML = "🗑️";
          deleteFileBtn.title = "Delete this file";
          deleteFileBtn.style.background = "var(--danger)";
          deleteFileBtn.style.color = "white";
          deleteFileBtn.style.border = "none";
          deleteFileBtn.style.borderRadius = "4px";
          deleteFileBtn.style.padding = "0.25rem 0.5rem";
          deleteFileBtn.style.cursor = "pointer";
          deleteFileBtn.addEventListener("click", function (e) {
            e.stopPropagation();
            deleteStudentSubmission(sub.submission_id, function () {
              initStudentView();
            });
          });
          fileItem.appendChild(deleteFileBtn);
          
          filesList.appendChild(fileItem);
        });
        
        card.appendChild(filesList);
      }
      
      // Drag-and-drop zone
      const dropZone = document.createElement("div");
      dropZone.className = "file-drop-zone";
      dropZone.style.padding = "1rem";
      dropZone.style.border = "2px dashed var(--card-border)";
      dropZone.style.borderRadius = "8px";
      dropZone.style.textAlign = "center";
      dropZone.style.cursor = "pointer";
      dropZone.style.marginTop = "1rem";
      dropZone.style.transition = "all 0.2s ease";
      dropZone.innerHTML = "📎 Drag & drop file here or click to browse";
      
      // Check if deadline passed
      const now = new Date();
      const deadlineDate = new Date(assignment.deadline);
      const isPastDeadline = now > deadlineDate;
      
      if (isPastDeadline) {
        dropZone.style.opacity = "0.5";
        dropZone.style.cursor = "not-allowed";
        dropZone.innerHTML = "🔒 Deadline passed - uploads disabled";
      } else {
        // Drag events
        dropZone.addEventListener("dragover", function (e) {
          e.preventDefault();
          dropZone.style.background = "var(--primary-fade)";
          dropZone.style.borderColor = "var(--primary)";
        });
        
        dropZone.addEventListener("dragleave", function () {
          dropZone.style.background = "";
          dropZone.style.borderColor = "var(--card-border)";
        });
        
        dropZone.addEventListener("drop", function (e) {
          e.preventDefault();
          dropZone.style.background = "";
          dropZone.style.borderColor = "var(--card-border)";
          
          const files = e.dataTransfer.files;
          if (files.length > 0) {
            uploadStudentFile(assignment, files[0]);
          }
        });
        
        dropZone.addEventListener("click", function () {
          showStudentUploadModal(assignment);
        });
      }
      
      card.appendChild(dropZone);
    }

    return card;
  }

  function formatDeadlineCountdown(deadlineStr) {
    const deadline = new Date(deadlineStr);
    const now = new Date();
    const diffMs = deadline - now;

    if (diffMs < 0) {
      return "⏰ Overdue";
    }

    const days = Math.floor(diffMs / (1000 * 60 * 60 * 24));
    const hours = Math.floor((diffMs % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
    const minutes = Math.floor((diffMs % (1000 * 60 * 60)) / (1000 * 60));

    if (days > 0) {
      return "Due in " + days + " day" + (days > 1 ? "s" : "") + " " + hours + " hour" + (hours !== 1 ? "s" : "");
    } else if (hours > 0) {
      return "Due in " + hours + " hour" + (hours > 1 ? "s" : "") + " " + minutes + " min";
    } else {
      return "Due in " + minutes + " minute" + (minutes !== 1 ? "s" : "");
    }
  }

  function createDeadlineBadge(deadline) {
    const badge = document.createElement("span");
    badge.className = "deadline-badge";

    const now = new Date();
    const deadlineDate = new Date(deadline);
    const hoursUntil = (deadlineDate - now) / (1000 * 60 * 60);

    if (hoursUntil < 0) {
      badge.classList.add("expired");
      badge.textContent = "⏰ Expired";
    } else if (hoursUntil < 24) {
      badge.classList.add("warning");
      badge.textContent = "⚠️ Due Soon";
    } else {
      badge.classList.add("active");
      badge.textContent = "✓ Active";
    }

    return badge;
  }

  function createSubmissionStatusBadge(assignment) {
    const badge = document.createElement("span");
    badge.className = "submission-status-badge";

    if (assignment.student_has_submitted) {
      badge.classList.add("submitted");
      badge.textContent = "✓ Submitted";
    } else {
      const now = new Date();
      const deadlineDate = new Date(assignment.deadline);
      if (now > deadlineDate) {
        badge.classList.add("not-submitted");
        badge.textContent = "✗ Not Submitted";
      } else {
        badge.classList.add("pending");
        badge.textContent = "⏳ Pending";
      }
    }

    return badge;
  }

  function formatDeadline(deadline) {
    if (!deadline) return "-";
    const d = new Date(deadline);
    return d.toLocaleDateString(undefined, {
      year: "numeric",
      month: "short",
      day: "numeric",
      hour: "2-digit",
      minute: "2-digit",
    });
  }

  async function deleteAssignment(assignment) {
    try {
      await fetchJson("php/delete_assignment.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ assignment_id: assignment.assignment_id }),
      });

      showSuccess("Assignment deleted successfully.");
      // Refresh teacher view
      initTeacherView();
      return Promise.resolve();
    } catch (err) {
      showError(err.message || "Failed to delete assignment.");
      return Promise.reject(err);
    }
  }

  async function viewAssignmentSubmissions(assignment) {
    // Create modal
    const modal = document.createElement("div");
    modal.className = "modal-overlay";
    modal.style.display = "flex";

    const modalBox = document.createElement("div");
    modalBox.className = "modal-box";
    modalBox.style.maxWidth = "800px";

    const title = document.createElement("h2");
    title.className = "modal-title";
    title.textContent = "Submissions: " + assignment.title;
    modalBox.appendChild(title);

    const loadingEl = document.createElement("p");
    loadingEl.className = "status";
    loadingEl.textContent = "Loading submissions…";
    modalBox.appendChild(loadingEl);

    const closeBtn = document.createElement("button");
    closeBtn.className = "btn btn-secondary";
    closeBtn.textContent = "Close";
    closeBtn.style.marginTop = "1rem";
    closeBtn.addEventListener("click", function () {
      document.body.removeChild(modal);
    });
    modalBox.appendChild(closeBtn);

    modal.appendChild(modalBox);
    document.body.appendChild(modal);

    try {
      const resp = await fetchJson(
        "php/get_assignment_detail.php?assignment_id=" + assignment.assignment_id
      );
      loadingEl.remove();

      const submissions = resp.data.submissions || [];
      const submitted = submissions.length;
      const total = parseInt(assignment.total_students) || 0;
      const percentage = total > 0 ? Math.round((submitted / total) * 100) : 0;
      
      // Check how many submitted before deadline
      const deadlineDate = new Date(assignment.deadline);
      const onTime = submissions.filter(function (sub) {
        return new Date(sub.submitted_at) <= deadlineDate;
      }).length;
      
      const late = submitted - onTime;

      // Quick stats panel
      const statsPanel = document.createElement("div");
      statsPanel.className = "stats-panel";
      statsPanel.style.display = "grid";
      statsPanel.style.gridTemplateColumns = "repeat(auto-fit, minmax(140px, 1fr))";
      statsPanel.style.gap = "0.75rem";
      statsPanel.style.marginBottom = "1.5rem";
      statsPanel.style.padding = "1rem";
      statsPanel.style.background = "var(--surface-1)";
      statsPanel.style.borderRadius = "8px";
      
      const stats = [
        { label: "Submitted", value: submitted + "/" + total, icon: "📥", color: "#10b981" },
        { label: "Completion", value: percentage + "%", icon: "📊", color: "#3b82f6" },
        { label: "On Time", value: onTime, icon: "✓", color: "#10b981" },
        { label: "Late", value: late, icon: "⏰", color: "#ef4444" }
      ];
      
      stats.forEach(function (stat) {
        const statCard = document.createElement("div");
        statCard.style.textAlign = "center";
        statCard.style.padding = "0.75rem";
        statCard.style.background = "var(--card)";
        statCard.style.borderRadius = "6px";
        statCard.style.border = "1px solid var(--card-border)";
        
        const icon = document.createElement("div");
        icon.style.fontSize = "1.5rem";
        icon.style.marginBottom = "0.25rem";
        icon.textContent = stat.icon;
        statCard.appendChild(icon);
        
        const value = document.createElement("div");
        value.style.fontSize = "1.25rem";
        value.style.fontWeight = "700";
        value.style.color = stat.color;
        value.textContent = stat.value;
        statCard.appendChild(value);
        
        const label = document.createElement("div");
        label.style.fontSize = "0.75rem";
        label.style.color = "var(--text-secondary)";
        label.style.marginTop = "0.25rem";
        label.textContent = stat.label;
        statCard.appendChild(label);
        
        statsPanel.appendChild(statCard);
      });
      
      modalBox.insertBefore(statsPanel, closeBtn);

      if (submissions.length === 0) {
        const empty = document.createElement("p");
        empty.className = "status";
        empty.textContent = "No submissions yet.";
        modalBox.insertBefore(empty, closeBtn);
        return;
      }

      const list = document.createElement("div");
      list.className = "submissions-list";

      submissions.forEach(function (sub) {
        const item = document.createElement("div");
        item.className = "submission-item";

        const info = document.createElement("div");
        info.className = "submission-item-info";
        
        const wasLate = new Date(sub.submitted_at) > deadlineDate;
        const lateLabel = wasLate ? ' <span style="color:#ef4444;font-weight:600;">(Late)</span>' : '';
        
        info.innerHTML =
          '<span class="submission-item-icon">📎</span>' +
          '<div class="submission-item-details">' +
          '<p class="submission-item-name">' + escapeHtml(sub.original_filename) + lateLabel + "</p>" +
          '<p class="submission-item-meta">By: ' + escapeHtml(sub.student_name) +
          " • " + formatDeadline(sub.submitted_at) + "</p>" +
          "</div>";

        const actions = document.createElement("div");
        actions.className = "submission-item-actions";

        const downloadBtn = document.createElement("button");
        downloadBtn.className = "btn btn-secondary btn-sm";
        downloadBtn.textContent = "Download";
        downloadBtn.addEventListener("click", function () {
          window.location.href =
            "php/download_assignment_submission.php?submission_id=" + sub.submission_id;
        });
        actions.appendChild(downloadBtn);

        item.appendChild(info);
        item.appendChild(actions);
        list.appendChild(item);
      });

      modalBox.insertBefore(list, closeBtn);
    } catch (err) {
      loadingEl.remove();
      const errorEl = document.createElement("p");
      errorEl.className = "status status-error";
      errorEl.textContent = err.message || "Failed to load submissions.";
      modalBox.insertBefore(errorEl, closeBtn);
    }
  }

  async function uploadStudentFile(assignment, file) {
    if (!file) {
      showError("No file selected.");
      return;
    }

    if (file.size > 52428800) {
      showError("File exceeds 50 MB limit.");
      return;
    }

    const formData = new FormData();
    formData.append("assignment_id", assignment.assignment_id);
    formData.append("file", file);

    try {
      showSuccess("Uploading " + file.name + "...");
      
      const resp = await fetch("php/upload_assignment_submission.php", {
        method: "POST",
        credentials: "same-origin",
        body: formData,
      });

      let data;
      try {
        data = await resp.json();
      } catch (_) {
        throw new Error("Invalid server response.");
      }

      if (!resp.ok || !data.success) {
        throw new Error(data.error || "Upload failed.");
      }

      showSuccess("File submitted successfully.");
      // Refresh student view
      initStudentView();
    } catch (err) {
      showError(err.message || "Failed to upload file.");
    }
  }

  async function deleteStudentSubmission(submissionId, onSuccess) {
    if (!confirm("Delete this file? This action cannot be undone.")) {
      return;
    }

    try {
      await fetchJson("php/delete_assignment_submission.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ submission_id: submissionId }),
      });

      showSuccess("File deleted successfully.");
      if (typeof onSuccess === "function") {
        onSuccess();
      }
    } catch (err) {
      showError(err.message || "Failed to delete file.");
    }
  }

  function showStudentUploadModal(assignment) {
    // Check deadline
    const now = new Date();
    const deadlineDate = new Date(assignment.deadline);
    if (now > deadlineDate) {
      showError("Assignment deadline has passed. Submissions are no longer accepted.");
      return;
    }

    // Create modal
    const modal = document.createElement("div");
    modal.className = "modal-overlay";
    modal.style.display = "flex";

    const modalBox = document.createElement("div");
    modalBox.className = "modal-box";

    const title = document.createElement("h2");
    title.className = "modal-title";
    title.textContent = "Upload Submission";
    modalBox.appendChild(title);

    const form = document.createElement("form");
    form.className = "assignment-upload-form";

    const dropZone = document.createElement("div");
    dropZone.className = "file-drop-zone";
    dropZone.innerHTML = `
      <div class="drop-zone-content">
        <svg viewBox="0 0 24 24" width="48" height="48" style="opacity:0.5; margin-bottom:12px;">
          <path fill="currentColor" d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20M12,19L8,15H10.5V12H13.5V15H16L12,19Z"/>
        </svg>
        <p class="drop-zone-text">Drag and drop file here</p>
        <p class="drop-zone-or">or</p>
        <label for="submissionFileInput" class="drop-zone-btn">Browse Files</label>
        <p class="drop-zone-hint">PDF, PPTX, ZIP, or RAR (max 50 MB)</p>
      </div>
      <div class="drop-zone-hover-overlay">
        <svg viewBox="0 0 24 24" width="64" height="64">
          <path fill="currentColor" d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20M12,19L8,15H10.5V12H13.5V15H16L12,19Z"/>
        </svg>
        <p>Drop file to upload</p>
      </div>
    `;

    const fileInput = document.createElement("input");
    fileInput.type = "file";
    fileInput.id = "submissionFileInput";
    fileInput.accept = ".pdf,.pptx,.zip,.rar";
    fileInput.required = true;
    fileInput.style.display = "none";

    // Drag-and-drop
    ["dragenter", "dragover", "dragleave", "drop"].forEach(function (eventName) {
      dropZone.addEventListener(eventName, function (e) {
        e.preventDefault();
        e.stopPropagation();
      });
    });

    dropZone.addEventListener("dragover", function () {
      dropZone.classList.add("drag-over");
    });

    dropZone.addEventListener("dragleave", function () {
      dropZone.classList.remove("drag-over");
    });

    dropZone.addEventListener("drop", function (e) {
      dropZone.classList.remove("drag-over");
      const files = e.dataTransfer.files;
      if (files.length > 0) {
        fileInput.files = files;
        updateDropZoneText(dropZone, files[0].name);
      }
    });

    fileInput.addEventListener("change", function (e) {
      if (e.target.files.length > 0) {
        updateDropZoneText(dropZone, e.target.files[0].name);
      }
    });

    function updateDropZoneText(zone, filename) {
      const textEl = zone.querySelector(".drop-zone-text");
      if (textEl) {
        textEl.innerHTML = "<strong>Selected:</strong> " + escapeHtml(filename);
        textEl.style.color = "var(--success)";
      }
    }

    const statusEl = document.createElement("p");
    statusEl.className = "status";
    statusEl.style.display = "none";

    const actions = document.createElement("div");
    actions.className = "modal-actions";
    const cancelBtn = document.createElement("button");
    cancelBtn.type = "button";
    cancelBtn.className = "btn btn-secondary";
    cancelBtn.textContent = "Cancel";
    const submitBtn = document.createElement("button");
    submitBtn.type = "submit";
    submitBtn.className = "btn btn-primary";
    submitBtn.textContent = "Submit";
    actions.appendChild(cancelBtn);
    actions.appendChild(submitBtn);

    form.appendChild(dropZone);
    form.appendChild(fileInput);
    form.appendChild(statusEl);
    form.appendChild(actions);
    modalBox.appendChild(form);
    modal.appendChild(modalBox);
    document.body.appendChild(modal);

    cancelBtn.addEventListener("click", function () {
      document.body.removeChild(modal);
    });

    form.addEventListener("submit", async function (e) {
      e.preventDefault();

      const file = fileInput.files[0];
      if (!file) {
        statusEl.textContent = "Please select a file.";
        statusEl.className = "status status-error";
        statusEl.style.display = "";
        return;
      }

      if (file.size > 52428800) {
        statusEl.textContent = "File exceeds 50 MB limit.";
        statusEl.className = "status status-error";
        statusEl.style.display = "";
        return;
      }

      submitBtn.disabled = true;
      submitBtn.textContent = "Uploading…";
      statusEl.textContent = "Uploading…";
      statusEl.className = "status";
      statusEl.style.display = "";

      const formData = new FormData();
      formData.append("assignment_id", assignment.assignment_id);
      formData.append("file", file);

      try {
        const resp = await fetch("php/upload_assignment_submission.php", {
          method: "POST",
          credentials: "same-origin",
          body: formData,
        });

        let data;
        try {
          data = await resp.json();
        } catch (_) {
          throw new Error("Invalid server response.");
        }

        if (!resp.ok || !data.success) {
          throw new Error(data.error || "Upload failed.");
        }

        showSuccess("File submitted successfully.");
        document.body.removeChild(modal);

        // Refresh student view
        initStudentView();
      } catch (err) {
        statusEl.textContent = err.message || "Failed to upload file.";
        statusEl.className = "status status-error";
        statusEl.style.display = "";
        submitBtn.disabled = false;
        submitBtn.textContent = "Submit";
      }
    });
  }

  async function viewStudentSubmissions(assignment) {
    const modal = document.createElement("div");
    modal.className = "modal-overlay";
    modal.style.display = "flex";

    const modalBox = document.createElement("div");
    modalBox.className = "modal-box";

    const title = document.createElement("h2");
    title.className = "modal-title";
    title.textContent = "My Submissions";
    modalBox.appendChild(title);

    const loadingEl = document.createElement("p");
    loadingEl.className = "status";
    loadingEl.textContent = "Loading…";
    modalBox.appendChild(loadingEl);

    const closeBtn = document.createElement("button");
    closeBtn.className = "btn btn-secondary";
    closeBtn.textContent = "Close";
    closeBtn.style.marginTop = "1rem";
    closeBtn.addEventListener("click", function () {
      document.body.removeChild(modal);
    });
    modalBox.appendChild(closeBtn);

    modal.appendChild(modalBox);
    document.body.appendChild(modal);

    try {
      const resp = await fetchJson(
        "php/get_assignment_detail.php?assignment_id=" + assignment.assignment_id
      );
      loadingEl.remove();

      const submissions = resp.data.submissions || [];

      if (submissions.length === 0) {
        const empty = document.createElement("p");
        empty.className = "status";
        empty.textContent = "No submissions found.";
        modalBox.insertBefore(empty, closeBtn);
        return;
      }

      const list = document.createElement("div");
      list.className = "submissions-list";

      submissions.forEach(function (sub) {
        const item = document.createElement("div");
        item.className = "submission-item";

        const info = document.createElement("div");
        info.className = "submission-item-info";
        info.innerHTML =
          '<span class="submission-item-icon">📎</span>' +
          '<div class="submission-item-details">' +
          '<p class="submission-item-name">' + escapeHtml(sub.original_filename) + "</p>" +
          '<p class="submission-item-meta">Submitted: ' + formatDeadline(sub.submitted_at) + "</p>" +
          "</div>";

        const actions = document.createElement("div");
        actions.className = "submission-item-actions";

        const downloadBtn = document.createElement("button");
        downloadBtn.className = "btn btn-secondary btn-sm";
        downloadBtn.textContent = "Download";
        downloadBtn.addEventListener("click", function () {
          window.location.href =
            "php/download_assignment_submission.php?submission_id=" + sub.submission_id;
        });
        actions.appendChild(downloadBtn);

        // Students can delete before deadline
        const now = new Date();
        const deadlineDate = new Date(assignment.deadline);
        if (now < deadlineDate) {
          const deleteBtn = document.createElement("button");
          deleteBtn.className = "btn btn-danger btn-sm";
          deleteBtn.textContent = "Delete";
          deleteBtn.addEventListener("click", async function () {
            if (!confirm("Delete this submission?")) return;

            try {
              await fetchJson("php/delete_assignment_submission.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ submission_id: sub.submission_id }),
              });

              showSuccess("Submission deleted.");
              document.body.removeChild(modal);
              initStudentView();
            } catch (err) {
              showError(err.message || "Failed to delete submission.");
            }
          });
          actions.appendChild(deleteBtn);
        }

        item.appendChild(info);
        item.appendChild(actions);
        list.appendChild(item);
      });

      modalBox.insertBefore(list, closeBtn);
    } catch (err) {
      loadingEl.remove();
      const errorEl = document.createElement("p");
      errorEl.className = "status status-error";
      errorEl.textContent = err.message || "Failed to load submissions.";
      modalBox.insertBefore(errorEl, closeBtn);
    }
  }
})();

// ═══════════════════════════════════════════════════════════════════
// IMPROVED ADMIN VIEW - MATCHES LECTURES STYLE
// ═══════════════════════════════════════════════════════════════════

window.dmportal.initAdminView = async function() {
  const { fetchJson, escapeHtml, showError, showSuccess } = window.dmportal || {};
  
  const root = document.getElementById("assignmentsRoot");
  if (!root) return;

  // 1. Show loading state
  root.innerHTML = '<p class="status">Loading assignments…</p>';

  // 2. Fetch all assignments
  let allAssignments = [];
  try {
    const res = await fetchJson("php/get_assignments.php");
    if (!res.success) throw new Error(res.error || "Failed to load assignments.");
    allAssignments = res.data || [];
  } catch (err) {
    root.innerHTML =
      '<p class="status status-error">Could not load assignments: ' +
      escapeHtml(err.message || "Unknown error.") +
      "</p>";
    return;
  }

  // 3. Build UI shell (same style as lectures)
  root.innerHTML = [
    '<header class="main-header" style="margin-bottom:16px;">',
      '<div>',
        '<h2>Assignments</h2>',
        '<p class="muted">Manage all assignments across courses, years, and semesters.</p>',
      "</div>",
    "</header>",

    '<section class="panel">',
      '<div class="filter-bar" role="group" aria-label="Filter assignments">',
        '<div class="field">',
          '<label class="muted" style="font-size:0.85rem;" for="filterYear">Academic Year</label>',
          '<select id="filterYear" class="navlink" aria-label="Filter by year level">',
            '<option value="">All Years</option>',
            '<option value="1">Year 1</option>',
            '<option value="2">Year 2</option>',
            '<option value="3">Year 3</option>',
          "</select>",
        "</div>",
        '<div class="field">',
          '<label class="muted" style="font-size:0.85rem;" for="filterSemester">Semester</label>',
          '<select id="filterSemester" class="navlink" aria-label="Filter by semester">',
            '<option value="">All Sem</option>',
            '<option value="1">Sem 1</option>',
            '<option value="2">Sem 2</option>',
          "</select>",
        "</div>",
        '<div class="field" style="flex:1;min-width:200px;">',
          '<label class="muted" style="font-size:0.85rem;" for="filterSearch">Search</label>',
          '<input id="filterSearch" class="navlink" type="text"',
            ' placeholder="Title or course…" autocomplete="off"',
            ' aria-label="Search assignments" />',
        "</div>",
      "</div>",

      '<div class="schedule-wrap" style="margin-top:16px;">',
        '<table class="data-table schedule-grid" style="width:100%;" role="table" aria-label="All assignments">',
          "<thead>",
            "<tr>",
              '<th scope="col">Title</th>',
              '<th scope="col">Course</th>',
              '<th scope="col">Teacher</th>',
              '<th scope="col">Year</th>',
              '<th scope="col">Sem</th>',
              '<th scope="col">Deadline</th>',
              '<th scope="col">Files</th>',
              '<th scope="col">Submissions</th>',
              '<th scope="col">Actions</th>',
            "</tr>",
          "</thead>",
          '<tbody id="assignmentsAdminTbody"></tbody>',
        "</table>",
      "</div>",
    "</section>",
  ].join("");

  const tbody = document.getElementById("assignmentsAdminTbody");
  const filterYear = document.getElementById("filterYear");
  const filterSem = document.getElementById("filterSemester");
  const filterSearch = document.getElementById("filterSearch");

  function getCurrentFilters() {
    return {
      yearLevel: filterYear.value,
      semester: filterSem.value,
      search: filterSearch.value,
    };
  }

  function refreshTable() {
    const filters = getCurrentFilters();
    const needle = (filters.search || "").trim().toLowerCase();

    const filtered = allAssignments.filter(function (a) {
      if (filters.yearLevel !== "All" && String(a.year_level) !== filters.yearLevel) return false;
      if (filters.semester !== "All" && String(a.semester) !== filters.semester) return false;
      if (needle) {
        const title = String(a.title || "").toLowerCase();
        const course = String(a.course_name || "").toLowerCase();
        if (!title.includes(needle) && !course.includes(needle)) return false;
      }
      return true;
    });

    renderAdminTable(filtered, tbody);
    bindTableActions();
  }

  async function reloadAndRefresh() {
    try {
      const res = await fetchJson("php/get_assignments.php");
      if (res.success) allAssignments = res.data || [];
    } catch (_) {}
    refreshTable();
  }

  function renderAdminTable(assignments, tbody) {
    if (!assignments || assignments.length === 0) {
      tbody.innerHTML =
        '<tr><td colspan="9" class="muted" style="text-align:center;padding:1.5rem;">No assignments found.</td></tr>';
      return;
    }

    const rows = assignments.map(function (a) {
      const aid = encodeURIComponent(a.assignment_id);
      const deadlineDate = new Date(a.deadline);
      const now = new Date();
      const isPast = deadlineDate < now;
      const deadlineClass = isPast ? 'style="color:var(--danger);"' : '';
      const fileMode = a.allow_multiple_files ? "Multiple" : "Single";

      return (
        "<tr>" +
        '<td class="lm-col-filename">' + escapeHtml(a.title || "") + "</td>" +
        '<td class="lm-col-course">' + escapeHtml(a.course_name || "-") + "</td>" +
        '<td class="lm-col-uploader">' + escapeHtml(a.doctor_name || "-") + "</td>" +
        '<td class="lm-col-year">' + escapeHtml(String(a.year_level ?? "-")) + "</td>" +
        '<td class="lm-col-sem">' + escapeHtml(String(a.semester ?? "-")) + "</td>" +
        '<td ' + deadlineClass + '>' + formatDeadline(a.deadline) + "</td>" +
        "<td>" + fileMode + "</td>" +
        "<td>" + (a.submission_count || 0) + "</td>" +
        '<td class="lm-col-actions">' +
        '<button class="btn btn-sm btn-secondary assign-btn-view" data-id="' + aid + '" aria-label="View submissions">View</button> ' +
        '<button class="btn btn-sm btn-danger assign-btn-delete" data-id="' + aid + '" aria-label="Delete assignment">Delete</button>' +
        "</td>" +
        "</tr>"
      );
    });

    tbody.innerHTML = rows.join("");
  }

  function bindTableActions() {
    tbody.querySelectorAll(".assign-btn-view").forEach(function (btn) {
      btn.addEventListener("click", function () {
        const aid = decodeURIComponent(btn.dataset.id);
        const assignment = allAssignments.find(function (a) {
          return String(a.assignment_id) === aid;
        });
        if (assignment) viewAssignmentSubmissions(assignment);
      });
    });

    tbody.querySelectorAll(".assign-btn-delete").forEach(function (btn) {
      btn.addEventListener("click", function () {
        const aid = decodeURIComponent(btn.dataset.id);
        const assignment = allAssignments.find(function (a) {
          return String(a.assignment_id) === aid;
        });
        if (assignment) {
          deleteAssignment(assignment).then(reloadAndRefresh);
        }
      });
    });
  }

  filterYear.addEventListener("change", refreshTable);
  filterSem.addEventListener("change", refreshTable);
  filterSearch.addEventListener("input", refreshTable);

  refreshTable();
};
