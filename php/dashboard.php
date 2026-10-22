<?php
/**
 * StudentHub — Practical 10: Authenticated Student Dashboard
 * Guarded by require_login(), displays authenticated student session details,
 * and provides secure logout functionality.
 */

require_once __DIR__ . '/auth.php';
start_secure_session();
require_login();

// If an administrator accesses student dashboard, provide link back to Admin Panel
$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
$student_name = $_SESSION['name'] ?? 'Student';
$student_email = $_SESSION['email'] ?? '';
$student_course = $_SESSION['course'] ?? 'Not Assigned';
$student_year = $_SESSION['year'] ?? '1';
$student_id = $_SESSION['student_id'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - StudentHub</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/dashboard.css">
</head>
<body>

    <!-- ================= NAVBAR ================= -->
    <nav class="dash-nav">
        <div class="nav-brand">
            <span class="logo-text">StudentHub</span>
            <span class="badge-role">Student Portal</span>
        </div>

        <div class="nav-links">
            <a href="dashboard.php" style="color:#6C63FF; font-weight:700;">Dashboard</a>
            <a href="../courses.html">Courses</a>
            <a href="../assignments.html">Assignments</a>
            <a href="../attendance.html">Attendance</a>
            <a href="../events.html">Events</a>
            <a href="../results.html">Results</a>

            <?php if ($is_admin): ?>
                <a href="admin_dashboard.php" class="btn-admin-switch">Admin Console ⚙</a>
            <?php endif; ?>

            <a href="logout.php" class="btn-logout">Logout ⏻</a>
        </div>
    </nav>

    <!-- ================= DASHBOARD BODY ================= -->
    <main class="dashboard-container">

        <!-- Welcome Banner -->
        <section class="welcome-card">
            <div class="welcome-text">
                <h1>Welcome back, <?= e($student_name) ?>! 👋</h1>
                <p>Academic session 2026–27 | You are securely authenticated with role: <strong>Student</strong></p>
            </div>
            <div class="student-pill">
                <div class="sid">Student ID #<?= e($student_id) ?></div>
                <div class="sname"><?= e($student_email) ?></div>
            </div>
        </section>

        <!-- Profile Details Strip -->
        <section class="profile-strip">
            <div class="strip-item">
                <div class="strip-label">Course</div>
                <div class="strip-value"><?= e($student_course) ?></div>
            </div>
            <div class="strip-item">
                <div class="strip-label">Academic Year</div>
                <div class="strip-value">Year <?= e($student_year) ?></div>
            </div>
            <div class="strip-item">
                <div class="strip-label">Account Status</div>
                <div class="strip-value" style="color:#15803d;">Active ✓</div>
            </div>
            <div class="strip-item">
                <div class="strip-label">Session Protection</div>
                <div class="strip-value" style="color:#6C63FF;">Encrypted / 15m Timeout</div>
            </div>
        </section>

        <!-- Quick Access Feature Cards -->
        <section class="cards-grid">
            <a href="../study-materials.html" class="card-box">
                <span class="card-icon">📚</span>
                <h3>Study Materials</h3>
                <p>Access syllabus, lecture notes, question banks, and reference PDFs.</p>
            </a>

            <a href="../assignments.html" class="card-box">
                <span class="card-icon">📝</span>
                <h3>Assignments</h3>
                <p>Check pending assignments, submission deadlines, and grading status.</p>
            </a>

            <a href="../attendance.html" class="card-box">
                <span class="card-icon">🗓️</span>
                <h3>Attendance</h3>
                <p>Monitor subject-wise attendance percentage and lecture schedules.</p>
            </a>

            <a href="../events.html" class="card-box">
                <span class="card-icon">🏆</span>
                <h3>College Events</h3>
                <p>Browse upcoming workshops, technical symposiums, and sports meets.</p>
            </a>

            <a href="../results.html" class="card-box">
                <span class="card-icon">📊</span>
                <h3>Exam Results</h3>
                <p>View mid-term and semester grade sheets and performance reports.</p>
            </a>

            <a href="../notices.html" class="card-box">
                <span class="card-icon">📢</span>
                <h3>Circulars & Notices</h3>
                <p>Official notices from department heads and university administration.</p>
            </a>
        </section>

        <!-- Lower Split Panel -->
        <section class="section-split">
            <div class="panel-box">
                <h2>Recent Academic Activity</h2>
                <ul class="activity-list">
                    <li class="activity-item">
                        <span class="activity-badge">✓</span>
                        <span>Mid-semester evaluation results published for <strong><?= e($student_course) ?></strong>.</span>
                    </li>
                    <li class="activity-item">
                        <span class="activity-badge">✓</span>
                        <span>New event registration open: Web Development Workshop (CSPIT Seminar Hall).</span>
                    </li>
                    <li class="activity-item">
                        <span class="activity-badge">✓</span>
                        <span>Attendance updated for current academic semester (92% aggregate).</span>
                    </li>
                </ul>
            </div>

            <div class="panel-box">
                <h2>Account Security</h2>
                <p style="font-size:13px; color:#64748b; line-height:1.6; margin-bottom:15px;">
                    Your account is protected using <strong>PHP Session Regeneration</strong> and <strong>Bcrypt</strong> credential hashing.
                </p>
                <div style="background:#f8fafc; padding:12px; border-radius:8px; font-size:12px; color:#475569;">
                    ⏱ Inactivity timer resets on every action.<br>
                    🔒 Back-button caching disabled.
                </div>
            </div>
        </section>

    </main>

</body>
</html>