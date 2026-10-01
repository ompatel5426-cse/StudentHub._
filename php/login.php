<?php

session_start();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    die("Invalid request method.");
}

$email = trim($_POST["email"] ?? "");
$password = $_POST["password"] ?? "";

if ($email === "" || $password === "") {
    die("Email and password are required.");
}

$host = "localhost";
$username = "root";
$dbPassword = "";
$database = "studenthub";

$conn = new mysqli(
    $host,
    $username,
    $dbPassword,
    $database
);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$stmt = $conn->prepare(
    "SELECT student_id, name, email, mobile, course, year, gender, password
     FROM students
     WHERE email = ?"
);

$stmt->bind_param("s", $email);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Invalid email or password.");
}

$student = $result->fetch_assoc();

if (!password_verify($password, $student["password"])) {
    die("Invalid email or password.");
}

$_SESSION["student_id"] = $student["student_id"];
$_SESSION["name"] = $student["name"];
$_SESSION["email"] = $student["email"];
$_SESSION["mobile"] = $student["mobile"];
$_SESSION["course"] = $student["course"];
$_SESSION["year"] = $student["year"];
$_SESSION["gender"] = $student["gender"];

$stmt->close();
$conn->close();

header("Location: ../dashboard.php");
exit;

?>