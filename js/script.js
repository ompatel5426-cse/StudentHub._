
const themeBtn = document.getElementById("themeBtn");

// Saved theme check
if (localStorage.getItem("theme") === "dark") {
    document.body.classList.add("dark");
    themeBtn.textContent = "☀️";
}

// Button click
themeBtn.addEventListener("click", function () {

    document.body.classList.toggle("dark");

    if (document.body.classList.contains("dark")) {
        themeBtn.textContent = "☀️";
        localStorage.setItem("theme", "dark");
    } else {
        themeBtn.textContent = "🌙";
        localStorage.setItem("theme", "light");
    }

});

/* ================= NOTIFICATION ================= */

const notification = document.getElementById(
    "studenthubNotification"
);

const closeNotification = document.getElementById(
    "closeStudenthubNotification"
);

if (closeNotification) {

    closeNotification.addEventListener("click", function () {

        notification.style.display = "none";

    });

}


// ================= LOGIN =================

function loginUser(event) {

    event.preventDefault();

    const email =
        document.getElementById("email").value;

    const password =
        document.getElementById("password").value;

    const correctEmail =
        "student@gmail.com";

    const correctPassword =
        "12345";

    if (
        email === correctEmail &&
        password === correctPassword
    ) {

        localStorage.setItem(
            "isLoggedIn",
            "true"
        );

        localStorage.setItem(
            "userEmail",
            email
        );

        alert("Login Successful! 🎉");

        window.location.href =
            "dashboard.html";

    } else {

        alert("Invalid Email or Password ❌");

    }

}


// ================= LOGOUT =================

function logoutUser() {

    localStorage.removeItem("isLoggedIn");

    localStorage.removeItem("userEmail");

    alert("Logged Out Successfully!");

    window.location.href =
        "login.html";

}


// ================= CHECK LOGIN =================

function checkLogin() {

    const isLoggedIn =
        localStorage.getItem("isLoggedIn");

    if (isLoggedIn !== "true") {

        window.location.href =
            "login.html";

    }

}


// ================= COURSES JSON =================

let allCourses = [];

const courseContainer =
    document.getElementById("courseContainer");

if (courseContainer) {

    fetch("data/courses.json")

        .then(response => response.json())

        .then(data => {

            allCourses = data;

            displayCourses(allCourses);

        })

        .catch(error => {

            console.log(
                "Error loading courses:",
                error
            );

            courseContainer.innerHTML =
                "<p>Unable to load courses.</p>";

        });

}


// ================= DISPLAY COURSES =================

function displayCourses(courses) {

    courseContainer.innerHTML = "";

    if (courses.length === 0) {

        courseContainer.innerHTML =
            "<p>No courses found.</p>";

        return;

    }

    courses.forEach(course => {

        courseContainer.innerHTML += `

            <div class="course-card">

                <div class="course-icon">
                    ${course.icon}
                </div>

                <h2>${course.name}</h2>

                <p>
                    <strong>Faculty:</strong>
                    ${course.faculty}
                </p>

                <p>
                    <strong>Duration:</strong>
                    ${course.duration}
                </p>

                <p>
                    <strong>Progress:</strong>
                    ${course.progress}%
                </p>

                <button>
                    View Details
                </button>

            </div>

        `;

    });

}


// ================= SEARCH & FILTER =================

const searchCourse =
    document.getElementById("searchCourse");

const filterCourse =
    document.getElementById("filterCourse");


function applyCourseFilter() {

    const searchValue =
        searchCourse.value.toLowerCase();

    const filterValue =
        filterCourse.value;

    let filteredCourses =
        allCourses.filter(course => {

            const matchesSearch =
                course.name
                    .toLowerCase()
                    .includes(searchValue);


            let matchesFilter = true;


            if (filterValue === "high") {

                matchesFilter =
                    course.progress >= 80;

            }

            else if (filterValue === "low") {

                matchesFilter =
                    course.progress < 80;

            }


            return matchesSearch &&
                   matchesFilter;

        });


    displayCourses(filteredCourses);

}


// Search

if (searchCourse) {

    searchCourse.addEventListener(
        "input",
        applyCourseFilter
    );

}


// Filter

if (filterCourse) {

    filterCourse.addEventListener(
        "change",
        applyCourseFilter
    );

}

/* ================= MODAL ================= */

const modal = document.getElementById(
    "studenthubModal"
);

const openModal = document.getElementById(
    "openStudenthubModal"
);

const closeModal = document.getElementById(
    "closeStudenthubModal"
);


if (openModal) {

    openModal.addEventListener("click", function () {

        modal.classList.add("show");

    });

}


if (closeModal) {

    closeModal.addEventListener("click", function () {

        modal.classList.remove("show");

    });

}


/* Close modal when clicking outside */

if (modal) {

    modal.addEventListener("click", function(event) {

        if (event.target === modal) {

            modal.classList.remove("show");

        }

    });

}


/* ================= FAQ ================= */

const faqQuestions = document.querySelectorAll(
    ".faq-question"
);

faqQuestions.forEach(function(question) {

    question.addEventListener("click", function() {

        const answer = this.nextElementSibling;
        const symbol = this.querySelector("span");

        answer.classList.toggle("show");

        if (answer.classList.contains("show")) {

            symbol.textContent = "−";

        } else {

            symbol.textContent = "+";

        }

    });

});


/* ================= SLIDER ================= */

const slides = document.querySelectorAll(
    ".studenthub-slide"
);

const dots = document.querySelectorAll(
    ".slider-dot"
);

const nextButton = document.getElementById(
    "sliderNext"
);

const previousButton = document.getElementById(
    "sliderPrev"
);

let currentSlide = 0;


function showSlide(index) {

    slides.forEach(function(slide) {

        slide.classList.remove("active");

    });

    dots.forEach(function(dot) {

        dot.classList.remove("active");

    });

    slides[index].classList.add("active");
    dots[index].classList.add("active");

}


if (nextButton) {

    nextButton.addEventListener("click", function() {

        currentSlide++;

        if (currentSlide >= slides.length) {

            currentSlide = 0;

        }

        showSlide(currentSlide);

    });

}


if (previousButton) {

    previousButton.addEventListener("click", function() {

        currentSlide--;

        if (currentSlide < 0) {

            currentSlide = slides.length - 1;

        }

        showSlide(currentSlide);

    });

}


/* Slider dots */

dots.forEach(function(dot, index) {

    dot.addEventListener("click", function() {

        currentSlide = index;

        showSlide(currentSlide);

    });

});