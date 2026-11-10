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
    :root {
      --qr-primary: rgba(18, 72, 200, 1);
      --qr-primary-rgb: 18, 72, 200;
      --qr-secondary: rgba(112, 80, 255, 1);
      --qr-success: #48bb78;
      --qr-error: #ff6b6b;
    }
    
    html[data-theme="dark"] {
      --qr-bg: #0a0e1a;
      --qr-text: #e6eeff;
      --qr-card-bg: #151b2e;
      --qr-surface: #1a2139;
      --qr-border: rgba(255, 255, 255, 0.08);
      --qr-muted: rgba(230, 238, 255, 0.55);
      --qr-input-bg: #0f1623;
      --qr-input-border: rgba(255, 255, 255, 0.15);
    }
    
    html[data-theme="light"] {
      --qr-bg: #f5f8ff;
      --qr-text: #0a0e1a;
      --qr-card-bg: #ffffff;
      --qr-surface: #f0f4ff;
      --qr-border: rgba(18, 72, 200, 0.12);
      --qr-muted: rgba(10, 14, 26, 0.6);
      --qr-input-bg: #ffffff;
      --qr-input-border: rgba(18, 72, 200, 0.25);
    }
    
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    
    body { 
      background: var(--qr-bg);
      color: var(--qr-text);
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px;
      position: relative;
      overflow-x: hidden;
    }
    
    body::before {
      content: '';
      position: fixed;
      top: -50%;
      right: -20%;
      width: 600px;
      height: 600px;
      background: radial-gradient(circle, rgba(var(--qr-primary-rgb), 0.08) 0%, transparent 70%);
      border-radius: 50%;
      pointer-events: none;
      z-index: 0;
    }
    
    body::after {
      content: '';
      position: fixed;
      bottom: -40%;
      left: -15%;
      width: 500px;
      height: 500px;
      background: radial-gradient(circle, rgba(112, 80, 255, 0.06) 0%, transparent 70%);
      border-radius: 50%;
      pointer-events: none;
      z-index: 0;
    }
    
    .qr-container {
      width: 100%;
      max-width: 440px;
      position: relative;
      z-index: 1;
    }
    
    .qr-card {
      background: var(--qr-card-bg);
      border: 1px solid var(--qr-border);
      border-radius: 24px;
      padding: 48px 36px;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15),
                  0 0 0 1px rgba(var(--qr-primary-rgb), 0.05);
      position: relative;
      backdrop-filter: blur(10px);
    }
    
    html[data-theme="light"] .qr-card {
      box-shadow: 0 20px 60px rgba(18, 72, 200, 0.08),
                  0 0 0 1px rgba(var(--qr-primary-rgb), 0.08);
    }
    
    .qr-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 50%;
      transform: translateX(-50%);
      width: 60%;
      height: 4px;
      background: linear-gradient(90deg, 
        transparent 0%, 
        rgba(var(--qr-primary-rgb), 0.8) 30%,
        rgba(112, 80, 255, 0.8) 70%,
        transparent 100%);
      border-radius: 0 0 4px 4px;
    }
    
    .qr-header {
      text-align: center;
      margin-bottom: 36px;
    }
    
    .qr-icon-wrap {
      width: 80px;
      height: 80px;
      margin: 0 auto 20px;
      background: linear-gradient(135deg, 
        rgba(var(--qr-primary-rgb), 1) 0%, 
        rgba(112, 80, 255, 0.9) 100%);
      border-radius: 20px;
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 8px 24px rgba(var(--qr-primary-rgb), 0.35),
                  0 0 0 4px rgba(var(--qr-primary-rgb), 0.1);
      animation: iconFloat 3s ease-in-out infinite;
    }
    
    @keyframes iconFloat {
      0%, 100% { transform: translateY(0px); }
      50% { transform: translateY(-8px); }
    }
    
    .qr-icon {
      font-size: 40px;
      color: white;
      line-height: 1;
    }
    
    .qr-title {
      font-size: 28px;
      font-weight: 700;
      color: var(--qr-text);
      margin: 0 0 8px 0;
      letter-spacing: -0.5px;
    }
    
    .qr-subtitle {
      font-size: 15px;
      color: var(--qr-muted);
      font-weight: 500;
    }
    
    .qr-student-badge {
      background: var(--qr-surface);
      border: 1px solid var(--qr-border);
      border-radius: 16px;
      padding: 20px 24px;
      margin-bottom: 32px;
      text-align: center;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      position: relative;
      overflow: hidden;
    }
    
    .qr-student-badge::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, 
        transparent, 
        rgba(var(--qr-primary-rgb), 0.05), 
        transparent);
      transition: left 0.6s;
    }
    
    .qr-student-badge:hover::before {
      left: 100%;
    }
    
    .qr-student-name {
      font-size: 19px;
      font-weight: 700;
      color: var(--qr-text);
      margin-bottom: 6px;
    }
    
    .qr-student-code {
      font-size: 15px;
      font-weight: 600;
      color: var(--qr-primary);
      letter-spacing: 1px;
      font-family: 'Courier New', monospace;
    }
    
    .qr-form-field {
      margin-bottom: 28px;
    }
    
    .qr-label {
      display: block;
      font-size: 14px;
      font-weight: 600;
      color: var(--qr-text);
      margin-bottom: 10px;
      letter-spacing: 0.3px;
    }
    
    .qr-input {
      width: 100%;
      padding: 16px 20px;
      font-size: 17px;
      font-weight: 600;
      background: var(--qr-input-bg);
      border: 2px solid var(--qr-input-border);
      border-radius: 14px;
      color: var(--qr-text);
      outline: none;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      text-transform: uppercase;
      letter-spacing: 1.5px;
      font-family: 'Courier New', monospace;
    }
    
    .qr-input:focus {
      border-color: var(--qr-primary);
      box-shadow: 0 0 0 4px rgba(var(--qr-primary-rgb), 0.15);
      transform: translateY(-2px);
    }
    
    .qr-input::placeholder {
      text-transform: none;
      letter-spacing: normal;
      font-weight: 400;
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }
    
    .qr-helper {
      font-size: 13px;
      color: var(--qr-muted);
      margin-top: 8px;
      display: flex;
      align-items: center;
      gap: 6px;
    }
    
    .qr-helper::before {
      content: 'ℹ️';
      font-size: 12px;
    }
    
    .qr-submit {
      width: 100%;
      padding: 18px 24px;
      font-size: 17px;
      font-weight: 700;
      color: white;
      background: linear-gradient(135deg, 
        rgba(var(--qr-primary-rgb), 1) 0%, 
        rgba(112, 80, 255, 0.9) 100%);
      border: none;
      border-radius: 14px;
      cursor: pointer;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      box-shadow: 0 6px 20px rgba(var(--qr-primary-rgb), 0.3);
      position: relative;
      overflow: hidden;
    }
    
    .qr-submit::before {
      content: '';
      position: absolute;
      top: 50%;
      left: 50%;
      width: 0;
      height: 0;
      background: rgba(255, 255, 255, 0.2);
      border-radius: 50%;
      transform: translate(-50%, -50%);
      transition: width 0.6s, height 0.6s;
    }
    
    .qr-submit:hover {
      transform: translateY(-3px);
      box-shadow: 0 10px 30px rgba(var(--qr-primary-rgb), 0.4);
    }
    
    .qr-submit:hover::before {
      width: 300px;
      height: 300px;
    }
    
    .qr-submit:active {
      transform: translateY(-1px);
    }
    
    .qr-submit:disabled {
      opacity: 0.6;
      cursor: not-allowed;
      transform: none;
    }
    
    .qr-status {
      margin-top: 20px;
      padding: 16px 20px;
      border-radius: 12px;
      font-size: 14px;
      font-weight: 600;
      text-align: center;
      display: none;
      animation: slideUp 0.4s cubic-bezier(0.4, 0, 0.2, 1);
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
      background: rgba(72, 187, 120, 0.12);
      color: var(--qr-success);
      border: 1px solid rgba(72, 187, 120, 0.3);
      display: block;
    }
    
    .qr-status.error {
      background: rgba(255, 107, 107, 0.12);
      color: var(--qr-error);
      border: 1px solid rgba(255, 107, 107, 0.3);
      display: block;
    }
    
    .qr-status.loading {
      background: rgba(var(--qr-primary-rgb), 0.12);
      color: var(--qr-primary);
      border: 1px solid rgba(var(--qr-primary-rgb), 0.3);
      display: block;
    }
    
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
      font-size: 72px;
      margin-bottom: 24px;
      animation: successBounce 0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55);
    }
    
    @keyframes successBounce {
      0%, 100% { transform: scale(1); }
      50% { transform: scale(1.2); }
    }
    
    .qr-success-title {
      font-size: 26px;
      font-weight: 700;
      color: var(--qr-success);
      margin-bottom: 20px;
    }
    
    .qr-success-info {
      background: var(--qr-surface);
      border: 1px solid var(--qr-border);
      border-radius: 14px;
      padding: 20px;
      margin: 24px 0;
    }
    
    .qr-success-name {
      font-size: 20px;
      font-weight: 700;
      color: var(--qr-text);
      margin-bottom: 8px;
    }
    
    .qr-success-code {
      font-size: 16px;
      font-weight: 600;
      color: var(--qr-primary);
      font-family: 'Courier New', monospace;
      letter-spacing: 1px;
      margin-bottom: 16px;
    }
    
    .qr-success-status {
      display: inline-block;
      padding: 10px 24px;
      background: rgba(72, 187, 120, 0.15);
      color: var(--qr-success);
      border-radius: 20px;
      font-size: 15px;
      font-weight: 700;
      border: 1px solid rgba(72, 187, 120, 0.3);
    }
    
    .qr-return-btn {
      display: inline-block;
      margin-top: 28px;
      padding: 14px 32px;
      background: var(--qr-primary);
      color: white;
      text-decoration: none;
      border-radius: 12px;
      font-weight: 600;
      font-size: 15px;
      transition: all 0.3s;
      box-shadow: 0 4px 12px rgba(var(--qr-primary-rgb), 0.25);
    }
    
    .qr-return-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(var(--qr-primary-rgb), 0.35);
    }
    
    @media (max-width: 500px) {
      .qr-card {
        padding: 36px 24px;
      }
      
      .qr-title {
        font-size: 24px;
      }
      
      body::before,
      body::after {
        display: none;
      }
    }
  </style>
</head>
<body>
  <div class="qr-container">
    <div class="qr-card">
      <div class="qr-header">
        <div class="qr-icon-wrap">
          <span class="qr-icon">✓</span>
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
