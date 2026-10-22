/**
 * StudentHub — Administrator Registration Script
 * Handles CSRF token retrieval and client-side password verification
 */
(function() {
    var csrfField = document.getElementById('admin_reg_csrf');
    if (csrfField) {
        fetch('php/csrf.php')
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data && data.csrf_token) {
                    csrfField.value = data.csrf_token;
                }
            })
            .catch(function(err) {});
    }

    var form = document.getElementById('adminRegForm');
    var alertBox = document.getElementById('regAlert');

    if (form) {
        form.addEventListener('submit', function(e) {
            var pass = document.getElementById('admin_pass').value;
            var cpass = document.getElementById('admin_cpass').value;

            if (pass !== cpass) {
                e.preventDefault();
                if (alertBox) {
                    alertBox.style.display = 'block';
                    alertBox.textContent = 'Passwords do not match. Please re-enter.';
                }
                return false;
            }
        });
    }
})();
