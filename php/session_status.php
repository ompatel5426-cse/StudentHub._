<?php
/**
 * StudentHub — Public Session Status API
 * Returns current authentication status and user role for dynamic navigation
 */

require_once __DIR__ . '/auth.php';
start_secure_session();

header('Content-Type: application/json; charset=utf-8');

if (is_logged_in() && isset($_SESSION['role'])) {
    echo json_encode([
        'logged_in' => true,
        'role'      => $_SESSION['role'],
        'name'      => $_SESSION['name'] ?? '',
        'email'     => $_SESSION['email'] ?? ''
    ]);
} else {
    echo json_encode([
        'logged_in' => false,
        'role'      => null
    ]);
}
