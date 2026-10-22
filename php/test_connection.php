<?php

require_once "db.php";

$student_id = 1;

$stmt = $pdo->prepare(
    "SELECT student_id, name, email, course, year
     FROM students
     WHERE student_id = ?"
);

$stmt->execute([$student_id]);

$student = $stmt->fetch();

?>

<!DOCTYPE html>
<html lang="en">
<head>

    <meta charset="UTF-8">

    <title>PDO Connection Test</title>

    <link rel="stylesheet" href="../css/test_connection.css">

</head>

<body>

<div class="box">

    <h1>PDO Database Connection Test</h1>

    <p class="success">
        Database Connection Successful!
    </p>

    <?php if ($student): ?>

        <h2>Student Data</h2>

        <table>

            <tr>
                <th>Student ID</th>
                <td><?= htmlspecialchars($student["student_id"]) ?></td>
            </tr>

            <tr>
                <th>Name</th>
                <td><?= htmlspecialchars($student["name"]) ?></td>
            </tr>

            <tr>
                <th>Email</th>
                <td><?= htmlspecialchars($student["email"]) ?></td>
            </tr>

            <tr>
                <th>Course</th>
                <td><?= htmlspecialchars($student["course"]) ?></td>
            </tr>

            <tr>
                <th>Year</th>
                <td><?= htmlspecialchars($student["year"]) ?></td>
            </tr>

        </table>

    <?php else: ?>

        <p>No student found.</p>

    <?php endif; ?>

</div>

</body>
</html>