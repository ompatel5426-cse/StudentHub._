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
// =====================================
$insert_stmt = $conn->prepare(
    "INSERT INTO students (name, email, mobile, course, year, gender, role, status, password) 
     VALUES (?, ?, ?, ?, ?, ?, 'student', 'active', ?)"
);

if (!$insert_stmt) {
    error_log("StudentHub Insert Prepare Error: " . $conn->error);
    render_error_page(["Registration could not be completed at this time. Please contact support."]);
    exit;
}

$insert_stmt->bind_param(
    "sssssss",
    $name,
    $email,
    $mobile,
    $course,
    $year,
    $gender,
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

// =====================================
// 8. POST / REDIRECT / GET (PRG) PATTERN
// =====================================
$_SESSION['registered_student'] = [
    'student_id' => $new_student_id,
    'name'       => $name,
    'email'      => $email,
    'mobile'     => $mobile,
    'course'     => $course,
    'year'       => $year,
    'gender'     => $gender
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
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', -apple-system, sans-serif; }
            body { background: #f1f5f9; display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 20px; }
            .box { background: white; padding: 35px; border-radius: 18px; width: 100%; max-width: 520px; box-shadow: 0 15px 40px rgba(15,23,42,0.12); border: 1px solid rgba(220,38,38,0.2); }
            .icon { width: 60px; height: 60px; background: #fee2e2; color: #dc2626; font-size: 28px; display: flex; align-items: center; justify-content: center; border-radius: 50%; margin: 0 auto 16px; }
            h2 { color: #dc2626; font-size: 22px; text-align: center; margin-bottom: 8px; }
            p.sub { color: #64748b; font-size: 13px; text-align: center; margin-bottom: 20px; }
            .error-list { background: #fef2f2; border: 1px solid #fecaca; border-radius: 10px; padding: 15px 20px; margin-bottom: 24px; list-style-type: none; }
            .error-list li { color: #b91c1c; font-size: 13px; margin-bottom: 8px; display: flex; align-items: flex-start; gap: 8px; }
            .error-list li:last-child { margin-bottom: 0; }
            .btn-wrap { text-align: center; }
            a.btn { display: inline-block; padding: 11px 24px; background: #2563eb; color: white; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 14px; }
            a.btn:hover { background: #1d4ed8; }
        </style>
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
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', -apple-system, sans-serif; }
            body { background: #f1f5f9; display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 20px; }
            .box { background: white; padding: 38px; border-radius: 18px; width: 100%; max-width: 520px; text-align: center; box-shadow: 0 15px 40px rgba(15,23,42,0.12); border: 1px solid rgba(234,88,12,0.25); }
            .icon { width: 64px; height: 64px; background: #ffedd5; color: #ea580c; font-size: 30px; display: flex; align-items: center; justify-content: center; border-radius: 50%; margin: 0 auto 18px; }
            h2 { color: #c2410c; font-size: 22px; margin-bottom: 8px; }
            p { color: #64748b; font-size: 14px; line-height: 1.6; margin-bottom: 24px; }
            .highlight { color: #1e293b; font-weight: 600; background: #f1f5f9; padding: 3px 8px; border-radius: 6px; }
            .btn-group { display: flex; gap: 12px; justify-content: center; }
            a.btn-primary { padding: 11px 22px; background: #2563eb; color: white; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 14px; }
            a.btn-outline { padding: 11px 22px; background: #f8fafc; color: #475569; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 14px; border: 1px solid #cbd5e1; }
        </style>
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