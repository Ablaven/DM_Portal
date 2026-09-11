# Digital Marketing Portal - Project Context

## Project Snapshot
- Purpose: web portal for Digital Marketing academic operations (scheduling, attendance, evaluation, reporting, and user administration).
- Stack: PHP (PDO) + MySQL backend, vanilla JS frontend, shared CSS design system.
- Auth model: role-based access with optional per-user page allowlist override via `allowed_pages_json` in `portal_users`.
- Runtime style: server-rendered PHP pages that hydrate with page-specific JS and call JSON endpoints under `php/`.

## Application Architecture

### Layer Model
1. Entry pages (`*.php` at repo root) enforce page access and render shells/UI regions.
2. Frontend modules (`js/*.js`) drive interactions, forms, modals, filters, and endpoint calls.
3. Endpoint layer (`php/*.php`) validates input, enforces auth/ownership, and executes DB logic.
4. Data layer (`digital_marketing_portal.sql`) stores scheduling, attendance, grading, and user metadata.

### Core Shared Runtime
- `php/_auth.php`: session setup, role checks, page checks, teacher ownership guards, navigation landing logic.
- `js/core.js`: shared client helpers (`fetchJson`, status/toast helpers, filter persistence, theme transitions).
- `js/navbar.js`: dynamic nav visibility from `auth_me`, logout, and global email modal flow.
- `php/db_connect.php` + `php/env.php`: DB/env bootstrapping.

## Top-Level Pages and Responsibilities
- `login.php`: sign-in entry with first-admin bootstrap handling.
- `index.php`: course dashboard summary.
- `schedule_builder.php`: admin/management scheduling workspace.
- `admin_courses.php`: course CRUD, doctor assignment, split-hour allocation, program catalog Excel export (3 years × 2 semesters).
- `admin_doctors.php`: doctor CRUD, metadata and year colors.
- `admin_students.php`: student CRUD.
- `admin_users.php`: portal account/permission management.
- `admin_panel.php`: academic-year/term lifecycle tools and DB utility actions.
- `doctor.php`: teacher-oriented schedule view.
- `availability.php`: doctor availability/unavailability management (teacher-own/admin scope).
- `attendance.php`: attendance recording interface.
- `evaluation.php`: grading/configuration interface.
- `students.php`: student schedule view.
- `student_dashboard.php`: student grade/performance dashboard.
- `hours_report.php`, `hours_report_detail.php`, `attendance_report.php`, `evaluation_reports.php`: reports and analytics views (Hours Report supports summary/detail/custom Excel exports with attendance-gated done hours).
- `profile.php`: password/account page.
- `architecture_map.php`: architecture visualization page.
- `ablaven.php`: gated easter-egg page.

## Frontend Module Map
- Shared on most authenticated pages: `js/core.js`, `js/navbar.js`.
- Scheduling and operations:
  - `js/schedule_builder.js`
  - `js/admin_courses.js`
  - `js/admin_doctors.js`
  - `js/admin_students.js`
  - `js/admin_users.js`
  - `js/admin_terms.js`
  - `js/admin_advance.js`
- Teaching/student workflows:
  - `js/doctor_view.js`
  - `js/availability.js`
  - `js/attendance.js`
  - `js/evaluation.js`
  - `js/students.js`
  - `js/student_dashboard.js`
  - `js/profile.js`
- Reporting:
  - `js/hours_report.js`
  - `js/reports_teacher_cards.js`
  - `js/attendance_report.js`
  - `js/evaluation_reports.js`
- Misc:
  - `js/course_dashboard.js`
  - `js/architecture_map.js`
  - `js/ablaven.js`

## Backend Endpoint Domains

### Auth and User Management
- Session/auth: `php/auth_login.php`, `php/auth_logout.php`, `php/auth_me.php`, `php/auth_change_password.php`, `php/auth_create_first_admin.php`.
- User admin APIs: `php/admin_users_list.php`, `php/admin_users_create.php`, `php/admin_users_update.php`, `php/admin_users_delete.php`, `php/admin_users_toggle_active.php`, `php/admin_users_set_password.php`.

