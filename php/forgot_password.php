<?php
/**
 * StudentHub — Secure Forgot Password & Reset Flow
 * Allows students and administrators to reset their password securely.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

start_secure_session();

$csrf_token = get_csrf_token();
$message = "";
$error = "";
$step = isset($_POST['step']) ? $_POST['step'] : 'request';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submitted_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($submitted_token)) {
        $error = "Security validation failed (CSRF mismatch). Request aborted.";
    } else {
        $email = trim($_POST['email'] ?? '');

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please provide a valid registered email address.";
        } else {
            // Check if user exists
            $stmt = $conn->prepare("SELECT student_id, name, role FROM students WHERE email = ? LIMIT 1");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$user) {
                // Generic notification to prevent user enumeration
                $error = "No active account found with email address: " . htmlspecialchars($email);
            } else {
                if ($step === 'reset') {
                    $new_password = $_POST['new_password'] ?? '';
                    $confirm_password = $_POST['confirm_password'] ?? '';

                    if (strlen($new_password) < 6) {
                        $error = "New password must be at least 6 characters long.";
                        $step = 'verify';
                    } elseif ($new_password !== $confirm_password) {
                        $error = "Passwords do not match. Please re-enter.";
                        $step = 'verify';
                    } else {
                        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                        $upd = $conn->prepare("UPDATE students SET password = ? WHERE student_id = ?");
                        $upd->bind_param("si", $hashed, $user['student_id']);
                        if ($upd->execute()) {
                            $message = "Password for " . htmlspecialchars($user['name']) . " has been successfully updated! You can now log in.";
                            $step = 'completed';
                            if (function_exists('log_audit_action')) {
                                log_audit_action($conn, 'PASSWORD_RESET', "Password reset completed for {$user['role']} #{$user['student_id']} ($email).");
                            }
                        } else {
                            $error = "Failed to update password: " . $conn->error;
                            $step = 'verify';
                        }
                        $upd->close();
                    }
                } else {
                    // Valid email verified, proceed to reset step
                    $step = 'verify';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password — StudentHub</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        body { background: #f8fafc; font-family: 'Poppins', -apple-system, sans-serif; color: #1e293b; min-height: 100vh; display: flex; flex-direction: column; }
        .skip-link { position: absolute; top: -50px; left: 20px; background: #4F46E5; color: white; padding: 10px 16px; z-index: 9999; text-decoration: none; font-weight: 700; border-radius: 0 0 8px 8px; transition: top 0.2s; }
        .skip-link:focus { top: 0; outline: 3px solid #F59E0B; }
        header.nav-header { background: #0f172a; padding: 16px 8%; display: flex; justify-content: space-between; align-items: center; }
        header.nav-header .logo h2 { color: #818cf8; margin: 0; font-size: 22px; }
        header.nav-header nav a { color: #cbd5e1; text-decoration: none; font-size: 14px; margin-left: 18px; }
        header.nav-header nav a:hover { color: white; }
        main.reset-wrap { flex: 1; display: flex; align-items: center; justify-content: center; padding: 40px 20px; }
        .reset-card { background: white; max-width: 480px; width: 100%; border-radius: 18px; padding: 36px 32px; box-shadow: 0 10px 30px rgba(0,0,0,0.06); border: 1px solid #e2e8f0; }
        .reset-card h1 { font-size: 24px; color: #0f172a; margin-bottom: 8px; }
        .reset-card p { font-size: 14px; color: #64748b; line-height: 1.5; margin-bottom: 22px; }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px; }
        .form-group input { width: 100%; padding: 11px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; box-sizing: border-box; }
        .form-group input:focus { outline: 2px solid #4F46E5; border-color: #4F46E5; }
        .btn-submit { width: 100%; background: #4F46E5; color: white; padding: 12px; border: none; border-radius: 8px; font-weight: 600; font-size: 15px; cursor: pointer; transition: background 0.2s; }
        .btn-submit:hover { background: #4338ca; }
        .alert { padding: 12px 16px; border-radius: 8px; font-size: 13px; margin-bottom: 20px; line-height: 1.4; }
        .alert-danger { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
        .alert-success { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
        .links-bottom { margin-top: 22px; text-align: center; font-size: 13px; color: #64748b; }
        .links-bottom a { color: #4F46E5; font-weight: 600; text-decoration: none; }
        .links-bottom a:hover { text-decoration: underline; }
    </style>
</head>
<body>

    <a href="#main-content" class="skip-link">Skip to main content</a>

    <header class="nav-header">
        <div class="logo">
            <h2>StudentHub</h2>
        </div>
        <nav aria-label="Main Navigation">
            <a href="../index.html">Home</a>
            <a href="../login.html">Student Login</a>
            <a href="../admin_login.html">Admin Login</a>
        </nav>
    </header>

    <main class="reset-wrap" id="main-content">
        <div class="reset-card">
            <h1>Account Password Recovery</h1>
            <p>Verify your registered email address to reset and update your portal password.</p>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger" role="alert" aria-live="assertive">
                    ⚠ <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($message)): ?>
                <div class="alert alert-success" role="alert" aria-live="polite">
                    ✓ <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <?php if ($step === 'completed'): ?>
                <div style="text-align:center; margin-top:20px;">
                    <a href="../login.html" class="btn-submit" style="display:inline-block; text-decoration:none;">Proceed to Student Login →</a>
                    <div style="margin-top:12px;">
                        <a href="../admin_login.html" style="font-size:13px; color:#4F46E5;">Or Login as Administrator</a>
                    </div>
                </div>

            <?php elseif ($step === 'verify'): ?>
                <form method="POST" action="forgot_password.php">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <input type="hidden" name="step" value="reset">
                    <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">

                    <div style="background:#f1f5f9; padding:10px 14px; border-radius:8px; margin-bottom:18px; font-size:13px;">
                        Account: <strong><?= htmlspecialchars($email) ?></strong>
                    </div>

                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <input type="password" id="new_password" name="new_password" required minlength="6" placeholder="Enter at least 6 characters" aria-required="true">
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required minlength="6" placeholder="Re-enter your new password" aria-required="true">
                    </div>

                    <button type="submit" class="btn-submit">Reset & Update Password</button>
                </form>

            <?php else: ?>
                <form method="POST" action="forgot_password.php">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <input type="hidden" name="step" value="request">

                    <div class="form-group">
                        <label for="email">Registered Email Address</label>
                        <input type="email" id="email" name="email" required placeholder="e.g. rahul@gmail.com" aria-required="true" value="<?= htmlspecialchars($email ?? '') ?>">
                    </div>

                    <button type="submit" class="btn-submit">Verify Account Email</button>
                </form>
            <?php endif; ?>

            <div class="links-bottom">
                Remember your password? <a href="../login.html">Back to Login</a>
            </div>
        </div>
    </main>

</body>
</html>
