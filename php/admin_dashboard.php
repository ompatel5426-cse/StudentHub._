<?php
/**
 * StudentHub — Practical 10: Administrator Dashboard & Role Portal
 * Strictly restricted to role = 'admin'. Unauthorized students or guests are rejected.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

start_secure_session();
require_admin();

$admin_user = get_logged_user();

// Fetch summary metrics
$total_students = 0;
$active_students = 0;
$inactive_students = 0;
$total_events = 0;
$total_registrations = 0;

// 1. Total & Active Students
$res = $conn->query("SELECT 
    COUNT(*) AS total,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active_cnt,
    SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) AS inactive_cnt
    FROM students WHERE role = 'student'");
if ($res && $row = $res->fetch_assoc()) {
    $total_students    = (int)$row['total'];
    $active_students   = (int)$row['active_cnt'];
    $inactive_students = (int)$row['inactive_cnt'];
}

// 2. Events Count
$res = $conn->query("SELECT COUNT(*) AS total FROM events");
if ($res && $row = $res->fetch_assoc()) {
    $total_events = (int)$row['total'];
}

// 3. Registrations Count
$res = $conn->query("SELECT COUNT(*) AS total FROM registrations");
if ($res && $row = $res->fetch_assoc()) {
    $total_registrations = (int)$row['total'];
}

// 4. Fetch 5 Recent Students (Ascending Order)
$recent_students = [];
$res = $conn->query("SELECT student_id, name, email, course, year, status FROM students WHERE role = 'student' ORDER BY student_id ASC LIMIT 5");
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $recent_students[] = $r;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - StudentHub</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', -apple-system, sans-serif;
        }

        body {
            background: #f4f6fa;
            color: #1e293b;
        }

        /* Navbar */
        nav.admin-nav {
            background: #0f172a;
            color: white;
            padding: 16px 8%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .brand-group {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand-text {
            font-size: 24px;
            font-weight: 700;
            color: #818cf8;
        }

        .admin-tag {
            background: #ef4444;
            color: white;
            font-size: 11px;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 12px;
            text-transform: uppercase;
            letter-spacing: 0.6px;
        }

        .admin-nav-links {
            display: flex;
            align-items: center;
            gap: 22px;
        }

        .admin-nav-links a {
            color: #cbd5e1;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: color 0.2s;
        }

        .admin-nav-links a:hover {
            color: #ffffff;
        }

        .btn-logout {
            background: #ef4444 !important;
            color: white !important;
            padding: 8px 18px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
        }

        /* Main Container */
        .admin-container {
            padding: 40px 8%;
            max-width: 1350px;
            margin: 0 auto;
        }

        .top-banner {
            background: linear-gradient(135deg, #1e1b4b, #312e81);
            color: white;
            padding: 35px 40px;
            border-radius: 20px;
            margin-bottom: 35px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            box-shadow: 0 10px 30px rgba(49, 46, 129, 0.25);
        }

        .top-banner h1 {
            font-size: 26px;
            margin-bottom: 8px;
        }

        .top-banner p {
            color: #c7d2fe;
            font-size: 14px;
        }

        .admin-actions {
            display: flex;
            gap: 12px;
        }

        .btn-action-primary {
            background: #6C63FF;
            color: white;
            padding: 11px 22px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: background 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-action-primary:hover {
            background: #5148d8;
        }

        .btn-action-outline {
            background: rgba(255, 255, 255, 0.12);
            color: white;
            padding: 11px 20px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-action-outline:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        /* Metric Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 35px;
        }

        .stat-card {
            background: white;
            padding: 24px;
            border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.04);
            border: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 18px;
        }

        .stat-icon {
            width: 54px;
            height: 54px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
        }

        .icon-blue { background: #dbeafe; color: #1d4ed8; }
        .icon-green { background: #dcfce7; color: #15803d; }
        .icon-orange { background: #ffedd5; color: #c2410c; }
        .icon-purple { background: #f3e8ff; color: #7e22ce; }

        .stat-content .stat-num {
            font-size: 26px;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.1;
        }

        .stat-content .stat-label {
            font-size: 13px;
            color: #64748b;
            margin-top: 4px;
        }

        /* Recent Table Card */
        .content-card {
            background: white;
            border-radius: 18px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.04);
            border: 1px solid #e2e8f0;
        }

        .card-header-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 22px;
        }

        .card-header-bar h2 {
            font-size: 20px;
            color: #0f172a;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table.admin-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        table.admin-table th {
            background: #f8fafc;
            color: #475569;
            font-weight: 600;
            text-align: left;
            padding: 12px 16px;
            border-bottom: 2px solid #e2e8f0;
        }

        table.admin-table td {
            padding: 14px 16px;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
        }

        table.admin-table tr:hover {
            background: #f8fafc;
        }

        .status-pill {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .pill-active { background: #dcfce7; color: #15803d; }
        .pill-inactive { background: #fee2e2; color: #b91c1c; }
    </style>
</head>
<body>

    <!-- ================= NAVBAR ================= -->
    <nav class="admin-nav">
        <div class="brand-group">
            <span class="brand-text">StudentHub</span>
            <span class="admin-tag">Administrator</span>
        </div>

        <div class="admin-nav-links">
            <a href="admin_dashboard.php" style="color:#ffffff; font-weight:700;">Admin Dashboard</a>
            <a href="manage_students.php">Manage Students (CRUD)</a>
            <a href="../events.html">Events Directory</a>
            <a href="dashboard.php">Student View</a>
            <a href="logout.php" class="btn-logout">Logout ⏻</a>
        </div>
    </nav>

    <!-- ================= ADMIN CONTENT ================= -->
    <main class="admin-container">

        <!-- Top Banner -->
        <section class="top-banner">
            <div>
                <h1>Administrator Console</h1>
                <p>Logged in as: <strong><?= e($admin_user['email']) ?></strong> (Role: Administrator)</p>
            </div>
            <div class="admin-actions">
                <a href="manage_students.php" class="btn-action-primary">
                    👥 Manage Students
                </a>
                <a href="manage_students.php?export=csv" class="btn-action-outline">
                    ⬇ Export CSV
                </a>
            </div>
        </section>

        <!-- Metrics Row -->
        <section class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon icon-blue">👥</div>
                <div class="stat-content">
                    <div class="stat-num"><?= $total_students ?></div>
                    <div class="stat-label">Total Registered Students</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon icon-green">✓</div>
                <div class="stat-content">
                    <div class="stat-num"><?= $active_students ?></div>
                    <div class="stat-label">Active Accounts</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon icon-orange">⏸</div>
                <div class="stat-content">
                    <div class="stat-num"><?= $inactive_students ?></div>
                    <div class="stat-label">Soft-Deleted (Inactive)</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon icon-purple">📅</div>
                <div class="stat-content">
                    <div class="stat-num"><?= $total_events ?></div>
                    <div class="stat-label">College Events (<?= $total_registrations ?> signs)</div>
                </div>
            </div>
        </section>

        <!-- Quick Students List Card -->
        <section class="content-card">
            <div class="card-header-bar">
                <h2>Recently Registered Students</h2>
                <a href="manage_students.php" style="color:#6C63FF; text-decoration:none; font-weight:600; font-size:14px;">
                    View All Students →
                </a>
            </div>

            <div class="table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th style="width:50px;">Sr.</th>
                            <th>ID</th>
                            <th>Student Name</th>
                            <th>Email Address</th>
                            <th>Course</th>
                            <th>Year</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_students)): ?>
                            <tr>
                                <td colspan="8" style="text-align:center; padding:25px; color:#64748b;">
                                    No student records found in database.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $dash_sr = 0; foreach ($recent_students as $st): $dash_sr++; ?>
                                <tr>
                                    <td style="color:#64748b; font-weight:600;"><?= $dash_sr ?></td>
                                    <td>#<?= e($st['student_id']) ?></td>
                                    <td><strong><?= e($st['name']) ?></strong></td>
                                    <td><?= e($st['email']) ?></td>
                                    <td><?= e($st['course']) ?></td>
                                    <td>Year <?= e($st['year']) ?></td>
                                    <td>
                                        <span class="status-pill <?= $st['status'] === 'active' ? 'pill-active' : 'pill-inactive' ?>">
                                            <?= ucfirst(e($st['status'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="manage_students.php?action=edit&id=<?= urlencode($st['student_id']) ?>" style="color:#2563eb; text-decoration:none; font-weight:600; margin-right:12px;">Edit</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

    </main>

</body>
</html>
