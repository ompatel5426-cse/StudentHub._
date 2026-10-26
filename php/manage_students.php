<?php
/**
 * StudentHub — Practical 11: Student Management CRUD with Search, Filter & CSV Export
 * Strictly guarded by require_admin().
 * Features:
 *  - Create, Read, Update, Delete (Soft & Hard Delete with confirmation)
 *  - External Student Approval Workflow (Approve / Reject self-registrations)
 *  - Student Marks Management (Add, View, Delete semester subject scores)
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
// 1. AJAX: GET STUDENT MARKS
// =========================================================================
if (isset($_GET['action']) && $_GET['action'] === 'get_marks') {
    header('Content-Type: application/json; charset=utf-8');
    $sid = (int)($_GET['student_id'] ?? 0);
    $st_stmt = $conn->prepare("SELECT student_id, name, course, year, enrollment_no FROM students WHERE student_id = ?");
    $st_stmt->bind_param("i", $sid);
    $st_stmt->execute();
    $st_data = $st_stmt->get_result()->fetch_assoc();
    $st_stmt->close();

    if (!$st_data) {
        echo json_encode(['success' => false, 'message' => 'Student not found']);
        exit;
    }

    $marks = [];
    $m_stmt = $conn->prepare("SELECT mark_id, semester, subject_name, subject_code, internal_marks, external_marks, total_marks, grade FROM student_marks WHERE student_id = ? ORDER BY semester ASC, mark_id ASC");
    $m_stmt->bind_param("i", $sid);
    $m_stmt->execute();
    $m_res = $m_stmt->get_result();
    while ($mr = $m_res->fetch_assoc()) {
        $marks[] = $mr;
    }
    $m_stmt->close();

    echo json_encode(['success' => true, 'student' => $st_data, 'marks' => $marks]);
    exit;
}

// =========================================================================
// 2. CSV EXPORT HANDLER (With Formula Injection Protection)
// =========================================================================
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
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
    fputs($output, "\xEF\xBB\xBF"); // UTF-8 BOM

    // CSV Column Headers
    fputcsv($output, ['Student ID', 'Enrollment No', 'Full Name', 'Email Address', 'Mobile', 'Course', 'Year', 'Gender', 'Source', 'Status']);

    $export_stmt = $conn->prepare("SELECT student_id, enrollment_no, name, email, mobile, course, year, gender, registration_source, status FROM students WHERE role = 'student' ORDER BY student_id ASC");
    if ($export_stmt) {
        $export_stmt->execute();
        $export_res = $export_stmt->get_result();
        while ($row = $export_res->fetch_assoc()) {
            fputcsv($output, [
                sanitize_csv_field($row['student_id']),
                sanitize_csv_field($row['enrollment_no'] ?: 'SH2026-' . str_pad($row['student_id'], 4, '0', STR_PAD_LEFT)),
                sanitize_csv_field($row['name']),
                sanitize_csv_field($row['email']),
                sanitize_csv_field($row['mobile']),
                sanitize_csv_field($row['course']),
                sanitize_csv_field($row['year']),
                sanitize_csv_field($row['gender']),
                sanitize_csv_field($row['registration_source'] ?? 'self'),
                sanitize_csv_field($row['status'])
            ]);
        }
        $export_stmt->close();
    }

    fclose($output);
    exit;
}

// =========================================================================
// 3. PROCESS MUTATING ACTIONS (POST)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submitted_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($submitted_token)) {
        $_SESSION['flash_error'] = "Security validation failed (CSRF mismatch). Request aborted.";
        header("Location: manage_students.php");
        exit;
    }

    $action = $_POST['action'] ?? '';

    // --- A. CREATE STUDENT (By Admin) ---
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
        if (!in_array($status, ['active', 'inactive', 'pending'], true)) $status = 'active';
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

        // Hash and insert as admin-created
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $ins = $conn->prepare("INSERT INTO students (name, email, mobile, course, year, gender, role, registration_source, status, password) VALUES (?, ?, ?, ?, ?, ?, 'student', 'admin', ?, ?)");
        $ins->bind_param("ssssssss", $name, $email, $mobile, $course, $year, $gender, $status, $hashed);

        if ($ins->execute()) {
            $new_id = $conn->insert_id;
            $enrol_no = sprintf("SH2026-%04d", $new_id);
            $enrol_stmt = $conn->prepare("UPDATE students SET enrollment_no = ? WHERE student_id = ?");
            if ($enrol_stmt) {
                $enrol_stmt->bind_param("si", $enrol_no, $new_id);
                $enrol_stmt->execute();
                $enrol_stmt->close();
            }

            $_SESSION['flash_success'] = "Student '$name' (#$new_id / $enrol_no) created successfully by Admin!";
            log_audit_action($conn, 'STUDENT_CREATE', "Admin created student #$new_id ($name, $email).");
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
        if (!in_array($status, ['active', 'inactive', 'pending'], true)) $status = 'active';

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

    // --- F. APPROVE SELF-REGISTERED STUDENT (Add to College) ---
    if ($action === 'approve') {
        $student_id = (int)($_POST['student_id'] ?? 0);
        if ($student_id <= 0) {
            $_SESSION['flash_error'] = "Invalid student ID.";
        } else {
            $s_stmt = $conn->prepare("SELECT name, email FROM students WHERE student_id = ? LIMIT 1");
            $s_stmt->bind_param("i", $student_id);
            $s_stmt->execute();
            $s_info = $s_stmt->get_result();
            $s_name = "Student #$student_id";
            if ($s_info && $sr = $s_info->fetch_assoc()) {
                $s_name = $sr['name'];
            }
            $stmt = $conn->prepare("UPDATE students SET status = 'active' WHERE student_id = ? AND role = 'student'");
            $stmt->bind_param("i", $student_id);
            if ($stmt->execute()) {
                $_SESSION['flash_success'] = "✓ Student '$s_name' (#$student_id) has been approved and added to active students! They can now log in.";
                log_audit_action($conn, 'STUDENT_APPROVE', "Approved self-registered student #$student_id ($s_name).");
            } else {
                $_SESSION['flash_error'] = "Failed to approve student: " . $conn->error;
            }
            $stmt->close();
        }
        header("Location: manage_students.php");
        exit;
    }

    // --- G. REJECT / REMOVE PENDING STUDENT ---
    if ($action === 'reject') {
        $student_id = (int)($_POST['student_id'] ?? 0);
        if ($student_id <= 0) {
            $_SESSION['flash_error'] = "Invalid student ID.";
        } else {
            $s_stmt = $conn->prepare("SELECT name, email FROM students WHERE student_id = ? AND status = 'pending' LIMIT 1");
            $s_stmt->bind_param("i", $student_id);
            $s_stmt->execute();
            $s_info = $s_stmt->get_result();
            $s_name = "Student #$student_id";
            if ($s_info && $sr = $s_info->fetch_assoc()) {
                $s_name = $sr['name'];
            }
            $stmt = $conn->prepare("DELETE FROM students WHERE student_id = ? AND status = 'pending' AND role = 'student'");
            $stmt->bind_param("i", $student_id);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $_SESSION['flash_success'] = "Pending registration for '$s_name' (#$student_id) was rejected and removed.";
                log_audit_action($conn, 'STUDENT_REJECT', "Rejected and removed pending student #$student_id ($s_name).");
            } else {
                $_SESSION['flash_error'] = "Could not reject student or record was already processed.";
            }
            $stmt->close();
        }
        header("Location: manage_students.php");
        exit;
    }

    // --- H. ADD STUDENT MARKS ---
    if ($action === 'add_mark') {
        $student_id     = (int)($_POST['student_id'] ?? 0);
        $semester       = trim($_POST['semester'] ?? '1st Semester');
        $subject_name   = trim($_POST['subject_name'] ?? '');
        $subject_code   = trim($_POST['subject_code'] ?? '');
        $internal_marks = (int)($_POST['internal_marks'] ?? 0);
        $external_marks = (int)($_POST['external_marks'] ?? 0);

        if ($student_id <= 0 || $subject_name === '') {
            $_SESSION['flash_error'] = "Student ID and Subject Name are required to add marks.";
            header("Location: manage_students.php");
            exit;
        }

        $internal_marks = max(0, min(40, $internal_marks));
        $external_marks = max(0, min(60, $external_marks));
        $total_marks    = $internal_marks + $external_marks;

        // Compute Grade
        if ($total_marks >= 90) $grade = 'A+';
        elseif ($total_marks >= 80) $grade = 'A';
        elseif ($total_marks >= 70) $grade = 'B+';
        elseif ($total_marks >= 60) $grade = 'B';
        elseif ($total_marks >= 50) $grade = 'C';
        elseif ($total_marks >= 40) $grade = 'P';
        else $grade = 'F';

        $m_ins = $conn->prepare("INSERT INTO student_marks (student_id, semester, subject_name, subject_code, internal_marks, external_marks, total_marks, grade) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $m_ins->bind_param("isssiiis", $student_id, $semester, $subject_name, $subject_code, $internal_marks, $external_marks, $total_marks, $grade);
        if ($m_ins->execute()) {
            $_SESSION['flash_success'] = "✓ Marks for '$subject_name' ($semester) added successfully! Total: $total_marks/100, Grade: $grade.";
            log_audit_action($conn, 'STUDENT_MARK_ADD', "Added mark for student #$student_id in $subject_name ($total_marks/100).");
        } else {
            $_SESSION['flash_error'] = "Failed to add marks: " . $conn->error;
        }
        $m_ins->close();
        header("Location: manage_students.php");
        exit;
    }

    // --- I. DELETE STUDENT MARKS ---
    if ($action === 'delete_mark') {
        $mark_id    = (int)($_POST['mark_id'] ?? 0);
        $student_id = (int)($_POST['student_id'] ?? 0);

        if ($mark_id > 0) {
            $m_del = $conn->prepare("DELETE FROM student_marks WHERE mark_id = ?");
            $m_del->bind_param("i", $mark_id);
            if ($m_del->execute()) {
                $_SESSION['flash_success'] = "Mark entry removed successfully.";
                log_audit_action($conn, 'STUDENT_MARK_DELETE', "Deleted mark #$mark_id for student #$student_id.");
            } else {
                $_SESSION['flash_error'] = "Failed to delete mark: " . $conn->error;
            }
            $m_del->close();
        }
        header("Location: manage_students.php");
        exit;
    }
}

// =========================================================================
// 4. SEARCH, FILTER & PAGINATION PARAMETERS
// =========================================================================
$search_query  = trim($_GET['search'] ?? '');
$filter_course = trim($_GET['course'] ?? '');
$filter_year   = trim($_GET['year'] ?? '');
$filter_gender = trim($_GET['gender'] ?? '');
$filter_status = trim($_GET['status'] ?? '');

$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 8;
$offset = ($page - 1) * $per_page;

// Count Pending Registrations for top notification banner
$pending_count = 0;
$p_chk = $conn->query("SELECT COUNT(*) FROM students WHERE status = 'pending' AND role = 'student'");
if ($p_chk) {
    $pending_count = (int)$p_chk->fetch_row()[0];
}

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

// Status filter (active, inactive, pending)
if ($filter_status !== "" && in_array($filter_status, ['active', 'inactive', 'pending'], true)) {
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
$data_sql = "SELECT student_id, name, email, mobile, course, year, gender, status, registration_source, enrollment_no 
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
    <link rel="stylesheet" href="../css/manage_students.css">
</head>
<body>
    <a href="#main-content" class="skip-link">Skip to main content</a>


    <!-- NAVBAR -->
    <nav class="admin-nav">
        <div>
            <span class="brand-text">StudentHub</span>
            <span class="admin-tag">Administrator</span>
        </div>
        <div class="admin-nav-links">
            <a href="admin_dashboard.php">Dashboard</a>
            <a href="manage_students.php" style="color:white; font-weight:700;">Students CRUD</a>
            <a href="manage_events.php">Events CRUD</a>
            <a href="audit_logs.php">Audit Logs</a>
            <a href="dashboard.php" target="_blank" style="color:#a78bfa;">Student View ↗</a>
            <a href="logout.php" class="btn-logout">Logout ⏻</a>
        </div>
    </nav>
    <main id="main-content">


    <!-- CONTENT -->
    <div class="page-wrap">

        <!-- Flash Messages -->
        <?php if (!empty($flash_success)): ?>
            <div class="alert alert-success">
                <span><?= e($flash_success) ?></span>
                <span style="cursor:pointer;" onclick="this.parentElement.style.display='none'">✕</span>
            </div>
        <?php endif; ?>

        <?php if (!empty($flash_error)): ?>
            <div class="alert alert-danger">
                <span>⚠ <?= e($flash_error) ?></span>
                <span style="cursor:pointer;" onclick="this.parentElement.style.display='none'">✕</span>
            </div>
        <?php endif; ?>

        <!-- Pending Registrations Alert Banner -->
        <?php if ($pending_count > 0): ?>
            <div class="pending-alert-banner">
                <div class="pending-alert-content">
                    <span class="pending-bell">🔔</span>
                    <div>
                        <strong><?= $pending_count ?> External Student Registration(s) Awaiting Approval!</strong>
                        <p>These students registered online and require administrator approval (Add / Approve) before their account is activated.</p>
                    </div>
                </div>
                <div class="pending-alert-action">
                    <a href="manage_students.php?status=pending" class="btn-review-pending">Review Pending Registrations »</a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Title & Action Buttons -->
        <div class="crud-header">
            <div>
                <h1>Student Records Management</h1>
                <p>Practical 11: Real-time search, multi-attribute filtering, approval workflow, marks system & CSV export.</p>
            </div>
            <div style="display:flex; gap:12px; flex-wrap:wrap;">
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
                        <option value="pending" <?= $filter_status === 'pending' ? 'selected' : '' ?>>⏳ Pending Approval (<?= $pending_count ?>)</option>
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
                    <th scope="col"ead>
                        <tr>
                            <th scope="col" style="width:45px;">Sr.</th>
                            <th scope="col">ID & Enrol</th>
                            <th scope="col">Full Name</th>
                            <th scope="col">Email Address</th>
                            <th scope="col">Mobile</th>
                            <th scope="col">Course / Year</th>
                            <th scope="col">Source</th>
                            <th scope="col">Status</th>
                            <th scope="col" style="text-align:center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($students)): ?>
                            <tr>
                                <td colspan="9" style="text-align:center; padding:35px; color:#64748b;">
                                    No student records match your search or filter criteria.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $counter = $offset; foreach ($students as $st): $counter++; ?>
                                <?php 
                                    $enrol = !empty($st['enrollment_no']) ? $st['enrollment_no'] : sprintf('SH2026-%04d', $st['student_id']);
                                    $source = $st['registration_source'] ?? 'self';
                                ?>
                                <tr class="<?= $st['status'] === 'pending' ? 'row-pending' : '' ?>">
                                    <td style="color:#64748b; font-weight:600;"><?= $counter ?></td>
                                    <td>
                                        <strong>#<?= e($st['student_id']) ?></strong><br>
                                        <small style="color:#6366f1; font-weight:600;"><?= e($enrol) ?></small>
                                    </td>
                                    <td>
                                        <strong style="color:#0f172a;"><?= e($st['name']) ?></strong><br>
                                        <small style="color:#64748b;"><?= e($st['gender']) ?></small>
                                    </td>
                                    <td><?= e($st['email']) ?></td>
                                    <td><?= e($st['mobile'] ?: '—') ?></td>
                                    <td>
                                        <strong><?= e($st['course']) ?></strong><br>
                                        <small style="color:#64748b;">Year <?= e($st['year']) ?></small>
                                    </td>
                                    <td>
                                        <?php if ($source === 'self'): ?>
                                            <span class="badge-source badge-source-self" title="Registered Online">🌐 Online Self-Reg</span>
                                        <?php else: ?>
                                            <span class="badge-source badge-source-admin" title="Added by Admin">👤 Admin Added</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($st['status'] === 'active'): ?>
                                            <span class="badge badge-active">Active</span>
                                        <?php elseif ($st['status'] === 'pending'): ?>
                                            <span class="badge badge-pending">⏳ Pending Approval</span>
                                        <?php else: ?>
                                            <span class="badge badge-inactive">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="actions-wrap" style="justify-content:center;">
                                            <?php if ($st['status'] === 'pending'): ?>
                                                <!-- APPROVE / ADD STUDENT BUTTON -->
                                                <form method="POST" action="manage_students.php" style="display:inline;" onsubmit="return confirm('Approve and activate <?= e($st['name']) ?> (#<?= e($st['student_id']) ?>)? They will now be able to log in to their student portal.');">
                                                    <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                                                    <input type="hidden" name="action" value="approve">
                                                    <input type="hidden" name="student_id" value="<?= e($st['student_id']) ?>">
                                                    <button type="submit" class="btn-action btn-approve" title="Approve and activate registration">
                                                        ✓ Add / Approve Student
                                                    </button>
                                                </form>

                                                <!-- REJECT / REMOVE STUDENT BUTTON -->
                                                <form method="POST" action="manage_students.php" style="display:inline;" onsubmit="return confirm('Reject and remove registration for <?= e($st['name']) ?> (#<?= e($st['student_id']) ?>)? This record will be permanently deleted.');">
                                                    <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                                                    <input type="hidden" name="action" value="reject">
                                                    <input type="hidden" name="student_id" value="<?= e($st['student_id']) ?>">
                                                    <button type="submit" class="btn-action btn-reject" title="Reject and remove registration">
                                                        ✕ Remove / Reject
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <!-- MARKS MANAGEMENT BUTTON -->
                                                <button type="button" class="btn-action btn-marks" onclick='openMarksModal(<?= (int)$st['student_id'] ?>, <?= json_encode($st['name']) ?>)' title="View or enter subject marks">
                                                    📊 Marks
                                                </button>

                                                <!-- EDIT BUTTON -->
                                                <button type="button" class="btn-action btn-edit" onclick='openEditModal(<?= json_encode($st, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                                                    ✏ Edit
                                                </button>

                                                <!-- SOFT DELETE / RESTORE -->
                                                <?php if ($st['status'] === 'active'): ?>
                                                    <form method="POST" action="manage_students.php" style="display:inline;" onsubmit="return confirm('Soft-delete student #<?= e($st['student_id']) ?> (<?= e($st['name']) ?>)? This will set status to inactive.');">
                                                        <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                                                        <input type="hidden" name="action" value="soft_delete">
                                                        <input type="hidden" name="student_id" value="<?= e($st['student_id']) ?>">
                                                        <button type="submit" class="btn-action btn-soft" title="Deactivate">⏸ Soft Delete</button>
                                                    </form>
                                                <?php else: ?>
                                                    <form method="POST" action="manage_students.php" style="display:inline;">
                                                        <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                                                        <input type="hidden" name="action" value="restore">
                                                        <input type="hidden" name="student_id" value="<?= e($st['student_id']) ?>">
                                                        <button type="submit" class="btn-action btn-restore" title="Restore active status">▶ Restore</button>
                                                    </form>
                                                <?php endif; ?>

                                                <!-- HARD DELETE -->
                                                <form method="POST" action="manage_students.php" style="display:inline;" onsubmit="return confirm('PERMANENT DELETE: Are you completely sure you want to permanently delete student #<?= e($st['student_id']) ?>? This cannot be undone.');">
                                                    <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                                                    <input type="hidden" name="action" value="hard_delete">
                                                    <input type="hidden" name="student_id" value="<?= e($st['student_id']) ?>">
                                                    <button type="submit" class="btn-action btn-hard" title="Permanent removal">🗑 Delete</button>
                                                </form>
                                            <?php endif; ?>
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

    <!-- ================= MANAGE MARKS MODAL ================= -->
    <div id="marksModal" class="modal">
        <div class="modal-box modal-marks-box">
            <div class="modal-head">
                <div>
                    <h3 id="marksModalTitle">Student Marks Management</h3>
                    <p id="marksModalSubtitle" style="font-size:13px; color:#64748b; margin-top:2px;">View existing marks and add subject scores.</p>
                </div>
                <button type="button" class="modal-close" onclick="closeModal('marksModal')">×</button>
            </div>

            <!-- Existing Marks Section -->
            <div style="margin-bottom:24px;">
                <h4 style="font-size:14px; color:#334155; margin-bottom:10px; font-weight:600;">Published Subject Marks</h4>
                <div id="marksLoading" style="text-align:center; padding:20px; color:#64748b; font-size:13px;">Loading student marks...</div>
                <div id="marksTableContainer" style="display:none; overflow-x:auto;">
                    <table class="crud-table" style="font-size:12px;">
                        <th scope="col"ead>
                            <tr>
                                <th scope="col">Semester</th>
                                <th scope="col">Subject</th>
                                <th scope="col">Code</th>
                                <th scope="col">Internal (40)</th>
                                <th scope="col">External (60)</th>
                                <th scope="col">Total (100)</th>
                                <th scope="col">Grade</th>
                                <th scope="col">Action</th>
                            </tr>
                        </thead>
                        <tbody id="marksTableBody">
                        </tbody>
                    </table>
                </div>
                <div id="noMarksNotice" style="display:none; text-align:center; padding:22px; background:#f8fafc; border:1px dashed #cbd5e1; border-radius:10px; color:#64748b; font-size:13px;">
                    ⚠️ No marks have been added for this student yet. Use the form below to enter subject marks.
                </div>
            </div>

            <!-- Add New Mark Form -->
            <div class="add-mark-box">
                <h4 style="font-size:14px; color:#1e293b; margin-bottom:12px; font-weight:600;">➕ Add New Subject Mark</h4>
                <form method="POST" action="manage_students.php">
                    <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                    <input type="hidden" name="action" value="add_mark">
                    <input type="hidden" name="student_id" id="mark_student_id">

                    <div class="form-grid">
                        <div class="form-group">
                            <label>Semester</label>
                            <select name="semester" required>
                                <option value="1st Semester">1st Semester</option>
                                <option value="2nd Semester">2nd Semester</option>
                                <option value="3rd Semester">3rd Semester</option>
                                <option value="4th Semester">4th Semester</option>
                                <option value="5th Semester">5th Semester</option>
                                <option value="6th Semester">6th Semester</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Subject Code</label>
                            <input type="text" name="subject_code" placeholder="e.g. CS101, IT204">
                        </div>

                        <div class="form-group form-full">
                            <label>Subject Name</label>
                            <input type="text" name="subject_name" required placeholder="e.g. Web Development Technologies, Database Systems">
                        </div>

                        <div class="form-group">
                            <label>Internal Marks (Max 40)</label>
                            <input type="number" name="internal_marks" min="0" max="40" required placeholder="0 to 40">
                        </div>

                        <div class="form-group">
                            <label>External Marks (Max 60)</label>
                            <input type="number" name="external_marks" min="0" max="60" required placeholder="0 to 60">
                        </div>
                    </div>

                    <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:14px;">
                        <button type="submit" class="btn-submit" style="background:#6C63FF;">+ Save Subject Mark</button>
                    </div>
                </form>
            </div>

            <div class="modal-footer" style="margin-top:16px;">
                <button type="button" class="btn-cancel" onclick="closeModal('marksModal')">Close</button>
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
                            <option value="active">Active (Immediate Login)</option>
                            <option value="pending">Pending Approval</option>
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
                            <option value="pending">Pending Approval</option>
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

    <!-- JAVASCRIPT FOR MODALS & MARKS -->
    <script src="../js/manage_students.js"></script>
    </main>
</body>
</html>