### Course and Scheduling Domain
- Course APIs: `php/get_courses.php`, `php/add_course.php`, `php/add_course_simple.php`, `php/update_course.php`, `php/delete_course.php`.
- Multi-doctor mapping: `php/set_course_doctors.php`, `php/set_course_doctor_hours.php`, `php/get_course_doctor_hours.php`.
- Schedule ops: `php/get_schedule.php`, `php/manage_schedule.php`, `php/check_slot_conflict.php`, `php/get_current_slot.php`.
- Week/term lifecycle: `php/get_terms.php`, `php/create_term.php`, `php/activate_term.php`, `php/advance_term.php`, `php/get_weeks.php`, `php/start_week.php`, `php/stop_week.php`, `php/reset_term_weeks.php`, `php/clone_week.php`, `php/set_week_type.php`, `php/get_academic_years.php`.
- Availability/cancellations: `php/get_doctor_availability.php`, `php/set_doctor_availability.php`, `php/add_unavailability.php`, `php/get_unavailability.php`, `php/delete_unavailability.php`, `php/set_doctor_cancellation.php`, `php/clear_doctor_cancellation.php`, `php/set_doctor_slot_cancellation.php`, `php/clear_doctor_slot_cancellation.php`.

### Attendance Domain
- `php/get_attendance_grid.php`, `php/get_attendance.php`, `php/set_attendance.php`, `php/get_attendance_courses.php`, `php/copy_attendance_next_lecture.php`, `php/get_master_list.php`, `php/get_attendance_reports_summary.php`.
- Lecture-window helpers: `php/_slot_time_helpers.php`, `php/_attendance_session_helpers.php` (`attendance_sessions` table).

### Evaluation Domain
- Config/catalog: `php/get_evaluation_courses.php`, `php/get_evaluation_categories.php`, `php/add_evaluation_category.php`, `php/get_evaluation_config.php`, `php/set_evaluation_config.php`.
- Grade/read models: `php/get_evaluation_grades.php`, `php/set_evaluation_grade.php`, `php/get_student_evaluation.php`, `php/get_evaluation_reports_summary.php`.

### Reports, Export, and Email
- Report data: `php/get_hours_report.php`, `php/get_hours_report_semester_summary.php`, `php/get_reports.php`, `php/get_missionnaire_hours_pie.php`, `php/get_doctor_type_hours_summary.php`.
- Exporters: `php/export_doctor_week_xls.php`, `php/export_all_doctors_week_xls.php`, `php/export_student_schedule_xls.php`, `php/export_attendance_xls.php`, `php/export_evaluation_summary_xls.php`, `php/export_evaluation_summary_all_xls.php`, `php/export_evaluation_grades_xls.php`, `php/export_hours_report_summary_xls.php`, `php/export_hours_report_detail_xls.php`, `php/export_hours_report_custom_xls.php`, `php/export_program_catalog_xls.php`, `php/export_database_sql.php`, `php/import_database_sql.php`.
- Email: `php/email_doctor_schedule.php`, `php/email_student_schedule.php`, `php/email_custom_message.php`, `php/email_recipient_options.php`.

### Helpers and Infra
- Schema/adaptation helpers: `php/_schema.php`, `php/_auth_schema.php`, `php/_week_schema_helpers.php`, `php/_attendance_schema_helpers.php`, `php/_attendance_session_helpers.php`, `php/_slot_time_helpers.php`, `php/_evaluation_schema_helpers.php`, `php/_availability_schema_helpers.php`, `php/_students_schema_helpers.php`, `php/_doctor_schema_helpers.php`, `php/_course_hours_helpers.php`, `php/_hours_report_helpers.php`, `php/_doctor_year_colors_helpers.php`, `php/_cancel_restore_helpers.php`, `php/_term_helpers.php`.
- Utility internals: `php/_xlsx_writer.php`, `php/_smtp_mailer.php`, `php/rate_limiter.php`, `php/_navbar.php`.

## Key End-to-End Flows

