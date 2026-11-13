(function () {
  "use strict";

  const { fetchJson, setStatusById, escapeHtml, makeCourseLabel, formatWeekLabelWithRange, formatWeekDisplayLabel } = window.dmportal || {};

  const DAYS = ["Sun", "Mon", "Tue", "Wed", "Thu"];
  const SLOTS = [1, 2, 3, 4, 5];
  const SLOT_TIMES = {
    1: "8:30 AM\u201310:00 AM",
    2: "10:10 AM\u201311:30 AM",
    3: "11:40 AM\u20131:00 PM",
    4: "1:10 PM\u20132:40 PM",
    5: "2:50 PM\u20134:20 PM",
  };

  const attendanceState = { weeks: [], activeWeekId: null };
  let meCache = null;

  async function authFetchMe() {
    try {
      const payload = await fetchJson("php/auth_me.php");
      return payload?.data || null;
    } catch {
      return null;
    }
  }

  async function loadWeeks() {
    const payload = await fetchJson("php/get_weeks.php");
    if (!payload.success) throw new Error(payload.error || "Failed to load weeks");
    attendanceState.weeks = payload.data || [];
    const active = attendanceState.weeks.find((w) => w.status === "active");
    attendanceState.activeWeekId = active ? Number(active.week_id) : null;
  }

  function openAttendanceModal() {
  const modal = document.getElementById("attendanceModal");
  if (!modal) return;
  modal.classList.add("open");
  modal.setAttribute("aria-hidden", "false");
  }

  function closeAttendanceModal() {
  const modal = document.getElementById("attendanceModal");
  if (!modal) return;
  modal.classList.remove("open");
  modal.setAttribute("aria-hidden", "true");
  setStatusById("attendanceModalStatus", "");
  }

  // ============================================
  // QR ATTENDANCE MODAL
  // ============================================
  
  let qrSession = null;
  let qrTokenPollInterval = null;
  let qrStatusPollInterval = null;

  function openQRAttendanceModal(scheduleId) {
    console.log('[QR Modal] Opening for schedule:', scheduleId);
    
    // Create QR modal dynamically
    const existingModal = document.getElementById("qrAttendanceModal");
    if (existingModal) {
      console.log('[QR Modal] Removing existing modal');
      existingModal.remove();
    }

    console.log('[QR Modal] Creating new modal HTML');
    
    const modalHTML = `
      <div class="modal open" id="qrAttendanceModal" aria-hidden="false" style="z-index:10000;">
        <div class="modal-backdrop"></div>
        <div class="modal-card" id="qrModalCard" style="max-width: 720px; max-height: 90vh; overflow-y: auto; transition: all 0.3s ease;">
          <div class="modal-header" style="display:flex; align-items:center; justify-content:space-between;">
            <h3 style="margin:0; font-size:22px; font-weight:700; letter-spacing:-0.3px;">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle; margin-right:8px; margin-top:-2px;">
                <rect x="3" y="3" width="18" height="18" rx="2"/>
                <rect x="7" y="7" width="4" height="4"/>
                <rect x="7" y="13" width="4" height="4"/>
                <rect x="13" y="7" width="4" height="4"/>
                <rect x="13" y="13" width="4" height="4"/>
              </svg>
              QR Code Attendance
            </h3>
            <button type="button" id="qrFullscreenBtn" class="btn btn-sm" style="padding:8px 12px; font-size:13px; font-weight:600; display:flex; align-items:center; gap:6px;" title="Fullscreen mode">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"/>
              </svg>
              <span id="qrFullscreenText">Fullscreen</span>
            </button>
          </div>
          <div class="modal-body" style="padding:0 32px 32px 32px;">
            
            <!-- QR Code Section -->
            <div style="text-align:center; margin-bottom:32px; padding:32px; background:var(--surface-1); border:1px solid var(--card-border); border-radius:16px; backdrop-filter:blur(20px);">
              <p style="margin:0 0 20px 0; color:var(--muted); font-size:15px; font-weight:500;">Students scan this QR code to mark their attendance</p>
              
              <div id="qrCodeContainer" style="display:inline-block; padding:24px; background:white; border-radius:16px; box-shadow:0 8px 24px rgba(0,0,0,0.15);"></div>
              
              <div style="margin-top:20px; display:inline-flex; align-items:center; gap:8px; padding:8px 16px; background:rgba(var(--accent-rgb), 0.12); border:1px solid rgba(var(--accent-rgb), 0.25); border-radius:20px;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2">
                  <polyline points="23 4 23 10 17 10"/>
                  <path d="M20.49 15a9 9 0 11-2.12-9.36L23 10"/>
                </svg>
                <span style="font-size:13px; color:var(--accent); font-weight:600;">Auto-refreshes every 15 seconds</span>
              </div>
            </div>
            
            <!-- Students List Section -->
            <div style="background:var(--surface-1); border:1px solid var(--card-border); border-radius:16px; padding:24px; backdrop-filter:blur(20px);">
              <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:18px; flex-wrap:wrap; gap:12px;">
                <div style="display:flex; align-items:center; gap:10px;">
                  <h4 style="margin:0; font-size:17px; font-weight:700;">Students Present</h4>
                  <span id="qrStudentCount" style="display:inline-flex; align-items:center; justify-content:center; min-width:32px; height:32px; padding:0 10px; background:var(--accent); color:white; border-radius:16px; font-size:15px; font-weight:700; box-shadow:0 4px 12px rgba(var(--accent-rgb), 0.3); transition: transform 0.3s ease;">0</span>
                  <span id="qrProgressText" style="font-size:13px; color:var(--muted); font-weight:500;"></span>
                </div>
                <div style="display:flex; gap:8px;">
                  <button type="button" id="qrFilterAbsent" class="btn btn-sm" style="padding:8px 14px; font-size:13px; font-weight:600; display:none;">
                    Show Missing
                  </button>
                  <button type="button" id="qrRefreshList" class="btn btn-sm" style="padding:8px 16px; font-size:13px; font-weight:600; display:flex; align-items:center; gap:6px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                      <polyline points="23 4 23 10 17 10"/>
                      <path d="M20.49 15a9 9 0 11-2.12-9.36L23 10"/>
                    </svg>
                    Refresh
                  </button>
                </div>
              </div>
              
              <div id="qrStudentList" class="qr-student-list" style="overflow-y:auto; border:1px solid var(--card-border); border-radius:12px; background:var(--surface-2);">
                <div style="padding:40px 20px; text-align:center; color:var(--muted);">
                  <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="opacity:0.3; margin-bottom:12px;">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                  </svg>
                  <div style="font-size:14px; font-weight:500;">No students have scanned yet...</div>
                  <div style="font-size:12px; margin-top:4px; opacity:0.7;">Students will appear here as they scan the QR code</div>
                </div>
              </div>
            </div>
          </div>
          
          <div class="modal-actions" style="padding:20px 32px; border-top:1px solid var(--card-border); background:var(--surface-1); border-radius:0 0 var(--radius) var(--radius);">
            <button type="button" class="btn btn-secondary" id="qrCloseBtn" style="width:100%; padding:16px; font-size:15px; font-weight:600;">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle; margin-right:8px; margin-top:-2px;">
                <line x1="18" y1="6" x2="6" y2="18"/>
                <line x1="6" y1="6" x2="18" y2="18"/>
              </svg>
              Close QR & Continue Manually
            </button>
          </div>
          
          <div id="qrModalStatus" style="margin:0 32px 20px 32px; padding:12px 16px; border-radius:10px; display:none; font-size:14px; font-weight:600;"></div>
        </div>
      </div>
    `;

    console.log('[QR Modal] Inserting modal into DOM');
    document.body.insertAdjacentHTML("beforeend", modalHTML);
    
    console.log('[QR Modal] Modal inserted, checking if visible');
    const modal = document.getElementById("qrAttendanceModal");
    console.log('[QR Modal] Modal element:', modal);

    // Bind close handler
    document.getElementById("qrCloseBtn")?.addEventListener("click", () => closeQRAttendanceModal(scheduleId));
    document.getElementById("qrRefreshList")?.addEventListener("click", () => refreshQRStudentList(scheduleId));
    
    // Bind fullscreen toggle
    document.getElementById("qrFullscreenBtn")?.addEventListener("click", toggleQRFullscreen);
    
    // Bind filter button (will be shown when students enrolled)
    document.getElementById("qrFilterAbsent")?.addEventListener("click", toggleAbsentFilter);
    
    // Add CSS for slide-in animation and student list height
    const style = document.createElement("style");
    style.textContent = `
      @keyframes slideInRight {
        from {
          opacity: 0;
          transform: translateX(20px);
        }
        to {
          opacity: 1;
          transform: translateX(0);
        }
      }
      .qr-student-list {
        max-height: 320px;
      }
    `;
    document.head.appendChild(style);

    // Start QR session
    startQRSession(scheduleId);
  }
  
  // Global variables for new features
  let qrIsFullscreen = false;
  let qrShowAbsentOnly = false;
  let qrAllStudents = [];
  let qrPresentStudents = [];
  let qrPreviousCount = 0;
  
  // Sound notification function
  function playQRNotificationSound() {
    if (!qrSoundEnabled) return;
    
    // Create audio context for beep sound
    const audioContext = new (window.AudioContext || window.webkitAudioContext)();
    const oscillator = audioContext.createOscillator();
    const gainNode = audioContext.createGain();
    
    oscillator.connect(gainNode);
    gainNode.connect(audioContext.destination);
    
    oscillator.frequency.value = 800; // Pleasant beep frequency
    oscillator.type = 'sine';
    
    gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
    gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.3);
    
    oscillator.start(audioContext.currentTime);
    oscillator.stop(audioContext.currentTime + 0.3);
  }
  
  // Fullscreen toggle function using proper Fullscreen API
  async function toggleQRFullscreen() {
    const modal = document.getElementById("qrModalCard");
    const btn = document.getElementById("qrFullscreenBtn");
    const btnText = document.getElementById("qrFullscreenText");
    
    if (!modal || !btn) return;
    
    // Check if already in fullscreen
    const isCurrentlyFullscreen = document.fullscreenElement || document.webkitFullscreenElement || document.mozFullScreenElement;
    
    if (!isCurrentlyFullscreen) {
      // Enter fullscreen
      try {
        if (modal.requestFullscreen) {
          await modal.requestFullscreen();
        } else if (modal.webkitRequestFullscreen) {
          await modal.webkitRequestFullscreen();
        } else if (modal.mozRequestFullScreen) {
          await modal.mozRequestFullScreen();
        } else if (modal.msRequestFullscreen) {
          await modal.msRequestFullscreen();
        }
        
        qrIsFullscreen = true;
        applyFullscreenStyles(modal, true);
        btnText.textContent = "Exit Fullscreen";
      } catch (err) {
        console.error('Fullscreen failed:', err);
        // Fallback to CSS-only fullscreen
        cssOnlyFullscreen(modal, true);
      }
    } else {
      // Exit fullscreen
      try {
        if (document.exitFullscreen) {
          await document.exitFullscreen();
        } else if (document.webkitExitFullscreen) {
          await document.webkitExitFullscreen();
        } else if (document.mozCancelFullScreen) {
          await document.mozCancelFullScreen();
        } else if (document.msExitFullscreen) {
          await document.msExitFullscreen();
        }
        
        qrIsFullscreen = false;
        applyFullscreenStyles(modal, false);
        btnText.textContent = "Fullscreen";
      } catch (err) {
        console.error('Exit fullscreen failed:', err);
        cssOnlyFullscreen(modal, false);
      }
    }
  }
  
  // Apply fullscreen styling
  function applyFullscreenStyles(modal, isFullscreen) {
    const qrContainer = document.getElementById("qrCodeContainer");
    const qrImage = qrContainer?.querySelector("img");
    const qrSection = qrContainer?.closest('div[style*="text-align:center"]');
    const modalBody = modal.querySelector(".modal-body");
    // Find student section by looking for the one that contains #qrStudentList
    const studentSectionWrapper = modal.querySelector('#qrStudentList')?.closest('div[style*="background:var(--surface-1)"]');
    
    if (isFullscreen) {
      // Fullscreen mode - show both QR and student list side by side
      modal.classList.add('qr-fullscreen-active');
      
      // Hide the modal backdrop in fullscreen
      const backdrop = modal.querySelector('.modal-backdrop');
      if (backdrop) {
        backdrop.style.display = 'none';
      }
      
      // Modal body: horizontal layout, fills full height
      if (modalBody) {
        modalBody.style.cssText = "display: flex !important; flex-direction: row !important; gap: 24px !important; padding: 32px !important; flex: 1 1 auto !important; overflow: hidden !important; align-items: stretch !important; min-height: 0 !important;";
      }
      
      // QR section: takes 60% of width - keep same bright styling as normal mode
      if (qrSection) {
        qrSection.style.cssText = "text-align: center; padding: 32px; background: var(--surface-1); border: 1px solid var(--card-border); border-radius: 16px; backdrop-filter: blur(20px); flex: 0 0 60% !important; display: flex !important; flex-direction: column !important; align-items: center !important; justify-content: center !important; margin-bottom: 0 !important;";
      }
      
      // Student section wrapper: takes 40% of width - keep same bright styling as normal mode
      if (studentSectionWrapper) {
        studentSectionWrapper.style.cssText = "background: var(--surface-1); border: 1px solid var(--card-border); border-radius: 16px; padding: 24px; backdrop-filter: blur(20px); flex: 0 0 40% !important; display: flex !important; flex-direction: column !important; min-height: 0 !important;";
        studentSectionWrapper.setAttribute('data-fullscreen-student', 'true');
        
        // Find header and list
        const studentHeader = studentSectionWrapper.querySelector('div[style*="justify-content:space-between"]');
        const studentList = studentSectionWrapper.querySelector('#qrStudentList');
        
        // Header stays fixed size
        if (studentHeader) {
          studentHeader.style.flex = "0 0 auto";
          studentHeader.style.marginBottom = "12px";
        }
        
        // List fills remaining space - use setProperty for clarity
        if (studentList) {
          studentList.style.setProperty('max-height', 'none', 'important');
          studentList.style.setProperty('flex', '1 1 auto', 'important');
          studentList.style.setProperty('min-height', '0', 'important');
          studentList.style.setProperty('height', 'auto', 'important');
          studentList.style.setProperty('overflow-y', 'auto', 'important');
        }
      }
      
      // Scale QR container
      if (qrContainer) {
        qrContainer.style.padding = "30px";
        qrContainer.style.margin = "auto";
      }
      
      // Scale QR image responsively
      if (qrImage) {
        qrImage.style.width = "min(50vh, 40vw, 600px)";
        qrImage.style.height = "min(50vh, 40vw, 600px)";
        qrImage.style.transition = "all 0.3s ease";
      }
      
    } else {
      // Normal mode
      modal.classList.remove('qr-fullscreen-active');
      
      // Show the modal backdrop again
      const backdrop = modal.querySelector('.modal-backdrop');
      if (backdrop) {
        backdrop.style.display = '';
      }
      
      // Reset modal body
      if (modalBody) {
        modalBody.style.display = "";
        modalBody.style.flexDirection = "";
        modalBody.style.gap = "";
        modalBody.style.padding = "";
        modalBody.style.flex = "";
        modalBody.style.overflow = "";
        modalBody.style.alignItems = "";
      }
      
      // Reset QR section
      if (qrSection) {
        qrSection.style.flex = "";
        qrSection.style.display = "";
        qrSection.style.flexDirection = "";
        qrSection.style.alignItems = "";
        qrSection.style.justifyContent = "";
        qrSection.style.marginBottom = "";
      }
      
      // Reset student section
      if (studentSectionWrapper) {
        studentSectionWrapper.style.flex = "";
        studentSectionWrapper.style.display = "";
        studentSectionWrapper.style.flexDirection = "";
        studentSectionWrapper.style.minHeight = "";
        studentSectionWrapper.removeAttribute('data-fullscreen-student');
        
        // Reset header
        const studentHeader = studentSectionWrapper.querySelector('div[style*="justify-content:space-between"]');
        if (studentHeader) {
          studentHeader.style.flex = "";
          studentHeader.style.marginBottom = "";
        }
        
        // Reset student list - clear inline styles to let CSS class apply
        const studentList = studentSectionWrapper.querySelector('#qrStudentList');
        if (studentList) {
          studentList.style.maxHeight = ""; // Falls back to CSS class rule (320px)
          studentList.style.flex = "";
          studentList.style.minHeight = "";
          studentList.style.height = "";
          studentList.style.overflowY = "";
        }
      }
      
      // Reset QR container
      if (qrContainer) {
        qrContainer.style.padding = "24px";
        qrContainer.style.margin = "";
      }
      
      // Reset QR image
      if (qrImage) {
        qrImage.style.width = "300px";
        qrImage.style.height = "300px";
      }
    }
  }
  
  // Fallback CSS-only fullscreen (if Fullscreen API not supported)
  function cssOnlyFullscreen(modal, isFullscreen) {
    const btnText = document.getElementById("qrFullscreenText");
    
    if (isFullscreen) {
      modal.style.position = "fixed";
      modal.style.top = "0";
      modal.style.left = "0";
      modal.style.width = "100vw";
      modal.style.height = "100vh";
      modal.style.maxWidth = "100vw";
      modal.style.maxHeight = "100vh";
      modal.style.zIndex = "99999";
      modal.style.borderRadius = "0";
      
      qrIsFullscreen = true;
      applyFullscreenStyles(modal, true);
      if (btnText) btnText.textContent = "Exit Fullscreen";
      
    } else {
      modal.style.position = "";
      modal.style.top = "";
      modal.style.left = "";
      modal.style.width = "";
      modal.style.height = "";
      modal.style.maxWidth = "720px";
      modal.style.maxHeight = "90vh";
      modal.style.zIndex = "";
      modal.style.borderRadius = "";
      
      qrIsFullscreen = false;
      applyFullscreenStyles(modal, false);
      if (btnText) btnText.textContent = "Fullscreen";
    }
  }
  
  // Filter toggle function
  function toggleAbsentFilter() {
    qrShowAbsentOnly = !qrShowAbsentOnly;
    const btn = document.getElementById("qrFilterAbsent");
    
    if (btn) {
      btn.textContent = qrShowAbsentOnly ? "Show All" : "Show Missing";
      btn.style.background = qrShowAbsentOnly ? "var(--accent)" : "";
      btn.style.color = qrShowAbsentOnly ? "white" : "";
    }
    
    renderQRStudentList();
  }
  
  // Render student list with filter support
  function renderQRStudentList() {
    const listEl = document.getElementById("qrStudentList");
    if (!listEl) return;
    
    let studentsToShow = [];
    
    if (qrShowAbsentOnly) {
      // Show students who haven't scanned
      const presentCodes = new Set(qrPresentStudents.map(s => s.student_code));
      studentsToShow = qrAllStudents.filter(s => !presentCodes.has(s.student_code));
      
      if (studentsToShow.length === 0) {
        listEl.innerHTML = `
          <div style="padding:40px 20px; text-align:center; color:var(--success);">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--success)" stroke-width="2" style="margin-bottom:12px;">
              <polyline points="20 6 9 17 4 12"/>
            </svg>
            <div style="font-size:14px; font-weight:600;">All students are present!</div>
            <div style="font-size:12px; margin-top:4px; opacity:0.8;">Everyone has scanned the QR code</div>
          </div>
        `;
        reapplyFullscreenStylesIfNeeded();
        return;
      }
      
      listEl.innerHTML = studentsToShow.map(s => `
        <div style="padding:14px 18px; border-bottom:1px solid var(--card-border); display:flex; align-items:center; justify-content:space-between; background:rgba(var(--danger-rgb), 0.05);">
          <div style="flex:1;">
            <div style="font-weight:700; color:var(--text); font-size:15px; margin-bottom:4px; letter-spacing:-0.2px;">${escapeHtml(s.name || '')}</div>
            <div style="font-size:13px; color:var(--muted); font-family: 'Courier New', monospace; letter-spacing:0.5px;">${escapeHtml(s.student_code || '')}</div>
          </div>
          <span style="padding:6px 14px; background:rgba(var(--danger-rgb), 0.12); color:var(--danger); border:1px solid rgba(var(--danger-rgb), 0.3); border-radius:8px; font-size:12px; font-weight:700;">
            ✗ MISSING
          </span>
        </div>
      `).join('');
      
      // Reapply fullscreen styles after render
      reapplyFullscreenStylesIfNeeded();
      
    } else {
      // Show students who scanned
      if (qrPresentStudents.length === 0) {
        listEl.innerHTML = `
          <div style="padding:40px 20px; text-align:center; color:var(--muted);">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="opacity:0.3; margin-bottom:12px;">
              <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
              <circle cx="9" cy="7" r="4"/>
              <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
              <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
            <div style="font-size:14px; font-weight:500;">No students have scanned yet...</div>
            <div style="font-size:12px; margin-top:4px; opacity:0.7;">Students will appear here as they scan the QR code</div>
          </div>
        `;
        reapplyFullscreenStylesIfNeeded();
        return;
      }
      
      listEl.innerHTML = qrPresentStudents.map(s => `
        <div style="padding:14px 18px; border-bottom:1px solid var(--card-border); display:flex; align-items:center; justify-content:space-between; transition:background 0.2s ease; backdrop-filter:blur(10px);" onmouseover="this.style.background='var(--surface-1)'" onmouseout="this.style.background='transparent'">
          <div style="flex:1;">
            <div style="font-weight:700; color:var(--text); font-size:15px; margin-bottom:4px; letter-spacing:-0.2px;">${escapeHtml(s.full_name || '')}</div>
            <div style="font-size:13px; color:var(--muted); font-family: 'Courier New', monospace; letter-spacing:0.5px;">${escapeHtml(s.student_code || '')}</div>
          </div>
          <div style="display:flex; align-items:center; gap:12px;">
            <span style="padding:6px 14px; background:var(--badge-success-bg); color:var(--badge-success-text); border:1px solid var(--badge-success-border); border-radius:8px; font-size:12px; font-weight:700; letter-spacing:0.3px;">
              ✓ ${escapeHtml(s.status || 'PRESENT')}
            </span>
            <span style="font-size:12px; color:var(--muted); font-weight:500; min-width:70px; text-align:right;">
              ${new Date(s.marked_at).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })}
            </span>
          </div>
        </div>
      `).join('');
      
      // Reapply fullscreen styles after render
      reapplyFullscreenStylesIfNeeded();
    }
  }
  
  // Helper to reapply fullscreen styles if currently in fullscreen mode
  function reapplyFullscreenStylesIfNeeded() {
    if (qrIsFullscreen) {
      const modal = document.getElementById("qrModalCard");
      if (modal) {
        // Direct call without setTimeout - inline style conflict removed
        applyFullscreenStyles(modal, true);
      }
    }
  }

  function closeQRAttendanceModal(scheduleId) {
    if (qrTokenPollInterval) {
      clearInterval(qrTokenPollInterval);
      qrTokenPollInterval = null;
    }
    if (qrStatusPollInterval) {
      clearInterval(qrStatusPollInterval);
      qrStatusPollInterval = null;
    }

    // Close QR session on server
    if (qrSession?.session_id) {
      const fd = new FormData();
      fd.append("session_id", String(qrSession.session_id));
      fetchJson("php/close_qr_session.php", { method: "POST", body: fd }).catch(() => {});
    }

    const modal = document.getElementById("qrAttendanceModal");
    if (modal) modal.remove();

    qrSession = null;
    qrIsFullscreen = false;
    qrShowAbsentOnly = false;
    qrAllStudents = [];
    qrPresentStudents = [];
    qrPreviousCount = 0;
    
    console.log('[QR Modal] Closed.');
  }

  async function startQRSession(scheduleId) {
    try {
      setQRModalStatus("Starting QR session...", "loading");

      const fd = new FormData();
      fd.append("schedule_id", String(scheduleId));

      let payload;
      try {
        payload = await fetchJson("php/start_qr_attendance.php", { method: "POST", body: fd });
      } catch (err) {
        // Check if it's a closed session error - if so, just close modal silently
        const res = await fetch("php/start_qr_attendance.php", { method: "POST", body: fd });
        const errorData = await res.json().catch(() => ({}));
        
        if (errorData.already_closed) {
          console.log('[QR] Session already closed, user must use normal attendance');
          closeQRAttendanceModal();
          setStatusById("attendanceStatus", "QR session was already used. Please click the slot again to open manual attendance.", "info");
          return;
        }
        
        // Otherwise re-throw the original error
        throw err;
      }
      
      if (!payload.success) throw new Error(payload.error || "Failed to start QR session");

      qrSession = payload.data;
      
      console.log('[QR] Session started:', qrSession);

      setQRModalStatus("", "");

      // Generate initial QR code
      if (qrSession.token) {
        generateQRCode(qrSession.token);
      } else {
        console.error('[QR] No token in response:', qrSession);
        setQRModalStatus("Error: No token received", "error");
      }

      // Start polling for token rotation (every 15 seconds)
      qrTokenPollInterval = setInterval(() => refreshQRToken(), 15000);

      // Start polling for student submissions (every 3 seconds)
      qrStatusPollInterval = setInterval(() => refreshQRStudentList(scheduleId), 3000);

      // Initial load of student list
      refreshQRStudentList(scheduleId);

    } catch (err) {
      console.error('[QR] Session start error:', err);
      setQRModalStatus(err.message || "Failed to start QR session", "error");
    }
  }

  async function refreshQRToken() {
    if (!qrSession?.session_id) return;

    try {
      const payload = await fetchJson(`php/get_qr_token.php?session_id=${qrSession.session_id}`);
      if (!payload.success) {
        console.error("[QR Token] Refresh failed:", payload.error);
        return;
      }

      if (payload.data.token !== qrSession.token) {
        console.log('[QR Token] Rotating to new token');
        qrSession.token = payload.data.token;
        generateQRCode(payload.data.token);
      }
    } catch (err) {
      console.error("[QR Token] Refresh error:", err);
    }
  }

  function generateQRCode(token) {
    const container = document.getElementById("qrCodeContainer");
    if (!container) return;

    // Build the URL for students
    const targetUrl = `${window.location.origin}/student_qr_attendance.php?token=${encodeURIComponent(token)}`;
    
    console.log('[QR] Target URL:', targetUrl);

    // Use our own PHP proxy to avoid CORS issues
    const qrApiUrl = `php/generate_qr_code.php?data=${encodeURIComponent(targetUrl)}`;
    
    console.log('[QR] API URL:', qrApiUrl);
    
    // Create image element
    const img = document.createElement('img');
    img.src = qrApiUrl;
    img.alt = 'QR Code for Attendance';
    img.style.width = '300px';
    img.style.height = '300px';
    img.style.display = 'block';
    img.style.margin = '0 auto';
    
    img.onerror = function() {
      console.error('[QR] Image failed to load');
      container.innerHTML = `<div style="padding:20px; text-align:center;">
        <p style="color:var(--danger); margin-bottom:10px;">Failed to load QR code</p>
        <p style="font-size:12px;"><a href="${targetUrl}" target="_blank" style="color:var(--primary); word-break:break-all;">${targetUrl}</a></p>
      </div>`;
    };
    
    img.onload = function() {
      console.log('[QR] Image loaded successfully');
    };
    
    container.innerHTML = '';
    container.appendChild(img);
  }

  async function refreshQRStudentList(scheduleId) {
    try {
      const payload = await fetchJson(`php/get_qr_attendance_status.php?schedule_id=${scheduleId}`);
      if (!payload.success) {
        console.error("Status refresh failed:", payload.error);
        return;
      }

      const students = payload.data.students || [];
      const totalEnrolled = payload.data.total_enrolled || 0;
      const allStudents = payload.data.all_students || [];
      
      // Update global state
      qrPresentStudents = students;
      qrAllStudents = allStudents;
      
      // Detect new student (for animation)
      if (students.length > qrPreviousCount && qrPreviousCount > 0) {
        // Pulse animation on count badge
        const countEl = document.getElementById("qrStudentCount");
        if (countEl) {
          countEl.style.transform = "scale(1.3)";
          setTimeout(() => { countEl.style.transform = "scale(1)"; }, 300);
        }
      }
      qrPreviousCount = students.length;
      
      // Update count badge
      const countEl = document.getElementById("qrStudentCount");
      if (countEl) countEl.textContent = String(students.length);
      
      // Update progress text
      const progressEl = document.getElementById("qrProgressText");
      if (progressEl && totalEnrolled > 0) {
        const percentage = Math.round((students.length / totalEnrolled) * 100);
        progressEl.textContent = `(${students.length}/${totalEnrolled} • ${percentage}%)`;
        
        // Show filter button if we have enrolled students
        const filterBtn = document.getElementById("qrFilterAbsent");
        if (filterBtn && students.length > 0) {
          filterBtn.style.display = "flex";
        }
      }
      
      // Render the list
      renderQRStudentList();
      
      // Note: renderQRStudentList already calls reapplyFullscreenStylesIfNeeded internally
      
    } catch (err) {
      console.error("Status refresh error:", err);
    }
  }

  function setQRModalStatus(message, type) {
    const statusEl = document.getElementById("qrModalStatus");
    if (!statusEl) return;

    if (!message) {
      statusEl.style.display = "none";
      return;
    }

    statusEl.style.display = "block";
    statusEl.textContent = message;
    statusEl.className = "";

    if (type === "error") {
      statusEl.style.background = "var(--danger-bg)";
      statusEl.style.color = "var(--danger)";
      statusEl.style.border = "1px solid var(--danger)";
    } else if (type === "loading") {
      statusEl.style.background = "var(--info-bg)";
      statusEl.style.color = "var(--info)";
      statusEl.style.border = "1px solid var(--info)";
    } else {
      statusEl.style.background = "var(--success-bg)";
      statusEl.style.color = "var(--success)";
      statusEl.style.border = "1px solid var(--success)";
    }
  }

  // ============================================
  // END QR ATTENDANCE MODAL
  // ============================================

  function lectureWindowLabel(state) {
    if (state === "active") return "Lecture in progress";
    if (state === "ended") return "Lecture ended";
    if (state === "upcoming") return "Not yet open";
    return "";
  }

  function scheduleStatusLabel(sched) {
    if (!sched) return "";
    if (sched.hours_counted || sched.attendance_taken) return "Attendance recorded";
    if (sched.lecture_window_state === "active") return "Lecture in progress";
    if (sched.lecture_window_state === "ended") return "Lecture ended \u2014 attendance locked";
    if (sched.lecture_window_state === "upcoming") return "Opens at scheduled Cairo time";
    return "";
  }

  function renderAttendanceGrid(grid, onSlotClick, options = {}) {
  const body = document.getElementById("attendanceScheduleBody");
  if (!body) return;

  const { isAdmin = false } = options || {};

  body.innerHTML = "";

  for (const slot of SLOTS) {
    const tr = document.createElement("tr");
    const th = document.createElement("th");
    th.innerHTML = `<div class="slot-hdr"><div class="slot-hdr-num">Slot ${slot}</div><div class="slot-hdr-time">${escapeHtml(SLOT_TIMES[slot] || "")}</div></div>`;
    tr.appendChild(th);

    for (const day of DAYS) {
      const td = document.createElement("td");
      const cell = document.createElement("div");
      cell.className = "slot";

      const assigned = grid?.[day]?.[String(slot)] || null;
      const items = (assigned?.items || []).filter(Boolean);

      if (!items.length) {
        cell.innerHTML = `<div class="slot-title">\u2014</div><div class="slot-sub">Empty</div>`;
        cell.style.cursor = "default";
      } else {
        const first = items[0];
        const canOpen = isAdmin || items.some((it) => it.can_open_attendance !== false);
        const windowState = first?.lecture_window_state || "";
        const taken = items.some((it) => it.attendance_taken);

        cell.classList.add("filled");

        // Apply state-specific class for teachers so visual is unambiguous
        if (!isAdmin) {
          if (taken) {
            cell.classList.add("slot-state-recorded");
          } else if (windowState === "active") {
            cell.classList.add("slot-state-active");
          } else if (windowState === "upcoming") {
            cell.classList.add("slot-state-upcoming");
          } else if (windowState === "ended") {
            cell.classList.add("slot-state-ended");
          }
        }

        if (!canOpen) {
          cell.style.cursor = "not-allowed";
        } else {
          cell.style.cursor = "pointer";
        }

        if (assigned.multiple) {
          cell.innerHTML = `<div class="slot-title">Multiple</div><div class="slot-sub">Same slot</div>`;
          if (first?.doctor_color) {
            cell.style.background = first.doctor_color + "22";
            cell.style.borderColor = first.doctor_color + "88";
          }
          if (canOpen) {
            cell.addEventListener("click", () => onSlotClick(day, slot, first, items));
          }
        } else {
          const one = items[0];
          const room = one.room_code ? `Room ${escapeHtml(one.room_code)}` : "";
          const line2 = `${escapeHtml(one.doctor_name || "")} &middot; ${escapeHtml(makeCourseLabel(one.course_type, one.subject_code))}${room ? " &middot; " + room : ""}`;

          // State badge for teachers (not admins)
          let badgeHTML = "";
          if (!isAdmin) {
            if (taken) {
              badgeHTML = `<div class="slot-state-badge slot-badge-recorded">&#10003; Recorded</div>`;
            } else if (windowState === "active") {
              badgeHTML = `<div class="slot-state-badge slot-badge-active">&#9679; Open now</div>`;
            } else if (windowState === "upcoming") {
              badgeHTML = `<div class="slot-state-badge slot-badge-upcoming">&#9679; Not open yet</div>`;
            } else if (windowState === "ended") {
              badgeHTML = `<div class="slot-state-badge slot-badge-ended">&#9632; Closed</div>`;
            }
          }

          cell.innerHTML = `<div class="slot-title">${escapeHtml(one.course_name || "")}</div><div class="slot-sub">${line2}</div>${badgeHTML}`;
          if (one?.doctor_color && !badgeHTML) {
            cell.style.background = one.doctor_color + "22";
            cell.style.borderColor = one.doctor_color + "88";
          }
          if (canOpen) {
            cell.addEventListener("click", () => onSlotClick(day, slot, one, [one]));
          }
        }
      }

      td.appendChild(cell);
      tr.appendChild(td);
    }

    body.appendChild(tr);
  }
  }

  function updateAttendanceSaveState(scheduleMeta, isAdmin) {
    const saveBtn = document.getElementById("attendanceSaveChanges");
    if (!saveBtn) return;
    const locked = !isAdmin && scheduleMeta && scheduleMeta.lecture_window_state === "ended";
    saveBtn.disabled = Boolean(locked);
  }

  function renderAttendanceModalRows(items, filterText, options = {}) {
  const body = document.getElementById("attendanceModalBody");
  if (!body) return;

  const { isAdmin = false, schedule = null } = options || {};
  const scheduleLocked = !isAdmin && schedule && schedule.lecture_window_state === "ended";
  const q = String(filterText || "").trim().toLowerCase();
  const filtered = (items || []).filter((s) => {
    if (!q) return true;
    return (
      String(s.full_name || "").toLowerCase().includes(q) ||
      String(s.student_code || "").toLowerCase().includes(q) ||
      String(s.student_id || "").toLowerCase().includes(q)
    );
  });

  if (!filtered.length) {
    body.innerHTML = `<tr><td colspan="3" class="muted" style="padding:14px;">No matching students.</td></tr>`;
    return;
  }

  body.innerHTML = "";

  for (const s of filtered) {
    const tr = document.createElement("tr");
    const status = String(s.attendance_status || "").toUpperCase();
    const isPresent = status === "PRESENT";
    const hasStatus = status === "PRESENT" || status === "ABSENT";
    const isLocked = scheduleLocked || (!isAdmin && Boolean(s.attendance_locked));

    tr.innerHTML = `
      <td class="muted" style="font-family: 'Courier New', monospace; font-weight:600;">${escapeHtml(s.student_code || s.student_id || "")}</td>
      <td class="student-name" style="font-weight:600;">${escapeHtml(s.full_name || "")}</td>
      <td style="text-align:center;">
        <div class="attendance-status-btns">
          <button type="button" class="attendance-status-btn present ${isPresent ? 'active' : ''}" data-status="PRESENT" ${isLocked ? 'disabled' : ''}>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" style="vertical-align:middle; margin-right:4px;">
              <polyline points="20 6 9 17 4 12"/>
            </svg>
            Present
          </button>
          <button type="button" class="attendance-status-btn absent ${!isPresent ? 'active' : ''}" data-status="ABSENT" ${isLocked ? 'disabled' : ''}>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:middle; margin-right:4px;">
              <circle cx="12" cy="12" r="10"/>
              <path d="M15 9l-6 6M9 9l6 6"/>
            </svg>
            Absent
          </button>
        </div>
      </td>
    `;

    if (isLocked) tr.classList.add("attendance-locked");

    tr.dataset.studentId = String(s.student_id);
    body.appendChild(tr);
  }
  }

  async function initAttendancePage() {
  let currentCtx = null; // {schedule_id:number, items:[], schedule:object}
  let latestGrid = {};
  const dirtyStudents = new Set();
  const me = meCache || (await authFetchMe());
  const role = String(me?.role || "").toLowerCase();
  const isAdmin = role === "admin" || role === "management";

  try {
    setStatusById("attendanceStatus", "Loading...");

    await loadWeeks();

    // Courses select (for export)
    const courseSel = document.getElementById("attendanceCourseSelect");

    async function refreshAttendanceCoursesDropdown(activeYear) {
      if (!courseSel) return;

      const weekIdVal = document.getElementById("attendanceWeekSelect")?.value;
      const weekId = weekIdVal ? Number(weekIdVal) : attendanceState.activeWeekId;

      courseSel.innerHTML = "";
      const opt0 = document.createElement("option");
      opt0.value = "";
      opt0.textContent = "Select a course...";
      courseSel.appendChild(opt0);

      if (!weekId) return;

      const qs = new URLSearchParams({ year_level: String(activeYear), week_id: String(weekId) });
      const payload = await fetchJson(`php/get_attendance_courses.php?${qs.toString()}`);
      if (!payload.success) throw new Error(payload.error || "Failed to load courses");

      const courses = payload.data || [];
      for (const c of courses) {
        const opt = document.createElement("option");
        opt.value = String(c.course_id);
        // Course list is already filtered to the active year.
        const label = `${c.course_name}${c.semester ? " (S" + c.semester + ")" : ""}`;
        opt.textContent = label;
        courseSel.appendChild(opt);
      }
    }

    // Weeks select
    const weekSel = document.getElementById("attendanceWeekSelect");
    if (weekSel) {
      weekSel.innerHTML = "";
      for (const w of attendanceState.weeks || []) {
        const opt = document.createElement("option");
        opt.value = String(w.week_id);
        const wk = formatWeekDisplayLabel
          ? formatWeekDisplayLabel(w)
          : (formatWeekLabelWithRange ? formatWeekLabelWithRange(w) : String(w.label || `Week ${w.week_id}`));
        opt.textContent = wk;
        if (w.status === "active") opt.selected = true;
        weekSel.appendChild(opt);
      }
      if (!weekSel.value && attendanceState.weeks?.[0]) weekSel.value = String(attendanceState.weeks[0].week_id);
    }

    // Modal close handlers
    document.getElementById("attendanceModal")?.addEventListener("click", (e) => {
      const t = e.target;
      if (t?.dataset?.close === "1" || t?.closest?.("[data-close='1']")) closeAttendanceModal();
    });
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape") closeAttendanceModal();
    });

    // Year tabs (match Student Schedule UI pattern)
    let activeYear = 1;
    document.querySelectorAll(".tabs [data-year]")?.forEach((b) => b.classList.remove("active"));
    document.querySelector(`.tabs [data-year='${activeYear}']`)?.classList.add("active");

    async function refreshAttendanceGrid() {
      setStatusById("attendanceStatus", "Loading...");

      const weekIdVal = document.getElementById("attendanceWeekSelect")?.value;
      const weekId = weekIdVal ? Number(weekIdVal) : attendanceState.activeWeekId;

      const qs = new URLSearchParams({ year_level: String(activeYear) });
      if (weekId) qs.set("week_id", String(weekId));

      const payload = await fetchJson(`php/get_attendance_grid.php?${qs.toString()}`);
      if (!payload.success) throw new Error(payload.error || "Failed to load attendance grid");

      latestGrid = payload.data?.grid || {};

      renderAttendanceGrid(latestGrid, async (_day, _slot, chosen) => {
        try {
          if (!chosen?.schedule_id) {
            setStatusById("attendanceStatus", "No schedule in this slot.", "error");
            return;
          }

          if (!isAdmin && chosen.can_open_attendance === false) {
            const hint = chosen.lecture_window_state === "upcoming"
              ? "This lecture is not open yet (Cairo time)."
              : "Attendance is locked for this lecture.";
            setStatusById("attendanceStatus", hint, "error");
            return;
          }

          // Check if QR session should be shown (teachers only, first time, active lecture)
          if (!isAdmin) {
            const hasAttendance = chosen.attendance_taken || chosen.hours_counted;
            
            if (!hasAttendance && chosen.lecture_window_state === "active") {
              // Check if there's a closed QR session for this slot
              try {
                const qrCheckFd = new FormData();
                qrCheckFd.append("schedule_id", String(chosen.schedule_id));
                const qrCheckRes = await fetch("php/start_qr_attendance.php", { method: "POST", body: qrCheckFd });
                const qrCheckData = await qrCheckRes.json().catch(() => ({}));
                
                // If already closed, skip QR and go to normal modal
                if (qrCheckData.already_closed) {
                  console.log('[Slot Click] QR session was closed, opening normal modal');
                  // Fall through to normal modal below
                } else {
                  // First time opening this slot - offer QR option
                  openQRAttendanceModal(chosen.schedule_id);
                  return;
                }
              } catch (err) {
                // If check fails, try QR anyway
                openQRAttendanceModal(chosen.schedule_id);
                return;
              }
            }
          }

          // Open normal attendance modal (original code)
          currentCtx = { schedule_id: Number(chosen.schedule_id), items: [], schedule: null };
          setStatusById("attendanceModalStatus", "");

          openAttendanceModal();
          setStatusById("attendanceModalStatus", "Loading students...");

          const attPayload = await fetchJson(`php/get_attendance.php?schedule_id=${encodeURIComponent(chosen.schedule_id)}`);
          if (!attPayload.success) throw new Error(attPayload.error || "Failed to load attendance");

          const scheduleMeta = attPayload.data?.schedule || {};
          currentCtx.schedule = scheduleMeta;
          currentCtx.items = attPayload.data?.items || [];

          if (!isAdmin && scheduleMeta.can_open_attendance === false) {
            closeAttendanceModal();
            setStatusById("attendanceStatus", "Attendance is not available for this lecture.", "error");
            return;
          }

          const meta = document.getElementById("attendanceModalMeta");
          const statusLabel = scheduleStatusLabel(scheduleMeta);
          if (meta) {
            meta.innerHTML = `<strong>${escapeHtml(scheduleMeta.course_name || chosen.course_name || "")}</strong> &middot; Year ${escapeHtml(scheduleMeta.year_level ?? chosen.year_level)}${scheduleMeta.room_code || chosen.room_code ? ` &middot; Room ${escapeHtml(scheduleMeta.room_code || chosen.room_code)}` : ""}${statusLabel ? ` &middot; <span class="muted">${escapeHtml(statusLabel)}</span>` : ""}`;
          }

          renderAttendanceModalRows(currentCtx.items, "", { isAdmin, schedule: scheduleMeta });
          dirtyStudents.clear();
          updateAttendanceSaveState(scheduleMeta, isAdmin);
          setStatusById("attendanceModalStatus", "");

          const searchEl = document.getElementById("attendanceStudentSearch");
          if (searchEl) {
            searchEl.value = "";
            searchEl.oninput = () => renderAttendanceModalRows(currentCtx.items, searchEl.value || "", { isAdmin, schedule: scheduleMeta });
          }

        } catch (err) {
          setStatusById("attendanceModalStatus", err.message || "Failed to load attendance", "error");
        }
      }, { isAdmin });

      setStatusById("attendanceStatus", "");
    }

    document.getElementById("refreshAttendanceGrid")?.addEventListener("click", async () => {
      await refreshAttendanceGrid();
      await refreshAttendanceCoursesDropdown(activeYear);
    });
    document.getElementById("attendanceWeekSelect")?.addEventListener("change", async () => {
      await refreshAttendanceGrid();
      await refreshAttendanceCoursesDropdown(activeYear);
    });

    document.querySelectorAll(".tabs [data-year]")?.forEach((btn) => {
      btn.addEventListener("click", async () => {
        document.querySelectorAll(".tabs [data-year]").forEach((b) => b.classList.remove("active"));
        btn.classList.add("active");
        activeYear = Number(btn.dataset.year) || 1;
        await refreshAttendanceGrid();
        await refreshAttendanceCoursesDropdown(activeYear);
      });
    });

    async function saveSingle(studentId, status) {
      const fd = new FormData();
      fd.append("schedule_id", String(currentCtx?.schedule_id || 0));
      fd.append("student_id", String(studentId));
      fd.append("status", String(status));
      const payload = await fetchJson("php/set_attendance.php", { method: "POST", body: fd });
      if (!payload.success) throw new Error(payload.error || "Save failed");
    }

    function markDirty(studentId) {
      if (!studentId) return;
      dirtyStudents.add(studentId);
      setStatusById("attendanceModalStatus", "Unsaved changes", "warn");
    }

    document.getElementById("attendanceModalBody")?.addEventListener("click", (e) => {
      const btn = e.target.closest(".attendance-status-btn");
      if (!btn || btn.disabled) return;

      const tr = btn.closest("tr");
      const studentId = Number(tr?.dataset?.studentId || 0);
      if (!studentId || !currentCtx?.schedule_id) return;

      if (!isAdmin) {
        const sched = currentCtx?.schedule || {};
        if (sched.lecture_window_state === "ended") {
          return;
        }
      }

      const status = btn.dataset.status;
      const target = (currentCtx.items || []).find((x) => Number(x.student_id) === studentId);
      if (target) target.attendance_status = status;

      // Update button states
      const buttons = tr.querySelectorAll(".attendance-status-btn");
      buttons.forEach(b => {
        if (b.dataset.status === status) {
          b.classList.add("active");
        } else {
          b.classList.remove("active");
        }
      });

      markDirty(studentId);
    });

    async function bulkSet(toStatus) {
      if (!currentCtx?.schedule_id) {
        setStatusById("attendanceModalStatus", "Open a slot first.", "error");
        return;
      }
      if (!isAdmin && currentCtx.schedule?.lecture_window_state === "ended") {
        setStatusById("attendanceModalStatus", "Attendance is locked for this lecture.", "error");
        return;
      }
      const targets = (currentCtx.items || []).map((x) => Number(x.student_id)).filter((n) => n > 0);
      if (!targets.length) {
        setStatusById("attendanceModalStatus", "No students to update.", "error");
        return;
      }

      for (const it of currentCtx.items || []) it.attendance_status = toStatus;
      renderAttendanceModalRows(currentCtx.items, document.getElementById("attendanceStudentSearch")?.value || "", { isAdmin, schedule: currentCtx.schedule });
      for (const sid of targets) dirtyStudents.add(sid);
      setStatusById("attendanceModalStatus", `Marked ${targets.length}. Remember to save.`, "warn");
    }

    document.getElementById("attendanceMarkAllPresent")?.addEventListener("click", () => {
      bulkSet("PRESENT");
      window.dmportal?.showSuccess?.("✅ All students marked as Present");
    });
    document.getElementById("attendanceMarkAllAbsent")?.addEventListener("click", () => {
      bulkSet("ABSENT");
      window.dmportal?.showInfo?.("All students marked as Absent");
    });

    // Hide "Copy to Next Lecture" for teachers (admin-only feature)
    if (!isAdmin) {
      const copyNextBtn = document.getElementById("attendanceCopyNextLecture");
      if (copyNextBtn) copyNextBtn.style.display = "none";
    }

    document.getElementById("attendanceSaveChanges")?.addEventListener("click", async () => {
      if (!currentCtx?.schedule_id) {
        setStatusById("attendanceModalStatus", "Open a slot first.", "error");
        return;
      }
      const allIds = (currentCtx.items || []).map((x) => Number(x.student_id)).filter((n) => n > 0);
      const dirtyList = Array.from(dirtyStudents || []).filter((n) => Number(n) > 0);
      const targets = dirtyList.length > 0 ? dirtyList : allIds;
      if (!targets.length) {
        setStatusById("attendanceModalStatus", "No changes to save.", "info");
        return;
      }

      if (!isAdmin) {
        const sched = currentCtx.schedule || {};
        if (sched.lecture_window_state === "ended") {
          setStatusById("attendanceModalStatus", "Attendance is locked for this lecture.", "error");
          return;
        }
        for (const it of currentCtx.items || []) {
          const status = String(it?.attendance_status || "").toUpperCase();
          it.attendance_status = status === "PRESENT" ? "PRESENT" : "ABSENT";
        }
      }

      try {
        setStatusById("attendanceModalStatus", `Saving ${targets.length}...`);
        let done = 0;
        for (const sid of targets) {
          const it = (currentCtx.items || []).find((x) => Number(x.student_id) === sid);
          const status = String(it?.attendance_status || "ABSENT").toUpperCase() === "PRESENT" ? "PRESENT" : "ABSENT";
          await saveSingle(sid, status);
          done++;
          if (done % 10 === 0) setStatusById("attendanceModalStatus", `Saving... ${done}/${targets.length}`);
        }

        dirtyStudents.clear();

        const attPayload = await fetchJson(`php/get_attendance.php?schedule_id=${encodeURIComponent(currentCtx.schedule_id)}`);
        if (attPayload.success) {
          currentCtx.schedule = attPayload.data?.schedule || currentCtx.schedule;
          currentCtx.items = attPayload.data?.items || currentCtx.items;
        } else if (!isAdmin && currentCtx.schedule) {
          currentCtx.schedule.attendance_taken = true;
        }

        renderAttendanceModalRows(currentCtx.items, document.getElementById("attendanceStudentSearch")?.value || "", { isAdmin, schedule: currentCtx.schedule });
        updateAttendanceSaveState(currentCtx.schedule, isAdmin);
        setStatusById("attendanceModalStatus", `Saved ${targets.length}.`, "success");
      } catch (err) {
        setStatusById("attendanceModalStatus", err.message || "Save failed", "error");
      }
    });

    // Export whole subject (course) with 20-week placeholders
    document.getElementById("attendanceCopyNextLecture")?.addEventListener("click", async () => {
      if (!isAdmin) {
        setStatusById("attendanceModalStatus", "Admins only.", "error");
        return;
      }
      if (!currentCtx?.schedule_id) {
        setStatusById("attendanceModalStatus", "Open a slot first.", "error");
        return;
      }
      if (dirtyStudents.size > 0) {
        setStatusById("attendanceModalStatus", "Save changes before copying.", "warn");
        return;
      }
      try {
        setStatusById("attendanceModalStatus", "Copying to next lecture...");
        const fd = new FormData();
        fd.append("schedule_id", String(currentCtx.schedule_id));
        const payload = await fetchJson("php/copy_attendance_next_lecture.php", { method: "POST", body: fd });
        if (!payload.success) throw new Error(payload.error || "Copy failed");

        setStatusById(
          "attendanceModalStatus",
          `Copied ${payload.data?.copied || 0} records to next lecture (week ${payload.data?.target_week_id}, ${payload.data?.target_day_of_week} slot ${payload.data?.target_slot_number}).`,
          "success"
        );

        await refreshAttendanceGrid();

        const nextScheduleId = Number(payload.data?.target_schedule_id || 0);
        if (nextScheduleId) {
          let nextSlot = null;
          for (const day of Object.keys(latestGrid || {})) {
            const slots = latestGrid?.[day] || {};
            for (const slot of Object.keys(slots || {})) {
              const items = slots?.[slot]?.items || [];
              const found = items.find((it) => Number(it?.schedule_id) === nextScheduleId);
              if (found) {
                nextSlot = found;
                break;
              }
            }
            if (nextSlot) break;
          }

          if (nextSlot) {
            currentCtx = { schedule_id: Number(nextSlot.schedule_id), items: [], schedule: null };
            setStatusById("attendanceModalStatus", "Loading students...");
            const attPayload = await fetchJson(`php/get_attendance.php?schedule_id=${encodeURIComponent(nextSlot.schedule_id)}`);
            if (!attPayload.success) throw new Error(attPayload.error || "Failed to load attendance");
            currentCtx.schedule = attPayload.data?.schedule || null;
            currentCtx.items = attPayload.data?.items || [];
            const meta = document.getElementById("attendanceModalMeta");
            const statusLabel = scheduleStatusLabel(currentCtx.schedule);
            if (meta) {
              meta.innerHTML = `<strong>${escapeHtml(nextSlot.course_name || "")}</strong> &middot; Year ${escapeHtml(nextSlot.year_level)}${nextSlot.room_code ? ` &middot; Room ${escapeHtml(nextSlot.room_code)}` : ""}${statusLabel ? ` &middot; <span class="muted">${escapeHtml(statusLabel)}</span>` : ""}`;
            }
            renderAttendanceModalRows(currentCtx.items, document.getElementById("attendanceStudentSearch")?.value || "", { isAdmin, schedule: currentCtx.schedule });
            updateAttendanceSaveState(currentCtx.schedule, isAdmin);
            dirtyStudents.clear();
            setStatusById("attendanceModalStatus", "");
          }
        }
      } catch (err) {
        setStatusById("attendanceModalStatus", err.message || "Copy failed", "error");
      }
    });

    document.getElementById("exportAttendanceXls")?.addEventListener("click", () => {
      const courseId = Number(document.getElementById("attendanceCourseSelect")?.value || 0);
      if (!courseId) {
        setStatusById("attendanceStatus", "Select a course to export.", "error");
        return;
      }

      const endWeekIdVal = document.getElementById("attendanceWeekSelect")?.value;
      const endWeekId = endWeekIdVal ? Number(endWeekIdVal) : 0;
      if (!endWeekId) {
        setStatusById("attendanceStatus", "Select a week.", "error");
        return;
      }

      const qs = new URLSearchParams({ course_id: String(courseId), end_week_id: String(endWeekId) });
      window.location.href = `php/export_attendance_xls.php?${qs.toString()}`;
    });

    await refreshAttendanceGrid();
    await refreshAttendanceCoursesDropdown(activeYear);
  } catch (err) {
    setStatusById("attendanceStatus", err.message || "Failed to load Attendance.", "error");
  }
  }


  window.dmportal = window.dmportal || {};
  window.dmportal.initAttendancePage = initAttendancePage;
})();
