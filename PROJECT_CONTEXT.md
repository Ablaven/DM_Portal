# Digital Marketing Portal — Project Context

## read first
# DIGITAL MARKETING PORTAL - UI/UX STYLE GUIDE

## 🎨 DESIGN SYSTEM: APPLE GLASS MORPHISM

When creating ANY new page or feature, ALWAYS use this design system.

### Core Design Principles

1. **Glass Morphism**: Semi-transparent backgrounds with backdrop blur
2. **Subtle Gradients**: Use CSS variables for accent gradients
3. **Smooth Transitions**: All interactive elements have 0.3s transitions
4. **Elevation & Depth**: Cards lift on hover with shadows
5. **Rounded Corners**: Use `var(--radius)` (22px) for cards

### CSS Variables (Always Use These)

```css
/* Colors */
--bg: Background color
--card: Card background (semi-transparent)
--card-border: Card border color
--text: Primary text color
--muted: Secondary text color
--accent: Primary accent color (blue)
--accent-rgb: Accent color RGB values (for rgba())
--accent-2: Secondary accent color

/* Effects */
--shadow: Box shadow for elevation
--radius: Border radius for cards (22px)
--surface-1, --surface-2, --surface-3: Layered surfaces
--input-bg: Form input backgrounds
--input-border: Form input borders

/* Gradients */
--bg-grad-1, --bg-grad-2, --bg-grad-3: Background gradients
```

### Standard Component Classes

#### 1. **Page Headers**
```html
<div class="lectures-header">
  <div class="lectures-header-content">
    <h1 class="lectures-title">Page Title</h1>
    <p class="lectures-subtitle">Description</p>
  </div>
</div>
```

**CSS Pattern:**
- `padding: 2rem`
- `background: var(--surface-1)`
- `border: 1px solid var(--card-border)`
- `border-radius: 18px`
- `backdrop-filter: blur(20px)`
- Gradient overlay at 10% opacity

#### 2. **Card Grids**
```html
<div class="card-grid">
  <div class="card card-clickable">...</div>
</div>
```

**CSS:**
```css
.card-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
  gap: 1.25rem;
}
```

#### 3. **Interactive Cards**
- Base: `.card` (background, border, rounded)
- Clickable: `.card-clickable` (cursor, hover effects)
- **Hover Effects:**
  - `transform: translateY(-6px)`
  - `box-shadow: 0 16px 48px rgba(0, 0, 0, 0.2)`
  - `border-color: var(--accent)`

#### 4. **Buttons**
```html
<button class="btn btn-primary">Primary Action</button>
<button class="btn btn-secondary">Secondary</button>
<button class="btn btn-danger">Delete</button>
```

**Styles:**
- Primary: Gradient background with accent color
- Secondary: Surface background with border
- All buttons: `padding: 0.75rem 1.5rem`, `border-radius: 10px`

#### 5. **Form Inputs**
```html
<input class="form-control" type="text">
<select class="form-control">...</select>
```

**CSS:**
- `background: var(--input-bg)`
- `border: 1px solid var(--input-border)`
- `border-radius: 10px`
- Focus: accent border + box-shadow

#### 6. **Tables**
```html
<div class="table-responsive">
  <table class="data-table">...</table>
</div>
```

**CSS:**
- Sticky header with `background: var(--surface-2)`
- Row hover: `background: var(--surface-2)`
- Border radius on wrapper: `14px`

### Animation Standards

**All transitions use:** `cubic-bezier(0.4, 0, 0.2, 1)`

```css
/* Standard hover lift */
.card:hover {
  transform: translateY(-6px);
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

/* Icon zoom */
.icon:hover {
  transform: scale(1.1);
  transition: transform 0.3s;
}

/* Fade in */
.element {
  opacity: 0;
  transform: translateY(8px);
  animation: fadeIn 0.4s ease forwards;
}
```

### Spacing Scale

