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
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', -apple-system, sans-serif;
        }

        body {
            background: #f7f8fc;
            color: #333;
        }

        /* Navbar */
        nav.dash-nav {
            background: white;
            padding: 18px 8%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .nav-brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo-text {
            font-size: 26px;
            font-weight: 700;
            color: #6C63FF;
        }

        .badge-role {
            background: #e0e7ff;
            color: #4338ca;
            font-size: 11px;
            font-weight: 700;
            padding: 3px 9px;
            border-radius: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .nav-links a {
            text-decoration: none;
            color: #475569;
            font-weight: 500;
            font-size: 14px;
            transition: color 0.2s;
        }

        .nav-links a:hover {
            color: #6C63FF;
        }

        .btn-logout {
            text-decoration: none;
            background: #ef4444;
            color: white !important;
            padding: 8px 18px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            transition: background 0.2s;
        }

        .btn-logout:hover {
            background: #dc2626;
        }

        .btn-admin-switch {
            background: #4f46e5;
            color: white !important;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
        }

        /* Dashboard Container */
        .dashboard-container {
            padding: 40px 8%;
            max-width: 1300px;
            margin: 0 auto;
        }

        /* Hero Welcome */
        .welcome-card {
            background: linear-gradient(135deg, #6C63FF, #8b5cf6);
            color: white;
            padding: 35px 40px;
            border-radius: 20px;
            margin-bottom: 35px;
            box-shadow: 0 10px 30px rgba(108, 99, 255, 0.25);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .welcome-text h1 {
            font-size: 28px;
            margin-bottom: 8px;
        }

        .welcome-text p {
            color: #e0e7ff;
            font-size: 15px;
        }

        .student-pill {
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(8px);
            padding: 12px 20px;
            border-radius: 14px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            text-align: right;
        }

        .student-pill .sid {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            opacity: 0.9;
        }

        .student-pill .sname {
            font-size: 16px;
            font-weight: 700;
        }

        /* Academic Profile Summary */
        .profile-strip {
            background: white;
            padding: 20px 30px;
            border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.04);
            margin-bottom: 30px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .strip-item {
            border-right: 1px solid #f1f5f9;
            padding-right: 15px;
        }

        .strip-item:last-child {
            border-right: none;
        }

        .strip-label {
            font-size: 12px;
            color: #64748b;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .strip-value {
            font-size: 15px;
            color: #1e293b;
            font-weight: 600;
        }

        /* Dashboard Cards Grid */
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 22px;
            margin-bottom: 35px;
        }

        .card-box {
            background: white;
            padding: 26px 22px;
            border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            transition: transform 0.2s, box-shadow 0.2s;
            text-decoration: none;
            color: inherit;
            display: block;
            border: 1px solid #f1f5f9;
        }

        .card-box:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 25px rgba(108, 99, 255, 0.12);
            border-color: #e0e7ff;
        }

        .card-icon {
            font-size: 30px;
            margin-bottom: 12px;
            display: inline-block;
        }

        .card-box h3 {
            color: #1e293b;
            font-size: 17px;
            margin-bottom: 6px;
        }

        .card-box p {
            color: #64748b;
            font-size: 13px;
            line-height: 1.5;
        }

        /* Activity / Notice Section */
        .section-split {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 25px;
        }

        @media (max-width: 900px) {
            .section-split {
                grid-template-columns: 1fr;
            }
        }

        .panel-box {
            background: white;
            padding: 28px;
            border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.04);
            border: 1px solid #f1f5f9;
        }

        .panel-box h2 {
            font-size: 18px;
            color: #1e293b;
            margin-bottom: 18px;
            padding-bottom: 12px;
            border-bottom: 2px solid #f1f5f9;
        }

        .activity-list {
            list-style: none;
        }

        .activity-item {
            padding: 12px 0;
            border-bottom: 1px dashed #e2e8f0;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
            color: #334155;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-badge {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: #dcfce7;
            color: #15803d;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: bold;
        }
    </style>
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