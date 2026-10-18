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
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }

        body {
            background: #f1f5f9;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }

        .success-box {
            background: white;
            padding: 40px;
            border-radius: 20px;
            width: 100%;
            max-width: 520px;
            text-align: center;
            box-shadow: 0 15px 40px rgba(15, 23, 42, 0.12);
            border: 1px solid rgba(148, 163, 184, 0.2);
            animation: fadeIn 0.4s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(15px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .icon-circle {
            width: 70px;
            height: 70px;
            background: #dcfce7;
            color: #15803d;
            font-size: 34px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            margin: 0 auto 20px;
        }

        h2 {
            color: #15803d;
            font-size: 24px;
            margin-bottom: 8px;
        }

        p.subtitle {
            color: #64748b;
            font-size: 14px;
            margin-bottom: 24px;
        }

        .details-table {
            width: 100%;
            background: #f8fafc;
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 25px;
            text-align: left;
            border: 1px solid #e2e8f0;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px dashed #e2e8f0;
            font-size: 14px;
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            color: #64748b;
            font-weight: 500;
        }

        .detail-value {
            color: #1e293b;
            font-weight: 600;
            text-align: right;
        }

        .btn-group {
            display: flex;
            gap: 12px;
            justify-content: center;
        }

        .btn-primary {
            display: inline-block;
            padding: 12px 26px;
            background: #2563eb;
            color: white;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14px;
            transition: 0.2s ease;
        }

        .btn-primary:hover {
            background: #1d4ed8;
        }

        .btn-outline {
            display: inline-block;
            padding: 12px 24px;
            background: #f8fafc;
            color: #475569;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14px;
            border: 1px solid #cbd5e1;
            transition: 0.2s ease;
        }

        .btn-outline:hover {
            background: #e2e8f0;
        }
    </style>
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
