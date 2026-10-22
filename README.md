# StudentHub — Smart Academic & Event Management Portal

StudentHub is a comprehensive college student management portal built with **PHP, MySQL, HTML5, CSS3, and JavaScript (Fetch API)** running locally on XAMPP.

---

## 🚀 Quick Setup on XAMPP (Windows)

1. **Start Local Server:**
   - Open **XAMPP Control Panel**.
   - Click **Start** for **Apache** and **MySQL** (both should turn green).

2. **Project Directory:**
   - Place this project folder at:  
     `C:\xampp\htdocs\StudentHub`

3. **Database Configuration:**
   - Open phpMyAdmin: [http://localhost/phpmyadmin](http://localhost/phpmyadmin)
   - Database name: `studenthub`
   - If setting up fresh, run `schema.sql`, `migration.sql`, and `migration_practicals_12_14.sql`.

4. **Access Website:**
   - Home Page: [http://localhost/StudentHub/index.html](http://localhost/StudentHub/index.html)

---

## 🔑 Default Login Credentials

| Role | Portal URL | Email Address | Password | Security Passcode |
| :--- | :--- | :--- | :--- | :--- |
| **Administrator** | [`admin_login.html`](http://localhost/StudentHub/admin_login.html) | `admin@studenthub.com` | `Admin@123` | — |
| **Admin Register** | [`admin_register.html`](http://localhost/StudentHub/admin_register.html) | — | — | `ADMIN2026` |
| **Student** | [`login.html`](http://localhost/StudentHub/login.html) | `aarav.test@example.com` | `Password@123` | — |
| **Student Register**| [`registration.html`](http://localhost/StudentHub/registration.html) | — | — | — |

---

## 📚 College Practicals Implemented

### Practical 8: Database Connection & Schema Design
- Centralized database configuration in [`php/db.php`](php/db.php) providing both **MySQLi** (`$conn`) and **PDO** (`$pdo`).
- Normalized relational schema: `students`, `events`, `registrations`, and `audit_logs`.
- Test connection page: [`php/test_connection.php`](http://localhost/StudentHub/php/test_connection.php).

### Practical 9: Secure User Registration
- Form: [`registration.html`](http://localhost/StudentHub/registration.html) $\rightarrow$ Processor: [`php/process.php`](php/process.php).
- Server-side validation (Name, 10-digit Mobile, Email, Course, Year, Gender).
- Duplicate email prevention with prepared statements.
- Password hashing with `password_hash($pass, PASSWORD_DEFAULT)` (BCrypt).
- CSRF token validation and Post/Redirect/Get (PRG) pattern with success view: [`php/register_success.php`](http://localhost/StudentHub/php/register_success.php).

### Practical 10: Secure Authentication & Role-Based Access Control (RBAC)
- Student Login: [`login.html`](http://localhost/StudentHub/login.html) $\rightarrow$ [`php/login.php`](php/login.php).
- Dedicated Admin Login: [`admin_login.html`](http://localhost/StudentHub/admin_login.html) $\rightarrow$ [`php/admin_login.php`](php/admin_login.php).
- Dedicated Admin Registration: [`admin_register.html`](http://localhost/StudentHub/admin_register.html) (requires security passcode `ADMIN2026`).
- Password verification using `password_verify()`.
- Session fixation protection using `session_regenerate_id(true)`.
- 15-minute inactivity session timeout and cache-control security headers.
- Secure Remember-Me using SHA-256 hashed tokens in MySQL.
- Secure Logout: [`php/logout.php`](php/logout.php).

### Practical 11: Student Management CRUD with Search, Filter & CSV Export
- Admin Student Management: [`php/manage_students.php`](http://localhost/StudentHub/php/manage_students.php).
- **Create**: Add new students with server-side validation.
- **Read**: Live search (name, email, course), multi-criteria filters (course, year, gender, status), pagination, and sequential **Sr. No.**.
- **Update**: Edit student details and optional password update.
- **Delete**: Soft Delete (sets `status = 'inactive'`) with instant Restore, and permanent Delete with confirmation dialog.
- **CSV Export**: One-click download with CSV formula/DDE injection protection (`manage_students.php?export=csv`).

### Practical 12: Event Management CRUD with Poster Upload & File Validation
- Admin Event Management: [`php/manage_events.php`](http://localhost/StudentHub/php/manage_events.php).
- **CRUD Operations**: Add, Edit, Delete, and Open/Closed status toggle for events.
- **Poster Image Upload**:
  - Validates file format: JPG, JPEG, PNG, WEBP.
  - Validates file size: Maximum 2MB.
  - Safe unique renaming (`event_<uniqid>_<timestamp>.<ext>`).
  - Saved in protected upload directory: [`uploads/posters/`](uploads/posters/).
  - Old poster files are automatically removed upon update or event deletion.
- Event Category and Open/Closed status filters.

### Practical 13: PHP JSON REST API & AJAX/Fetch-Based CRUD
- **REST API Endpoint**: [`php/api/events.php`](http://localhost/StudentHub/php/api/events.php).
  - Returns `application/json` with standard envelopes and HTTP status codes (200, 201, 400, 401, 404, 500).
  - Handles `GET` (list/search), `POST` (create), `PUT` (update), and `DELETE` (delete).
- **Interactive AJAX Interface**: [`php/ajax_events.php`](http://localhost/StudentHub/php/ajax_events.php).
  - Performs all CRUD operations asynchronously **without full page reload**.
  - Live search with 300ms **debounce**.
  - Loading indicators, modal forms, and real-time DOM updates.
  - Real-time **Fetch Network Log Console** displaying HTTP status codes and responses.

### Practical 14: Admin Dashboard with RBAC, Dynamic Menu & Audit Log
- Admin Dashboard: [`php/admin_dashboard.php`](http://localhost/StudentHub/php/admin_dashboard.php).
- **Dynamic SQL Aggregation**: Real-time counts for students, events, registrations, and audit logs.
- **Role-Based Dynamic Menu**: Navigation adapts automatically based on user role (Admin vs Student).
- **Active / Inactive Status Toggle**: Quick one-click status toggle from the dashboard table.
- **Analytics Chart**: Event distribution bar chart grouped by category (Workshop, Technical, Competition, Sports, Seminar).
- **System Audit Trail**: Dedicated log viewer at [`php/audit_logs.php`](http://localhost/StudentHub/php/audit_logs.php) recording admin actions (`STUDENT_CREATE`, `EVENT_UPDATE`, `LOGIN`, `LOGOUT`, etc.) with user email, timestamp, and IP address.

---

## 📁 Key File Structure

```text
StudentHub/
├── index.html                   # Website Home Page
├── about.html                   # About Page
├── courses.html                 # Academic Courses
├── events.html                  # Student Events Directory (API-Connected)
├── login.html                   # Student Portal Login
├── registration.html            # Student Portal Registration
├── admin_login.html             # Dedicated Administrator Login
├── admin_register.html          # Dedicated Administrator Registration
├── schema.sql                   # Initial Database Schema
├── migration.sql                # Practical 9-11 Migration
├── migration_practicals_12_14.sql # Practical 12-14 Migration
├── css/
│   ├── style.css                # Primary Theme Stylesheet
│   └── events.css               # Events Directory Stylesheet
├── js/
│   ├── script.js                # Core UI Scripts
│   └── events.js                # Events Fetch & Filter Logic
├── uploads/
│   ├── .htaccess                # Script execution protection
│   └── posters/                 # Uploaded Event Poster Images
└── php/
    ├── db.php                   # Database Connection (MySQLi + PDO)
    ├── auth.php                 # Security, RBAC, Sessions & Audit Logger
    ├── csrf.php                 # CSRF Token API Endpoint
    ├── login.php                # Student Login Processor
    ├── logout.php               # Secure Session Logout
    ├── process.php              # Student Registration Processor
    ├── register_success.php     # PRG Registration Success View
    ├── dashboard.php            # Student Dashboard
    ├── admin_login.php          # Admin Login Processor
    ├── admin_register_process.php # Admin Registration Processor
    ├── admin_dashboard.php      # Admin Analytics Dashboard (Practical 14)
    ├── manage_students.php      # Student CRUD & CSV Export (Practical 11)
    ├── manage_events.php        # Event CRUD with Poster Upload (Practical 12)
    ├── ajax_events.php          # Asynchronous Fetch CRUD UI (Practical 13)
    ├── audit_logs.php           # Audit Trail Viewer (Practical 14)
    └── api/
        └── events.php           # RESTful JSON API Endpoint (Practical 13)
```

---

## 🧪 Testing Summary

All features have been tested and verified automatically via local HTTP requests on XAMPP:
- **Practicals 9, 10, 11 Suite**: 21 Passed, 0 Failed.
- **Practicals 12, 13, 14 Suite**: 11 Passed, 0 Failed.
- Total: **32 Automated Tests Passed**.
