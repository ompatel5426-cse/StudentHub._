<?php
/**
 * StudentHub — Dynamic Student Profile Page
 * Displays actual student registration information and allows profile editing.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

start_secure_session();
require_login();

$student_id = (int)($_SESSION['student_id'] ?? 0);
$success_msg = "";
$error_msg = "";

// Handle Profile Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $error_msg = "Security validation failed. Please try again.";
    } else {
        $name    = trim($_POST['name'] ?? '');
        $mobile  = trim($_POST['mobile'] ?? '');
        $dob     = trim($_POST['dob'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $course  = trim($_POST['course'] ?? '');
        $year    = trim($_POST['year'] ?? '1');
        $gender  = trim($_POST['gender'] ?? 'Male');

        if ($name === '' || !preg_match("/^[A-Za-z ]{2,50}$/", $name)) {
            $error_msg = "Full name must be 2 to 50 characters (letters and spaces only).";
        } elseif ($mobile === '' || !preg_match("/^[6-9][0-9]{9}$/", $mobile)) {
            $error_msg = "Please enter a valid 10-digit Indian mobile number.";
        } else {
            $dob_val = !empty($dob) ? $dob : null;
            $upd = $conn->prepare("UPDATE students SET name = ?, mobile = ?, dob = ?, address = ?, course = ?, year = ?, gender = ? WHERE student_id = ?");
            if ($upd) {
                $upd->bind_param("sssssssi", $name, $mobile, $dob_val, $address, $course, $year, $gender, $student_id);
                if ($upd->execute()) {
                    $success_msg = "Profile information updated successfully!";
                    $_SESSION['name'] = $name;
                    $_SESSION['course'] = $course;
                    $_SESSION['year'] = $year;
                    $_SESSION['gender'] = $gender;
                    if (function_exists('log_audit_action')) {
                        log_audit_action($conn, 'STUDENT_PROFILE_UPDATE', "Student #$student_id updated their profile details.");
                    }
                } else {
                    $error_msg = "Failed to update profile: " . $conn->error;
                }
                $upd->close();
            }
        }
    }
}

// Fetch complete up-to-date student information from database
$stmt = $conn->prepare("SELECT student_id, name, email, mobile, course, year, gender, enrollment_no, dob, address, role, status, registration_source FROM students WHERE student_id = ? LIMIT 1");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$res = $stmt->get_result();
$student = $res->fetch_assoc();
$stmt->close();

if (!$student) {
    header("Location: logout.php");
    exit;
}

// Query student's event registrations count
$reg_count_res = $conn->query("SELECT COUNT(*) FROM registrations WHERE student_id = $student_id");
$total_registrations = $reg_count_res ? (int)$reg_count_res->fetch_row()[0] : 0;

// Query student's marks count
$marks_count_res = $conn->query("SELECT COUNT(*) FROM student_marks WHERE student_id = $student_id");
$total_marks_records = $marks_count_res ? (int)$marks_count_res->fetch_row()[0] : 0;

$enrollment_display = !empty($student['enrollment_no']) ? $student['enrollment_no'] : sprintf('SH2026-%04d', $student['student_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($student['name']) ?> — Student Profile</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/profile.css">
</head>
<body>

    <!-- Header Navigation -->
    <header>
        <div class="logo">
            <h2>StudentHub</h2>
        </div>
        <nav id="mainNav">
            <a href="dashboard.php">Dashboard</a>
            <a href="profile.php" style="color:#6C63FF; font-weight:700;">My Profile 👤</a>
            <a href="../courses.html">Courses</a>
            <a href="../events.html">Events</a>
            <a href="results.php">My Results 📊</a>
            <a href="logout.php" style="color:#ef4444; font-weight:600;">Logout ⏻</a>
        </nav>
    </header>

    <main class="profile-container">

        <?php if (!empty($success_msg)): ?>
            <div class="alert-banner success">
                <span>✓</span>
                <span><?= e($success_msg) ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_msg)): ?>
            <div class="alert-banner error">
                <span>⚠</span>
                <span><?= e($error_msg) ?></span>
            </div>
        <?php endif; ?>

        <!-- Profile Hero Banner -->
        <section class="profile-hero">
            <div class="profile-hero-left">
                <div class="avatar-wrap">
                    🎓
                </div>
                <div class="profile-titles">
                    <h1><?= e($student['name']) ?></h1>
                    <p><?= e($student['course'] ?? 'Computer Science') ?> • Academic Year <?= e($student['year'] ?? '1') ?></p>
                    <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                        <span class="badge-enrol">🆔 <?= e($enrollment_display) ?></span>
                        <span class="badge-status-active">✓ <?= ucfirst(e($student['status'])) ?></span>
                    </div>
                </div>
            </div>

            <div>
                <button type="button" class="btn-edit-profile" onclick="openEditProfileModal()">
                    ✏️ Edit Profile Details
                </button>
            </div>
        </section>

        <!-- Stats Overview Cards -->
        <section class="profile-stats-grid">
            <div class="stat-card">
                <div class="stat-icon-circle stat-icon-blue">🎓</div>
                <div class="stat-card-info">
                    <h3>Academic Level</h3>
                    <p>Year <?= e($student['year']) ?></p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon-circle stat-icon-purple">📚</div>
                <div class="stat-card-info">
                    <h3>Enrolled Degree</h3>
                    <p><?= e($student['course'] ?? 'BCA') ?></p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon-circle stat-icon-green">🏆</div>
                <div class="stat-card-info">
                    <h3>Events Registered</h3>
                    <p><?= $total_registrations ?> Event(s)</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon-circle stat-icon-orange">📊</div>
                <div class="stat-card-info">
                    <h3>Published Marks</h3>
                    <p><?= $total_marks_records > 0 ? ($total_marks_records . ' Subjects') : 'Not Added Yet' ?></p>
                </div>
            </div>
        </section>

        <!-- Personal Information Section -->
        <section class="profile-section-card">
            <h2>👤 Personal Information</h2>
            <div class="info-grid">
                <div class="info-field">
                    <div class="info-field-label">Full Name</div>
                    <div class="info-field-val"><?= e($student['name']) ?></div>
                </div>

                <div class="info-field">
                    <div class="info-field-label">Official Enrollment ID</div>
                    <div class="info-field-val"><?= e($enrollment_display) ?></div>
                </div>

                <div class="info-field">
                    <div class="info-field-label">Email Address</div>
                    <div class="info-field-val"><?= e($student['email']) ?></div>
                </div>

                <div class="info-field">
                    <div class="info-field-label">Mobile Number</div>
                    <div class="info-field-val"><?= !empty($student['mobile']) ? '+91 ' . e($student['mobile']) : 'Not provided' ?></div>
                </div>

                <div class="info-field">
                    <div class="info-field-label">Gender</div>
                    <div class="info-field-val"><?= e($student['gender'] ?? 'Not Specified') ?></div>
                </div>

                <div class="info-field">
                    <div class="info-field-label">Date of Birth</div>
                    <div class="info-field-val"><?= !empty($student['dob']) ? date('d F Y', strtotime($student['dob'])) : 'Not provided' ?></div>
                </div>

                <div class="info-field" style="grid-column: 1 / -1;">
                    <div class="info-field-label">Residential Address</div>
                    <div class="info-field-val"><?= !empty($student['address']) ? nl2br(e($student['address'])) : 'No residential address recorded. Click "Edit Profile" to add.' ?></div>
                </div>
            </div>
        </section>

        <!-- Academic & Portal Details Section -->
        <section class="profile-section-card">
            <h2>🎓 Academic & Portal Credentials</h2>
            <div class="info-grid">
                <div class="info-field">
                    <div class="info-field-label">Registered Course / Stream</div>
                    <div class="info-field-val"><?= e($student['course']) ?></div>
                </div>

                <div class="info-field">
                    <div class="info-field-label">Academic Year</div>
                    <div class="info-field-val">Year <?= e($student['year']) ?></div>
                </div>

                <div class="info-field">
                    <div class="info-field-label">Registration Type</div>
                    <div class="info-field-val"><?= ($student['registration_source'] ?? '') === 'self' ? 'Online Registration' : 'Department Enrolled' ?></div>
                </div>

                <div class="info-field">
                    <div class="info-field-label">Portal Account Status</div>
                    <div class="info-field-val" style="color:#15803d;">Active & Verified ✓</div>
                </div>
            </div>
        </section>

    </main>

    <!-- Edit Profile Modal -->
    <div class="modal-overlay" id="editProfileModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Profile Information</h3>
                <button type="button" class="modal-close-btn" onclick="closeEditProfileModal()">✕</button>
            </div>

            <form action="profile.php" method="POST">
                <input type="hidden" name="action" value="update_profile">
                <input type="hidden" name="csrf_token" value="<?= e(get_csrf_token()) ?>">

                <div class="form-row">
                    <label for="p_name">Full Name *</label>
                    <input type="text" id="p_name" name="name" value="<?= e($student['name']) ?>" required>
                </div>

                <div class="form-row">
                    <label for="p_mobile">Mobile Number (10 Digits) *</label>
                    <input type="tel" id="p_mobile" name="mobile" maxlength="10" value="<?= e($student['mobile']) ?>" required>
                </div>

                <div class="form-row">
                    <label for="p_dob">Date of Birth</label>
                    <input type="date" id="p_dob" name="dob" value="<?= e($student['dob'] ?? '') ?>">
                </div>

                <div class="form-row">
                    <label for="p_course">Course / Department</label>
                    <select id="p_course" name="course">
                        <?php foreach (["BCA", "BSc IT", "B.Tech", "MCA", "MSc IT", "Computer Science Engineering", "Information Technology"] as $c): ?>
                            <option value="<?= $c ?>" <?= ($student['course'] === $c) ? 'selected' : '' ?>><?= $c ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row">
                    <label for="p_year">Academic Year</label>
                    <select id="p_year" name="year">
                        <?php foreach (["1" => "1st Year", "2" => "2nd Year", "3" => "3rd Year", "4" => "4th Year"] as $val => $lbl): ?>
                            <option value="<?= $val ?>" <?= ((string)$student['year'] === (string)$val) ? 'selected' : '' ?>><?= $lbl ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row">
                    <label for="p_gender">Gender</label>
                    <select id="p_gender" name="gender">
                        <option value="Male" <?= ($student['gender'] === 'Male') ? 'selected' : '' ?>>Male</option>
                        <option value="Female" <?= ($student['gender'] === 'Female') ? 'selected' : '' ?>>Female</option>
                        <option value="Other" <?= ($student['gender'] === 'Other') ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>

                <div class="form-row">
                    <label for="p_address">Residential Address</label>
                    <textarea id="p_address" name="address" rows="3" placeholder="Enter home / hostel address"><?= e($student['address'] ?? '') ?></textarea>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn-cancel" onclick="closeEditProfileModal()">Cancel</button>
                    <button type="submit" class="btn-save">Save Profile Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Footer -->
    <footer>
        <div>
            <h3>StudentHub</h3>
            <p>Smart Student Management Portal</p>
        </div>
        <div>
            <h4>Quick Links</h4>
            <ul>
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="profile.php">My Profile</a></li>
                <li><a href="results.php">My Results</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
        <div>
            <h4>Helpdesk</h4>
            <p>Email: support@studenthub.com</p>
            <p>Phone: +91 9876543210</p>
        </div>
    </footer>

    <script src="../js/profile.js"></script>
</body>
</html>
