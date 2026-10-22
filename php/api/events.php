<?php
/**
 * StudentHub — Practical 13: PHP JSON REST API for Events
 * Returns structured JSON with proper HTTP response status codes.
 * Supports GET (list/search), POST (create), PUT/POST (update), DELETE/POST (delete).
 */

header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/auth.php';

start_secure_session();

function api_response($success, $message, $data = null, $status_code = 200, $errors = []) {
    http_response_code($status_code);
    $payload = [
        'success'   => $success,
        'message'   => $message,
        'timestamp' => date('c')
    ];
    if ($data !== null) $payload['data'] = $data;
    if (!empty($errors)) $payload['errors'] = $errors;

    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// Support _method override for REST clients
if ($method === 'POST' && isset($_POST['_method'])) {
    $method = strtoupper($_POST['_method']);
} elseif ($method === 'POST' && isset($_GET['action'])) {
    if ($_GET['action'] === 'update') $method = 'PUT';
    if ($_GET['action'] === 'delete') $method = 'DELETE';
}

$upload_dir = dirname(dirname(__DIR__)) . '/uploads/posters/';

// =========================================================================
// GET: LIST OR SINGLE EVENT
// =========================================================================
if ($method === 'GET') {
    // Single event by ID
    if (isset($_GET['id'])) {
        $event_id = (int)$_GET['id'];
        $stmt = $conn->prepare("SELECT event_id, event_name, event_date, venue, description, poster, category, status, created_at FROM events WHERE event_id = ?");
        $stmt->bind_param("i", $event_id);
        $stmt->execute();
        $evt = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($evt) {
            $evt['poster_url'] = !empty($evt['poster']) ? '../uploads/posters/' . $evt['poster'] : null;
            api_response(true, "Event fetched successfully.", $evt, 200);
        } else {
            api_response(false, "Event not found with ID: $event_id", null, 404);
        }
    }

    // List events with optional search & category filter
    $search   = trim($_GET['search'] ?? '');
    $category = trim($_GET['category'] ?? '');
    $status   = trim($_GET['status'] ?? '');

    $where = ["1=1"];
    $types = "";
    $vals  = [];

    if ($search !== '') {
        $where[] = "(event_name LIKE ? OR venue LIKE ? OR description LIKE ?)";
        $like = "%$search%";
        $types .= "sss";
        $vals[] = $like; $vals[] = $like; $vals[] = $like;
    }

    if ($category !== '' && $category !== 'all') {
        $where[] = "category = ?";
        $types .= "s";
        $vals[] = $category;
    }

    if ($status !== '' && in_array($status, ['open', 'closed'], true)) {
        $where[] = "status = ?";
        $types .= "s";
        $vals[] = $status;
    }

    $sql = "SELECT event_id, event_name, event_date, venue, description, poster, category, status, created_at 
            FROM events 
            WHERE " . implode(" AND ", $where) . " 
            ORDER BY event_date ASC, event_id ASC";

    $stmt = $conn->prepare($sql);
    if ($types !== "") {
        $stmt->bind_param($types, ...$vals);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $events = [];
    while ($row = $res->fetch_assoc()) {
        $row['poster_url'] = !empty($row['poster']) ? '../uploads/posters/' . $row['poster'] : null;
        $events[] = $row;
    }
    $stmt->close();

    api_response(true, "Events fetched successfully.", $events, 200);
}

// For mutating actions (POST, PUT, DELETE), enforce administrator authentication
if (!is_logged_in() || ($_SESSION['role'] ?? '') !== 'admin') {
    api_response(false, "Unauthorized: Administrator access required.", null, 401);
}

// =========================================================================
// POST: CREATE EVENT
// =========================================================================
if ($method === 'POST') {
    $event_name  = trim($_POST['event_name'] ?? '');
    $event_date  = trim($_POST['event_date'] ?? '');
    $venue       = trim($_POST['venue'] ?? '');
    $category    = trim($_POST['category'] ?? 'Workshop');
    $status      = trim($_POST['status'] ?? 'open');
    $description = trim($_POST['description'] ?? '');

    $errors = [];
    if ($event_name === '') $errors[] = "Event title is required.";
    if ($event_date === '') $errors[] = "Event date is required.";
    if ($venue === '') $errors[] = "Venue location is required.";
    if (!in_array($status, ['open', 'closed'], true)) $status = 'open';

    // Poster upload validation
    $poster_filename = null;
    if (isset($_FILES['poster']) && $_FILES['poster']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['poster'];
        if ($file['size'] > 2 * 1024 * 1024) {
            $errors[] = "Poster size cannot exceed 2MB.";
        }
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $errors[] = "Allowed image types: JPG, PNG, WEBP.";
        }
        if (empty($errors)) {
            $safe_name = "event_" . uniqid() . "_" . time() . "." . $ext;
            if (move_uploaded_file($file['tmp_name'], $upload_dir . $safe_name)) {
                $poster_filename = $safe_name;
            } else {
                $errors[] = "Failed to store uploaded poster.";
            }
        }
    }

    if (!empty($errors)) {
        api_response(false, "Validation failed.", null, 400, $errors);
    }

    $stmt = $conn->prepare("INSERT INTO events (event_name, event_date, venue, description, poster, category, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssss", $event_name, $event_date, $venue, $description, $poster_filename, $category, $status);

    if ($stmt->execute()) {
        $new_id = $conn->insert_id;
        $stmt->close();
        log_audit_action($conn, 'API_EVENT_CREATE', "Created event #$new_id ($event_name) via JSON API.");
        api_response(true, "Event created successfully via API.", ['event_id' => $new_id], 201);
    } else {
        $err = $stmt->error;
        $stmt->close();
        api_response(false, "Database insert failed: $err", null, 500);
    }
}

// =========================================================================
// PUT: UPDATE EVENT
// =========================================================================
if ($method === 'PUT') {
    // If sent as raw php://input (JSON) or multipart/POST
    $input_data = [];
    if (empty($_POST)) {
        $raw = file_get_contents('php://input');
        $input_data = json_decode($raw, true) ?? [];
    } else {
        $input_data = $_POST;
    }

    $event_id    = (int)($input_data['event_id'] ?? ($_GET['id'] ?? 0));
    $event_name  = trim($input_data['event_name'] ?? '');
    $event_date  = trim($input_data['event_date'] ?? '');
    $venue       = trim($input_data['venue'] ?? '');
    $category    = trim($input_data['category'] ?? 'Workshop');
    $status      = trim($input_data['status'] ?? 'open');
    $description = trim($input_data['description'] ?? '');

    if ($event_id <= 0) {
        api_response(false, "Event ID is required for update.", null, 400);
    }

    $curr = $conn->prepare("SELECT poster FROM events WHERE event_id = ?");
    $curr->bind_param("i", $event_id);
    $curr->execute();
    $row = $curr->get_result()->fetch_assoc();
    $curr->close();

    if (!$row) {
        api_response(false, "Event not found with ID: $event_id", null, 404);
    }

    $final_poster = $row['poster'];
    if (isset($_FILES['poster']) && $_FILES['poster']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['poster'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($file['size'] <= 2 * 1024 * 1024 && in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $safe_name = "event_" . uniqid() . "_" . time() . "." . $ext;
            if (move_uploaded_file($file['tmp_name'], $upload_dir . $safe_name)) {
                if ($final_poster && file_exists($upload_dir . $final_poster)) {
                    unlink($upload_dir . $final_poster);
                }
                $final_poster = $safe_name;
            }
        }
    }

    $stmt = $conn->prepare("UPDATE events SET event_name = ?, event_date = ?, venue = ?, description = ?, poster = ?, category = ?, status = ? WHERE event_id = ?");
    $stmt->bind_param("sssssssi", $event_name, $event_date, $venue, $description, $final_poster, $category, $status, $event_id);

    if ($stmt->execute()) {
        $stmt->close();
        log_audit_action($conn, 'API_EVENT_UPDATE', "Updated event #$event_id ($event_name) via JSON API.");
        api_response(true, "Event #$event_id updated successfully via API.", ['event_id' => $event_id], 200);
    } else {
        $err = $stmt->error;
        $stmt->close();
        api_response(false, "Database update failed: $err", null, 500);
    }
}

// =========================================================================
// DELETE: DELETE EVENT
// =========================================================================
if ($method === 'DELETE') {
    $input_data = [];
    if (empty($_POST)) {
        $raw = file_get_contents('php://input');
        $input_data = json_decode($raw, true) ?? [];
    } else {
        $input_data = $_POST;
    }

    $event_id = (int)($input_data['event_id'] ?? ($_GET['id'] ?? 0));
    if ($event_id <= 0) {
        api_response(false, "Event ID is required for deletion.", null, 400);
    }

    $stmt = $conn->prepare("SELECT event_name, poster FROM events WHERE event_id = ?");
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $evt = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$evt) {
        api_response(false, "Event not found with ID: $event_id", null, 404);
    }

    if (!empty($evt['poster']) && file_exists($upload_dir . $evt['poster'])) {
        unlink($upload_dir . $evt['poster']);
    }

    $del = $conn->prepare("DELETE FROM events WHERE event_id = ?");
    $del->bind_param("i", $event_id);
    if ($del->execute()) {
        $del->close();
        log_audit_action($conn, 'API_EVENT_DELETE', "Deleted event #$event_id ({$evt['event_name']}) via JSON API.");
        api_response(true, "Event #$event_id deleted successfully via API.", null, 200);
    } else {
        $err = $del->error;
        $del->close();
        api_response(false, "Database delete failed: $err", null, 500);
    }
}

api_response(false, "Method not allowed: $method", null, 405);
