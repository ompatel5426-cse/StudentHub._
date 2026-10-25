<?php
/**
 * StudentHub — Practical 13: AJAX/Fetch-Based Event CRUD Interface
 * Interacts with PHP JSON REST API asynchronously without page reloads.
 * Features live debounced search, loading states, modal forms, and live API logs.
 */

require_once __DIR__ . '/auth.php';
start_secure_session();
require_admin();

$csrf_token = get_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title> AJAX/Fetch API Event CRUD</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/ajax_events.css">
</head>
<body>

    <nav class="admin-nav">
        <div>
            <span class="brand-text">StudentHub</span>
            <span class="admin-tag"> API</span>
        </div>
        <div class="admin-nav-links">
            <a href="admin_dashboard.php">Dashboard</a>
            <a href="manage_students.php">Students CRUD</a>
            <a href="manage_events.php">Events CRUD</a>
            <a href="ajax_events.php" style="color:#38bdf8; font-weight:700;">AJAX Fetch CRUD ⚡</a>
            <a href="audit_logs.php">Audit Logs</a>
            <a href="logout.php" class="btn-logout">Logout ⏻</a>
        </div>
    </nav>

    <div class="page-wrap">

        <div class="banner-ajax">
            <div>
                <h1> Fetch API & JSON Endpoint Integration</h1>
                <p>Asynchronous event management without full-page reloads. Real-time REST endpoints consumed via JavaScript Fetch.</p>
            </div>
            <div>
                <a href="manage_events.php" style="color:white; font-size:13px; text-decoration:underline;">
                    ← Standard Form Mode
                </a>
            </div>
        </div>

        <div id="toast" class="toast">
            <span id="toastMsg"></span>
            <span style="cursor:pointer;" onclick="hideToast()">✕</span>
        </div>

        <div class="control-panel">
            <div class="search-box">
                <input type="text" id="searchInput" class="search-input" placeholder="🔍 Live search with debounce (name, venue, description)...">
            </div>

            <select id="categoryFilter" class="select-filter">
                <option value="all">All Categories</option>
                <option value="Workshop">Workshop</option>
                <option value="Technical">Technical</option>
                <option value="Competition">Competition</option>
                <option value="Sports">Sports</option>
                <option value="Seminar">Seminar</option>
                <option value="Cultural">Cultural</option>
            </select>

            <select id="statusFilter" class="select-filter">
                <option value="">All Statuses</option>
                <option value="open">Open</option>
                <option value="closed">Closed</option>
            </select>

            <button type="button" class="btn-new-evt" onclick="openEventModal()">
                ＋ Add Event (AJAX)
            </button>
        </div>

        <div class="layout-grid">
            <!-- Table View -->
            <div class="table-box">
                <table class="ajax-table">
                    <thead>
                        <tr>
                            <th style="width:40px;">#</th>
                            <th>Poster</th>
                            <th>Title & Details</th>
                            <th>Category</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th style="text-align:center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="eventsTableBody">
                        <tr>
                            <td colspan="7" style="text-align:center; padding:30px; color:#64748b;">
                                <div class="spinner"></div> Loading events from JSON API...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Real-Time Network Log Console -->
            <div class="api-console">
                <div class="console-head">
                    <span class="console-title">⚡ Live Fetch Network Log</span>
                    <button type="button" onclick="clearLogs()" style="background:none; border:none; color:#94a3b8; font-size:11px; cursor:pointer;">Clear</button>
                </div>
                <div id="consoleBody" class="console-body">
                    <div style="color:#64748b;">Waiting for network operations...</div>
                </div>
            </div>
        </div>

    </div>

    <!-- AJAX Modal Form -->
    <div id="eventModal" class="modal">
        <div class="modal-box">
            <div class="modal-head">
                <h3 id="modalTitle">Add New Event (AJAX)</h3>
                <button type="button" onclick="closeEventModal()" style="background:none; border:none; font-size:22px; cursor:pointer;">×</button>
            </div>
            <form id="ajaxEventForm">
                <input type="hidden" id="modal_event_id" name="event_id" value="">
                <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">

                <div class="form-group">
                    <label>Event Name *</label>
                    <input type="text" id="modal_name" name="event_name" required>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div class="form-group">
                        <label>Category *</label>
                        <select id="modal_category" name="category" required>
                            <option value="Workshop">Workshop</option>
                            <option value="Technical">Technical</option>
                            <option value="Competition">Competition</option>
                            <option value="Sports">Sports</option>
                            <option value="Seminar">Seminar</option>
                            <option value="Cultural">Cultural</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Date *</label>
                        <input type="date" id="modal_date" name="event_date" required>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div class="form-group">
                        <label>Venue *</label>
                        <input type="text" id="modal_venue" name="venue" required>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select id="modal_status" name="status">
                            <option value="open">Open</option>
                            <option value="closed">Closed</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Poster Image (Max 2MB - JPG/PNG/WEBP)</label>
                    <input type="file" id="modal_poster" name="poster" accept=".jpg,.jpeg,.png,.webp">
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea id="modal_desc" name="description" rows="3"></textarea>
                </div>

                <div class="modal-foot">
                    <button type="button" class="btn-cancel" onclick="closeEventModal()">Cancel</button>
                    <button type="submit" id="btnSubmitModal" class="btn-save">Save Event</button>
                </div>
            </form>
        </div>
    </div>

    <!-- JAVASCRIPT FETCH LOGIC -->
    <script src="../js/ajax_events.js"></script>
</body>
</html>
