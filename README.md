# Telecaller Reporting & Lead Management CRM (TeleConnect Sales CRM)

A responsive, production-ready Telecaller Reporting, Lead Management CRM, and Audio Recording Management Web App built with **PHP, MySQL, Bootstrap 4, and AdminLTE 3**.

Designed specifically for sales teams, BPOs, and telecalling operations, this application runs seamlessly on **standard shared hosting (cPanel/Apache)** as well as VPS and local environments without requiring heavy frameworks like Laravel.

---

## 🌟 Key Features

### 📞 1. Calling Workspace & Lead Management (Employee Panel)
- **Top Summary Cards (Real-time Today Stats)**:
  - **Today's Call Attempts**: Total calls initiated today.
  - **Today's Connected Calls**: Productive connected calls (`Interested`, `Follow-up Required`, `Meeting Scheduled`, `Sales Closed`, `Not Interested`).
  - **Today's Follow-ups Scheduled**: Prospects requiring scheduled callback.
  - **Today's Meetings Scheduled**: Demos and appointments booked today.
  - **Today's Sales Closed**: Deals finalized and conversions won.
- **Assigned Leads Table**:
  - Filterable by Status, Follow-up Urgency (`Due Today`, `Overdue`, `New`), and search query.
  - Pagination and responsive design on desktop, tablet, and mobile.
  - Clickable phone links with `tel:` protocol for mobile dialing.
  - Instant **Details / Call** button to open the Lead Action Modal.

### 📱 2. Interactive Lead Details Popup Modal
- Complete lead profile (Name, Phone, Company, Email, City, Initial Notes).
- Clickable **Call Now** button (`tel:`) and clipboard copy button.
- Call outcome dropdown with 10 standard dispositions:
  - `Interested`
  - `Follow-up Required` (automatically displays mandatory **Follow-up Date & Time** picker)
  - `Meeting Scheduled`
  - `Sales Closed`
  - `Not Interested`
  - `Ringing`
  - `Not Answered`
  - `Busy`
  - `Switched Off`
  - `Invalid Number`
- Call duration auto-detection upon recording upload.
- Call discussion remarks and optional audio recording attachment.
- **Call History Timeline**: Full audit history of previous calls for that lead with inline audio player and speed controls.

### 📊 3. Daily Reports & Audio Recording Workflow
- **Today's Report** (`employee/today_report.php`): Form remains empty until the employee submits their daily statistics:
  - Total Calls
  - Call Attempts
  - Interested Leads
  - Meetings Booked
  - Sales Closed
  - Optional Shift Notes
- **Next Step Workflow**: Unlocks call recording uploads after daily numbers are entered.
- **Date-wise Reports** (`employee/reports.php`): Review past reports and listen to uploaded recordings with audio player.

### 🛡️ 4. Admin Management & Lead Distribution
- **Executive Dashboard** (`admin/index.php`):
  - Primary KPI Cards: Total Calls, Interested Leads, Meetings Booked, Sales Closed.
  - Pipeline Banner: Leads in CRM, Assigned vs Unassigned, and Active Follow-ups.
  - Secondary Metrics: Call Attempts, Uploaded Audio Files, Active Telecallers.
  - 7-Day Performance Trend Chart (via Chart.js).
  - Funnel Conversion Ratio Donut Chart.
  - Telecaller Performance Leaderboard.
  - Recent Call Recordings with inline playback.
- **Lead Import & Allocation** (`admin/leads.php`):
  - Upload CSV/Excel lead files with Name, Phone, Company, Email, City, Notes.
  - Automatic duplicate phone number detection (skip duplicates or update existing).
  - Telecaller assignment: Assign to specific employee, leave unassigned, or **Round-Robin Distribute** evenly across active employees.
  - Bulk lead selection and reassignment.
  - Downloadable sample CSV template (`assets/template_leads.csv`).
