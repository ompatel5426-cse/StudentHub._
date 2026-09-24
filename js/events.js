let events = [];

let filteredEvents = [];

let currentPage = 1;

const recordsPerPage = 6;


// =========================
// LOAD JSON DATA
// =========================

async function loadEvents() {

    const loading = document.getElementById("loading");

    const error = document.getElementById("error");

    try {

        loading.style.display = "block";

        error.textContent = "";


        const response = await fetch("data/events.json");


        if (!response.ok) {

            throw new Error(
                "Unable to load events.json"
            );

        }


        events = await response.json();


        filteredEvents = [...events];


        loading.style.display = "none";


        renderEvents();


    } catch (err) {

        loading.style.display = "none";

        error.textContent =
            "❌ Failed to load events. Please try again.";

        console.error(err);

    }

}


// =========================
// DISPLAY EVENTS
// =========================

function renderEvents() {

    const container =
        document.getElementById("eventContainer");


    container.innerHTML = "";


    const start =
        (currentPage - 1) * recordsPerPage;


    const end =
        start + recordsPerPage;


    const pageEvents =
        filteredEvents.slice(start, end);


    if (pageEvents.length === 0) {

        container.innerHTML = `
            <div class="no-results">
                <h3>No events found</h3>
                <p>Try another search or category.</p>
            </div>
        `;

        renderPagination();

        return;
    }


    pageEvents.forEach(event => {

        const card =
            document.createElement("div");


        card.className = "event-card";


        card.innerHTML = `

            <span class="event-category">
                ${event.category}
            </span>

            <h3>
                ${event.title}
            </h3>

            <p>
                📅 <strong>Date:</strong>
                ${event.date}
            </p>

            <p>
                📍 <strong>Location:</strong>
                ${event.location}
            </p>

        `;


        container.appendChild(card);

    });


    renderPagination();

}


// =========================
// SEARCH
// =========================

document
    .getElementById("searchInput")
    .addEventListener("input", function () {

        const searchText =
            this.value.toLowerCase();


        filteredEvents =
            events.filter(event =>

                event.title
                    .toLowerCase()
                    .includes(searchText)

            );


        currentPage = 1;


        applyFiltersAndSort();

    });


// =========================
// CATEGORY FILTER
// =========================

document
    .getElementById("categoryFilter")
    .addEventListener("change", function () {

        currentPage = 1;

        applyFiltersAndSort();

    });


// =========================
// SORT
// =========================

document
    .getElementById("sortOption")
    .addEventListener("change", function () {

        applyFiltersAndSort();

    });


// =========================
// FILTER + SORT
// =========================

function applyFiltersAndSort() {

    const searchText =
        document
            .getElementById("searchInput")
            .value
            .toLowerCase();


    const category =
        document
            .getElementById("categoryFilter")
            .value;


    const sort =
        document
            .getElementById("sortOption")
            .value;


    // Search

    filteredEvents = events.filter(event => {

        const matchesSearch =
            event.title
                .toLowerCase()
                .includes(searchText);


        const matchesCategory =
            category === "all" ||
            event.category === category;


        return matchesSearch && matchesCategory;

    });


    // Sort by title

    if (sort === "title") {

        filteredEvents.sort((a, b) =>
            a.title.localeCompare(b.title)
        );

    }


    // Sort by date

    if (sort === "date") {

        filteredEvents.sort((a, b) =>
            new Date(a.date) - new Date(b.date)
        );

    }


    renderEvents();

}


// =========================
// PAGINATION
// =========================

function renderPagination() {

    const pagination =
        document.getElementById("pagination");


    pagination.innerHTML = "";


    const totalPages =
        Math.ceil(
            filteredEvents.length /
            recordsPerPage
        );


    if (totalPages <= 1) {
        return;
    }


    // Previous button

    const previous =
        document.createElement("button");


    previous.textContent = "← Previous";


    previous.disabled =
        currentPage === 1;


    previous.onclick = function () {

        currentPage--;

        renderEvents();

    };


    pagination.appendChild(previous);


    // Page buttons

    for (
        let i = 1;
        i <= totalPages;
        i++
    ) {

        const button =
            document.createElement("button");


        button.textContent = i;


        if (i === currentPage) {

            button.classList.add("active");

        }


        button.onclick = function () {

            currentPage = i;

            renderEvents();

        };


        pagination.appendChild(button);

    }


    // Next button

    const next =
        document.createElement("button");


    next.textContent = "Next →";


    next.disabled =
        currentPage === totalPages;


    next.onclick = function () {

        currentPage++;

        renderEvents();

    };


    pagination.appendChild(next);

}


// =========================
// START APPLICATION
// =========================

loadEvents();