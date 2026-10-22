<?php
require_once __DIR__ . '/auth.php';
start_secure_session();

// Check if user has a valid completed registration in session
if (empty($_SESSION['registered_student'])) {
    header("Location: ../registration.html");
    exit;
}

$student = $_SESSION['registered_student'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Successful - StudentHub</title>
    <link rel="stylesheet" href="../css/register_success.css">
</head>
<body>

<div class="success-box">
    <div class="icon-circle">✓</div>
    <h2>Registration Successful!</h2>
    <p class="subtitle">Your StudentHub account has been securely created in MySQL.</p>

    <div class="details-table">
        <div class="detail-row">
            <span class="detail-label">Student ID</span>
            <span class="detail-value">#<?= e($student['student_id']) ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Full Name</span>
            <span class="detail-value"><?= e($student['name']) ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Email Address</span>
            <span class="detail-value"><?= e($student['email']) ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Mobile</span>
            <span class="detail-value"><?= e($student['mobile']) ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Course</span>
            <span class="detail-value"><?= e($student['course']) ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Academic Year</span>
            <span class="detail-value">Year <?= e($student['year']) ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Gender</span>
            <span class="detail-value"><?= e($student['gender']) ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Status</span>
            <span class="detail-value" style="color:#15803d;">Active (Student)</span>
        </div>
    </div>

    <div class="btn-group">
        <a href="../login.html" class="btn-primary">Proceed to Login →</a>
        <a href="../index.html" class="btn-outline">Home</a>
    </div>
</div>

</body>
</html>
