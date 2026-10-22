/**
 * StudentHub — Administrator Login Script
 * Handles URL message banner display and CSRF token retrieval
 */
(function() {
    var params = new URLSearchParams(window.location.search);
    var alertBox = document.getElementById("loginAlert");

    if (alertBox && params.has("msg")) {
        var msg = params.get("msg");
        alertBox.style.display = "block";

        if (msg === "registered") {
            alertBox.style.background = "#dcfce7";
            alertBox.style.color = "#15803d";
            alertBox.style.border = "1px solid #bbf7d0";
            alertBox.innerHTML = "✓ Administrator account created successfully! Please sign in.";
        } else if (msg === "logged_out") {
            alertBox.style.background = "#dcfce7";
            alertBox.style.color = "#15803d";
            alertBox.style.border = "1px solid #bbf7d0";
            alertBox.innerHTML = "✓ Logged out from Administrator Console.";
        } else if (msg === "admin_required") {
            alertBox.style.background = "#fee2e2";
            alertBox.style.color = "#b91c1c";
            alertBox.style.border = "1px solid #fecaca";
            alertBox.innerHTML = "⚠ Administrator login required to access that resource.";
        } else if (msg === "student_denied") {
            alertBox.style.background = "#fee2e2";
            alertBox.style.color = "#b91c1c";
            alertBox.style.border = "1px solid #fecaca";
            alertBox.innerHTML = "⚠ Access Denied: That account is a Student. Please use the Student Login.";
        }
    }

    var csrfField = document.getElementById('admin_csrf_token');
    if (csrfField) {
        var csrfUrl = window.location.pathname.indexOf('/php/') !== -1 ? 'csrf.php' : 'php/csrf.php';
        fetch(csrfUrl)
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data && data.csrf_token) {
                    csrfField.value = data.csrf_token;
                }
            })
            .catch(function(err) {});
    }
})();
