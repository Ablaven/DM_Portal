<?php
declare(strict_types=1);

// REQUIRE STUDENT LOGIN
require_once 'php/db_connect.php';
require_once 'php/_auth.php';

// Check if logged in as student
$currentUser = auth_current_user();

if (!$currentUser || strcasecmp($currentUser['role'], 'Student') !== 0) {
    $currentUrl = $_SERVER['REQUEST_URI'];
    header("Location: login.php?next=" . urlencode($currentUrl));
    exit;
}

header('Content-Type: text/html; charset=utf-8');

$token = trim((string)($_GET['token'] ?? ''));

if (empty($token)) {
    http_response_code(400);
    echo '<!doctype html><html><head><title>Error</title></head><body><h1>Invalid QR Code</h1><p>No token provided.</p></body></html>';
    exit;
}

// Get student info
$studentName = $currentUser['username'];
$studentCode = $currentUser['username'];

try {
    $pdo = get_pdo();
    if ($currentUser['student_id']) {
        $stmt = $pdo->prepare('SELECT name, student_code FROM students WHERE student_id = ? LIMIT 1');
        $stmt->execute([$currentUser['student_id']]);
        $student = $stmt->fetch();
        if ($student) {
            $studentName = $student['name'];
            $studentCode = $student['student_code'];
        }
    }
} catch (Exception $e) {
    // Use defaults if query fails
}

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>QR Attendance - Digital Marketing Portal</title>
  <link rel="stylesheet" href="css/style.css?v=20261110a" />
  <script>
    let theme = localStorage.getItem('dmportal-theme');
    if (!theme && window.matchMedia) {
      theme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }
    theme = theme || 'dark';
    document.documentElement.setAttribute('data-theme', theme);
  </script>
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    
    body { 
      background: var(--bg);
      color: var(--text);
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
      position: relative;
      overflow-x: hidden;
    }
    
    /* Glassmorphism background gradients matching main site */
    body::before {
      content: '';
      position: fixed;
      top: -15%;
      right: -10%;
      width: 800px;
      height: 800px;
      background: radial-gradient(circle, var(--bg-grad-1) 0%, transparent 70%);
      border-radius: 50%;
      pointer-events: none;
      z-index: 0;
      animation: float1 20s ease-in-out infinite;
    }
    
    body::after {
      content: '';
      position: fixed;
      bottom: -20%;
      left: -15%;
      width: 700px;
      height: 700px;
      background: radial-gradient(circle, var(--bg-grad-2) 0%, transparent 70%);
      border-radius: 50%;
      pointer-events: none;
      z-index: 0;
      animation: float2 25s ease-in-out infinite;
    }
    
    @keyframes float1 {
      0%, 100% { transform: translate(0, 0) scale(1); }
      33% { transform: translate(-30px, 50px) scale(1.1); }
      66% { transform: translate(40px, -40px) scale(0.95); }
    }
    
    @keyframes float2 {
      0%, 100% { transform: translate(0, 0) scale(1); }
      33% { transform: translate(50px, -30px) scale(1.05); }
      66% { transform: translate(-40px, 40px) scale(0.9); }
    }
    
    .qr-container {
      width: 100%;
      max-width: 460px;
      position: relative;
      z-index: 1;
    }
    
    /* Card matching portal style */
    .qr-card {
      background: var(--card);
      backdrop-filter: blur(40px);
      -webkit-backdrop-filter: blur(40px);
      border: 1px solid var(--card-border);
      border-radius: var(--radius);
      padding: 44px 38px;
      box-shadow: var(--shadow);
      position: relative;
    }
    
    .qr-header {
      text-align: center;
      margin-bottom: 32px;
    }
    
    .qr-icon-wrap {
      width: 88px;
      height: 88px;
      margin: 0 auto 20px;
      background: linear-gradient(135deg, var(--accent) 0%, rgba(var(--accent-rgb), 0.7) 100%);
      border-radius: 20px;
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 8px 24px rgba(var(--accent-rgb), 0.35);
      animation: iconFloat 3s ease-in-out infinite;
    }
    
    @keyframes iconFloat {
      0%, 100% { transform: translateY(0px); }
      50% { transform: translateY(-8px); }
    }
    
    .qr-icon {
      width: 48px;
      height: 48px;
      filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.2));
    }
    
    .qr-title {
      font-size: 26px;
      font-weight: 700;
      color: var(--text);
      margin: 0 0 8px 0;
      letter-spacing: -0.5px;
    }
    
    .qr-subtitle {
      font-size: 15px;
      color: var(--muted);
      font-weight: 500;
    }
    
    /* Student badge matching portal card style */
    .qr-student-badge {
      background: var(--surface-1);
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
      border: 1px solid var(--card-border);
      border-radius: 18px;
      padding: 22px 26px;
      margin-bottom: 28px;
      text-align: center;
      transition: all 0.3s ease;
    }
    
    .qr-student-badge:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(var(--accent-rgb), 0.15);
    }
    
    .qr-student-name {
      font-size: 19px;
      font-weight: 700;
      color: var(--text);
      margin-bottom: 8px;
      letter-spacing: -0.3px;
    }
    
    .qr-student-code {
      font-size: 15px;
      font-weight: 600;
      color: var(--accent);
      letter-spacing: 1.2px;
      font-family: 'Courier New', monospace;
      display: inline-block;
      padding: 6px 14px;
      background: rgba(var(--accent-rgb), 0.12);
      border-radius: 10px;
      border: 1px solid rgba(var(--accent-rgb), 0.25);
    }
    
    .qr-form-field {
      margin-bottom: 24px;
    }
    
    .qr-label {
      display: block;
      font-size: 14px;
      font-weight: 600;
      color: var(--text);
      margin-bottom: 10px;
      letter-spacing: 0.3px;
    }
    
    /* Input matching portal style */
    .qr-input {
      width: 100%;
      padding: 16px 20px;
      font-size: 16px;
      font-weight: 600;
      background: var(--input-bg);
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
      border: 1px solid var(--input-border);
      border-radius: 14px;
      color: var(--text);
      outline: none;
      transition: all 0.3s ease;
      text-transform: uppercase;
      letter-spacing: 1.5px;
      font-family: 'Courier New', monospace;
    }
    
    .qr-input:focus {
      border-color: var(--accent);
      box-shadow: 0 0 0 3px rgba(var(--accent-rgb), 0.15);
      transform: translateY(-2px);
    }
    
    .qr-input::placeholder {
      text-transform: none;
      letter-spacing: normal;
      font-weight: 400;
      color: var(--muted);
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }
    
    .qr-helper {
      font-size: 13px;
      color: var(--muted);
      margin-top: 8px;
    }
    
    /* Button matching portal .btn style */
    .qr-submit {
      width: 100%;
      padding: 18px 24px;
      font-size: 16px;
      font-weight: 700;
      color: white;
      background: var(--accent);
      border: none;
      border-radius: 14px;
      cursor: pointer;
      transition: all 0.3s ease;
      box-shadow: 0 6px 20px rgba(var(--accent-rgb), 0.3);
      letter-spacing: 0.5px;
    }
    
    .qr-submit:hover {
      transform: translateY(-3px);
      box-shadow: 0 10px 30px rgba(var(--accent-rgb), 0.4);
      background: linear-gradient(135deg, var(--accent) 0%, var(--accent-2) 100%);
    }
    
    .qr-submit:active {
      transform: translateY(-1px);
    }
    
    .qr-submit:disabled {
      opacity: 0.6;
      cursor: not-allowed;
      transform: none;
    }
    
    /* Status messages */
    .qr-status {
      margin-top: 18px;
      padding: 14px 18px;
      border-radius: 12px;
      font-size: 14px;
      font-weight: 600;
      text-align: center;
      display: none;
      animation: slideUp 0.4s ease;
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
    }
    
    @keyframes slideUp {
      from {
        opacity: 0;
        transform: translateY(10px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    
    .qr-status.success {
      background: var(--badge-success-bg);
      color: var(--badge-success-text);
      border: 1px solid var(--badge-success-border);
      display: block;
    }
    
    .qr-status.error {
      background: rgba(var(--danger-rgb), 0.12);
      color: var(--danger);
      border: 1px solid rgba(var(--danger-rgb), 0.3);
      display: block;
    }
    
    .qr-status.loading {
      background: rgba(var(--accent-rgb), 0.12);
      color: var(--accent);
      border: 1px solid rgba(var(--accent-rgb), 0.3);
      display: block;
    }
    
    /* Success view */
    .qr-success-view {
      text-align: center;
      animation: successZoom 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
    }
    
    @keyframes successZoom {
      0% {
        opacity: 0;
        transform: scale(0.8);
      }
      100% {
        opacity: 1;
        transform: scale(1);
      }
    }
    
    .qr-success-icon-big {
      font-size: 80px;
      margin-bottom: 24px;
      animation: successBounce 0.8s cubic-bezier(0.68, -0.55, 0.265, 1.55);
    }
    
    @keyframes successBounce {
      0% { 
        transform: scale(0); 
        opacity: 0;
      }
      50% { 
        transform: scale(1.2); 
      }
      100% { 
        transform: scale(1);
        opacity: 1;
      }
    }
    
    .qr-success-title {
      font-size: 26px;
      font-weight: 800;
      color: var(--success);
      margin-bottom: 20px;
      letter-spacing: -0.5px;
    }
    
    .qr-success-info {
      background: var(--surface-1);
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
      border: 1px solid var(--card-border);
      border-radius: 16px;
      padding: 22px;
      margin: 24px 0;
    }
    
    .qr-success-name {
      font-size: 20px;
      font-weight: 700;
      color: var(--text);
      margin-bottom: 8px;
    }
    
    .qr-success-code {
      font-size: 16px;
      font-weight: 600;
      color: var(--accent);
      font-family: 'Courier New', monospace;
      letter-spacing: 1px;
      margin-bottom: 16px;
    }
    
    .qr-success-status {
      display: inline-block;
      padding: 10px 24px;
      background: var(--badge-success-bg);
      color: var(--badge-success-text);
      border-radius: 20px;
      font-size: 15px;
      font-weight: 700;
      border: 1px solid var(--badge-success-border);
    }
    
    .qr-return-btn {
      display: inline-block;
      margin-top: 24px;
      padding: 14px 32px;
      background: var(--accent);
      color: white;
      text-decoration: none;
      border-radius: 12px;
      font-weight: 600;
      font-size: 15px;
      transition: all 0.3s ease;
      box-shadow: 0 4px 12px rgba(var(--accent-rgb), 0.25);
    }
    
    .qr-return-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(var(--accent-rgb), 0.35);
    }
    
    @media (max-width: 500px) {
      .qr-card {
        padding: 36px 24px;
      }
      
      .qr-title {
        font-size: 22px;
      }
      
      body::before,
      body::after {
        width: 400px;
        height: 400px;
      }
    }
  </style>
</head>
<body>
  <div class="qr-container">
    <div class="qr-card">
      <div class="qr-header">
        <div class="qr-icon-wrap">
          <svg class="qr-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z" fill="white"/>
          </svg>
        </div>
        <h1 class="qr-title">Mark Attendance</h1>
        <p class="qr-subtitle">Confirm your presence for this lecture</p>
      </div>

      <div class="qr-student-badge">
        <div class="qr-student-name"><?php echo htmlspecialchars($studentName, ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="qr-student-code"><?php echo htmlspecialchars($studentCode, ENT_QUOTES, 'UTF-8'); ?></div>
      </div>

      <form id="qrForm" class="qr-form">
        <input type="hidden" id="token" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">

        <div class="qr-form-field">
          <label for="studentCode" class="qr-label">Student ID</label>
          <input 
            type="text" 
            id="studentCode" 
            name="student_code" 
            class="qr-input" 
            value="<?php echo htmlspecialchars($studentCode, ENT_QUOTES, 'UTF-8'); ?>"
            placeholder="e.g., MG12345"
            pattern="[Mm][Gg]\d+"
            required
            autocomplete="off"
          />
          <p class="qr-helper">Verify your student ID before submitting</p>
        </div>

        <button type="submit" class="qr-submit" id="submitBtn">
          <span>Confirm Attendance</span>
        </button>

        <div id="statusMsg" class="qr-status"></div>
      </form>
    </div>
  </div>

  <script>
    (function() {
      'use strict';

      const form = document.getElementById('qrForm');
      const input = document.getElementById('studentCode');
      const submitBtn = document.getElementById('submitBtn');
      const statusMsg = document.getElementById('statusMsg');
      const token = document.getElementById('token').value;

      function showStatus(msg, type) {
        statusMsg.textContent = msg;
        statusMsg.className = 'qr-status ' + type;
      }

      function showSuccessView(name, code) {
        const card = document.querySelector('.qr-card');
        card.innerHTML = `
          <div class="qr-success-view">
            <div class="qr-success-icon-big">✅</div>
            <div class="qr-success-title">Attendance Recorded!</div>
            <div class="qr-success-info">
              <div class="qr-success-name">${name}</div>
              <div class="qr-success-code">${code}</div>
              <div class="qr-success-status">✓ PRESENT</div>
            </div>
            <a href="students.php" class="qr-return-btn">Return to Portal</a>
          </div>
        `;
      }

      form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const code = input.value.trim().toUpperCase();

        if (!code) {
          showStatus('Please enter your student ID', 'error');
          return;
        }

        if (!/^MG\d+$/i.test(code)) {
          showStatus('Invalid format. Must be MGxxxx', 'error');
          return;
        }

        submitBtn.disabled = true;
        showStatus('Submitting attendance...', 'loading');

        try {
          const fd = new FormData();
          fd.append('token', token);
          fd.append('student_code', code);

          const res = await fetch('php/student_submit_qr_attendance.php', {
            method: 'POST',
            body: fd
          });

          const data = await res.json();

          if (data.success) {
            showSuccessView(
              data.data.student_name || 'Student',
              data.data.student_code || code
            );
          } else {
            showStatus(data.error || 'Failed to submit attendance', 'error');
            submitBtn.disabled = false;
          }
        } catch (err) {
          console.error('Error:', err);
          showStatus('Network error. Please try again.', 'error');
          submitBtn.disabled = false;
        }
      });

      input.addEventListener('input', () => {
        input.value = input.value.toUpperCase();
      });
    })();
  </script>
</body>
</html>
