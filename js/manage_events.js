var lastActiveElement = null;

function trapFocus(modal) {
    if (!modal) return;
    var focusable = modal.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
    if (focusable.length === 0) return;
    var first = focusable[0];
    var last = focusable[focusable.length - 1];

    modal.onkeydown = function(e) {
        if (e.key === 'Tab') {
            if (e.shiftKey) {
                if (document.activeElement === first) {
                    last.focus();
                    e.preventDefault();
                }
            } else {
                if (document.activeElement === last) {
                    first.focus();
                    e.preventDefault();
                }
            }
        }
    };
    setTimeout(function() { first.focus(); }, 50);
}

/**
 * StudentHub — Manage Events Scripts (Practical 12)
 * Modal handlers for Create and Edit event records
 */

function openCreateModal() {
    lastActiveElement = document.activeElement; 
    var modal = document.getElementById('createModal');
    if (modal) { modal.classList.add('open'); modal.setAttribute('role', 'dialog'); modal.setAttribute('aria-modal', 'true'); trapFocus(modal); } 
}

function openEditModal(evt) {
    lastActiveElement = document.activeElement;
    document.getElementById('edit_event_id').value = evt.event_id;
    document.getElementById('edit_event_name').value = evt.event_name;
    document.getElementById('edit_category').value = evt.category || 'Workshop';
    document.getElementById('edit_event_date').value = evt.event_date;
    document.getElementById('edit_venue').value = evt.venue || '';
    document.getElementById('edit_status').value = evt.status || 'open';
    document.getElementById('edit_description').value = evt.description || '';
    var modal = document.getElementById('editModal');
    if (modal) { modal.classList.add('open'); modal.setAttribute('role', 'dialog'); modal.setAttribute('aria-modal', 'true'); trapFocus(modal); }
}

function closeModal(id) { 
    var modal = document.getElementById(id);
    if (modal) {
        modal.classList.remove('open');
        if (lastActiveElement && typeof lastActiveElement.focus === 'function') {
            lastActiveElement.focus();
        }
    }
}

// Close modal on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') { 
        closeModal('createModal'); 
        closeModal('editModal'); 
    }
});
