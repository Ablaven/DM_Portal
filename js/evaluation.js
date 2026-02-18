(function () {
  "use strict";

  const {
    fetchJson,
    setStatusById,
    escapeHtml,
    getGlobalFilters,
    setGlobalFilters,
    getEffectivePageFilters,
    initPageFiltersUI,
    applyPageFiltersToCourses,
    doesItemMatchPageFilters,
  } = window.dmportal || {};

  let CATEGORIES = [];

  function initEvaluationPage(options = {}) {
    const { canConfigure = true } = options;
    const courseSelect = document.getElementById("evaluationCourseSelect");
    const doctorSelect = document.getElementById("evaluationDoctorFilter");
    const refreshBtn = document.getElementById("evaluationRefresh");
    const statusId = "evaluationStatus";

    const configBody = document.getElementById("evaluationConfigBody");
    const configStatus = document.getElementById("evaluationConfigStatus");
    const configSave = document.getElementById("saveEvaluationConfig");
    const addItemBtn = document.getElementById("addEvaluationItem");

    const gradesHead = document.getElementById("evaluationGradesHead");
    const gradesBody = document.getElementById("evaluationGradesBody");
    const gradesStatus = document.getElementById("evaluationGradesStatus");
    const gradesSave = document.getElementById("saveEvaluationGrades");
    const studentSearch = document.getElementById("evaluationStudentSearch");

    const tabs = document.querySelectorAll(".tabs [data-tab]");
    const panels = document.querySelectorAll(".tab-panel");

    if (!courseSelect || !gradesBody || !gradesHead) return;
    if (!canConfigure && (!gradesBody || !gradesHead)) return;

    let currentCourseId = 0;
    let configItems = [];
    let studentsCache = [];
    let coursesCache = [];
    let doctorsCache = [];

    function setStatus(msg, type = "") {
      setStatusById?.(statusId, msg, type);
    }

    function showAlert(msg, ok = false) {
      const alert = document.getElementById("evaluationAlert");
      if (!alert) return;
      alert.textContent = msg || "";
      alert.hidden = !msg;
      alert.style.background = ok ? `rgba(${getComputedStyle(document.documentElement).getPropertyValue('--success-rgb')}, 0.15)` : `rgba(${getComputedStyle(document.documentElement).getPropertyValue('--danger-rgb')}, 0.15)`;
      alert.style.borderColor = ok ? `rgba(${getComputedStyle(document.documentElement).getPropertyValue('--success-rgb')}, 0.35)` : `rgba(${getComputedStyle(document.documentElement).getPropertyValue('--danger-rgb')}, 0.35)`;
      alert.style.color = ok ? getComputedStyle(document.documentElement).getPropertyValue('--success') : getComputedStyle(document.documentElement).getPropertyValue('--danger');
    }

    function setConfigStatus(msg, ok = true) {
      if (configStatus) {
        configStatus.textContent = msg || "";
        configStatus.style.color = ok ? getComputedStyle(document.documentElement).getPropertyValue('--success') : getComputedStyle(document.documentElement).getPropertyValue('--danger');
      }
      if (msg && !ok) {
        showAlert(msg, false);
      }
    }

    function setGradesStatus(msg, ok = true) {
      if (gradesStatus) {
        gradesStatus.textContent = msg || "";
        gradesStatus.style.color = ok ? getComputedStyle(document.documentElement).getPropertyValue('--success') : getComputedStyle(document.documentElement).getPropertyValue('--danger');
      }
      if (msg && !ok) {
        showAlert(msg, false);
      }
    }

    function switchTab(tabKey) {
      tabs.forEach((btn) => {
        btn.classList.toggle("active", btn.dataset.tab === tabKey);
      });
      panels.forEach((panel) => {
        panel.hidden = panel.dataset.tabPanel !== tabKey;
      });
    }

    tabs.forEach((btn) => {
      btn.addEventListener("click", () => switchTab(btn.dataset.tab));
    });

    function renderConfig(items) {
      configBody.innerHTML = "";
      if (!items.length) {
        addEmptyRow();
        return;
      }
      items.forEach((item) => appendConfigRow(item));
    }

    function updateLabelState(row) {
      const categoryKey = row.querySelector(".eval-category")?.value || "";
      const labelInput = row.querySelector(".eval-label");
      const splitBtn = row.querySelector(".eval-split");
      const category = CATEGORIES.find((c) => c.key === categoryKey) || {};
      if (!labelInput) return;

      if (category.autoLabel) {
        labelInput.value = category.label;
        labelInput.placeholder = category.label;
        labelInput.disabled = category.key === "attendance";
      } else {
        labelInput.disabled = false;
        labelInput.placeholder = "";
      }

      if (splitBtn) {
        splitBtn.style.display = category.noSplit ? "none" : "inline-flex";
      }
    }

    function appendConfigRow(item = {}) {
      const row = document.createElement("tr");
      row.innerHTML = `
        <td>
          <select class="eval-category">
            ${CATEGORIES.map(
              (c) => `<option value="${c.key}" ${item.category === c.key ? "selected" : ""}>${escapeHtml(c.label)}</option>`
            ).join("")}
            <option value="__add__">+ Add Category…</option>
          </select>
        </td>
        <td>
          <input type="text" class="eval-label" placeholder="" value="${escapeHtml(item.label || "")}" />
        </td>
        <td class="col-number">
          <input type="number" min="0" max="100" step="0.01" class="eval-weight" value="${item.weight ?? ""}" style="max-width:120px;" />
        </td>
        <td>
          <button type="button" class="btn btn-secondary btn-small eval-split">Split</button>
          <button type="button" class="btn btn-secondary btn-small eval-remove">Remove</button>
        </td>
      `;
      row.querySelector(".eval-remove").addEventListener("click", () => {
        row.remove();
        updateTotalMarks();
      });
      row.querySelector(".eval-category").addEventListener("change", async (e) => {
        if (e.target.value === "__add__") {
          openCategoryModal(e.target);
          return;
        }
        updateLabelState(row);
      });
      row.querySelector(".eval-weight").addEventListener("input", updateTotalMarks);
      row.querySelector(".eval-split").addEventListener("click", () => handleSplit(row));
      updateLabelState(row);
      configBody.appendChild(row);
      updateTotalMarks();
    }

    function addEmptyRow() {
      appendConfigRow({ category: "participation", label: "", weight: "" });
    }

    function collectConfigItems() {
      const rows = Array.from(configBody.querySelectorAll("tr"));
      const items = [];
      rows.forEach((row, idx) => {
        const category = row.querySelector(".eval-category")?.value || "";
        const label = row.querySelector(".eval-label")?.value || "";
        const weight = row.querySelector(".eval-weight")?.value || "";
        items.push({ category, label, weight, sort_order: idx });
      });
      return items;
    }

    function updateTotalMarks() {
      const totalEl = document.getElementById("evaluationConfigTotal");
      const items = collectConfigItems();
      const sum = items.reduce((acc, i) => acc + Number(i.weight || 0), 0);
      if (totalEl) {
        totalEl.textContent = `Total marks: ${sum.toFixed(2)} / 100`;
        totalEl.style.color = sum > 100 ? getComputedStyle(document.documentElement).getPropertyValue('--danger') : "";
      }
    }

    function validateUniqueCategories(items) {
      const counts = items.reduce((acc, item) => {
        acc[item.category] = (acc[item.category] || 0) + 1;
        return acc;
      }, {});
      if ((counts.attendance || 0) > 1) {
        return 'Only one Attendance item is allowed.';
      }
      if ((counts.participation || 0) > 1) {
        return 'Only one Participation item is allowed.';
      }
      return '';
    }

    const splitModal = document.getElementById("evaluationSplitModal");
    const splitCountInput = document.getElementById("evaluationSplitCount");
    const splitConfirm = document.getElementById("evaluationSplitConfirm");
    const splitMeta = document.getElementById("evaluationSplitMeta");
    let splitRow = null;

    const categoryModal = document.getElementById("evaluationCategoryModal");
    const categoryNameInput = document.getElementById("evaluationCategoryName");
    const categorySave = document.getElementById("evaluationCategorySave");
    let categorySelectRef = null;

    function closeSplitModal() {
      if (splitModal) {
        splitModal.setAttribute("aria-hidden", "true");
        splitModal.classList.remove("open");
      }
      splitRow = null;
    }

    function openSplitModal(row) {
      splitRow = row;
      if (splitMeta) {
        const category = row.querySelector(".eval-category")?.value || "";
        splitMeta.textContent = `Split ${category} into multiple items.`;
      }
      if (splitCountInput) {
        splitCountInput.value = "2";
        splitCountInput.focus();
      }
      if (splitModal) {
        splitModal.setAttribute("aria-hidden", "false");
        splitModal.classList.add("open");
      }
    }

    function closeCategoryModal() {
      if (categoryModal) {
        categoryModal.setAttribute("aria-hidden", "true");
        categoryModal.classList.remove("open");
      }
      if (categoryNameInput) {
        categoryNameInput.value = "";
      }
      categorySelectRef = null;
    }

    function openCategoryModal(selectEl) {
      categorySelectRef = selectEl;
      if (categoryModal) {
        categoryModal.setAttribute("aria-hidden", "false");
        categoryModal.classList.add("open");
      }
      categoryNameInput?.focus();
    }

    if (categoryModal) {
      categoryModal.addEventListener("click", (e) => {
        if (e.target?.dataset?.close) {
          closeCategoryModal();
        }
      });
    }

    categorySave?.addEventListener("click", async () => {
      const label = String(categoryNameInput?.value || "").trim();
      if (!label) {
        setConfigStatus("Category name is required.", false);
        return;
      }
      try {
        const fd = new FormData();
        fd.append("label", label);
        const res = await fetchJson("php/add_evaluation_category.php", { method: "POST", body: fd });
        const cat = res?.data;
        if (cat?.category_key) {
          CATEGORIES.push({ key: cat.category_key, label: cat.label });
          if (categorySelectRef) {
            categorySelectRef.innerHTML = CATEGORIES.map(
              (c) => `<option value="${c.key}">${escapeHtml(c.label)}</option>`
            ).join("") + '<option value="__add__">+ Add Category…</option>';
            categorySelectRef.value = cat.category_key;
            updateLabelState(categorySelectRef.closest("tr"));
          }
          closeCategoryModal();
        }
      } catch (err) {
        setConfigStatus(err.message || "Failed to add category.", false);
      }
    });

    if (splitModal) {
      splitModal.addEventListener("click", (e) => {
        if (e.target?.dataset?.close) {
          closeSplitModal();
        }
      });
    }

    splitConfirm?.addEventListener("click", () => {
      if (!splitRow) return;
      const category = splitRow.querySelector(".eval-category")?.value || "";
      const label = splitRow.querySelector(".eval-label")?.value || "";
      const weight = splitRow.querySelector(".eval-weight")?.value || "";
      const count = Number(splitCountInput?.value || 0);
      if (!Number.isFinite(count) || count <= 1) {
        setConfigStatus("Split count must be 2 or more.", false);
        return;
      }

      const baseLabel = label || "Item";
      const formattedBase = baseLabel
        .split(/\s+/)
        .filter(Boolean)
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join("_");
      splitRow.remove();
      for (let i = 1; i <= count; i += 1) {
        appendConfigRow({
          category,
          label: `${formattedBase}_${i}`,
          weight: weight ? (Number(weight) / count).toFixed(2) : "",
        });
      }
      updateTotalMarks();
      closeSplitModal();
    });

    function handleSplit(row) {
      openSplitModal(row);
    }

    function getSelectedDoctorId() {
      return Number(doctorSelect?.value || 0);
    }

    function courseMatchesDoctor(course) {
      const doctorId = getSelectedDoctorId();
      if (!doctorId) return true;
      const primaryDoctorId = Number(course?.doctor_id || 0);
      if (primaryDoctorId === doctorId) return true;
      const assignedIds = String(course?.doctor_ids || "")
        .split(",")
        .map((id) => Number(String(id).trim()))
        .filter((id) => Number.isFinite(id) && id > 0);
      return assignedIds.includes(doctorId);
    }

    function renderCourses() {
      const filtered = (applyPageFiltersToCourses?.(coursesCache) || coursesCache).filter(courseMatchesDoctor);
      courseSelect.innerHTML = '<option value="">Select course...</option>';
      filtered.forEach((c) => {
        const label = `${c.course_name} (Y${c.year_level} / S${c.semester})`;
        const opt = document.createElement("option");
        opt.value = String(c.course_id);
        opt.textContent = label;
        courseSelect.appendChild(opt);
      });
    }

    function renderDoctors() {
      if (!doctorSelect) return;
      const current = Number(doctorSelect.value || 0);
      doctorSelect.innerHTML = '<option value="">All</option>';
      doctorsCache.forEach((doctor) => {
        const opt = document.createElement("option");
        opt.value = String(doctor.doctor_id);
        opt.textContent = doctor.full_name || doctor.doctor_name || `Doctor #${doctor.doctor_id}`;
        if (Number(opt.value) === current) {
          opt.selected = true;
        }
        doctorSelect.appendChild(opt);
      });
    }

    async function loadCourses() {
      setStatus("Loading courses...");
      try {
        const [coursesPayload, categoriesPayload] = await Promise.all([
          fetchJson("php/get_evaluation_courses.php"),
          fetchJson("php/get_evaluation_categories.php"),
        ]);
        coursesCache = coursesPayload?.data || [];
        CATEGORIES = (categoriesPayload?.data || []).map((c) => ({
          key: c.category_key,
          label: c.label,
          autoLabel: ["attendance"].includes(c.category_key),
          noSplit: ["attendance", "participation"].includes(c.category_key),
        }));
        renderCourses();
        setStatus("");
      } catch (err) {
        setStatus(err.message || "Failed to load courses.", "error");
      }
    }

    async function loadDoctors() {
      if (!doctorSelect) return;
      try {
        const payload = await fetchJson("php/get_doctors.php");
        doctorsCache = payload?.data || [];
        renderDoctors();
      } catch (err) {
        setStatus(err.message || "Failed to load doctors.", "error");
      }
    }

    async function loadConfig(courseId) {
      if (!canConfigure) return;
      setConfigStatus("Loading...", true);
      try {
        const payload = await fetchJson(`php/get_evaluation_config.php?course_id=${courseId}`);
        configItems = payload?.data?.items || [];
        if (payload?.data?.categories?.length) {
          CATEGORIES = payload.data.categories.map((c) => ({
            key: c.category_key,
            label: c.label,
            autoLabel: ["attendance"].includes(c.category_key),
            noSplit: ["attendance", "participation"].includes(c.category_key),
          }));
        }
        const normalized = configItems.map((i) => ({
          category: i.category_key || i.category,
          label: i.item_label || i.label,
          weight: i.weight,
        }));
        renderConfig(normalized);
        updateTotalMarks();
        setConfigStatus("");
      } catch (err) {
        setConfigStatus(err.message || "Failed to load config.", false);
      }
    }

    const evaluationLabelAbbrev = {
      assignments: "assign",
      attendance: "attend",
      exams: "exam",
      participation: "participate",
      presentations: "present",
      projects: "project",
      quizzes: "quiz",
    };

    function abbreviateEvaluationLabel(label) {
      const key = String(label || "").trim().toLowerCase();
      return evaluationLabelAbbrev[key] || label;
    }

    function renderGradesTable(items) {
      const headers = [
        '<tr>',
        '<th style="width:80px;" class="col-id">ID</th>',
        '<th style="width:460px;" class="col-student">Student</th>',
        `<th style="width:90px;" class="col-number">${escapeHtml(abbreviateEvaluationLabel("attendance"))}</th>`,
      ];
      items.forEach((item) => {
        if (item.category === "attendance") return;
        const abbreviatedLabel = abbreviateEvaluationLabel(item.label);
        headers.push(`<th style="width:100px;" class="col-grade">${escapeHtml(abbreviatedLabel)}<div class="muted grade-max">/${Number(item.weight || 0).toFixed(2)}</div></th>`);
      });
      headers.push('<th style="width:100px;" class="col-number col-final">Final</th>');
      headers.push('</tr>');
      gradesHead.innerHTML = headers.join("");

      gradesBody.innerHTML = "";
      studentsCache.forEach((item) => {
        const row = document.createElement("tr");
        row.dataset.studentId = String(item.student_id);

        const cells = [
          `<td class="col-id">${escapeHtml(item.student_code || item.student_id)}</td>`,
          `<td class="col-student student-name">${escapeHtml(item.full_name)}</td>`,
          `<td class="col-number">${Number(item.attendance?.score ?? 0).toFixed(2)}</td>`,
        ];

        items.forEach((cfgItem) => {
          if (cfgItem.category === "attendance") return;
          const scoreVal = item.scores?.[cfgItem.item_id] ?? "";
          cells.push(`
            <td class="col-number">
              <input type="number" min="0" max="${cfgItem.weight ?? 0}" step="0.01" data-score-item="${cfgItem.item_id}" value="${scoreVal}" style="max-width:80px;" />
            </td>
          `);
        });

        const finalScore = item.computed_final_score ?? item.stored_final_score ?? "";
        cells.push(`<td class="col-number" data-final-score>${finalScore !== "" ? Number(finalScore).toFixed(2) : ""}</td>`);

        row.innerHTML = cells.join("");
        gradesBody.appendChild(row);
      });
    }

    async function loadGrades(courseId) {
      setGradesStatus("Loading...", true);
      try {
        const payload = await fetchJson(`php/get_evaluation_grades.php?course_id=${courseId}`);
        configItems = payload?.data?.items || [];
        studentsCache = payload?.data?.students || [];
        renderGradesTable(configItems);
        setGradesStatus("");
      } catch (err) {
        setGradesStatus(err.message || "Failed to load grades.", false);
      }
    }

    function filterStudents() {
      const q = String(studentSearch?.value || "").trim().toLowerCase();
      const rows = gradesBody.querySelectorAll("tr");
      rows.forEach((row) => {
        if (!q) {
          row.style.display = "";
          return;
        }
        const name = row.children[1]?.textContent?.toLowerCase() || "";
        row.style.display = name.includes(q) ? "" : "none";
      });
    }

    gradesSave?.addEventListener("click", async () => {
      if (!currentCourseId) return;
      setGradesStatus("Saving...", true);
      try {
        const rows = Array.from(gradesBody.querySelectorAll("tr"));
        for (const row of rows) {
          const studentId = Number(row.dataset.studentId || 0);
          if (!studentId) continue;

          const scoresPayload = {};
          configItems.forEach((cfgItem) => {
            if (cfgItem.category === "attendance") return;
            const input = row.querySelector(`input[data-score-item="${cfgItem.item_id}"]`);
            if (!input) return;
            if (input.value === "") return;
            const num = Number(input.value);
            const max = Number(cfgItem.weight || 0);
            if (!Number.isFinite(num) || num < 0 || num > max) {
              setGradesStatus(`Grade for ${cfgItem.label} must be 0-${max}.`, false);
              throw new Error("Invalid grade");
            }
            scoresPayload[cfgItem.item_id] = num;
          });

          const fd = new FormData();
          fd.append("course_id", String(currentCourseId));
          fd.append("student_id", String(studentId));
          fd.append("scores_json", JSON.stringify(scoresPayload));

          const res = await fetchJson("php/set_evaluation_grade.php", { method: "POST", body: fd });
          const finalCell = row.querySelector("[data-final-score]");
          if (finalCell) {
            const val = res?.data?.final_score;
            finalCell.textContent = val !== undefined && val !== null ? Number(val).toFixed(2) : "";
          }
        }
        setGradesStatus("Grades saved successfully.", true);
        showAlert("Grades saved successfully.", true);
      } catch (err) {
        if (err.message !== "Invalid grade") {
          setGradesStatus(err.message || "Failed to save grades.", false);
        }
      }
    });

    configSave?.addEventListener("click", async () => {
      if (!currentCourseId) return;
      setConfigStatus("Saving...", true);
      try {
        const items = collectConfigItems();
        const filtered = items.filter((i) => String(i.label).trim() !== "" && Number(i.weight) > 0 && i.category !== "__add__");
        const sum = filtered.reduce((acc, i) => acc + Number(i.weight || 0), 0);
        if (!filtered.length) {
          setConfigStatus("Please add at least one item.", false);
          return;
        }
        const uniqueError = validateUniqueCategories(filtered);
        if (uniqueError) {
          setConfigStatus(uniqueError, false);
          return;
        }
        if (Math.abs(sum - 100) > 0.01) {
          setConfigStatus("Total marks must equal 100.", false);
          return;
        }
        showAlert("", true);
        const fd = new FormData();
        fd.append("course_id", String(currentCourseId));
        fd.append("items_json", JSON.stringify(filtered));
        await fetchJson("php/set_evaluation_config.php", { method: "POST", body: fd });
        setConfigStatus("Configuration saved successfully.", true);
        showAlert("Configuration saved successfully.", true);
        await loadGrades(currentCourseId);
      } catch (err) {
        setConfigStatus(err.message || "Failed to save config.", false);
      }
    });

    addItemBtn?.addEventListener("click", () => {
      if (!currentCourseId) {
        setConfigStatus("Please select a course before adding items.", false);
        return;
      }
      appendConfigRow({ category: "projects", label: "", weight: "" });
    });

    courseSelect.addEventListener("change", async () => {
      const val = Number(courseSelect.value || 0);
      currentCourseId = val;
      if (!val) return;
      if (canConfigure) {
        await loadConfig(val);
      }
      await loadGrades(val);
    });

    refreshBtn?.addEventListener("click", async () => {
      if (!currentCourseId) return;
      if (canConfigure) {
        await loadConfig(currentCourseId);
      }
      await loadGrades(currentCourseId);
    });

    const exportSummaryBtn = document.getElementById("exportEvaluationSummary");
    exportSummaryBtn?.addEventListener("click", () => {
      if (!currentCourseId) {
        setGradesStatus("Please select a course first.", false);
        return;
      }
      const url = `php/export_evaluation_summary_xls.php?course_id=${currentCourseId}`;
      window.location.href = url;
    });

    const exportGradesBtn = document.getElementById("exportEvaluationGrades");
    exportGradesBtn?.addEventListener("click", () => {
      if (!currentCourseId) {
        setGradesStatus("Please select a course first.", false);
        return;
      }
      const url = `php/export_evaluation_grades_xls.php?course_id=${currentCourseId}`;
      window.location.href = url;
    });

    const exportSummaryAllBtn = document.getElementById("exportEvaluationSummaryAll");
    exportSummaryAllBtn?.addEventListener("click", () => {
      const fallbackFilters = {
        year_level: Number(document.getElementById("evaluationYearFilter")?.value || 0),
        semester: Number(document.getElementById("evaluationSemesterFilter")?.value || 0),
      };
      const filters = getEffectivePageFilters?.() || getGlobalFilters?.() || fallbackFilters;
      const qs = new URLSearchParams();
      if (filters.year_level) qs.set("year_level", String(filters.year_level));
      if (filters.semester) qs.set("semester", String(filters.semester));
      const suffix = qs.toString();
      window.location.href = "php/export_evaluation_summary_all_xls.php" + (suffix ? `?${suffix}` : "");
    });

    studentSearch?.addEventListener("input", filterStudents);

    initPageFiltersUI?.({ yearSelectId: "evaluationYearFilter", semesterSelectId: "evaluationSemesterFilter" });
    window.addEventListener("dmportal:pageFiltersChanged", () => {
      const current = Number(courseSelect.value || 0);
      renderCourses();
      const currentCourse = coursesCache.find((c) => Number(c.course_id) === current);
      if (current && (!doesItemMatchPageFilters?.(currentCourse) || !courseMatchesDoctor(currentCourse))) {
        courseSelect.value = "";
        currentCourseId = 0;
      }
    });

    doctorSelect?.addEventListener("change", () => {
      const current = Number(courseSelect.value || 0);
      renderCourses();
      const currentCourse = coursesCache.find((c) => Number(c.course_id) === current);
      if (current && (!courseMatchesDoctor(currentCourse) || !doesItemMatchPageFilters?.(currentCourse))) {
        courseSelect.value = "";
        currentCourseId = 0;
      }
    });

    loadDoctors();
    loadCourses();
    switchTab(canConfigure ? "config" : "grading");
  }

  window.dmportal = window.dmportal || {};
  window.dmportal.initEvaluationPage = initEvaluationPage;
})();
