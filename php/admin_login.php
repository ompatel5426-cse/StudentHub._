<?php
/**
 * StudentHub — Dedicated Administrator Login Processor & View
 * Strictly allows only users with role = 'admin'.
 * Rejects student accounts and unauthorized guests.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

start_secure_session();

// If already logged in as admin, redirect to admin dashboard
if (is_logged_in() && isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    header("Location: admin_dashboard.php");
    exit;
}

$error_message = "";
$info_message = "";

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'registered') {
        $info_message = "Administrator account created successfully! Please sign in.";
    } elseif ($_GET['msg'] === 'logged_out') {
        $info_message = "You have been logged out from Administrator Console.";
    } elseif ($_GET['msg'] === 'admin_required') {
        $error_message = "Administrator login required to access that section.";
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email       = trim($_POST["email"] ?? "");
    $password    = $_POST["password"] ?? "";
    $remember_me = !empty($_POST["remember"]);

    if (!empty($_POST["csrf_token"]) && !verify_csrf_token($_POST["csrf_token"])) {
        $error_message = "Security token mismatch. Please try again.";
    } elseif ($email === "" || $password === "") {
        $error_message = "Both email address and password are required.";
    } else {
        $stmt = $conn->prepare(
            "SELECT student_id, name, email, mobile, course, year, gender, role, status, password 
             FROM students 
             WHERE email = ? 
             LIMIT 1"
        );

        if (!$stmt) {
            error_log("StudentHub Admin Login Prepare Error: " . $conn->error);
            $error_message = "A server error occurred. Please try again later.";
        } else {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && $row = $result->fetch_assoc()) {
                // Verify password
                if (password_verify($password, $row["password"])) {
                    // Check role strictly
                    if ($row["role"] !== "admin") {
                        $error_message = "Access Denied: This account is a Student account. Please log in via the Student Login page.";
                    } elseif ($row["status"] !== "active") {
                        $error_message = "Your administrator account has been deactivated.";
                    } else {
                        // Success: establish admin session
                        session_regenerate_id(true);

                        $_SESSION["student_id"]    = (int)$row["student_id"];
                        $_SESSION["name"]          = $row["name"];
                        $_SESSION["email"]         = $row["email"];
                        $_SESSION["role"]          = "admin";
                        $_SESSION["status"]        = $row["status"];
                        $_SESSION["last_activity"] = time();

                        if ($remember_me) {
                            set_remember_me_token($conn, $row["student_id"]);
                        }

                        $stmt->close();
                        header("Location: admin_dashboard.php");
                        exit;
                    }
                } else {
                    $error_message = "Invalid administrator email address or password.";
                }
            } else {
                $error_message = "Invalid administrator email address or password.";
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentHub | Administrator Sign In</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .alert-box { padding: 12px 16px; border-radius: 8px; font-size: 14px; margin-bottom: 18px; display: flex; align-items: center; gap: 10px; }
        .alert-danger { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
        .alert-success { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
        .remember-wrap { display: flex; justify-content: space-between; align-items: center; margin: 12px 0 16px; font-size: 13px; }
        .remember-wrap label { display: flex; align-items: center; gap: 6px; color: #555; cursor: pointer; }
        .btn-admin-submit { background: #0f172a; color: white; width: 100%; padding: 13px; border: none; border-radius: 8px; font-weight: 600; font-size: 15px; cursor: pointer; }
        .btn-admin-submit:hover { background: #1e293b; }
    </style>
</head>
<body>

    <header>
        <div class="logo">
            <h2>StudentHub</h2>
        </div>
        <nav>
            <a href="../index.html">Home</a>
            <a href="../about.html">About</a>
            <a href="../login.html">Student Login</a>
            <a href="admin_login.php" style="color:#6C63FF; font-weight:700;">Admin Login 🛡️</a>
        </nav>
    </header>

    <section class="login-section">
        <div class="login-left" style="background: linear-gradient(145deg, #0f172a, #1e1b4b);">
            <div style="background:rgba(239,68,68,0.2); color:#fca5a5; display:inline-block; padding:4px 12px; border-radius:20px; font-size:12px; font-weight:700; margin-bottom:15px;">
                🛡️ RESTRICTED ADMIN PORTAL
            </div>
            <h1 style="color:white;">Administrator Sign In</h1>
            <p style="color:#cbd5e1;">
                Restricted access for college management and faculty members. Manage student databases, attendance, circulars, and system configurations.
            </p>
        </div>

        <div class="login-right">
            <form action="admin_login.php" method="POST">
                <h2>Admin Login</h2>

                <?php if (!empty($error_message)): ?>
                    <div class="alert-box alert-danger">
                        <span>⚠</span>
                        <span><?= e($error_message) ?></span>
                    </div>
                <?php endif; ?>

                <?php if (!empty($info_message)): ?>
                    <div class="alert-box alert-success">
                        <span>✓</span>
                        <span><?= e($info_message) ?></span>
                    </div>
                <?php endif; ?>

                <input type="hidden" name="csrf_token" value="<?= e(get_csrf_token()) ?>">

                <p>Administrator Email</p>
                <input
                    type="email"
                    name="email"
                    placeholder="admin@studenthub.com"
                    value="<?= isset($email) ? e($email) : '' ?>"
                    required
                    autocomplete="username"
                >

                <p>Password</p>
                <input
                    type="password"
                    name="password"
                    placeholder="Enter password"
                    required
                    autocomplete="current-password"
                >

                <div class="remember-wrap">
                    <label>
                        <input type="checkbox" name="remember" value="1"> Remember Me
                    </label>
                    <a href="#">Forgot Password?</a>
                </div>

                <button type="submit" class="btn-admin-submit">
                    Sign In to Console →
                </button>

                <div style="margin-top:20px; padding-top:15px; border-top:1px dashed #e2e8f0; font-size:13px; text-align:center; display:flex; flex-direction:column; gap:8px;">
                    <div>
                        New Administrator?
                        <a href="../admin_register.html" style="color:#6C63FF; font-weight:600;">Register Admin Account</a>
                    </div>
                    <div>
                        Are you a student?
                        <a href="../login.html" style="color:#475569; text-decoration:underline;">Switch to Student Login →</a>
                    </div>
                </div>
            </form>
        </div>
    </section>

</body>
</html>