- **xs**: 0.25rem (4px)
- **sm**: 0.5rem (8px)
- **md**: 1rem (16px)
- **lg**: 1.5rem (24px)
- **xl**: 2rem (32px)
- **2xl**: 3rem (48px)

### Typography

```css
/* Page title */
.page-title {
  font-size: 2.5rem;
  font-weight: 800;
  line-height: 1.2;
}

/* Section heading */
.section-heading {
  font-size: 1.875rem;
  font-weight: 800;
  line-height: 1.2;
}

/* Card title */
.card-title {
  font-size: 1.25rem;
  font-weight: 700;
  line-height: 1.3;
}

/* Body text */
font-size: 0.9375rem (15px)
line-height: 1.5
```

### Responsive Breakpoints

```css
@media (max-width: 768px) {
  /* Mobile adjustments */
  .card-grid {
    grid-template-columns: 1fr;
  }
}
```

### File Structure

When creating a new page:

1. **PHP File** (root): `newpage.php`
   - Include `css/style.css` (always)
   - Include page-specific CSS if needed
   - Body class should match pattern: `newpage-view`

2. **JavaScript** (js/): `newpage.js`
   - Use `fetchJson()` from core.js
   - Follow existing patterns

3. **CSS** (css/): `newpage.css` (if needed)
   - Only page-specific styles
   - Use existing classes first
   - Follow the design system

### Examples from Existing Pages

**Good Examples:**
- `lectures.php` - Glass headers, card grids, proper spacing
- `schedule_builder.php` - Complex UI with consistent styling
- `students.php` - Table styling, filters

**Reference Files:**
- `css/style.css` - All base styles and variables
- `css/lectures.css` - Page-specific enhancements

### Quick Checklist for New Pages

- [ ] Uses `var(--*)` CSS variables
- [ ] Has glassmorphic header with backdrop blur
- [ ] Cards use `.card` and `.card-clickable`
- [ ] Buttons use `.btn .btn-primary/secondary/danger`
- [ ] Tables wrapped in `.table-responsive`
- [ ] All hover effects: 0.3s transitions
- [ ] Responsive mobile breakpoints
- [ ] Consistent spacing (use spacing scale)
- [ ] Rounded corners: `var(--radius)` or 10-18px

---

**Remember: Consistency > Creativity. Use existing patterns!**

## Project Snapshot
- **Purpose:** Web portal for Digital Marketing academic operations — scheduling, attendance, evaluation, reporting, and user administration.
- **Stack:** PHP (PDO, strict types) + MySQL backend, vanilla JS frontend, shared CSS design system.
- **Auth model:** Role-based access (`admin`, `management`, `teacher`, `student`) with optional per-user page allowlist override via `allowed_pages_json` in `portal_users`.
- **Runtime style:** Server-rendered PHP pages that hydrate with page-specific JS and call JSON endpoints under `php/`.
- **Environment:** Config loaded from `.env` via `php/env.php` (`DM_PORTAL_DB`, `DM_PORTAL_SMTP_*`, `DM_PORTAL_MASTER_KEY`).

---

## Application Architecture

### Layer Model
1. **Entry pages** (`*.php` at repo root) — enforce page access, render HTML shells and UI regions.
2. **Frontend modules** (`js/*.js`) — drive interactions, forms, modals, filters, and endpoint calls.
3. **Endpoint layer** (`php/*.php`) — validate input, enforce auth/ownership, execute DB logic, return JSON.
4. **Helper/schema layer** (`php/_*.php`) — shared SQL helpers, schema-ensure guards, and domain utilities.
5. **Data layer** (`digital_marketing_portal.sql`) — full DB dump; source of truth for schema.

### Core Shared Runtime
- `php/_auth.php` — session setup, role checks, page-allowlist checks, teacher ownership guards, navigation landing logic.
- `php/_navbar.php` — renders the portal navigation bar server-side.
- `js/core.js` — shared client helpers: `fetchJson`, `setStatusById`, `escapeHtml`, `formatWeekDisplayLabel`, filter persistence, theme transitions. Exports all helpers onto `window.dmportal`.
- `js/navbar.js` — dynamic nav visibility from `auth_me`, logout, global email modal flow.
- `php/db_connect.php` + `php/env.php` — DB/env bootstrapping.

