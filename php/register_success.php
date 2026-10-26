<?php
require_once __DIR__ . '/auth.php';
start_secure_session();

// Check if user has a valid completed registration in session
if (empty($_SESSION['registered_student'])) {
    header("Location: ../registration.html");
    exit;
}

$student = $_SESSION['registered_student'];
$is_pending = ($student['status'] ?? '') === 'pending';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $is_pending ? 'Registration Submitted - Pending Approval' : 'Registration Successful' ?> - StudentHub</title>
    <link rel="stylesheet" href="../css/register_success.css">
    <style>
        .icon-circle.pending {
            background: #ffedd5;
            color: #c2410c;
        }
        .notice-box {
            background: #fefce8;
            border: 1px solid #fef08a;
            border-radius: 10px;
            padding: 12px 16px;
            font-size: 13px;
            color: #854d0e;
            margin-bottom: 22px;
            text-align: left;
            line-height: 1.5;
        }
    </style>
</head>
<body>
    <main id="main-content">

    <a href="#main-content" class="skip-link">Skip to main content</a>


<div class="success-box">
    <?php if ($is_pending): ?>
        <div class="icon-circle pending">⏳</div>
        <h2 style="color:#c2410c;">Registration Submitted!</h2>
        <p class="subtitle">Your information has been received and sent to the Administrator for approval.</p>
        
        <div class="notice-box">
            🔔 <strong>Pending Admin Review:</strong> Your student profile has been submitted to the university administrator. Once the administrator verifies and approves your account, you can log in to your student portal.
        </div>
    <?php else: ?>
        <div class="icon-circle">✓</div>
        <h2>Registration Successful!</h2>
        <p class="subtitle">Your StudentHub account has been securely created in MySQL.</p>
    <?php endif; ?>

    <div class="details-table">
        <div class="detail-row">
            <span class="detail-label">Enrollment ID</span>
            <span class="detail-value"><?= e($student['enrollment_no'] ?? ('#' . $student['student_id'])) ?></span>
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
            <span class="detail-label">Account Status</span>
            <?php if ($is_pending): ?>
                <span class="detail-value" style="color:#c2410c; background:#ffedd5; padding:2px 8px; border-radius:12px; font-size:12px;">⏳ Pending Approval</span>
            <?php else: ?>
                <span class="detail-value" style="color:#15803d;">Active (Student)</span>
            <?php endif; ?>
        </div>
    </div>

    <div class="btn-group">
        <a href="../login.html" class="btn-primary"><?= $is_pending ? 'Check Login Status →' : 'Proceed to Login →' ?></a>
        <a href="../index.html" class="btn-outline">Home</a>
    </div>
</div>

    </main>
</body>
</html>
