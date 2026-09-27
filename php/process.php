<?php

// Only allow POST requests
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    die("Invalid request method.");
}


// ===============================
// GET FORM DATA
// ===============================

$name = trim($_POST["name"] ?? "");
$email = trim($_POST["email"] ?? "");
$mobile = trim($_POST["mobile"] ?? "");
$course = trim($_POST["course"] ?? "");
$year = trim($_POST["year"] ?? "");
$gender = trim($_POST["gender"] ?? "");
$password = $_POST["password"] ?? "";
$confirmPassword = $_POST["confirmPassword"] ?? "";


// ===============================
// SERVER-SIDE VALIDATION
// ===============================

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
    $errors[] = "Password must contain 8+ characters, uppercase, lowercase, number and special character.";
}


// Confirm Password
if ($password !== $confirmPassword) {
    $errors[] = "Passwords do not match.";
}


// ===============================
// DISPLAY ERRORS
// ===============================

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
        echo "<p class='error'>• " . htmlspecialchars($error) . "</p>";
    }

    echo "<a href='../registration.html'>Go Back</a>";

    echo "</div>";

    echo "</body>";
    echo "</html>";

    exit;
}


// ===============================
// SANITIZE DATA
// ===============================

$name = htmlspecialchars($name, ENT_QUOTES, "UTF-8");
$email = htmlspecialchars($email, ENT_QUOTES, "UTF-8");
$mobile = htmlspecialchars($mobile, ENT_QUOTES, "UTF-8");
$course = htmlspecialchars($course, ENT_QUOTES, "UTF-8");
$year = htmlspecialchars($year, ENT_QUOTES, "UTF-8");
$gender = htmlspecialchars($gender, ENT_QUOTES, "UTF-8");


// ===============================
// DATE & TIME
// ===============================

$date = date("Y-m-d H:i:s");


// ===============================
// DATA FOLDER
// ===============================

$dataFolder = __DIR__ . "/data";

if (!is_dir($dataFolder)) {
    mkdir($dataFolder, 0755, true);
}


// ===============================
// CSV STORAGE
// ===============================

$csvFile = $dataFolder . "/registrations.csv";

$isNewFile = !file_exists($csvFile);

$file = fopen($csvFile, "a");

if ($file === false) {
    die("Unable to open CSV file.");
}


// Lock file while writing
flock($file, LOCK_EX);


// Add header for new CSV
if ($isNewFile) {

    fputcsv($file, [
        "Name",
        "Email",
        "Mobile",
        "Course",
        "Year",
        "Gender",
        "Date"
    ]);
}


// Add registration data
fputcsv($file, [
    $name,
    $email,
    $mobile,
    $course,
    $year,
    $gender,
    $date
]);


// Unlock and close
flock($file, LOCK_UN);
fclose($file);


// ===============================
// JSON STORAGE
// ===============================

$jsonFile = $dataFolder . "/registrations.json";

$records = [];


// Read existing JSON
if (file_exists($jsonFile)) {

    $jsonData = file_get_contents($jsonFile);

    if ($jsonData !== false && $jsonData !== "") {

        $decodedData = json_decode($jsonData, true);

        if (is_array($decodedData)) {
            $records = $decodedData;
        }
    }
}


// Add new record
$records[] = [
    "name" => $name,
    "email" => $email,
    "mobile" => $mobile,
    "course" => $course,
    "year" => $year,
    "gender" => $gender,
    "date" => $date
];


// Save JSON
file_put_contents(
    $jsonFile,
    json_encode($records, JSON_PRETTY_PRINT),
    LOCK_EX
);


// ===============================
// SUCCESS MESSAGE
// ===============================

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

    <p>Your StudentHub account has been registered successfully.</p>

    <div class="details">

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

    <a href="../registration.html">
        Back to Registration
    </a>

</div>

</body>

</html>