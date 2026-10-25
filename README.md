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
   - Migrations executed: `schema.sql`, `migration.sql`, `migration_practicals_12_14.sql`, and `migration_student_approvals_marks.sql`.

4. **Access Website:**
   - Home Page: [http://localhost/StudentHub/index.html](http://localhost/StudentHub/index.html)
   - Student Portal: [http://localhost/StudentHub/login.html](http://localhost/StudentHub/login.html)
   - Admin Console: [http://localhost/StudentHub/admin_login.html](http://localhost/StudentHub/admin_login.html)

---

## 🔑 Default Login Credentials

| Role | Portal URL | Email Address | Password | Security Passcode |
| :--- | :--- | :--- | :--- | :--- |
| **Administrator** | [`admin_login.html`](http://localhost/StudentHub/admin_login.html) | `admin@studenthub.com` | `Admin@123` | — |
| **Admin Register** | [`admin_register.html`](http://localhost/StudentHub/admin_register.html) | — | — | `ADMIN2026` |
| **Student (Active)** | [`login.html`](http://localhost/StudentHub/login.html) | `rahul@gmail.com` | `Rahul@123` | — |
| **Student Register**| [`registration.html`](http://localhost/StudentHub/registration.html) | — | — | — |

---

## 🌟 Key Features & Approval Workflows

### 1. Dynamic Student Profile Linking
- **URL**: [`profile.php`](http://localhost/StudentHub/profile.php) (or via Dashboard navbar `My Profile 👤`)
- When a student registers online, all submitted details (Full Name, Email, Mobile, Course, Year, Gender) along with an auto-generated **Enrollment Number** (`SH2026-XXXX`) are securely stored in MySQL.
- When logged into their student account, their profile automatically displays their exact registered details, enrollment number, account status, registered events count, and published marks count.
- Students can click **✏️ Edit Profile Details** to update their Name, Mobile, Date of Birth (DOB), Address, Course, Year, and Gender.

### 2. External Student Self-Registration & Admin Approval Workflow
- When an external student registers via [`registration.html`](http://localhost/StudentHub/registration.html), their account is created with `status = 'pending'` and `registration_source = 'self'`.
- **Login Guard**: If a pending student attempts to log in, access is blocked with a clear notice:
  > *"⏳ Your account is pending Administrator approval. Please wait for the admin to approve and activate your registration."*
- **Admin Review**:
  - The Administrator is alerted on the **Admin Dashboard** ([`php/admin_dashboard.php`](http://localhost/StudentHub/php/admin_dashboard.php)) and in **Student CRUD** ([`php/manage_students.php`](http://localhost/StudentHub/php/manage_students.php)) via a top notification banner:
    `🔔 X External Student Registration(s) Awaiting Approval!`
  - For every pending student, the Admin has two one-click options:
    - **`[✓ Add / Approve Student]`**: Activates the student (`status = 'active'`). The student can now log in immediately.
    - **`[✕ Remove / Reject]`**: Permanently removes and rejects the unapproved registration.

### 3. Student Marks Management & Conditional Display
- **Admin Side**:
  - In [`php/manage_students.php`](http://localhost/StudentHub/php/manage_students.php), the Admin can click **`[📊 Marks]`** on any active student record.
  - A modal opens showing all published marks for that student with options to:
    - Add subject scores: Semester, Subject Name, Subject Code, Internal Marks (max 40), External Marks (max 60), Total (100), and auto-calculated Grade (`A+`, `A`, `B+`, `B`, `C`, `P`, `F`).
    - Remove/delete any entered subject mark.
- **Student Side**:
  - **URL**: [`results.php`](http://localhost/StudentHub/results.php) (or via Student Dashboard `My Results 📊`).
  - **If marks have NOT been added by Admin**:
    - The page displays the clean empty state:
      `📊 Marks Not Added Yet` (`⚠️ Not add marks / Pending Evaluation`).
      No fake/dummy scores are shown.
  - **If marks HAVE been added by Admin**:
    - Displays real calculated metrics: **SGPA**, **Percentage**, **Total Score**, **Result Status (PASS/REAPPEAR)**, and the complete subject-wise marks table.

### 4. Events System & Conditional Display
- **URL**: [`events.html`](http://localhost/StudentHub/events.html)
- Connected to MySQL database via [`php/api/events.php`](http://localhost/StudentHub/php/api/events.php).
- **If no events exist in the database**:
  - Displays: `📅 No New Events Added` (`Not new event add / Waiting for Admin`).
- **When events are added by Admin**:
  - Real event cards with poster images, venue, date, and category filters are rendered dynamically.
- In **Student Dashboard** ([`php/dashboard.php`](http://localhost/StudentHub/php/dashboard.php)), the "Recent Academic Activity" feed reflects whether events and marks are added or pending.

---

## 📚 College Practicals Summary (8 through 14)

- **Practical 8**: MySQLi (`$conn`) & PDO (`$pdo`) database connection in [`php/db.php`](php/db.php).
- **Practical 9**: Secure registration with prepared statements, validation, and `password_hash()`.
- **Practical 10**: Secure authentication with `password_verify()`, session regeneration, 15m timeout, and RBAC.
- **Practical 11**: Student CRUD management with live search, filters, pagination, CSV export, and Approval workflow.
- **Practical 12**: Event CRUD with poster file upload (JPG/PNG/WEBP, 2MB max) in [`php/manage_events.php`](php/manage_events.php).
- **Practical 13**: RESTful JSON API ([`php/api/events.php`](php/api/events.php)) & Fetch-based Async CRUD ([`php/ajax_events.php`](php/ajax_events.php)).
- **Practical 14**: Admin dashboard with dynamic SQL metrics, event analytics chart, and audit logging in [`php/audit_logs.php`](php/audit_logs.php).

---

## 🧪 Step-by-Step Testing Guide

### Test Case 1: Student Self-Registration & Approval
1. Go to [http://localhost/StudentHub/registration.html](http://localhost/StudentHub/registration.html).
2. Register a new student (e.g. `Kavya Patel`, `kavya@example.com`, `9876543210`, `BCA`, Year `1`, Female, `Password@123`).
3. Note the success page: *"Registration Received — Pending Admin Approval"*.
4. Go to [http://localhost/StudentHub/login.html](http://localhost/StudentHub/login.html) and attempt to log in with `kavya@example.com` / `Password@123`.
5. Observe rejection: *"⏳ Your account is pending Administrator approval"*.
6. Open [http://localhost/StudentHub/admin_login.html](http://localhost/StudentHub/admin_login.html) and log in as Admin (`admin@studenthub.com` / `Admin@123`).
7. Observe pending registration alert banner on Admin Dashboard.
8. Click **Review & Approve Students »** or go to `manage_students.php`.
9. Click **`[✓ Add / Approve Student]`**.
10. Log in again as `kavya@example.com` $ightarrow$ Login succeeds and opens Student Dashboard!

### Test Case 2: Profile Viewing & Editing
1. From Student Dashboard, click **My Profile 👤** or visit [http://localhost/StudentHub/profile.php](http://localhost/StudentHub/profile.php).
2. Observe all registered information (Name, Email, Mobile, Course, Year, Gender, Enrollment No).
3. Click **✏️ Edit Profile Details**, update Address or DOB, and save.
4. Verify changes are updated in the database and visible on page.

### Test Case 3: Marks Management ("Not Add Marks" $ightarrow$ Published Scorecard)
1. In Student Portal, click **My Results 📊** or visit [http://localhost/StudentHub/results.php](http://localhost/StudentHub/results.php).
2. Observe empty state: *"Marks Not Added Yet / Not add marks / Pending Evaluation"*.
3. In another tab or Admin login, go to [`php/manage_students.php`](http://localhost/StudentHub/php/manage_students.php).
4. Click **`[📊 Marks]`** next to that student's row.
5. In the modal, add a subject (e.g., `Web Development Technologies`, Internal: `36`, External: `54`).
6. Click **+ Save Subject Mark**.
7. Refresh student's results page $ightarrow$ real SGPA, percentage, and subject scorecard appear immediately!
