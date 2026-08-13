const themeBtn = document.getElementById("themeBtn");

// Page load hote hi saved theme apply karo
if(localStorage.getItem("theme") === "dark")
    {
    document.body.classList.add("dark");
    themeBtn.textContent = "☀️";
}

// Button click
themeBtn.addEventListener("click", () => 
    {

    document.body.classList.toggle("dark");

    if(document.body.classList.contains("dark"))
        {
        localStorage.setItem("theme", "dark");
        themeBtn.textContent = "☀️";
    }else
        {
        localStorage.setItem("theme", "light");
        themeBtn.textContent = "🌙";
    }

});

// ===== Notification Banner =====

const closeBanner = document.getElementById("closeBanner");

if(closeBanner)
    {
    closeBanner.addEventListener("click", () => 
        {
        document.getElementById("banner").style.display = "none";
        });
}

// ================= LOGIN =================

function loginUser(event)
{
    event.preventDefault();

    const email = document.getElementById("email").value;
    const password = document.getElementById("password").value;

    // Demo login details
    const correctEmail = "student@gmail.com";
    const correctPassword = "12345";

    if (email === correctEmail && password === correctPassword) 
        {
        localStorage.setItem("isLoggedIn", "true");
        localStorage.setItem("userEmail", email);

        alert("Login Successful! 🎉");

        window.location.href = "dashboard.html";
    } 
    else 
        {
        alert("Invalid Email or Password ❌");
    }
}


// ================= LOGOUT =================

function logoutUser() 
{
    localStorage.removeItem("isLoggedIn");
    localStorage.removeItem("userEmail");

    alert("Logged Out Successfully!");

    window.location.href = "login.html";
}


// ================= CHECK LOGIN =================

function checkLogin() 
{
    const isLoggedIn = localStorage.getItem("isLoggedIn");

    if (isLoggedIn !== "true") 
    {
        window.location.href = "login.html";
    }
}

function logoutUser() {
    localStorage.removeItem("isLoggedIn");
    localStorage.removeItem("userEmail");

    alert("Logged Out Successfully!");

    window.location.href = "login.html";
}