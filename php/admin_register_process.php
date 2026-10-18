<?php
/**
 * StudentHub — Dedicated Administrator Registration Processor
 * Validates admin credentials, checks Admin Authorization Key,
 * hashes passwords, and assigns role = 'admin'.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

start_secure_session();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    header("Location: ../admin_register.html");
    exit;
}

// 1. Verify CSRF
$submitted_csrf = $_POST["csrf_token"] ?? "";
if (!empty($submitted_csrf) && !verify_csrf_token($submitted_csrf)) {
    render_admin_reg_error(["Security token mismatch. Please try submitting again."]);
    exit;
}

// 2. Validate Admin Authorization Security Key
$allowed_admin_keys = ["ADMIN2026", "CHARUSAT@ADMIN", "ADMIN@123", "ADMIN@CHARUSAT"];
$admin_key = trim($_POST["admin_key"] ?? "");

if (!in_array($admin_key, $allowed_admin_keys, true)) {
    render_admin_reg_error([
        "Invalid Admin Authorization Security Key. Unauthorized attempts to register as administrator are blocked."
    ]);
    exit;
}

// 3. Retrieve and sanitize inputs
$name            = trim($_POST["name"] ?? "");
$email           = trim($_POST["email"] ?? "");
$mobile          = trim($_POST["mobile"] ?? "");
$department      = trim($_POST["department"] ?? "Academic Administration");
$gender          = trim($_POST["gender"] ?? "Other");
$password        = $_POST["password"] ?? "";
$confirmPassword = $_POST["confirmPassword"] ?? "";

// 4. Server-side validation
$errors = [];

if ($name === "" || !preg_match("/^[A-Za-z. ]{2,60}$/", $name)) {
    $errors[] = "Full name must be 2 to 60 characters and contain only letters, dots, and spaces.";
}

if ($email === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Please enter a valid official email address.";
}

if ($mobile === "" || !preg_match("/^[6-9][0-9]{9}$/", $mobile)) {
    $errors[] = "Mobile number must be a valid 10-digit phone number.";
}

if (strlen($password) < 8 ||
    !preg_match("/[A-Z]/", $password) ||
    !preg_match("/[a-z]/", $password) ||
    !preg_match("/[0-9]/", $password)
) {
    $errors[] = "Password must be at least 8 characters long and contain uppercase, lowercase, and numeric characters.";
}

if ($password !== $confirmPassword) {
    $errors[] = "Password and confirm password do not match.";
}

if (!empty($errors)) {
    render_admin_reg_error($errors);
    exit;
}

// 5. Check duplicate email
$stmt = $conn->prepare("SELECT student_id FROM students WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    $stmt->close();
    render_admin_reg_error(["An account with email '$email' is already registered in StudentHub."]);
    exit;
}
$stmt->close();

// 6. Hash password with bcrypt
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// 7. Insert admin record
$ins = $conn->prepare(
    "INSERT INTO students (name, email, mobile, course, year, gender, role, status, password) 
     VALUES (?, ?, ?, ?, 'Faculty', ?, 'admin', 'active', ?)"
);

if (!$ins) {
    render_admin_reg_error(["Server error preparing statement. Please try again."]);
    exit;
}

$ins->bind_param("ssssss", $name, $email, $mobile, $department, $gender, $hashed_password);

if (!$ins->execute()) {
    $ins->close();
    render_admin_reg_error(["Failed to create administrator account. Please contact technical support."]);
    exit;
}

$ins->close();

// 8. Redirect to Admin Login with success message (PRG)
header("Location: ../admin_login.html?msg=registered");
exit;

function render_admin_reg_error(array $errors) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Registration Failed - StudentHub</title>
        <style>
            * { margin:0; padding:0; box-sizing:border-box; font-family:'Poppins',-apple-system,sans-serif; }
            body { background: #0f172a; display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 20px; }
            .box { background: white; padding: 35px; border-radius: 18px; width: 100%; max-width: 520px; box-shadow: 0 15px 40px rgba(0,0,0,0.3); border: 1px solid rgba(239,68,68,0.3); }
            h2 { color: #dc2626; font-size: 22px; text-align: center; margin-bottom: 8px; }
            p.sub { color: #64748b; font-size: 13px; text-align: center; margin-bottom: 20px; }
            .error-list { background: #fef2f2; border: 1px solid #fecaca; border-radius: 10px; padding: 15px 20px; margin-bottom: 24px; list-style-type: none; }
            .error-list li { color: #b91c1c; font-size: 13px; margin-bottom: 8px; }
            .btn-wrap { text-align: center; }
            a.btn { display: inline-block; padding: 11px 24px; background: #0f172a; color: white; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 14px; }
            a.btn:hover { background: #1e293b; }
        </style>
    </head>
    <body>
    <div class="box">
        <h2>Administrator Registration Blocked</h2>
        <p class="sub">Please resolve the issues below:</p>
        <ul class="error-list">
            <?php foreach ($errors as $err): ?>
                <li>• <?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></li>
            <?php endforeach; ?>
        </ul>
        <div class="btn-wrap">
            <a href="javascript:history.back()" class="btn">← Back to Form</a>
        </div>
    </div>
    </body>
    </html>
    <?php
}
