<?php

require_once "db.php";

$email = "test@student.com";

$stmt = $pdo->prepare(
    "SELECT student_id, name, email, course, year
     FROM students
     WHERE email = ?"
);

$stmt->execute([$email]);

$student = $stmt->fetch();

if ($student) {

    echo "<h2>Student Found</h2>";

    echo "Student ID: " . htmlspecialchars($student["student_id"]) . "<br>";
    echo "Name: " . htmlspecialchars($student["name"]) . "<br>";
    echo "Email: " . htmlspecialchars($student["email"]) . "<br>";
    echo "Course: " . htmlspecialchars($student["course"]) . "<br>";
    echo "Year: " . htmlspecialchars($student["year"]);

} else {

    echo "No student found with this email.";

}

?>