
// ================= NOTIFICATION =================

const closeBanner =
    document.getElementById("closeBanner");

if (closeBanner) {

    closeBanner.addEventListener("click", () => {

        document.getElementById("banner").style.display =
            "none";

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