### 1) Schedule Builder Flow
- Page: `schedule_builder.php`
- JS: `js/schedule_builder.js`
- Data bootstrap: doctors/courses/weeks/schedule via `php/get_doctors.php`, `php/get_courses.php`, `php/get_weeks.php`, `php/get_schedule.php`.
- Write path: `php/manage_schedule.php` (POST `set`/`remove`).
- Major server validations in `manage_schedule.php`:
  - day/slot/time argument constraints.
  - cancelled day and cancelled slot guard.
  - optional unavailability overlap guard.
  - room conflict prevention.
  - student conflict prevention by `(program, year_level, semester)`.
  - doctor-course assignment enforcement.
  - remaining-hours enforcement (split allocation or course-level fallback).
- Week controls/exports/emails wired to `php/start_week.php`, `php/set_week_type.php`, `php/stop_week.php`, `php/export_doctor_week_xls.php`, `php/export_all_doctors_week_xls.php`, `php/email_doctor_schedule.php`.

### 2) Course Remaining-Hours Model
- Source table for total: `courses.total_hours`.
- Split mapping:
  - `course_doctors`: which doctors are attached to a course.
  - `course_doctor_hours`: explicit per-doctor allocated hours when split is configured.
- **Done/used hours** count only scheduled slots with `counts_towards_hours = 1`, not cancelled, and a matching `attendance_sessions` row with `hours_counted = 1` (teacher saved attendance during the Cairo lecture window).
- Shared SQL helpers in `php/_course_hours_helpers.php` (`dmportal_schedule_hours_join_xall`, `dmportal_schedule_hours_join_xdoc`, `dmportal_done_hours_subquery_sql`, `dmportal_done_hours_course_subquery_sql`).
- Remaining read logic in `php/get_courses.php`:
  - excludes cancelled day/slot schedules from used-hours sums.
  - supports doctor-scoped remaining when `doctor_id` is provided.
  - uses distinct placeholders (`:doctor_id_asg`, `:doctor_id_h`, `:doctor_id_xdoc`) to avoid duplicate-name PDO issues.
- Hours report done hours (`php/_hours_report_helpers.php`, `php/get_hours_report.php`, semester/type summaries, XLS exports) use the same attendance-confirmed rule.
- Enforcement logic in `php/manage_schedule.php` mirrors split-vs-non-split behavior (scheduling guard still uses timetable slots; done hours require confirmed attendance).

### 3) Attendance Flow
- Page: `attendance.php`
- JS: `js/attendance.js`
- **Lecture windows** are evaluated in `Africa/Cairo` via `php/_slot_time_helpers.php` (regular and Ramadan slot tables; slot 1 unlocks 08:30, locks at end time e.g. 10:00).
- **Teachers** may save attendance only during the active Cairo lecture window for their own assigned slots; the slot locks when the lecture window ends (missed window = no hours).
- **Admins/management** may save outside the window; hours still count only when the assigned doctor saves during the Cairo lecture window.
- Session persistence: `attendance_sessions` (`term_id`, `schedule_id`, `doctor_id`, `opened_at`, `opened_by_user_id`, `hours_counted`) via `php/_attendance_session_helpers.php`; created on first teacher save during the window.
- **Grandfather rule:** existing slots that already have `attendance_records` are repaired to `hours_counted = 1` so pre-rule historical hours are preserved; new lectures still require the assigned teacher to save during the Cairo window (admin-only saves do not create a session).
- API cycle: grid/course/students/records load (`get_attendance_grid`, `get_attendance_courses`, `get_attendance`) -> write via `set_attendance` -> optional carry-forward with `copy_attendance_next_lecture`.
- Grid and modal APIs return `lecture_window_state`, `can_take_attendance`, `can_open_attendance`, `attendance_locked`, `hours_counted`.
- Cairo slot boundaries (regular weeks): slot 1 `08:30–10:00`, 2 `10:10–11:30`, 3 `11:40–13:00`, 4 `13:10–14:40`, 5 `14:50–16:20`; Ramadan times via `weeks.is_ramadan` (see `php/_slot_time_helpers.php`).
- Export available via `php/export_attendance_xls.php`.

