/**
 * StudentHub — Asynchronous Event Management Scripts (Practical 13)
 * Fetches, renders, adds, updates, and deletes events dynamically with live console logging.
 */

const API_URL = 'api/events.php';
let debounceTimer = null;

function logNetwork(method, url, status, detail) {
    const body = document.getElementById('consoleBody');
    if (!body) return;
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
    const body = document.getElementById('consoleBody');
    if (body) body.innerHTML = '';
}

function showToast(msg, isSuccess = true) {
    const toast = document.getElementById('toast');
    if (!toast) return;
    toast.className = 'toast ' + (isSuccess ? 'success' : 'error');
    document.getElementById('toastMsg').textContent = (isSuccess ? '✓ ' : '⚠ ') + msg;
    setTimeout(() => { toast.style.display = 'none'; }, 4000);
}

function hideToast() {
    const toast = document.getElementById('toast');
    if (toast) toast.style.display = 'none';
}

// Fetch & render events
async function loadEvents() {
    const search = document.getElementById('searchInput') ? document.getElementById('searchInput').value.trim() : '';
    const category = document.getElementById('categoryFilter') ? document.getElementById('categoryFilter').value : '';
    const status = document.getElementById('statusFilter') ? document.getElementById('statusFilter').value : '';

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
        if (!tbody) return;

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

// Live Debounced Search
var searchInput = document.getElementById('searchInput');
if (searchInput) {
    searchInput.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(loadEvents, 300);
    });
}

var categoryFilter = document.getElementById('categoryFilter');
if (categoryFilter) {
    categoryFilter.addEventListener('change', loadEvents);
}

var statusFilter = document.getElementById('statusFilter');
if (statusFilter) {
    statusFilter.addEventListener('change', loadEvents);
}

// Modal Handlers
function openEventModal() {
    var form = document.getElementById('ajaxEventForm');
    if (form) form.reset();
    document.getElementById('modal_event_id').value = '';
    document.getElementById('modalTitle').textContent = 'Add New Event (AJAX)';
    var modal = document.getElementById('eventModal');
    if (modal) modal.classList.add('open');
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
            var modal = document.getElementById('eventModal');
            if (modal) modal.classList.add('open');
        }
    } catch (err) {
        showToast("Failed to fetch event details: " + err.message, false);
    }
}

function closeEventModal() {
    var modal = document.getElementById('eventModal');
    if (modal) modal.classList.remove('open');
}

// Form Submission (Add or Update via Fetch)
var ajaxForm = document.getElementById('ajaxEventForm');
if (ajaxForm) {
    ajaxForm.addEventListener('submit', async function(e) {
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
}

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
if (document.getElementById('eventsTableBody')) {
    loadEvents();
}
