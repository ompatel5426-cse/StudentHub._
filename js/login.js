/**
 * StudentHub — Student Login Script
 * Handles URL status message banners and CSRF token retrieval
 */
(function() {
    var params = new URLSearchParams(window.location.search);
    var alertBox = document.getElementById("loginAlert");

    if (alertBox && params.has("msg")) {
        var msg = params.get("msg");
        alertBox.style.display = "block";

        if (msg === "logged_out") {
            alertBox.style.background = "#dcfce7";
            alertBox.style.color = "#15803d";
            alertBox.style.border = "1px solid #bbf7d0";
            alertBox.innerHTML = "✓ You have been logged out successfully.";
        } else if (msg === "timeout") {
            alertBox.style.background = "#fee2e2";
            alertBox.style.color = "#b91c1c";
            alertBox.style.border = "1px solid #fecaca";
            alertBox.innerHTML = "⚠ Session expired due to inactivity. Please sign in again.";
        } else if (msg === "login_required") {
            alertBox.style.background = "#fee2e2";
            alertBox.style.color = "#b91c1c";
            alertBox.style.border = "1px solid #fecaca";
            alertBox.innerHTML = "⚠ Please sign in to access your dashboard.";
        }
    }

    var csrfField = document.getElementById('login_csrf_token');
    if (csrfField) {
        fetch('php/csrf.php')
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data && data.csrf_token) {
                    csrfField.value = data.csrf_token;
                }
            })
            .catch(function(err) {
                console.warn('CSRF token fetch skipped:', err);
            });
    }
})();
