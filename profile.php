<?php
/**
 * Root Profile Router
 * Seamlessly forwards to php/profile.php with active session
 */
require_once __DIR__ . '/php/auth.php';
start_secure_session();

if (!is_logged_in()) {
    header("Location: login.html?msg=login_required");
    exit;
}

header("Location: php/profile.php");
exit;
