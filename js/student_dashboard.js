(function () {
  "use strict";

  const {
    fetchJson,
    escapeHtml,
    showError,
    showSuccess,
    showInfo,
    renderEmptyState,
  } = window.dmportal || {};

  // State management
  const state = {
    dashboardData: null,
    isLoading: false,
    retryCount: 0,
  };

  const MAX_RETRY_ATTEMPTS = 3;

  /**
   * Fetch dashboard data from the API
   * @returns {Promise<Object>} Dashboard data payload
   */
  async function fetchDashboardData() {
    console.log("Fetching dashboard data...");
    const payload = await fetchJson("php/get_student_dashboard.php");
    console.log("API Response:", payload);
    
    if (!payload.success) {
      throw new Error(payload.error || "Failed to load dashboard data");
    }
    
    return payload.data || null;
  }

  /**
   * Render loading state in the dashboard container
   */
  function renderLoadingState() {
    // Don't destroy the card structure - just show loading in each card
    const gradesContent = document.getElementById("gradesContent");
    const attendanceContent = document.getElementById("attendanceContent");
    const performanceContent = document.getElementById("performanceContent");
    
    const loadingHTML = '<div class="loading-spinner" role="status">Loading...</div>';
    
    if (gradesContent) gradesContent.innerHTML = loadingHTML;
    if (attendanceContent) attendanceContent.innerHTML = loadingHTML;
    if (performanceContent) performanceContent.innerHTML = loadingHTML;
  }

  /**
   * Render error state with retry functionality
   * @param {string} errorMessage - Error message to display
   * @param {boolean} allowRetry - Whether to show retry button
   */
  function renderErrorState(errorMessage, allowRetry = true) {
    const container = document.getElementById("dashboardContainer");
    if (!container) return;

    const errorDiv = document.createElement("div");
    errorDiv.className = "dashboard-error";
    errorDiv.setAttribute("role", "alert");
    errorDiv.setAttribute("aria-live", "assertive");
    errorDiv.setAttribute("aria-atomic", "true");

    const errorIcon = document.createElement("div");
    errorIcon.className = "error-icon";
    errorIcon.setAttribute("aria-hidden", "true");
    errorIcon.textContent = "âš ï¸";

    const errorTitle = document.createElement("h2");
    errorTitle.className = "error-title";
    errorTitle.textContent = "Unable to Load Dashboard";

    const errorText = document.createElement("p");
    errorText.className = "error-message";
    errorText.textContent = errorMessage || "An unexpected error occurred.";

    errorDiv.appendChild(errorIcon);
    errorDiv.appendChild(errorTitle);
    errorDiv.appendChild(errorText);

    if (allowRetry && state.retryCount < MAX_RETRY_ATTEMPTS) {
      const retryButton = document.createElement("button");
      retryButton.className = "btn btn-primary";
      retryButton.setAttribute("aria-label", "Retry loading dashboard");
      retryButton.textContent = "Retry";
      retryButton.addEventListener("click", () => {
        state.retryCount++;
        loadDashboard();
      });
      errorDiv.appendChild(retryButton);
    } else if (state.retryCount >= MAX_RETRY_ATTEMPTS) {
      const helpText = document.createElement("p");
      helpText.className = "muted";
      helpText.textContent = "Please refresh the page or contact your administrator if the problem persists.";
      errorDiv.appendChild(helpText);
    }

    container.innerHTML = "";
    container.appendChild(errorDiv);
  }

  /**
   * Render "no active term" message
   */
  function renderNoTermMessage() {
    const container = document.getElementById("dashboardContainer");
    if (!container) return;

    renderEmptyState(container, {
      icon: "",
      title: "No Active Term",
      subtitle: "There is currently no active academic term. Please contact your administrator to activate a term.",
      className: "dashboard-no-term"
    });
  }

  /**
   * Render the grades card
   * @param {Array} grades - Array of grade objects
   */
  function renderGradesCard(grades) {
    const container = document.getElementById("gradesContent");
    if (!container) return;

    // Handle empty grades array
    if (!grades || grades.length === 0) {
      renderEmptyState(container, {
        icon: "",
        title: "No Courses Found",
        subtitle: "You are not enrolled in any courses for this term.",
        className: "grades-empty"
      });
      return;
    }

    const gradedCount = grades.filter(g => g.has_grade).length;
    const totalCount = grades.length;

    // Build course list with semantic HTML and accessibility attributes
    let html = `
      <div class="grades-summary">
        <p class="grades-count" role="status" aria-label="${gradedCount} of ${totalCount} courses have been graded">${gradedCount} of ${totalCount} courses graded</p>
      </div>
      <ul class="grades-list" aria-label="List of course grades">
    `;

    grades.forEach(grade => {
      const scoreDisplay = grade.has_grade 
        ? escapeHtml(grade.final_score_display)
        : 'N/A';
      
      const scoreClass = grade.has_grade ? 'grade-score' : 'grade-score grade-na';
      const ariaLabel = grade.has_grade 
        ? `${grade.course_name}: Grade ${grade.final_score_display} out of 100`
        : `${grade.course_name}: Not graded yet`;

      const courseCode = grade.subject_code 
        ? `<span class="course-code"><span class="sr-only">Course code: </span>${escapeHtml(grade.subject_code)}</span>` 
        : '';

      html += `
        <li class="grade-item">
          <div class="grade-info">
            <div class="course-name">${escapeHtml(grade.course_name)}</div>
            ${courseCode}
          </div>
          <span class="${scoreClass}" aria-label="${escapeHtml(ariaLabel)}">${scoreDisplay}</span>
        </li>
      `;
    });

    html += `</ul>`;
    container.innerHTML = html;
  }

  /**
   * Render the attendance card
   * @param {Object} attendance - Attendance statistics object
   */
  function renderAttendanceCard(attendance) {
    const container = document.getElementById("attendanceContent");
    if (!container) return;

    if (!attendance || attendance.total_scheduled === 0) {
      renderEmptyState(container, {
        icon: "",
        title: "No Attendance Data",
        subtitle: "No attendance records are available for this term.",
        className: "attendance-empty"
      });
      return;
    }

    const rate = attendance.attendance_rate || 0;
    const rateClass = rate >= 80 ? 'rate-good' : rate >= 60 ? 'rate-warning' : 'rate-danger';
    const rateStatus = rate >= 80 ? 'good' : rate >= 60 ? 'moderate' : 'needs improvement';

    const html = `
      <div class="attendance-stats">
        <div class="attendance-rate ${rateClass}" role="status" aria-label="Your attendance rate is ${rate.toFixed(1)} percent, which is ${rateStatus}">
          <div class="rate-number" aria-hidden="true">${rate.toFixed(1)}%</div>
          <div class="rate-label" aria-hidden="true">Attendance Rate</div>
        </div>
        <div class="attendance-breakdown" role="list" aria-label="Attendance breakdown">
          <div class="attendance-item" role="listitem">
            <span class="attendance-label">Present</span>
            <span class="attendance-value" aria-label="${attendance.present_count} classes attended out of ${attendance.total_scheduled} total">${attendance.present_count} / ${attendance.total_scheduled}</span>
          </div>
          <div class="attendance-item" role="listitem">
            <span class="attendance-label">Absent</span>
            <span class="attendance-value" aria-label="${attendance.absent_count} classes missed">${attendance.absent_count}</span>
          </div>
          <div class="attendance-item" role="listitem">
            <span class="attendance-label">Late</span>
            <span class="attendance-value" aria-label="${attendance.late_count} times late">${attendance.late_count}</span>
          </div>
        </div>
      </div>
    `;

    container.innerHTML = html;
  }

  /**
   * Render the performance metrics card
   * @param {Object} performance - Performance metrics object
   */
  function renderPerformanceCard(performance) {
    const container = document.getElementById("performanceContent");
    if (!container) return;

    if (!performance || performance.total_graded_courses === 0) {
      renderEmptyState(container, {
        icon: "",
        title: "No Performance Data",
        subtitle: "Performance metrics will appear once grades are available.",
        className: "performance-empty"
      });
      return;
    }

    const avgGrade = performance.average_grade !== null 
      ? performance.average_grade.toFixed(1) 
      : 'N/A';
    
    const highestGrade = performance.highest_grade !== null 
      ? performance.highest_grade.toFixed(1) 
      : 'N/A';
    
    const lowestGrade = performance.lowest_grade !== null 
      ? performance.lowest_grade.toFixed(1) 
      : 'N/A';

    const showHighLow = performance.total_graded_courses >= 2;

    const html = `
      <div class="performance-grid" role="list" aria-label="Performance metrics">
        <div class="performance-metric" role="listitem">
          <div class="metric-value" aria-hidden="true">${avgGrade}</div>
          <div class="metric-label">Average Grade</div>
          <span class="sr-only">Your average grade is ${avgGrade === 'N/A' ? 'not available' : avgGrade + ' out of 100'}</span>
        </div>
        ${showHighLow ? `
          <div class="performance-metric" role="listitem">
            <div class="metric-value" aria-hidden="true">${highestGrade}</div>
            <div class="metric-label">Highest Grade</div>
            <span class="sr-only">Your highest grade is ${highestGrade} out of 100</span>
          </div>
          <div class="performance-metric" role="listitem">
            <div class="metric-value" aria-hidden="true">${lowestGrade}</div>
            <div class="metric-label">Lowest Grade</div>
            <span class="sr-only">Your lowest grade is ${lowestGrade} out of 100</span>
          </div>
        ` : ''}
        <div class="performance-metric" role="listitem">
          <div class="metric-value" aria-hidden="true">${performance.high_performing_count}</div>
          <div class="metric-label">High Performing (>85)</div>
          <span class="sr-only">You have ${performance.high_performing_count} high performing ${performance.high_performing_count === 1 ? 'course' : 'courses'} with grades above 85</span>
        </div>
        <div class="performance-metric" role="listitem">
          <div class="metric-value" aria-hidden="true">${performance.low_performing_count}</div>
          <div class="metric-label">Low Performing (<60)</div>
          <span class="sr-only">You have ${performance.low_performing_count} low performing ${performance.low_performing_count === 1 ? 'course' : 'courses'} with grades below 60</span>
        </div>
        <div class="performance-metric" role="listitem">
          <div class="metric-value" aria-hidden="true">${performance.total_graded_courses} / ${performance.total_courses}</div>
          <div class="metric-label">Courses Graded</div>
          <span class="sr-only">${performance.total_graded_courses} out of ${performance.total_courses} courses have been graded</span>
        </div>
      </div>
    `;

    container.innerHTML = html;
  }

  /**
   * Render the term info header
   * @param {Object} term - Term information object
   * @param {Object} student - Student information object
   */
  function renderTermInfo(term, student) {
    const termInfo = document.getElementById("termInfo");
    if (!termInfo) return;

    if (!term) {
      termInfo.innerHTML = '<p class="muted">No active term</p>';
      return;
    }

    const studentName = student ? escapeHtml(student.full_name) : 'Student';
    const termLabel = escapeHtml(term.label || 'Current Term');
    const academicYear = term.academic_year ? escapeHtml(term.academic_year) : '';

    termInfo.innerHTML = `
      <p class="welcome-message">Welcome back, <strong>${studentName}</strong>!</p>
      <p class="term-label">Current Term: ${termLabel} ${academicYear}</p>
    `;
  }

  /**
   * Render the complete dashboard
   * @param {Object} data - Complete dashboard data
   */
  function renderDashboard(data) {
    console.log("Rendering dashboard with data:", data);
    if (!data) {
      renderErrorState("No dashboard data available.");
      return;
    }

    // Handle case where no active term exists
    if (!data.term) {
      renderNoTermMessage();
      return;
    }

    // Render term info header
    renderTermInfo(data.term, data.student);

    // Render each dashboard card
    renderGradesCard(data.grades);
    renderAttendanceCard(data.attendance);
    renderPerformanceCard(data.performance);
  }

  /**
   * Handle API errors with user-friendly messages
   * @param {Error} error - Error object
   */
  function handleError(error) {
    console.error("Dashboard error:", error);

    const errorMessage = error.message || "An unexpected error occurred.";

    // Handle authentication errors
    if (errorMessage.includes("Not authenticated") || errorMessage.includes("401")) {
      showError("Your session has expired. Redirecting to login...");
      setTimeout(() => {
        window.location.href = "login.php?next=student_dashboard.php";
      }, 2000);
      return;
    }

    // Handle authorization errors
    if (errorMessage.includes("Forbidden") || errorMessage.includes("403")) {
      showError("You do not have permission to access this page.");
      renderErrorState("Access Denied: This page is only available to students.", false);
      return;
    }

    // Handle network errors
    if (errorMessage.includes("Failed to fetch") || errorMessage.includes("Network")) {
      showError("Network error. Please check your connection.");
      renderErrorState("Unable to connect to the server. Please check your internet connection.", true);
      return;
    }

    // Handle server errors
    if (errorMessage.includes("500") || errorMessage.includes("Internal Server Error")) {
      showError("Server error. Please try again later.");
      renderErrorState("The server encountered an error. Please try again later.", true);
      return;
    }

    // Generic error handling
    showError(errorMessage);
    renderErrorState(errorMessage, true);
  }

  /**
   * Main function to load dashboard data
   */
  async function loadDashboard() {
    if (state.isLoading) return;

    state.isLoading = true;
    renderLoadingState();

    try {
      const data = await fetchDashboardData();
      state.dashboardData = data;
      state.retryCount = 0; // Reset retry count on success
      renderDashboard(data);
    } catch (error) {
      handleError(error);
    } finally {
      state.isLoading = false;
    }
  }

  /**
   * Initialize the student dashboard
   * Public API function called from student_dashboard.php
   */
  function initStudentDashboard() {
    // Check if required container exists
    const container = document.getElementById("dashboardContainer");
    if (!container) {
      console.error("Dashboard container not found");
      return;
    }

    // Load dashboard data
    loadDashboard();

    // Add refresh button listener if it exists
    const refreshButton = document.getElementById("refreshDashboard");
    if (refreshButton) {
      refreshButton.addEventListener("click", () => {
        state.retryCount = 0; // Reset retry count on manual refresh
        loadDashboard();
      });
    }
  }

  // Expose public API
  window.dmportal = window.dmportal || {};
  window.dmportal.initStudentDashboard = initStudentDashboard;
})();


