const themeBtn = document.getElementById("themeBtn");

// Page load hote hi saved theme apply karo
if(localStorage.getItem("theme") === "dark"){
    document.body.classList.add("dark");
    themeBtn.textContent = "☀️";
}

// Button click
themeBtn.addEventListener("click", () => {

    document.body.classList.toggle("dark");

    if(document.body.classList.contains("dark")){
        localStorage.setItem("theme", "dark");
        themeBtn.textContent = "☀️";
    }else{
        localStorage.setItem("theme", "light");
        themeBtn.textContent = "🌙";
    }

});

// ===== Notification Banner =====

const closeBanner = document.getElementById("closeBanner");

if(closeBanner){
    closeBanner.addEventListener("click", () => {
        document.getElementById("banner").style.display = "none";
    });
}