- **Employee Accounts & Credentials** (`admin/employees.php`):
  - Create, edit, activate/deactivate, and reset passwords for telecaller accounts.
- **Daily Performance Reports & CSV Export** (`admin/reports.php`):
  - Filter across telecallers, dates, and export entire datasets to CSV.
- **Call Recordings Quality Audit** (`admin/recordings.php`):
  - Search recordings by phone number, customer name, telecaller, and status.
  - In-browser playback with 1x, 1.5x, 2x speed toggles.
- **System Configuration** (`admin/settings.php`):
  - Configurable max audio upload size (MB) and allowed extensions.
  - Hosting environment diagnostics.

### 🕒 5. Attendance Management System
- **Daily Mandatory Attendance Check-In**:
  - Automatically displays a non-dismissible modal (`#mandatoryAttendanceModal`) when an employee logs in for the first time on or after 7:00 AM before accessing the workspace/dashboard.
  - Three distinct attendance statuses:
    - **Present**: Full working day (1.0 day weight).
    - **Half Day**: Partial shift of 4 hours (0.5 day weight).
    - **Absent**: Leave / not available for calling duties today (0.0 day weight).
  - Optional remarks/reasons (e.g., client field visit, approved sick leave, doctor appointment).
- **One Entry Per Day & 7:00 AM Daily Cutoff**:
  - Strictly limited to one submission per working day.
  - Eligibility resets every morning at 7:00 AM server timezone (`Asia/Kolkata`).
  - Working dates correctly attributed before and after the 7:00 AM boundary.
  - Server-side validation and database unique key constraint `UNIQUE(user_id, attendance_date)` prevent duplicate punches.
- **Employee Panel Attendance Section** (`employee/attendance.php`):
  - Today's Check-In Status Card with recorded punch time and IP.
  - KPI summary boxes: Total Full Days Present, Half Days, Absences, and Effective Attendance Rate (%).
  - Month and Year selector to view past attendance logs.
  - Detailed history table with day of the week, punch timestamp, IP, remarks, and admin correction audit status.
- **Admin Attendance Management & Correction** (`admin/attendance.php`):
  - Filterable by Date Range, Telecaller, and Attendance Status.
  - Quick KPI summary of today's attendance directly on the Admin Dashboard.
  - **Status Correction Modal**: Admin can adjust an employee's status (`Present`, `Half Day`, `Absent`) with **mandatory audit remarks** permanently stamped with `modified_by`, `modified_at`, and `admin_remarks`.
  - **Manual Entry Punch**: Admin can manually record attendance for employees who were unable to self-punch.
- **Complete Monthly Attendance Sheet Matrix** (`admin/attendance_sheet.php`):
  - Grid layout with **Employees as rows** and **Calendar dates (01..31) as columns**.
  - Visual status badges (`P` in green, `HD` in amber, `A` in red, `-` for unmarked/weekends).
  - Row summary totals: Total Present, Total Half Day, Total Absent, Effective Days, and Attendance Rate %.
  - **One-Click Excel / CSV Export**: Downloads complete UTF-8 formatted CSV matrix report.

---

## 🔐 Security Architecture

- **100% Prepared SQL Statements**: Implemented through PHP PDO, eliminating SQL Injection vulnerabilities.
- **Cross-Site Scripting (XSS) Prevention**: All user inputs sanitized with strict `htmlspecialchars` output encoding.
- **Cross-Site Request Forgery (CSRF) Protection**: Cryptographically secure CSRF tokens verified on all form submissions.
- **Session Security**: Cookies flagged `HttpOnly`, `use_only_cookies`, with automatic `session_regenerate_id(true)` upon authentication to prevent session fixation.
- **Safe Audio Upload Pipeline**:
  - File extension whitelist validation (`mp3`, `wav`, `m4a`, `ogg`, `aac`, `amr`, `webm`).
  - File MIME verification via PHP `finfo` (Fileinfo extension).
  - Server-side maximum file size enforcement.
  - Filename sanitization with random cryptographic tokens to prevent path traversal.
  - Upload directory protected by Apache `.htaccess` to block direct script execution (`.php`, `.phtml`, `.sh`, `.exe`).

