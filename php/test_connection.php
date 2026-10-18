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

    <style>

        body {
            font-family: Arial, sans-serif;
            background: #f5f5ff;
            padding: 40px;
        }

        .box {
            max-width: 700px;
            margin: auto;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        h1 {
            color: #6C63FF;
        }

        .success {
            color: green;
            font-weight: bold;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th, td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }

        th {
            background: #6C63FF;
            color: white;
        }

    </style>

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