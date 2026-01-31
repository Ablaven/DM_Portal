(function () {
  "use strict";

  const {
    fetchJson,
    setStatusById,
    escapeHtml,
    initPageFiltersUI,
    getPageFilters,
  } = window.dmportal || {};

  function initStudentDashboardPage() {
    const body = document.getElementById("studentGradesBody");
    const statusId = "studentGradesStatus";
    const refreshBtn = document.getElementById("studentGradesRefresh");

    const titleEl = document.getElementById("studentGradesTitle");
    const subtitleEl = document.getElementById("studentGradesSubtitle");
    const adminSelectWrap = document.getElementById("studentGradesAdminSelect");
    const studentSelect = document.getElementById("studentGradesStudentSelect");
    const courseSelectWrap = document.getElementById("studentGradesCourseSelectWrap");
    const courseSelect = document.getElementById("studentGradesCourseSelect");
    const summaryWrap = document.getElementById("studentDashboardSummary");
    const avgFinalEl = document.getElementById("studentDashboardAvgFinal");
    const avgAttendanceEl = document.getElementById("studentDashboardAvgAttendance");
    const coursesEl = document.getElementById("studentDashboardCourses");
    const visualsCard = document.getElementById("studentDashboardVisuals");
    const tableCard = document.getElementById("studentDashboardTable");
    const tableHeaderRow = document.querySelector("#studentDashboardTable thead tr");
    const tableWrap = document.querySelector("#studentDashboardTable table");
    const cardsWrap = document.getElementById("studentGradesCards");
    const insightAverage = document.getElementById("studentInsightAverage");
    const insightAverageNote = document.getElementById("studentInsightAverageNote");
    const insightAverageBadge = document.getElementById("studentInsightAverageBadge");
    const insightTopCourse = document.getElementById("studentInsightTopCourse");
    const insightTopScore = document.getElementById("studentInsightTopScore");
    const insightTopBadge = document.getElementById("studentInsightTopBadge");
    const insightLowCourse = document.getElementById("studentInsightLowCourse");
    const insightLowScore = document.getElementById("studentInsightLowScore");
    const insightLowBadge = document.getElementById("studentInsightLowBadge");
    const insightAttendanceRisk = document.getElementById("studentInsightAttendanceRisk");
    const insightAttendanceNote = document.getElementById("studentInsightAttendanceNote");
    const insightAttendanceBadge = document.getElementById("studentInsightAttendanceBadge");
    const trendList = document.getElementById("studentTrendList");

    if (!body) return;

    let isAdminView = false;
    let studentsCache = [];
    let coursesCache = [];
    async function loadAdminStudents() {
      if (!studentSelect) return;
      try {
        const payload = await fetchJson("php/get_students.php");
        studentsCache = payload?.data || [];
        studentSelect.innerHTML = '<option value="">All students</option>';
        studentsCache.forEach((s) => {
          const opt = document.createElement("option");
          opt.value = String(s.student_id);
          opt.textContent = `${s.full_name} (${s.student_code || s.student_id})`;
          studentSelect.appendChild(opt);
        });
      } catch (err) {
        setStatusById?.(statusId, err.message || "Failed to load students.", "error");
      }
    }

    function renderAdminCourses() {
      if (!courseSelect) return;
      const year = document.getElementById("studentGradesYear")?.value || "";
      const sem = document.getElementById("studentGradesSemester")?.value || "";
      const filtered = coursesCache.filter((c) => {
        if (year && Number(c.year_level) !== Number(year)) return false;
        if (sem && Number(c.semester) !== Number(sem)) return false;
        return true;
      });

      const current = courseSelect.value;
      courseSelect.innerHTML = '<option value="">All courses</option>';
      filtered.forEach((c) => {
        const opt = document.createElement("option");
        opt.value = String(c.course_id);
        opt.textContent = `${c.course_name} (Y${c.year_level} / S${c.semester})`;
        courseSelect.appendChild(opt);
      });

      if (current && !filtered.find((c) => String(c.course_id) === String(current))) {
        courseSelect.value = "";
      } else if (current) {
        courseSelect.value = current;
      }
    }

    async function loadAdminCourses() {
      if (!courseSelect) return;
      try {
        const payload = await fetchJson("php/get_evaluation_courses.php");
        coursesCache = payload?.data || [];
        renderAdminCourses();
      } catch (err) {
        setStatusById?.(statusId, err.message || "Failed to load courses.", "error");
      }
    }

    function updateStudentSummary(items) {
      if (!summaryWrap || !avgFinalEl || !avgAttendanceEl || !coursesEl) return {};

      const scores = items
        .map((item) => (item.final_score !== null ? Number(item.final_score) : null))
        .filter((value) => typeof value === "number" && !Number.isNaN(value));
      const attendanceScores = items
        .map((item) => (item.attendance_score !== null ? Number(item.attendance_score) : null))
        .filter((value) => typeof value === "number" && !Number.isNaN(value));

      const avgFinal = scores.length ? scores.reduce((sum, v) => sum + v, 0) / scores.length : null;
      const avgAttendance = attendanceScores.length
        ? attendanceScores.reduce((sum, v) => sum + v, 0) / attendanceScores.length
        : null;

      if (isAdminView) {
        avgFinalEl.textContent = avgFinal !== null ? avgFinal.toFixed(2) : "--";
        avgAttendanceEl.textContent = avgAttendance !== null ? avgAttendance.toFixed(2) : "--";
        coursesEl.textContent = String(items.length || 0);
        summaryWrap.style.display = "grid";
      } else {
        summaryWrap.style.display = "none";
      }

      return { avgFinal, avgAttendance };
    }

    function setBadge(el, text, tone) {
      if (!el) return;
      el.textContent = text;
      el.classList.remove("badge-positive", "badge-warning", "badge-negative", "badge-neutral");
      if (tone) el.classList.add(tone);
    }

    function renderInsights(items, averages) {
      if (!visualsCard || isAdminView) return;

      const safeItems = items.filter((item) => item.course_name);
      const withFinals = safeItems
        .map((item) => ({
          ...item,
          finalScore: item.final_score !== null ? Number(item.final_score) : null,
          attendanceScore: item.attendance_score !== null ? Number(item.attendance_score) : null,
        }))
        .filter((item) => typeof item.finalScore === "number" && !Number.isNaN(item.finalScore));

      let avgFinal = typeof averages?.avgFinal === "number" && !Number.isNaN(averages.avgFinal) ? averages.avgFinal : null;
      if (avgFinal === null && withFinals.length) {
        avgFinal = withFinals.reduce((sum, item) => sum + (item.finalScore ?? 0), 0) / withFinals.length;
      }

      if (insightAverage) {
        insightAverage.textContent = avgFinal !== null ? avgFinal.toFixed(2) : "--";
      }
      if (insightAverageNote) {
        insightAverageNote.textContent = avgFinal !== null ? "Target 12+ to stay on track." : "No scores yet.";
      }
      if (insightAverageBadge) {
        if (avgFinal === null) {
          setBadge(insightAverageBadge, "No Data", "badge-neutral");
        } else if (avgFinal >= 14) {
          setBadge(insightAverageBadge, "On Track", "badge-positive");
        } else if (avgFinal >= 12) {
          setBadge(insightAverageBadge, "Stable", "badge-warning");
        } else {
          setBadge(insightAverageBadge, "Needs Work", "badge-negative");
        }
      }

      const sorted = [...withFinals].sort((a, b) => (b.finalScore ?? 0) - (a.finalScore ?? 0));
      const top = sorted.length ? sorted[0] : null;
      const low = sorted.length > 1 ? sorted[sorted.length - 1] : null;

      if (insightTopCourse) {
        insightTopCourse.textContent = top ? top.course_name : "--";
      }
      if (insightTopScore) {
        insightTopScore.textContent = top ? `Final ${top.finalScore.toFixed(2)} / 20` : "No data yet.";
      }
      if (insightTopBadge) {
        setBadge(insightTopBadge, top ? "Best" : "No Data", top ? "badge-positive" : "badge-neutral");
      }

      if (insightLowCourse) {
        insightLowCourse.textContent = low ? low.course_name : "--";
      }
      if (insightLowScore) {
        insightLowScore.textContent = low ? `Final ${low.finalScore.toFixed(2)} / 20` : "Add another course to compare.";
      }
      if (insightLowBadge) {
        setBadge(insightLowBadge, low ? "Focus" : "No Data", low ? "badge-negative" : "badge-neutral");
      }

      const attendanceScores = safeItems
        .map((item) => (item.attendance_score !== null ? Number(item.attendance_score) : null))
        .filter((value) => typeof value === "number" && !Number.isNaN(value));

      const attendanceRisk = safeItems.filter((item) => {
        const score = item.attendance_score !== null ? Number(item.attendance_score) : null;
        return typeof score === "number" && !Number.isNaN(score) && score < 12;
      });

      if (insightAttendanceRisk) {
        insightAttendanceRisk.textContent = attendanceScores.length ? String(attendanceRisk.length) : "--";
      }
      if (insightAttendanceNote) {
        if (!attendanceScores.length) {
          insightAttendanceNote.textContent = "Attendance data not available yet.";
        } else if (!attendanceRisk.length) {
          insightAttendanceNote.textContent = "Great! No risky attendance so far.";
        } else {
          const names = attendanceRisk.map((item) => item.course_name).slice(0, 3).join(", ");
          insightAttendanceNote.textContent = `Improve attendance in: ${names}${attendanceRisk.length > 3 ? "..." : ""}`;
        }
      }
      if (insightAttendanceBadge) {
        if (!attendanceScores.length) {
          setBadge(insightAttendanceBadge, "No Data", "badge-neutral");
        } else if (!attendanceRisk.length) {
          setBadge(insightAttendanceBadge, "Safe", "badge-positive");
        } else if (attendanceRisk.length <= 2) {
          setBadge(insightAttendanceBadge, "Watch", "badge-warning");
        } else {
          setBadge(insightAttendanceBadge, "Risk", "badge-negative");
        }
      }

      if (trendList) {
        trendList.innerHTML = "";
        if (withFinals.length < 2 || avgFinal === null) {
          trendList.innerHTML = '<div class="muted">Add at least two courses to see trends.</div>';
        } else {
          const trendItems = [...withFinals].map((item) => {
            const delta = item.finalScore - avgFinal;
            const status = delta >= 1 ? "up" : delta <= -1 ? "down" : "steady";
            return { ...item, delta, status };
          });

          trendItems
            .sort((a, b) => Math.abs(b.delta) - Math.abs(a.delta))
            .forEach((item) => {
              const row = document.createElement("div");
              row.className = `student-trend-item ${item.status}`;
              const arrow = item.status === "up" ? "▲" : item.status === "down" ? "▼" : "■";
              const deltaText = `${arrow} ${item.delta >= 0 ? "+" : ""}${item.delta.toFixed(2)}`;
              row.innerHTML = `
                <div class="student-trend-course">${escapeHtml(item.course_name)}</div>
                <div class="student-trend-score">Final ${item.finalScore.toFixed(2)} / 20</div>
                <div class="student-trend-delta">${deltaText}</div>
              `;
              trendList.appendChild(row);
            });
        }
      }
    }

    function applyTableColumns(showStudent, showAttendance) {
      if (!tableHeaderRow) return;
      const headers = tableHeaderRow.querySelectorAll("[data-col]");
      headers.forEach((th) => {
        const col = th.getAttribute("data-col");
        const hideStudent = col === "student" && !showStudent;
        const hideAttendance = col === "attendance" && !showAttendance;
        th.hidden = hideStudent || hideAttendance;
      });
    }

    function getGreetingLabel() {
      let hour = new Date().getHours();
      try {
        const formatted = new Intl.DateTimeFormat("en-US", {
          timeZone: "Africa/Cairo",
          hour: "numeric",
          hour12: false,
        }).format(new Date());
        hour = Number(formatted);
      } catch (err) {
        // Fallback to local time if timezone formatting fails.
      }
      if (hour >= 5 && hour < 12) return "Good morning";
      if (hour >= 12 && hour < 17) return "Good afternoon";
      if (hour >= 17 && hour < 21) return "Good evening";
      return "Good night";
    }

    function renderStudentCards(items) {
      if (!cardsWrap) return;
      cardsWrap.innerHTML = "";

      if (!items.length) {
        cardsWrap.style.display = "block";
        cardsWrap.innerHTML = '<div class="dashboard-card"><div class="dashboard-card-title">No grades available yet.</div><div class="dashboard-card-subtitle">Try adjusting the filters.</div></div>';
        return;
      }

      const groups = new Map();
      items.forEach((item) => {
        const key = `${item.year_level}-${item.semester}`;
        if (!groups.has(key)) {
          groups.set(key, { year: item.year_level, semester: item.semester, items: [] });
        }
        groups.get(key).items.push(item);
      });

      Array.from(groups.values())
        .sort((a, b) => Number(a.year) - Number(b.year) || Number(a.semester) - Number(b.semester))
        .forEach((group) => {
          const card = document.createElement("div");
          card.className = "dashboard-card";
          const title = `Year ${group.year} · Sem ${group.semester}`;
          const listItems = group.items
            .map((item) => {
              const score = item.final_score !== null ? Number(item.final_score).toFixed(2) : "";
              return `
                <div class="student-grade-item">
                  <div class="student-grade-course">${escapeHtml(item.course_name)}</div>
                  <div class="student-grade-score">${score}</div>
                </div>
              `;
            })
            .join("");

          card.innerHTML = `
            <div class="dashboard-card-title">${title}</div>
            <div class="dashboard-card-subtitle">${group.items.length} course${group.items.length === 1 ? "" : "s"}</div>
            <div class="student-grade-list">
              ${listItems}
            </div>
          `;
          cardsWrap.appendChild(card);
        });

      cardsWrap.style.display = "grid";
    }

    async function loadGrades() {
      setStatusById?.(statusId, "Loading...");
      try {
        const year = document.getElementById("studentGradesYear")?.value || "";
        const sem = document.getElementById("studentGradesSemester")?.value || "";

        const qs = new URLSearchParams();
        if (year) qs.set("year_level", year);
        if (sem) qs.set("semester", sem);

        if (isAdminView) {
          const sid = studentSelect?.value || "";
          if (sid) {
            qs.set("scope", "student");
            qs.set("student_id", sid);
          } else {
            qs.set("scope", "all");
          }
          const cid = courseSelect?.value || "";
          if (cid) {
            qs.set("course_id", cid);
          }
        }

        const payload = await fetchJson(`php/get_student_evaluation.php?${qs.toString()}`);
        const items = payload?.data?.items || [];
        const scope = payload?.data?.scope || "self";

        body.innerHTML = "";
        const showStudent = isAdminView;
        const showAttendance = isAdminView;
        applyTableColumns(showStudent, showAttendance);
        const colCount = (showStudent ? 1 : 0) + 3 + (showAttendance ? 1 : 0) + 1;

        if (!isAdminView) {
          if (tableWrap) tableWrap.style.display = "none";
          renderStudentCards(items);
        } else {
          if (cardsWrap) cardsWrap.style.display = "none";
          if (tableWrap) tableWrap.style.display = "table";
        }

        if (!items.length) {
          if (isAdminView) {
            body.innerHTML = `<tr><td colspan="${colCount}" class="muted">No grades available yet.</td></tr>`;
          } else {
            renderStudentCards([]);
          }
          const averages = updateStudentSummary([]);
          renderInsights([], averages);
          setStatusById?.(statusId, "");
          return;
        }

        if (isAdminView) {
          items.forEach((item) => {
            const row = document.createElement("tr");
            const cells = [];
            if (showStudent) {
              cells.push(`<td>${escapeHtml(item.student_name || "")}</td>`);
            }
            cells.push(`<td>${escapeHtml(item.course_name)}</td>`);
            cells.push(`<td>${escapeHtml(item.year_level)}</td>`);
            cells.push(`<td>${escapeHtml(item.semester)}</td>`);
            if (showAttendance) {
              cells.push(`<td>${item.attendance_score !== null ? Number(item.attendance_score).toFixed(2) : ""}</td>`);
            }
            cells.push(`<td>${item.final_score !== null ? Number(item.final_score).toFixed(2) : ""}</td>`);
            row.innerHTML = cells.join("");
            body.appendChild(row);
          });
        }

        if (titleEl && subtitleEl) {
          if (isAdminView) {
            titleEl.textContent = "Student Dashboard";
            subtitleEl.textContent = scope === "all" ? "All student evaluation results." : "Selected student evaluation results.";
          } else {
            titleEl.textContent = titleEl.dataset.greeting || "My Dashboard";
            subtitleEl.textContent = "Read-only view of your evaluation results.";
          }
        }

        const averages = updateStudentSummary(items);
        renderInsights(items, averages);
        setStatusById?.(statusId, "");
      } catch (err) {
        setStatusById?.(statusId, err.message || "Failed to load grades.", "error");
      }
    }

    initPageFiltersUI?.({ yearSelectId: "studentGradesYear", semesterSelectId: "studentGradesSemester" });

    refreshBtn?.addEventListener("click", loadGrades);
    studentSelect?.addEventListener("change", loadGrades);
    courseSelect?.addEventListener("change", loadGrades);
    document.getElementById("studentGradesYear")?.addEventListener("change", () => {
      renderAdminCourses();
      loadGrades();
    });
    document.getElementById("studentGradesSemester")?.addEventListener("change", () => {
      renderAdminCourses();
      loadGrades();
    });

    const initialFilters = getPageFilters?.() || {};
    if (initialFilters.year_level && document.getElementById("studentGradesYear")) {
      document.getElementById("studentGradesYear").value = String(initialFilters.year_level || "");
    }
    if (initialFilters.semester && document.getElementById("studentGradesSemester")) {
      document.getElementById("studentGradesSemester").value = String(initialFilters.semester || "");
    }

    fetchJson("php/auth_me.php")
      .then((payload) => {
        const role = payload?.data?.role || "";
        const studentId = Number(payload?.data?.student_id || 0);
        isAdminView = role === "admin" || role === "management";

        if (isAdminView) {
          adminSelectWrap.style.display = "block";
          courseSelectWrap.style.display = "block";
          loadAdminStudents();
          loadAdminCourses();
          if (visualsCard) visualsCard.style.display = "none";
          if (summaryWrap) summaryWrap.style.display = "grid";
        } else {
          if (visualsCard) visualsCard.style.display = "block";
          if (tableCard) tableCard.style.display = "block";
          if (summaryWrap) summaryWrap.style.display = "none";
        }

        if (!isAdminView && studentId <= 0) {
          setStatusById?.(statusId, "Student account missing student_id.", "error");
          return;
        }

        if (!isAdminView && titleEl) {
          fetchJson("php/get_student_profile.php")
            .then((profile) => {
              const fullName = profile?.data?.full_name || "";
              const greeting = getGreetingLabel();
              const nameText = fullName ? `, ${fullName}` : "";
              titleEl.dataset.greeting = `${greeting}${nameText}`;
              titleEl.textContent = titleEl.dataset.greeting;
            })
            .catch(() => {
              const greeting = getGreetingLabel();
              titleEl.dataset.greeting = greeting;
              titleEl.textContent = greeting;
            });
        }

        loadGrades();
      })
      .catch(() => {
        loadGrades();
      });
  }

  window.dmportal = window.dmportal || {};
  window.dmportal.initStudentDashboardPage = initStudentDashboardPage;
})();