---

## Top-Level Pages and Responsibilities

| Page | Description |
|---|---|
| `login.php` | Sign-in entry with first-admin bootstrap handling |
| `index.php` | Course dashboard summary (main landing page) |
| `dashboard.php` | Legacy redirect → `index.php` (302) |
| `schedule_builder.php` | Admin/management scheduling workspace |
| `admin_courses.php` | Course CRUD, doctor assignment, split-hour allocation, program catalog Excel export |
| `admin_doctors.php` | Doctor CRUD, metadata, per-year color management |
| `admin_students.php` | Student CRUD |
| `admin_users.php` | Portal account and permission management |
| `admin_panel.php` | Academic-year/term lifecycle wizard (Semester Management) and DB utility tools |
| `doctor.php` | Teacher-oriented schedule view |
| `availability.php` | Doctor availability/unavailability management (teacher-own + admin scope) |
| `attendance.php` | Attendance recording interface |
| `evaluation.php` | Grading and configuration interface |
| `students.php` | Student schedule view |
| `student_dashboard.php` | Student grade and performance dashboard |
| `hours_report.php` | Hours report — semester summary cards |
| `hours_report_detail.php` | Hours report — per-doctor course breakdown |
| `attendance_report.php` | Attendance analytics view |
| `evaluation_reports.php` | Evaluation/grades analytics view |
| `profile.php` | Password and account page |
| `architecture_map.php` | Architecture visualization page |
| `ablaven.php` | Gated easter-egg page (requires code `700` via `php/easter_egg_entry.php`, 5-minute session token) |

---

## Frontend Module Map

**Shared on all authenticated pages:** `js/core.js`, `js/navbar.js`

| Module | Page(s) |
|---|---|
| `js/admin_terms.js` | `admin_panel.php` — loads terms/years, manual panel (activate, create, reset weeks) |
| `js/admin_advance.js` | `admin_panel.php` — semester advance wizard (Sem 1→2 and Sem 2→New Year) |
| `js/admin_courses.js` | `admin_courses.php` |
| `js/admin_doctors.js` | `admin_doctors.php` |
| `js/admin_students.js` | `admin_students.php` |
| `js/admin_users.js` | `admin_users.php` |
| `js/schedule_builder.js` | `schedule_builder.php` |
| `js/doctor_view.js` | `doctor.php` |
| `js/availability.js` | `availability.php` |
| `js/attendance.js` | `attendance.php` |
| `js/evaluation.js` | `evaluation.php` |
| `js/students.js` | `students.php` |
| `js/student_dashboard.js` | `student_dashboard.php` |
| `js/profile.js` | `profile.php` |
| `js/course_dashboard.js` | `index.php` |
| `js/hours_report.js` | `hours_report.php`, `hours_report_detail.php` |
| `js/reports_teacher_cards.js` | `hours_report.php` |
| `js/attendance_report.js` | `attendance_report.php` |
| `js/evaluation_reports.js` | `evaluation_reports.php` |
| `js/architecture_map.js` | `architecture_map.php` |
| `js/ablaven.js` | `ablaven.php` |

---

## Backend Endpoint Domains

### Auth and User Management
- **Session/auth:** `auth_login.php`, `auth_logout.php`, `auth_me.php`, `auth_change_password.php`, `auth_create_first_admin.php`
- **User admin APIs:** `admin_users_list.php`, `admin_users_create.php`, `admin_users_update.php`, `admin_users_delete.php`, `admin_users_toggle_active.php`, `admin_users_set_password.php`

