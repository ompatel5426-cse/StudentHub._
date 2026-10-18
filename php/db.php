<?php
/**
 * StudentHub Central Database Connection
 * Provides both MySQLi ($conn) and PDO ($pdo) connections
 * Configured securely for XAMPP local environment
 */

$host = "localhost";
$dbname = "studenthub";
$username = "root";
$password = "";

// Disable direct mysqli error output to prevent leaking credentials
mysqli_report(MYSQLI_REPORT_OFF);

// =====================================
// 1. MySQLi CONNECTION (Primary for Practicals 9, 10, 11)
// =====================================
$conn = @new mysqli($host, $username, $password, $dbname);

if ($conn->connect_error) {
    error_log("StudentHub MySQLi Connection Error: " . $conn->connect_error);
    die("Database connection error. Please ensure MySQL is running in XAMPP.");
}

$conn->set_charset("utf8mb4");

// =====================================
// 2. PDO CONNECTION (Preserved for Practical 8 / test_connection.php)
// =====================================
try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    error_log("StudentHub PDO Connection Error: " . $e->getMessage());
    $pdo = null;
}