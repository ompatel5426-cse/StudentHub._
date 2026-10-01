<?php

// =====================================
// ONLY POST REQUEST ALLOWED
// =====================================

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    die("Invalid request method.");
}


// =====================================
// GET FORM DATA
// =====================================

$name = trim($_POST["name"] ?? "");
$email = trim($_POST["email"] ?? "");
$mobile = trim($_POST["mobile"] ?? "");
$course = trim($_POST["course"] ?? "");
$year = trim($_POST["year"] ?? "");
$gender = trim($_POST["gender"] ?? "");
$password = $_POST["password"] ?? "";
$confirmPassword = $_POST["confirmPassword"] ?? "";


// =====================================
// VALIDATION
// =====================================

$errors = [];


// Name
if ($name === "") {
    $errors[] = "Full name is required.";
} elseif (!preg_match("/^[A-Za-z ]{2,50}$/", $name)) {
    $errors[] = "Please enter a valid name.";
}


// Email
if ($email === "") {
    $errors[] = "Email is required.";
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Please enter a valid email address.";
}


// Mobile
if ($mobile === "") {
    $errors[] = "Mobile number is required.";
} elseif (!preg_match("/^[6-9][0-9]{9}$/", $mobile)) {
    $errors[] = "Enter a valid 10-digit mobile number.";
}


// Course
if ($course === "") {
    $errors[] = "Please select your course.";
}


// Year
if ($year === "") {
    $errors[] = "Please select your academic year.";
}


// Gender
if ($gender === "") {
    $errors[] = "Please select your gender.";
}


// Password
if ($password === "") {

    $errors[] = "Password is required.";

} elseif (
    strlen($password) < 8 ||
    !preg_match("/[A-Z]/", $password) ||
    !preg_match("/[a-z]/", $password) ||
    !preg_match("/[0-9]/", $password) ||
    !preg_match("/[@$!%*?&]/", $password)
) {

    $errors[] =
        "Password must contain 8+ characters, uppercase, lowercase, number and special character.";
}


// Confirm password
if ($password !== $confirmPassword) {
    $errors[] = "Passwords do not match.";
}


// =====================================
// SHOW VALIDATION ERRORS
// =====================================

if (!empty($errors)) {

    echo "<!DOCTYPE html>";
    echo "<html>";
    echo "<head>";

    echo "<title>Registration Error</title>";

    echo "<style>

        body {
            font-family: Arial, sans-serif;
            background: #f1f5f9;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .box {
            background: white;
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }

        h2 {
            color: #dc2626;
        }

        .error {
            color: #dc2626;
            margin: 10px 0;
        }

        a {
            display: inline-block;
            margin-top: 15px;
            padding: 10px 20px;
            background: #2563eb;
            color: white;
            text-decoration: none;
            border-radius: 8px;
        }

    </style>";

    echo "</head>";
    echo "<body>";

    echo "<div class='box'>";

    echo "<h2>Registration Failed</h2>";

    foreach ($errors as $error) {
        echo "<p class='error'>• "
            . htmlspecialchars($error)
            . "</p>";
    }

    echo "<a href='../registration.html'>Go Back</a>";

    echo "</div>";

    echo "</body>";
    echo "</html>";

    exit;
}


// =====================================
// DATABASE CONNECTION
// =====================================

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


// Check connection

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}


// =====================================
// CHECK EMAIL ALREADY EXISTS
// =====================================

$check = $conn->prepare(
    "SELECT student_id
     FROM students
     WHERE email = ?"
);

$check->bind_param("s", $email);

$check->execute();

$result = $check->get_result();


if ($result->num_rows > 0) {

    echo "<!DOCTYPE html>";

    echo "<html>";
    echo "<head>";
    echo "<title>Registration Error</title>";

    echo "<style>

        body {
            font-family: Arial, sans-serif;
            background: #f1f5f9;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .box {
            background: white;
            padding: 35px;
            border-radius: 16px;
            width: 90%;
            max-width: 500px;
            text-align: center;
            box-shadow: 0 15px 40px rgba(0,0,0,0.15);
        }

        h2 {
            color: #dc2626;
        }

        a {
            display: inline-block;
            margin-top: 20px;
            padding: 11px 22px;
            background: #2563eb;
            color: white;
            text-decoration: none;
            border-radius: 8px;
        }

    </style>";

    echo "</head>";

    echo "<body>";

    echo "<div class='box'>";

    echo "<h2>Email Already Registered</h2>";

    echo "<p>This email is already registered in StudentHub.</p>";

    echo "<a href='../registration.html'>Back to Registration</a>";

    echo "</div>";

    echo "</body>";
    echo "</html>";

    $check->close();
    $conn->close();

    exit;
}

$check->close();


// =====================================
// HASH PASSWORD
// =====================================

$hashedPassword = password_hash(
    $password,
    PASSWORD_DEFAULT
);


// =====================================
// INSERT STUDENT
// =====================================

$stmt = $conn->prepare(
    "INSERT INTO students
    (name, email, mobile, course, year, gender, password)
    VALUES (?, ?, ?, ?, ?, ?, ?)"
);


$stmt->bind_param(
    "sssssss",
    $name,
    $email,
    $mobile,
    $course,
    $year,
    $gender,
    $hashedPassword
);


// =====================================
// EXECUTE INSERT
// =====================================

if (!$stmt->execute()) {

    die(
        "Registration failed: "
        . htmlspecialchars($stmt->error)
    );
}


// Get newly created student ID

$student_id = $conn->insert_id;


// Close database

$stmt->close();
$conn->close();


// =====================================
// SUCCESS PAGE
// =====================================

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Registration Successful</title>

    <style>

        body {
            font-family: Arial, sans-serif;
            background: #f1f5f9;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .success-box {
            background: white;
            padding: 35px;
            border-radius: 16px;
            width: 90%;
            max-width: 500px;
            text-align: center;
            box-shadow: 0 15px 40px rgba(0,0,0,0.15);
        }

        h2 {
            color: #15803d;
        }

        .details {
            text-align: left;
            margin-top: 20px;
            line-height: 1.8;
        }

        a {
            display: inline-block;
            margin-top: 20px;
            padding: 11px 22px;
            background: #2563eb;
            color: white;
            text-decoration: none;
            border-radius: 8px;
        }

    </style>

</head>

<body>

<div class="success-box">

    <h2>✓ Registration Successful!</h2>

    <p>
        Your StudentHub account has been created successfully.
    </p>

    <div class="details">

        <strong>Student ID:</strong>
        <?= htmlspecialchars($student_id) ?><br>

        <strong>Name:</strong>
        <?= htmlspecialchars($name) ?><br>

        <strong>Email:</strong>
        <?= htmlspecialchars($email) ?><br>

        <strong>Mobile:</strong>
        <?= htmlspecialchars($mobile) ?><br>

        <strong>Course:</strong>
        <?= htmlspecialchars($course) ?><br>

        <strong>Academic Year:</strong>
        <?= htmlspecialchars($year) ?><br>

        <strong>Gender:</strong>
        <?= htmlspecialchars($gender) ?><br>

    </div>

    <a href="../login.html">
        Go to Login
    </a>

</div>

</body>

</html>