### Course and Scheduling Domain
- **Course APIs:** `get_courses.php`, `add_course.php`, `add_course_simple.php`, `update_course.php`, `delete_course.php`
- **Doctor-course mapping:** `set_course_doctors.php`, `set_course_doctor_hours.php`, `get_course_doctor_hours.php`, `get_doctor_courses.php`
- **Schedule ops:** `get_schedule.php`, `manage_schedule.php`, `check_slot_conflict.php`, `get_current_slot.php`
- **Week/term lifecycle:** `get_terms.php`, `create_term.php`, `activate_term.php`, `advance_term.php`, `get_weeks.php`, `start_week.php`, `stop_week.php`, `reset_term_weeks.php`, `clone_week.php`, `set_week_type.php`, `get_academic_years.php`
- **Availability/cancellations:** `get_doctor_availability.php`, `set_doctor_availability.php`, `add_unavailability.php`, `get_unavailability.php`, `delete_unavailability.php`, `set_doctor_cancellation.php`, `clear_doctor_cancellation.php`, `set_doctor_slot_cancellation.php`, `clear_doctor_slot_cancellation.php`
- **Doctor year colors:** `get_doctor_year_colors.php`, `set_doctor_year_colors.php`
- **Student schedule:** `get_student_schedule.php`

### Attendance Domain
- `get_attendance_grid.php`, `get_attendance.php`, `set_attendance.php`, `get_attendance_courses.php`, `copy_attendance_next_lecture.php`, `get_master_list.php`, `get_attendance_reports_summary.php`
- Lecture-window helpers: `_slot_time_helpers.php`, `_attendance_session_helpers.php` (`attendance_sessions` table)

### Evaluation Domain
- **Config/catalog:** `get_evaluation_courses.php`, `get_evaluation_categories.php`, `add_evaluation_category.php`, `get_evaluation_config.php`, `set_evaluation_config.php`
- **Grade read/write:** `get_evaluation_grades.php`, `set_evaluation_grade.php`, `get_student_evaluation.php`, `get_evaluation_reports_summary.php`

### Student Domain
- `get_students.php`, `add_student.php`, `update_student.php`, `delete_student.php`
- `get_student_profile.php` — student-scoped own profile (name, student_code); student role only
- `get_student_schedule.php` — week grid for a given program/year_level/semester; filters cancelled days/slots

### Reports, Export, and Email
- **Report data:** `get_hours_report.php`, `get_hours_report_semester_summary.php`, `get_reports.php`, `get_missionnaire_hours_pie.php`, `get_doctor_type_hours_summary.php`
- **Exporters:** `export_doctor_week_xls.php`, `export_all_doctors_week_xls.php`, `export_student_schedule_xls.php`, `export_attendance_xls.php`, `export_evaluation_summary_xls.php`, `export_evaluation_summary_all_xls.php`, `export_evaluation_grades_xls.php`, `export_hours_report_summary_xls.php`, `export_hours_report_detail_xls.php`, `export_hours_report_custom_xls.php`, `export_program_catalog_xls.php`, `export_database_sql.php`, `import_database_sql.php`
- **Email:** `email_doctor_schedule.php`, `email_student_schedule.php`, `email_custom_message.php`, `email_recipient_options.php`

### Easter Egg
- `php/easter_egg_entry.php` — POST with JSON `{"code":"700"}`, grants a 5-minute session token via `_easter_egg_gate.php`
- `php/require_easter_egg.php` — included by `ablaven.php` to enforce gate access
- `php/set_easter_egg_token.php` — token management helper
- `php/_easter_egg_gate.php` — `dmportal_grant_easter_egg()`, `dmportal_has_easter_egg_access()`, `dmportal_require_easter_egg_access()`

