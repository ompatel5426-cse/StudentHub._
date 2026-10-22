/**
 * StudentHub — Manage Students Scripts (Practical 11)
 * Modal handlers for Create and Edit student records
 */

function openCreateModal() {
    var modal = document.getElementById('createModal');
    if (modal) modal.classList.add('open');
}

function openEditModal(student) {
    document.getElementById('edit_student_id').value = student.student_id;
    document.getElementById('edit_name').value = student.name;
    document.getElementById('edit_email').value = student.email;
    document.getElementById('edit_mobile').value = student.mobile || '';
    document.getElementById('edit_course').value = student.course || 'BCA';
    document.getElementById('edit_year').value = student.year || '1';
    document.getElementById('edit_gender').value = student.gender || 'Male';
    document.getElementById('edit_status').value = student.status || 'active';
    var modal = document.getElementById('editModal');
    if (modal) modal.classList.add('open');
}

function closeModal(modalId) {
    var modal = document.getElementById(modalId);
    if (modal) modal.classList.remove('open');
}

// Close modal on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal('createModal');
        closeModal('editModal');
    }
});
