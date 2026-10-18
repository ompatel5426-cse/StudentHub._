<?php
/**
 * StudentHub — Practical 10: Secure Login Processor & View
 * Authenticates users using password_verify(), enforces role-based redirects,
 * regenerates session IDs, handles remember-me tokens, and session timeouts.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

start_secure_session();

// If already logged in, redirect directly to the appropriate dashboard
if (is_logged_in()) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: admin_dashboard.php");
    } else {
        header("Location: dashboard.php");
    }
    exit;
}

$error_message = "";
$info_message = "";

// Check for query parameters from logout or middleware redirects
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'logged_out') {
        $info_message = "You have been logged out successfully.";
    } elseif ($_GET['msg'] === 'timeout') {
        $error_message = "Your session expired due to inactivity. Please sign in again.";
    } elseif ($_GET['msg'] === 'login_required') {
        $error_message = "Please sign in to access your dashboard.";
    }
}

// =====================================
// PROCESS POST LOGIN ATTEMPT
// =====================================
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email       = trim($_POST["email"] ?? "");
    $password    = $_POST["password"] ?? "";
    $remember_me = !empty($_POST["remember"]);

    // Verify CSRF if token was submitted
    if (!empty($_POST["csrf_token"]) && !verify_csrf_token($_POST["csrf_token"])) {
        $error_message = "Security token mismatch. Please try again.";
    } elseif ($email === "" || $password === "") {
        $error_message = "Both email address and password are required.";
    } else {
        // Query user with prepared statement
        $stmt = $conn->prepare(
            "SELECT student_id, name, email, mobile, course, year, gender, role, status, password 
             FROM students 
             WHERE email = ? 
             LIMIT 1"
        );

        if (!$stmt) {
            error_log("StudentHub Login Prepare Error: " . $conn->error);
            $error_message = "A server error occurred. Please try again later.";
        } else {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && $row = $result->fetch_assoc()) {
                // Verify password hash
                if (password_verify($password, $row["password"])) {
                    // Check account status
                    if ($row["status"] !== "active") {
                        $error_message = "Your account has been deactivated. Please contact an administrator.";
                    } else {
                        // Password is correct & account is active:
                        // 1. Regenerate session ID to prevent session fixation attacks
                        session_regenerate_id(true);

                        // 2. Set essential session variables
                        $_SESSION["student_id"]    = (int)$row["student_id"];
                        $_SESSION["name"]          = $row["name"];
                        $_SESSION["email"]         = $row["email"];
                        $_SESSION["role"]          = $row["role"]; // 'student' or 'admin'
                        $_SESSION["status"]        = $row["status"];
                        $_SESSION["course"]        = $row["course"];
                        $_SESSION["year"]          = $row["year"];
                        $_SESSION["gender"]        = $row["gender"];
                        $_SESSION["last_activity"] = time();

                        // 3. Process optional Remember-Me
                        if ($remember_me) {
                            set_remember_me_token($conn, $row["student_id"]);
                        }

                        $stmt->close();

                        // 4. Role-based redirect
                        if ($row["role"] === "admin") {
                            header("Location: admin_dashboard.php");
                        } else {
                            header("Location: dashboard.php");
                        }
                        exit;
                    }
                } else {
                    // Generic failure message to prevent user enumeration
                    $error_message = "Invalid email address or password.";
                }
            } else {
                // User not found
                $error_message = "Invalid email address or password.";
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
    <title>StudentHub | Login</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .alert-box {
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-danger {
            background: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }
        .alert-success {
            background: #dcfce7;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }
        .remember-wrap {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 12px 0 16px;
            font-size: 13px;
        }
        .remember-wrap label {
            display: flex;
            align-items: center;
            gap: 6px;
            color: #555;
            cursor: pointer;
        }
        .remember-wrap a {
            color: #6C63FF;
        }
    </style>
</head>
<body>

    <!-- ================= HEADER ================= -->
    <header>
        <div class="logo">
            <h2>StudentHub</h2>
        </div>
        <nav>
            <a href="../index.html">Home</a>
            <a href="../about.html">About</a>
            <a href="../courses.html">Courses</a>
            <a href="../study-materials.html">Study Materials</a>
            <a href="../assignments.html">Assignments</a>
            <a href="../events.html">Events</a>
            <a href="login.php" style="color:#6C63FF; font-weight:700;">Login</a>
        </nav>
    </header>

    <!-- ================= LOGIN SECTION ================= -->
    <section class="login-section">
        <div class="login-left">
            <h1>Welcome Back!</h1>
            <p>
                Login to access your personalized dashboard, attendance,
                assignments, study materials, and examination results.
            </p>
        </div>

        <div class="login-right">
            <form action="login.php" method="POST">
                <h2>Portal Sign In</h2>

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

                <p>Email Address</p>
                <input
                    type="email"
                    name="email"
                    placeholder="Enter your email"
                    value="<?= isset($email) ? e($email) : '' ?>"
                    required
                    autocomplete="username"
                >

                <p>Password</p>
                <input
                    type="password"
                    name="password"
                    placeholder="Enter your password"
                    required
                    autocomplete="current-password"
                >

                <div class="remember-wrap">
                    <label>
                        <input type="checkbox" name="remember" value="1"> Remember Me
                    </label>
                    <a href="#">Forgot Password?</a>
                </div>

                <button type="submit">
                    Sign In →
                </button>

                <p class="register">
                    Don't have an account?
                    <a href="../registration.html">Register here</a>
                </p>
            </form>
        </div>
    </section>

    <!-- ================= FOOTER ================= -->
    <footer>
        <div>
            <h3>StudentHub</h3>
            <p>Smart Student Management Portal</p>
        </div>
        <div>
            <h4>Quick Links</h4>
            <ul>
                <li><a href="../index.html">Home</a></li>
                <li><a href="../events.html">Events</a></li>
                <li><a href="../courses.html">Courses</a></li>
            </ul>
        </div>
        <div>
            <h4>Contact</h4>
            <p>Email: info@studenthub.com</p>
            <p>Phone: +91 9876543210</p>
        </div>
    </footer>

</body>
</html>