### Helpers and Infra
- **Schema/adaptation helpers:** `_schema.php`, `_auth_schema.php`, `_week_schema_helpers.php`, `_attendance_schema_helpers.php`, `_attendance_session_helpers.php`, `_slot_time_helpers.php`, `_evaluation_schema_helpers.php`, `_availability_schema_helpers.php`, `_students_schema_helpers.php`, `_doctor_schema_helpers.php`, `_course_hours_helpers.php`, `_hours_report_helpers.php`, `_doctor_year_colors_helpers.php`, `_cancel_restore_helpers.php`, `_term_helpers.php`
- **Utility internals:** `_xlsx_writer.php`, `_smtp_mailer.php`, `rate_limiter.php`, `_navbar.php`
- **One-time migrations:** `migrate_add_course_coefficient.php` — adds `courses.coefficient` column (run once manually)

---

## Key End-to-End Flows

### 1) Schedule Builder Flow
- **Page:** `schedule_builder.php` | **JS:** `js/schedule_builder.js`
- Data bootstrap: doctors/courses/weeks/schedule via `get_doctors.php`, `get_courses.php`, `get_weeks.php`, `get_schedule.php`
- Write path: `manage_schedule.php` (POST `set`/`remove`)
- Major server validations in `manage_schedule.php`: day/slot constraints, cancelled-day/slot guard, unavailability overlap guard, room conflict prevention, student timetable conflict by `(program, year_level, semester)`, doctor-course assignment enforcement, remaining-hours enforcement (split allocation or course-level fallback)
- Week controls/exports/emails wired to `start_week.php`, `set_week_type.php`, `stop_week.php`, `export_doctor_week_xls.php`, `export_all_doctors_week_xls.php`, `email_doctor_schedule.php`

### 2) Course Remaining-Hours Model
- **Source of total hours:** `courses.total_hours`
- **Split mapping:** `course_doctors` (which doctors are attached) + `course_doctor_hours` (explicit per-doctor allocated hours)
- **Done hours rule:** Only slots with `counts_towards_hours = 1`, not cancelled, AND a matching `attendance_sessions` row with `hours_counted = 1` (teacher saved attendance during the Cairo lecture window) count as done.
- **Term scoping:** Done hours are scoped to the **active term** (`w_h.term_id = activeTermId` injected as a literal integer by the SQL helpers). This means starting a new academic year resets done hours to 0. Hours within the same academic year (Sem 1→Sem 2) carry forward naturally because the term changes.
- **Shared SQL helpers in `_course_hours_helpers.php`:**
  - `dmportal_attendance_confirmed_join_sql(int $termId = 0)` — joins weeks + attendance_sessions; inlines term filter as literal integer when `$termId > 0`
  - `dmportal_schedule_hours_join_xall(string $courseAlias, string $joinAlias, int $termId = 0)` — scheduled hours per course (all doctors)
  - `dmportal_schedule_hours_join_xdoc(string $courseAlias, string $placeholder, string $joinAlias, int $termId = 0)` — scheduled hours per (course, doctor)
  - `dmportal_done_hours_subquery_sql(string $weekCancelJoin, string $slotCancelJoin, int $termId = 0)` — done-hours aggregate for reports
  - `dmportal_done_hours_course_subquery_sql(string $extraWhere, int $termId = 0)` — per-course done slot counts
- **IMPORTANT:** The term filter is inlined as a literal integer (e.g. `AND w_h.term_id = 3`), NOT as a PDO named placeholder, to avoid duplicate-parameter errors when multiple subqueries use the same helper in one prepared statement.
- `get_courses.php` fetches `$activeTermId` via `dmportal_get_active_term_id($pdo)` and passes it to all join helpers. The no-doctor path uses a prepared statement with `$stmt->execute([])`.
- `get_hours_report.php` / `dmportal_fetch_hours_report()` similarly accept and pass `$termId`.

### 3) Semester Advance Wizard
- **Page:** `admin_panel.php` | **JS:** `js/admin_terms.js` + `js/admin_advance.js`
- **Two paths:**
  - **Sem 1→Sem 2:** Creates/activates Semester 2 term, resets weeks to Week 1. Wrapped in a single `beginTransaction/commit/rollBack`. Student year levels unchanged.
  - **Sem 2→New Academic Year:** Closes current academic year, creates next year (label auto-incremented e.g. `2025-2026` → `2026-2027`), creates Semester 1 (active) + Semester 2 (closed) for new year, resets weeks to Week 1, updates student year levels per selected preset.
