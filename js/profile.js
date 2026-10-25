/**
 * StudentHub — Profile Page Scripts
 * Handles Edit Profile Modal interactions
 */

function openEditProfileModal() {
    var modal = document.getElementById('editProfileModal');
    if (modal) modal.classList.add('active');
}

function closeEditProfileModal() {
    var modal = document.getElementById('editProfileModal');
    if (modal) modal.classList.remove('active');
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeEditProfileModal();
    }
});

// Close when clicking modal backdrop
window.addEventListener('click', function(e) {
    var modal = document.getElementById('editProfileModal');
    if (e.target === modal) {
        closeEditProfileModal();
    }
});
