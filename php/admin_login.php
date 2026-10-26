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

                        // Practical 14: Audit Logging
                        log_audit_action($conn, 'ADMIN_LOGIN', "Administrator {$row['email']} signed into admin console.");

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
    <link rel="stylesheet" href="../css/admin_login.css">
</head>
<body>
    <a href="#main-content" class="skip-link">Skip to main content</a>


    <header>
        <div class="logo">
            <h2>StudentHub</h2>
        </div>
        <nav>
            <a href="../index.html">Home</a>
            <a href="../about.html">About</a>
            <a href="../courses.html">Courses</a>
            <a href="../events.html">Events</a>
            <a href="../login.html">Student Login</a>
            <a href="admin_login.php" style="color:#6C63FF; font-weight:700;">Admin Login 🛡️</a>
        </nav>
    </header>
    <main id="main-content">


    <section class="login-section">
        <div class="login-left">
            <div class="login-badge">🛡️ RESTRICTED ACCESS</div>
            <h1 style="color:white; font-size:42px; margin-bottom:15px; font-weight:700; line-height:1.2;">Administrator Portal</h1>
            <p style="color:#f1f5f9; font-size:16px; line-height:1.6; margin-bottom:10px;">
                Authorized access for university administrators, department heads, and faculty staff. Manage student records, publish campus events, configure settings, and audit system activities.
            </p>

            <div class="login-features-list">
                <div class="login-feature-pill">
                    <span>🔒</span> <span>Role-Based Access Control (Admin verified)</span>
                </div>
                <div class="login-feature-pill">
                    <span>🛡️</span> <span>Bcrypt password hashing & session regeneration</span>
                </div>
                <div class="login-feature-pill">
                    <span>⚡</span> <span>MySQLi prepared statements & audit logging</span>
                </div>
            </div>
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

                <label for="admin_email" style="display:block; font-size:13px; font-weight:600; color:#334155; margin-bottom:6px;">Administrator Email</label>
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
                    <a href="forgot_password.php">Forgot Password?</a>
                </div>

                <button type="submit" class="btn-admin-submit">
                    Sign In to Admin Portal
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

    <!-- ================= FEATURES ================= -->
    <section class="login-features">
        <h2>Administrator Capabilities</h2>

        <div class="cards">
            <div class="card">
                <h3>👥 Student Management</h3>
                <p>Add, update, search, filter, and export student academic records to CSV.</p>
            </div>

            <div class="card">
                <h3>📅 Event Management</h3>
                <p>Publish campus events, upload poster images with validation, and track registrations.</p>
            </div>

            <div class="card">
                <h3>⚡ Real-Time API</h3>
                <p>RESTful JSON endpoints and asynchronous Fetch API live event feeds.</p>
            </div>

            <div class="card">
                <h3>🛡️ Security Audits</h3>
                <p>Monitor critical administrative actions with immutable timestamped audit logs.</p>
            </div>
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
                <li><a href="../login.html">Student Login</a></li>
                <li><a href="admin_login.php">Admin Login</a></li>
            </ul>
        </div>
        <div>
            <h4>Contact</h4>
            <p>Email: admin@studenthub.com</p>
            <p>Phone: +91 9876543210</p>
        </div>
    </footer>

    </main>
</body>
</html>