- **Student advancement presets:** `advance_except_final` (auto — advances all, graduates final year), `advance_all` (advances Year 1/2, graduates Year 3+), `repeat_all` (no changes), `custom` (per-student table)
- **Transaction safety:** All DDL-bearing schema-ensure functions (`dmportal_ensure_terms_table`, `dmportal_ensure_weeks_prep_column`) use a `static $done` guard — they run once per request and are no-ops on subsequent calls, preventing DDL from executing inside an active transaction and triggering MySQL implicit commits.
- **Event flow:** On success, JS dispatches `dmportal:terms-updated` → `admin_terms.js` reloads data → dispatches `dmportal:active-term-loaded` → wizard header and button update.

### 4) Attendance Flow
- **Page:** `attendance.php` | **JS:** `js/attendance.js`
- Lecture windows evaluated in `Africa/Cairo` via `_slot_time_helpers.php` (regular and Ramadan slot tables)
- Teachers may save attendance only during the active Cairo lecture window for their own assigned slots
- Admins/management may save outside the window; hours only count when the assigned doctor saves during the Cairo window
- Session persistence: `attendance_sessions` (`term_id`, `schedule_id`, `doctor_id`, `opened_at`, `opened_by_user_id`, `hours_counted`) via `_attendance_session_helpers.php`
- **Grandfather rule:** existing slots with `attendance_records` are repaired to `hours_counted = 1`
- Cairo slot boundaries (regular): slot 1 `08:30–10:00`, 2 `10:10–11:30`, 3 `11:40–13:00`, 4 `13:10–14:40`, 5 `14:50–16:20`; Ramadan times via `weeks.is_ramadan`

### 5) Hours Report Flow
- **Pages:** `hours_report.php` (semester summary), `hours_report_detail.php` (per-doctor breakdown)
- **JS:** `js/hours_report.js`, `js/reports_teacher_cards.js`
- **Data:** `get_hours_report.php` → `dmportal_fetch_hours_report()` in `_hours_report_helpers.php`
- Done hours require `attendance_sessions.hours_counted = 1` scoped to active term
- **Excel exports:**
  - **Summary:** `export_hours_report_summary_xls.php` — all professors totals for current year/sem filter
  - **Detail:** `export_hours_report_detail_xls.php` — one professor, per-course rows + totals
  - **Custom:** `export_hours_report_custom_xls.php` — pick multiple doctors + year/sem combos (6 checkboxes: Y1–Y3 × Sem 1–2); one workbook, one sheet per doctor; Overall Total row

### 6) Evaluation Flow
- **Page:** `evaluation.php` | **JS:** `js/evaluation.js`
- Config and categories via `get/set_evaluation_config`, `get_evaluation_categories`, `add_evaluation_category`
- Grade writes via `set_evaluation_grade.php`; reads via `get_evaluation_grades.php`
- Config and grades use `doctor_id = 0` as a shared record model for admin/teacher reads

### 7) User/Auth and Access Control Flow
- `_auth.php` governs: login requirement, role checks, page allowlist checks, teacher ownership checks
- If a user has non-empty `allowed_pages_json`, this override becomes primary and JSON endpoints under `/php/` are allowed
- Login hardening: session cookie (`HttpOnly`, `SameSite=Lax`, conditional `Secure`, 8-hour lifetime), rate limiting in `rate_limiter.php`, redirect target sanitized by `auth_sanitize_next`
- `auth_login.php` contains a master-key bypass (`DM_PORTAL_MASTER_KEY`) — high-sensitivity, do not modify