### 4) Hours Report Flow
- Pages: `hours_report.php` (semester summary cards), `hours_report_detail.php` (per-doctor course breakdown).
- JS: `js/hours_report.js` (filters, on-screen report, export triggers, custom-export modal).
- Data: `php/get_hours_report.php` via `php/_hours_report_helpers.php` (`dmportal_fetch_hours_report`).
- **Done hours** require attendance-confirmed slots (`attendance_sessions.hours_counted = 1`); remaining = allocated − done.
- Excel exports:
  - **Summary** (`php/export_hours_report_summary_xls.php`): all professors totals for current year/sem filter (admin/management only).
  - **Detail** (`php/export_hours_report_detail_xls.php`): one professor, per-course rows + section totals; includes done-hours rule note.
  - **Custom** (`php/export_hours_report_custom_xls.php`): modal on `hours_report_detail.php` — pick multiple doctors and year/sem combos (6 checkboxes: Y1–Y3 × Sem 1–2); one workbook, one sheet per doctor; stacked sections per selected period with per-section totals; **Overall Total** row at the bottom sums allocated/done/remaining across all selected periods (lists selected periods when more than one).
- Custom export POST payload: `{ doctor_ids: number[], filters: [{ year_level, semester }, ...] }` via form field `payload` to `export_hours_report_custom_xls.php`.
- Teachers scoped to own `doctor_id`; admins pick any doctors.

### 5) Evaluation Flow
- Page: `evaluation.php`
- JS: `js/evaluation.js`
- Config and categories via `get/set_evaluation_config`, `get_evaluation_categories`, `add_evaluation_category`.
- Grade writes via `php/set_evaluation_grade.php`.
- Grade reads via `php/get_evaluation_grades.php`.
- Important behavior: evaluation config and grades are persisted with `doctor_id = 0` as a shared record model for admin/teacher reads.

### 6) User/Auth and Access Control Flow
- `php/_auth.php` governs:
  - login requirement.
  - role checks.
  - page allowlist checks.
  - teacher ownership checks (`auth_require_teacher_own_doctor`).
- Permission nuance:
  - if a user has non-empty `allowed_pages_json`, this override becomes primary and JSON endpoints under `/php/` are allowed by that mode.
  - ownership checks are still applied by sensitive endpoints for teacher data scope.
- Login hardening:
  - session cookie hardened (`HttpOnly`, `SameSite=Lax`, conditional `Secure`, 8-hour lifetime).
  - login rate limiting in `php/rate_limiter.php`.
  - redirect target sanitized by `auth_sanitize_next`.

## Data Model Summary (From SQL + Runtime Usage)

### Identity and Access
- `portal_users`: credentials, role, optional doctor/student linkage, `allowed_pages_json`.
- legacy/adjacent tables include `admins` and `audit_log`.

### Academic Structure
- `academic_years`, `terms`, `weeks`: term/week lifecycle used by scheduling, attendance, and evaluation endpoints.

### People and Teaching Entities
- `doctors`, `students`.
- `courses` stores core course metadata.
- `course_doctors` + `course_doctor_hours` provide multi-doctor assignment and split-hour allocations.

### Schedule and Availability
- `doctor_schedules`: week/day/slot assignments (`counts_towards_hours`, `extra_minutes`, optional `room_code`).
- `doctor_week_cancellations`, `doctor_slot_cancellations`: cancellation overlays.
- `doctor_availability`, `doctor_unavailability`: availability windows.
- `doctor_year_colors`: presentation metadata.

### Attendance
- `attendance_records`: attendance per `(term_id, schedule_id, student_id)` style uniqueness.
- `attendance_sessions`: one row per `(term_id, schedule_id)` when attendance is taken; `hours_counted = 1` when the assigned doctor saved during the Cairo lecture window.

### Evaluation
- `evaluation_categories`, `evaluation_configs`, `evaluation_config_items`.
- `evaluation_grades`, `evaluation_grade_items`.
- App runtime currently uses shared `doctor_id=0` records for config/grades.

### Additional Tables Present
- `student_schedules`, `rooms`, `floors`, `events`, `cancelled_doctor_schedules`, `schema_versions`.

## UI/CSS Notes
- `css/style.css` is the global style system (tokens, themes, shared components).
- Specialized overlays: `css/admin_users.css`, `css/architecture_map.css`, `css/ablaven.css`.
- UX pattern is modal-heavy with status/toast feedback through shared helpers.
- No continuous polling loop detected; page refreshes are event/action driven.

