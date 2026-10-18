<?php
/**
 * StudentHub Authentication & Security Helper
 * Handles Sessions, Role-Based Access Control, CSRF, and Remember-Me
 */

// Define session inactivity timeout: 15 minutes (900 seconds)
define('SESSION_TIMEOUT_SECONDS', 900);

/**
 * Start session with secure cookie parameters and prevent caching
 */
function start_secure_session() {
    if (session_status() === PHP_SESSION_NONE) {
        // Enforce secure cookie attributes
        session_set_cookie_params([
            'lifetime' => 0, // Until browser closes
            'path'     => '/',
            'domain'   => '',
            'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            'httponly' => true,
            'samesite' => 'Lax'
        ]);

        session_start();
    }

    // Set cache prevention headers to stop back-button navigation to protected pages after logout
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");

    // Check inactivity timeout for authenticated sessions
    if (isset($_SESSION['student_id']) && isset($_SESSION['last_activity'])) {
        if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT_SECONDS) {
            // Expire session
            logout_user(true);
            header("Location: ../login.html?msg=timeout");
            exit;
        }
    }
    
    // Update last activity timestamp
    if (isset($_SESSION['student_id'])) {
        $_SESSION['last_activity'] = time();
    }
}

/**
 * Generate or retrieve CSRF token
 */
function get_csrf_token() {
    if (session_status() === PHP_SESSION_NONE) {
        start_secure_session();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verify_csrf_token($token) {
    if (session_status() === PHP_SESSION_NONE) {
        start_secure_session();
    }
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Escape output for XSS protection
 */
function e($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Check if user is logged in
 */
function is_logged_in() {
    if (session_status() === PHP_SESSION_NONE) {
        start_secure_session();
    }
    return isset($_SESSION['student_id']);
}

/**
 * Get current logged in user array
 */
function get_logged_user() {
    if (!is_logged_in()) {
        return null;
    }
    return [
        'student_id' => $_SESSION['student_id'],
        'name'       => $_SESSION['name'] ?? '',
        'email'      => $_SESSION['email'] ?? '',
        'role'       => $_SESSION['role'] ?? 'student',
        'status'     => $_SESSION['status'] ?? 'active',
        'course'     => $_SESSION['course'] ?? '',
        'year'       => $_SESSION['year'] ?? ''
    ];
}

/**
 * Guard page: require authentication (either role)
 */
function require_login() {
    if (session_status() === PHP_SESSION_NONE) {
        start_secure_session();
    }
    if (!is_logged_in()) {
        // Attempt remember-me login before redirecting
        if (!check_remember_me_cookie()) {
            header("Location: ../login.html?msg=login_required");
            exit;
        }
    }
}

/**
 * Guard page: require Administrator role
 */
function require_admin() {
    require_login();
    if ($_SESSION['role'] !== 'admin') {
        // Logged-in user is a student, not allowed in admin section
        header("Location: dashboard.php?msg=unauthorized");
        exit;
    }
}

/**
 * Guard page: require Student role
 */
function require_student() {
    require_login();
    // Admin is also allowed to view student dashboard or redirect
}

/**
 * Set Remember-Me cookie and hashed token in database
 */
function set_remember_me_token($conn, $student_id) {
    $raw_token = bin2hex(random_bytes(32));
    $hashed_token = hash('sha256', $raw_token);
    
    // Valid for 30 days
    $expiry = date('Y-m-d H:i:s', time() + (86400 * 30));

    $stmt = $conn->prepare("UPDATE students SET remember_token = ?, remember_expiry = ? WHERE student_id = ?");
    if ($stmt) {
        $stmt->bind_param("ssi", $hashed_token, $expiry, $student_id);
        $stmt->execute();
        $stmt->close();

        $cookie_val = $student_id . ':' . $raw_token;
        setcookie('studenthub_remember', $cookie_val, [
            'expires'  => time() + (86400 * 30),
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'
        ]);
    }
}

/**
 * Check Remember-Me cookie to auto-authenticate
 */
function check_remember_me_cookie() {
    if (empty($_COOKIE['studenthub_remember'])) {
        return false;
    }

    $parts = explode(':', $_COOKIE['studenthub_remember'], 2);
    if (count($parts) !== 2) {
        return false;
    }

    list($student_id, $raw_token) = $parts;
    $student_id = (int)$student_id;
    if ($student_id <= 0 || empty($raw_token)) {
        return false;
    }

    require_once __DIR__ . '/db.php';
    global $conn;

    $stmt = $conn->prepare("SELECT student_id, name, email, role, status, course, year, remember_token, remember_expiry FROM students WHERE student_id = ? AND status = 'active'");
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res && $row = $res->fetch_assoc()) {
        $stmt->close();
        if (!empty($row['remember_token']) && strtotime($row['remember_expiry']) > time()) {
            $expected_hash = hash('sha256', $raw_token);
            if (hash_equals($row['remember_token'], $expected_hash)) {
                // Token matches: establish session
                session_regenerate_id(true);
                $_SESSION['student_id']    = $row['student_id'];
                $_SESSION['name']          = $row['name'];
                $_SESSION['email']         = $row['email'];
                $_SESSION['role']          = $row['role'];
                $_SESSION['status']        = $row['status'];
                $_SESSION['course']        = $row['course'];
                $_SESSION['year']          = $row['year'];
                $_SESSION['last_activity'] = time();
                return true;
            }
        }
    } else {
        $stmt->close();
    }

    // Invalid cookie, clear it
    setcookie('studenthub_remember', '', time() - 3600, '/');
    return false;
}

/**
 * Revoke remember token and destroy session
 */
function logout_user($is_timeout = false) {
    if (session_status() === PHP_SESSION_NONE) {
        start_secure_session();
    }

    // Clear remember token from DB if logged in
    if (!empty($_SESSION['student_id'])) {
        require_once __DIR__ . '/db.php';
        global $conn;
        if ($conn) {
            $stmt = $conn->prepare("UPDATE students SET remember_token = NULL, remember_expiry = NULL WHERE student_id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $_SESSION['student_id']);
                $stmt->execute();
                $stmt->close();
            }
        }
    }

    // Clear remember-me cookie
    if (isset($_COOKIE['studenthub_remember'])) {
        setcookie('studenthub_remember', '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        unset($_COOKIE['studenthub_remember']);
    }

    // Unset all session variables
    $_SESSION = [];

    // Delete session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    // Destroy session storage
    session_destroy();
}
