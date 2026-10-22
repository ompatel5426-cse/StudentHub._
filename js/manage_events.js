/**
 * StudentHub — Manage Events Scripts (Practical 12)
 * Modal handlers for Create and Edit event records
 */

function openCreateModal() { 
    var modal = document.getElementById('createModal');
    if (modal) modal.classList.add('open'); 
}

function openEditModal(evt) {
    document.getElementById('edit_event_id').value = evt.event_id;
    document.getElementById('edit_event_name').value = evt.event_name;
    document.getElementById('edit_category').value = evt.category || 'Workshop';
    document.getElementById('edit_event_date').value = evt.event_date;
    document.getElementById('edit_venue').value = evt.venue || '';
    document.getElementById('edit_status').value = evt.status || 'open';
    document.getElementById('edit_description').value = evt.description || '';
    var modal = document.getElementById('editModal');
    if (modal) modal.classList.add('open');
}

function closeModal(id) { 
    var modal = document.getElementById(id);
    if (modal) modal.classList.remove('open'); 
}

// Close modal on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') { 
        closeModal('createModal'); 
        closeModal('editModal'); 
    }
});
