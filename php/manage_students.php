<?php
/**
 * StudentHub — Practical 11: Student Management CRUD with Search, Filter & CSV Export
 * Strictly guarded by require_admin().
 * Features:
 *  - Create, Read, Update, Delete (Soft & Hard Delete with confirmation)
 *  - Prepared statements for ALL SQL operations
 *  - Live search by Name, Email, Course
 *  - Multi-criteria filtering by Course, Academic Year, Gender, and Status
 *  - Pagination (10 records/page)
 *  - CSV Export with protection against CSV Formula Injection
 *  - CSRF protection on all mutating actions
 *  - Consistent styling with StudentHub Theme
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

start_secure_session();
require_admin();

$csrf_token = get_csrf_token();
$flash_success = $_SESSION['flash_success'] ?? "";
$flash_error   = $_SESSION['flash_error'] ?? "";
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// =========================================================================
// 1. CSV EXPORT HANDLER (With Formula Injection Protection)
// =========================================================================
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    // Sanitizer function to guard against CSV Formula / DDE Injection
    function sanitize_csv_field($val) {
        $str = (string)$val;
        if (isset($str[0]) && in_array($str[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $str;
        }
        return $str;
    }

    $filename = "studenthub_students_" . date('Y-m-d_His') . ".csv";

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    // UTF-8 BOM for clean Excel display
    fputs($output, "\xEF\xBB\xBF");

    // CSV Column Headers
    fputcsv($output, ['Student ID', 'Full Name', 'Email Address', 'Mobile', 'Course', 'Year', 'Gender', 'Role', 'Status']);

    $export_stmt = $conn->prepare("SELECT student_id, name, email, mobile, course, year, gender, role, status FROM students WHERE role = 'student' ORDER BY student_id ASC");
    if ($export_stmt) {
        $export_stmt->execute();
        $export_res = $export_stmt->get_result();
        while ($row = $export_res->fetch_assoc()) {
            fputcsv($output, [
                sanitize_csv_field($row['student_id']),
                sanitize_csv_field($row['name']),
                sanitize_csv_field($row['email']),
                sanitize_csv_field($row['mobile']),
                sanitize_csv_field($row['course']),
                sanitize_csv_field($row['year']),
                sanitize_csv_field($row['gender']),
                sanitize_csv_field($row['role']),
                sanitize_csv_field($row['status'])
            ]);
        }
        $export_stmt->close();
    }

    fclose($output);
    exit;
}

// =========================================================================
// 2. PROCESS MUTATING ACTIONS (POST: ADD, EDIT, SOFT_DELETE, RESTORE, DELETE)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submitted_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($submitted_token)) {
        $_SESSION['flash_error'] = "Security validation failed (CSRF mismatch). Request aborted.";
        header("Location: manage_students.php");
        exit;
    }

    $action = $_POST['action'] ?? '';

    // --- A. CREATE STUDENT ---
    if ($action === 'create') {
        $name     = trim($_POST['name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $mobile   = trim($_POST['mobile'] ?? '');
        $course   = trim($_POST['course'] ?? '');
        $year     = trim($_POST['year'] ?? '');
        $gender   = trim($_POST['gender'] ?? '');
        $status   = trim($_POST['status'] ?? 'active');
        $password = $_POST['password'] ?? '';

        $errors = [];
        if ($name === '' || !preg_match("/^[A-Za-z ]{2,50}$/", $name)) {
            $errors[] = "Valid student name (2-50 letters/spaces) is required.";
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "A valid email address is required.";
        }
        if ($mobile === '' || !preg_match("/^[6-9][0-9]{9}$/", $mobile)) {
            $errors[] = "Valid 10-digit mobile number starting with 6-9 is required.";
        }
        if ($course === '') $errors[] = "Course selection is required.";
        if ($year === '') $errors[] = "Academic year selection is required.";
        if (!in_array($gender, ['Male', 'Female', 'Other'], true)) $errors[] = "Valid gender is required.";
        if (!in_array($status, ['active', 'inactive'], true)) $status = 'active';
        if (strlen($password) < 6) $errors[] = "Temporary password must be at least 6 characters.";

        if (!empty($errors)) {
            $_SESSION['flash_error'] = implode(" ", $errors);
            header("Location: manage_students.php");
            exit;
        }

        // Check duplicate email
        $chk = $conn->prepare("SELECT student_id FROM students WHERE email = ? LIMIT 1");
        $chk->bind_param("s", $email);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            $chk->close();
            $_SESSION['flash_error'] = "A student with email '$email' already exists.";
            header("Location: manage_students.php");
            exit;
        }
        $chk->close();

        // Hash and insert
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $ins = $conn->prepare("INSERT INTO students (name, email, mobile, course, year, gender, role, status, password) VALUES (?, ?, ?, ?, ?, ?, 'student', ?, ?)");
        $ins->bind_param("ssssssss", $name, $email, $mobile, $course, $year, $gender, $status, $hashed);

        if ($ins->execute()) {
            $new_id = $conn->insert_id;
            $_SESSION['flash_success'] = "Student '$name' (#$new_id) created successfully!";
            log_audit_action($conn, 'STUDENT_CREATE', "Created student #$new_id ($name, $email).");
        } else {
            $_SESSION['flash_error'] = "Database insert failed: " . $conn->error;
        }
        $ins->close();
        header("Location: manage_students.php");
        exit;
    }

    // --- B. UPDATE STUDENT ---
    if ($action === 'update') {
        $student_id = (int)($_POST['student_id'] ?? 0);
        $name       = trim($_POST['name'] ?? '');
        $email      = trim($_POST['email'] ?? '');
        $mobile     = trim($_POST['mobile'] ?? '');
        $course     = trim($_POST['course'] ?? '');
        $year       = trim($_POST['year'] ?? '');
        $gender     = trim($_POST['gender'] ?? '');
        $status     = trim($_POST['status'] ?? 'active');
        $new_pwd    = $_POST['new_password'] ?? '';

        if ($student_id <= 0) {
            $_SESSION['flash_error'] = "Invalid student ID.";
            header("Location: manage_students.php");
            exit;
        }

        $errors = [];
        if ($name === '' || !preg_match("/^[A-Za-z ]{2,50}$/", $name)) $errors[] = "Valid student name is required.";
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required.";
        if ($mobile === '' || !preg_match("/^[6-9][0-9]{9}$/", $mobile)) $errors[] = "Valid 10-digit mobile is required.";
        if (!in_array($status, ['active', 'inactive'], true)) $status = 'active';

        if (!empty($errors)) {
            $_SESSION['flash_error'] = implode(" ", $errors);
            header("Location: manage_students.php");
            exit;
        }

        // Check if email taken by someone else
        $chk = $conn->prepare("SELECT student_id FROM students WHERE email = ? AND student_id != ? LIMIT 1");
        $chk->bind_param("si", $email, $student_id);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            $chk->close();
            $_SESSION['flash_error'] = "Email '$email' is already in use by another user.";
            header("Location: manage_students.php");
            exit;
        }
        $chk->close();

        // Update with or without new password
        if (!empty($new_pwd)) {
            if (strlen($new_pwd) < 6) {
                $_SESSION['flash_error'] = "New password must be at least 6 characters.";
                header("Location: manage_students.php");
                exit;
            }
            $hashed = password_hash($new_pwd, PASSWORD_DEFAULT);
            $upd = $conn->prepare("UPDATE students SET name = ?, email = ?, mobile = ?, course = ?, year = ?, gender = ?, status = ?, password = ? WHERE student_id = ?");
            $upd->bind_param("ssssssssi", $name, $email, $mobile, $course, $year, $gender, $status, $hashed, $student_id);
        } else {
            $upd = $conn->prepare("UPDATE students SET name = ?, email = ?, mobile = ?, course = ?, year = ?, gender = ?, status = ? WHERE student_id = ?");
            $upd->bind_param("sssssssi", $name, $email, $mobile, $course, $year, $gender, $status, $student_id);
        }

        if ($upd->execute()) {
            $_SESSION['flash_success'] = "Student #$student_id updated successfully!";
            log_audit_action($conn, 'STUDENT_UPDATE', "Updated student #$student_id ($name).");
        } else {
            $_SESSION['flash_error'] = "Update failed: " . $conn->error;
        }
        $upd->close();
        header("Location: manage_students.php");
        exit;
    }

    // --- C. SOFT DELETE (Deactivate) ---
    if ($action === 'soft_delete') {
        $student_id = (int)($_POST['student_id'] ?? 0);
        if ($student_id <= 0) {
            $_SESSION['flash_error'] = "Invalid student ID.";
        } else {
            $stmt = $conn->prepare("UPDATE students SET status = 'inactive' WHERE student_id = ? AND role = 'student'");
            $stmt->bind_param("i", $student_id);
            if ($stmt->execute()) {
                $_SESSION['flash_success'] = "Student #$student_id has been deactivated (Soft Deleted).";
                log_audit_action($conn, 'STUDENT_SOFT_DELETE', "Deactivated student #$student_id.");
            }
            $stmt->close();
        }
        header("Location: manage_students.php");
        exit;
    }

    // --- D. RESTORE (Activate) ---
    if ($action === 'restore') {
        $student_id = (int)($_POST['student_id'] ?? 0);
        if ($student_id <= 0) {
            $_SESSION['flash_error'] = "Invalid student ID.";
        } else {
            $stmt = $conn->prepare("UPDATE students SET status = 'active' WHERE student_id = ? AND role = 'student'");
            $stmt->bind_param("i", $student_id);
            if ($stmt->execute()) {
                $_SESSION['flash_success'] = "Student #$student_id has been restored to Active status.";
                log_audit_action($conn, 'STUDENT_RESTORE', "Restored student #$student_id to active.");
            }
            $stmt->close();
        }
        header("Location: manage_students.php");
        exit;
    }

    // --- E. HARD DELETE (Permanent removal) ---
    if ($action === 'hard_delete') {
        $student_id = (int)($_POST['student_id'] ?? 0);
        if ($student_id <= 0) {
            $_SESSION['flash_error'] = "Invalid student ID.";
        } else {
            $stmt = $conn->prepare("DELETE FROM students WHERE student_id = ? AND role = 'student'");
            $stmt->bind_param("i", $student_id);
            if ($stmt->execute()) {
                $_SESSION['flash_success'] = "Student #$student_id permanently deleted from database.";
                log_audit_action($conn, 'STUDENT_HARD_DELETE', "Permanently deleted student #$student_id.");
            } else {
                $_SESSION['flash_error'] = "Failed to delete student: " . $conn->error;
            }
            $stmt->close();
        }
        header("Location: manage_students.php");
        exit;
    }
}

// =========================================================================
// 3. SEARCH, FILTER & PAGINATION PARAMETERS
// =========================================================================
$search_query = trim($_GET['search'] ?? '');
$filter_course = trim($_GET['course'] ?? '');
$filter_year   = trim($_GET['year'] ?? '');
$filter_gender = trim($_GET['gender'] ?? '');
$filter_status = trim($_GET['status'] ?? '');

$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 8;
$offset = ($page - 1) * $per_page;

// Build Dynamic Prepared Query
$where_clauses = ["role = 'student'"];
$param_types = "";
$param_values = [];

// Search condition: name, email, or course
if ($search_query !== "") {
    $where_clauses[] = "(name LIKE ? OR email LIKE ? OR course LIKE ?)";
    $like_term = "%" . $search_query . "%";
    $param_types .= "sss";
    $param_values[] = $like_term;
    $param_values[] = $like_term;
    $param_values[] = $like_term;
}

// Course filter
if ($filter_course !== "") {
    $where_clauses[] = "course = ?";
    $param_types .= "s";
    $param_values[] = $filter_course;
}

// Year filter
if ($filter_year !== "") {
    $where_clauses[] = "year = ?";
    $param_types .= "s";
    $param_values[] = $filter_year;
}

// Gender filter
if ($filter_gender !== "") {
    $where_clauses[] = "gender = ?";
    $param_types .= "s";
    $param_values[] = $filter_gender;
}

// Status filter
if ($filter_status !== "" && in_array($filter_status, ['active', 'inactive'], true)) {
    $where_clauses[] = "status = ?";
    $param_types .= "s";
    $param_values[] = $filter_status;
}

$where_sql = implode(" AND ", $where_clauses);

// Count Total Matches
$count_sql = "SELECT COUNT(*) AS total FROM students WHERE " . $where_sql;
$count_stmt = $conn->prepare($count_sql);
if ($param_types !== "") {
    $count_stmt->bind_param($param_types, ...$param_values);
}
$count_stmt->execute();
$total_records = (int)$count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();

$total_pages = max(1, (int)ceil($total_records / $per_page));

// Fetch Paginated Records
$data_sql = "SELECT student_id, name, email, mobile, course, year, gender, status 
             FROM students 
             WHERE $where_sql 
             ORDER BY student_id ASC 
             LIMIT ? OFFSET ?";

$data_types = $param_types . "ii";
$data_params = array_merge($param_values, [$per_page, $offset]);

$data_stmt = $conn->prepare($data_sql);
$data_stmt->bind_param($data_types, ...$data_params);
$data_stmt->execute();
$result_set = $data_stmt->get_result();

$students = [];
while ($row = $result_set->fetch_assoc()) {
    $students[] = $row;
}
$data_stmt->close();

// Distinct courses for filter dropdown
$course_options = [];
$c_res = $conn->query("SELECT DISTINCT course FROM students WHERE course IS NOT NULL AND course != '' ORDER BY course");
if ($c_res) {
    while ($cr = $c_res->fetch_assoc()) {
        $course_options[] = $cr['course'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Students - Practical 11 CRUD</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Poppins',-apple-system,sans-serif; }
        body { background: #f4f6fa; color: #1e293b; }

        /* Navbar */
        nav.admin-nav {
            background: #0f172a;
            color: white;
            padding: 16px 8%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .brand-text { font-size: 24px; font-weight: 700; color: #818cf8; }
        .admin-tag { background: #ef4444; color: white; font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 12px; text-transform: uppercase; margin-left: 8px; }
        .admin-nav-links { display: flex; align-items: center; gap: 20px; }
        .admin-nav-links a { color: #cbd5e1; text-decoration: none; font-size: 14px; font-weight: 500; }
        .admin-nav-links a:hover { color: white; }
        .btn-logout { background: #ef4444 !important; color: white !important; padding: 7px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; text-decoration: none; }

        /* Container */
        .page-wrap { max-width: 1350px; margin: 35px auto; padding: 0 4%; }

        /* Alerts */
        .alert { padding: 14px 18px; border-radius: 10px; margin-bottom: 22px; font-size: 14px; display: flex; align-items: center; justify-content: space-between; }
        .alert-success { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
        .alert-danger { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }

        /* Top Bar */
        .crud-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 25px; }
        .crud-header h1 { font-size: 26px; color: #0f172a; }
        .crud-header p { font-size: 14px; color: #64748b; }
        .btn-add { background: #6C63FF; color: white; padding: 11px 22px; border-radius: 10px; border: none; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; font-size: 14px; }
        .btn-add:hover { background: #5148d8; }
        .btn-export { background: #059669; color: white; padding: 11px 20px; border-radius: 10px; text-decoration: none; font-weight: 600; font-size: 14px; display: inline-flex; align-items: center; gap: 8px; }
        .btn-export:hover { background: #047857; }

        /* Search & Filter Box */
        .filter-panel { background: white; padding: 22px 26px; border-radius: 16px; box-shadow: 0 4px 15px rgba(0,0,0,0.04); border: 1px solid #e2e8f0; margin-bottom: 25px; }
        .filter-form { display: grid; grid-template-columns: 2fr repeat(4, 1fr) auto auto; gap: 12px; align-items: center; }
        @media (max-width: 1024px) {
            .filter-form { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 640px) {
            .filter-form { grid-template-columns: 1fr; }
        }
        .filter-input, .filter-select { padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; color: #1e293b; background: white; width: 100%; }
        .btn-filter { background: #2563eb; color: white; border: none; padding: 10px 18px; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 13px; }
        .btn-reset { background: #f1f5f9; color: #475569; padding: 10px 16px; border-radius: 8px; text-decoration: none; font-size: 13px; text-align: center; border: 1px solid #cbd5e1; }

        /* Table Card */
        .table-card { background: white; border-radius: 16px; box-shadow: 0 4px 15px rgba(0,0,0,0.04); border: 1px solid #e2e8f0; overflow: hidden; }
        .table-responsive { overflow-x: auto; }
        table.crud-table { width: 100%; border-collapse: collapse; font-size: 13px; text-align: left; }
        table.crud-table th { background: #f8fafc; color: #475569; font-weight: 600; padding: 14px 16px; border-bottom: 2px solid #e2e8f0; white-space: nowrap; }
        table.crud-table td { padding: 14px 16px; border-bottom: 1px solid #f1f5f9; color: #334155; vertical-align: middle; }
        table.crud-table tr:hover { background: #fbfcfe; }

        /* Status & Badges */
        .badge { padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
        .badge-active { background: #dcfce7; color: #15803d; }
        .badge-inactive { background: #fee2e2; color: #b91c1c; }

        /* Action Buttons */
        .actions-wrap { display: flex; gap: 8px; align-items: center; }
        .btn-action { padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; border: none; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; }
        .btn-edit { background: #dbeafe; color: #1e40af; }
        .btn-edit:hover { background: #bfdbfe; }
        .btn-soft { background: #fed7aa; color: #9a3412; }
        .btn-soft:hover { background: #fdba74; }
        .btn-restore { background: #dcfce7; color: #15803d; }
        .btn-restore:hover { background: #bbf7d0; }
        .btn-hard { background: #fee2e2; color: #b91c1c; }
        .btn-hard:hover { background: #fecaca; }

        /* Pagination */
        .pagination-bar { display: flex; justify-content: space-between; align-items: center; padding: 18px 24px; background: #fafafa; border-top: 1px solid #e2e8f0; font-size: 13px; color: #64748b; flex-wrap: wrap; gap: 12px; }
        .page-links { display: flex; gap: 6px; }
        .page-link { padding: 6px 12px; border: 1px solid #cbd5e1; border-radius: 6px; text-decoration: none; color: #334155; font-size: 12px; }
        .page-link.active { background: #6C63FF; color: white; border-color: #6C63FF; }

        /* Modal Styles */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(15,23,42,0.5); backdrop-filter: blur(4px); align-items: center; justify-content: center; }
        .modal.open { display: flex; }
        .modal-box { background: white; border-radius: 18px; width: 95%; max-width: 600px; padding: 30px; box-shadow: 0 20px 50px rgba(0,0,0,0.2); max-height: 90vh; overflow-y: auto; }
        .modal-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 1px solid #e2e8f0; }
        .modal-head h3 { font-size: 20px; color: #0f172a; }
        .modal-close { background: none; border: none; font-size: 24px; color: #64748b; cursor: pointer; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px; }
        .form-full { grid-column: span 2; }
        .form-group label { display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 6px; text-transform: uppercase; }
        .form-group input, .form-group select { width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; }
        .modal-footer { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; padding-top: 15px; border-top: 1px solid #e2e8f0; }
        .btn-cancel { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; padding: 10px 18px; border-radius: 8px; cursor: pointer; font-size: 13px; }
        .btn-submit { background: #6C63FF; color: white; border: none; padding: 10px 22px; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 13px; }
    </style>
</head>
<body>

    <!-- NAVBAR -->
    <nav class="admin-nav">
        <div>
            <span class="brand-text">StudentHub</span>
            <span class="admin-tag">Administrator</span>
        </div>
        <div class="admin-nav-links">
            <a href="admin_dashboard.php">Dashboard</a>
            <a href="manage_students.php" style="color:white; font-weight:700;">Students CRUD</a>
            <a href="../events.html">Events</a>
            <a href="dashboard.php">Student View</a>
            <a href="logout.php" class="btn-logout">Logout ⏻</a>
        </div>
    </nav>

    <!-- CONTENT -->
    <div class="page-wrap">

        <!-- Flash Messages -->
        <?php if (!empty($flash_success)): ?>
            <div class="alert alert-success">
                <span>✓ <?= e($flash_success) ?></span>
                <span style="cursor:pointer;" onclick="this.parentElement.style.display='none'">✕</span>
            </div>
        <?php endif; ?>

        <?php if (!empty($flash_error)): ?>
            <div class="alert alert-danger">
                <span>⚠ <?= e($flash_error) ?></span>
                <span style="cursor:pointer;" onclick="this.parentElement.style.display='none'">✕</span>
            </div>
        <?php endif; ?>

        <!-- Title & Action Buttons -->
        <div class="crud-header">
            <div>
                <h1>Student Records Management</h1>
                <p>Practical 11: Real-time search, multi-attribute filtering, CRUD operations & secure CSV export.</p>
            </div>
            <div style="display:flex; gap:12px;">
                <button type="button" class="btn-add" onclick="openCreateModal()">
                    ＋ Add New Student
                </button>
                <a href="manage_students.php?export=csv" class="btn-export">
                    ⬇ Export CSV
                </a>
            </div>
        </div>

        <!-- SEARCH & FILTER BAR -->
        <div class="filter-panel">
            <form method="GET" action="manage_students.php" class="filter-form">
                <div>
                    <input type="text" name="search" class="filter-input" placeholder="🔍 Search Name, Email, Course..." value="<?= e($search_query) ?>">
                </div>

                <div>
                    <select name="course" class="filter-select">
                        <option value="">All Courses</option>
                        <?php foreach ($course_options as $c): ?>
                            <option value="<?= e($c) ?>" <?= $filter_course === $c ? 'selected' : '' ?>><?= e($c) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <select name="year" class="filter-select">
                        <option value="">All Years</option>
                        <option value="1" <?= $filter_year === '1' ? 'selected' : '' ?>>Year 1</option>
                        <option value="2" <?= $filter_year === '2' ? 'selected' : '' ?>>Year 2</option>
                        <option value="3" <?= $filter_year === '3' ? 'selected' : '' ?>>Year 3</option>
                        <option value="4" <?= $filter_year === '4' ? 'selected' : '' ?>>Year 4</option>
                    </select>
                </div>

                <div>
                    <select name="gender" class="filter-select">
                        <option value="">All Genders</option>
                        <option value="Male" <?= $filter_gender === 'Male' ? 'selected' : '' ?>>Male</option>
                        <option value="Female" <?= $filter_gender === 'Female' ? 'selected' : '' ?>>Female</option>
                        <option value="Other" <?= $filter_gender === 'Other' ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>

                <div>
                    <select name="status" class="filter-select">
                        <option value="">All Statuses</option>
                        <option value="active" <?= $filter_status === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $filter_status === 'inactive' ? 'selected' : '' ?>>Inactive (Soft-Deleted)</option>
                    </select>
                </div>

                <div>
                    <button type="submit" class="btn-filter">Apply</button>
                </div>

                <div>
                    <a href="manage_students.php" class="btn-reset">Reset</a>
                </div>
            </form>
        </div>

        <!-- STUDENT RECORDS TABLE -->
        <div class="table-card">
            <div class="table-responsive">
                <table class="crud-table">
                    <thead>
                        <tr>
                            <th style="width:50px;">Sr.</th>
                            <th>ID</th>
                            <th>Full Name</th>
                            <th>Email Address</th>
                            <th>Mobile</th>
                            <th>Course</th>
                            <th>Year</th>
                            <th>Gender</th>
                            <th>Status</th>
                            <th style="text-align:center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($students)): ?>
                            <tr>
                                <td colspan="10" style="text-align:center; padding:35px; color:#64748b;">
                                    No student records match your search or filter criteria.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $counter = $offset; foreach ($students as $st): $counter++; ?>
                                <tr>
                                    <td style="color:#64748b; font-weight:600;"><?= $counter ?></td>
                                    <td><strong>#<?= e($st['student_id']) ?></strong></td>
                                    <td><strong style="color:#0f172a;"><?= e($st['name']) ?></strong></td>
                                    <td><?= e($st['email']) ?></td>
                                    <td><?= e($st['mobile']) ?></td>
                                    <td><?= e($st['course']) ?></td>
                                    <td>Year <?= e($st['year']) ?></td>
                                    <td><?= e($st['gender']) ?></td>
                                    <td>
                                        <span class="badge <?= $st['status'] === 'active' ? 'badge-active' : 'badge-inactive' ?>">
                                            <?= ucfirst(e($st['status'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="actions-wrap" style="justify-content:center;">
                                            <!-- Edit Button -->
                                            <button type="button" class="btn-action btn-edit" onclick='openEditModal(<?= json_encode($st, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                                                ✏ Edit
                                            </button>

                                            <!-- Soft Delete / Restore -->
                                            <?php if ($st['status'] === 'active'): ?>
                                                <form method="POST" action="manage_students.php" style="display:inline;" onsubmit="return confirm('Soft-delete student #<?= e($st['student_id']) ?> (<?= e($st['name']) ?>)? This will set status to inactive.');">
                                                    <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                                                    <input type="hidden" name="action" value="soft_delete">
                                                    <input type="hidden" name="student_id" value="<?= e($st['student_id']) ?>">
                                                    <button type="submit" class="btn-action btn-soft">⏸ Soft Delete</button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST" action="manage_students.php" style="display:inline;">
                                                    <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                                                    <input type="hidden" name="action" value="restore">
                                                    <input type="hidden" name="student_id" value="<?= e($st['student_id']) ?>">
                                                    <button type="submit" class="btn-action btn-restore">▶ Restore</button>
                                                </form>
                                            <?php endif; ?>

                                            <!-- Hard Delete with Explicit Confirmation -->
                                            <form method="POST" action="manage_students.php" style="display:inline;" onsubmit="return confirm('PERMANENT DELETE: Are you completely sure you want to permanently delete student #<?= e($st['student_id']) ?>? This cannot be undone.');">
                                                <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                                                <input type="hidden" name="action" value="hard_delete">
                                                <input type="hidden" name="student_id" value="<?= e($st['student_id']) ?>">
                                                <button type="submit" class="btn-action btn-hard">🗑 Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- PAGINATION -->
            <div class="pagination-bar">
                <div>
                    Showing <strong><?= count($students) ?></strong> of <strong><?= $total_records ?></strong> students (Page <?= $page ?> of <?= $total_pages ?>)
                </div>
                <div class="page-links">
                    <?php if ($page > 1): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="page-link">« Prev</a>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" class="page-link <?= $i === $page ? 'active' : '' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="page-link">Next »</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>

    <!-- ================= CREATE STUDENT MODAL ================= -->
    <div id="createModal" class="modal">
        <div class="modal-box">
            <div class="modal-head">
                <h3>Add New Student Record</h3>
                <button type="button" class="modal-close" onclick="closeModal('createModal')">×</button>
            </div>
            <form method="POST" action="manage_students.php">
                <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                <input type="hidden" name="action" value="create">

                <div class="form-grid">
                    <div class="form-group form-full">
                        <label>Full Name</label>
                        <input type="text" name="name" required placeholder="e.g. Priyansh Shah">
                    </div>
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" required placeholder="e.g. priyansh@example.com">
                    </div>
                    <div class="form-group">
                        <label>Mobile Number (10 Digits)</label>
                        <input type="tel" name="mobile" required maxlength="10" placeholder="e.g. 9876543210">
                    </div>
                    <div class="form-group">
                        <label>Course</label>
                        <select name="course" required>
                            <option value="BCA">BCA</option>
                            <option value="BSc IT">BSc IT</option>
                            <option value="B.Tech">B.Tech</option>
                            <option value="MCA">MCA</option>
                            <option value="MSc IT">MSc IT</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Academic Year</label>
                        <select name="year" required>
                            <option value="1">First Year (1)</option>
                            <option value="2">Second Year (2)</option>
                            <option value="3">Third Year (3)</option>
                            <option value="4">Fourth Year (4)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Gender</label>
                        <select name="gender" required>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Account Status</label>
                        <select name="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="form-group form-full">
                        <label>Initial Password</label>
                        <input type="password" name="password" required placeholder="Minimum 6 characters">
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeModal('createModal')">Cancel</button>
                    <button type="submit" class="btn-submit">Create Student Record</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ================= EDIT STUDENT MODAL ================= -->
    <div id="editModal" class="modal">
        <div class="modal-box">
            <div class="modal-head">
                <h3>Edit Student Record</h3>
                <button type="button" class="modal-close" onclick="closeModal('editModal')">×</button>
            </div>
            <form method="POST" action="manage_students.php">
                <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="student_id" id="edit_student_id">

                <div class="form-grid">
                    <div class="form-group form-full">
                        <label>Full Name</label>
                        <input type="text" name="name" id="edit_name" required>
                    </div>
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" id="edit_email" required>
                    </div>
                    <div class="form-group">
                        <label>Mobile Number</label>
                        <input type="tel" name="mobile" id="edit_mobile" required maxlength="10">
                    </div>
                    <div class="form-group">
                        <label>Course</label>
                        <select name="course" id="edit_course" required>
                            <option value="BCA">BCA</option>
                            <option value="BSc IT">BSc IT</option>
                            <option value="B.Tech">B.Tech</option>
                            <option value="MCA">MCA</option>
                            <option value="MSc IT">MSc IT</option>
                            <option value="Computer Science Engineering">Computer Science Engineering</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Academic Year</label>
                        <select name="year" id="edit_year" required>
                            <option value="1">First Year (1)</option>
                            <option value="2">Second Year (2)</option>
                            <option value="3">Third Year (3)</option>
                            <option value="4">Fourth Year (4)</option>
                            <option value="3rd Year">3rd Year</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Gender</label>
                        <select name="gender" id="edit_gender" required>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Account Status</label>
                        <select name="status" id="edit_status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive (Soft Deleted)</option>
                        </select>
                    </div>
                    <div class="form-group form-full">
                        <label>Change Password (leave blank to keep current)</label>
                        <input type="password" name="new_password" placeholder="Enter new password only if changing">
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeModal('editModal')">Cancel</button>
                    <button type="submit" class="btn-submit">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- JAVASCRIPT FOR MODALS -->
    <script>
        function openCreateModal() {
            document.getElementById('createModal').classList.add('open');
        }

        function openEditModal(student) {
            document.getElementById('edit_student_id').value = student.student_id;
            document.getElementById('edit_name').value = student.name;
            document.getElementById('edit_email').value = student.email;
            document.getElementById('edit_mobile').value = student.mobile || '';
            document.getElementById('edit_course').value = student.course || 'BCA';
            document.getElementById('edit_year').value = student.year || '1';
            document.getElementById('edit_gender').value = student.gender || 'Male';
            document.getElementById('edit_status').value = student.status || 'active';
            document.getElementById('editModal').classList.add('open');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('open');
        }

        // Close modal on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal('createModal');
                closeModal('editModal');
            }
        });
    </script>
</body>
</html>
