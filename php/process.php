<?php
/**
 * StudentHub — Practical 9: Secure User Registration Processor
 * Uses PHP MySQLi with Prepared Statements, Server-side Validation,
 * Secure Hashing (password_hash), CSRF Protection, and PRG Pattern.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

start_secure_session();

// =====================================
// 1. ENFORCE HTTP POST METHOD
// =====================================
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    header("Location: ../registration.html");
    exit;
}

// =====================================
// 2. CSRF TOKEN VALIDATION
// =====================================
$submitted_csrf = $_POST["csrf_token"] ?? "";
if (!empty($submitted_csrf) && !verify_csrf_token($submitted_csrf)) {
    render_error_page(["Security validation failed (invalid CSRF token). Please try submitting the form again."]);
    exit;
}

// =====================================
// 3. SANITIZE & RETRIEVE INPUTS
// =====================================
$name            = trim($_POST["name"] ?? "");
$email           = trim($_POST["email"] ?? "");
$mobile          = trim($_POST["mobile"] ?? "");
$course          = trim($_POST["course"] ?? "");
$year            = trim($_POST["year"] ?? "");
$gender          = trim($_POST["gender"] ?? "");
$password        = $_POST["password"] ?? "";
$confirmPassword = $_POST["confirmPassword"] ?? "";
$terms           = isset($_POST["terms"]) || isset($_POST["agree"]);

// =====================================
// 4. RIGOROUS SERVER-SIDE VALIDATION
// =====================================
$errors = [];

// Name validation (2 to 50 characters, letters and spaces only)
if ($name === "") {
    $errors[] = "Full name is required.";
} elseif (!preg_match("/^[A-Za-z ]{2,50}$/", $name)) {
    $errors[] = "Full name must be 2 to 50 characters and contain only letters and spaces.";
}

// Email validation
if ($email === "") {
    $errors[] = "Email address is required.";
} elseif (strlen($email) > 100) {
    $errors[] = "Email address cannot exceed 100 characters.";
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Please provide a valid email address (e.g. name@example.com).";
}

// Mobile validation (10 digits starting with 6, 7, 8, or 9)
if ($mobile === "") {
    $errors[] = "Mobile number is required.";
} elseif (!preg_match("/^[6-9][0-9]{9}$/", $mobile)) {
    $errors[] = "Mobile number must be a valid 10-digit Indian phone number starting with 6, 7, 8, or 9.";
}

// Course validation
$allowed_courses = ["BCA", "BSc IT", "B.Tech", "MCA", "MSc IT", "Computer Science Engineering", "Information Technology"];
if ($course === "") {
    $errors[] = "Please select your course.";
} elseif (!in_array($course, $allowed_courses, true) && strlen($course) > 100) {
    $errors[] = "Invalid course selection.";
}

// Year validation
$allowed_years = ["1", "2", "3", "4", "First Year", "Second Year", "Third Year", "Fourth Year", "1st Year", "2nd Year", "3rd Year", "4th Year"];
if ($year === "") {
    $errors[] = "Please select your academic year.";
} elseif (!in_array($year, $allowed_years, true)) {
    $errors[] = "Invalid academic year selection.";
}

// Gender validation
$allowed_genders = ["Male", "Female", "Other"];
if ($gender === "") {
    $errors[] = "Please select your gender.";
} elseif (!in_array($gender, $allowed_genders, true)) {
    $errors[] = "Invalid gender selection.";
}

// Password complexity validation (8+ chars, min 1 uppercase, 1 lowercase, 1 digit, 1 special char)
if ($password === "") {
    $errors[] = "Password is required.";
} elseif (
    strlen($password) < 8 ||
    !preg_match("/[A-Z]/", $password) ||
    !preg_match("/[a-z]/", $password) ||
    !preg_match("/[0-9]/", $password) ||
    !preg_match("/[@$!%*?&#]/", $password)
) {
    $errors[] = "Password must be at least 8 characters long and contain uppercase, lowercase, number, and special character (@$!%*?&#).";
}

// Password confirmation match
if ($password !== $confirmPassword) {
    $errors[] = "Password and confirm password do not match.";
}

// If any validation errors exist, render the error view
if (!empty($errors)) {
    render_error_page($errors);
    exit;
}

// =====================================
// 5. CHECK IF EMAIL ALREADY EXISTS (Prepared Statement)
// =====================================
$check_stmt = $conn->prepare("SELECT student_id FROM students WHERE email = ? LIMIT 1");
if (!$check_stmt) {
    error_log("StudentHub Prepare Error: " . $conn->error);
    render_error_page(["An unexpected server error occurred. Please try again later."]);
    exit;
}

$check_stmt->bind_param("s", $email);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result && $check_result->num_rows > 0) {
    $check_stmt->close();
    render_duplicate_email_page($email);
    exit;
}
$check_stmt->close();

// =====================================
// 6. SECURE PASSWORD HASHING (PASSWORD_DEFAULT)
// =====================================
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// =====================================
// 7. INSERT STUDENT RECORD (Prepared Statement)
// External student self-registration requires Admin approval (status = 'pending')
// =====================================
$status = 'pending';
$source = 'self';

$insert_stmt = $conn->prepare(
    "INSERT INTO students (name, email, mobile, course, year, gender, role, status, registration_source, password) 
     VALUES (?, ?, ?, ?, ?, ?, 'student', ?, ?, ?)"
);

if (!$insert_stmt) {
    error_log("StudentHub Insert Prepare Error: " . $conn->error);
    render_error_page(["Registration could not be completed at this time. Please contact support."]);
    exit;
}

$insert_stmt->bind_param(
    "sssssssss",
    $name,
    $email,
    $mobile,
    $course,
    $year,
    $gender,
    $status,
    $source,
    $hashed_password
);

if (!$insert_stmt->execute()) {
    error_log("StudentHub Execute Error: " . $insert_stmt->error);
    $insert_stmt->close();
    render_error_page(["An error occurred while saving your record. Please try again."]);
    exit;
}

$new_student_id = $conn->insert_id;
$insert_stmt->close();

// Generate unique Enrollment ID for student
$enrollment_no = sprintf("SH2026-%04d", $new_student_id);
$upd_stmt = $conn->prepare("UPDATE students SET enrollment_no = ? WHERE student_id = ?");
if ($upd_stmt) {
    $upd_stmt->bind_param("si", $enrollment_no, $new_student_id);
    $upd_stmt->execute();
    $upd_stmt->close();
}

// Log audit action
if (function_exists('log_audit_action')) {
    log_audit_action($conn, 'STUDENT_SELF_REGISTER', "External student {$name} ({$email}) registered online. Status: Pending Admin Approval.");
}

// =====================================
// 8. POST / REDIRECT / GET (PRG) PATTERN
// =====================================
$_SESSION['registered_student'] = [
    'student_id'    => $new_student_id,
    'enrollment_no' => $enrollment_no,
    'name'          => $name,
    'email'         => $email,
    'mobile'        => $mobile,
    'course'        => $course,
    'year'          => $year,
    'gender'        => $gender,
    'status'        => 'pending'
];

header("Location: register_success.php");
exit;


// =========================================================================
// HELPER UI FUNCTIONS (Matching StudentHub Theme)
// =========================================================================

function render_error_page(array $errors) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Registration Error - StudentHub</title>
        <link rel="stylesheet" href="../css/process_feedback.css">
    </head>
    <body>
    <div class="box">
        <div class="icon">✕</div>
        <h2>Registration Failed</h2>
        <p class="sub">Please correct the following errors and try again:</p>
        <ul class="error-list">
            <?php foreach ($errors as $err): ?>
                <li><span>•</span> <span><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></span></li>
            <?php endforeach; ?>
        </ul>
        <div class="btn-wrap">
            <a href="javascript:history.back()" class="btn">← Go Back & Edit</a>
        </div>
    </div>
    </body>
    </html>
    <?php
}

function render_duplicate_email_page($email) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Email Already Registered - StudentHub</title>
        <link rel="stylesheet" href="../css/process_feedback.css">
    </head>
    <body>
    <div class="box">
        <div class="icon">⚠</div>
        <h2>Email Already Registered</h2>
        <p>The email address <span class="highlight"><?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?></span> is already linked to an existing StudentHub account.</p>
        <div class="btn-group">
            <a href="../login.html" class="btn-primary">Sign In Instead →</a>
            <a href="../registration.html" class="btn-outline">Use Different Email</a>
        </div>
    </div>
    </body>
    </html>
    <?php
}