### 8) Doctor Year Colors
- Doctors have a base `color_code` on the `doctors` table
- Per-year-level overrides stored in `doctor_year_colors` (`doctor_id`, `year_level`, `color_code`)
- All schedule grids (schedule builder, doctor view, student view) prefer `COALESCE(dyc.color_code, d.color_code)`
- Managed via `get_doctor_year_colors.php` / `set_doctor_year_colors.php` (admin/management only)

### 9) SMTP / Email
- Custom raw SMTP implementation in `_smtp_mailer.php` (`DmportalSmtpMailer`)
- Config via `.env`: `DM_PORTAL_SMTP_HOST`, `DM_PORTAL_SMTP_PORT`, `DM_PORTAL_SMTP_ENCRYPTION`, `DM_PORTAL_SMTP_USER`, `DM_PORTAL_SMTP_PASS`, `DM_PORTAL_SMTP_FROM`
- Gmail requires an **App Password** (not the account password); 2-Step Verification must be enabled on the Google account
- Supports attachments (base64 MIME) and CC addresses

---

## Data Model Summary

### Identity and Access
- `portal_users` — credentials, role, optional doctor/student linkage, `allowed_pages_json`
- `admins`, `audit_log` — legacy/adjacent tables

### Academic Structure
- `academic_years` — `academic_year_id`, `label` (e.g. `2025-2026`), `status` (`active`/`closed`), `start_date`, `end_date`
- `terms` — `term_id`, `academic_year_id`, `label`, `semester` (1 or 2), `status`, dates
- `weeks` — `week_id`, `term_id`, `label` (canonical `Week N`), `start_date`, `end_date`, `status` (`active`/`closed`), `is_prep`, `is_ramadan`

### People and Teaching Entities
- `doctors` — includes `color_code`, `full_name`, metadata
- `students` — `student_id`, `full_name`, `student_code`, `email`, `program`, `year_level`, `semester`
- `courses` — `course_id`, `course_name`, `program`, `year_level`, `semester`, `course_type` (`R`/`LAS`/`ZH`), `total_hours`, `coefficient`, `default_room_code`, `doctor_id` (legacy single-doctor), `subject_code`
- `course_doctors` + `course_doctor_hours` — multi-doctor assignment and split-hour allocations
- `doctor_year_colors` — per-doctor per-year color overrides

### Schedule and Availability
- `doctor_schedules` — `schedule_id`, `week_id`, `doctor_id`, `course_id`, `day_of_week`, `slot_number`, `room_code`, `counts_towards_hours`, `extra_minutes`
- `doctor_week_cancellations`, `doctor_slot_cancellations` — cancellation overlays
- `doctor_availability`, `doctor_unavailability` — availability windows
- `schema_versions` — migration version tracking

### Attendance
- `attendance_records` — per `(term_id, schedule_id, student_id)`
- `attendance_sessions` — one row per `(term_id, schedule_id)`; `hours_counted = 1` when assigned doctor saved during Cairo window

### Evaluation
- `evaluation_categories`, `evaluation_configs`, `evaluation_config_items`
- `evaluation_grades`, `evaluation_grade_items`
- Shared `doctor_id = 0` records for config/grades

### Additional Tables
- `student_schedules`, `rooms`, `floors`, `events`, `cancelled_doctor_schedules`

---

## UI/CSS Notes
- `css/style.css` — global design system (tokens, themes, shared components, `.status`, `.btn`, `.card`, `.data-table`, `.modal`, etc.)
- `css/admin_users.css`, `css/architecture_map.css`, `css/ablaven.css` — specialized overlays
- UX pattern: modal-heavy with status/toast feedback via shared helpers (`setStatusById`, toast system in `core.js`)
- No continuous polling; page refreshes are event/action driven
- Week state tags (`active`, `prep`, `ramadan`) rendered via `formatWeekDisplayLabel` in `core.js`, used by schedule builder, students, attendance, availability, doctor view, admin doctors

---

