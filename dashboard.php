<?php
/**
 * Root Dashboard Router
 * Seamlessly forwards to php/dashboard.php with active session
 */
require_once __DIR__ . '/php/auth.php';
start_secure_session();

if (!is_logged_in()) {
    header("Location: login.html?msg=login_required");
    exit;
}

if ($_SESSION['role'] === 'admin') {
    header("Location: php/admin_dashboard.php");
} else {
    header("Location: php/dashboard.php");
}
exit;
