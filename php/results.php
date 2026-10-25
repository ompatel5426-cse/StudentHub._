<?php
/**
 * StudentHub — Dynamic Student Results & Semester Marks
 * Displays actual student marks from MySQL `student_marks` table.
 * If no marks have been entered by the administrator, renders the
 * "Marks Not Added Yet" / "Not add marks" empty state.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

start_secure_session();
require_login();

$student_id = (int)($_SESSION['student_id'] ?? 0);

// Fetch logged in student details
$stmt = $conn->prepare("SELECT student_id, name, email, course, year, enrollment_no FROM students WHERE student_id = ? LIMIT 1");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) {
    header("Location: logout.php");
    exit;
}

$enrollment_display = !empty($student['enrollment_no']) ? $student['enrollment_no'] : sprintf('SH2026-%04d', $student['student_id']);

// Fetch all marks entered for this student
$marks_stmt = $conn->prepare("SELECT mark_id, semester, subject_name, subject_code, internal_marks, external_marks, total_marks, grade FROM student_marks WHERE student_id = ? ORDER BY semester ASC, mark_id ASC");
$marks_stmt->bind_param("i", $student_id);
$marks_stmt->execute();
$marks_res = $marks_stmt->get_result();

$marks_list = [];
$total_obtained = 0;
$total_max = 0;
$has_failed = false;

while ($row = $marks_res->fetch_assoc()) {
    $marks_list[] = $row;
    $total_obtained += (int)$row['total_marks'];
    $total_max += 100;
    if ((int)$row['total_marks'] < 40) {
        $has_failed = true;
    }
}
$marks_stmt->close();

$has_marks = count($marks_list) > 0;

// Calculate performance metrics if marks exist
$percentage = 0;
$sgpa = 0;
$result_status = "PASS";

if ($has_marks && $total_max > 0) {
    $percentage = round(($total_obtained / $total_max) * 100, 2);
    // Standard 10-point scale conversion
    $sgpa = round($percentage / 9.5, 2);
    if ($sgpa > 10.0) $sgpa = 10.0;
    $result_status = $has_failed ? "REAPPEAR" : "PASS";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Semester Results — StudentHub</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/results.css">
</head>
<body>

    <!-- ================= NAVBAR ================= -->
    <header>
        <div class="logo">
            <h2>StudentHub</h2>
        </div>
        <nav id="mainNav">
            <a href="dashboard.php">Dashboard</a>
            <a href="profile.php">My Profile 👤</a>
            <a href="../courses.html">Courses</a>
            <a href="../events.html">Events</a>
            <a href="results.php" style="color:#6C63FF; font-weight:700;">My Results 📊</a>
            <a href="logout.php" class="btn-nav-logout">Logout ⏻</a>
        </nav>
    </header>

    <!-- ================= MAIN CONTENT ================= -->
    <main class="results-container">

        <!-- Page Header Card -->
        <section class="page-title-card">
            <div>
                <h1>Semester Examination Results</h1>
                <p>Official student performance scorecard & subject-wise grade evaluation.</p>
            </div>
            <div class="student-badge-strip">
                <span>👤 <strong><?= e($student['name']) ?></strong></span>
                <span>🆔 <strong><?= e($enrollment_display) ?></strong></span>
                <span>🎓 <strong><?= e($student['course'] ?? 'Computer Science') ?> (Year <?= e($student['year'] ?? '1') ?>)</strong></span>
            </div>
        </section>

        <?php if (!$has_marks): ?>
            <!-- ================= EMPTY STATE: NOT ADD MARKS ================= -->
            <section class="empty-marks-card">
                <div class="empty-icon">📊</div>
                <h2>Marks Not Added Yet</h2>
                <div class="empty-badge">⚠️ Not add marks / Pending Evaluation</div>
                <p>
                    Your semester marks have not been added or published by the administrator yet.
                    Once the college examination branch enters your internal and external marks, your overall SGPA, grade report, and subject-wise score will automatically appear here.
                </p>

                <div class="student-info-strip">
                    <div>
                        <span>Student Name</span>
                        <strong><?= e($student['name']) ?></strong>
                    </div>
                    <div>
                        <span>Enrollment No</span>
                        <strong><?= e($enrollment_display) ?></strong>
                    </div>
                    <div>
                        <span>Registered Course</span>
                        <strong><?= e($student['course'] ?? 'BCA') ?></strong>
                    </div>
                    <div>
                        <span>Academic Year</span>
                        <strong>Year <?= e($student['year'] ?? '1') ?></strong>
                    </div>
                </div>
            </section>

        <?php else: ?>
            <!-- ================= REAL MARKS SUMMARY ================= -->
            <section class="summary-grid">
                <div class="summary-card">
                    <div class="card-icon-circle circle-purple">🎯</div>
                    <div class="summary-card-info">
                        <h3>SGPA</h3>
                        <div class="val"><?= number_format($sgpa, 2) ?></div>
                    </div>
                </div>

                <div class="summary-card">
                    <div class="card-icon-circle circle-blue">📈</div>
                    <div class="summary-card-info">
                        <h3>Percentage</h3>
                        <div class="val"><?= number_format($percentage, 1) ?>%</div>
                    </div>
                </div>

                <div class="summary-card">
                    <div class="card-icon-circle circle-amber">📝</div>
                    <div class="summary-card-info">
                        <h3>Total Marks</h3>
                        <div class="val"><?= $total_obtained ?> / <?= $total_max ?></div>
                    </div>
                </div>

                <div class="summary-card">
                    <div class="card-icon-circle circle-green">
                        <?= $result_status === 'PASS' ? '✓' : '⚠' ?>
                    </div>
                    <div class="summary-card-info">
                        <h3>Result Status</h3>
                        <div class="val" style="color: <?= $result_status === 'PASS' ? '#15803d' : '#b91c1c' ?>;">
                            <?= $result_status ?>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ================= SUBJECT-WISE TABLE ================= -->
            <section class="table-card">
                <div class="table-header-box">
                    <h2>Subject-Wise Performance & Grades</h2>
                    <span style="font-size:13px; color:#64748b; font-weight:500;">
                        Total Subjects Evaluated: <strong><?= count($marks_list) ?></strong>
                    </span>
                </div>

                <div class="table-responsive">
                    <table class="marks-table">
                        <thead>
                            <tr>
                                <th style="width:50px;">Sr.</th>
                                <th>Semester</th>
                                <th>Subject Code</th>
                                <th>Subject Name</th>
                                <th style="text-align:center;">Internal (40)</th>
                                <th style="text-align:center;">External (60)</th>
                                <th style="text-align:center;">Total (100)</th>
                                <th style="text-align:center;">Grade</th>
                                <th style="text-align:center;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $sr = 0; foreach ($marks_list as $m): $sr++; ?>
                                <tr>
                                    <td style="color:#64748b; font-weight:600;"><?= $sr ?></td>
                                    <td><strong><?= e($m['semester']) ?></strong></td>
                                    <td><code><?= e($m['subject_code'] ?: '—') ?></code></td>
                                    <td><strong style="color:#0f172a;"><?= e($m['subject_name']) ?></strong></td>
                                    <td style="text-align:center;"><?= (int)$m['internal_marks'] ?></td>
                                    <td style="text-align:center;"><?= (int)$m['external_marks'] ?></td>
                                    <td style="text-align:center;"><strong><?= (int)$m['total_marks'] ?></strong></td>
                                    <td style="text-align:center;">
                                        <span class="badge-grade"><?= e($m['grade']) ?></span>
                                    </td>
                                    <td style="text-align:center;">
                                        <?php if ((int)$m['total_marks'] >= 40): ?>
                                            <span class="badge-pass">PASS</span>
                                        <?php else: ?>
                                            <span class="badge-fail">FAIL</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endif; ?>

    </main>

</body>
</html>