## Week State Semantics
- `status = 'active'` — scheduling-active week
- `is_prep = 1` — prep week (closed scheduling state)
- `is_ramadan = 1` — Ramadan week (active scheduling semantics, different Cairo slot times)
- Labels are canonical `Week N` — state is represented by flags/status, never embedded in the label
- `set_week_type.php` enforces exclusive state per term (removes conflicting flags when switching)
- `start_week.php` keeps labels canonical, prevents stale flags on new week creation

---

## Operational Notes
- Several endpoints tolerate older schemas via `try/catch` guards for optional tables/columns
- All DDL-bearing schema-ensure helpers use a `static $done` guard to be no-ops after first call — **never call them inside an open transaction**
- `advance_term.php` calls all schema-ensure functions before `beginTransaction()`
- Schema helper files ensure required structures before sensitive operations
- Exports depend on `_xlsx_writer.php`; email depends on `_smtp_mailer.php`
- `migrate_add_course_coefficient.php` is a one-time admin-run migration script (not auto-applied)
- `dashboard.php` is a legacy 302 redirect to `index.php` — do not add logic to it

---

## Known Risk / Attention Areas
- `allowed_pages_json` override behavior in `_auth.php` must be preserved intentionally
- `auth_login.php` master-key bypass (`DM_PORTAL_MASTER_KEY`) is high-sensitivity
- Done-hours term scoping uses **literal integer injection** into SQL (not PDO placeholders) — this is intentional to avoid duplicate named-parameter errors across multi-subquery prepared statements
- Multiple compatibility fallbacks for missing tables/columns mean behavior can vary across partially migrated databases
- Easter egg gate uses a PHP session with 5-minute TTL — not tied to portal auth session

---

## Most Likely Files to Touch (By Feature)

| Feature Area | Files |
|---|---|
| Auth/permissions | `php/_auth.php`, `php/auth_login.php`, `php/auth_me.php`, `admin_users.php`, `js/admin_users.js` |
| Scheduling core | `schedule_builder.php`, `js/schedule_builder.js`, `php/manage_schedule.php`, `php/get_schedule.php`, `php/get_courses.php` |
| Course allocations | `admin_courses.php`, `js/admin_courses.js`, `php/set_course_doctors.php`, `php/set_course_doctor_hours.php`, `php/get_course_doctor_hours.php`, `php/export_program_catalog_xls.php` |
| Semester/year lifecycle | `admin_panel.php`, `js/admin_terms.js`, `js/admin_advance.js`, `php/advance_term.php`, `php/_term_helpers.php`, `php/_week_schema_helpers.php` |
| Hours model | `php/_course_hours_helpers.php`, `php/_hours_report_helpers.php`, `php/get_courses.php`, `php/get_hours_report.php` |
| Attendance | `attendance.php`, `js/attendance.js`, `php/get_attendance_grid.php`, `php/set_attendance.php`, `php/_slot_time_helpers.php`, `php/_attendance_session_helpers.php` |
| Evaluation | `evaluation.php`, `js/evaluation.js`, `php/get_evaluation_config.php`, `php/set_evaluation_config.php`, `php/get_evaluation_grades.php`, `php/set_evaluation_grade.php` |
| Reporting | `hours_report.php`, `hours_report_detail.php`, `js/hours_report.js`, `php/_hours_report_helpers.php`, export endpoints, `attendance_report.php`, `evaluation_reports.php` |
| Doctor colors | `admin_doctors.php`, `js/admin_doctors.js`, `php/get_doctor_year_colors.php`, `php/set_doctor_year_colors.php`, `php/_doctor_year_colors_helpers.php` |
| Student views | `students.php`, `student_dashboard.php`, `js/students.js`, `js/student_dashboard.js`, `php/get_student_schedule.php`, `php/get_student_profile.php` |
| Data model/migrations | `digital_marketing_portal.sql`, `php/_schema.php`, domain-specific `*_schema_helpers.php`, `php/migrate_add_course_coefficient.php` |
| SMTP/email | `php/_smtp_mailer.php`, `php/env.php`, `.env` |
