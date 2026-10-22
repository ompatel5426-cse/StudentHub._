/**
 * StudentHub — Dynamic Navigation Script
 * Automatically adapts navbar between Guest (1st-time user) and Authenticated states
 */
(function() {
    const nav = document.getElementById('mainNav') || document.querySelector('header nav');
    if (!nav) return;

    // Detect if current path is inside /php/ or root
    const isPhpDir = window.location.pathname.indexOf('/php/') !== -1;
    const prefix = isPhpDir ? '' : 'php/';
    const rootPrefix = isPhpDir ? '../' : '';

    fetch(prefix + 'session_status.php')
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data && data.logged_in) {
                // If user is authenticated, adapt navigation
                if (data.role === 'admin') {
                    nav.innerHTML = `
                        <a href="${rootPrefix}index.html">Home</a>
                        <a href="${rootPrefix}about.html">About</a>
                        <a href="${rootPrefix}courses.html">Courses</a>
                        <a href="${rootPrefix}events.html">Events</a>
                        <a href="${prefix}admin_dashboard.php" style="color:#6C63FF; font-weight:700;">Admin Console 🛡️</a>
                        <a href="${prefix}logout.php" style="color:#ef4444; font-weight:600;">Logout</a>
                    `;
                } else {
                    nav.innerHTML = `
                        <a href="${rootPrefix}index.html">Home</a>
                        <a href="${rootPrefix}about.html">About</a>
                        <a href="${rootPrefix}courses.html">Courses</a>
                        <a href="${rootPrefix}events.html">Events</a>
                        <a href="${prefix}dashboard.php" style="color:#6C63FF; font-weight:700;">My Dashboard 🎓</a>
                        <a href="${prefix}logout.php" style="color:#ef4444; font-weight:600;">Logout</a>
                    `;
                }
            }
            // If not logged in (guest / 1st time user), the default static HTML remains intact!
        })
        .catch(function(err) {
            // Keep default guest navbar on network failure
        });
})();
