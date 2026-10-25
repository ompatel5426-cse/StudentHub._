/**
 * StudentHub — Manage Students Scripts (Practical 11)
 * Modal handlers for Create, Edit, and Student Marks
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

function openMarksModal(studentId, studentName) {
    var modal = document.getElementById('marksModal');
    var title = document.getElementById('marksModalTitle');
    var subtitle = document.getElementById('marksModalSubtitle');
    var hiddenId = document.getElementById('mark_student_id');
    var loading = document.getElementById('marksLoading');
    var tableContainer = document.getElementById('marksTableContainer');
    var tableBody = document.getElementById('marksTableBody');
    var noMarks = document.getElementById('noMarksNotice');

    hiddenId.value = studentId;
    title.textContent = "Marks for " + studentName + " (#" + studentId + ")";
    subtitle.textContent = "View existing marks and enter subject scores.";
    loading.style.display = 'block';
    tableContainer.style.display = 'none';
    noMarks.style.display = 'none';
    tableBody.innerHTML = '';

    modal.classList.add('open');

    // Fetch CSRF token from page
    var csrfInput = document.querySelector('input[name=csrf_token]');
    var csrfVal = csrfInput ? csrfInput.value : '';

    // Fetch marks via AJAX
    fetch('manage_students.php?action=get_marks&student_id=' + studentId)
        .then(function(res) { return res.json(); })
        .then(function(data) {
            loading.style.display = 'none';
            if (data.success && data.marks && data.marks.length > 0) {
                tableContainer.style.display = 'block';
                noMarks.style.display = 'none';
                data.marks.forEach(function(m) {
                    var tr = document.createElement('tr');
                    tr.innerHTML = '<td><strong>' + escapeHtml(m.semester) + '</strong></td>' +
                        '<td>' + escapeHtml(m.subject_name) + '</td>' +
                        '<td>' + escapeHtml(m.subject_code || '—') + '</td>' +
                        '<td>' + m.internal_marks + ' / 40</td>' +
                        '<td>' + m.external_marks + ' / 60</td>' +
                        '<td><strong style="color:#0f172a;">' + m.total_marks + ' / 100</strong></td>' +
                        '<td><span class="badge badge-active">' + escapeHtml(m.grade) + '</span></td>' +
                        '<td>' +
                            '<form method="POST" action="manage_students.php" style="display:inline;" onsubmit="return confirm(\'Delete this mark entry for ' + escapeHtml(m.subject_name) + '?\');">' +
                                '<input type="hidden" name="csrf_token" value="' + csrfVal + '">' +
                                '<input type="hidden" name="action" value="delete_mark">' +
                                '<input type="hidden" name="mark_id" value="' + m.mark_id + '">' +
                                '<input type="hidden" name="student_id" value="' + studentId + '">' +
                                '<button type="submit" class="btn-action btn-hard" style="padding:4px 8px; font-size:11px;" title="Delete mark">🗑 Delete</button>' +
                            '</form>' +
                        '</td>';
                    tableBody.appendChild(tr);
                });
            } else {
                tableContainer.style.display = 'none';
                noMarks.style.display = 'block';
            }
        })
        .catch(function(err) {
            loading.style.display = 'none';
            noMarks.style.display = 'block';
            noMarks.textContent = 'Failed to load marks. Please try again.';
            console.error(err);
        });
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
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
        closeModal('marksModal');
    }
});
