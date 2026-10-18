<?php
require_once __DIR__ . '/auth.php';
start_secure_session();

header('Content-Type: application/json');
echo json_encode(['csrf_token' => get_csrf_token()]);
