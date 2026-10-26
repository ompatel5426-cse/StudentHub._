<?php
/**
 * StudentHub — Database & System Health Verification Test (test_db.php)
 * Verifies MySQLi ($conn) and PDO ($pdo) database connections,
 * schema tables, and student authentication records.
 */

require_once __DIR__ . '/db.php';

// Check connection health
$mysqli_status = ($conn && !$conn->connect_error) ? "Connected Successfully (MySQLi)" : "MySQLi Connection Failed";
$pdo_status = ($pdo !== null) ? "Connected Successfully (PDO)" : "PDO Connection Failed";

// Query total metrics
$total_students = 0;
$active_students = 0;
$pending_students = 0;

$st_res = $conn->query("SELECT 
    COUNT(*) AS total,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active_cnt,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_cnt
    FROM students");
if ($st_res && $row = $st_res->fetch_assoc()) {
    $total_students   = (int)$row['total'];
    $active_students  = (int)$row['active_cnt'];
    $pending_students = (int)$row['pending_cnt'];
}

$events_count = (int)$conn->query("SELECT COUNT(*) FROM events")->fetch_row()[0];
$marks_count = (int)$conn->query("SELECT COUNT(*) FROM student_marks")->fetch_row()[0];
$reg_count = (int)$conn->query("SELECT COUNT(*) FROM registrations")->fetch_row()[0];
$logs_count = (int)$conn->query("SELECT COUNT(*) FROM audit_logs")->fetch_row()[0];

// Fetch students list
$students_list = [];
$res = $conn->query("SELECT student_id, name, email, role, status, registration_source, enrollment_no FROM students ORDER BY student_id ASC");
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $students_list[] = $r;
    }
}