---

## 📁 Directory Structure

```text
sales-kp/
├── admin/
│   ├── attendance.php       # Attendance logs, status corrections, and manual punches
│   ├── attendance_sheet.php # Monthly attendance sheet matrix with Excel/CSV export
│   ├── employees.php        # Employee CRUD & credentials management
│   ├── index.php            # Admin Dashboard (KPIs, Charts, Pipeline, Attendance)
│   ├── leads.php            # Leads management, CSV import, round-robin assignment
│   ├── recordings.php       # Call recordings search & audio player
│   ├── report_view.php      # Drill-down view of single daily report
│   ├── reports.php          # All daily reports with CSV export
│   └── settings.php         # System settings & hosting diagnostics
├── assets/
│   ├── css/
│   │   └── custom.css       # Custom styles, responsive tweaks, audio player UI
│   ├── js/
│   │   └── app.js           # Audio playback controller, duration auto-detector
│   └── template_leads.csv   # Downloadable CSV sample import template
├── config/
│   ├── config.php           # App configuration, upload limits, DB credentials
│   └── db.php               # Database connection manager (PDO MySQL & SQLite fallback)
├── employee/
│   ├── attendance.php       # Employee attendance history, monthly summary, & rate
│   ├── attendance_action.php# Action handler for daily attendance check-in
│   ├── index.php            # Redirects to workspace.php
│   ├── reports.php          # Date-wise historical reports with recording player
│   ├── today_report.php     # Today's report entry & recording upload workflow
│   └── workspace.php        # Calling Workspace (5 top summary cards, lead table)
├── includes/
│   ├── attendance_modal.php # Mandatory daily attendance check-in modal (7:00 AM cutoff)
│   ├── auth.php             # Session authentication, login/logout, role enforcement
│   ├── footer.php           # AdminLTE 3 footer & script inclusions
│   ├── functions.php        # Sanitization, attendance helpers, audio uploader
│   ├── header.php           # AdminLTE 3 header & responsive navigation bar
│   ├── lead_modal.php       # Interactive Lead Details & Call Logging Modal
│   └── sidebar.php          # Dynamic sidebar (Admin vs Employee navigation)
├── uploads/
│   ├── .htaccess            # Prohibits script execution in upload directory
│   └── recordings/          # Organized by {employee_code}/{YYYY-MM-DD}/
├── index.php                # Root router
├── lead_api.php             # JSON API for modal data and call logging
├── login.php                # Unified login portal with quick demo buttons
├── logout.php               # Session termination
├── schema.sql               # Production MySQL DDL schema and initial seed data
├── seed_demo_data.php       # Demo data generator for local testing
└── README.md                # Comprehensive documentation
```

---

## 🚀 Database Configuration & Production Credentials

The application is pre-configured in `config/config.php` for production:

```php
define('DB_DRIVER', 'mysql');
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'helnovexaa_kpsales');
define('DB_USER', 'helnovexaa_kpsales');
define('DB_PASS', 'Soumojit1234@');
define('DB_CHARSET', 'utf8mb4');
```

To set up the MySQL database on your server:
1. In cPanel phpMyAdmin, open database `helnovexaa_kpsales`.
2. Import `schema.sql`.

---

## 🔑 Default Login Credentials

| Role | Employee ID / Username | Password | Full Name | Notes |
|---|---|---|---|---|
| **Administrator** | `admin` | `Admin@123` | System Administrator | Full access to leads, employees, reports, recordings |
| **Telecaller** | `EMP001` | `telecaller123` | Rahul Sharma | Assigned leads & historical reports |
| **Telecaller** | `EMP002` | `telecaller123` | Priya Patel | Assigned leads & workspace testing |