## Operational Notes
- Several endpoints tolerate older schemas using `try/catch` guards for optional tables/columns.
- Schema helper files are used to ensure required structures before some operations.
- Exports depend on `php/_xlsx_writer.php`.
- Email actions depend on `php/_smtp_mailer.php`.
- Environment-sensitive behavior includes auth/session security flags and credential loading via `php/env.php`.

## Recent Stabilization (Weeks System)
- Week labels are canonicalized to `Week N`; state is represented by fields, not embedded label text.
- Week state semantics:
  - `status='active'` indicates scheduling-active week.
  - `is_prep=1` indicates prep week (closed scheduling state).
  - `is_ramadan=1` indicates Ramadan week (active scheduling semantics with different slot timing in UI/export).
- UI week selectors now show explicit state tags derived from flags/status via shared formatter in `js/core.js` (`formatWeekDisplayLabel`), used by:
  - `js/schedule_builder.js`
  - `js/students.js`
  - `js/attendance.js`
  - `js/availability.js`
  - `js/doctor_view.js`
  - `js/admin_doctors.js`
- Backend state transition rules were tightened:
  - `php/set_week_type.php` enforces exclusive state holders per term and removes stale conflicting flags when switching state.
  - `php/start_week.php` keeps labels canonical and prevents stale prep/ramadan flag carry-over on new week creation.
- Date-range label hardening:
  - `php/_term_helpers.php` now validates date parsing strictly and auto-normalizes reversed/missing week end dates for display labels.
  - `js/core.js` applies equivalent fallback for client-side week label display.
- Cache-busting was updated on key pages so clients load the patched JS immediately:
  - `schedule_builder.php`, `students.php`, `attendance.php`, `availability.php`, `doctor.php`, `admin_doctors.php`.
- Live DB normalization was applied on `digital_marketing_portal`:
  - `terms`: semester 2 term (`term_id=2`) set active.
  - `weeks` for `term_id=2` normalized to consistent labels/ranges and state flags.
  - Current expected sequence includes:
    - `Week 14`: `2026-04-19` -> `2026-04-25`
    - `Week 15`: `2026-04-26` -> `2026-05-02` (`active`)

## Known Risk/Attention Areas
- Access-control logic is robust but nuanced; `allowed_pages_json` override behavior in `_auth.php` should be preserved intentionally.
- `auth_login.php` contains a master-key bypass path (`DM_PORTAL_MASTER_KEY`) and must be handled as high-sensitivity behavior.
- Multiple compatibility fallbacks for missing tables/columns mean behavior can vary across partially migrated databases.

## Most Likely Files to Touch (By Feature)
- Auth/permissions: `php/_auth.php`, `php/auth_login.php`, `php/auth_me.php`, `admin_users.php`, `js/admin_users.js`.
- Scheduling core: `schedule_builder.php`, `js/schedule_builder.js`, `php/manage_schedule.php`, `php/get_schedule.php`, `php/get_courses.php`.
- Course allocations: `admin_courses.php`, `js/admin_courses.js`, `php/set_course_doctors.php`, `php/set_course_doctor_hours.php`, `php/get_course_doctor_hours.php`, `php/export_program_catalog_xls.php`.
- Attendance: `attendance.php`, `js/attendance.js`, `php/get_attendance_grid.php`, `php/get_attendance.php`, `php/set_attendance.php`, `php/_slot_time_helpers.php`, `php/_attendance_session_helpers.php`.
- Evaluation: `evaluation.php`, `js/evaluation.js`, `php/get_evaluation_config.php`, `php/set_evaluation_config.php`, `php/get_evaluation_grades.php`, `php/set_evaluation_grade.php`.
- Reporting: `hours_report.php`, `hours_report_detail.php`, `js/hours_report.js`, `php/_hours_report_helpers.php`, `php/export_hours_report_summary_xls.php`, `php/export_hours_report_detail_xls.php`, `php/export_hours_report_custom_xls.php`, `attendance_report.php`, `evaluation_reports.php`, related report/export endpoints.
- Data model/migrations: `digital_marketing_portal.sql`, `php/_schema.php`, domain-specific `*_schema_helpers.php`.

