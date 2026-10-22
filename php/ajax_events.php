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
    <title>Practical 13 — AJAX/Fetch API Event CRUD</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Poppins',-apple-system,sans-serif; }
        body { background: #f0f4f9; color: #1e293b; }

        /* Navbar */
        nav.admin-nav {
            background: #0f172a; color: white; padding: 16px 8%;
            display: flex; justify-content: space-between; align-items: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15); position: sticky; top: 0; z-index: 100;
        }
        .brand-text { font-size: 24px; font-weight: 700; color: #38bdf8; }
        .admin-tag { background: #0284c7; color: white; font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 12px; text-transform: uppercase; margin-left: 8px; }
        .admin-nav-links { display: flex; align-items: center; gap: 20px; }
        .admin-nav-links a { color: #cbd5e1; text-decoration: none; font-size: 14px; font-weight: 500; }
        .admin-nav-links a:hover { color: white; }
        .btn-logout { background: #ef4444 !important; color: white !important; padding: 7px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; text-decoration: none; }

        .page-wrap { max-width: 1350px; margin: 35px auto; padding: 0 4%; }

        .banner-ajax {
            background: linear-gradient(135deg, #0369a1, #0284c7);
            color: white; padding: 25px 30px; border-radius: 18px; margin-bottom: 25px;
            display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;
            box-shadow: 0 10px 25px rgba(2,132,199,0.25);
        }
        .banner-ajax h1 { font-size: 24px; margin-bottom: 5px; }
        .banner-ajax p { font-size: 13px; opacity: 0.9; }

        /* Toast & Alerts */
        .toast {
            display: none; padding: 14px 20px; border-radius: 10px; margin-bottom: 20px;
            font-size: 14px; align-items: center; justify-content: space-between;
            animation: fadeIn 0.3s ease;
        }
        .toast.success { display: flex; background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
        .toast.error { display: flex; background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }

        /* Filter & Controls */
        .control-panel {
            background: white; padding: 20px; border-radius: 16px; border: 1px solid #e2e8f0;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03); margin-bottom: 25px;
            display: flex; gap: 15px; align-items: center; flex-wrap: wrap;
        }
        .search-box { flex: 2; min-width: 240px; position: relative; }
        .search-input {
            width: 100%; padding: 11px 16px; border: 1px solid #cbd5e1; border-radius: 10px;
            font-size: 13px; outline: none;
        }
        .search-input:focus { border-color: #0284c7; box-shadow: 0 0 0 3px rgba(2,132,199,0.15); }
        .select-filter {
            flex: 1; min-width: 160px; padding: 11px 14px; border: 1px solid #cbd5e1; border-radius: 10px;
            font-size: 13px; background: white; outline: none;
        }
        .btn-new-evt {
            background: #0284c7; color: white; border: none; padding: 11px 22px; border-radius: 10px;
            font-size: 13px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 8px;
        }
        .btn-new-evt:hover { background: #0369a1; }

        /* Split Layout: Table + Live Network Log */
        .layout-grid {
            display: grid; grid-template-columns: 2.2fr 1fr; gap: 25px;
        }
        @media (max-width: 1024px) {
            .layout-grid { grid-template-columns: 1fr; }
        }

        /* Table */
        .table-box {
            background: white; border-radius: 16px; border: 1px solid #e2e8f0;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03); overflow: hidden;
        }
        table.ajax-table { width: 100%; border-collapse: collapse; font-size: 13px; text-align: left; }
        table.ajax-table th { background: #f8fafc; color: #475569; padding: 14px 16px; border-bottom: 2px solid #e2e8f0; }
        table.ajax-table td { padding: 14px 16px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        table.ajax-table tr:hover { background: #f8fafc; }

        .thumb-img { width: 44px; height: 44px; border-radius: 8px; object-fit: cover; }
        .thumb-placeholder { width: 44px; height: 44px; border-radius: 8px; background: #f1f5f9; display: flex; align-items: center; justify-content: center; font-size: 18px; color: #94a3b8; }
        .badge { padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; }
        .badge-open { background: #dcfce7; color: #15803d; }
        .badge-closed { background: #fee2e2; color: #b91c1c; }
        .btn-act { padding: 5px 10px; border-radius: 6px; font-size: 11px; font-weight: 600; border: none; cursor: pointer; }
        .btn-act-edit { background: #e0f2fe; color: #0369a1; margin-right: 6px; }
        .btn-act-del { background: #fee2e2; color: #b91c1c; }

        /* API Activity Console */
        .api-console {
            background: #0f172a; color: #e2e8f0; border-radius: 16px; padding: 22px;
            box-shadow: 0 10px 25px rgba(15,23,42,0.15); display: flex; flex-direction: column; height: 550px;
        }
        .console-head {
            display: flex; justify-content: space-between; align-items: center; padding-bottom: 12px;
            border-bottom: 1px solid #334155; margin-bottom: 15px;
        }
        .console-title { font-size: 13px; font-weight: 700; color: #38bdf8; display: flex; align-items: center; gap: 8px; }
        .console-body {
            flex: 1; overflow-y: auto; font-family: monospace; font-size: 11px; line-height: 1.6;
        }
        .log-entry { margin-bottom: 10px; padding: 8px 10px; border-radius: 6px; background: #1e293b; border-left: 3px solid #38bdf8; }
        .log-method { font-weight: 700; color: #a78bfa; }
        .log-status-200 { color: #4ade80; }
        .log-status-201 { color: #38bdf8; }
        .log-status-400, .log-status-401 { color: #f87171; }

        /* Spinner */
        .spinner {
            display: inline-block; width: 18px; height: 18px; border: 2px solid rgba(2,132,199,0.3);
            border-radius: 50%; border-top-color: #0284c7; animation: spin 0.7s linear infinite; vertical-align: middle;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* Modal */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(15,23,42,0.5); backdrop-filter: blur(4px); align-items: center; justify-content: center; }
        .modal.open { display: flex; }
        .modal-box { background: white; border-radius: 18px; width: 95%; max-width: 600px; padding: 30px; box-shadow: 0 20px 50px rgba(0,0,0,0.2); max-height: 90vh; overflow-y: auto; }
        .modal-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 1px solid #e2e8f0; }
        .form-group { margin-bottom: 14px; }
        .form-group label { display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 4px; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; font-family: inherit; }
        .modal-foot { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }
        .btn-cancel { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; padding: 9px 18px; border-radius: 8px; cursor: pointer; }
        .btn-save { background: #0284c7; color: white; border: none; padding: 9px 22px; border-radius: 8px; font-weight: 600; cursor: pointer; }
    </style>
</head>
<body>

    <nav class="admin-nav">
        <div>
            <span class="brand-text">StudentHub</span>
            <span class="admin-tag">Practical 13 API</span>
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
                <h1>Practical 13: Fetch API & JSON Endpoint Integration</h1>
                <p>Asynchronous event management without full-page reloads. Real-time REST endpoints consumed via JavaScript Fetch.</p>
            </div>
            <div>
                <a href="manage_events.php" style="color:white; font-size:13px; text-decoration:underline;">
                    ← Standard Form Mode (Practical 12)
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
    <script>
        const API_URL = 'api/events.php';
        let debounceTimer = null;

        function logNetwork(method, url, status, detail) {
            const body = document.getElementById('consoleBody');
            const entry = document.createElement('div');
            entry.className = 'log-entry';
            const time = new Date().toLocaleTimeString();
            const statusClass = 'log-status-' + status;

            entry.innerHTML = `
                <div><span class="log-method">[${method}]</span> <span class="${statusClass}">HTTP ${status}</span> <span style="color:#64748b; font-size:10px;">${time}</span></div>
                <div style="color:#94a3b8; word-break:break-all;">${url}</div>
                ${detail ? `<div style="color:#cbd5e1; margin-top:3px;">${detail}</div>` : ''}
            `;
            body.insertBefore(entry, body.firstChild);
        }

        function clearLogs() {
            document.getElementById('consoleBody').innerHTML = '';
        }

        function showToast(msg, isSuccess = true) {
            const toast = document.getElementById('toast');
            toast.className = 'toast ' + (isSuccess ? 'success' : 'error');
            document.getElementById('toastMsg').textContent = (isSuccess ? '✓ ' : '⚠ ') + msg;
            setTimeout(() => { toast.style.display = 'none'; }, 4000);
        }
        function hideToast() { document.getElementById('toast').style.display = 'none'; }

        // Fetch & render events
        async function loadEvents() {
            const search = document.getElementById('searchInput').value.trim();
            const category = document.getElementById('categoryFilter').value;
            const status = document.getElementById('statusFilter').value;

            const params = new URLSearchParams();
            if (search) params.append('search', search);
            if (category && category !== 'all') params.append('category', category);
            if (status) params.append('status', status);

            const url = API_URL + '?' + params.toString();
            try {
                const res = await fetch(url);
                const data = await res.json();
                logNetwork('GET', url, res.status, `Returned ${data.data ? data.data.length : 0} event(s)`);

                const tbody = document.getElementById('eventsTableBody');
                if (!data.success || !data.data || data.data.length === 0) {
                    tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; padding:30px; color:#64748b;">No events found.</td></tr>`;
                    return;
                }

                tbody.innerHTML = data.data.map((evt, idx) => `
                    <tr id="event-row-${evt.event_id}">
                        <td style="color:#64748b; font-weight:600;">${idx + 1}</td>
                        <td>
                            ${evt.poster_url 
                                ? `<a href="${evt.poster_url}" target="_blank"><img src="${evt.poster_url}" class="thumb-img" alt="Poster"></a>`
                                : `<div class="thumb-placeholder">🖼</div>`
                            }
                        </td>
                        <td>
                            <strong style="color:#0f172a;">${escapeHtml(evt.event_name)}</strong>
                            <div style="font-size:11px; color:#64748b;">${escapeHtml(evt.venue || '')}</div>
                        </td>
                        <td><span style="background:#f3e8ff; color:#7e22ce; padding:3px 8px; border-radius:6px; font-size:11px;">${escapeHtml(evt.category)}</span></td>
                        <td><strong>${escapeHtml(evt.event_date)}</strong></td>
                        <td>
                            <span class="badge ${evt.status === 'open' ? 'badge-open' : 'badge-closed'}">
                                ${escapeHtml(evt.status.toUpperCase())}
                            </span>
                        </td>
                        <td style="text-align:center;">
                            <button type="button" class="btn-act btn-act-edit" onclick="editEvent(${evt.event_id})">Edit</button>
                            <button type="button" class="btn-act btn-act-del" onclick="deleteEvent(${evt.event_id}, '${escapeHtml(evt.event_name)}')">Delete</button>
                        </td>
                    </tr>
                `).join('');
            } catch (err) {
                logNetwork('GET', url, 500, err.message);
                showToast("Failed to connect to JSON API: " + err.message, false);
            }
        }

        // Live Debounced Search (Intermediate Extension)
        document.getElementById('searchInput').addEventListener('input', () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(loadEvents, 300);
        });
        document.getElementById('categoryFilter').addEventListener('change', loadEvents);
        document.getElementById('statusFilter').addEventListener('change', loadEvents);

        // Modal Handlers
        function openEventModal() {
            document.getElementById('ajaxEventForm').reset();
            document.getElementById('modal_event_id').value = '';
            document.getElementById('modalTitle').textContent = 'Add New Event (AJAX)';
            document.getElementById('eventModal').classList.add('open');
        }

        async function editEvent(eventId) {
            try {
                const res = await fetch(API_URL + '?id=' + eventId);
                const data = await res.json();
                if (data.success && data.data) {
                    const evt = data.data;
                    document.getElementById('modal_event_id').value = evt.event_id;
                    document.getElementById('modal_name').value = evt.event_name;
                    document.getElementById('modal_category').value = evt.category || 'Workshop';
                    document.getElementById('modal_date').value = evt.event_date;
                    document.getElementById('modal_venue').value = evt.venue || '';
                    document.getElementById('modal_status').value = evt.status || 'open';
                    document.getElementById('modal_desc').value = evt.description || '';
                    document.getElementById('modalTitle').textContent = `Edit Event #${evt.event_id} (AJAX)`;
                    document.getElementById('eventModal').classList.add('open');
                }
            } catch (err) {
                showToast("Failed to fetch event details: " + err.message, false);
            }
        }

        function closeEventModal() { document.getElementById('eventModal').classList.remove('open'); }

        // Form Submission (Add or Update via Fetch)
        document.getElementById('ajaxEventForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = document.getElementById('btnSubmitModal');
            btn.disabled = true;
            btn.textContent = 'Saving...';

            const formData = new FormData(this);
            const isUpdate = !!document.getElementById('modal_event_id').value;
            const targetUrl = isUpdate ? API_URL + '?action=update' : API_URL;

            try {
                const res = await fetch(targetUrl, {
                    method: 'POST',
                    body: formData
                });
                const result = await res.json();
                logNetwork(isUpdate ? 'PUT' : 'POST', targetUrl, res.status, result.message);

                if (result.success) {
                    showToast(result.message, true);
                    closeEventModal();
                    loadEvents();
                } else {
                    const errDetail = result.errors ? result.errors.join(' ') : result.message;
                    showToast(errDetail, false);
                }
            } catch (err) {
                logNetwork('POST', targetUrl, 500, err.message);
                showToast("Network error: " + err.message, false);
            } finally {
                btn.disabled = false;
                btn.textContent = 'Save Event';
            }
        });

        // Delete Event (AJAX)
        async function deleteEvent(id, title) {
            if (!confirm(`Delete event '${title}' via Fetch API?`)) return;

            const url = API_URL + '?action=delete';
            try {
                const formData = new FormData();
                formData.append('event_id', id);

                const res = await fetch(url, { method: 'POST', body: formData });
                const result = await res.json();
                logNetwork('DELETE', url, res.status, result.message);

                if (result.success) {
                    showToast(result.message, true);
                    const row = document.getElementById('event-row-' + id);
                    if (row) row.remove();
                } else {
                    showToast(result.message, false);
                }
            } catch (err) {
                logNetwork('DELETE', url, 500, err.message);
                showToast("Error: " + err.message, false);
            }
        }

        function escapeHtml(str) {
            return (str || '').replace(/[&<>'"]/g, tag => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;'
            }[tag] || tag));
        }

        // Initialize on load
        loadEvents();
    </script>
</body>
</html>
