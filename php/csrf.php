<?php
/**
 * StudentHub — CSRF Token API Endpoint (csrf.php)
 * 
 * Purpose:
 *  Provides a cryptographically secure, session-bound CSRF token via JSON
 *  for asynchronous fetch requests (Registration, Login, Admin Login/Register).
 * 
 * Referenced by:
 *  - js/registration.js
 *  - js/login.js
 *  - js/admin_login.js
 *  - js/admin_register.js
 * 
 * Response Format:
 *  {
 *      "success": true,
 *      "csrf_token": "64-character-hex-token",
 *      "timestamp": 1729881234
 *  }
 */

require_once __DIR__ . '/auth.php';

// Initialize or resume secure PHP session
start_secure_session();

// Prevent browser or proxy caching of sensitive security tokens
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Content-Type-Options: nosniff');

// Retrieve or generate CSRF token for the current session
$csrf_token = get_csrf_token();

// Return JSON payload
echo json_encode([
    'success'    => true,
    'csrf_token' => $csrf_token,
    'timestamp'  => time()
], JSON_UNESCAPED_SLASHES);
exit;
