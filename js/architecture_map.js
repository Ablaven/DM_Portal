/* ─────────────────────────────────────────────────────────────
   Architecture Map — Interactive Portal Schema Explorer
   Depends on: core.js (shared utilities)
   ───────────────────────────────────────────────────────────── */

(function () {
'use strict';

/* ============================================================
   DATA MODEL — complete schema, pages, endpoints, roles
   ============================================================ */

const MODULES = {
  auth:       { label: 'Auth & Users',   color: '#5b8cff' },
  academic:   { label: 'Academic',       color: '#a78bfa' },
  schedule:   { label: 'Scheduling',     color: '#22d3ee' },
  cancel:     { label: 'Cancellations',  color: '#fb923c' },
  attendance: { label: 'Attendance',     color: '#34d399' },
  evaluation: { label: 'Evaluation',     color: '#ec4899' },
  facility:   { label: 'Facilities',     color: '#fbbf24' },
  system:     { label: 'System',         color: '#94a3b8' },
};

const TABLES = [
  { id:'portal_users', label:'portal_users', module:'auth',
    cols:['id','username','password_hash','role','doctor_id FK','student_id FK','allowed_pages JSON','is_active','master_key_used'],
    fks:[{to:'doctors',col:'doctor_id'},{to:'students',col:'student_id'}]},
  { id:'admins', label:'admins (legacy)', module:'auth', cols:['id','username','password_hash'], fks:[]},
  { id:'students', label:'students', module:'auth',
    cols:['id','student_id','name','email','year_level','semester','program'], fks:[]},
  { id:'doctors', label:'doctors', module:'auth',
    cols:['id','name','email','type','specialty'], fks:[]},

  { id:'academic_years', label:'academic_years', module:'academic',
    cols:['id','label'], fks:[]},
  { id:'terms', label:'terms', module:'academic',
    cols:['id','academic_year_id FK','label','is_active','current_week_id'],
    fks:[{to:'academic_years',col:'academic_year_id'}]},
  { id:'weeks', label:'weeks', module:'academic',
    cols:['id','term_id FK','week_number','start_date','end_date','type','is_started','is_stopped'],
    fks:[{to:'terms',col:'term_id'}]},
  { id:'courses', label:'courses', module:'academic',
    cols:['id','name','code','year_level','semester','total_hours','coefficient'], fks:[]},

  { id:'doctor_schedules', label:'doctor_schedules', module:'schedule',
    cols:['id','week_id FK','doctor_id FK','course_id FK','day','slot_number','room','extra_minutes','counts_towards_hours'],
    fks:[{to:'weeks',col:'week_id'},{to:'doctors',col:'doctor_id'},{to:'courses',col:'course_id'}]},
  { id:'course_doctors', label:'course_doctors', module:'schedule',
    cols:['course_id FK','doctor_id FK'],
    fks:[{to:'courses',col:'course_id'},{to:'doctors',col:'doctor_id'}]},
  { id:'course_doctor_hours', label:'course_doctor_hours', module:'schedule',
    cols:['course_id FK','doctor_id FK','allocated_hours'],
    fks:[{to:'courses',col:'course_id'},{to:'doctors',col:'doctor_id'}]},
  { id:'doctor_year_colors', label:'doctor_year_colors', module:'schedule',
    cols:['doctor_id FK','year_level','color'],
    fks:[{to:'doctors',col:'doctor_id'}]},
  { id:'availability', label:'availability', module:'schedule',
    cols:['doctor_id FK','day','slot_number'],
    fks:[{to:'doctors',col:'doctor_id'}]},
  { id:'unavailability', label:'unavailability', module:'schedule',
    cols:['doctor_id FK','date','reason'],
    fks:[{to:'doctors',col:'doctor_id'}]},

  { id:'cancelled_doctor_schedules', label:'cancelled_doctor_schedules', module:'cancel',
    cols:['backup of removed slots before cancellation'], fks:[]},
  { id:'doctor_day_cancellations', label:'doctor_day_cancellations', module:'cancel',
    cols:['doctor_id FK','week_id FK','day','reason'],
    fks:[{to:'doctors',col:'doctor_id'},{to:'weeks',col:'week_id'}]},
  { id:'doctor_slot_cancellations', label:'doctor_slot_cancellations', module:'cancel',
    cols:['doctor_id FK','week_id FK','day','slot_number','reason'],
    fks:[{to:'doctors',col:'doctor_id'},{to:'weeks',col:'week_id'}]},

  { id:'attendance_records', label:'attendance_records', module:'attendance',
    cols:['id','schedule_id FK','student_id FK','status','recorded_at'],
    fks:[{to:'doctor_schedules',col:'schedule_id'},{to:'students',col:'student_id'}]},

  { id:'evaluation_categories', label:'evaluation_categories', module:'evaluation',
    cols:['id','name'], fks:[]},
  { id:'evaluation_configs', label:'evaluation_configs', module:'evaluation',
    cols:['id','course_id FK','year_level','semester','academic_year_id FK'],
    fks:[{to:'courses',col:'course_id'},{to:'academic_years',col:'academic_year_id'}]},
  { id:'evaluation_config_items', label:'evaluation_config_items', module:'evaluation',
    cols:['id','config_id FK','category_id FK','label','marks','is_attendance','is_participation','allow_split'],
    fks:[{to:'evaluation_configs',col:'config_id'},{to:'evaluation_categories',col:'category_id'}]},
  { id:'evaluation_grades', label:'evaluation_grades', module:'evaluation',
    cols:['id','config_id FK','student_id FK','final_score','attendance_score','graded_at'],
    fks:[{to:'evaluation_configs',col:'config_id'},{to:'students',col:'student_id'}]},
  { id:'evaluation_grade_items', label:'evaluation_grade_items', module:'evaluation',
    cols:['id','grade_id FK','item_id FK','score'],
    fks:[{to:'evaluation_grades',col:'grade_id'},{to:'evaluation_config_items',col:'item_id'}]},

  { id:'floors', label:'floors', module:'facility', cols:['id','name'], fks:[]},
  { id:'rooms', label:'rooms', module:'facility',
    cols:['id','floor_id FK','name','code'],
    fks:[{to:'floors',col:'floor_id'}]},
  { id:'audit_log', label:'audit_log', module:'system',
    cols:['id','user_id FK','action','target','details','created_at'],
    fks:[{to:'portal_users',col:'user_id'}]},
];

const PAGES = [
  { id:'index.php', label:'Course Dashboard', roles:['admin','management'], module:'academic',
    apis:['auth_me','get_courses','get_hours_report','get_doctor_type_hours_summary'] },
  { id:'schedule_builder.php', label:'Schedule Builder', roles:['admin','management'], module:'schedule',
    apis:['auth_me','get_schedule','manage_schedule','get_doctors','get_courses','get_weeks','get_terms',
          'clone_week','get_doctor_year_colors','check_slot_conflict','get_student_schedule',
          'set_doctor_cancellation','clear_doctor_cancellation','set_doctor_slot_cancellation',
          'clear_doctor_slot_cancellation','get_doctor_availability','email_doctor_schedule'] },
  { id:'admin_courses.php', label:'Course Management', roles:['admin','management'], module:'academic',
    apis:['auth_me','get_courses','add_course','update_course','delete_course',
          'set_course_doctors','get_doctors','set_course_doctor_hours','get_course_doctor_hours','get_hours_report'] },
  { id:'admin_doctors.php', label:'Doctor Management', roles:['admin','management'], module:'auth',
    apis:['auth_me','get_doctors','add_doctor','update_doctor','delete_doctor',
          'get_doctor_year_colors','set_doctor_year_colors'] },
  { id:'admin_students.php', label:'Student Management', roles:['admin','management'], module:'auth',
    apis:['auth_me','get_students','add_student','update_student','delete_student'] },
  { id:'admin_users.php', label:'User Accounts', roles:['admin'], module:'auth',
    apis:['auth_me','admin_users_list','admin_users_create','admin_users_update',
          'admin_users_delete','admin_users_set_password','admin_users_toggle_active'] },
  { id:'doctor.php', label:'Doctor Schedule', roles:['admin','management','teacher'], module:'schedule',
    apis:['auth_me','get_schedule','get_weeks','get_terms','get_doctors','get_doctor_year_colors',
          'export_doctor_week_xls','email_doctor_schedule'] },
  { id:'students.php', label:'Student Schedule', roles:['admin','management','student'], module:'schedule',
    apis:['auth_me','get_student_schedule','get_weeks','get_terms'] },
  { id:'attendance.php', label:'Attendance', roles:['admin','management','teacher'], module:'attendance',
    apis:['auth_me','get_attendance_grid','get_attendance','set_attendance','get_students',
          'get_current_slot','copy_attendance_next_lecture','get_attendance_courses','export_attendance_xls'] },
  { id:'attendance_report.php', label:'Attendance Report', roles:['admin','management'], module:'attendance',
    apis:['auth_me','get_attendance_reports_summary'] },
  { id:'evaluation.php', label:'Evaluation', roles:['admin','management','teacher'], module:'evaluation',
    apis:['auth_me','get_evaluation_config','set_evaluation_config','get_evaluation_grades',
          'set_evaluation_grade','get_evaluation_categories','add_evaluation_category',
          'get_courses','get_students','export_evaluation_grades_xls'] },
  { id:'evaluation_reports.php', label:'Evaluation Reports', roles:['admin','management'], module:'evaluation',
    apis:['auth_me','get_evaluation_reports_summary','get_evaluation_courses',
          'export_evaluation_summary_xls','export_evaluation_summary_all_xls'] },
  { id:'hours_report.php', label:'Reports Hub', roles:['admin','management','teacher'], module:'system',
    apis:['auth_me','get_hours_report','get_hours_report_semester_summary'] },
  { id:'hours_report_detail.php', label:'Hours Detail', roles:['admin','management'], module:'system',
    apis:['auth_me','get_hours_report'] },
  { id:'student_dashboard.php', label:'Student Dashboard', roles:['admin','management','student'], module:'evaluation',
    apis:['auth_me','get_student_evaluation','get_student_profile'] },
  { id:'profile.php', label:'Profile', roles:['admin','management','teacher','student'], module:'auth',
    apis:['auth_me','auth_change_password'] },
  { id:'dashboard.php', label:'Admin Dashboard', roles:['admin','management'], module:'system', apis:['auth_me'] },
  { id:'login.php', label:'Login', roles:['*'], module:'auth', apis:['auth_login'] },
  { id:'ablaven.php', label:'Easter Egg', roles:['*'], module:'system', apis:['easter_egg_entry'] },
];

/* ============================================================
   GRAPH NODE SETUP
   ============================================================ */
const DPR = window.devicePixelRatio || 1;
const canvas = document.getElementById('graphCanvas');
const ctx = canvas.getContext('2d');
let W, H;

// Camera
let cam = { x: 0, y: 0, zoom: 1 };

// Graph state
let nodes = [];
let edges = [];
let currentView = 'er';
let searchQuery = '';
let hoveredNode = null;
let selectedNode = null;
let dragNode = null;
let isPanning = false;
let panStart = { x:0, y:0 };
let camStart = { x:0, y:0 };

function resize() {
  W = window.innerWidth; H = window.innerHeight;
  canvas.width = W * DPR; canvas.height = H * DPR;
  canvas.style.width = W + 'px'; canvas.style.height = H + 'px';
  ctx.setTransform(DPR, 0, 0, DPR, 0, 0);
  const mc = document.getElementById('minimapCanvas');
  mc.width = 160 * DPR; mc.height = 100 * DPR;
}
window.addEventListener('resize', resize);
resize();

/* ── Build nodes for ER view ── */
function buildERGraph() {
  nodes = []; edges = [];
  const moduleGroups = {};
  TABLES.forEach((t, i) => {
    if (!moduleGroups[t.module]) moduleGroups[t.module] = [];
    moduleGroups[t.module].push(i);
  });

  const moduleKeys = Object.keys(moduleGroups);
  const cx = W / 2, cy = H / 2;
  const ringR = Math.min(W, H) * 0.30;

  moduleKeys.forEach((mk, mi) => {
    const angle0 = (mi / moduleKeys.length) * Math.PI * 2 - Math.PI / 2;
    const group = moduleGroups[mk];
    const subR = 60 + group.length * 18;
    group.forEach((ti, gi) => {
      const subAngle = (gi / group.length) * Math.PI * 2 - Math.PI / 2;
      const t = TABLES[ti];
      nodes.push({
        id: t.id, label: t.label, module: t.module,
        cols: t.cols, fks: t.fks, type: 'table',
        x: cx + Math.cos(angle0) * ringR + Math.cos(subAngle) * subR,
        y: cy + Math.sin(angle0) * ringR + Math.sin(subAngle) * subR,
        vx: 0, vy: 0,
        w: Math.max(140, t.label.length * 8.5 + 30), h: 38,
        pinned: false
      });
    });
  });

  TABLES.forEach(t => {
    t.fks.forEach(fk => {
      edges.push({ from: t.id, to: fk.to, label: fk.col });
    });
  });
}

/* ── Build nodes for Pages view ── */
function buildPagesGraph() {
  nodes = []; edges = [];
  const cx = W / 2, cy = H / 2;

  PAGES.forEach((p, i) => {
    const angle = (i / PAGES.length) * Math.PI * 2 - Math.PI / 2;
    const r = Math.min(W, H) * 0.25;
    nodes.push({
      id: p.id, label: p.label, module: p.module,
      roles: p.roles, apis: p.apis, type: 'page',
      x: cx + Math.cos(angle) * r,
      y: cy + Math.sin(angle) * r,
      vx: 0, vy: 0,
      w: Math.max(130, p.label.length * 8 + 30), h: 36,
      pinned: false
    });
  });

  const apiSet = new Set();
  PAGES.forEach(p => p.apis.forEach(a => apiSet.add(a)));
  const apis = [...apiSet];
  apis.forEach((a, i) => {
    const angle = (i / apis.length) * Math.PI * 2 - Math.PI / 2;
    const r = Math.min(W, H) * 0.10;
    nodes.push({
      id: 'api_' + a, label: a, module: 'system', type: 'api',
      x: cx + Math.cos(angle) * r,
      y: cy + Math.sin(angle) * r,
      vx: 0, vy: 0,
      w: Math.max(100, a.length * 6.5 + 20), h: 28,
      pinned: false
    });
  });

  PAGES.forEach(p => {
    p.apis.forEach(a => {
      edges.push({ from: p.id, to: 'api_' + a, label: '' });
    });
  });
}

/* ============================================================
   PHYSICS — Force-directed layout
   ============================================================ */
const PHYSICS = {
  repulsion: 12000,
  attraction: 0.0004,
  damping: 0.88,
  centerGravity: 0.0003,
  maxVelocity: 12,
  edgeLength: 220
};

function stepPhysics() {
  const N = nodes.length;
  for (let i = 0; i < N; i++) {
    if (nodes[i].pinned) continue;
    let fx = 0, fy = 0;
    for (let j = 0; j < N; j++) {
      if (i === j) continue;
      const dx = nodes[i].x - nodes[j].x;
      const dy = nodes[i].y - nodes[j].y;
      const dist = Math.sqrt(dx * dx + dy * dy) || 1;
      const force = PHYSICS.repulsion / (dist * dist);
      fx += (dx / dist) * force;
      fy += (dy / dist) * force;
    }
    const dcx = W / 2 - nodes[i].x;
    const dcy = H / 2 - nodes[i].y;
    fx += dcx * PHYSICS.centerGravity;
    fy += dcy * PHYSICS.centerGravity;

    nodes[i].vx = (nodes[i].vx + fx) * PHYSICS.damping;
    nodes[i].vy = (nodes[i].vy + fy) * PHYSICS.damping;

    const speed = Math.sqrt(nodes[i].vx ** 2 + nodes[i].vy ** 2);
    if (speed > PHYSICS.maxVelocity) {
      nodes[i].vx = (nodes[i].vx / speed) * PHYSICS.maxVelocity;
      nodes[i].vy = (nodes[i].vy / speed) * PHYSICS.maxVelocity;
    }
  }

  edges.forEach(e => {
    const a = nodes.find(n => n.id === e.from);
    const b = nodes.find(n => n.id === e.to);
    if (!a || !b) return;
    const dx = b.x - a.x;
    const dy = b.y - a.y;
    const dist = Math.sqrt(dx * dx + dy * dy) || 1;
    const force = (dist - PHYSICS.edgeLength) * PHYSICS.attraction;
    const fx = (dx / dist) * force;
    const fy = (dy / dist) * force;
    if (!a.pinned) { a.vx += fx; a.vy += fy; }
    if (!b.pinned) { b.vx -= fx; b.vy -= fy; }
  });

  nodes.forEach(n => {
    if (n.pinned) return;
    n.x += n.vx;
    n.y += n.vy;
  });
}

/* ============================================================
   RENDERING
   ============================================================ */
function screenToWorld(sx, sy) {
  return { x: (sx - W / 2) / cam.zoom + cam.x, y: (sy - H / 2) / cam.zoom + cam.y };
}
function worldToScreen(wx, wy) {
  return { x: (wx - cam.x) * cam.zoom + W / 2, y: (wy - cam.y) * cam.zoom + H / 2 };
}

function drawEdge(e, time) {
  const a = nodes.find(n => n.id === e.from);
  const b = nodes.find(n => n.id === e.to);
  if (!a || !b) return;
  const sa = worldToScreen(a.x, a.y);
  const sb = worldToScreen(b.x, b.y);

  const isHighlighted = selectedNode && (selectedNode.id === e.from || selectedNode.id === e.to);
  const isSearchMatch = searchQuery && (
    a.label.toLowerCase().includes(searchQuery) || b.label.toLowerCase().includes(searchQuery));
  const dimmed = (selectedNode || searchQuery) && !isHighlighted && !isSearchMatch;

  const modColor = MODULES[a.module]?.color || '#5b8cff';

  ctx.save();
  ctx.globalAlpha = dimmed ? 0.06 : (isHighlighted ? 0.7 : 0.2);

  const mx = (sa.x + sb.x) / 2;
  const my = (sa.y + sb.y) / 2 - 30 * cam.zoom;
  ctx.beginPath();
  ctx.moveTo(sa.x, sa.y);
  ctx.quadraticCurveTo(mx, my, sb.x, sb.y);
  ctx.strokeStyle = isHighlighted ? modColor : 'rgba(100,160,255,0.5)';
  ctx.lineWidth = isHighlighted ? 2.5 : 1.2;
  ctx.stroke();

  if (!dimmed) {
    const t = ((time * 0.0004 + edges.indexOf(e) * 0.15) % 1);
    const px = (1-t)*(1-t)*sa.x + 2*(1-t)*t*mx + t*t*sb.x;
    const py = (1-t)*(1-t)*sa.y + 2*(1-t)*t*my + t*t*sb.y;
    ctx.beginPath();
    ctx.arc(px, py, isHighlighted ? 3.5 : 2, 0, Math.PI * 2);
    ctx.fillStyle = modColor;
    ctx.globalAlpha = isHighlighted ? 0.9 : 0.5;
    ctx.fill();
  }

  ctx.restore();
}

function drawNode(n, time) {
  const s = worldToScreen(n.x, n.y);
  const hw = n.w / 2 * cam.zoom;
  const hh = n.h / 2 * cam.zoom;
  const modColor = MODULES[n.module]?.color || '#5b8cff';

  const isSearch = searchQuery && n.label.toLowerCase().includes(searchQuery);
  const isSelected = selectedNode && selectedNode.id === n.id;
  const isConnected = selectedNode && edges.some(e =>
    (e.from === selectedNode.id && e.to === n.id) || (e.to === selectedNode.id && e.from === n.id));
  const dimmed = (selectedNode && !isSelected && !isConnected) ||
                 (searchQuery && !isSearch);
  const isHovered = hoveredNode && hoveredNode.id === n.id;

  ctx.save();
  ctx.globalAlpha = dimmed ? 0.12 : 1;

  if ((isSelected || isHovered) && !dimmed) {
    ctx.shadowColor = modColor;
    ctx.shadowBlur = 25;
  }

  const rx = s.x - hw; const ry = s.y - hh;
  const r = 10 * cam.zoom;
  ctx.beginPath();
  ctx.roundRect(rx, ry, hw * 2, hh * 2, r);
  ctx.fillStyle = isSelected ? 'rgba(30,50,90,0.95)' : 'rgba(14,22,44,0.88)';
  ctx.fill();
  ctx.strokeStyle = isSelected ? modColor : (isHovered ? 'rgba(100,160,255,0.6)' : 'rgba(100,160,255,0.2)');
  ctx.lineWidth = isSelected ? 2 : 1;
  ctx.stroke();

  ctx.shadowBlur = 0;

  ctx.beginPath();
  ctx.roundRect(rx, ry, 4 * cam.zoom, hh * 2, [r, 0, 0, r]);
  ctx.fillStyle = modColor;
  ctx.fill();

  const fontSize = (n.type === 'api' ? 9.5 : 11.5) * cam.zoom;
  ctx.font = `600 ${fontSize}px 'Segoe UI', system-ui, sans-serif`;
  ctx.textAlign = 'center';
  ctx.textBaseline = 'middle';
  ctx.fillStyle = isSelected ? '#fff' : (dimmed ? 'rgba(200,210,240,0.4)' : '#e8eeff');
  ctx.fillText(n.label, s.x + 2 * cam.zoom, s.y);

  if (n.type === 'api') {
    const badgeW = 22 * cam.zoom;
    ctx.font = `700 ${7.5 * cam.zoom}px 'Segoe UI', system-ui, sans-serif`;
    ctx.fillStyle = 'rgba(91,140,255,0.25)';
    ctx.beginPath();
    ctx.roundRect(s.x + hw - badgeW - 4 * cam.zoom, s.y - 6 * cam.zoom, badgeW, 12 * cam.zoom, 4 * cam.zoom);
    ctx.fill();
    ctx.fillStyle = '#5b8cff';
    ctx.fillText('API', s.x + hw - badgeW / 2 - 4 * cam.zoom, s.y);
  } else if (n.type === 'page') {
    const badgeW = 28 * cam.zoom;
    ctx.font = `700 ${7 * cam.zoom}px 'Segoe UI', system-ui, sans-serif`;
    ctx.fillStyle = 'rgba(52,211,153,0.2)';
    ctx.beginPath();
    ctx.roundRect(s.x + hw - badgeW - 4 * cam.zoom, s.y - 6 * cam.zoom, badgeW, 12 * cam.zoom, 4 * cam.zoom);
    ctx.fill();
    ctx.fillStyle = '#34d399';
    ctx.fillText('PAGE', s.x + hw - badgeW / 2 - 4 * cam.zoom, s.y);
  }

  ctx.restore();
}

function drawModuleClusters() {
  if (currentView !== 'er') return;
  const moduleGroups = {};
  nodes.forEach(n => {
    if (!moduleGroups[n.module]) moduleGroups[n.module] = [];
    moduleGroups[n.module].push(n);
  });

  Object.entries(moduleGroups).forEach(([mod, group]) => {
    if (group.length < 2) return;
    const color = MODULES[mod]?.color || '#5b8cff';
    let cx = 0, cy = 0;
    group.forEach(n => { cx += n.x; cy += n.y; });
    cx /= group.length; cy /= group.length;
    let maxR = 0;
    group.forEach(n => {
      const d = Math.sqrt((n.x - cx) ** 2 + (n.y - cy) ** 2);
      if (d > maxR) maxR = d;
    });
    maxR += 80;

    const sc = worldToScreen(cx, cy);
    const sr = maxR * cam.zoom;

    ctx.save();
    ctx.beginPath();
    ctx.arc(sc.x, sc.y, sr, 0, Math.PI * 2);
    ctx.fillStyle = color.replace(')', ',0.04)').replace('rgb', 'rgba');
    ctx.fill();
    ctx.strokeStyle = color.replace(')', ',0.12)').replace('rgb', 'rgba');
    ctx.lineWidth = 1;
    ctx.setLineDash([6, 6]);
    ctx.stroke();
    ctx.setLineDash([]);

    ctx.font = `700 ${11 * cam.zoom}px 'Segoe UI', system-ui, sans-serif`;
    ctx.textAlign = 'center';
    ctx.fillStyle = color.replace(')', ',0.4)').replace('rgb', 'rgba');
    ctx.fillText(MODULES[mod]?.label || mod, sc.x, sc.y - sr + 14 * cam.zoom);
    ctx.restore();
  });
}

function drawMinimap() {
  const mc = document.getElementById('minimapCanvas');
  const mctx = mc.getContext('2d');
  mctx.setTransform(DPR, 0, 0, DPR, 0, 0);
  mctx.clearRect(0, 0, 160, 100);

  if (!nodes.length) return;

  let minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
  nodes.forEach(n => {
    if (n.x < minX) minX = n.x;
    if (n.y < minY) minY = n.y;
    if (n.x > maxX) maxX = n.x;
    if (n.y > maxY) maxY = n.y;
  });
  const pad = 100;
  minX -= pad; minY -= pad; maxX += pad; maxY += pad;
  const scaleX = 160 / (maxX - minX);
  const scaleY = 100 / (maxY - minY);
  const s = Math.min(scaleX, scaleY);

  nodes.forEach(n => {
    const mx = (n.x - minX) * s;
    const my = (n.y - minY) * s;
    const color = MODULES[n.module]?.color || '#5b8cff';
    mctx.fillStyle = color;
    mctx.globalAlpha = 0.7;
    mctx.fillRect(mx - 1.5, my - 1, 3, 2);
  });

  const tl = screenToWorld(0, 60);
  const br = screenToWorld(W, H);
  mctx.globalAlpha = 1;
  mctx.strokeStyle = '#5b8cff';
  mctx.lineWidth = 1;
  mctx.strokeRect(
    (tl.x - minX) * s, (tl.y - minY) * s,
    (br.x - tl.x) * s, (br.y - tl.y) * s
  );
}

/* ============================================================
   ROLE ACCESS MATRIX
   ============================================================ */
function showRoleMatrix() {
  const panel = document.getElementById('archInfoPanel');
  const roles = ['admin', 'management', 'teacher', 'student'];
  let html = '<h3>Role \u2192 Page Access Matrix</h3>';
  html += '<table class="arch-matrix-table"><thead><tr><th>Page</th>';
  roles.forEach(r => { html += `<th>${r}</th>`; });
  html += '</tr></thead><tbody>';
  PAGES.forEach(p => {
    html += '<tr>';
    html += `<td class="page-name">${p.label}</td>`;
    roles.forEach(r => {
      const has = p.roles.includes(r) || p.roles.includes('*');
      html += `<td class="${has ? 'yes' : 'no'}">${has ? '\u2713' : '\u2212'}</td>`;
    });
    html += '</tr>';
  });
  html += '</tbody></table>';
  panel.innerHTML = html;
  panel.classList.add('show');
}

/* ============================================================
   LEGEND + STATS
   ============================================================ */
function updateLegend() {
  const legendEl = document.getElementById('archLegend');
  if (currentView === 'roles') { legendEl.innerHTML = ''; return; }
  let html = `<h3>${currentView === 'er' ? 'Table Modules' : 'Node Types'}</h3>`;
  if (currentView === 'er') {
    Object.entries(MODULES).forEach(([k, m]) => {
      html += `<div class="arch-legend-item"><div class="arch-legend-swatch" style="background:${m.color}"></div>${m.label}</div>`;
    });
  } else {
    html += `<div class="arch-legend-item"><div class="arch-legend-swatch" style="background:#34d399"></div>Pages</div>`;
    html += `<div class="arch-legend-item"><div class="arch-legend-swatch" style="background:#5b8cff"></div>API Endpoints</div>`;
  }
  legendEl.innerHTML = html;
}

function updateStats() {
  const el = document.getElementById('archStatsBar');
  const edgeCount = edges.length;
  el.innerHTML = `<div class="arch-stat"><b>${nodes.length}</b> nodes</div>` +
    `<div class="arch-stat"><b>${edgeCount}</b> connections</div>` +
    `<div class="arch-stat">Zoom <b>${(cam.zoom * 100).toFixed(0)}%</b></div>`;
}

/* ============================================================
   TOOLTIP
   ============================================================ */
function showTooltip(n, mx, my) {
  const tip = document.getElementById('archTooltip');
  let html = `<h4>${n.label}</h4>`;
  html += `<div style="margin-bottom:4px;color:${MODULES[n.module]?.color || '#5b8cff'};font-weight:600;font-size:.72rem">${MODULES[n.module]?.label || n.module}</div>`;

  if (n.type === 'table' && n.cols) {
    html += '<div style="margin-top:6px">';
    n.cols.forEach(c => {
      const isFK = c.includes('FK');
      const isJSON = c.includes('JSON');
      html += `<div class="col">${isFK ? '<b style="color:#a78bfa">\u{1F517} ' + c + '</b>' : isJSON ? '<b style="color:#fbbf24">{} ' + c + '</b>' : c}</div>`;
    });
    html += '</div>';
    if (n.fks && n.fks.length) {
      html += '<div class="fk">References: ' + n.fks.map(f => f.to).join(', ') + '</div>';
    }
  } else if (n.type === 'page') {
    html += `<div style="margin-top:4px;color:var(--muted)">Roles: ${n.roles.join(', ')}</div>`;
    html += `<div style="margin-top:2px;color:var(--muted)">APIs: ${n.apis?.length || 0} endpoints</div>`;
  }

  tip.innerHTML = html;
  tip.classList.add('show');

  const rect = tip.getBoundingClientRect();
  let left = mx + 16;
  let top = my - 10;
  if (left + rect.width > W - 20) left = mx - rect.width - 16;
  if (top + rect.height > H - 20) top = H - rect.height - 20;
  if (top < 60) top = 60;
  tip.style.left = left + 'px';
  tip.style.top = top + 'px';
}

function hideTooltip() {
  document.getElementById('archTooltip').classList.remove('show');
}

/* ============================================================
   INTERACTION
   ============================================================ */
function nodeAt(mx, my) {
  const w = screenToWorld(mx, my);
  for (let i = nodes.length - 1; i >= 0; i--) {
    const n = nodes[i];
    const hw = n.w / 2; const hh = n.h / 2;
    if (w.x >= n.x - hw && w.x <= n.x + hw && w.y >= n.y - hh && w.y <= n.y + hh) {
      return n;
    }
  }
  return null;
}

canvas.addEventListener('mousedown', e => {
  const n = nodeAt(e.clientX, e.clientY);
  if (n) {
    dragNode = n;
    dragNode.pinned = true;
    canvas.classList.add('grabbing');
  } else {
    isPanning = true;
    panStart = { x: e.clientX, y: e.clientY };
    camStart = { x: cam.x, y: cam.y };
    canvas.classList.add('grabbing');
  }
});

canvas.addEventListener('mousemove', e => {
  if (dragNode) {
    const w = screenToWorld(e.clientX, e.clientY);
    dragNode.x = w.x;
    dragNode.y = w.y;
    dragNode.vx = 0; dragNode.vy = 0;
    return;
  }
  if (isPanning) {
    cam.x = camStart.x - (e.clientX - panStart.x) / cam.zoom;
    cam.y = camStart.y - (e.clientY - panStart.y) / cam.zoom;
    return;
  }
  const n = nodeAt(e.clientX, e.clientY);
  hoveredNode = n;
  canvas.style.cursor = n ? 'pointer' : 'grab';
  if (n) {
    showTooltip(n, e.clientX, e.clientY);
  } else {
    hideTooltip();
  }
});

canvas.addEventListener('mouseup', () => {
  if (dragNode) {
    dragNode.pinned = false;
    dragNode = null;
  }
  isPanning = false;
  canvas.classList.remove('grabbing');
});

canvas.addEventListener('click', e => {
  if (dragNode) return;
  const n = nodeAt(e.clientX, e.clientY);
  selectedNode = (selectedNode && n && selectedNode.id === n.id) ? null : n;
});

canvas.addEventListener('wheel', e => {
  e.preventDefault();
  const factor = e.deltaY > 0 ? 0.92 : 1.08;
  cam.zoom = Math.max(0.15, Math.min(4, cam.zoom * factor));
}, { passive: false });

canvas.addEventListener('dblclick', () => {
  cam = { x: 0, y: 0, zoom: 1 };
  selectedNode = null;
});

// Search
const searchInput = document.getElementById('archSearchInput');
searchInput.addEventListener('input', e => {
  searchQuery = e.target.value.trim().toLowerCase();
});

// View tabs
document.querySelectorAll('.arch-view-tab').forEach(tab => {
  tab.addEventListener('click', () => {
    document.querySelectorAll('.arch-view-tab').forEach(t => t.classList.remove('active'));
    tab.classList.add('active');
    const view = tab.dataset.view;
    currentView = view;
    selectedNode = null;
    searchQuery = '';
    searchInput.value = '';
    cam = { x: 0, y: 0, zoom: 1 };

    const panel = document.getElementById('archInfoPanel');

    if (view === 'er') {
      buildERGraph();
      panel.classList.remove('show');
    } else if (view === 'pages') {
      buildPagesGraph();
      panel.classList.remove('show');
    } else if (view === 'roles') {
      showRoleMatrix();
      buildERGraph();
    }
    updateLegend();
  });
});

/* ============================================================
   ANIMATION LOOP
   ============================================================ */
function animate(time) {
  requestAnimationFrame(animate);

  for (let i = 0; i < 3; i++) stepPhysics();

  ctx.clearRect(0, 0, W, H);

  drawModuleClusters();
  edges.forEach(e => drawEdge(e, time));
  nodes.forEach(n => drawNode(n, time));

  if (Math.floor(time / 200) % 1 === 0) drawMinimap();
  updateStats();
}

// Init
buildERGraph();
updateLegend();
requestAnimationFrame(animate);

})();