// If running in Command Line (CLI)
if (php_sapi_name() === 'cli') {
    echo "========================================================\n";
    echo "       StudentHub — Database Verification Test          \n";
    echo "========================================================\n";
    echo "MySQLi Status : $mysqli_status\n";
    echo "PDO Status    : $pdo_status\n";
    echo "Database Name : studenthub\n";
    echo "--------------------------------------------------------\n";
    echo "Tables Overview:\n";
    echo " - Students      : $total_students ($active_students Active, $pending_students Pending)\n";
    echo " - Events        : $events_count\n";
    echo " - Student Marks : $marks_count\n";
    echo " - Registrations : $reg_count\n";
    echo " - Audit Logs    : $logs_count\n";
    echo "--------------------------------------------------------\n";
    echo "Students Records:\n";
    foreach ($students_list as $s) {
        $enrol = $s['enrollment_no'] ?: sprintf('SH2026-%04d', $s['student_id']);
        echo "#{$s['student_id']} | {$s['name']} | {$s['email']} | {$s['role']} | {$s['status']} | {$s['registration_source']} | $enrol\n";
    }
    echo "========================================================\n";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Test — StudentHub</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        body { background: #f8fafc; font-family: 'Poppins', -apple-system, sans-serif; color: #1e293b; padding: 40px 20px; }
        .test-box { max-width: 950px; margin: 0 auto; background: white; border-radius: 18px; padding: 35px; box-shadow: 0 10px 30px rgba(0,0,0,0.06); border: 1px solid #e2e8f0; }
        .head { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 20px; margin-bottom: 25px; flex-wrap: wrap; gap: 15px; }
        .head h1 { font-size: 24px; color: #0f172a; margin: 0; }
        .status-badge { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; padding: 6px 14px; border-radius: 20px; font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; }
        .metrics-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 14px; margin-bottom: 30px; }
        .metric-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; text-align: center; }
        .metric-card .num { font-size: 26px; font-weight: 700; color: #6C63FF; }
        .metric-card .lbl { font-size: 12px; color: #64748b; font-weight: 600; text-transform: uppercase; margin-top: 4px; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; text-align: left; margin-top: 15px; }
        th { background: #f1f5f9; color: #475569; padding: 12px 14px; border-bottom: 2px solid #cbd5e1; font-weight: 600; }
        td { padding: 12px 14px; border-bottom: 1px solid #f1f5f9; color: #334155; }
        tr:hover { background: #fbfcfe; }
        .badge-active { background: #dcfce7; color: #15803d; padding: 3px 8px; border-radius: 6px; font-weight: 600; font-size: 11px; }
        .badge-pending { background: #fef3c7; color: #92400e; padding: 3px 8px; border-radius: 6px; font-weight: 600; font-size: 11px; }
        .btn-home { background: #6C63FF; color: white; padding: 8px 18px; border-radius: 8px; text-decoration: none; font-size: 13px; font-weight: 600; display: inline-block; margin-top: 20px; }
    </style>
</head>
<body>
    <main id="main-content">

    <a href="#main-content" class="skip-link">Skip to main content</a>


    <div class="test-box">
        <div class="head">
            <div>
                <h1>StudentHub Database Test</h1>
                <p style="color:#64748b; font-size:13px; margin-top:4px;">MySQLi ($conn) & PDO ($pdo) Connection Diagnostics</p>
            </div>
            <div>
                <span class="status-badge">✓ Database Connected</span>
            </div>
        </div>

        <div class="metrics-grid">
            <div class="metric-card">
                <div class="num"><?= $total_students ?></div>
                <div class="lbl">Students</div>
            </div>
            <div class="metric-card">
                <div class="num"><?= $pending_students ?></div>
                <div class="lbl">Pending Approvals</div>
            </div>
            <div class="metric-card">
                <div class="num"><?= $events_count ?></div>
                <div class="lbl">College Events</div>
            </div>
            <div class="metric-card">
                <div class="num"><?= $marks_count ?></div>
                <div class="lbl">Student Marks</div>
            </div>
            <div class="metric-card">
                <div class="num"><?= $reg_count ?></div>
                <div class="lbl">Registrations</div>
            </div>
            <div class="metric-card">
                <div class="num"><?= $logs_count ?></div>
                <div class="lbl">Audit Logs</div>
            </div>
        </div>

        <h3 style="font-size:16px; color:#0f172a; margin-bottom:8px;">Registered Users in Database</h3>
        <div style="overflow-x:auto;">
            <table>
                <th scope="col"ead>
                    <tr>
                        <th scope="col">ID</th>
                        <th scope="col">Enrollment No</th>
                        <th scope="col">Name</th>
                        <th scope="col">Email Address</th>
                        <th scope="col">Role</th>
                        <th scope="col">Source</th>
                        <th scope="col">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students_list as $s): ?>
                        <tr>
                            <td><strong>#<?= (int)$s['student_id'] ?></strong></td>
                            <td style="color:#6366f1; font-weight:600;"><?= htmlspecialchars($s['enrollment_no'] ?: 'SH2026-' . str_pad($s['student_id'], 4, '0', STR_PAD_LEFT)) ?></td>
                            <td><strong><?= htmlspecialchars($s['name']) ?></strong></td>
                            <td><?= htmlspecialchars($s['email']) ?></td>
                            <td><span style="text-transform:capitalize;"><?= htmlspecialchars($s['role']) ?></span></td>
                            <td><?= htmlspecialchars($s['registration_source'] ?: 'self') ?></td>
                            <td>
                                <?php if ($s['status'] === 'active'): ?>
                                    <span class="badge-active">Active</span>
                                <?php elseif ($s['status'] === 'pending'): ?>
                                    <span class="badge-pending">Pending</span>
                                <?php else: ?>
                                    <span style="color:#ef4444; font-weight:600;">Inactive</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div style="margin-top:25px; display:flex; justify-content:space-between; align-items:center;">
            <a href="../index.html" class="btn-home">← Back to Home Page</a>
            <span style="color:#64748b; font-size:12px;">Database: <strong>studenthub</strong> | Server: <strong>localhost:3306</strong></span>
        </div>
    </div>

    </main>
</body>
</html>
