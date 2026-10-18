<?php
/**
 * StudentHub — Practical 10: Secure Logout Processor
 * Destroys user session, revokes remember-me token, clears cookie, and redirects
 */

require_once __DIR__ . '/auth.php';

start_secure_session();
logout_user();

header("Location: ../login.html?msg=logged_out");
exit;
