<?php
/**
 * StudentHub — Practical 14: Administrator Dashboard with RBAC, Dynamic Menu & Audit Log
 * Enhanced with:
 *  - Dynamic SQL Aggregations (Students, Events, Registrations, Audit Logs)
 *  - Active/Inactive Quick Status Toggle (Intermediate Extension)
 *  - Registration Analytics Chart (Advanced Extension)
 *  - Recent Audit Log entries directly on dashboard
 *  - Recent Event Registrations with Joined Student & Event data
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

start_secure_session();
require_admin();

$csrf_token = get_csrf_token();
$admin_user = get_logged_user();

// Flash Messages
$flash_msg = "";
if (isset($_GET['msg']) && $_GET['msg'] === 'toggled') {
    $flash_msg = "Student status updated successfully.";
}

// Quick Student Status Toggle (Intermediate Extension)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_student') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $sid = (int)$_POST['student_id'];
        $new_st = $_POST['new_status'] === 'inactive' ? 'inactive' : 'active';
        $stmt = $conn->prepare("UPDATE students SET status = ? WHERE student_id = ? AND role = 'student'");
        $stmt->bind_param("si", $new_st, $sid);
        if ($stmt->execute()) {
            log_audit_action($conn, 'STUDENT_STATUS_TOGGLE', "Changed student #$sid status to $new_st from dashboard.");
        }
        $stmt->close();
        header("Location: admin_dashboard.php?msg=toggled");
        exit;
    }
}

// =========================================================================
// 1. DYNAMIC METRICS AGGREGATION
// =========================================================================
$total_students = 0; $active_students = 0; $inactive_students = 0; $pending_students = 0;
$res = $conn->query("SELECT 
    COUNT(*) AS total,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active_cnt,
    SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) AS inactive_cnt,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_cnt
    FROM students WHERE role = 'student'");
if ($res && $row = $res->fetch_assoc()) {
    $total_students    = (int)$row['total'];
    $active_students   = (int)$row['active_cnt'];
    $inactive_students = (int)$row['inactive_cnt'];
    $pending_students  = (int)$row['pending_cnt'];
}

$total_events = 0; $open_events = 0; $closed_events = 0;
$res = $conn->query("SELECT 
    COUNT(*) AS total,
    SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) AS open_cnt,
    SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) AS closed_cnt
    FROM events");
if ($res && $row = $res->fetch_assoc()) {
    $total_events  = (int)$row['total'];
    $open_events   = (int)$row['open_cnt'];
    $closed_events = (int)$row['closed_cnt'];
}

$total_registrations = 0;
$res = $conn->query("SELECT COUNT(*) AS total FROM registrations");
if ($res && $row = $res->fetch_assoc()) {
    $total_registrations = (int)$row['total'];
}

$total_audit_logs = 0;
$res = $conn->query("SELECT COUNT(*) AS total FROM audit_logs");
if ($res && $row = $res->fetch_assoc()) {
    $total_audit_logs = (int)$row['total'];
}

// 2. Events by Category (For Analytics Chart - Advanced Extension)
$cat_data = [];
$res = $conn->query("SELECT category, COUNT(*) as cnt FROM events GROUP BY category ORDER BY cnt DESC");
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $cat_data[$r['category']] = (int)$r['cnt'];
    }
}

// 3. Recent 5 Students (Ascending Order with Sr. No.)
$recent_students = [];
$res = $conn->query("SELECT student_id, name, email, course, year, status FROM students WHERE role = 'student' ORDER BY student_id ASC LIMIT 5");
if ($res) {
    while ($r = $res->fetch_assoc()) $recent_students[] = $r;
}

// 4. Latest Event Registrations
$latest_registrations = [];
$res = $conn->query("SELECT r.registration_id, s.name AS student_name, s.email AS student_email, e.event_name, r.registration_date 
                     FROM registrations r 
                     JOIN students s ON r.student_id = s.student_id 
                     JOIN events e ON r.event_id = e.event_id 
                     ORDER BY r.registration_date DESC LIMIT 5");
if ($res) {
    while ($r = $res->fetch_assoc()) $latest_registrations[] = $r;
}

// 5. Recent 5 Audit Logs
$recent_logs = [];
$res = $conn->query("SELECT log_id, user_email, role, action, details, created_at FROM audit_logs ORDER BY log_id DESC LIMIT 5");
if ($res) {
    while ($r = $res->fetch_assoc()) $recent_logs[] = $r;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard </title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin_dashboard.css">
</head>
<body>

    <!-- DYNAMIC ROLE-BASED NAVBAR (Practical 14) -->
    <nav class="admin-nav">
        <div style="display:flex; align-items:center; gap:8px;">
            <span class="brand-text">StudentHub</span>
            <span class="admin-tag">Administrator</span>
        </div>

        <div class="admin-nav-links">
            <a href="admin_dashboard.php" style="color:white; font-weight:700;">Dashboard</a>
            <a href="manage_students.php">Students (CRUD)</a>
            <a href="manage_events.php">Events (CRUD)</a>
            <a href="ajax_events.php" style="color:#38bdf8;">AJAX API ⚡</a>
            <a href="audit_logs.php">Audit Logs 📋</a>
            <a href="dashboard.php" target="_blank" style="color:#a78bfa;">Student View ↗</a>
            <a href="logout.php" class="btn-logout">Logout ⏻</a>
        </div>
    </nav>

    <main class="admin-container">

        <!-- Top Welcome Banner -->
        <section class="top-banner">
            <div>
                <h1>Administrator Console</h1>
                <p>Welcome back, <strong><?= e($admin_user['email']) ?></strong> | Role: <strong>Administrator</strong></p>
            </div>
            <div style="display:flex; gap:10px;">
                <a href="manage_students.php" class="btn-action-primary">👥 Manage Students</a>
                <a href="manage_events.php" class="btn-action-primary">🏆 Manage Events</a>
                <a href="audit_logs.php" class="btn-action-outline">📋 View Audit Trail</a>
            </div>
        </section>

        <!-- Pending Registrations Banner -->
        <?php if ($pending_students > 0): ?>
            <div style="background:#fffbeb; border:1px solid #fde68a; border-left:5px solid #f59e0b; padding:16px 22px; border-radius:14px; margin-bottom:24px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:14px; box-shadow:0 4px 15px rgba(245,158,11,0.08);">
                <div style="display:flex; align-items:center; gap:14px;">
                    <span style="font-size:26px;">🔔</span>
                    <div>
                        <strong style="color:#92400e; font-size:15px; display:block; margin-bottom:3px;">
                            <?= $pending_students ?> Online Student Registration(s) Awaiting Approval!
                        </strong>
                        <p style="color:#b45309; font-size:13px; margin:0;">
                            External students registered online. Please approve them (Add to College) or reject/remove invalid applications.
                        </p>
                    </div>
                </div>
                <div>
                    <a href="manage_students.php?status=pending" style="background:#f59e0b; color:white; padding:9px 18px; border-radius:8px; font-weight:600; text-decoration:none; font-size:13px; display:inline-block;">
                        Review & Approve Students »
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Metric Aggregation Grid (Practical 14) -->
        <section class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon icon-blue">👥</div>
                <div>
                    <div class="stat-num"><?= $total_students ?></div>
                    <div class="stat-label">Students (<?= $active_students ?> Active / <?= $inactive_students ?> Inactive)</div>
                </div>
            </div>

            <a href="manage_students.php?status=pending" class="stat-card" style="text-decoration:none; color:inherit;">
                <div class="stat-icon" style="background:#fef3c7; color:#d97706;">⏳</div>
                <div>
                    <div class="stat-num" style="color:<?= $pending_students > 0 ? '#d97706' : '#1e293b' ?>;"><?= $pending_students ?></div>
                    <div class="stat-label">Pending Approvals <?= $pending_students > 0 ? '⚠️' : '' ?></div>
                </div>
            </a>

            <div class="stat-card">
                <div class="stat-icon icon-purple">🏆</div>
                <div>
                    <div class="stat-num"><?= $total_events ?></div>
                    <div class="stat-label">College Events (<?= $open_events ?> Open / <?= $closed_events ?> Closed)</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon icon-green">📝</div>
                <div>
                    <div class="stat-num"><?= $total_registrations ?></div>
                    <div class="stat-label">Total Event Signups</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon icon-orange">📋</div>
                <div>
                    <div class="stat-num"><?= $total_audit_logs ?></div>
                    <div class="stat-label">System Audit Log Entries</div>
                </div>
            </div>
        </section>

        <!-- Analytics Chart & Quick Status Toggle Section -->
        <section class="analytics-grid">
            <!-- Event Category Analytics Chart (Advanced Extension) -->
            <div class="card-panel">
                <h2>
                    <span>📊 Event Category Distribution (Analytics)</span>
                    <span style="font-size:12px; color:#64748b; font-weight:normal;">Practical 14 Chart</span>
                </h2>
                <div class="chart-wrap">
                    <?php 
                    $max_cnt = max(1, ...array_values($cat_data ?: [1]));
                    foreach ($cat_data as $cat_name => $cnt): 
                        $pct = round(($cnt / $max_cnt) * 100);
                    ?>
                        <div class="chart-row">
                            <span class="chart-label"><?= e($cat_name) ?></span>
                            <div class="chart-bar-container">
                                <div class="chart-bar-fill" style="width: <?= $pct ?>%;"></div>
                            </div>
                            <span class="chart-val"><?= $cnt ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Quick Status Toggle (Intermediate Extension) -->
            <div class="card-panel">
                <h2>
                    <span>👥 Active / Inactive Status Toggle</span>
                    <a href="manage_students.php" style="font-size:12px; color:#6C63FF; text-decoration:none;">View All →</a>
                </h2>
                <table class="dash-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Status</th>
                            <th>Quick Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($recent_students, 0, 4) as $st): ?>
                            <tr>
                                <td>
                                    <strong><?= e($st['name']) ?></strong>
                                    <div style="font-size:11px; color:#64748b;">#<?= e($st['student_id']) ?> • <?= e($st['course']) ?></div>
                                </td>
                                <td>
                                    <span class="<?= $st['status'] === 'active' ? 'pill-active' : 'pill-inactive' ?>">
                                        <?= ucfirst(e($st['status'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <form method="POST" action="admin_dashboard.php">
                                        <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                                        <input type="hidden" name="action" value="toggle_student">
                                        <input type="hidden" name="student_id" value="<?= e($st['student_id']) ?>">
                                        <input type="hidden" name="new_status" value="<?= $st['status'] === 'active' ? 'inactive' : 'active' ?>">
                                        <button type="submit" class="btn-toggle-status">
                                            <?= $st['status'] === 'active' ? '⏸ Deactivate' : '▶ Activate' ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Latest Event Registrations & Audit Logs Row -->
        <section class="analytics-grid">
            <!-- Latest Event Registrations -->
            <div class="card-panel">
                <h2>
                    <span>📝 Latest Event Registrations</span>
                    <span style="font-size:12px; color:#64748b; font-weight:normal;">Junction Query</span>
                </h2>
                <table class="dash-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Event Name</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($latest_registrations)): ?>
                            <tr><td colspan="3" style="text-align:center; color:#64748b; padding:20px;">No event signups yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($latest_registrations as $reg): ?>
                                <tr>
                                    <td>
                                        <strong><?= e($reg['student_name']) ?></strong>
                                        <div style="font-size:11px; color:#64748b;"><?= e($reg['student_email']) ?></div>
                                    </td>
                                    <td><?= e($reg['event_name']) ?></td>
                                    <td style="font-size:12px; color:#64748b;"><?= date('d M Y', strtotime($reg['registration_date'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Recent Audit Logs Preview -->
            <div class="card-panel">
                <h2>
                    <span>📋 Recent Audit Trail</span>
                    <a href="audit_logs.php" style="font-size:12px; color:#6C63FF; text-decoration:none;">All Logs →</a>
                </h2>
                <table class="dash-table">
                    <thead>
                        <tr>
                            <th>Action</th>
                            <th>Details</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_logs as $lg): ?>
                            <tr>
                                <td><span class="badge-act act-log"><?= e($lg['action']) ?></span></td>
                                <td style="font-size:12px; color:#334155;"><?= e(substr($lg['details'], 0, 45)) ?>...</td>
                                <td style="font-size:11px; color:#64748b; white-space:nowrap;"><?= date('H:i:s', strtotime($lg['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

    </main>

</body>
</html>
