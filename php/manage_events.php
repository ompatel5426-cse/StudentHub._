<?php
/**
 * StudentHub — Practical 12: Event Management CRUD with Poster Upload & File Validation
 * Admin-only functionality with file type validation, size checks, safe renaming,
 * category filtering, status management, and audit logging.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

start_secure_session();
require_admin();

$csrf_token = get_csrf_token();
$flash_success = $_SESSION['flash_success'] ?? "";
$flash_error   = $_SESSION['flash_error'] ?? "";
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$upload_dir = dirname(__DIR__) . '/uploads/posters/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// =========================================================================
// 1. FILE UPLOAD & VALIDATION HELPER
// =========================================================================
function handle_poster_upload($file_array, $upload_dir, &$error_msg) {
    if (!isset($file_array) || $file_array['error'] === UPLOAD_ERR_NO_FILE) {
        return null; // No file uploaded (optional)
    }

    if ($file_array['error'] !== UPLOAD_ERR_OK) {
        $error_msg = "File upload failed with error code: " . $file_array['error'];
        return false;
    }

    // 1. Validate File Size (Max 2MB)
    $max_bytes = 2 * 1024 * 1024;
    if ($file_array['size'] > $max_bytes) {
        $error_msg = "Poster image exceeds maximum allowed size of 2MB.";
        return false;
    }

    // 2. Validate Extension & MIME Type
    $filename = $file_array['name'];
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];

    if (!in_array($ext, $allowed_exts, true)) {
        $error_msg = "Invalid file format. Allowed formats: JPG, JPEG, PNG, WEBP.";
        return false;
    }

    // Check MIME type using finfo or getimagesize
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file_array['tmp_name']);
    finfo_close($finfo);

    $allowed_mimes = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($mime, $allowed_mimes, true)) {
        $error_msg = "Security alert: Uploaded file is not a valid image.";
        return false;
    }

    // 3. Generate Secure Unique Filename (Advanced Extension)
    $safe_name = "event_" . uniqid() . "_" . time() . "." . $ext;
    $target_path = $upload_dir . $safe_name;

    if (!move_uploaded_file($file_array['tmp_name'], $target_path)) {
        $error_msg = "Failed to move uploaded poster to server directory.";
        return false;
    }

    return $safe_name;
}

// =========================================================================
// 2. PROCESS MUTATING ACTIONS (POST: ADD, EDIT, DELETE, TOGGLE_STATUS)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submitted_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($submitted_token)) {
        $_SESSION['flash_error'] = "Security validation failed (CSRF mismatch). Request aborted.";
        header("Location: manage_events.php");
        exit;
    }

    $action = $_POST['action'] ?? '';

    // --- A. CREATE EVENT ---
    if ($action === 'create') {
        $event_name  = trim($_POST['event_name'] ?? '');
        $event_date  = trim($_POST['event_date'] ?? '');
        $venue       = trim($_POST['venue'] ?? '');
        $category    = trim($_POST['category'] ?? 'Workshop');
        $status      = trim($_POST['status'] ?? 'open');
        $description = trim($_POST['description'] ?? '');

        $errors = [];
        if ($event_name === '') $errors[] = "Event title is required.";
        if ($event_date === '') $errors[] = "Event date is required.";
        if ($venue === '') $errors[] = "Event venue is required.";
        if (!in_array($status, ['open', 'closed'], true)) $status = 'open';

        $poster_error = '';
        $poster_filename = handle_poster_upload($_FILES['poster'] ?? null, $upload_dir, $poster_error);
        if ($poster_filename === false) {
            $errors[] = $poster_error;
        }

        if (!empty($errors)) {
            $_SESSION['flash_error'] = implode(" ", $errors);
            header("Location: manage_events.php");
            exit;
        }

        $stmt = $conn->prepare("INSERT INTO events (event_name, event_date, venue, description, poster, category, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssss", $event_name, $event_date, $venue, $description, $poster_filename, $category, $status);

        if ($stmt->execute()) {
            $new_event_id = $conn->insert_id;
            $_SESSION['flash_success'] = "Event '$event_name' (#$new_event_id) created successfully!";
            log_audit_action($conn, 'EVENT_CREATE', "Created event #$new_event_id ($event_name).");
        } else {
            $_SESSION['flash_error'] = "Failed to create event: " . $conn->error;
        }
        $stmt->close();
        header("Location: manage_events.php");
        exit;
    }

    // --- B. UPDATE EVENT ---
    if ($action === 'update') {
        $event_id    = (int)($_POST['event_id'] ?? 0);
        $event_name  = trim($_POST['event_name'] ?? '');
        $event_date  = trim($_POST['event_date'] ?? '');
        $venue       = trim($_POST['venue'] ?? '');
        $category    = trim($_POST['category'] ?? 'Workshop');
        $status      = trim($_POST['status'] ?? 'open');
        $description = trim($_POST['description'] ?? '');

        if ($event_id <= 0) {
            $_SESSION['flash_error'] = "Invalid event ID.";
            header("Location: manage_events.php");
            exit;
        }

        // Fetch current poster
        $curr_stmt = $conn->prepare("SELECT poster FROM events WHERE event_id = ?");
        $curr_stmt->bind_param("i", $event_id);
        $curr_stmt->execute();
        $existing_event = $curr_stmt->get_result()->fetch_assoc();
        $curr_stmt->close();

        $current_poster = $existing_event['poster'] ?? null;
        $final_poster = $current_poster;

        // Check if new poster uploaded
        if (isset($_FILES['poster']) && $_FILES['poster']['error'] !== UPLOAD_ERR_NO_FILE) {
            $poster_error = '';
            $uploaded_poster = handle_poster_upload($_FILES['poster'], $upload_dir, $poster_error);
            if ($uploaded_poster === false) {
                $_SESSION['flash_error'] = $poster_error;
                header("Location: manage_events.php");
                exit;
            }
            // Remove old poster file if exists
            if ($current_poster && file_exists($upload_dir . $current_poster)) {
                unlink($upload_dir . $current_poster);
            }
            $final_poster = $uploaded_poster;
        }

        $stmt = $conn->prepare("UPDATE events SET event_name = ?, event_date = ?, venue = ?, description = ?, poster = ?, category = ?, status = ? WHERE event_id = ?");
        $stmt->bind_param("sssssssi", $event_name, $event_date, $venue, $description, $final_poster, $category, $status, $event_id);

        if ($stmt->execute()) {
            $_SESSION['flash_success'] = "Event #$event_id updated successfully!";
            log_audit_action($conn, 'EVENT_UPDATE', "Updated event #$event_id ($event_name).");
        } else {
            $_SESSION['flash_error'] = "Failed to update event: " . $conn->error;
        }
        $stmt->close();
        header("Location: manage_events.php");
        exit;
    }

    // --- C. DELETE EVENT ---
    if ($action === 'delete') {
        $event_id = (int)($_POST['event_id'] ?? 0);
        if ($event_id > 0) {
            $stmt = $conn->prepare("SELECT event_name, poster FROM events WHERE event_id = ?");
            $stmt->bind_param("i", $event_id);
            $stmt->execute();
            $evt = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($evt) {
                // Delete poster file if present
                if (!empty($evt['poster']) && file_exists($upload_dir . $evt['poster'])) {
                    unlink($upload_dir . $evt['poster']);
                }

                $del = $conn->prepare("DELETE FROM events WHERE event_id = ?");
                $del->bind_param("i", $event_id);
                if ($del->execute()) {
                    $_SESSION['flash_success'] = "Event #$event_id deleted successfully.";
                    log_audit_action($conn, 'EVENT_DELETE', "Deleted event #$event_id ({$evt['event_name']}).");
                } else {
                    $_SESSION['flash_error'] = "Delete failed: " . $conn->error;
                }
                $del->close();
            }
        }
        header("Location: manage_events.php");
        exit;
    }

    // --- D. TOGGLE STATUS (Intermediate Extension) ---
    if ($action === 'toggle_status') {
        $event_id = (int)($_POST['event_id'] ?? 0);
        $new_status = $_POST['new_status'] === 'closed' ? 'closed' : 'open';

        $stmt = $conn->prepare("UPDATE events SET status = ? WHERE event_id = ?");
        $stmt->bind_param("si", $new_status, $event_id);
        if ($stmt->execute()) {
            $_SESSION['flash_success'] = "Event #$event_id status changed to $new_status.";
            log_audit_action($conn, 'EVENT_STATUS_TOGGLE', "Changed event #$event_id status to $new_status.");
        }
        $stmt->close();
        header("Location: manage_events.php");
        exit;
    }
}

// =========================================================================
// 3. SEARCH & FILTERING (Intermediate Extension)
// =========================================================================
$search_query   = trim($_GET['search'] ?? '');
$filter_cat     = trim($_GET['category'] ?? '');
$filter_status  = trim($_GET['status'] ?? '');

$where_clauses = ["1=1"];
$param_types = "";
$param_values = [];

if ($search_query !== "") {
    $where_clauses[] = "(event_name LIKE ? OR venue LIKE ? OR description LIKE ?)";
    $like = "%$search_query%";
    $param_types .= "sss";
    $param_values[] = $like;
    $param_values[] = $like;
    $param_values[] = $like;
}

if ($filter_cat !== "") {
    $where_clauses[] = "category = ?";
    $param_types .= "s";
    $param_values[] = $filter_cat;
}

if ($filter_status !== "" && in_array($filter_status, ['open', 'closed'], true)) {
    $where_clauses[] = "status = ?";
    $param_types .= "s";
    $param_values[] = $filter_status;
}

$where_sql = implode(" AND ", $where_clauses);
$sql = "SELECT event_id, event_name, event_date, venue, description, poster, category, status 
        FROM events 
        WHERE $where_sql 
        ORDER BY event_date ASC, event_id ASC";

$stmt = $conn->prepare($sql);
if ($param_types !== "") {
    $stmt->bind_param($param_types, ...$param_values);
}
$stmt->execute();
$events_res = $stmt->get_result();
$events = [];
while ($row = $events_res->fetch_assoc()) {
    $events[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Events - Practical 12 CRUD</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Poppins',-apple-system,sans-serif; }
        body { background: #f4f6fa; color: #1e293b; }

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
        .brand-text { font-size: 24px; font-weight: 700; color: #818cf8; }
        .admin-tag { background: #ef4444; color: white; font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 12px; text-transform: uppercase; margin-left: 8px; }
        .admin-nav-links { display: flex; align-items: center; gap: 20px; }
        .admin-nav-links a { color: #cbd5e1; text-decoration: none; font-size: 14px; font-weight: 500; }
        .admin-nav-links a:hover { color: white; }
        .btn-logout { background: #ef4444 !important; color: white !important; padding: 7px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; text-decoration: none; }

        /* Page Layout */
        .page-wrap { max-width: 1350px; margin: 35px auto; padding: 0 4%; }
        .alert { padding: 14px 18px; border-radius: 10px; margin-bottom: 22px; font-size: 14px; display: flex; align-items: center; justify-content: space-between; }
        .alert-success { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
        .alert-danger { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }

        .header-bar { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 25px; }
        .header-bar h1 { font-size: 26px; color: #0f172a; }
        .header-bar p { font-size: 14px; color: #64748b; }
        .btn-primary-action { background: #6C63FF; color: white; padding: 11px 22px; border-radius: 10px; border: none; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; font-size: 14px; text-decoration: none; }
        .btn-primary-action:hover { background: #5148d8; }
        .btn-ajax-link { background: #0284c7; color: white; padding: 11px 20px; border-radius: 10px; border: none; font-weight: 600; font-size: 14px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
        .btn-ajax-link:hover { background: #0369a1; }

        /* Filter Panel */
        .filter-panel { background: white; padding: 20px 25px; border-radius: 16px; box-shadow: 0 4px 15px rgba(0,0,0,0.04); border: 1px solid #e2e8f0; margin-bottom: 25px; }
        .filter-form { display: grid; grid-template-columns: 2fr 1fr 1fr auto auto; gap: 12px; align-items: center; }
        @media (max-width: 900px) { .filter-form { grid-template-columns: 1fr 1fr; } }
        @media (max-width: 600px) { .filter-form { grid-template-columns: 1fr; } }
        .filter-input, .filter-select { padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; color: #1e293b; background: white; width: 100%; }
        .btn-filter { background: #2563eb; color: white; border: none; padding: 10px 18px; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 13px; }
        .btn-reset { background: #f1f5f9; color: #475569; padding: 10px 16px; border-radius: 8px; text-decoration: none; font-size: 13px; text-align: center; border: 1px solid #cbd5e1; }

        /* Table Card */
        .table-card { background: white; border-radius: 16px; box-shadow: 0 4px 15px rgba(0,0,0,0.04); border: 1px solid #e2e8f0; overflow: hidden; }
        .table-responsive { overflow-x: auto; }
        table.events-table { width: 100%; border-collapse: collapse; font-size: 13px; text-align: left; }
        table.events-table th { background: #f8fafc; color: #475569; font-weight: 600; padding: 14px 16px; border-bottom: 2px solid #e2e8f0; white-space: nowrap; }
        table.events-table td { padding: 14px 16px; border-bottom: 1px solid #f1f5f9; color: #334155; vertical-align: middle; }
        table.events-table tr:hover { background: #fbfcfe; }

        /* Poster Thumbnail */
        .poster-thumb { width: 50px; height: 50px; border-radius: 8px; object-fit: cover; border: 1px solid #e2e8f0; }
        .poster-placeholder { width: 50px; height: 50px; border-radius: 8px; background: #f1f5f9; display: flex; align-items: center; justify-content: center; font-size: 20px; color: #94a3b8; border: 1px dashed #cbd5e1; }

        /* Badges */
        .badge { padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
        .badge-open { background: #dcfce7; color: #15803d; }
        .badge-closed { background: #fee2e2; color: #b91c1c; }
        .badge-cat { background: #f3e8ff; color: #7e22ce; font-size: 11px; padding: 3px 8px; border-radius: 6px; font-weight: 500; }

        /* Actions */
        .btn-action { padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; border: none; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; }
        .btn-edit { background: #dbeafe; color: #1e40af; }
        .btn-edit:hover { background: #bfdbfe; }
        .btn-toggle { background: #fef3c7; color: #92400e; }
        .btn-delete { background: #fee2e2; color: #b91c1c; }

        /* Modals */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(15,23,42,0.5); backdrop-filter: blur(4px); align-items: center; justify-content: center; }
        .modal.open { display: flex; }
        .modal-box { background: white; border-radius: 18px; width: 95%; max-width: 650px; padding: 30px; box-shadow: 0 20px 50px rgba(0,0,0,0.2); max-height: 90vh; overflow-y: auto; }
        .modal-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 1px solid #e2e8f0; }
        .modal-head h3 { font-size: 20px; color: #0f172a; }
        .modal-close { background: none; border: none; font-size: 24px; color: #64748b; cursor: pointer; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px; }
        .form-full { grid-column: span 2; }
        .form-group label { display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 6px; text-transform: uppercase; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; font-family: inherit; }
        .modal-footer { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; padding-top: 15px; border-top: 1px solid #e2e8f0; }
        .btn-cancel { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; padding: 10px 18px; border-radius: 8px; cursor: pointer; font-size: 13px; }
        .btn-submit { background: #6C63FF; color: white; border: none; padding: 10px 22px; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 13px; }
    </style>
</head>
<body>

    <!-- NAVBAR -->
    <nav class="admin-nav">
        <div>
            <span class="brand-text">StudentHub</span>
            <span class="admin-tag">Administrator</span>
        </div>
        <div class="admin-nav-links">
            <a href="admin_dashboard.php">Dashboard</a>
            <a href="manage_students.php">Students CRUD</a>
            <a href="manage_events.php" style="color:white; font-weight:700;">Events CRUD</a>
            <a href="ajax_events.php" style="color:#38bdf8;">Practical 13 AJAX API ⚡</a>
            <a href="audit_logs.php">Audit Logs</a>
            <a href="logout.php" class="btn-logout">Logout ⏻</a>
        </div>
    </nav>

    <!-- CONTENT -->
    <div class="page-wrap">

        <!-- Flash Messages -->
        <?php if (!empty($flash_success)): ?>
            <div class="alert alert-success">
                <span>✓ <?= e($flash_success) ?></span>
                <span style="cursor:pointer;" onclick="this.parentElement.style.display='none'">✕</span>
            </div>
        <?php endif; ?>

        <?php if (!empty($flash_error)): ?>
            <div class="alert alert-danger">
                <span>⚠ <?= e($flash_error) ?></span>
                <span style="cursor:pointer;" onclick="this.parentElement.style.display='none'">✕</span>
            </div>
        <?php endif; ?>

        <!-- Title & Action Buttons -->
        <div class="header-bar">
            <div>
                <h1>College Event Management</h1>
                <p>Practical 12: Event CRUD with Poster Upload, MIME & Size Validation, and Category Filtering.</p>
            </div>
            <div style="display:flex; gap:12px;">
                <button type="button" class="btn-primary-action" onclick="openCreateModal()">
                    ＋ Add New Event
                </button>
                <a href="ajax_events.php" class="btn-ajax-link">
                    ⚡ AJAX CRUD Mode (Practical 13)
                </a>
            </div>
        </div>

        <!-- SEARCH & FILTER PANEL -->
        <div class="filter-panel">
            <form method="GET" action="manage_events.php" class="filter-form">
                <div>
                    <input type="text" name="search" class="filter-input" placeholder="🔍 Search Event Title, Venue, Description..." value="<?= e($search_query) ?>">
                </div>

                <div>
                    <select name="category" class="filter-select">
                        <option value="">All Categories</option>
                        <option value="Workshop" <?= $filter_cat === 'Workshop' ? 'selected' : '' ?>>Workshop</option>
                        <option value="Technical" <?= $filter_cat === 'Technical' ? 'selected' : '' ?>>Technical</option>
                        <option value="Competition" <?= $filter_cat === 'Competition' ? 'selected' : '' ?>>Competition</option>
                        <option value="Sports" <?= $filter_cat === 'Sports' ? 'selected' : '' ?>>Sports</option>
                        <option value="Seminar" <?= $filter_cat === 'Seminar' ? 'selected' : '' ?>>Seminar</option>
                        <option value="Cultural" <?= $filter_cat === 'Cultural' ? 'selected' : '' ?>>Cultural</option>
                    </select>
                </div>

                <div>
                    <select name="status" class="filter-select">
                        <option value="">All Statuses</option>
                        <option value="open" <?= $filter_status === 'open' ? 'selected' : '' ?>>Open for Registration</option>
                        <option value="closed" <?= $filter_status === 'closed' ? 'selected' : '' ?>>Closed</option>
                    </select>
                </div>

                <div>
                    <button type="submit" class="btn-filter">Filter</button>
                </div>

                <div>
                    <a href="manage_events.php" class="btn-reset">Reset</a>
                </div>
            </form>
        </div>

        <!-- EVENTS TABLE -->
        <div class="table-card">
            <div class="table-responsive">
                <table class="events-table">
                    <thead>
                        <tr>
                            <th style="width:40px;">Sr.</th>
                            <th>Poster</th>
                            <th>Event Title</th>
                            <th>Category</th>
                            <th>Event Date</th>
                            <th>Venue</th>
                            <th>Status</th>
                            <th style="text-align:center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($events)): ?>
                            <tr>
                                <td colspan="8" style="text-align:center; padding:35px; color:#64748b;">
                                    No events found matching your search and filter criteria.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $evt_sr = 0; foreach ($events as $evt): $evt_sr++; ?>
                                <tr>
                                    <td style="color:#64748b; font-weight:600;"><?= $evt_sr ?></td>
                                    <td>
                                        <?php if (!empty($evt['poster']) && file_exists($upload_dir . $evt['poster'])): ?>
                                            <a href="../uploads/posters/<?= e($evt['poster']) ?>" target="_blank">
                                                <img src="../uploads/posters/<?= e($evt['poster']) ?>" class="poster-thumb" alt="Poster">
                                            </a>
                                        <?php else: ?>
                                            <div class="poster-placeholder" title="No poster uploaded">🖼</div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong style="color:#0f172a; font-size:14px;"><?= e($evt['event_name']) ?></strong>
                                        <div style="font-size:11px; color:#64748b; margin-top:2px;">
                                            <?= e(substr($evt['description'] ?? '', 0, 70)) . (strlen($evt['description'] ?? '') > 70 ? '...' : '') ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge-cat"><?= e($evt['category']) ?></span>
                                    </td>
                                    <td>
                                        <strong><?= date('d M Y', strtotime($evt['event_date'])) ?></strong>
                                    </td>
                                    <td><?= e($evt['venue']) ?></td>
                                    <td>
                                        <span class="badge <?= $evt['status'] === 'open' ? 'badge-open' : 'badge-closed' ?>">
                                            <?= ucfirst(e($evt['status'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="display:flex; gap:8px; justify-content:center; align-items:center;">
                                            <button type="button" class="btn-action btn-edit" onclick='openEditModal(<?= json_encode($evt, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                                                ✏ Edit
                                            </button>

                                            <!-- Toggle Open/Closed -->
                                            <form method="POST" action="manage_events.php" style="display:inline;">
                                                <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="event_id" value="<?= e($evt['event_id']) ?>">
                                                <input type="hidden" name="new_status" value="<?= $evt['status'] === 'open' ? 'closed' : 'open' ?>">
                                                <button type="submit" class="btn-action btn-toggle" title="Click to toggle Open/Closed">
                                                    <?= $evt['status'] === 'open' ? '⏸ Close' : '▶ Open' ?>
                                                </button>
                                            </form>

                                            <!-- Delete -->
                                            <form method="POST" action="manage_events.php" style="display:inline;" onsubmit="return confirm('Delete event \'<?= e($evt['event_name']) ?>\' and its poster image?');">
                                                <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="event_id" value="<?= e($evt['event_id']) ?>">
                                                <button type="submit" class="btn-action btn-delete">🗑 Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- ================= CREATE EVENT MODAL ================= -->
    <div id="createModal" class="modal">
        <div class="modal-box">
            <div class="modal-head">
                <h3>Add New College Event</h3>
                <button type="button" class="modal-close" onclick="closeModal('createModal')">×</button>
            </div>
            <form method="POST" action="manage_events.php" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                <input type="hidden" name="action" value="create">

                <div class="form-grid">
                    <div class="form-group form-full">
                        <label>Event Name / Title</label>
                        <input type="text" name="event_name" required placeholder="e.g. AI & Machine Learning Symposium">
                    </div>

                    <div class="form-group">
                        <label>Category</label>
                        <select name="category" required>
                            <option value="Workshop">Workshop</option>
                            <option value="Technical">Technical</option>
                            <option value="Competition">Competition</option>
                            <option value="Sports">Sports</option>
                            <option value="Seminar">Seminar</option>
                            <option value="Cultural">Cultural</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Event Date</label>
                        <input type="date" name="event_date" required>
                    </div>

                    <div class="form-group">
                        <label>Venue / Location</label>
                        <input type="text" name="venue" required placeholder="e.g. CSPIT Seminar Hall">
                    </div>

                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="open">Open (Accepting Registrations)</option>
                            <option value="closed">Closed</option>
                        </select>
                    </div>

                    <div class="form-group form-full">
                        <label>Event Poster Image (Max 2MB - JPG, PNG, WEBP)</label>
                        <input type="file" name="poster" accept=".jpg,.jpeg,.png,.webp">
                        <small style="color:#64748b; font-size:11px;">Validated for file type and size. Safe renaming applied.</small>
                    </div>

                    <div class="form-group form-full">
                        <label>Event Description</label>
                        <textarea name="description" rows="3" placeholder="Provide schedule, speakers, and requirements..."></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeModal('createModal')">Cancel</button>
                    <button type="submit" class="btn-submit">Publish Event</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ================= EDIT EVENT MODAL ================= -->
    <div id="editModal" class="modal">
        <div class="modal-box">
            <div class="modal-head">
                <h3>Edit College Event</h3>
                <button type="button" class="modal-close" onclick="closeModal('editModal')">×</button>
            </div>
            <form method="POST" action="manage_events.php" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="event_id" id="edit_event_id">

                <div class="form-grid">
                    <div class="form-group form-full">
                        <label>Event Name / Title</label>
                        <input type="text" name="event_name" id="edit_event_name" required>
                    </div>

                    <div class="form-group">
                        <label>Category</label>
                        <select name="category" id="edit_category" required>
                            <option value="Workshop">Workshop</option>
                            <option value="Technical">Technical</option>
                            <option value="Competition">Competition</option>
                            <option value="Sports">Sports</option>
                            <option value="Seminar">Seminar</option>
                            <option value="Cultural">Cultural</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Event Date</label>
                        <input type="date" name="event_date" id="edit_event_date" required>
                    </div>

                    <div class="form-group">
                        <label>Venue / Location</label>
                        <input type="text" name="venue" id="edit_venue" required>
                    </div>

                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" id="edit_status">
                            <option value="open">Open (Accepting Registrations)</option>
                            <option value="closed">Closed</option>
                        </select>
                    </div>

                    <div class="form-group form-full">
                        <label>Update Poster (leave blank to keep current)</label>
                        <input type="file" name="poster" accept=".jpg,.jpeg,.png,.webp">
                    </div>

                    <div class="form-group form-full">
                        <label>Event Description</label>
                        <textarea name="description" id="edit_description" rows="3"></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeModal('editModal')">Cancel</button>
                    <button type="submit" class="btn-submit">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openCreateModal() { document.getElementById('createModal').classList.add('open'); }
        function openEditModal(evt) {
            document.getElementById('edit_event_id').value = evt.event_id;
            document.getElementById('edit_event_name').value = evt.event_name;
            document.getElementById('edit_category').value = evt.category || 'Workshop';
            document.getElementById('edit_event_date').value = evt.event_date;
            document.getElementById('edit_venue').value = evt.venue || '';
            document.getElementById('edit_status').value = evt.status || 'open';
            document.getElementById('edit_description').value = evt.description || '';
            document.getElementById('editModal').classList.add('open');
        }
        function closeModal(id) { document.getElementById(id).classList.remove('open'); }
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') { closeModal('createModal'); closeModal('editModal'); }
        });
    </script>
</body>
</html>
