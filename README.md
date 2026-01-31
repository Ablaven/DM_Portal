<div align="center">

# Digital Marketing Portal

**A full-featured scheduling and management portal for Digital Marketing programs**  
PHP + MySQL backend, vanilla JS/CSS frontend, with role-based access and admin tooling.

[![Stack](https://img.shields.io/badge/Stack-PHP%20%2B%20MySQL-4c6fff?style=for-the-badge)](#)
[![UI](https://img.shields.io/badge/UI-Vanilla%20JS%20%2B%20CSS-2ed573?style=for-the-badge)](#)
[![Status](https://img.shields.io/badge/Status-Active-ff6b81?style=for-the-badge)](#)

</div>

---

## ✨ What this portal does

- **Admin management** for courses, doctors, students, users, and schedule building
- **Doctor view** for personal teaching schedule
- **Student view** for schedule and grades dashboard
- **Attendance system** with export tools
- **Evaluation/grades system** with detailed per‑item scoring
- **Theme system** with light/dark support

---

## 🧭 Key pages

- `index.php` → Course Dashboard
- `schedule_builder.php` → Admin schedule builder
- `admin_courses.php` → Course management (incl. coefficients)
- `admin_doctors.php` → Doctor management
- `admin_students.php` → Student management
- `admin_users.php` → User accounts + permissions
- `doctor.php` → Doctor schedule view
- `students.php` → Student schedule
- `student_dashboard.php` → Student grades & insights
- `attendance.php` → Attendance tracking + exports

---

## ⚙️ Setup (local)

1. Install **XAMPP** (or any Apache + PHP + MySQL stack)
2. Place the portal inside your `htdocs` folder
3. Import the database file:
   - `digital_marketing_portal.sql`
4. Update DB connection in `php/db_connect.php` if needed
5. Open the site in your browser:
   - `http://localhost/Digital%20Marketing%20Portal/`

---

## 🧩 Patcher (Installer / Updater)

A dedicated **patcher app** is included to simplify installation and updates.

### ✅ What it does
- **Install** → Downloads the latest repo and deploys to `htdocs`
- **Update** → Syncs only changed files
- **Uninstall** → Clears the portal from `htdocs`
- **Status** → Shows **Up to date** / **Out of date**

<div align="center">

**Built for fast scheduling, clear reporting, and smooth admin workflows.**

</div>
