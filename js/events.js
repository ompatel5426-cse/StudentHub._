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
       // await new Promise(resolve => setTimeout(resolve, 3000));   // Simulate a delay for testing purposes


        // Practical 13: Fetch directly from PHP JSON API (MySQL database)
        try {
            const apiRes = await fetch("php/api/events.php");
            if (apiRes.ok) {
                const json = await apiRes.json();
                if (json.success && Array.isArray(json.data)) {
                    events = json.data.map(item => ({
                        id: item.event_id,
                        title: item.event_name,
                        date: item.event_date,
                        category: item.category || 'General',
                        location: item.venue || 'Campus',
                        poster_url: item.poster_url || null,
                        description: item.description || ''
                    }));
                } else {
                    events = [];
                }
            } else {
                events = [];
            }
        } catch (e) {
            console.warn("API fetch error:", e);
            events = [];
        }

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

        if (events.length === 0) {
            container.innerHTML = `
                <div class="no-results" style="text-align:center; padding:55px 20px;">
                    <div style="font-size:52px; margin-bottom:12px;">📅</div>
                    <h3 style="font-size:24px; color:#0f172a; margin-bottom:10px;">No New Events Added</h3>
                    <div style="background:#fef3c7; color:#92400e; border:1px solid #fde68a; padding:6px 18px; border-radius:20px; font-size:13px; font-weight:700; display:inline-block; margin-bottom:14px;">
                        Not new event add / Waiting for Admin
                    </div>
                    <p style="color:#64748b; font-size:15px; max-width:520px; margin:0 auto; line-height:1.6;">
                        There are currently no new events, competitions, or workshops added by the administrator. Once the college schedules new activities, they will appear here.
                    </p>
                </div>
            `;
        } else {
            container.innerHTML = `
                <div class="no-results" style="text-align:center; padding:45px 20px;">
                    <div style="font-size:42px; margin-bottom:10px;">🔍</div>
                    <h3 style="font-size:20px; color:#0f172a; margin-bottom:8px;">No Matching Events Found</h3>
                    <p style="color:#64748b; font-size:14px;">Try searching with different keywords or reset your category filter.</p>
                </div>
            `;
        }

        renderPagination();

        return;
    }


    pageEvents.forEach(event => {

        const card =
            document.createElement("div");


        card.className = "event-card";


        card.innerHTML = `
            ${event.poster_url ? `<img src="${event.poster_url}" alt="${event.title}" style="width:100%; height:150px; object-fit:cover; border-radius:10px; margin-bottom:12px; display:block;">` : ''}
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